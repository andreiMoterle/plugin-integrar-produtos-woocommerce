<?php

function traduzir_status($status) {
    switch ($status) {
        case 'publish': return 'Publicado';
        case 'pending': return 'Revisão pendente';
        case 'draft':   return 'Rascunho';
        default:        return ucfirst($status);
    }
}

function get_array_option($option_name, $default = []) {
    $value = get_option($option_name, $default);
    return is_array($value) ? $value : $default;
}

/**
 * Monta headers de autenticação para requisições REST WooCommerce.
 *
 * @param string $ck
 * @param string $cs
 * @return array
 */
function importar_woo_get_auth_headers($ck, $cs) {
    return [
        'Authorization' => 'Basic ' . base64_encode($ck . ':' . $cs),
        'Content-Type'  => 'application/json',
    ];
}