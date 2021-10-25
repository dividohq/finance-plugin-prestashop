<?php

namespace Divido\Helper;

use Divido\MerchantSDK\Environment;

class DividoHelper{

    const CALCULATOR_URL = "//cdn.divido.com/widget/v3/";

    public static function generateCalcUrl($tenant, $environment){
        
        $prefixes = [$tenant];
        if($environment !== Environment::PRODUCTION){
            $prefixes[] = $environment;
        }
        return sprintf("%s%s.calculator.js", self::CALCULATOR_URL, implode(".",$prefixes));
    }

}