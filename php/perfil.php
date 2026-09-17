<?php
require_once __DIR__ . '/../BD/conexion.php';
require_once __DIR__ . '/../funciones/funciones.php';
session_start();

$idUsuario = $_SESSION['id_usuario'] ?? null;
if (!$idUsuario || strtolower((string) ($_SESSION['usuario_rol'] ?? '')) !== 'repartidor') {
    header('Location: ../index.html?error=1');
    exit;
}

$conn = obtenerConexion();
$errores = [];
$tokenCsrf = $_SESSION['perfil_csrf'] ?? bin2hex(random_bytes(32));
$_SESSION['perfil_csrf'] = $tokenCsrf;
$stmt = $conn->prepare('SELECT nombre, apellido, email FROM usuario WHERE id_usuario = ?');
$stmt->execute([$idUsuario]);
$usuarioActual = $stmt->fetch();

if (!$usuarioActual) {
    session_destroy();
    header('Location: ../index.html?error=1');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tokenRecibido = (string) ($_POST['csrf_token'] ?? '');
    if (!hash_equals($tokenCsrf, $tokenRecibido)) {
        http_response_code(403);
        exit('Solicitud no válida.');
    }

    $nombre = trim($_POST['nombre'] ?? '');
    $apellido = trim($_POST['apellido'] ?? '');
    $vehiculo = trim($_POST['vehiculo'] ?? '');

    if ($nombre === '' || $apellido === '' || mb_strlen($nombre) > 100 || mb_strlen($apellido) > 100) {
        $errores[] = 'El nombre y el apellido son obligatorios.';
    }
    $vehiculosPermitidos = ['Moto', 'Bicicleta', 'Carro', 'Monopatín'];
    if (!in_array($vehiculo, $vehiculosPermitidos, true)) {
        $errores[] = 'El vehículo es obligatorio.';
    }

    if (!$errores) {
        $conn->beginTransaction();
        try {
            $stmt = $conn->prepare(
                'SELECT id_repartidor FROM repartidor WHERE id_usuario = ?'
            );
            $stmt->execute([$idUsuario]);
            if (!$stmt->fetch()) {
                throw new RuntimeException('No existe un perfil de repartidor asociado.');
            }

            $stmt = $conn->prepare(
                'UPDATE usuario SET nombre = ?, apellido = ? WHERE id_usuario = ?'
            );
            $stmt->execute([$nombre, $apellido, $idUsuario]);

            $stmt = $conn->prepare(
                'UPDATE repartidor SET nombre = ?, apellido = ?, vehiculo = ? WHERE id_usuario = ?'
            );
            $stmt->execute([$nombre, $apellido, $vehiculo, $idUsuario]);

            $conn->commit();
            $_SESSION['usuario_nombre'] = $nombre;
            header('Location: perfil.php?mensaje=actualizado');
            exit;
        } catch (PDOException | RuntimeException $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            $errores[] = 'No se pudo actualizar el perfil. Intenta de nuevo.';
        }
    }
}

$stmt = $conn->prepare(
    'SELECT u.nombre, u.apellido, u.email, r.vehiculo, r.foto_url,
            r.licencia_conduccion_url, r.soat_url, r.tarjeta_propiedad_url
     FROM usuario u
     LEFT JOIN repartidor r ON r.id_usuario = u.id_usuario
     WHERE u.id_usuario = ?'
);
$stmt->execute([$idUsuario]);
$perfil = $stmt->fetch();

if (!$perfil) {
    session_destroy();
    header('Location: ../index.html?error=1');
    exit;
}

$nombreCompleto = trim($perfil['nombre'] . ' ' . $perfil['apellido']);
$iniciales = strtoupper(substr($perfil['nombre'], 0, 1) . substr($perfil['apellido'], 0, 1));
$fotoUrl = rutaDocumento($perfil['foto_url'] ?? '');
$vehiculo = $perfil['vehiculo'] ?: 'No registrado';
$mensaje = isset($_GET['mensaje']) ? 'Perfil actualizado correctamente.' : '';
$alertasHtml = '';
if ($mensaje !== '') {
    $alertasHtml .= '<p class="profile-alert profile-alert--success">' .
        htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8') . '</p>';
}
foreach ($errores as $error) {
    $alertasHtml .= '<p class="profile-alert profile-alert--error">' .
        htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . '</p>';
}

function rutaDocumento(?string $ruta): string
{
    $ruta = trim((string) $ruta);
    if ($ruta === '' || preg_match('/[\x00-\x1F\x7F]/', $ruta)) {
        return '';
    }
    if (preg_match('/^https?:\/\//i', $ruta) || preg_match('/^\/(?!\/)/', $ruta)) {
        return $ruta;
    }
    if (preg_match('/^[a-z][a-z0-9+.-]*:/i', $ruta) || str_starts_with($ruta, '//')) {
        return '';
    }
    return '../' . ltrim($ruta, './');
}

$documentos = [
    'Licencia de conducción' => rutaDocumento($perfil['licencia_conduccion_url']),
    'SOAT' => rutaDocumento($perfil['soat_url']),
    'Tarjeta de propiedad' => rutaDocumento($perfil['tarjeta_propiedad_url']),
];
$documentosHtml = '';
foreach ($documentos as $nombreDocumento => $rutaDocumentoActual) {
    if ($rutaDocumentoActual === '') {
        continue;
    }
    $esImagen = (bool) preg_match('/\.(?:jpe?g|png|webp|gif)(?:[?#].*)?$/i', $rutaDocumentoActual);
    $rutaSegura = htmlspecialchars($rutaDocumentoActual, ENT_QUOTES, 'UTF-8');
    $nombreSeguro = htmlspecialchars($nombreDocumento, ENT_QUOTES, 'UTF-8');
    $contenidoDocumento = $esImagen
        ? sprintf('<img src="%s" alt="%s">', $rutaSegura, $nombreSeguro)
        : sprintf('<a href="%s" target="_blank" rel="noopener">Abrir documento</a>', $rutaSegura);
    $documentosHtml .= sprintf(
        '<article class="vehicle-document-card"><label>%s</label><input class="field" type="text" value="%s" readonly aria-readonly="true">%s</article>',
        $nombreSeguro,
        $rutaSegura,
        $contenidoDocumento
    );
}
if ($documentosHtml === '') {
    $documentosHtml = '';
}

$template = file_get_contents(__DIR__ . '/../pages/perfil.html');
$replacements = [
    '{{NOMBRE_COMPLETO}}' => htmlspecialchars($nombreCompleto, ENT_QUOTES, 'UTF-8'),
    '{{NOMBRE}}' => htmlspecialchars($perfil['nombre'], ENT_QUOTES, 'UTF-8'),
    '{{APELLIDO}}' => htmlspecialchars($perfil['apellido'], ENT_QUOTES, 'UTF-8'),
    '{{INICIALES}}' => htmlspecialchars($iniciales, ENT_QUOTES, 'UTF-8'),
    '{{FOTO_URL}}' => htmlspecialchars($fotoUrl, ENT_QUOTES, 'UTF-8'),
    '{{EMAIL}}' => htmlspecialchars($perfil['email'], ENT_QUOTES, 'UTF-8'),
    '{{VEHICULO}}' => htmlspecialchars($vehiculo, ENT_QUOTES, 'UTF-8'),
    '{{DOCUMENTOS_VEHICULO}}' => $documentosHtml,
    '{{CSRF_TOKEN}}' => htmlspecialchars($tokenCsrf, ENT_QUOTES, 'UTF-8'),
    '{{ALERTAS}}' => $alertasHtml,
];
echo strtr($template, $replacements);
