{*
* 2007-2018 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author PrestaShop SA <contact@prestashop.com>
*  @copyright  2007-2019 PrestaShop SA
*  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*}
{literal}
    <script>
        __widgetConfig = {
            lenderConfig:{ preset: '{/literal}{$lender}{literal}'},
            apiKey: '{/literal}{$api_key}{literal}',
            theme:{}
        }
    </script>
{/literal}
<div data-calculator-widget data-amount="{$raw_total*100|escape:'htmlall':'UTF-8'}"  data-plans="{$plans|escape:'htmlall':'UTF-8'}"></div>
{if $calc_conf_api_url != ""}
    {literal}
    <script>
    window.__calculatorConfig = {
        apiKey: '{/literal}{$api_key}{literal}',
        calculatorApiPubUrl: '{/literal}{$calc_conf_api_url}{literal}'
    };
    </script>
    {/literal}
{/if}
{literal}
    <script type="text/javascript"  src="{/literal}{$calculator_url}{literal}" ></script>
{/literal}
