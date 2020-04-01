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
*  @author    PrestaShop SA <contact@prestashop.com>
*  @copyright 2007-2019 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*}

<div class="box">
    {if (isset($status) == true) && ($status == 'ok')}
        <h3>{l s='order_complete_header' mod='financepayment'}</h3>
        <hr />
        <p>
            <br />- {l s='total_amount_label' mod='financepayment'}: <span class="price"><strong>{$total|escape:'htmlall':'UTF-8'}</strong></span>
            <br />- {l s='order_id_label' mod='financepayment'}: <span class="reference"><strong>{$reference|escape:'html':'UTF-8'}</strong></span>
            <br /><br />{l s='email_sent_msg' mod='financepayment'}
            <br /><br /><a href="{$link->getPageLink('contact', true)|escape:'html':'UTF-8'}">{l s='customer_support_msg' mod='financepayment'}</a>
        </p>
    {else}
        <h3>{l s='order_incomplete_header' mod='financepayment'}</h3>
        <hr />
        <p>
            <br />- {l s='order_id_label' mod='financepayment'}: <span class="reference"> <strong>{$reference|escape:'html':'UTF-8'}</strong></span>
            <br /><br />{l s='try_again_msg' mod='financepayment'}.
            <br /><br /><a href="{$link->getPageLink('contact', true)|escape:'html':'UTF-8'}">{l s='customer_support_msg' mod='financepayment'}</a>
        </p>
    {/if}
</div>