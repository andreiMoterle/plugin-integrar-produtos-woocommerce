<?php
/*
Plugin Name: Importador de Produtos WooCommerce
Description: Envia produtos deste site WooCommerce para outro via API REST.
Version: 2.91
Author: Andrei Moterle
*/

require_once plugin_dir_path(__FILE__) . 'includes/products.php';
require_once plugin_dir_path(__FILE__) . 'includes/categories.php';
require_once plugin_dir_path(__FILE__) . 'includes/helpers.php';

add_action('admin_menu', 'importador_menu');

add_action('admin_enqueue_scripts', function() {
    wp_enqueue_script(
        'importador-custom-js',
        plugin_dir_url(__FILE__) . 'assets/js/custom.js',
        [],
        '1.0',
        true
    );
    wp_enqueue_style(
        'importador-loader-css',
        plugin_dir_url(__FILE__) . 'assets/css/loader.css',
        [],
        '1.0'
    );
});

function importador_menu() {
    add_menu_page('Importar Produto', 'Importar Produto', 'manage_options', 'importador-woo', 'importador_interface');
    add_submenu_page('importador-woo', 'Importar Produto', 'Importar Produto', 'manage_options', 'importador-woo', 'importador_interface');
    add_submenu_page('importador-woo', 'Importar Categorias', 'Importar Categorias', 'manage_options', 'importador-woo-categorias', 'importador_categorias_interface');
    add_submenu_page('importador-woo', 'Configurações', 'Configurações', 'manage_options', 'importador-woo-config', 'importador_config_interface');
    add_submenu_page('importador-woo', 'Histórico de Envios', 'Histórico de Envios', 'manage_options', 'importador-woo-historico', 'importador_historico_interface');
}

function importador_interface() {
    if (isset($_POST['importar_produtos'])) {
        enviar_produtos_para_destino();
    }
    include plugin_dir_path(__FILE__) . 'templates/import-page.php';
}

function importador_config_interface() {
    if (isset($_POST['salvar_config'])) {
        update_option('importador_woo_destino_url', esc_url_raw($_POST['importador_woo_destino_url']));
        update_option('importador_woo_destino_ck', sanitize_text_field($_POST['importador_woo_destino_ck']));
        update_option('importador_woo_destino_cs', sanitize_text_field($_POST['importador_woo_destino_cs']));
        echo '<div class="updated"><p>Configurações salvas!</p></div>';
    }
    $destino_url = get_option('importador_woo_destino_url', '');
    $destino_ck = get_option('importador_woo_destino_ck', '');
    $destino_cs = get_option('importador_woo_destino_cs', '');
    include plugin_dir_path(__FILE__) . 'templates/settings-page.php';
}

function registrar_envio_produto($produto_id, $destino_url, $id_destino) {
    $historico = get_option('importador_woo_historico_envios', []);
    if (!isset($historico[$destino_url])) {
        $historico[$destino_url] = [];
    }
    $historico[$destino_url][$produto_id] = [
        'enviado_em' => time(),
        'id_destino' => $id_destino
    ];
    update_option('importador_woo_historico_envios', $historico);
}

function produto_ja_enviado($produto_id, $destino_url) {
    $historico = get_option('importador_woo_historico_envios', []);
    return isset($historico[$destino_url][$produto_id]);
}

function id_destino_produto($produto_id, $destino_url) {
    $historico = get_option('importador_woo_historico_envios', []);
    return isset($historico[$destino_url][$produto_id]['id_destino']) ? $historico[$destino_url][$produto_id]['id_destino'] : false;
}

