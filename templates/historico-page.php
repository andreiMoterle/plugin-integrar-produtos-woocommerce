<?php
$historico = get_option('importador_woo_historico_envios', []);
$destinos = get_array_option('importador_woo_destinos');
$destinos_map = [];
foreach ($destinos as $i => $dest) {
    $destinos_map[$dest['url']] = ($dest['nome'] ?? '') . ' (' . ($dest['url'] ?? '') . ')';
}
$urls = array_keys($historico);
$destino_selecionado = isset($_GET['destino']) ? $_GET['destino'] : (count($urls) ? $urls[0] : '');
?>

<div class="wrap">
<div id="importador-loader" class="importador-loader"></div>
    <h1>Histórico de Envios</h1>
    <?php if (empty($historico)): ?>
        <?php importar_woo_mensagem('Nenhum envio registrado.', 'error'); ?>
    <?php else: ?>
        <form method="get" id="form-seleciona-destino">
            <input type="hidden" name="page" value="importador-woo-historico">
            <label for="destino">Destino:</label>
            <select name="destino" id="destino">
                <?php foreach ($urls as $url): ?>
                    <option value="<?php echo esc_attr($url); ?>" <?php selected($destino_selecionado, $url); ?>>
                        <?php echo esc_html($destinos_map[$url] ?? $url); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
        <table class="widefat" id="historico-tabela">
            <thead>
                <tr>
                    <th>ID Produto</th>
                    <th>Nome</th>
                    <th>Enviado em</th>
                    <th>ID no Destino</th>
                    <th>Vendas no Destino</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if (!empty($historico[$destino_selecionado])) :
                    foreach ($historico[$destino_selecionado] as $produto_id => $info):
                        $produto = get_post($produto_id);
                        ?>
                        <tr data-id-destino="<?php echo esc_attr($info['id_destino'] ?? ''); ?>">
                            <td><?php echo esc_html($produto_id); ?></td>
                            <td><?php echo esc_html($produto ? $produto->post_title : '-'); ?></td>
                            <td><?php echo date_i18n('d/m/Y H:i', $info['enviado_em']); ?></td>
                            <td><?php echo esc_html($info['id_destino'] ?? '-'); ?></td>
                            <td class="vendas-no-destino">-</td>
                        </tr>
                    <?php
                    endforeach;
                endif;
                ?>
            </tbody>
        </table>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const select = document.getElementById('destino');
            select.addEventListener('change', function() {
                document.getElementById('form-seleciona-destino').submit();
            });

            <?php if ($destino_selecionado && !empty($historico[$destino_selecionado])): ?>
            const linhas = document.querySelectorAll('#historico-tabela tbody tr');
            linhas.forEach(function(tr) {
                const idDestino = tr.getAttribute('data-id-destino');
                if (idDestino && idDestino !== '-') {
                    tr.querySelector('.vendas-no-destino').textContent = '...';
                    fetch(ajaxurl + '?action=buscar_vendas_no_destino&destino_url=<?php echo urlencode($destino_selecionado); ?>&id_destino_produto=' + idDestino)
                        .then(resp => resp.json())
                        .then(data => {
                            tr.querySelector('.vendas-no-destino').textContent = data.vendas !== undefined ? data.vendas : '-';
                        })
                        .catch(() => {
                            tr.querySelector('.vendas-no-destino').textContent = '-';
                        });
                }
            });
            <?php endif; ?>
        });
        </script>
    <?php endif; ?>
</div>