<?php
// ── includes/luhn.php ─────────────────────────────────────────
// Algoritmo de Luhn para validación de tarjetas (sin cargo real).

/**
 * Valida un número de tarjeta con el algoritmo de Luhn.
 * Solo dígitos, sin espacios ni guiones.
 */
function luhn_check(string $numero): bool {
    $numero = preg_replace('/\D/', '', $numero);
    $len    = strlen($numero);
    if ($len < 13 || $len > 19) return false;

    $sum    = 0;
    $par    = false;
    for ($i = $len - 1; $i >= 0; $i--) {
        $digito = (int)$numero[$i];
        if ($par) {
            $digito *= 2;
            if ($digito > 9) $digito -= 9;
        }
        $sum += $digito;
        $par = !$par;
    }
    return $sum % 10 === 0;
}

/**
 * Detecta la marca de la tarjeta por su prefijo.
 */
function detectar_marca(string $numero): string {
    $n = preg_replace('/\D/', '', $numero);
    if (preg_match('/^4/',          $n)) return 'VISA';
    if (preg_match('/^5[1-5]/',     $n)) return 'MASTERCARD';
    if (preg_match('/^2[2-7]/',     $n)) return 'MASTERCARD';
    if (preg_match('/^3[47]/',      $n)) return 'AMEX';
    if (preg_match('/^6(?:011|5)/', $n)) return 'DISCOVER';
    return 'OTRA';
}

/**
 * Formatea el número enmascarado: **** **** **** 1234
 */
function mascara_tarjeta(string $numero): string {
    $n = preg_replace('/\D/', '', $numero);
    return '**** **** **** ' . substr($n, -4);
}
