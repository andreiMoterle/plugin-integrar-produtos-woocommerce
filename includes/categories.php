<?php
function importar_categorias_para_destino($destino_idx = null) {
    $destinos = get_array_option('importador_woo_destinos');
    if ($destino_idx === null) {
        if (!isset($_POST['destino_idx']) || !isset($destinos[$_POST['destino_idx']])) {
            importar_woo_mensagem('Selecione um destino válido.', 'error');
            return;
        }
        $destino = $destinos[$_POST['destino_idx']];
    } else {
        if (!isset($destinos[$destino_idx])) {
            importar_woo_mensagem('Destino inválido.', 'error');
            return;
        }
        $destino = $destinos[$destino_idx];
    }
    $destino_url = $destino['url'];
    $destino_ck = $destino['ck'];
    $destino_cs = $destino['cs'];

    $categorias = get_terms([
        'taxonomy' => 'product_cat',
        'hide_empty' => false,
        'orderby' => 'parent',
        'order' => 'ASC'
    ]);

    if (empty($categorias) || is_wp_error($categorias)) {
        importar_woo_mensagem('Nenhuma categoria encontrada.', 'error');
        return;
    }

    $enviadas = 0;
    $erros = [];
    $mapa_ids = [];

    foreach ($categorias as $cat) {
        if (strtolower($cat->slug) === 'uncategorized' || strtolower($cat->name) === 'todos os produtos') {
            continue;
        }
        $parent_id_destino = 0;
        if ($cat->parent && isset($mapa_ids[$cat->parent])) {
            $parent_id_destino = $mapa_ids[$cat->parent];
        }
        $data = [
            'name' => $cat->name,
            'slug' => $cat->slug,
            'description' => $cat->description,
            'parent' => $parent_id_destino,
        ];
        $response = wp_remote_post(trailingslashit($destino_url) . 'wp-json/wc/v3/products/categories', [
            'headers' => importar_woo_get_auth_headers($destino_ck, $destino_cs),
            'body' => json_encode($data),
            'timeout' => 30,
        ]);
        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) < 300) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            if (isset($body['id'])) {
                $mapa_ids[$cat->term_id] = $body['id'];
                $enviadas++;
            }
        } else {
            $erro = is_wp_error($response) ? $response->get_error_message() : wp_remote_retrieve_body($response);
            $erros[] = 'Erro ao enviar categoria "' . $cat->name . '": ' . $erro;
        }
    }

    if ($enviadas > 0) {
        importar_woo_mensagem($enviadas . ' categoria(s) enviadas com sucesso.', 'success');
    }
    if ($erros) {
        importar_woo_mensagem('Ocorreram erros ao enviar algumas categorias:', 'error');
        echo '<ul class="importador-erros">';
        foreach ($erros as $erro) {
            echo '<li>' . esc_html($erro) . '</li>';
        }
        echo '</ul>';
    }
}

function mapear_categorias_destino($destino) {
    $cat_response = wp_remote_get(trailingslashit($destino['url']) . 'wp-json/wc/v3/products/categories?per_page=100', [
        'headers' => importar_woo_get_auth_headers($destino['ck'], $destino['cs']),
        'timeout' => 20,
    ]);
    $cat_body = is_wp_error($cat_response) ? [] : json_decode(wp_remote_retrieve_body($cat_response), true);
    $cat_map = [];
    foreach ($cat_body as $cat) {
        if (isset($cat['slug']) && isset($cat['id'])) {
            $cat_map[$cat['slug']] = $cat['id'];
        }
    }
    return $cat_map;
}