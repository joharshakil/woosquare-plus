
const giftpay_appId = squaregiftcardpay_params.application_id;
const giftpay_locationId = squaregiftcardpay_params.lid;


function buildPaymentRequest(payments) {
	return payments.paymentRequest({
		countryCode:squaregiftcardpay_params.country_code,
		currencyCode: squaregiftcardpay_params.currency_code ,
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


async function initializeGiftCard(payments) {
	const giftCard = await payments.giftCard();
	await giftCard.attach('#gift-card-container');
	return giftCard;
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
	const payments = window.Square.payments(giftpay_appId, giftpay_locationId);

	let giftCard;
	try {
		giftCard = await initializeGiftCard(payments);
	} catch (e) {
		console.error('Initializing Gift Card failed', e);
		return;
	}

	async function handlePaymentMethodSubmission(event, paymentMethod) {
		console.log(paymentMethod);
		//debugger;
		event.preventDefault();

		try {
			// disable the submit button as we await tokenization and make a
			// payment request.
			const token = await tokenize(paymentMethod);

			if(token){
				console.log(token);

				var $form = jQuery( 'form.woocommerce-checkout, form#order_review' );
				// inject nonce to a hidden field to be submitted
				/*$form.append( '<input type="hidden" class="errors" name="errors" value="' + errors + '" />' );
				 $form.append( '<input type="hidden" class="noncedatatype" name="noncedatatype" value="' + noncedatatype + '" />' );
				 $form.append( '<input type="hidden" class="cardData" name="cardData" value="' + cardData + '" />' );
				 */
				$form.append( '<input type="hidden" class="square-nonce" name="square_nonce" value="' + nonce + '" />' );



				$form.submit();
			} else{
				var html = '';
				html += '<ul class="woocommerce_error woocommerce-error">';
				$('#place_order').prop('disabled', false);
				html += '<li>' + token + '</li>';
				html += '</ul>';
				$( '.payment_method_square_plus fieldset' ).eq(0).prepend( html );

			}

			/*cardButton.disabled = true;

			 console.log('TK: ' + token);
			 const paymentResults = await createPayment(token);
			 displayPaymentResults('SUCCESS');*/

			console.debug('Payment Success', paymentResults);
		} catch (e) {
			/*cardButton.disabled = false;
			 displayPaymentResults('FAILURE');*/
			console.error(e.message);
		}
	}

	const cardButton = document.getElementById(
			'place_order'
	);
	cardButton.addEventListener('click', async function (event) {
		await handlePaymentMethodSubmission(event, card);
	});

	const giftCardButton = document.getElementById('gift-card-button');
	giftCardButton.addEventListener('click', async function (event) {
		await handlePaymentMethodSubmission(event, giftCard);
	});

});
