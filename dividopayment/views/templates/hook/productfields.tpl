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
*  @copyright 2007-2018 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*}

<div id="dividopayment" class="row">
    <div class="col-md-12">
    	<div class="row form-group">
			<div class="form-group  col-md-4">
				<label class="form-control-label">{l s='Available for finance?' mod='dividopayment'}</label>
				<select name="DIVIDO_display" class="form-control display_plans">
					<option value="default">{l s='No' mod='dividopayment'}</option>
					<option value="custom" {if isset($product_settings['display']) && $product_settings['display'] == 'custom'} selected="selected" {/if}>{l s='Selected' mod='dividopayment'}</option>
				</select>
			</div>
    	</div>
    	<div class="row form-group divido_plans_wrapper">
			<div class="form-group  col-md-4">
				<label class="form-control-label">{l s='Selected Plans' mod='dividopayment'}</label>
				<select name="DIVIDO_plans[]" multiple="multiple" class="form-control select_plans">
					{foreach from=$plans item=plan}
						<option value="{$plan->id|escape:'htmlall':'UTF-8'}" {if $plan->id|in_array:$product_settings.plans} selected="selected" {/if}>{$plan->text|escape:'htmlall':'UTF-8'}</option>
					{/foreach}
				</select>
			</div>
    	</div>
	</div>
</div>