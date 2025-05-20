<?php

namespace Robokassa\Payment;

/**
 * Утилиты общего назначения для плагина Robokassa
 */
class Util {

    /**
     * Безопасный site_url() с принудительным HTTPS
     */
    public static function siteUrl(string $path = '', string $scheme = 'https'): string {
        $url = site_url($path, $scheme);
        return preg_replace('#^http://#', 'https://', $url);
    }

}
