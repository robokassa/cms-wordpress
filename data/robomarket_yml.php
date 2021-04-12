<?php
header('Content-Type: application/xml');
header('Content-Description: File Transfer');
header('Content-Disposition: attachment; filename=robomarket'.date("d_m_Y_His").'.yml.xml');
header('Connection: Keep-Alive');
header('Expires: 0');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Pragma: public');
echo '<?xml version="1.0" encoding="utf-8"?>';
?>
            <yml_catalog date="2021-04-12 13:21">
          <shop>
            <name>draxon</name>
            <company>draxon</company>
            <url>http://wp.loc</url>
            <currencies><currency id="RUB" rate="1"/>
                </currencies>
            <categories><category id="1" parentId="0">Без рубрики</category>
            </categories>
            <cpa>1</cpa>
            <offers>
                      <offer id="18" available="true">
                        <name>наруто</name>
                        <description><![CDATA[]]></description>
                        <url>http://wp.loc/product/%d0%bd%d0%b0%d1%80%d1%83%d1%82%d0%be/</url>
                        <price>10</price>
                        <currencyId>RUB</currencyId>
                        <categoryId>0</categoryId><picture></picture>
                      </offer>
                      <offer id="10" available="true">
                        <name>Анимешка</name>
                        <description><![CDATA[Самая лучшая]]></description>
                        <url>http://wp.loc/product/%d0%b0%d0%bd%d0%b8%d0%bc%d0%b5%d1%88%d0%ba%d0%b0/</url>
                        <price>5</price>
                        <currencyId>RUB</currencyId>
                        <categoryId>0</categoryId><picture>http://wp.loc/wp-content/uploads/2021/02/4223-150x150.jpg</picture>
                      </offer>
                </offers>
          </shop>
        </yml_catalog>
        