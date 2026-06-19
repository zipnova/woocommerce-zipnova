<?php

namespace Zippin\Zippin\Utils;

use Zippin\Zippin\ZippinConnector;
use Zippin\Zippin\Helper;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

function add_method($methods)
{
    $methods['zippin'] = 'Zippin\Zippin\WC_Zippin';
    return $methods;
}

function update_order_meta($order_id)
{
    $order = wc_get_order($order_id);
    if (!$order) return false;

    $chosen_shipping_methods = WC()->session ? WC()->session->get('chosen_shipping_methods') : null;
    
    if (empty($chosen_shipping_methods) || !is_array($chosen_shipping_methods)) {
        return false;
    }

    $chosen_shipping_method = reset($chosen_shipping_methods);
    $chosen_shipping_method = explode("|", $chosen_shipping_method);
    $chosen_shipping_method[0] = explode(":", $chosen_shipping_method[0])[0];

    if ($chosen_shipping_method[0] === 'zippin' || ($chosen_shipping_method[0]=='free_shipping' && get_option('zippin_create_free_shipments') == 'yes')) {
        // Save shipment preferences (carrier and service)
        $data = array();

        if($chosen_shipping_method[0] === 'zippin') {
            $data['carrier_id'] = $chosen_shipping_method[1];
            $data['service_type'] = $chosen_shipping_method[2];
            if (isset($chosen_shipping_method[3])) {
                $data['logistic_type'] = $chosen_shipping_method[3];
            }
            if (isset($chosen_shipping_method[4])) {
                $data['point_id'] = $chosen_shipping_method[4];
            }
        } else {
            $data['carrier_id'] = null;
            $data['service_type'] = null;
            $data['logistic_type'] = null;
            $data['point_id'] = null;
        }

        $order->update_meta_data('zippin_shipping_info', serialize($data));
        $order->save();

        $shipment_creation_trigger_status = get_option('zippin_shipping_status');
        if ($shipment_creation_trigger_status && ($order->get_status() === $shipment_creation_trigger_status)) {
            $connector = new ZippinConnector;
            $shipment = $connector->create_shipment($order);
            if ($shipment) {
                $order->update_meta_data('zippin_shipment', serialize($shipment));
                $order->add_order_note('Se creó el envío en Zipnova (ID: '.$shipment['id'].')');
                $order->save();
            } else {
                $order->add_order_note('Falló la creación del envío en Zipnova ('.$connector->getLastError().')');
            }
        }
    }
}

function process_order_status($order_id, $old_status, $new_status)
{
    $order = wc_get_order($order_id);
    if (!$order) return false;

    $order_shipping_methods = $order->get_items('shipping');
    $order_shipping_method = reset($order_shipping_methods);
    $shipment_creation_trigger_status = get_option('zippin_shipping_status');

    if (!$shipment_creation_trigger_status || !$order_shipping_method) return false;
    if (!in_array($order_shipping_method->get_method_id(), ['zippin','free_shipping'])) return false;

    if (WC()->session && !$order->get_meta('zippin_shipping_info', true)) {
        update_order_meta($order_id);
        $order = wc_get_order($order_id);
    }

    if ($order->get_meta('zippin_shipment', true)) {
        // Ya hay un envío creado
        if (in_array($shipment_creation_trigger_status, ['wc-'.$new_status, $new_status])) {
            // El estado es el estado en que hay crear envio.
            // No hacer nada
        }

    } else {
        // NO hay un envío creado
        if ($order->get_meta('zippin_shipping_info', true) && in_array($shipment_creation_trigger_status, ['wc-'.$new_status, $new_status])) {
            wc_get_logger()->info('Creating shipment for order '.$order->get_id(), unserialize(ZIPPIN_LOGGER_CONTEXT));

            $connector = new ZippinConnector;
            $shipment = $connector->create_shipment($order);
            if ($shipment) {
                // Shipment creado
                $order->update_meta_data('zippin_shipment', serialize($shipment));
                $order->add_order_note('Se creó el envío en Zipnova (ID: '.$shipment['id'].')');
                $order->save();
            } else {
                wc_get_logger()->warning('Failed shipment creation for order '.$order->get_id().$connector->getLastError(), unserialize(ZIPPIN_LOGGER_CONTEXT));
                $order->add_order_note('Falló la creación del envío en Zipnova ('.$connector->getLastError().')');

            }
        }

    }
}


