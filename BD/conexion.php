<?php
/**
 * conexion.php
 * ---------------------------------------------------------
 * Conexión a la base de datos MySQL usando PDO.
 * Ajusta estas constantes si tu configuración de XAMPP es distinta
 * (por defecto XAMPP usa usuario "root" y contraseña vacía).
 * ---------------------------------------------------------
 */

const DB_HOST = "localhost";
const DB_NAME = "shizen";
const DB_USER = "root";
const DB_PASS = "";
const DB_CHARSET = "utf8mb4";

function obtenerConexion(): PDO {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;

    $opciones = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Lanza excepciones en errores SQL
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Resultados como arreglos asociativos
        PDO::ATTR_EMULATE_PREPARES   => false,                  // Usa preparación real del driver (más seguro)
    ];

    try {
        return new PDO($dsn, DB_USER, DB_PASS, $opciones);
    } catch (PDOException $e) {
        // No exponemos el mensaje real de la BD al usuario final, solo lo registramos.
        error_log("Error de conexion a la base de datos: " . $e->getMessage());
        throw new RuntimeException("No fue posible conectar con la base de datos.");
    }
}
