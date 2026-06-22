<?php

namespace Zippin\Zippin\Settings;

use Zippin\Zippin\Helper;
use Zippin\Zippin\ZippinConnector;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

function init_settings()
{
    register_setting('zippin_main_section', 'zippin_other_options');

    add_settings_section(
        'zippin_main_section',
        'Configuración',
        __NAMESPACE__ . '\print_instructions',
        'zippin_settings'
    );

    add_settings_field(
        'country',
        'País de la cuenta',
        __NAMESPACE__ . '\select_country',
        'zippin_settings',
        'zippin_main_section'
    );

    add_settings_field(
        'account_id',
        'ID de Cuenta',
        __NAMESPACE__ . '\print_account_id',
        'zippin_settings',
        'zippin_main_section'
    );

    add_settings_field(
        'api_key',
        'Credenciales API v2 ',
        __NAMESPACE__ . '\print_credentials',
        'zippin_settings',
        'zippin_main_section'
    );

    add_settings_field(
        'origin_id',
        'Origen predeterminado para envíos',
        __NAMESPACE__ . '\print_origins',
        'zippin_settings',
        'zippin_main_section'
    );

    /*
    add_settings_field(
        'packaging_mode',
        'Agrupación en paquetes',
        __NAMESPACE__ . '\print_packaging_mode',
        'zippin_settings',
        'zippin_main_section'
    );
    */

    add_settings_field(
        'options_mix',
        'Resultados a mostrar',
        __NAMESPACE__ . '\print_options_mix',
        'zippin_settings',
        'zippin_main_section'
    );


    add_settings_field(
        'default_shipping_status',
        'Estado de pedido para crear en Zipnova',
        __NAMESPACE__ . '\print_default_shipping_status',
        'zippin_settings',
        'zippin_main_section'
    );

    add_settings_field(
        'enable_free_shipping_creation',
        'Crear los envíos gratuitos con Zipnova',
        __NAMESPACE__ . '\print_free_shipping_creation',
        'zippin_settings',
        'zippin_main_section'
    );

    add_settings_field(
        'free_shipping_threshold',
        'Ofrecer envío gratis según valor de orden',
        __NAMESPACE__ . '\print_free_shipping_threshold',
        'zippin_settings',
        'zippin_main_section'
    );

    add_settings_field(
        'additional_charge',
        'Modificar el precio del envío',
        __NAMESPACE__ . '\print_additional_charge',
        'zippin_settings',
        'zippin_main_section'
    );

    add_settings_field(
        'insurance_modifier',
        'Valor declarado (seguro)',
        __NAMESPACE__ . '\print_insurance_modifier',
        'zippin_settings',
        'zippin_main_section'
    );


    add_settings_field(
        'webhook_instructions',
        'Sincronización entre Zipnova y WooCommerce',
        __NAMESPACE__ . '\print_webhooks_instructions',
        'zippin_settings',
        'zippin_main_section'
    );

    add_settings_field(
        'servicetype_instructions',
        'Tipos de servicio habilitados',
        __NAMESPACE__ . '\print_servicetype_instructions',
        'zippin_settings',
        'zippin_main_section'
    );


    add_settings_field(
        'extra_info',
        'Integrar tracking',
        __NAMESPACE__ . '\print_extra_info',
        'zippin_settings',
        'zippin_main_section'
    );

    add_settings_field(
        'advanced_feature_flags',
        'Opciones avanzadas',
        __NAMESPACE__ . '\advanced_feature_flags',
        'zippin_settings',
        'zippin_main_section'
    );


}


function print_instructions()
{
    echo '<p>Para continuar deberás crear credenciales de API v2.</p><p>Deberás crear un nuevo <b>token de cuenta</b> desde la sección de Configuración &gt; Integraciones &gt; Credenciales y Webhooks.</p> ';
}

function select_country()
{
    $previous_config = get_option('zippin_domain');
    echo '<select name="zippin_domain" required>';
    echo '<option>Seleccionar un país</option>';
    foreach (Helper::get_domains() as $id => $domain_data) {
        if ($previous_config) {
            echo '<option value="' . $id . '" ' . ($previous_config === $id ? 'selected' : '') . '>' . $domain_data['name'] . '</option>';
        } else {
            echo '<option value="' . $id . '">' . $domain_data['name'] . '</option>';
        }
    }
    echo '</select>';
}