function add_order_side_box()
{
    $screen = class_exists( '\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController' ) && wc_get_container()->get( \Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class )->custom_orders_table_usage_is_enabled()
        ? wc_get_page_screen_id( 'shop-order' )
        : 'shop_order';

    add_meta_box(
        'zippin_box',
        '<img src="'.plugin_dir_url(__FILE__) . 'images/zipnova.png" title="Zipnova" style="height: 20px">',
        __NAMESPACE__ . '\box_content',
        $screen,
        'side'
    );

}

function box_content($post_or_order_object)
{
    $order = ( $post_or_order_object instanceof \WP_Post ) ? wc_get_order( $post_or_order_object->ID ) : $post_or_order_object;

    if ( ! $order->get_meta('zippin_shipping_info', true)) {
        echo 'Para esta orden no se ha elegido Zipnova como método de envío.';
        return true;
    }

    $shipment = $order->get_meta('zippin_shipment', true);

    if (empty($shipment) || $shipment == 'b:0;') {
        $statuses = wc_get_order_statuses();
        $target_status = @$statuses[get_option('zippin_shipping_status')];

        echo '<b>Aún no se ha creado un envío en Zipnova.</b><br>Se creará una vez que la orden se ponga en el estado configurado en las opciones del plugin ('.$target_status.').';
        return true;
    }

    $shipment = unserialize($shipment);
    $zippin_domain = Helper::get_current_domain();
    $detail_url = 'https://app.'.$zippin_domain['domain'].'/shipments/'.$shipment['id'];
    echo '<h4><a target="_blank" href="'.$detail_url.'">Envío '.$shipment['external_id'].' ('.$shipment['id'].')</a></h4>';
    echo '<p>Estado: <b>'.$shipment['status_name'].'</b></p>';
    if (!empty($shipment['carrier']['logo'])) {
        echo '<p><img style="max-height: 40px; max-width: 100%" src="'.$shipment['carrier']['logo'].'" title="'.$shipment['carrier']['name'].'">';
    } else {
        echo '<p>Transporte: <b>'.$shipment['carrier']['name'].'</b>';
    }
    echo '<br>Tipo de servicio: <b>'.$shipment['service_type'].'</b>';
    echo '<br>Costo de envío: <b>$'.$shipment['price_incl_tax'].'</b> <small style="color:#777"> ($'.$shipment['price'].'+IVA)</small>';
    echo '<br>Origen: <b>'.$shipment['origin']['name'].'</b>';
    echo '<br>Destino: <br>&nbsp;&nbsp;&nbsp;&nbsp;<b>'.$shipment['destination']['name'].'</b><br>
            &nbsp;&nbsp;&nbsp;&nbsp;'.$shipment['destination']['street'].' '.$shipment['destination']['street_number'].' '.$shipment['destination']['street_extras'].'<br>
            &nbsp;&nbsp;&nbsp;&nbsp;'.$shipment['destination']['city'].', '.$shipment['destination']['state'].' ('.$shipment['destination']['zipcode'].')';

    echo '<p>';
    if (in_array($shipment['status'],['documentation_ready','ready_to_ship'])) {
        echo '<a class="button button-primary" target="_blank" href="https://app.'.$zippin_domain['domain'].'/shipments/' . $shipment['id'] . '/download_documentation?format=pdf">Descargar Etiquetas</a>';
    }
    echo '<a class="button" target="_blank" href="'.$detail_url.'">Ver Detalle</a> <a class="button" target="_blank" href="'.$shipment['tracking'].'">Ver Tracking</a></p>';

}



