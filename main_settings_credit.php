<div class="content_holder">

    <form action="options.php" method="POST">
        <?php wp_nonce_field('update-options'); ?>

        <?php

        if (!\current_user_can('activate_plugins')) {

            echo '<br /><br />
				<div class="error notice">
	                <p>У Вас не хватает прав на настройку компонента</p>
				</div>
			';
            return;
        }

        \wp_enqueue_script(
            'robokassa_payment_admin_main_payment',
            \plugin_dir_url(__FILE__) . 'assets/js/admin-payment.js'
        );

        \wp_enqueue_style(
            'robokassa_payment_admin_style_main',
            \plugin_dir_url(__FILE__) . 'assets/css/main.css'
        );

        /** @var array $formProperties */
        $formProperties = [
            'robokassa_podeli',
            'robokassa_payment_podeli_widget_onoff',
            'robokassa_podeli_widget_style',
            'robokassa_credit',
            'robokassa_payment_credit_widget_onoff',
            'robokassa_payment_credit_selected_method',
        ];


        $json_file_path = dirname(__FILE__) . '/data/currencies.json';
        $json_data = file_get_contents($json_file_path);
        $json_decoded = json_decode($json_data, true);

        $show_podeli = false;
        $show_credit = false;


        function searchAlias($data, &$show_podeli, &$show_credit) {
            foreach ($data as $key => $value) {
                if ($key === 'Alias') {
                    if ($value === 'Podeli') {
                        $show_podeli = true;
                    } elseif ($value === 'OTP') {
                        $show_credit = true;
                    }
                } elseif (is_array($value) || is_object($value)) {
                    searchAlias($value, $show_podeli, $show_credit);
                }
            }
        }

        searchAlias($json_decoded, $show_podeli, $show_credit);

        if ($show_podeli || $show_credit) {
            if ($show_podeli) {
                include dirname(__FILE__) . '/templates/podeli-menu-form.php';
            }

            if ($show_credit) {
                include dirname(__FILE__) . '/templates/credit-menu-form.php';
            }
        }
        else {
            echo 'Дополнительные методы оплаты в рассрочку или кредит не найдены!';
        }

        ?>

        <input type="hidden" name="action" value="update"/>
        <input type="hidden" name="page_options" value="<?php echo \implode(',', $formProperties); ?>"/>

        <p class="submit">
            <input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>"/>
        </p>
    </form>
</div>