function advanced_feature_flags()
{
    if (get_option('zippin_domain')=='CL') {
        echo '<p style="margin-bottom: 16px"><label><input type="checkbox" name="zippin_avoid_add_states" value="1"' . (get_option('zippin_avoid_add_states') == 1 ? ' checked' : '') . '> Compatibilidad: Evitar agregar estados/regiones/provincias faltantes. <br><small>Activa esta opción si tienes otro plugin que también agregue los estados que WooCommerce no trae</small>.</label></p> ';
    }
    echo '<p style="margin-bottom: 16px"><label><b>Campo personalizado con el DNI/RUT del cliente</b><br><small>Si tienes algún plugin o personalización para capturar el número de documento del destinatario, indica aquí el código del campo personalizado de la orden.</small><br><input type="text" name="zippin_document_field" placeholder="Indica el código del campo personalizado" value="'.(get_option('zippin_document_field')).'"></label></p> ';
    echo '<p style="margin-bottom: 16px"><label><b>Cantidad máxima de puntos de retiro</b><br><small>Límite de opciones de puntos de retiro (pickup points) que se mostrarán al cliente en el checkout.</small><br><input type="number" min="1" name="zippin_max_pickup_points" value="'.intval(get_option('zippin_max_pickup_points', 3)).'"></label></p>';
}

function print_credentials()
{
    $previous_key = get_option('zippin_api_key');
    $previous_secret = get_option('zippin_api_secret');

    echo '<p style="margin-bottom: 12px;"><small>API KEY</small><br><input type="text" required name="api_key" value="' . ($previous_key ? $previous_key : '') . '" /></p>';
    echo '<p style="margin-bottom: 12px;"><small>API SECRET</small><br><input type="text" required name="api_secret" value="' . ($previous_secret ? $previous_secret : '') . '" /></p>';

}

function print_account_id()
{
    $previous_config = get_option('zippin_account_id');
    echo '<input type="number" required name="account_id" value="' . ($previous_config ? $previous_config : '') . '" />
    <br><small>Lo encontrarás a la derecha en la página de credenciales y webhooks.</small>';

    if (get_option('zippin_account_id')) {
        $connector = new ZippinConnector;
        $account = $connector->get_account();
        if ($account) {
            echo '<div class="help-box success-text">Cuenta conectada: <b>'.$account['name'].'</b></div>';
        } else {
            echo '<div class="help-box danger-text">Cuenta: <b>SIN AUTORIZACIÓN</b><br>
                Verifica que las credenciales sean válidas y correspondan a la cuenta indicada y el país correcto.</div>';
        }
    }

}

function print_webhooks_instructions()
{
    echo '<div class="help-box warning-text">Para mantener WooCommerce sincronizado con Zipnova es necesario configurar un webhook. Ingresa a a tu cuenta de Zipnova y ve a Configuración &gt; Integraciones &gt; Credenciales y Webhooks, y a la derecha, en Webhooks, ve a crear un nuevo webhook.';
    echo '<br>Configura tu webhook de la siguiente manera: <br>topic: <b>shipment</b> <br>URL: <strong>' . get_site_url(null, '?wc-api=zippin') . '</strong></div>';
}

function print_servicetype_instructions()
{
    echo '<div class="help-box warning-text">Para gestionar los tipos de servicio habilitados ingresa a los <a href="admin.php?page=wc-settings&tab=shipping" target="_blank">ajustes de envío de WooCommerce</a>, ingresa a la zona que quieras modificar, y en el metodo de envío <b>Envío con Zipnova</b> (o Zippin) (clic en Editar) selecciona los tipos de servicio que quieras habilitar.</div>';
}


function print_options_mix()
{
    $previous_config = get_option('zippin_options_mix');
    echo '<p><label><input type="radio" required name="options_mix" value="first_by_service"'.($previous_config=='first_by_service' || empty($previous_config) ? ' checked':'').'> La mejor opcion por cada tipo de servicio</label></p>';
    echo '<p><label><input type="radio" required name="options_mix" value="all"'.($previous_config=='all' ? ' checked':'').'> Mostrar todas las opciones de todos los servicios</label></p>';
    echo '<p><label><input type="radio" required name="options_mix" value="first"'.($previous_config=='first' ? ' checked':'').'> Mostrar una cantidad maxima de opciones:</label></p>';
    echo '<p><small>Cantidad máxima de opciones</small><br><input type="number"  min="1" name="options_mix_count" value="' . get_option('zippin_options_mix_count',1) . '" /></p>';
}


