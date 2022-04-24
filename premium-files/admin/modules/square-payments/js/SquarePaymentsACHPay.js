(function ( $ ) {
    'use strict';

    // const env = square_ach_params.environment;
    const appId = square_ach_params.application_id;
    const locationId = square_ach_params.lid;
    // const payments = window.Square.payments();

    // function buildPaymentRequest(payments) {
    // return payments.paymentRequest({
    // countryCode: square_ach_params.country_code,
    // currencyCode: square_ach_params.currency_code,
    // total: {
    // amount: square_ach_params.order_total,
    // label: 'Order Total',
    // },
    // });
    // }


    async function initializeACH(payments) {
        const ach = await payments.ach();
        // Note: ACH does not have an .attach(...) method
        // the ACH auth flow is triggered by .tokenize(...)
        return ach;
    }

    // Call this function to send a payment token, buyer name, and other details
    // to the project server code so that a payment can be created with
    // Payments API

    async function createPayment(token) {
        if ( document.getElementsByClassName('woocommerce-error')){
            jQuery('#ach-button').prop('disabled', false);
        }
        console.log('NONCE: ' + token);
        document.getElementById('card_nonce').value = token;
        jQuery('form.woocommerce-checkout').submit();

    }

    // async function createPayment(token) {
    // console.log('NONCE: ' + token);
    // document.getElementById('card_nonce').value = token;
    // const body = JSON.stringify({
    // locationId,
    // sourceId: token,
    // });
    // console.log('body' + body);
    // const paymentResponse = await fetch('/payment', {
    // method: 'POST',
    // headers: {
    // 'Content-Type': 'application/json',
    // },
    // body,
    // });
    // if (paymentResponse.ok) {
    // return paymentResponse.json();
    // }
    // const errorBody = await paymentResponse.text();
    // throw new Error(errorBody);
    // }

    // This function tokenizes a payment method.
    // The â€˜errorâ€™ thrown from this async function denotes a failed tokenization,
    // which is due to buyer error (such as an expired card). It is up to the
    // developer to handle the error and provide the buyer the chance to fix
    // their mistakes.
    async function tokenize(paymentMethod, options = {}) {
        const tokenResult = await paymentMethod.tokenize(options);
        if (tokenResult.status === 'OK') {
            return tokenResult.token;
        } else {
            let errorMessage = `Tokenization failed-status: ${tokenResult.status}`;
            if (tokenResult.errors) {
                errorMessage += ` and errors: ${JSON.stringify(
					tokenResult.errors
				)}`;
            }
            throw new Error(errorMessage);
        }
    }

    function getBillingContact(form) {
        const formData = new FormData(form);
        // It is expected that the developer performs form field validation
        // which does not occur in this example.
        return {
            givenName: formData.get('billing_first_name'),
            familyName: formData.get('billing_last_name'),
        };
    }

    function getACHOptions(form) {
        const billingContact = getBillingContact(form);
        console.log(billingContact);
        const accountHolderName = `${billingContact.givenName} ${billingContact.familyName}`;

        return { accountHolderName };
    }

    function displayPaymentResults(status) {
        const statusContainer = document.getElementById(
            'payment-status-container'
        );
        if (status === 'SUCCESS') {
            statusContainer.classList.remove('is-failure');
            statusContainer.classList.add('is-success');
        } else {
            statusContainer.classList.remove('is-success');
            statusContainer.classList.add('is-failure');
        }

        statusContainer.style.visibility = 'visible';
    }


    document.addEventListener('DOMContentLoaded', async function () {
        if (!window.Square) {
            throw new Error('Square.js failed to load properly');
        }

        let payments;
        try {
            payments = window.Square.payments(appId, locationId);
        } catch {
            const statusContainer = document.getElementById(
                'payment-status-container'
            );
            statusContainer.className = 'missing-credentials';
            statusContainer.style.visibility = 'visible';
            return;
        }
        let ach;
        try {
            ach = await initializeACH(payments);
        } catch (e) {
            console.error('Initializing ACH failed', e);
            return;
        }


        // Checkpoint 2.

        async function handlePaymentMethodSubmission(event, paymentMethod, options ) {
            event.preventDefault();

            try {
                // disable the submit button as we await tokenization and make a
                // payment request.
                if ( document.getElementsByClassName('woocommerce-error')){
                    document.getElementById('card_nonce').value = '';
                }
                achButton.disabled = true;
                jQuery('.woocommerce-error').remove();
                const token = await tokenize(paymentMethod, options);
                const paymentResults = await createPayment(token);
                displayPaymentResults('SUCCESS');
                console.debug('Payment Success', paymentResults);

            } catch (e) {

                displayPaymentResults('FAILURE');
                achButton.disabled = false;
                console.error(e.message);
            }
        }

        const achButton = document.getElementById('ach-button');
        achButton.disabled = false;
        achButton.addEventListener('click', async function (event) {
            // jQuery('.woocommerce-error').remove();
            const paymentForm = document.getElementsByClassName('woocommerce-checkout')[1];
            const achOptions = getACHOptions(paymentForm);
            await handlePaymentMethodSubmission(event, ach, achOptions);
        });
    });
    if(jQuery('.woocommerce-checkout-payment .input-radio:checked').val() == 'square_ach_payment'){
        jQuery('#place_order').css('display', 'none');
    } else {
        jQuery('#place_order').css('display', 'block');
    }


}( jQuery ) );

jQuery( window  ).load(function() {
    jQuery(".woocommerce-checkout-payment").on('change', '.input-radio', function(){
        setTimeout(explode, 300);
    });
    setTimeout(explode, 1000);
});


jQuery( function($){
    $('form.checkout').on('change', '.woocommerce-checkout-payment input', function(){
        hideunhide();
    });
});

function hideunhide(){
    if( jQuery('.woocommerce-checkout-payment .input-radio:checked').val() == 'square_google_pay' ){
        jQuery('#place_order').css('display', 'none');
    } else if(jQuery('.woocommerce-checkout-payment .input-radio:checked').val() == 'square_gift_card_pay' ) {
        jQuery('#place_order').css('display', 'block');
    } else if(jQuery('.woocommerce-checkout-payment .input-radio:checked').val() == 'square_plus' ){
        jQuery('#place_order').css('display', 'block');
    } else if(jQuery('.woocommerce-checkout-payment .input-radio:checked').val() == 'square_apple_pay' ){
        jQuery('#place_order').css('display', 'none');
    }else if(jQuery('.woocommerce-checkout-payment .input-radio:checked').val() == 'square_ach_payment' ){
        jQuery('#place_order').css('display', 'none');
    } else {
        jQuery('#place_order').css('display', 'block');
    }
}

function explode(){
    jQuery('.woocommerce-checkout-payment .input-radio').change(function() {
        console.log(jQuery('.woocommerce-checkout-payment .input-radio:checked').val());
        hideunhide();
    });

    console.log(jQuery('.woocommerce-checkout-payment .input-radio:checked').val());
    hideunhide();

}
