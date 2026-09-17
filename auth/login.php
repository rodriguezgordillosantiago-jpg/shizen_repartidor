<?php
require_once __DIR__ . '/../BD/conexion.php';
require_once __DIR__ . '/../funciones/funciones.php';
session_start();

function mostrarErrorLogin(): void
{
    $html = file_get_contents(__DIR__ . '/../index.html');
    if ($html === false) {
        http_response_code(500);
        exit('No se pudo cargar el formulario de inicio de sesión.');
    }

    $html = str_replace('<head>', '<head><base href="../">', $html);
    $html = str_replace('id="login-error" hidden', 'id="login-error"', $html);
    echo $html;
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    mostrarErrorLogin();
}

$email = limpiarTexto($_POST['email'] ?? '');
$password = (string) ($_POST['password'] ?? '');
$emailValido = filter_var($email, FILTER_VALIDATE_EMAIL) !== false;

if (!$emailValido || strlen($email) > 254 || strlen($password) < 6 || strlen($password) > 255) {
    mostrarErrorLogin();
}

$stmt = obtenerConexion()->prepare("SELECT * FROM usuario WHERE email = ? AND LOWER(rol) = 'repartidor'");
$stmt->execute([$email]);
$usuario = $stmt->fetch();

if (!$usuario || !password_verify($password, $usuario['password_hash'])) {
    mostrarErrorLogin();
}

session_regenerate_id(true);
$_SESSION['id_usuario'] = $usuario['id'] ?? $usuario['id_usuario'] ?? null;
$_SESSION['usuario_nombre'] = $usuario['nombre'];
$_SESSION['usuario_email'] = $usuario['email'];
$_SESSION['usuario_rol'] = 'repartidor';
header('Location: ../pages/inicio.html');
exit;
