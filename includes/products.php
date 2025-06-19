<?php
function importar_produto_para_destino($produto, $destino, $cat_map) {
    $destino_url = $destino['url'];
    $produto_id = $produto->ID;

    $id_destino = id_destino_produto($produto_id, $destino_url);

    $tem_variacoes = get_posts([
        'post_type' => 'product_variation',
        'post_parent' => $produto->ID,
        'posts_per_page' => 1,
        'post_status' => ['publish', 'private'],
        'fields' => 'ids'
    ]);
    $is_variable = !empty($tem_variacoes);

    $data = montar_dados_produto($produto, $cat_map);

    if ($is_variable) {
        $data['type'] = 'variable';
        $data['attributes'] = montar_atributos_produto($produto);
    } else {
        $data['type'] = 'simple';
    }

    $log_prefix = '[Importador Woo] Produto "' . $produto->post_title . '" (ID local: ' . $produto_id . ') - ';

    if ($id_destino) {
        // Já existe: atualiza (PUT)
        $url = trailingslashit($destino_url) . 'wp-json/wc/v3/products/' . $id_destino;
        $response = wp_remote_request(
            $url,
            [
                'method'  => 'PUT',
                'headers' => importar_woo_get_auth_headers($destino['ck'], $destino['cs']),
                'body'    => json_encode($data),
                'timeout' => 30,
            ]
        );
        error_log($log_prefix . 'Atualizando produto no destino (PUT). Dados: ' . json_encode($data));
        error_log($log_prefix . 'Resposta PUT: ' . print_r($response, true));

        // Se der erro de ID inválido, tenta criar de novo
        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) == 400) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            if (isset($body['code']) && $body['code'] === 'woocommerce_rest_product_invalid_id') {
                // Remove vínculo antigo
                $historico = get_option('importador_woo_historico_envios', []);
                unset($historico[$destino_url][$produto_id]);
                update_option('importador_woo_historico_envios', $historico);
                error_log($log_prefix . 'ID inválido no destino. Removendo vínculo e tentando criar novamente.');

                $url = trailingslashit($destino_url) . 'wp-json/wc/v3/products';
                $response = wp_remote_post(
                    $url,
                    [
                        'headers' => importar_woo_get_auth_headers($destino['ck'], $destino['cs']),
                        'body'    => json_encode($data),
                        'timeout' => 30,
                    ]
                );
                error_log($log_prefix . 'Resposta POST após erro de ID: ' . print_r($response, true));
            }
        }
    } else {
        $url = trailingslashit($destino_url) . 'wp-json/wc/v3/products';
        $response = wp_remote_post(
            $url,
            [
                'headers' => importar_woo_get_auth_headers($destino['ck'], $destino['cs']),
                'body'    => json_encode($data),
                'timeout' => 30,
            ]
        );
        error_log($log_prefix . 'Criando produto no destino (POST). Dados: ' . json_encode($data));
        error_log($log_prefix . 'Resposta POST: ' . print_r($response, true));
    }

    if (is_wp_error($response)) {
        error_log($log_prefix . 'Erro WP: ' . $response->get_error_message());
        return [
            'success' => false,
            'mensagem' => 'Erro ao enviar "' . $produto->post_title . '": ' . $response->get_error_message()
        ];
    }
    if (wp_remote_retrieve_response_code($response) >= 300) {
        $body = wp_remote_retrieve_body($response);
        error_log($log_prefix . 'Erro HTTP: ' . $body);
        return [
            'success' => false,
            'mensagem' => 'Erro ao enviar "' . $produto->post_title . '": ' . $body
        ];
    }
    $body = json_decode(wp_remote_retrieve_body($response), true);
    $id_destino_novo = $body['id'] ?? null;

    if ($id_destino_novo && (!$id_destino || $id_destino != $id_destino_novo)) {
        registrar_envio_produto($produto_id, $destino_url, $id_destino_novo);
    }

    if ($is_variable && $id_destino_novo) {
        importar_variacoes_para_destino($produto, $destino, $id_destino_novo);
    }

    return [
        'success' => true,
        'id_destino' => $id_destino_novo
    ];
}

