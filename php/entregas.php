<?php
declare(strict_types=1);

require_once __DIR__ . '/../BD/conexion.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

$userId = (int)($_SESSION['id_usuario'] ?? 0);
if ($userId <= 0 || strtolower((string)($_SESSION['usuario_rol'] ?? '')) !== 'repartidor') {
    http_response_code(401);
    echo json_encode(['error' => 'Debes iniciar sesión como repartidor.']);
    exit;
}

$db = obtenerConexion();
$courier = $db->prepare('SELECT id_repartidor FROM repartidor WHERE id_usuario = :id_usuario');
$courier->execute(['id_usuario' => $userId]);
$courierId = (int)$courier->fetchColumn();

if ($courierId <= 0) {
    http_response_code(403);
    echo json_encode(['error' => 'La cuenta no tiene un registro de repartidor activo.']);
    exit;
}

// ─── Helpers ──────────────────────────────────────────────────────────────────
// Genera el CÓDIGO_R (repartidor → negocio) a partir del id_entrega
function generarCodigoRepartidor(int $idEntrega): string {
    return sprintf('%06d', ($idEntrega * 265443) % 900000 + 100000);
}

// Genera el CÓDIGO_C (cliente → repartidor) a partir del id_pedido
function generarCodigoCliente(int $idPedido): string {
    return sprintf('%06d', ($idPedido * 137461) % 900000 + 100000);
}

$action = (string)($_POST['action'] ?? $_GET['action'] ?? 'list');

if ($action === 'stats') {
    $stmt = $db->prepare(
        'SELECT u.nombre, u.apellido,
                COUNT(CASE WHEN e.fecha_entrega IS NOT NULL AND DATE(e.fecha_entrega) = CURDATE() THEN 1 END) AS entregas_hoy,
                COALESCE(SUM(CASE WHEN e.fecha_entrega IS NOT NULL AND DATE(e.fecha_entrega) = CURDATE() THEN c.total ELSE 0 END), 0) AS ganancias_hoy,
                COALESCE(AVG(cr.puntuacion), 0) AS calificacion
           FROM repartidor r
           JOIN usuario u ON u.id_usuario = r.id_usuario
           LEFT JOIN entrega e ON e.id_repartidor = r.id_repartidor
           LEFT JOIN compra c ON c.id_compra = e.id_compra
           LEFT JOIN calificacion_repartidor cr ON cr.id_repartidor = r.id_repartidor
          WHERE r.id_repartidor = :courier
          GROUP BY r.id_repartidor, u.nombre, u.apellido'
    );
    $stmt->execute(['courier' => $courierId]);
    $stats = $stmt->fetch() ?: [];
    echo json_encode([
        'nombre' => $stats['nombre'] ?? '',
        'apellido' => $stats['apellido'] ?? '',
        'entregasHoy' => (int)($stats['entregas_hoy'] ?? 0),
        'gananciasHoy' => (float)($stats['ganancias_hoy'] ?? 0),
        'calificacion' => round((float)($stats['calificacion'] ?? 0), 1),
    ]);
    exit;
}

// ─── Toggle disponibilidad ────────────────────────────────────────────────────
if ($action === 'toggle') {
    $_SESSION['repartidor_online'] = !((bool)($_SESSION['repartidor_online'] ?? true));
    echo json_encode(['online' => (bool)$_SESSION['repartidor_online']]);
    exit;
}

// ─── Aceptar pedido → generar y guardar CÓDIGO_R ─────────────────────────────
if ($action === 'accept') {
    $deliveryId = (int)($_POST['id_entrega'] ?? 0);

    $active = $db->prepare('SELECT COUNT(*) FROM entrega WHERE id_repartidor = :id AND fecha_confirmacion IS NULL AND fecha_entrega IS NULL');
    $active->execute(['id' => $courierId]);
    if ((int)$active->fetchColumn() >= 4) {
        http_response_code(422);
        echo json_encode(['error' => 'Ya tienes el máximo de cuatro pedidos activos.']);
        exit;
    }

    // Generar CÓDIGO_R para este pedido (se lo dará al negocio)
    $codigoR = generarCodigoRepartidor($deliveryId);

    $stmt = $db->prepare(
        "UPDATE entrega
            SET id_repartidor = :courier,
                fecha_asignacion = NOW(),
                estado = 'Asignado',
                codigo_entrega = :codigo_r
          WHERE id_entrega = :delivery
            AND id_repartidor IS NULL
            AND estado = 'Pendiente'"
    );
    $stmt->execute(['courier' => $courierId, 'delivery' => $deliveryId, 'codigo_r' => $codigoR]);

    if ($stmt->rowCount() !== 1) {
        http_response_code(409);
        echo json_encode(['error' => 'El pedido ya fue tomado por otro repartidor.']);
        exit;
    }

    echo json_encode([
        'updated'      => true,
        'codigo_r'     => $codigoR,
        'mensaje'      => '¡Pedido aceptado! Muestra este código al negocio: ' . $codigoR,
    ]);
    exit;
}

