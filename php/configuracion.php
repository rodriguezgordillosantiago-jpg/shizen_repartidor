<?php
require_once __DIR__ . '/../BD/conexion.php';
require_once __DIR__ . '/../funciones/funciones.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $notificaciones_push = isset($_POST['notificaciones_push']) ? 1 : 0;
    $sonido_pedidos = isset($_POST['sonido_pedidos']) ? 1 : 0;

    // TODO: Guardar en la base de datos cuando exista la tabla de configuracion para repartidor
    $_SESSION['notificaciones_push'] = $notificaciones_push;
    $_SESSION['sonido_pedidos'] = $sonido_pedidos;

    // Redirigir de vuelta a la vista
    header('Location: ../pages/configuracion.html?mensaje=guardado');
    exit;
}
?>
