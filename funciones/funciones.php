<?php
/**
 * funciones.php
 * ---------------------------------------------------------
 * Funciones reutilizables para sanitizar y validar datos
 * que llegan desde los formularios (pedido y contacto).
 * ---------------------------------------------------------
 */

/** Limpia espacios y convierte caracteres especiales a entidades HTML seguras. */
function limpiarTexto(?string $valor): string {
    $valor = trim($valor ?? "");
    return htmlspecialchars($valor, ENT_QUOTES, "UTF-8");
}

/** Valida que el texto contenga solo letras y espacios (incluye tildes y ñ). */
function esSoloLetras(string $valor): bool {
    return preg_match('/^[A-Za-zÁÉÍÓÚáéíóúÑñ\s]+$/u', $valor) === 1;
}

/** Valida que el texto contenga solo números. */
function esSoloNumeros(string $valor): bool {
    return preg_match('/^[0-9]+$/', $valor) === 1;
}

/** Valida un correo electrónico usando el filtro nativo de PHP. */
function esCorreoValido(string $valor): bool {
    return filter_var($valor, FILTER_VALIDATE_EMAIL) !== false;
}

/** Valida que el teléfono tenga entre 7 y 10 dígitos numéricos. */
function esTelefonoValido(string $valor): bool {
    return preg_match('/^[0-9]{7,10}$/', $valor) === 1;
}

/**
 * Valida que todos los campos requeridos (por nombre) existan y no estén vacíos
 * dentro del arreglo de datos dado. Devuelve un arreglo con los nombres de los
 * campos que fallaron (vacío si todo está bien).
 */
function validarCamposRequeridos(array $datos, array $requeridos): array {
    $faltantes = [];
    foreach ($requeridos as $campo) {
        if (!isset($datos[$campo]) || trim((string) $datos[$campo]) === "") {
            $faltantes[] = $campo;
        }
    }
    return $faltantes;
}

/** Redirige a una URL con un parámetro de estado (ok / error) y termina la ejecución. */
function redirigirConEstado(string $url, string $estado): void {
    $separador = str_contains($url, "?") ? "&" : "?";
    header("Location: " . $url . $separador . "estado=" . urlencode($estado));
    exit;
}
