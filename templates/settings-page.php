<?php
$destinos = get_option('importador_woo_destinos', []);
if (!is_array($destinos)) $destinos = [];

// Adiciona novo destino
if (isset($_POST['adicionar_destino'])) {
    $novo = [
        'nome' => sanitize_text_field($_POST['novo_nome']),
        'url'  => esc_url_raw($_POST['novo_url']),
        'ck'   => sanitize_text_field($_POST['novo_ck']),
        'cs'   => sanitize_text_field($_POST['novo_cs']),
    ];
    $destinos[] = $novo;
    update_option('importador_woo_destinos', $destinos);
    echo '<div class="updated"><p>Destino adicionado!</p></div>';
}

// Remove destino
if (isset($_POST['remover_destino'])) {
    $idx = intval($_POST['remover_destino']);
    if (isset($destinos[$idx])) {
        unset($destinos[$idx]);
        $destinos = array_values($destinos);
        update_option('importador_woo_destinos', $destinos);
        echo '<div class="updated"><p>Destino removido!</p></div>';
    }
}
?>

<div class="wrap">
<div id="importador-loader" class="importador-loader">
    <div class="importador-loader-center">
        <div class="loader"></div>
    </div>
</div>

    <h1>Destinos de Franqueados</h1>
    <h2>Adicionar novo destino</h2>
    <form method="post">
        <table class="form-table">
            <tr>
                <th><label for="novo_nome">Nome</label></th>
                <td><input type="text" name="novo_nome" id="novo_nome" required></td>
            </tr>
            <tr>
                <th><label for="novo_url">URL</label></th>
                <td><input type="text" name="novo_url" id="novo_url" required></td>
            </tr>
            <tr>
                <th><label for="novo_ck">Consumer Key</label></th>
                <td><input type="text" name="novo_ck" id="novo_ck" required></td>
            </tr>
            <tr>
                <th><label for="novo_cs">Consumer Secret</label></th>
                <td><input type="text" name="novo_cs" id="novo_cs" required></td>
            </tr>
        </table>
        <?php submit_button('Adicionar Destino', 'primary', 'adicionar_destino'); ?>
    </form>

    <h2>Destinos cadastrados</h2>
    <table class="widefat">
        <thead>
            <tr>
                <th>Nome</th>
                <th>URL</th>
                <th>Consumer Key</th>
                <th>Consumer Secret</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($destinos as $i => $destino): ?>
                <tr>
                    <td><?php echo esc_html($destino['nome']); ?></td>
                    <td><?php echo esc_html($destino['url']); ?></td>
                    <td><?php echo esc_html($destino['ck']); ?></td>
                    <td><?php echo esc_html($destino['cs']); ?></td>
                    <td>
                        <form method="post" style="display:inline;">
                            <input type="hidden" name="remover_destino" value="<?php echo $i; ?>">
                            <?php submit_button('Remover', 'delete', '', false); ?>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>