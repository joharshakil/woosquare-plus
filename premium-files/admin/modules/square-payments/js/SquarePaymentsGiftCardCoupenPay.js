(function ( $ ) {
	'use strict';
const giftpay_appId = squaregiftcardcoupenpay_params.application_id;
const giftpay_locationId = squaregiftcardcoupenpay_params.lid;
const orderID =  squaregiftcardcoupenpay_params.unique_id;

if(squaregiftcardcoupenpay_params.get_amount_store !== null && squaregiftcardcoupenpay_params.get_amount_store !== '' ){
	jQuery('.add_woosquare_gift_card_form').hide();
	jQuery('#sq_amount_result').show();
	if( jQuery('#sq_amount_result').length > 0 ) {
		var chc = '';
		if(squaregiftcardcoupenpay_params.squ_giftfee){
			var chc = 'checked=checked';
		}

		jQuery('#sq_amount_result').html(`<h4>Have a gift card?</h4> <input type="checkbox" `+chc+`class="input-checkbox" id="add_gift_box" name="add_gift_box" value="${squaregiftcardcoupenpay_params.get_amount_store}" /> Use ${squaregiftcardcoupenpay_params.currency_symbol}${squaregiftcardcoupenpay_params.get_amount_store} from gift card <input type="hidden" class="input-checkbox" id="sq_payment_id_box" name="square_payment_id" value="${squaregiftcardcoupenpay_params.square_payment_id}" /> <button  type="button" class="removal_square_btn" name="removal_square" id="removal_square"> Remove </button>`);

	}
}

jQuery(document).on( 'click', '#add_gift_box', function(e){

	jQuery('body').trigger('update_checkout');
});


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
	await giftCard.attach('#sq-gift-card-coupen');
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

				//var $form = jQuery( 'form.woocommerce-checkout, form#order_review' );
				// inject nonce to a hidden field to be submitted
				/*$form.append( '<input type="hidden" class="errors" name="errors" value="' + errors + '" />' );
				 $form.append( '<input type="hidden" class="noncedatatype" name="noncedatatype" value="' + noncedatatype + '" />' );
				 $form.append( '<input type="hidden" class="cardData" name="cardData" value="' + cardData + '" />' );
				 */
				/*$form.append( '<input type="hidden" class="square-nonce" name="square_nonce" value="' + nonce + '" />' );
				$form.submit();*/

				var response;
				let payment = {
					"nonce": token,
					"orderID" : orderID,

				};



				jQuery.ajax({
					type:"POST",
					url: squaregiftcardcoupenpay_params.ajax_url,
					data: {
						action:'sqaure_redeem_coupen_code',
						orderID:orderID,
						nonce:token,
						currency_code: squaregiftcardcoupenpay_params.currency_code
					},
					success:function(res){

						console.log(res);
						var response = jQuery.parseJSON(res);
						if (response.payment.status !== undefined && response.payment.status === "FAILED") {
							var html = 'Card denied:' + response.errors[0].code;
							jQuery( '.woowoosquare_gift_card_coupen_code_notices' ).eq(0).text( html.replace(/_/g, ' ') );
							jQuery( '.woowoosquare_gift_card_coupen_code_notices' ).css('display', 'block');
							jQuery('#woosquare_get_cart_redeem_send').text('Apply');
							return;
						}

						if ( response.payment.balance !== undefined && response.payment.balance > 0) {
							//Notify buyer of remaining balance and ask for another card.

							var html = 'Gift card authorized. Additional payment of '
									+ squaregiftcardcoupenpay_params.currency_symbol + response.payment.balance  + ' needed.';
							jQuery( '.woowoosquare_gift_card_coupen_code_notices' ).eq(0).text( html.replace(/_/g, ' ') );
							jQuery('#sq_amount_result').show();
							if( jQuery('#sq_amount_result').length > 0 ) {
								if(response.errors){
									jQuery('#woosquare_get_cart_redeem_send').text('Apply');
									//	jQuery('.add_woosquare_gift_card_form').show();
								} else {
									jQuery('#sq_amount_result').html(`<h4>Have a gift card?</h4> <input type="checkbox" class="input-checkbox" id="add_gift_box" name="add_gift_box" value="${(response.payment.amount_money.amount/100).toFixed(2)}" /> Use ${squaregiftcardcoupenpay_params.currency_symbol}${(response.payment.amount_money.amount/100).toFixed(2)} from gift card <input type="hidden" class="input-checkbox" id="sq_payment_id_box" name="sq_payment_id_box" value="${(response.payment.id)}" /> <button type="button" class="removal_square_btn" name="removal_square" id="removal_square"> Remove </button>`);

									jQuery('.squareboxvisible').show();
									jQuery('.add_woosquare_gift_card_form').hide();
								}
							}
							// Display results of the call.
							// let successDiv = document.getElementsByClassName('squareboxvisible');
							// successDiv.style.display = 'block';
							//jQuery('.squareboxvisible').show();
							//jQuery('.add_woosquare_gift_card_form').hide();
						} else if (response.payment.balance !== undefined && response.payment.balance == 0) {
							jQuery('#sq_amount_result').show();
							if( jQuery('#sq_amount_result').length > 0 ) {

								jQuery('#sq_amount_result').html(`<h4>Have a gift card?</h4> <input type="checkbox" class="input-checkbox" id="add_gift_box" name="add_gift_box" value="${(response.payment.amount_money.amount/100).toFixed(2)}" /> Use ${squaregiftcardcoupenpay_params.currency_symbol}${(response.payment.amount_money.amount/100).toFixed(2)} from gift card. <input type="hidden" class="input-checkbox" id="sq_payment_id_box" name="sq_payment_id_box" value="${(response.payment.id)}" /> <button type="button" name="removal_square" id="removal_square"> Remove </button>`);


							}
							// Display results of the call.
							// let successDiv = document.getElementsByClassName('squareboxvisible');
							// successDiv.style.display = 'block';
							jQuery('.squareboxvisible').show();
							jQuery('.add_woosquare_gift_card_form').hide();

						}
					}

				});

			} else{

				var html = errors[0].message+'.' ;
				jQuery( '.woowoosquare_gift_card_coupen_code_notices' ).eq(0).text( html.replace(/_/g, ' ') );
				jQuery( '.woowoosquare_gift_card_coupen_code_notices' ).show();
				jQuery('#woosquare_get_cart_redeem_send').text('Apply');
				return;

			}

			/*cardButton.disabled = true;
			 displayPaymentResults('SUCCESS');*/

			console.log('TK: ' + token);
			//const paymentResults = await createPayment(token);
			//console.debug('Payment Success', paymentResults);
		} catch (e) {
			console.error(e.message);
		}
		jQuery(document).on( 'click', '#add_gift_box', function(e){

			jQuery('body').trigger('update_checkout');
		});
	}

	const cardButton = document.getElementById(
			'place_order'
	);
	cardButton.addEventListener('click', async function (event) {
		await handlePaymentMethodSubmission(event, card);
	});

	//Checkpoint 2
	const giftCardButton = document.getElementById('woosquare_get_cart_redeem_send');
	giftCardButton.addEventListener('click', async function (event) {
		jQuery('#woosquare_get_cart_redeem_send').text('Loading...');
		await handlePaymentMethodSubmission(event, giftCard);
	});

});

jQuery(document).on( 'click', '#removal_square', function(e){
//jQuery("#sq_amount_result").on("click",".removal_square_btn", function(e){
	e.preventDefault();
	jQuery('#removal_square').text('Loading...');

	//		jQuery('body').trigger('update_checkout');
	jQuery.ajax({
		type:"POST",
		url: squaregiftcardcoupenpay_params.ajax_url,
		data: {
			action:'sqaure_redeem_coupen_code_cancel_payment',
			paymentID:squaregiftcardcoupenpay_params.square_payment_id,
			orderID:orderID,
			currency_code: squaregiftcardcoupenpay_params.currency_code
		},
		success:function(res){

            console.log(res);
			var response = jQuery.parseJSON(res);

			jQuery('body').trigger('update_checkout');
			jQuery( '.woowoosquare_gift_card_coupen_code_notices' ).hide();
			//	giftCardForm.build();
			jQuery('.add_woosquare_gift_card_form').show();
			jQuery('#sq_amount_result').hide();

			jQuery('#woosquare_get_cart_redeem_send').text('Apply');
			//		location.reload();
		}

	});
});

jQuery(document).ready(function() {
	jQuery('.squareboxvisible').hide();
});

}( jQuery ) );