function add_action_button($actions, $order)
{
    $order_shipping_methods = $order->get_shipping_methods();
    $chosen_shipping_method = reset($order_shipping_methods);
    $zippin_domain = Helper::get_current_domain();

    if (!$chosen_shipping_method) {
        return $actions;
    }
    $chosen_shipping_method_id = $chosen_shipping_method->get_method_id();
    $chosen_shipping_method = explode("|", $chosen_shipping_method_id);
    if ($chosen_shipping_method[0] === 'zippin') {
        $shipment_info = $order->get_meta('zippin_shipment', true);
        if ($shipment_info) {
            $shipment_info = unserialize($shipment_info);
            $actions['zippin-label'] = array(
                'url' => 'https://app.'.$zippin_domain['domain'].'/shipments/'.$shipment_info['id'].'/download_documentation?format=pdf',
                'name' => 'Obtener documentación Zipnova',
                'action' => 'zippin-label',
            );
        }
    }
    return $actions;
}

function add_button_css_file($hook)
{
    if ($hook !== 'edit.php') return;
    wp_enqueue_style('action-button.css', plugin_dir_url(__FILE__) . 'css/action-button.css', array(), 1.0);
}

function activate_plugin()
{
    global $wp_version;

    if (version_compare(PHP_VERSION, '7.0', '<')) {
        $flag = 'PHP';
        $version = '7.0';

    } else if (version_compare($wp_version, '4.9', '<')) {
        $flag = 'WordPress';
        $version = '4.9';

    } else {
        if (defined('ZIPPIN_APIKEY') && defined('ZIPPIN_SECRETKEY')  && defined('ZIPPIN_DOMAIN')
            && !empty('ZIPPIN_APIKEY') && !empty('ZIPPIN_SECRETKEY') && !empty('ZIPPIN_DOMAIN')
            && empty(get_option('zippin_api_key')) && empty(get_option('zippin_api_secret'))  && empty(get_option('zippin_domain'))) {
            // Get basic settings from constants instead of the database
            update_option('zippin_api_key', ZIPPIN_APIKEY);
            update_option('zippin_api_secret', ZIPPIN_SECRETKEY);
            update_option('zippin_domain', ZIPPIN_DOMAIN);
        }

        if (!empty(get_option('zippin_api_key')) && !empty(get_option('zippin_api_secret')) && !empty(get_option('zippin_domain'))) {
            // Check basic settings are complete
            update_option('zippin_credentials_check', true);
        }

        $delivery_zones = \WC_Shipping_Zones::get_zones();

        foreach ($delivery_zones as $zone_id => $zone_data ) {
            if (in_array($zone_data['zone_name'], ['Argentina', 'Chile', 'México', 'Mexico'] )) {
                // Adding zippin to the first available zone
                $zone = \WC_Shipping_Zones::get_zone($zone_id);
                $methods = $zone->get_shipping_methods();
                foreach ($methods as $method) {
                    if ($method->id == 'zippin') {
                        return;
                    }
                }
                $zone->add_shipping_method('zippin');
                $zone->save();
                return;
            }
        }

        return;
    }

    deactivate_plugins(basename(__FILE__));
    wp_die('<p><strong>Zipnova</strong> Requiere al menos ' . $flag . ' version ' . $version . ' o mayor.</p>', 'Plugin Activation Error', array('response' => 200, 'back_link' => true));

}

