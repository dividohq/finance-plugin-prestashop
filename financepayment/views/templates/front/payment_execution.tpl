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
{extends file='page.tpl'}

{block name='content_wrapper'}
{if $payment_error}
    <p class="alert alert-warning">
        {l s='order_error_description_prefix' mod='financepayment'}:
        <br>
        {l s='error_title_short' mod='financepayment'}: {$responsetext|escape:'htmlall':'UTF-8'}
        <br>
        {l s='error_description_label'  mod='financepayment'}: {$responsedes|escape:'htmlall':'UTF-8'}
    </p>
{/if}

{if $nbProducts <= 0}
    <p class="alert alert-warning">
        {l s='empty_cart_error_msg' mod='financepayment'}
    </p>
{else}
{literal}
<script>
    __widgetConfig = {
        lenderConfig:{ preset: '{/literal}{$lender}{literal}'},
        apiKey: '{/literal}{$api_key}{literal}',
        theme:{}
    }
</script>
{/literal}
    <div id="finance-checkout">
        <div data-calculator-widget data-mode="calculator" data-amount="{$raw_total *100|escape:'htmlall':'UTF-8'}" data-plans="{$plans|escape:'htmlall':'UTF-8'}">
    </div>
{literal}
    <script type="text/javascript"  src="https://cdn.divido.com/widget/dist/{/literal}{$finance_environment|escape:'htmlall':'UTF-8'}{literal}.calculator.js" ></script>
{/literal}
<div class="buttons">
    <p class="cart_navigation clearfix">
        <a class="btn btn-primary pull-xs-left" href="{url entity=order}">
            <i class="icon-chevron-left"></i>{l s='alt_payment_methods_label' mod='financepayment'}
        </a>
        <input type="hidden" name="divido_total" value="{$raw_total|escape:'htmlall':'UTF-8'}">
        <input type="button" class="btn btn-primary pull-xs-right" value="{l s='confirm_label' mod='financepayment'}" id="button-confirm-finance" class="btn btn-primary" data-loading-text="{l s='loading_label' mod='financepayment'}" data-confirm-text="{l s='confirm_label' mod='financepayment'}"/>
    </p>
</div>
{/if}
{/block}
