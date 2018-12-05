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
*  @copyright  2007-2018 PrestaShop SA
*  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*}
{extends file='page.tpl'}

{block name='content_wrapper'}
{if $payment_error}
    <p class="alert alert-warning">
        {l s='Order can not processed because of error occurred.' mod='dividopayment'}
        <br>
        {l s='Error:'  mod='dividopayment'} {$responsetext|escape:'htmlall':'UTF-8'}
        <br>
        {l s='Error Description:'  mod='dividopayment'} {$responsedes|escape:'htmlall':'UTF-8'}
    </p>
{/if}

{if $nbProducts <= 0}
    <p class="alert alert-warning">
        {l s='Your shopping cart is empty.' mod='dividopayment'}
    </p>
{else}
{literal}
    <script type="text/javascript" src="https://cdn.divido.com/calculator/v2.1/production/js/template.divido.js"></script>
{/literal}
<div id="divido-checkout">
    <div data-divido-widget data-divido-prefix="finance for" data-divido-suffix="with" data-divido-title-logo data-divido-amount="{$raw_total|escape:'htmlall':'UTF-8'}" data-divido-apply="true" data-divido-apply-label="Apply Now" data-divido-plans = "{$plans|escape:'htmlall':'UTF-8'}"></div>

</div>
<div class="buttons">
    <p class="cart_navigation clearfix">
        <a class="btn btn-primary pull-xs-left" href="{url entity=order}">
            <i class="icon-chevron-left"></i>{l s='Other payment methods' mod='dividopayment'}
        </a>
        <input type="hidden" name="divido_total" value="{$raw_total|escape:'htmlall':'UTF-8'}">
        <input type="button" class="btn btn-primary pull-xs-right" value="{l s='I confirm my order' mod='dividopayment'}" id="button-confirm-divido" class="btn btn-primary" data-loading-text="{l s='Loading...' mod='dividopayment'}" data-confirm-text="{l s='I confirm my order' mod='dividopayment'}"/>
    </p>
</div>
{/if}
{/block}