function create_shortcode()
{
    $content = '';

    if (isset($_REQUEST['zippin_tracking_order_id'],$_REQUEST['zippin_tracking_order_email'])) {

        $order = wc_get_order(filter_var($_REQUEST['zippin_tracking_order_id'],FILTER_SANITIZE_NUMBER_INT));

        if (!$order) {
            echo '<p class="zipnova-tracking-result-error zippin-tracking-result-error">No se encontró una orden con los datos ingresados.</p>';

        } elseif ($order->get_billing_email() != sanitize_email($_REQUEST['zippin_tracking_order_email'])) {
            echo '<p class="zipnova-tracking-result-error zippin-tracking-result-error">No se encontró una orden con los datos ingresados.</p>';

        } elseif(!$order->get_meta('zippin_shipment', true)) {
            echo '<p class="zipnova-tracking-result-error zippin-tracking-result-error">No se encontró hay datos de envío para la orden ingresada.</p>';

        } else {
            $shipment = unserialize($order->get_meta('zippin_shipment', true));
            $connector = new ZippinConnector;
            $shipment = $connector->get_shipment($shipment['id']);
            $order->update_meta_data('zippin_shipment', serialize($shipment));
            $order->save();

            $content.= '<div class="zipnova-tracking-result zippin-tracking-result">
                    <h4>Orden #'.$order->get_id().'</h4>
                    <p>Estado del envío: <b>'.$shipment['status_name'].'</b></p>
                    <p><a href="'.$shipment['tracking_external'].'" class="button" target="_blank">Realizar seguimiento</a></p>
            </div>';
        }

        $content .= '<hr>';

    }

    $content .= '<form method="post" class="zipnova-tracking-form zippin-tracking-form" action="'.get_permalink().'">
        <div class="wc-block-components-text-input">
		<input id="zn_order_id" type="text" value="'.(isset($_REQUEST['zippin_tracking_order_id']) ? filter_var($_REQUEST['zippin_tracking_order_id'],FILTER_SANITIZE_NUMBER_INT) : '').'" name="zippin_tracking_order_id" style="width:40%" class="zipnova-tracking-form-field zippin-tracking-form-field" placeholder="Ingresa número de orden">
		</div>
		<div class="wc-block-components-text-input">
		<input id="zn_order_email" type="email" value="'.(isset($_REQUEST['zippin_tracking_order_email']) ? sanitize_email($_REQUEST['zippin_tracking_order_email']) : '').'" name="zippin_tracking_order_email" style="width:40%" class="zipnova-tracking-form-field zippin-tracking-form-field" placeholder="Ingresa el e-mail de la compra">
		</div>
		<br />
		<input type="submit" value="Consultar estado" id="update_button" class="wc-block-components-button wp-element-button zipnova-tracking-form zippin-tracking-form-submit update_button" style="cursor: pointer;"/>
		</form>';

    return $content;
}

function add_plugin_column_links($links)
{
    $links[] = '<a href="' . esc_url(get_admin_url(null, 'options-general.php?page=zippin_settings')) . '">Configurar</a>';
    return $links;
}

function add_plugin_description_links($meta, $file, $data, $status)
{
    if ($data['TextDomain'] == 'zippin_woocommerce') {
        $meta[] = '<a href="' . esc_url('https://ayuda.zipnova.com/instalaci%C3%B3n-y-uso-del-plugin-para-woocommerce') . '">Guía de configuración</a>';
        $meta[] = '<a href="' . esc_url('https://ayuda.zipnova.com/problemas-comunes-con-el-plugin-de-woocommerce') . '">Resolver problemas</a>';
    }
    return $meta;
}

function zippin_add_free_shipping_label($label, $method)
{
    $label_tmp = explode(':', $label);
    if ($method->get_cost() == 0 && get_option('zippin_create_free_shipments') == 'yes') {
        //$label = $label_tmp[0] . __(' - ¡Envío Gratis!', 'woocommerce');
        // TODO: Agregar tiempo de entrega de opción ganadora al resultado de free shipping.
    }
    return $label;
}

function clear_cache()
{
    $packages = WC()->cart->get_shipping_packages();
    foreach ($packages as $key => $value) {
        $shipping_session = "shipping_for_package_$key";
        unset(WC()->session->$shipping_session);
    }
}

function handle_webhook()
{
    $raw_post = file_get_contents('php://input');
    $data = json_decode($raw_post, 1);
    wc_get_logger()->info('Incoming zippin webhook:' . wc_print_r($data, 1), array('source' => 'zippin'));

    if ($data['topic'] == 'status' || $data['topic'] == 'shipment') {
        $connector = new ZippinConnector;
        $shipment = $connector->get_shipment($data['data']['shipment_id']);

        if ($shipment) {
            $order = wc_get_order(filter_var($shipment['external_id'],FILTER_SANITIZE_NUMBER_INT));

            if ($order) {
                $order->update_meta_data('zippin_shipment', serialize($shipment));
                $order->save();
            }
        }
    }

    die();
}

function add_missing_states($states)
{

    // Chile
    if (get_option('zippin_domain')=='CL' && get_option('zippin_avoid_add_states')!=1) {
        $states['CL'] = Helper::get_states('CL');
    }

    return $states;


}