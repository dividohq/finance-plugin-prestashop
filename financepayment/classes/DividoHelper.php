<?php

declare(strict_types=1);

namespace Divido\Helper;

use Divido\MerchantSDK\Environment;

class DividoHelper
{
    const V3_CALCULATOR_URL = "//cdn.divido.com/widget/v3/";

    const V4_CALCULATOR_URL = "//cdn.divido.com/widget/v4/divido.calculator.js";

    const PLUGIN_VERSION = "2.5.0";

    public static function generateCalcUrl($isV4Compatible, $tenant=null, $environment=null){
        if($isV4Compatible){
            return self::V4_CALCULATOR_URL;
        }

        $prefixes = [$tenant];
        if($environment !== Environment::PRODUCTION){
            $prefixes[] = $environment;
        }

        return sprintf("%s%s.calculator.js", self::V3_CALCULATOR_URL, implode(".", $prefixes));
    }

    public static function getPluginVersion() {
        return self::PLUGIN_VERSION;
    }
}
