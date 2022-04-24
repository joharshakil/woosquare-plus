(function ( $ ) {
	'use strict';
const Apay_appId = squaregpay_params.application_id;
const Apay_locationId = squaregpay_params.lid;



function buildPaymentRequest(payments) {
	return payments.paymentRequest({
		countryCode:squaregpay_params.country_code,
		currencyCode: squaregpay_params.currency_code ,
		total: {
			amount: squaregpay_params.order_total,
			label: 'Total',
		},
	});
}

async function tokenize(paymentMethod) {

	const tokenResult = await paymentMethod.tokenize();
	if (tokenResult.status === 'OK') {
		return tokenResult.token;
	} else {
		let errorMessage = tokenResult.status;
		if (tokenResult.errors) {
			errorMessage += tokenResult.errors;
		}
		throw new Error(errorMessage);
	}
}


async function initializeApplePay(payments) {
	const paymentRequest = buildPaymentRequest(payments)
	const applePay = await payments.applePay(paymentRequest);
	// Note: You do not need to `attach` applePay.
	return applePay;
}

// Helper method for displaying the Payment Status on the screen.
// status is either SUCCESS or FAILURE;
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
	const payments = window.Square.payments(Apay_appId, Apay_locationId);

	let applePay;
	try {
		applePay = await initializeApplePay(payments);
	} catch (e) {
		jQuery( "#browser_support_msg" ).text("Apple Pay is not available on this browser.");
		document.getElementById("apple-pay-button").style.display = "none";
		console.log('Initializing Apple Pay failed', e);
	}

	async function handlePaymentMethodSubmission(event, paymentMethod) {
		console.log(paymentMethod);
		//debugger;
		event.preventDefault();

		try {
			// disable the submit button as we await tokenization and make a
			// payment request.
			const token = await tokenize(paymentMethod);
			console.log(token);
			console.log('ss');
			if(token){
				console.log(token);

				var $form = jQuery( 'form.woocommerce-checkout, form#order_review' );
				// inject nonce to a hidden field to be submitted
				/*$form.append( '<input type="hidden" class="errors" name="errors" value="' + errors + '" />' );
				 $form.append( '<input type="hidden" class="noncedatatype" name="noncedatatype" value="' + noncedatatype + '" />' );
				 $form.append( '<input type="hidden" class="cardData" name="cardData" value="' + cardData + '" />' );
				 */
				$form.append( '<input type="hidden" class="square-nonce" name="square_nonce" value="' + token + '" />' );
				$form.submit();
			} else{
				var html = '';
				html += '<ul class="woocommerce_error woocommerce-error">';
				$('#place_order').prop('disabled', false);
				html += '<li>' + token + '</li>';
				html += '</ul>';
				$( '.payment_method_square_plus fieldset' ).eq(0).prepend( html );
				var $form = jQuery( 'form.woocommerce-checkout, form#order_review' );
				$form.append( '<input type="hidden" class="square_submit_error" name="square_submit_error" value="' + html + '" />' );
			}
			console.debug('Payment Success', paymentResults);
		} catch (e) {
			console.error(e.message);
		}
	}

	const cardButton = document.getElementById(
			'place_order'
	);
	cardButton.addEventListener('click', async function (event) {
		await handlePaymentMethodSubmission(event, card);
	});

	//Checkpoint 2
	if(applePay !== undefined) {
		const applePayButton = document.getElementById('apple-pay-button');
		applePayButton.addEventListener('click', async function (event) {
			await handlePaymentMethodSubmission(event, applePay);
		});
	}

});
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