function enviar_produtos_para_destino() {
    $destinos = get_option('importador_woo_destinos', []);
    if (!isset($_POST['destino_idx']) || !isset($destinos[$_POST['destino_idx']])) {
        importar_woo_mensagem('Selecione um destino válido.', 'error');
        return;
    }
    $destino = $destinos[$_POST['destino_idx']];
    $destino_url = $destino['url'];
    $destino_ck = $destino['ck'];
    $destino_cs = $destino['cs'];

    if (!$destino_url || !$destino_ck || !$destino_cs) {
        importar_woo_mensagem('Configure o destino antes de enviar.', 'error');
        return;
    }

    $cat_map = mapear_categorias_destino($destino);

    $args = [
        'post_type' => 'product',
        'posts_per_page' => -1,
        'post_status' => 'publish',
    ];
    $produtos = get_posts($args);

    if (empty($produtos)) {
        echo '<p>Nenhum produto encontrado para enviar.</p>';
        return;
    }

    $enviados = 0;
    $erros = [];

    foreach ($produtos as $produto) {
        $resultado = importar_produto_para_destino($produto, $destino, $cat_map);
        if (is_array($resultado) && !empty($resultado['success'])) {
            registrar_envio_produto($produto->ID, $destino_url, $resultado['id_destino']);
            $enviados++;
        } else {
            $erros[] = $resultado['mensagem'] ?? 'Erro ao enviar "' . $produto->post_title . '"';
        }
    }

    importar_woo_mensagem($enviados . ' produto(s) enviados.', 'success');
    if ($erros) {
        importar_woo_mensagem('Ocorreram erros ao enviar alguns produtos:', 'error');
        echo '<ul class="importador-erros">';
        foreach ($erros as $erro) {
            echo '<li>' . esc_html($erro) . '</li>';
        }
        echo '</ul>';
    }
}


function importador_historico_interface() {
    include plugin_dir_path(__FILE__) . 'templates/historico-page.php';
}

function deletar_produto_destinos($produto_id) {
    $destinos = get_option('importador_woo_destinos', []);
    $historico = get_option('importador_woo_historico_envios', []);
    foreach ($destinos as $destino) {
        $destino_url = $destino['url'];
        $destino_ck = $destino['ck'];
        $destino_cs = $destino['cs'];
        if (isset($historico[$destino_url][$produto_id]['id_destino'])) {
            $id_destino = $historico[$destino_url][$produto_id]['id_destino'];
            $response = wp_remote_request(trailingslashit($destino_url) . 'wp-json/wc/v3/products/' . $id_destino . '?force=true', [
                'method' => 'DELETE',
                'headers' => importar_woo_get_auth_headers($destino_ck, $destino_cs),
                'timeout' => 30,
            ]);
            // Remove do histórico local
            unset($historico[$destino_url][$produto_id]);
        }
    }
    update_option('importador_woo_historico_envios', $historico);
}

add_action('before_delete_post', function($post_id) {
    if (get_post_type($post_id) === 'product') {
        deletar_produto_destinos($post_id);
    }
});

function importador_categorias_interface() {
    if (isset($_POST['importar_categorias'])) {
        importar_categorias_para_destino();
    }
    include plugin_dir_path(__FILE__) . 'templates/categorias-page.php';
}

function buscar_info_no_destino($destino, $id_destino_produto) {
    if (!$id_destino_produto) return ['vendas' => '-', 'status' => '-'];
    $url = trailingslashit($destino['url']) . 'wp-json/wc/v3/products/' . $id_destino_produto;
    $response = wp_remote_get($url, [
        'headers' => importar_woo_get_auth_headers($destino['ck'], $destino['cs']),
        'timeout' => 20,
    ]);
    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) >= 300) {
        return ['vendas' => 'Erro', 'status' => 'Erro'];
    }
    $body = json_decode(wp_remote_retrieve_body($response), true);
    return [
        'vendas' => isset($body['total_sales']) ? intval($body['total_sales']) : 0,
        'status' => isset($body['status']) ? $body['status'] : '-'
    ];
}