function print_default_shipping_status()
{
    $statuses = wc_get_order_statuses();
    $previous_config = get_option('zippin_shipping_status');
    if (!$previous_config) update_option('zippin_shipping_status', 'wc-completed');
    echo '<select name="shipping_status">';
    foreach ($statuses as $status_key => $status_name) {
        if ($previous_config) {
            echo '<option value="' . $status_key . '" ' . ($previous_config === $status_key ? 'selected' : '') . '>' . $status_name . '</option>';
        } else {
            echo '<option value="' . $status_key . '" ' . ($status_key === 'wc-completed' ? 'selected' : '') . '>' . $status_name . '</option>';
        }
    }
    echo '</select>';
    echo '<div class="help-box info-text">Los pedidos con este estado serán enviados automáticamente a Zipnova</div>';
}

function print_free_shipping_creation()
{
    $previous_config = get_option('zippin_create_free_shipments');
    echo '<p><label><input type="radio" name="enable_free_shipping_creation" value="yes"'.($previous_config=='yes' ? ' checked':'').'> Si</label> ';
    echo '&nbsp;&nbsp;&nbsp;&nbsp;<label><input type="radio" name="enable_free_shipping_creation" value="no"'.($previous_config=='no' || empty($previous_config) ? ' checked':'').'> No</label></p>';
    echo '<div class="help-box info-text">Si habilitas esta opción, los envíos con Envío gratis se crearán en Zipnova. La selección del transporte será automática según la configuración de tu cuenta. <br>
<small>Aprende <a href="https://docs.woocommerce.com/document/free-shipping/" target="_blank">cómo configurar envío gratis en Woocomerce</a> y configura en Zipnova tus opciones de selección de transporte (solapa Opciones).</small>
</div>';
}

function print_additional_charge(){
	$previous_config = get_option('zippin_additional_charge_operation', 'add');
    echo '<select name="additional_charge_operation">';
    echo '<option value="add" ' . ($previous_config === 'add' ? 'selected' : '') . '>Sumar (+)</option>';
    echo '<option value="sub" ' . ($previous_config === 'sub' ? 'selected' : '') . '>Restar (-)</option>';
    echo '</select>';

    $previous_config = get_option('zippin_additional_charge', '0');
    echo '<br><input type="number" required name="additional_charge" min="0" value="' . (isset($previous_config) ? $previous_config : 0) . '" />';

    $previous_config = get_option('zippin_additional_charge_type','rel');
    echo '<br><select name="additional_charge_type">';
    echo '<option value="rel" ' . ($previous_config === 'rel' ? 'selected' : '') . '>%</option>';
    echo '<option value="abs" ' . ($previous_config === 'abs' ? 'selected' : '') . '>$</option>';
    echo '</select>';

    echo '<div class="help-box info-text">Usa esta opción si quieres cobrarle un precio de envío distinto tu cliente. Zipnova te cobrará el precio real que corresponda para el envío.</div>';

}

function print_insurance_modifier(){
    $previous_config = get_option('zippin_insurance_modifier', 100);
    echo '<input type="number" required min="0" name="insurance_modifier" value="' . (get_option('zippin_insurance_modifier', 100)) . '" /> % (del subtotal de la orden)
	<div class="help-box info-text">Indicar el porcentaje del subtotal de la orden que quieras declarar para el seguro. Si indicas 0% significa que no quieres asegurar el envío. 100% significa que el valor declarado es equivalente al subtotal de la orden.</div>';
}

function print_free_shipping_threshold()
{
    $previous_config = get_option('zippin_free_shipping_threshold');
    echo '<input type="number" name="free_shipping_threshold" value="' . ($previous_config ? $previous_config : '') . '" />
	<div class="help-box info-text">Hacer que el precio del envío se muestre como gratis si el total de la orden es igual o mayor al valor indicado. Dejar en blanco para desactivar opción.</div>';
}

