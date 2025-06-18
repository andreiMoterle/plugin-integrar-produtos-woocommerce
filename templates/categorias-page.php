<?php
$destinos = get_array_option('importador_woo_destinos');
?>
<div class="wrap">
<div id="importador-loader" class="importador-loader">
    <div class="importador-loader-center">
        <div class="loader"></div>
    </div>
</div>
    <h1>Importar Categorias para Franqueado</h1>
    <?php if (empty($destinos)): ?>
        <p style="color:red;">Cadastre pelo menos um destino nas configurações.</p>
    <?php else: ?>
        <form method="post">
            <?php wp_nonce_field('importar_categorias_action', 'importar_categorias_nonce'); ?>
            <table class="form-table">
                <tr>
                    <th><label for="destino_idx">Destino</label></th>
                    <td>
                        <select name="destino_idx" id="destino_idx" required>
                            <option value="">Selecione...</option>
                            <?php foreach ($destinos as $i => $dest): ?>
                                <option value="<?php echo esc_attr($i); ?>">
                                    <?php echo esc_html($dest['nome'] ?? $dest['url']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
            </table>
            <?php submit_button('Importar Categorias', 'primary', 'importar_categorias'); ?>
        </form>
    <?php endif; ?>
</div>