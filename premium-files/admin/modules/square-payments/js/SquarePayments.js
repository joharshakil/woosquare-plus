

const appId = square_params.application_id;
const locationId = square_params.locationId;

async function initializeCard(payments) {
		const card = await payments.card();
	    await card.attach('#card-container_payment');
	    console.log(card);
	return card;


}


// This function tokenizes a payment method.
// The ‘error’ thrown from this async function denotes a failed tokenization,
// which is due to buyer error (such as an expired card). It is up to the
// developer to handle the error and provide the buyer the chance to fix
// their mistakes.

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


async function verifyBuyer(payments, token) {


	if(jQuery( '#sq-card-saved' ).is(":checked")){
		var intten = 'STORE';
	} else if(square_params.subscription) {
		var intten = 'STORE';
	} else if(
			jQuery( '._wcf_flow_id' ).val() != null ||
			jQuery( '._wcf_flow_id' ).val() != undefined ||

			jQuery( '._wcf_checkout_id' ).val() != null ||
			jQuery( '._wcf_checkout_id' ).val() != undefined
	) {
		var intten = 'STORE';
	} else if(jQuery( '.is_preorder' ).val()) {
		var intten = 'STORE';
	} else {
		var intten = 'CHARGE';
	}

	const verificationDetails = {
		amount: square_params.cart_total,
		currencyCode: square_params.get_woocommerce_currency,
		intent: intten,
		billingContact: {}
	};

	const verificationResults = await payments.verifyBuyer(
			token,
			verificationDetails
	);
	return verificationResults.token;
}


// Helper method for displaying the Payment Status on the screen.
// status is either SUCCESS or FAILURE;

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
//window.addEventListener('load', async function () {
	if (!window.Square) {
		throw new Error('Square.js failed to load properly');
	}

	const payments = window.Square.payments(appId, locationId);
	let card;



	console.log('payye');
	console.log(payments);
	try {
		card = await initializeCard(payments);
	} catch (e) {

		console.error('Initializing Card failed', e);
		return;
	}



	// Checkpoint 2.
	async function handlePaymentMethodSubmission(event, paymentMethod) {
		event.preventDefault();

		try {
			// disable the submit button as we await tokenization and make a
			// payment request.
			//place_order.disabled = true;
			const token = await tokenize(paymentMethod);
            if(token){
				console.log(token);
				var $form = jQuery( 'form.woocommerce-checkout, form#order_review' );

				// inject nonce to a hidden field to be submitted
				$form.append( '<input type="hidden" class="square-nonce" name="square_nonce" value="' + token + '" />' );
				$form.append( '<input type="hidden" class="buyerVerification-token" name="buyerVerification_token"  />' );

				let verificationToken;
				verificationToken = await verifyBuyer(
						payments,
						token
				);

				jQuery('.buyerVerification-token').val(verificationToken);
				if(jQuery('.buyerVerification-token').val()){
					$form.submit();
					jQuery('#place_order').prop('disabled', true);
				} else {
						var html = '';
						html += '<ul class="woocommerce_error woocommerce-error">';
				     	jQuery('#place_order').prop('disabled', false);
						// handle errors
						html += '<li>Customer verification failed contact to site admin</li>';
						html += '</ul>';
						// append it to DOM
					    jQuery( '.payment_method_square_plus fieldset' ).eq(0).prepend( html );
					    jQuery('.blockUI').fadeOut(200);
				}
			} else{
				var html = '';
				html += '<ul class="woocommerce_error woocommerce-error">';
				jQuery('#place_order').prop('disabled', false);
				html += '<li>' + token + '</li>';
				html += '</ul>';
				jQuery( '.payment_method_square_plus fieldset' ).eq(0).prepend( html );

			}

			//const paymentResults = await createPayment(token);
			//displayPaymentResults('SUCCESS');

			//console.debug('Payment Success', paymentResults);
		} catch (e) {
			place_order.disabled = false;
			console.error(e.message);
			//displayPaymentResults('FAILURE');

		}
	}

	const cardButton = document.getElementById(
			'place_order'
	);
	cardButton.addEventListener('click', async function (event) {
		await handlePaymentMethodSubmission(event, card);
	});
});


