<?php
function importar_produto_para_destino($produto, $destino, $cat_map) {
    // Detecta se o produto tem variações
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

    // Cria produto principal
    $response = wp_remote_post(trailingslashit($destino['url']) . 'wp-json/wc/v3/products', [
        'headers' => importar_woo_get_auth_headers($destino['ck'], $destino['cs']),
        'body' => json_encode($data),
        'timeout' => 30,
    ]);
    error_log("Produto: " . $produto->post_title . " - Dados enviados: " . json_encode($data));
    error_log("Produto: " . $produto->post_title . " - Resposta: " . print_r($response, true));

    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) >= 300) {
        return false;
    }
    $body = json_decode(wp_remote_retrieve_body($response), true);
    $id_destino = $body['id'] ?? null;

    // Se for variável, cria as variações
    if ($is_variable && $id_destino) {
        importar_variacoes_para_destino($produto, $destino, $id_destino);
    }

    return $id_destino;
}

function montar_dados_produto($produto, $cat_map) {
    $preco = get_post_meta($produto->ID, '_price', true);
    $preco_promocional = get_post_meta($produto->ID, '_sale_price', true);
    $sku = get_post_meta($produto->ID, '_sku', true);
    $peso = get_post_meta($produto->ID, '_weight', true);
    $comprimento = get_post_meta($produto->ID, '_length', true);
    $largura = get_post_meta($produto->ID, '_width', true);
    $altura = get_post_meta($produto->ID, '_height', true);

    // Imagem destacada
    $imagem_id = get_post_thumbnail_id($produto->ID);
    $imagem_url = $imagem_id ? wp_get_attachment_url($imagem_id) : '';

    // Galeria de imagens (sem duplicar a destacada)
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
            // Marca se já tem "uncategorized" ou "todos os produtos"
            if (
                strtolower($cat->slug) === 'uncategorized' ||
                strtolower($cat->name) === 'todos os produtos'
            ) {
                $tem_uncategorized = true;
            }
            $categorias[] = ['id' => $cat_map[$cat->slug]];
        }
    }
    // Sempre adiciona "uncategorized" se existir no destino e ainda não está no array
    if (isset($cat_map['uncategorized']) && !$tem_uncategorized) {
        $categorias[] = ['id' => $cat_map['uncategorized']];
    }

    $data = [
        'name'        => $produto->post_title,
        'description' => $produto->post_content,
        'regular_price' => $preco ? strval($preco) : '',
        'sku'        => $sku ? $sku : '',
    ];

    if ($preco_promocional) $data['sale_price'] = strval($preco_promocional);
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
        // Imagem da variação (opcional)
        $img_id = get_post_thumbnail_id($variacao->ID);
        $img_url = $img_id ? wp_get_attachment_url($img_id) : '';
        if ($img_url) {
            $var_data['image'] = ['src' => $img_url];
        }

        // Cria a variação no destino
        wp_remote_post(trailingslashit($destino['url']) . 'wp-json/wc/v3/products/' . $id_destino . '/variations', [
            'headers' => importar_woo_get_auth_headers($destino['ck'], $destino['cs']),
            'body' => json_encode($var_data),
            'timeout' => 30,
        ]);
    }
}