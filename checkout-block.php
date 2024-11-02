<?php

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class WC_Robokassa_Blocks extends AbstractPaymentMethodType
{

    protected $name = 'robokassa';

    public function initialize()
    {
        $this->settings = get_option('woocommerce_robokassa_settings', []);
    }

    public function get_payment_method_script_handles()
    {

        wp_register_script(
            'robokassa-blocks-integration',
            plugin_dir_url(__FILE__) . 'blocks.js',
            [
                'wc-blocks-registry',
                'wc-settings',
                'wp-element',
                'wp-html-entities',
                'wp-i18n',
            ],
            null,
            true
        );

        return ['robokassa-blocks-integration'];
    }

    public function get_payment_method_data()
    {
        return [
            'title' => (get_option('RobokassaOrderPageTitle_robokassa', null)),
            'description' => (get_option('RobokassaOrderPageDescription_robokassa', null)),
        ];
    }

}