function print_origins()
{
    $previous_config = get_option('zippin_origin_id');
    if (get_option('zippin_api_key') && get_option('zippin_api_secret') && get_option('zippin_account_id')) {
        $connector = new ZippinConnector;
        $addresses = $connector->get_origins();
        if ($addresses) {
            echo '<select name="origin_id" required>';
        } else {
            echo '<select name="origin_id">';
        }
        echo '<option value="">Seleccionar Origen...</option>';
        foreach ($addresses as $address) {
            $show_as = '['.$address['id'].'] '.$address['name'].' - '.$address['street'].' '.$address['street_number'].', '.$address['city']['name'];
            if ($previous_config) {
                if ($previous_config == $address['id']) {
                    echo '<option value="' . $address['id'] . '" selected>' . $show_as . '</option>';
                } else {
                    echo '<option value="' . $address['id'] . '">' . $show_as . '</option>';
                }
            } else {
                if (count($addresses)===1) {
                    echo '<option value="' . $address['id'] . '" selected>' . $show_as . '</option>';
                    update_option('zippin_origin_id', $address['id']);
                } else {
                    echo '<option value="' . $address['id'] . '">' . $show_as . '</option>';
                }
            }
        }
        echo '</select>';
    } else {
        echo '<div class="help-box warning-text">Luego de guardar tus credenciales te mostraremos aquí los orígenes disponibles y podrás seleccionar uno.</div>';
    }

}


function print_extra_info()
{
    echo '<div class="help-box warning-text">Al instalar este plugin podrás empezar a usar el shortcode <code>[zipnova_tracking]</code>. Coloca este shortcode en cualquier página que desees usar para crear un formulario de seguimiento de pedidos de Zipnova.</div>';
}

function create_menu_option()
{
    add_menu_page(
        'Configuración de Zipnova',
        'Envíos con Zipnova',
        'manage_woocommerce',
        'zippin_settings',
        __NAMESPACE__ . '\settings_page_content',
		'dashicons-store'
    );
}

