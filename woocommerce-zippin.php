<?php
/*
 * Plugin Name: Envíos con Zipnova para Woocommerce
 * Plugin URI: https://www.zipnova.com/productos/envios/integraciones/woocommerce
 * Description: Integra WooCommerce con Zipnova para realizar envíos con múltiples transportes a todo el país.
 * Version: 2.6.0
 * Author: Zipnova
 * Author URI: https://www.zipnova.com/
 * Requires PHP: 7
 * License: GPL2
 * License URI:  https://www.gnu.org/licenses/gpl-2.0.html
 * WC requires at least: 4.0.0
 * WC tested up to: 9.3.3
 * Text Domain: zippin_woocommerce
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

define('ZIPPIN_APIKEY', '');
define('ZIPPIN_SECRETKEY', '');
define('ZIPPIN_DOMAIN', '');


define('ZIPPIN_LOGGER_CONTEXT', serialize(array('source' => 'zippin')));
define('ZIPPIN_VERSION', '2.6.0');

// Setting plugin as HPOS compatible
add_action( 'before_woocommerce_init', function() {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
    }
} );


register_activation_hook(__FILE__, 'Zippin\Zippin\Utils\activate_plugin');
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'Zippin\Zippin\Utils\add_plugin_column_links');
add_filter('plugin_row_meta', 'Zippin\Zippin\Utils\add_plugin_description_links', 10, 4);

require_once 'zippin-method.php';
require_once 'zippin-settings.php';
require_once 'zippin-connector.php';
require_once 'hooks.php';
require_once 'helper.php';
require_once 'utils.php';

add_filter('gettext', 'zippin_translate_words_array', 20, 3);
add_filter('ngettext', 'zippin_translate_words_array', 20, 3);

function zippin_translate_words_array($translation, $text, $domain)
{
    if ($text === 'Enter your address to view shipping options.') {
        $translation = 'Ingresa tu dirección para conocer los costos de envío';
    }
    return $translation;
}