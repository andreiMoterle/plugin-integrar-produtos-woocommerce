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

function importar_woo_mensagem($mensagem, $tipo = 'success') {
    $classe = $tipo === 'error' ? 'notice notice-error' : 'notice notice-success';
    echo '<div class="' . esc_attr($classe) . '"><p>' . esc_html($mensagem) . '</p></div>';
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