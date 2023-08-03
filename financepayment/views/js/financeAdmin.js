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
*  @copyright 2007-2019 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*
* Don't forget to prefix your containers with your own identifier
* to avoid any conflicts with others containers.
*/

$(document).ready(
    function(){
        if ($('input[name="FINANCE_ALL_PLAN_SELECTION"]').length > 0) {
            updatePlansDiv();
        }
        if ($('select[name="FINANCE_PRODUCTS_OPTIONS"]').length > 0) {
            updateProductOptions();
        }
        if ($('select[name="FINANCE_display"]').length > 0) {
            updateProductPlans();
        }

        $("#reasonModal").dialog({
            dialogClass: 'reason',
            closeText: "close",
            autoOpen: false,
            resizable: false,
            height: "auto",
            modal: true,
            buttons: {
                "Do something": function(){
                    $(this).dialog("close");
                },
                "Do something else": function(){
                    $(this).dialog("close");
                }
            }
        });
    }

);

$(document).on('change', 'input[name="FINANCE_ALL_PLAN_SELECTION"]', updatePlansDiv);
$(document).on('change', 'select[name="FINANCE_PRODUCTS_OPTIONS"]', updateProductOptions);
$(document).on('change', 'select[name="FINANCE_display"]', updateProductPlans);

$(document).on('click', '.update-status', checkForReason);

function updatePlansDiv() {
    val = $('input[name="FINANCE_ALL_PLAN_SELECTION"]:checked').val();
    if (!val) {
        $('select[name="FINANCE_PLAN_SELECTION_available[]"]').closest('.form-group').parent().closest('.form-group').slideDown();
    } else {
        $('select[name="FINANCE_PLAN_SELECTION_available[]"]').closest('.form-group').parent().closest('.form-group').slideUp();
    }
}

function updateProductOptions() {
    val = $('select[name="FINANCE_PRODUCTS_OPTIONS"]').val();
    if (val == 'min_price') {
        $('input[name="FINANCE_PRODUCTS_MINIMUM"]').closest('.form-group').slideDown();
    } else {
        $('input[name="FINANCE_PRODUCTS_MINIMUM"]').closest('.form-group').slideUp();
    }    
}

function updateProductPlans() {
    val = $('select[name="FINANCE_display"]').val();

    if ($('select[name="FINANCE_display"]').val() == 'custom') {
        $('.finance_plans_wrapper').slideDown();
    } else {
        $('.finance_plans_wrapper').slideUp();
    }
}

function checkForReason(event){
    event.preventDefault();

    var newOrderStatus = $("#update_order_status_new_order_status_id").val();
    var reasonUrl = $("#reasonUri").val();
    var orderId = $("#orderId").val();

    $.ajax({
        url: reasonUrl,
        method: 'GET',
        data: {
            newOrderStatus: newOrderStatus,
            orderId: orderId
        }
    }).done(function(data){
        console.log(data);
        
        if(!data.action){
            submitStatus(newOrderStatus);
            return;
        }
        
        $("#reasonModal").parent().addClass('reason-modal-container');
        $("#reasonModal .body").html(data.message);
        if(data.reasons != null){
            var reasonSelect = document.createElement("select")
            reasonSelect.classList.add('custom-select');
            for(reason in data.reasons) {
                let option = document.createElement("option");
                option.text = data.reasons[reason]
                option.value = reason
                reasonSelect.add(option);
            }
            $("#reasonModal .body").append(reasonSelect);
        }

        let buttons = [
            {
                text: data.action+" (without notifying lender)",
                click: function(){
                    submitStatus(newOrderStatus);
                }
            }
        ];
        if(data.notify){
            buttons.push({
                text: data.action+" and notify lender",
                click: function(){
                    var updateUrl = $("#updateUri").val();
                    $.ajax({
                        url: updateUrl,
                        type: 'POST',
                        data: {
                            action: data.action,
                            orderId: orderId,
                            applicationId: data.application_id
                        }
                    }).done(function(response){
                        console.log(response);
                        if(response.success){
                            submitStatus(newOrderStatus);
                        } else {
                            $("#reasonModal .body")
                                .html("<p>"+response.message+"</p>");
                            $( "#reasonModal" )
                                .dialog("option", "buttons", []);
                        }
                    });
                }
            });
        }
        
        $( "#reasonModal" )
            .dialog("option", "buttons", buttons)
            .dialog("open");

        $(".reason-modal-container button")
            .addClass('btn btn-primary');
    })

    function submitStatus(status){
        var submitForm = document.getElementsByName('update_order_status')[0];
        $("#update_order_status_new_order_status_id").val(status);
        submitForm.submit();
    }
    
}