function settings_page_content()
{
    if (!current_user_can('manage_woocommerce')) {
        return;
    }

    // Save country
    if (isset($_POST['zippin_domain'])) {
        wp_verify_nonce($_REQUEST['zippin_wpnonce'], 'zippin_settings_save' );
        update_option('zippin_domain', sanitize_text_field($_POST['zippin_domain']));
    }


    // Save api_key
    if (isset($_POST['api_key'])) {
        wp_verify_nonce($_REQUEST['zippin_wpnonce'], 'zippin_settings_save' );
        update_option('zippin_api_key', sanitize_text_field($_POST['api_key']));
    }

	// Save api_secret
    if (isset($_POST['api_secret'])) {
        wp_verify_nonce($_REQUEST['zippin_wpnonce'], 'zippin_settings_save' );
        update_option('zippin_api_secret', sanitize_text_field($_POST['api_secret']));
    }

    // Save account_id
    if (isset($_POST['account_id'])) {
        wp_verify_nonce($_REQUEST['zippin_wpnonce'], 'zippin_settings_save' );
        if (get_option('zippin_account_id') != filter_var($_POST['account_id'],FILTER_SANITIZE_NUMBER_INT)) {
            delete_option('zippin_origin_id');
        }
        update_option('zippin_account_id', filter_var($_POST['account_id'],FILTER_SANITIZE_NUMBER_INT));
    }


    if (isset($_POST['api_key']) && isset($_POST['api_secret']) && isset($_POST['account_id'])) {
        $connector = new ZippinConnector;
        $account = $connector->get_account();
        if ($account) {
            update_option('zippin_credentials_check',true);
        } else {
            update_option('zippin_credentials_check',false);
        }
    }

    // Save origin id
    if (isset($_POST['origin_id'])) {
        wp_verify_nonce($_REQUEST['zippin_wpnonce'], 'zippin_settings_save' );
        update_option('zippin_origin_id', filter_var($_POST['origin_id'],FILTER_SANITIZE_NUMBER_INT));
    }

	// Save packaging mode
    if (isset($_POST['packaging_mode'])) {
        wp_verify_nonce($_REQUEST['zippin_wpnonce'], 'zippin_settings_save' );
        update_option('zippin_packaging_mode', sanitize_text_field($_POST['packaging_mode']));
    }

    // Save result options mix
    if (isset($_POST['options_mix'])) {
        wp_verify_nonce($_REQUEST['zippin_wpnonce'], 'zippin_settings_save' );
        update_option('zippin_options_mix', sanitize_text_field($_POST['options_mix']));
    }

    if (isset($_POST['options_mix_count'])) {
        wp_verify_nonce($_REQUEST['zippin_wpnonce'], 'zippin_settings_save' );
        update_option('zippin_options_mix_count', sanitize_text_field($_POST['options_mix_count']));
    }


	// Save shipping status
    if (isset($_POST['shipping_status'])) {
        wp_verify_nonce($_REQUEST['zippin_wpnonce'], 'zippin_settings_save' );
        update_option('zippin_shipping_status', sanitize_text_field($_POST['shipping_status']));
    }

    // Save shipping status
    if (isset($_POST['enable_free_shipping_creation'])) {
        wp_verify_nonce($_REQUEST['zippin_wpnonce'], 'zippin_settings_save' );
        update_option('zippin_create_free_shipments', sanitize_text_field($_POST['enable_free_shipping_creation']));
    }


    // insurance_modifier
    if (isset($_POST['insurance_modifier'])) {
        wp_verify_nonce($_REQUEST['zippin_wpnonce'], 'zippin_settings_save' );
        update_option('zippin_insurance_modifier', filter_var($_POST['insurance_modifier'],FILTER_SANITIZE_NUMBER_INT));
    }

    // aditional charge
    if (isset($_POST['additional_charge'])) {
		wp_verify_nonce($_REQUEST['zippin_wpnonce'], 'zippin_settings_save' );
        update_option('zippin_additional_charge', filter_var($_POST['additional_charge'],FILTER_SANITIZE_NUMBER_INT));
    }

    if (isset($_POST['additional_charge_type'])) {
        wp_verify_nonce($_REQUEST['zippin_wpnonce'], 'zippin_settings_save' );
        update_option('zippin_additional_charge_type', sanitize_text_field($_POST['additional_charge_type']));
    }

    if (isset($_POST['additional_charge_operation'])) {
        wp_verify_nonce($_REQUEST['zippin_wpnonce'], 'zippin_settings_save' );
        update_option('zippin_additional_charge_operation', sanitize_text_field($_POST['additional_charge_operation']));
    }

    if (isset($_POST['free_shipping_threshold'])) {
        wp_verify_nonce($_REQUEST['zippin_wpnonce'], 'zippin_settings_save' );
        update_option('zippin_free_shipping_threshold', filter_var($_POST['free_shipping_threshold'],FILTER_SANITIZE_NUMBER_FLOAT));
    }

    if (isset($_POST['zippin_avoid_add_states'])) {
        wp_verify_nonce($_REQUEST['zippin_wpnonce'], 'zippin_settings_save' );
        update_option('zippin_avoid_add_states', $_POST['zippin_avoid_add_states'] == '1' ? 1 : 0);
    }

    if (isset($_POST['zippin_document_field'])) {
        wp_verify_nonce($_REQUEST['zippin_wpnonce'], 'zippin_settings_save' );
        update_option('zippin_document_field', sanitize_text_field($_POST['zippin_document_field']));
    }

    if (isset($_POST['zippin_max_pickup_points'])) {
        wp_verify_nonce($_REQUEST['zippin_wpnonce'], 'zippin_settings_save' );
        update_option('zippin_max_pickup_points', max(1, filter_var($_POST['zippin_max_pickup_points'], FILTER_SANITIZE_NUMBER_INT)));
    }

    ?>

	<div class="wrap">
        <img src="<?=plugin_dir_url(__FILE__) ?>images/zipnova.png" height="50"/>
        <div class="help-box info-text">
            <b style="font-size: 14px">Ayuda</b><br>
            <a target="_blank" href="https://ayuda.zipnova.com/instalaci%C3%B3n-y-uso-del-plugin-para-woocommerce">Guía de configuración del plugin</a> |
            <a target="_blank" href="https://ayuda.zipnova.com/problemas-comunes-con-el-plugin-de-woocommerce">Resolver un problema</a> |
            Versión actual del plugin: <?php echo ZIPPIN_VERSION; ?>
        </div>

        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
		<form action="options-general.php?page=zippin_settings" method="post">
        <?php
        wp_enqueue_style('admin.css', plugin_dir_url(__FILE__) . 'css/admin.css', array(), ZIPPIN_VERSION);
        wp_nonce_field('zippin_settings_save','zippin_wpnonce',false,true);
        settings_fields('zippin_settings');
        do_settings_sections('zippin_settings');
        submit_button('Guardar');
        ?>
		</form>
	</div>
	<?php

}