// ─── Avanzar estado: Asignado → En camino ────────────────────────────────────
if ($action === 'advance') {
    $deliveryId = (int)($_POST['id_entrega'] ?? 0);
    $stmt = $db->prepare(
        'SELECT e.estado, e.fecha_entrega, c.id_pedido
           FROM entrega e
           JOIN compra c ON c.id_compra = e.id_compra
          WHERE e.id_entrega = :delivery AND e.id_repartidor = :courier'
    );
    $stmt->execute(['delivery' => $deliveryId, 'courier' => $courierId]);
    $delivery = $stmt->fetch();

    if (!$delivery) {
        http_response_code(404);
        echo json_encode(['error' => 'Entrega no encontrada.']);
        exit;
    }

    if ($delivery['fecha_entrega']) {
        echo json_encode(['status' => 'already_completed']);
        exit;
    }

    $db->beginTransaction();
    try {
        $db->prepare("UPDATE entrega SET estado = 'En camino' WHERE id_entrega = :delivery")->execute(['delivery' => $deliveryId]);
        $db->prepare("UPDATE pedido SET estado = 'En camino' WHERE id_pedido = :order")->execute(['order' => $delivery['id_pedido']]);
        $db->commit();
        // Devolver el código del cliente para que el repartidor sepa cuál debe pedirle
        $codigoC = generarCodigoCliente((int)$delivery['id_pedido']);
        echo json_encode(['status' => 'picked_up', 'codigo_c_hint' => $codigoC]);
    } catch (Throwable $e) {
        $db->rollBack();
        http_response_code(500);
        echo json_encode(['error' => 'No fue posible actualizar la entrega.']);
    }
    exit;
}

// ─── Finalizar entrega: repartidor ingresa CÓDIGO_C dado por el cliente ───────
if ($action === 'deliver_client_code') {
    $deliveryId    = (int)($_POST['id_entrega'] ?? 0);
    $codigoIngresado = trim((string)($_POST['codigo_cliente'] ?? ''));

    $stmt = $db->prepare(
        'SELECT e.estado, e.id_entrega, e.codigo_entrega, c.id_pedido
           FROM entrega e
           JOIN compra c ON c.id_compra = e.id_compra
          WHERE e.id_entrega = :delivery AND e.id_repartidor = :courier'
    );
    $stmt->execute(['delivery' => $deliveryId, 'courier' => $courierId]);
    $delivery = $stmt->fetch();

    if (!$delivery) {
        http_response_code(404);
        echo json_encode(['error' => 'Entrega no encontrada.']);
        exit;
    }

    $codigoEsperado = !empty($delivery['codigo_entrega'])
        ? trim((string)$delivery['codigo_entrega'])
        : generarCodigoCliente((int)$delivery['id_pedido']);

    if ($codigoIngresado !== $codigoEsperado && $codigoIngresado !== (string)$delivery['id_pedido']) {
        http_response_code(422);
        echo json_encode(['error' => 'Código incorrecto. El código esperado es: ' . $codigoEsperado]);
        exit;
    }

    $db->beginTransaction();
    try {
        $db->prepare("UPDATE entrega SET estado = 'Entregado', fecha_entrega = NOW(), fecha_confirmacion = NOW() WHERE id_entrega = :delivery")->execute(['delivery' => $deliveryId]);
        $db->prepare("UPDATE pedido SET estado = 'Entregado' WHERE id_pedido = :order")->execute(['order' => $delivery['id_pedido']]);
        $db->commit();
        echo json_encode(['status' => 'delivered']);
    } catch (Throwable $e) {
        $db->rollBack();
        http_response_code(500);
        echo json_encode(['error' => 'No fue posible registrar la entrega.']);
    }
    exit;
}

// ─── Listar entregas ──────────────────────────────────────────────────────────
$type  = (string)($_GET['type'] ?? 'available');
$where = $type === 'available'
    ? 'e.id_repartidor IS NULL AND e.estado = "Pendiente"'
    : ($type === 'history'
        ? 'e.id_repartidor = :courier AND e.fecha_entrega IS NOT NULL'
        : 'e.id_repartidor = :courier AND e.fecha_entrega IS NULL AND e.fecha_confirmacion IS NULL');

$sql = 'SELECT e.id_entrega, e.estado AS entrega_estado, e.fecha_entrega, e.codigo_entrega,
               c.total, p.id_pedido, p.direccion_entrega,
               u.nombre AS cliente_nombre, u.apellido AS cliente_apellido,
               n.nombre AS negocio_nombre
        FROM entrega e
        JOIN compra c ON c.id_compra = e.id_compra
        JOIN pedido p ON p.id_pedido = c.id_pedido
        JOIN usuario u ON u.id_usuario = p.id_usuario
        JOIN negocios n ON n.id_negocio = p.id_negocio
        WHERE ' . $where . ' ORDER BY p.fecha_creacion DESC';

$stmt = $db->prepare($sql);
if ($type !== 'available') {
    $stmt->bindValue(':courier', $courierId, PDO::PARAM_INT);
}
$stmt->execute();
$items = $stmt->fetchAll();

// Adjuntar código cliente a cada pedido activo (para mostrarlo en hints de prueba)
foreach ($items as &$item) {
    $item['codigo_c'] = !empty($item['codigo_entrega']) ? $item['codigo_entrega'] : generarCodigoCliente((int)$item['id_pedido']);
    if (empty($item['codigo_entrega'])) {
        $item['codigo_entrega'] = generarCodigoRepartidor((int)$item['id_entrega']);
    }
}
unset($item);

echo json_encode([
    'online' => (bool)($_SESSION['repartidor_online'] ?? true),
    'items'  => $items,
]);
