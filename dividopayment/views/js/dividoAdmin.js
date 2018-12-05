/**
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
*
* Don't forget to prefix your containers with your own identifier
* to avoid any conflicts with others containers.
*/

$(document).ready(function(){
	if ($('input[name="DIVIDO_ALL_PLAN_SELECTION"]').length > 0) {
		updatePlansDiv();
	}
	if ($('select[name="DIVIDO_PRODUCTS_OPTIONS"]').length > 0) {
		updateProductOptions();
	}
	if ($('select[name="DIVIDO_display"]').length > 0) {
		updateProductPlans();
	}
});

$(document).on('change', 'input[name="DIVIDO_ALL_PLAN_SELECTION"]', updatePlansDiv);
$(document).on('change', 'select[name="DIVIDO_PRODUCTS_OPTIONS"]', updateProductOptions);
$(document).on('change', 'select[name="DIVIDO_display"]', updateProductPlans);

function updatePlansDiv() {
	val = $('input[name="DIVIDO_ALL_PLAN_SELECTION"]:checked').val();
	if (!val) {
		$('select[name="DIVIDO_PLAN_SELECTION_available[]"]').closest('.form-group').parent().closest('.form-group').slideDown();
	} else {
		$('select[name="DIVIDO_PLAN_SELECTION_available[]"]').closest('.form-group').parent().closest('.form-group').slideUp();
	}
}

function updateProductOptions() {
	val = $('select[name="DIVIDO_PRODUCTS_OPTIONS"]').val();
	if (val == 'min_price') {
		$('input[name="DIVIDO_PRODUCTS_MINIMUM"]').closest('.form-group').slideDown();
	} else {
		$('input[name="DIVIDO_PRODUCTS_MINIMUM"]').closest('.form-group').slideUp();
	}	
}

function updateProductPlans() {
	val = $('select[name="DIVIDO_display"]').val();

	if ($('select[name="DIVIDO_display"]').val() == 'custom') {
		$('.divido_plans_wrapper').slideDown();
	} else {
		$('.divido_plans_wrapper').slideUp();
	}
}
