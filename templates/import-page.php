<?php
$destinos = get_array_option('importador_woo_destinos');
$args = [
    'post_type' => 'product',
    'posts_per_page' => -1,
    'post_status' => 'publish',
];
$produtos = get_posts($args);

$destino_selecionado = '';
if (isset($_POST['destino_idx'])) {
    $destino_selecionado = $_POST['destino_idx'];
} elseif (isset($_GET['destino_idx'])) {
    $destino_selecionado = $_GET['destino_idx'];
}
?>
<div class="wrap">

    <h1>Enviar Produtos para Franqueado</h1>
    <?php if (empty($destinos)): ?>
        <?php importar_woo_mensagem('Cadastre pelo menos um destino nas configurações.', 'error'); ?>
    <?php elseif (empty($produtos)): ?>
        <?php importar_woo_mensagem('Nenhum produto encontrado.', 'error'); ?>
    <?php else: ?>
        
    <form method="post" id="form-importar-produto">
        <script>
            var importar_produto_unico_ajax = {
                nonce: '<?php echo wp_create_nonce('importar_produto_unico_ajax'); ?>'
            };
        </script>
        <?php wp_nonce_field('importar_produto_unico_action', 'importar_produto_unico_nonce'); ?>
        
        <label for="destino_idx"><strong>Destino:</strong></label>
        <select name="destino_idx" id="destino_idx" required>
            <option value="">Selecione...</option>
            <?php foreach ($destinos as $i => $dest): ?>
                <option value="<?php echo esc_attr($i); ?>" <?php selected($destino_selecionado, $i); ?>>
                    <?php echo esc_html($dest['nome'] ?? $dest['url']); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <br><br>

        <button type="button" id="importar-todos" class="button button-primary">Importar Todos</button>
        <div id="importar-todos-status" style="margin:10px 0;"></div>
        <div class="progressbar-container" id="importar-todos-progressbar"><div class="progressbar-bar"></div></div>

        <button type="button" id="importar-novos" class="button">Importar Apenas Novos</button>
        <div id="importar-novos-status" style="margin:10px 0;"></div>
        <div class="progressbar-container" id="importar-novos-progressbar"><div class="progressbar-bar"></div></div>

        <br>
        <table class="widefat">
            <thead>
                <tr>
                    <th>Imagem</th>
                    <th>Nome</th>
                    <th>Ação</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($produtos as $produto): ?>
                    <tr>
                        <td>
                            <?php
                            $img_id = get_post_thumbnail_id($produto->ID);
                            $img_url = $img_id ? wp_get_attachment_image_url($img_id, 'thumbnail') : '';
                            if ($img_url) {
                                echo '<img src="' . esc_url($img_url) . '" style="max-width:60px;max-height:60px;" />';
                            } else {
                                echo '-';
                            }
                            ?>
                        </td>
                        <td><?php echo esc_html($produto->post_title); ?></td>
                        <td>
                            <button type="button" name="importar_produto_unico" value="<?= esc_attr($produto->ID) ?>" class="button button-primary">Importar/Sincronizar</button>
<div class="progressbar-container importar-produto-progressbar" id="importar-produto-progressbar-<?= $produto->ID ?>"><div class="progressbar-bar"></div></div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </form>
<?php endif; ?>
</div>