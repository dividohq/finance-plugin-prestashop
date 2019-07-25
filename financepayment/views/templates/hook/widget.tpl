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



<div data-calculator-widget data-mode="lightbox" data-amount="{$raw_total|escape:'htmlall':'UTF-8'}" data-plans= "{$plans|escape:'htmlall':'UTF-8'}" data-api-key="{$api_key|escape:'htmlall':'UTF-8'}">
</div>

{literal}
<script type="text/javascript"  src="https://cdn.divido.com/widget/dist/{/literal}{$finance_environment|escape:'htmlall':'UTF-8'}{literal}.calculator.js" ></script>
{/literal}