function montar_dados_produto($produto, $cat_map) {
    $preco = get_post_meta($produto->ID, '_price', true);
    
    $preco_promocional = get_post_meta($produto->ID, '_sale_price', true);
    
    $sale_price_from = get_post_meta($produto->ID, '_sale_price_dates_from', true);
    
    $sale_price_to = get_post_meta($produto->ID, '_sale_price_dates_to', true);
    
    $sku = get_post_meta($produto->ID, '_sku', true);
    
    $peso = get_post_meta($produto->ID, '_weight', true);
    
    $comprimento = get_post_meta($produto->ID, '_length', true);
    
    $largura = get_post_meta($produto->ID, '_width', true);
    
    $altura = get_post_meta($produto->ID, '_height', true);


    $imagem_id = get_post_thumbnail_id($produto->ID);
    $imagem_url = $imagem_id ? wp_get_attachment_url($imagem_id) : '';


    $images = [];
    if ($imagem_url) {
        $images[] = ['src' => $imagem_url];
    }
    $galeria_ids = get_post_meta($produto->ID, '_product_image_gallery', true);
    if ($galeria_ids) {
        $galeria_ids = explode(',', $galeria_ids);
        foreach ($galeria_ids as $gid) {
            $url = wp_get_attachment_url($gid);
            if ($url && $url !== $imagem_url) {
                $images[] = ['src' => $url];
            }
        }
    }

    // Categorias associadas (mapeando para o ID do destino)
    $categorias_ids = wp_get_post_terms($produto->ID, 'product_cat', ['fields' => 'ids']);
    $categorias = [];
    $tem_uncategorized = false;
    foreach ($categorias_ids as $cat_id) {
        $cat = get_term($cat_id, 'product_cat');
        if (
            $cat && !is_wp_error($cat)
            && isset($cat_map[$cat->slug])
        ) {

            if (
                strtolower($cat->slug) === 'uncategorized' ||
                strtolower($cat->name) === 'todos os produtos'
            ) {
                $tem_uncategorized = true;
            }
            $categorias[] = ['id' => $cat_map[$cat->slug]];
        }
    }

    if (isset($cat_map['uncategorized']) && !$tem_uncategorized) {
        $categorias[] = ['id' => $cat_map['uncategorized']];
    }

    $data = [
        'name'        => $produto->post_title,
        'description' => $produto->post_content,
        'regular_price' => $preco !== '' ? strval($preco) : '',
        'sku'        => $sku ? $sku : '',
    ];

    // Sempre envia o campo sale_price (mesmo vazio)
    $data['sale_price'] = ($preco_promocional !== '') ? strval($preco_promocional) : '';

    // Envia datas de promoção se existirem
    if ($sale_price_from) $data['date_on_sale_from'] = date('Y-m-d', intval($sale_price_from));
    if ($sale_price_to) $data['date_on_sale_to'] = date('Y-m-d', intval($sale_price_to));

    if ($peso) $data['weight'] = strval($peso);
    if ($comprimento || $largura || $altura) {
        $data['dimensions'] = [
            'length' => $comprimento ? strval($comprimento) : '',
            'width'  => $largura ? strval($largura) : '',
            'height' => $altura ? strval($altura) : '',
        ];
    }
    if (!empty($images)) $data['images'] = $images;
    if (!empty($categorias)) $data['categories'] = $categorias;

    // Log para depuração do preço promocional
    error_log('[Importador Woo] Produto "' . $produto->post_title . '" (ID: ' . $produto->ID . ') - regular_price: ' . $data['regular_price'] . ' | sale_price: ' . $data['sale_price'] . ' | sale_from: ' . $sale_price_from . ' | sale_to: ' . $sale_price_to);

    return $data;
}

function montar_atributos_produto($produto) {
    $attributes = [];
    $product_attributes = get_post_meta($produto->ID, '_product_attributes', true);
    if (is_array($product_attributes)) {
        foreach ($product_attributes as $attr_name => $attr) {
            $attributes[] = [
                'name' => $attr['name'],
                'position' => intval($attr['position']),
                'visible' => !empty($attr['is_visible']),
                'variation' => !empty($attr['is_variation']),
                'options' => isset($attr['value']) ? array_map('trim', explode('|', $attr['value'])) : [],
            ];
        }
    }
    return $attributes;
}

function importar_variacoes_para_destino($produto, $destino, $id_destino) {
    $product_attributes = get_post_meta($produto->ID, '_product_attributes', true);
    $args_var = [
        'post_type' => 'product_variation',
        'post_parent' => $produto->ID,
        'posts_per_page' => -1,
        'post_status' => ['publish', 'private'],
    ];
    $variacoes = get_posts($args_var);
    foreach ($variacoes as $variacao) {
        $var_data = [
            'regular_price' => get_post_meta($variacao->ID, '_regular_price', true),
            'sale_price'    => get_post_meta($variacao->ID, '_sale_price', true),
            'sku'           => get_post_meta($variacao->ID, '_sku', true),
            'weight'        => get_post_meta($variacao->ID, '_weight', true),
            'dimensions'    => [
                'length' => get_post_meta($variacao->ID, '_length', true),
                'width'  => get_post_meta($variacao->ID, '_width', true),
                'height' => get_post_meta($variacao->ID, '_height', true),
            ],
            'attributes'    => [],
        ];
        // Atributos da variação
        if (is_array($product_attributes)) {
            foreach ($product_attributes as $attr) {
                $attr_slug = 'attribute_' . sanitize_title($attr['name']);
                $value = get_post_meta($variacao->ID, $attr_slug, true);
                if ($value) {
                    $var_data['attributes'][] = [
                        'name'  => $attr['name'],
                        'option'=> $value,
                    ];
                }
            }
        }

        $img_id = get_post_thumbnail_id($variacao->ID);
        $img_url = $img_id ? wp_get_attachment_url($img_id) : '';
        if ($img_url) {
            $var_data['image'] = ['src' => $img_url];
        }


        wp_remote_post(trailingslashit($destino['url']) . 'wp-json/wc/v3/products/' . $id_destino . '/variations', [
            'headers' => importar_woo_get_auth_headers($destino['ck'], $destino['cs']),
            'body' => json_encode($var_data),
            'timeout' => 30,
        ]);
    }
}