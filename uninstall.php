<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}
delete_option('importador_woo_destinos');
delete_option('importador_woo_historico_envios');
delete_option('importador_woo_destino_url');
delete_option('importador_woo_destino_ck');
delete_option('importador_woo_destino_cs');