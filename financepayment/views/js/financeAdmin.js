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
            dialogClass: 'pbd-modal',
            closeText: "close",
            autoOpen: false,
            resizable: false,
            height: "auto",
            modal: true
        });

        $("#pbdWarningModal").dialog({
            dialogClass: 'pbd-modal',
            closeText: "okay",
            autoOpen: false,
            resizable: false,
            height: "auto",
            modal: true,
        });
    }

);

$(document).on('change', 'input[name="FINANCE_ALL_PLAN_SELECTION"]', updatePlansDiv);
$(document).on('change', 'select[name="FINANCE_PRODUCTS_OPTIONS"]', updateProductOptions);
$(document).on('change', 'select[name="FINANCE_display"]', updateProductPlans);

$(document).on('click', '.update-status', checkForReasonFromOrderBody);
$(document).on('click', '#update_order_status_action_btn', checkForReasonFromTab);
$(document).on('click', '.choice-type button', warn);

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

function warn(e){

    const greatGrandParent = e.target.parentElement.parentElement.parentElement;
    for(const child of greatGrandParent.children){
        if(child.classList.contains('column-payment')){
            if(child.innerHTML.trim() == pbdDisplayName){
                $("#pbdWarningModal").parent().addClass('pbd-modal-container');
                $(".pbd-modal-container button").addClass('btn btn-primary');
                $("#pbdWarningModal").dialog("open");
            }
        }
    }
    
}

function checkForReasonFromTab(event){
    event.preventDefault();

    const newOrderStatus = $("#update_order_status_action_input").val();

    checkForReason(newOrderStatus);
}

function checkForReasonFromOrderBody(event){
    event.preventDefault();

    const newOrderStatus = $("#update_order_status_new_order_status_id").val();

    checkForReason(newOrderStatus);
}

function checkForReason(newOrderStatus){
    
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
        
        $("#reasonModal").parent().addClass('pbd-modal-container');
        $("#reasonModal .body").html(data.message);
        if(data.reasons != null){
            const reasonSelect = document.createElement("select");
            reasonSelect.setAttribute('id', 'pbdReason')
            reasonSelect.classList.add('custom-select');
            for(reason in data.reasons) {
                let option = document.createElement("option");
                option.text = data.reasons[reason]
                option.value = reason
                reasonSelect.add(option);
            }
            $("#reasonModal .body").append(reasonSelect);
        }

        const continueBtn = {
            text: data.action+" (without notifying lender)",
            click: function(){
                submitStatus(newOrderStatus);
            }
        };

        let buttons = [continueBtn];
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
                            status: newOrderStatus,
                            applicationId: data.application_id,
                            amount: data.amount,
                            reason: (document.getElementById('pbdReason'))
                                ? $('#pbdReason').val()
                                : null
                        }
                    }).done(function(response){
                        console.log(response);
                        $("#reasonModal .body")
                            .html("<p>"+response.message+"</p>");
                        
                        let newBtns = [];
                        if(response.success === false){
                            newBtns.push(continueBtn);
                        }
                        setModalButtons($("#reasonModal"), newBtns);
                        $("#reasonModal").dialog("open");
                    });
                }
            });
        }
        
        setModalButtons($("#reasonModal"), buttons);
        $("#reasonModal").dialog("open");
    })

    function submitStatus(status){
        var submitForm = document.getElementsByName('update_order_status')[0];
        $("#update_order_status_new_order_status_id").val(status);
        submitForm.submit();
    }

    function setModalButtons(modal, buttons){
        modal.dialog("option", "buttons", buttons)

        $(".pbd-modal-container button")
            .addClass('btn btn-primary');
    }
    
}