add_action('wp_ajax_buscar_vendas_no_destino', function() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['vendas' => '-']);
    }
    $destino_url = $_GET['destino_url'] ?? '';
    $id_destino_produto = $_GET['id_destino_produto'] ?? '';
    $destinos = get_array_option('importador_woo_destinos');
    $destino = null;
    foreach ($destinos as $d) {
        if ($d['url'] === $destino_url) {
            $destino = $d;
            break;
        }
    }
    if (!$destino || !$id_destino_produto) {
        wp_send_json_success(['vendas' => '-']);
    }
    $url = trailingslashit($destino['url']) . 'wp-json/wc/v3/products/' . $id_destino_produto;
    $response = wp_remote_get($url, [
        'headers' => importar_woo_get_auth_headers($destino['ck'], $destino['cs']),
        'timeout' => 20,
    ]);
    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) >= 300) {
        wp_send_json_success(['vendas' => '-']);
    }
    $body = json_decode(wp_remote_retrieve_body($response), true);
    $vendas = isset($body['total_sales']) ? intval($body['total_sales']) : '-';
    wp_send_json_success(['vendas' => $vendas]);
});

add_action('wp_ajax_importar_produtos_em_lote', function() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['mensagem' => 'Permissão negada.']);
    }
    $destino_idx = intval($_POST['destino_idx'] ?? 0);
    $offset = intval($_POST['offset'] ?? 0);
    $batch_size = 5;

    $destinos = get_option('importador_woo_destinos', []);
    if (!isset($destinos[$destino_idx])) {
        wp_send_json_error(['mensagem' => 'Destino inválido.']);
    }
    $destino = $destinos[$destino_idx];
    $cat_map = mapear_categorias_destino($destino);

    // Pegue todos os IDs de produtos apenas uma vez
    static $all_ids = null;
    if ($all_ids === null) {
        $all_ids = get_posts([
            'post_type' => 'product',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'fields' => 'ids'
        ]);
    }
    $total = count($all_ids);

    // Pegue o lote correto
    $produtos = array_slice($all_ids, $offset, $batch_size);

    $apenas_novos = !empty($_POST['apenas_novos']);
    if ($apenas_novos) {
        $produtos = array_filter($produtos, function($produto_id) use ($destino) {
            return !produto_ja_enviado($produto_id, $destino['url']);
        });
    }

    $enviados = 0;
    $erros = [];

    foreach ($produtos as $produto_id) {
        $produto = get_post($produto_id);
        $resultado = importar_produto_para_destino($produto, $destino, $cat_map);
        if (is_array($resultado) && !empty($resultado['success'])) {
            registrar_envio_produto($produto->ID, $destino['url'], $resultado['id_destino']);
            $enviados++;
        } else {
            $erros[] = $resultado['mensagem'] ?? 'Erro ao enviar "' . $produto->post_title . '"';
        }
    }

    $proximo_offset = $offset + $batch_size;
    $finalizado = $proximo_offset >= $total;

    wp_send_json_success([
        'enviados' => $enviados,
        'erros' => $erros,
        'finalizado' => $finalizado,
        'progresso' => min($proximo_offset, $total) . " / $total"
    ]);
});

add_action('wp_ajax_importar_produto_unico_ajax', function() {
    check_ajax_referer('importar_produto_unico_ajax');

    $produto_id = intval($_POST['produto_id'] ?? 0);
    $destino_idx = $_POST['destino_idx'] ?? '';
    $destinos = get_option('importador_woo_destinos', []);
    if (!isset($destinos[$destino_idx])) {
        wp_send_json_error(['mensagem' => 'Destino inválido.']);
    }
    $produto = get_post($produto_id);
    if (!$produto || $produto->post_type !== 'product') {
        wp_send_json_error(['mensagem' => 'Produto inválido.']);
    }
    $destino = $destinos[$destino_idx];
    $cat_map = mapear_categorias_destino($destino);
    $resultado = importar_produto_para_destino($produto, $destino, $cat_map);
    if (is_array($resultado) && !empty($resultado['success'])) {
        registrar_envio_produto($produto->ID, $destino['url'], $resultado['id_destino']);
        wp_send_json_success(['mensagem' => 'Produto "' . $produto->post_title . '" importado/sincronizado com sucesso!']);
    } else {
        wp_send_json_error(['mensagem' => $resultado['mensagem'] ?? 'Erro ao importar o produto.']);
    }
});