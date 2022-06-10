<?php

declare(strict_types=1);

namespace Divido\Helper;

use Divido\MerchantSDK\Environment;

class DividoHelper
{
    const CALCULATOR_URL = "//cdn.divido.com/widget/v3/";

    const PLUGIN_VERSION = "2.3.4";

    public static function generateCalcUrl($tenant, $environment){

        $prefixes = [$tenant];
        if($environment !== Environment::PRODUCTION){
            $prefixes[] = $environment;
        }

        return sprintf("%s%s.calculator.js", self::CALCULATOR_URL, implode(".", $prefixes));
    }

    public static function getPluginVersion() {
        return self::PLUGIN_VERSION;
    }
}
