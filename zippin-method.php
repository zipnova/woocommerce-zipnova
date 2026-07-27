<?php

namespace Zippin\Zippin;

use WC_Shipping_Method;
use Zippin\Zippin\ZippinConnector;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

function zippin_init()
{
    if (!class_exists('WC_Zippin')) {
        class WC_Zippin extends WC_Shipping_Method
        {

            private $logger;

            public function __construct($instance_id = 0)
            {
                $this->id = 'zippin';
                $this->method_title = 'Zipnova';
                $this->method_description = 'Envíos con Zipnova';
                $this->title = 'Envío con Zipnova';
                $this->instance_id = absint($instance_id);
                $this->supports = array(
                    'shipping-zones',
                    'instance-settings',
                    'instance-settings-modal'
                );
                $this->logger = wc_get_logger();
                $this->init();
                add_action('woocommerce_update_options_shipping_zippin', array($this, 'process_admin_options'));
            }

            function init()
            {
                $this->form_fields = array();
                $this->instance_form_fields = array(
                    'service_types' => array(
                        'title' => __('Tipos de Servicio Habilitados', 'woocommerce'),
                        'description'=>'Selecciona los tipos de servicio que quieras ofrecer. Selecciona múltiples opciones manteniendo la tecla CTRL.',
                        'type' => 'multiselect',
                        'default' => array('standard_delivery','express_delivery','pickup_point'),
                        'options' => array(
                            'standard_delivery' => 'Entrega a domicilio estándar',
                            'express_delivery' => 'Entrega rápida',
                            'pickup_point' => 'Entrega en sucursal',
                        )
                    ),
                );
                /*
                $classes = WC()->shipping->get_shipping_classes();
                foreach ($classes as $class) {
                    $this->instance_form_fields['clase']['options'][$class->name] = $class->name;
                }
				*/
            }

            public function calculate_shipping($package = array())
            {

                // Prepare packages
                $products = $this->get_products_from_cart();

                if (!$products) {
                    // No shippable products to quote
                    return;
                }

                // Create destination object
                $billing_address = [
                    'city' => WC()->customer->get_billing_city(),
                    'state' => WC()->customer->get_billing_state(),
                    'zipcode' => WC()->customer->get_billing_postcode(),
                    'country' => WC()->customer->get_billing_country()
                ];

                $shipping_address = [
                    'city' => WC()->customer->get_shipping_city(),
                    'state' => WC()->customer->get_shipping_state(),
                    'zipcode' => WC()->customer->get_shipping_postcode(),
                    'country' => WC()->customer->get_shipping_country()
                ];

                $this->logger->debug('Quote Log - billing: '.wc_print_r(json_encode($billing_address), true).' - shipping: '.wc_print_r(json_encode($shipping_address), true), unserialize(ZIPPIN_LOGGER_CONTEXT));

                if (!empty($shipping_address['city']) && !empty($shipping_address['state'])) {
                    $destination = $shipping_address;
                } else {
                    $destination = $billing_address;
                }

                $destination['zipcode'] = filter_var($destination['zipcode'], FILTER_SANITIZE_NUMBER_INT);
                if ($destination['country'] == 'CL') {
                    unset($destination['zipcode']);
                }
                $destination['state'] = Helper::get_state_name($destination['state']);

                // Get declared value
                $declared_value = WC()->cart->get_subtotal();
                if (!empty($declared_value)) {
                    $declared_value_modifier = get_option('zippin_insurance_modifier', 100)/100;
                    $declared_value = max(0, $declared_value * $declared_value_modifier);
                    $declared_value = number_format($declared_value, 2, '.', '');
                }

                // Quote and get results
                $quote_results = (new ZippinConnector)->quote(
                    $destination,
                    [],
                    $products['items'],
                    $declared_value,
                    $this->get_instance_option('service_types'),
                    get_option('zippin_options_mix'),
                    (int)get_option('zippin_options_mix_count', 1)
                );

                if ($quote_results) {
                    if (!empty(get_option('zippin_additional_charge', 0))) {
                        $additional_charge = true;
                    } else {
                        $additional_charge = false;
                    }

                    $use_free_shipping = false;
                    if (get_option('zippin_free_shipping_threshold')) {
                        if (WC()->cart->get_subtotal() >= floatval(get_option('zippin_free_shipping_threshold'))) {
                            $use_free_shipping = true;
                        }
                    }

                    foreach ($quote_results as $result) {

                        /** @var \DateInterval $shipping_time */
                        $shipping_time = $result['shipping_time'];

                        /** @var \DateTime $delivery_date */
                        $delivery_date = $result['delivery_date'];

                        // Texto tiempo de entrega
                        if ($result['result']['service_type']['code'] == 'pickup_point') {
                            $time = 'disponible '.$this->localize_date($delivery_date, $shipping_time);
                        } else {
                            $time = 'llega '.$this->localize_date($delivery_date, $shipping_time);
                        }

                        // Costo
                        $cost = (isset($result['price']) ? $result['price'] : 0);

                        if ($use_free_shipping) {
                            $cost = 0;

                        } elseif ($additional_charge){
                            $operator = get_option('zippin_additional_charge_operation', 'add');
                            if ($operator == 'sub') {
                                $sign = -1;
                            } else {
                                $sign = 1;
                            }

                            if (get_option('zippin_additional_charge_type', 'rel') == 'abs') {
                                // Cambio en valor absoluto
                                $cost = $cost + get_option('zippin_additional_charge', 0) * $sign;

                            } else {
                                // Cambio en valor relativo
                                $cost = $cost + $cost * get_option('zippin_additional_charge', 0)/100 * $sign;
                            }

                            $cost = max(0, $cost);
                        }

                        // Armado de rates
                        if ($result['result']['service_type']['code'] == 'pickup_point') {
                            $i=1;
                            $max_pickup_points = intval(get_option('zippin_max_pickup_points', 3));
                            foreach ($result['result']['pickup_points'] as $point) {
                                if ($i>$max_pickup_points) { continue; }
                                $address = $point['location']['street'].' '.$point['location']['street_number'].', '.$point['location']['city'];
                                $rate = array(
                                    'id' => 'zippin|' . (isset($result['code']) ? $result['code'] : '').'|'.$point['point_id'],
                                    'label' => $result['service_name'] . ' - '. $point['description'] . ' - '. $address.', ' . $time,
                                    'cost' => $cost,
                                    'calc_tax' => 'per_order'
                                );

                                $this->add_rate($rate);
                                $i++;
                            }

                        } else {
                            $rate = array(
                                'id' => 'zippin|' . (isset($result['code']) ? $result['code'] : '').'|x',
                                'label' => $result['service_name'] . ', ' . $time,
                                'cost' => $cost,
                                'calc_tax' => 'per_order'
                            );

                            $this->add_rate($rate);
                        }
                    }
                }
            }

            public function get_products_from_cart()
            {
                $helper = new Helper();
                return $helper->get_items_from_cart();
            }

            private function localize_date(\DateTime $datetime, \DateInterval $diff_to_now)
            {

                $datetime = $datetime->setTimezone(new \DateTimeZone(wp_timezone_string()));

                if ($diff_to_now->days < 7) {
                    // Responder con fecha relativa o dia de semana
                    if ($diff_to_now->days < 1) {
                        return 'hoy';
                    } elseif ($diff_to_now->days > 1) {
                        return 'el '. __($datetime->format('l'));
                    } else {
                        return 'mañana';
                    }

                } else {
                    // Responder con la fecha exacta
                    return 'el '.$datetime->format('d').' de '.strtolower(__($datetime->format('F')));
                }


            }

        }
    }
}
