<?php
/**
 * Plugin Name: Ferramentas Upload
 * Plugin URI: https://github.com/cagezinho/ferramentas-upload
 * Description: Plugin para gerenciar uploads e atualizações em massa, incluindo textos alternativos de imagens, meta tags do Yoast SEO, exportação de posts e categorias, e movimentação de posts para a lixeira.
 * Version: 1.3.0
 * Author: Cage
 * Author URI: https://github.com/cagezinho/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ferramentas-upload
 */

if (!defined('ABSPATH')) {
    exit;
}

// Define o caminho do plugin
define('FU_PLUGIN_PATH', plugin_dir_path(__FILE__));

// Carrega arquivo de debug (remover após resolver problemas)
require_once FU_PLUGIN_PATH . 'debug-log.php';

// Carrega arquivo de teste de exportação somente se existir (evita erro fatal em produção)
if (file_exists(FU_PLUGIN_PATH . 'teste-exportacao.php')) {
    require_once FU_PLUGIN_PATH . 'teste-exportacao.php';
}

// Carrega o arquivo principal do plugin
require_once FU_PLUGIN_PATH . 'includes/class-plugin-loader.php';

// Inicializa o plugin
add_action('plugins_loaded', array('Ferramentas_Upload_Loader', 'get_instance'));