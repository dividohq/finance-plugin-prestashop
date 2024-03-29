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

{capture name=path}
	<a href="{$link->getPageLink('order', true, NULL, 'step=3')|escape:'html':'UTF-8'}" title="{l s='Go back to the Checkout' mod='financepayment'}">{l s='Checkout' mod='financepayment'}</a><span class="navigation-pipe">{$navigationPipe|escape:'htmlall':'UTF-8'}</span>{l s='Divido' mod='financepayment'}
{/capture}

<h1 class="page-heading">
    {l s='Order summary' mod='financepayment'}
</h1>

{if $payment_error}
	<p class="alert alert-warning">
		{l s='Order can not processed because of error occurred.' mod='financepayment'}
		<br>
		{l s='Error:'  mod='financepayment'} {$responsetext|escape:'htmlall':'UTF-8'}
		<br>
		{l s='Error Description:'  mod='financepayment'} {$responsedes|escape:'htmlall':'UTF-8'}
	</p>
{/if}

{if $nbProducts <= 0}
	<p class="alert alert-warning">
		{l s='Your shopping cart is empty.' mod='financepayment'}
	</p>
{else}

{literal}
<script>
    __widgetConfig = {
        apiKey: '{/literal}{$api_key}{literal}',
        theme:{}
    }
</script>
{/literal}
<div id="finance-checkout">
    <div data-calculator-widget data-mode="calculator" data-amount="{$raw_total *100|escape:'htmlall':'UTF-8'}" data-plans="{$plans|escape:'htmlall':'UTF-8'}">
</div>
{literal}
    <script type="text/javascript"  src="{/literal}{$calculator_url}{literal}" ></script>
{/literal}

    <div class="buttons">
    <p class="cart_navigation clearfix">
        <a class="btn btn-primary pull-xs-left" href="{$link->getPageLink('order')|escape:'htmlall':'UTF-8'}">
            <i class="icon-chevron-left"></i>{l s='Other payment methods' mod='financepayment'}
        </a>
        <input type="hidden" name="divido_total" value="{$raw_total|escape:'htmlall':'UTF-8'}">
        <input type="button" class="btn btn-primary pull-xs-right" value="{l s='I confirm my order' mod='financepayment'}" id="button-confirm-finance" class="btn btn-primary" data-loading-text="{l s='Loading...' mod='financepayment'}" data-confirm-text="{l s='I confirm my order' mod='financepayment'}"/>
    </p>
</div>
{/if}
