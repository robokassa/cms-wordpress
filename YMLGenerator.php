<?php

function robokassa_payment_generateYML()
{

    $saler_name = get_bloginfo('name');
    $company_name = trim(get_bloginfo('name'));
    $site_url = get_bloginfo('url');
    $cat_arr = array();

    $robomarketYML = \plugin_dir_path(__FILE__) . 'data/robomarket_yml.php';
    $robomarketYMLUrl = \plugin_dir_url(__FILE__) . 'data/robomarket_yml.php';

    //Получаем категории магазина для генерациии YML
    foreach (get_categories() as $value) {
        $cat_arr[$value->term_id]['name'] = html_entity_decode($value->name);
        $cat_arr[$value->term_id]['parentID'] = $value->parent;
    }

    //Получаем список валют для генерации YML
    $currencies['RUB'] = '1';

    //Получаем список продуктов для генерации YML
    $loop = new WP_Query(array(
            'post_type' => 'product',
            'posts_per_page' => 99999,
        )
    );

    $products = array();

    while ($loop->have_posts()) {
        $loop->the_post();

        $id = get_the_ID();

        $_product = wc_get_product($id);

        $product = array();

        if ($_product->get_stock_status() == 'instock') {
            $product['available'] = 'true';
        } else {
            $product['available'] = 'false';
        }

        $image = wp_get_attachment_image_src($_product->get_image_id());

        $product['name'] = $_product->get_name();
        $product['description'] = $_product->get_description();
        $product['url'] = get_permalink();
        $product['price'] = $_product->get_regular_price();;
        $product['currencyId'] = 'RUB';
        $product['categoryId'] = $_product->get_parent_id();
        $product['pictures'][$_product->get_image_id()] = $image[0];

        $product['params'] = array();

        foreach ($_product->get_attributes() as $value) {
            $data = $value->get_data();

            $product['params'][$data['name']] = array('value' => $data['value']);
        }

        $products[$id] = $product;
    }

    wp_reset_query();

    $offers = $products;

    $MEGAYML = '
            <yml_catalog date="' . date('Y-m-d H:i') . '">
          <shop>
            <name>' . $saler_name . '</name>
            <company>' . $company_name . '</company>
            <url>' . $site_url . '</url>
            <currencies>';

    foreach ($currencies as $key => $value) {
        $MEGAYML .= '<currency id="' . $key . '" rate="' . $value . '"/>';
    }

    $MEGAYML .= '
                </currencies>
            <categories>';

    foreach ($cat_arr as $key => $value) {
        if (isset($value['parentID']) || ($value['parentID'] != '0')) {
            $have_parent = 'parentId="' . $value['parentID'] . '"';
        } else {
            $have_parent = '';
        }
        $MEGAYML .= '<category id="' . $key . '" ' . $have_parent . '>' . $value['name'] . '</category>';
    }
    $MEGAYML .= '
            </categories>
            <cpa>1</cpa>
            <offers>';

    foreach ($offers as $key => $value) {
        $MEGAYML .= '
                      <offer id="' . $key . '" available="' . $value['available'] . '">
                        <name>' . $value['name'] . '</name>
                        <description><![CDATA[' . $value['description'] . ']]></description>
                        <url>' . $value['url'] . '</url>
                        <price>' . $value['price'] . '</price>
                        <currencyId>' . $value['currencyId'] . '</currencyId>
                        <categoryId>' . $value['categoryId'] . '</categoryId>';

        if (isset($value['pictures'])) {
            foreach ($value['pictures'] as $value1) {
                $MEGAYML .= '<picture>' . $value1 . '</picture>';
            }
        }

        if (isset($value['params'])) {
            foreach ($value['params'] as $key => $param) {
                if (isset($param['unit'])) {
                    $unit = ' unit="' . $param['unit'] . '"';
                } else {
                    $unit = '';
                }
                $MEGAYML .= '
                                                    <param name="' . $key . '"' . $unit . '>' . $param['value'] . '</param>';
            }
        }

        $MEGAYML .= '
                      </offer>';
    }
    $MEGAYML .= "
                </offers>";
    /**/
    $MEGAYML .= "
          </shop>
        </yml_catalog>
        ";
    $headers = "<?php
header('Content-Type: application/xml');
header('Content-Description: File Transfer');
header('Content-Disposition: attachment; filename=robomarket'.date(\"d_m_Y_His\").'.yml.xml');
header('Connection: Keep-Alive');
header('Expires: 0');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Pragma: public');
echo '<?xml version=\"1.0\" encoding=\"utf-8\"?>';
?>";
    $YML = fopen($robomarketYML, 'w');
    fwrite($YML, $headers . $MEGAYML);
    fclose($YML);
    echo "<script>document.location.href='" . $robomarketYMLUrl . "'</script>";
}
?>

<div class="content_holder">
    <h4>Сохраните автоматически сгенерированный каталог Вашего магазина на компьютер и следуйте инструкции на странице <a href="<?php echo admin_url('admin.php?page=robokassa_payment_robomarket_rb'); ?>">настроек экспорта в РобоМаркет</a></h4>
</div>
