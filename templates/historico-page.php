<?php
$historico = get_option('importador_woo_historico_envios', []);
$destinos = get_option('importador_woo_destinos', []);
$destinos_map = [];
foreach ($destinos as $i => $dest) {
    $destinos_map[$dest['url']] = $dest['nome'] . ' (' . $dest['url'] . ')';
}
$urls = array_keys($historico);
$destino_selecionado = isset($_GET['destino']) ? $_GET['destino'] : (count($urls) ? $urls[0] : '');

function buscar_vendas_no_destino($destino, $id_destino_produto) {
    if (!$id_destino_produto) return '-';
    $url = trailingslashit($destino['url']) . 'wp-json/wc/v3/products/' . $id_destino_produto;
    $response = wp_remote_get($url, [
        'headers' => [
            'Authorization' => 'Basic ' . base64_encode($destino['ck'] . ':' . $destino['cs']),
            'Content-Type'  => 'application/json',
        ],
        'timeout' => 20,
    ]);
    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) >= 300) {
        return 'Erro';
    }
    $body = json_decode(wp_remote_retrieve_body($response), true);
    return isset($body['total_sales']) ? intval($body['total_sales']) : 0;
}
?>
<div class="wrap">
<div id="importador-loader" class="importador-loader">
    <div class="importador-loader-center">
        <div class="loader"></div>
    </div>
</div>
    <h1>Histórico de Produtos Enviados</h1>
    <?php if ($urls): ?>
        <form method="get" style="display:inline;">
            <input type="hidden" name="page" value="importador-woo-historico" />
            <label for="destino">Selecione o destino:</label>
            <select name="destino" id="destino" onchange="this.form.submit()">
                <?php foreach ($urls as $url): ?>
                    <option value="<?php echo esc_attr($url); ?>" <?php selected($destino_selecionado, $url); ?>>
                        <?php echo esc_html(isset($destinos_map[$url]) ? $destinos_map[$url] : $url); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="button">Atualizar</button>
        </form>
        <table class="widefat">
            <thead>
                <tr>
                    <th>Status</th>
                    <th>Produto</th>
                    <th>Data/Hora do Envio</th>
                    <th>Vendas no Destino</th>
                </tr>
            </thead>
            <tbody>
                <?php
                    if ($destino_selecionado && isset($historico[$destino_selecionado])) {
                        $destino = null;
                        foreach ($destinos as $d) {
                            if ($d['url'] === $destino_selecionado) {
                                $destino = $d;
                                break;
                            }
                        }
                        foreach ($historico[$destino_selecionado] as $produto_id => $info) {
                            $produto = get_post($produto_id);
                            if ($produto) {
                                $info_destino = isset($info['id_destino']) && $destino
                                    ? buscar_info_no_destino($destino, $info['id_destino'])
                                    : ['vendas' => '-', 'status' => '-'];
                                echo '<tr>';
                                echo '<td>' . esc_html(traduzir_status($info_destino['status'])) . '</td>';
                                echo '<td>' . esc_html($produto->post_title) . ' (ID: ' . $produto_id . ')</td>';
                                echo '<td>' . date('d/m/Y H:i', $info['enviado_em']) . '</td>';
                                echo '<td>' . esc_html($info_destino['vendas']) . '</td>';
                                echo '</tr>';
                            }
                        }
                    }
                ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>Nenhum envio registrado ainda.</p>
    <?php endif; ?>
</div>