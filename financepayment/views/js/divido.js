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
$(document).on('click', '#button-confirm-divido', function() {
    console.log('1111');
    var finance_elem = $('input[name="divido_plan"]');
    var deposit      = $('input[name="divido_deposit"]').val();
    var total      = $('input[name="divido_total"]').val();

    var finance;
    if (finance_elem.length > 0) {
        finance = finance_elem.val();
    } else {
        alert('Please select plan.');
        return;
    }

    var data = {
        finance: finance,
        deposit: deposit,
        total: total,
    };
    el = $(this);
    el.val($(this).data('loading-text'));
    $.ajax({
        type     : 'post',
        url      : validationLink,
        data     : data,
        dataType : 'json',
        cache    : false,
        success: function(data) {
            el.val(el.data('confirm-text'));
            if (data.status) {
                location = data.url;
            } else {
                message = data.message || 'Credit request could not be initiated';
                $('#divido-checkout').prepend('<div class="alert alert-warning">' + message + '<button type="button" class="close" data-dismiss="alert">&times;</button></div>');
                $('html, body').animate({
                    scrollTop: $("#divido-checkout").offset().top
                }, 1000);
            }
        },
        error: function() {
            el.val(el.data('confirm-text'));
        }
    });
});