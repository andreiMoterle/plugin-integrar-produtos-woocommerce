<?php
$destinos = get_array_option('importador_woo_destinos');
?>
<div class="wrap">
<div id="importador-loader" class="importador-loader">
    <div class="importador-loader-center">
        <div class="loader"></div>
    </div>
</div>

    <h1>Enviar Produtos para Franqueado</h1>
    <?php if (empty($destinos)): ?>
        <p style="color:red;">Cadastre pelo menos um destino nas configurações.</p>
    <?php else: ?>
        <form method="post">
            <?php wp_nonce_field('importar_produtos_action', 'importar_produtos_nonce'); ?>
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
            <?php submit_button('Enviar Agora', 'primary', 'importar_produtos'); ?>
        </form>
    <?php endif; ?>
</div>