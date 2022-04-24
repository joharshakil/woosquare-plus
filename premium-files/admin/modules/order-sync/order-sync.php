<?php
if ( ! defined( 'WOO_SQUARE_ORDER_PLUGIN_URL' ) ) {
	define('WOO_SQUARE_ORDER_PLUGIN_URL',plugin_dir_url(__FILE__));
	define('WOO_SQUARE_ORDER_PLUGIN_PATH', plugin_dir_path(__FILE__));
}


/**
 * @param type $data
 */
function square_woo_debug_log($type, $data) {
	error_log("[$type] [" . date("Y-m-d H:i:s") . "] " . print_r($data, true) . "\n", 3, dirname(__FILE__) . '/logs.log');
}

function square_order_sync_handler()
{

	//square_woo_debug_log('info', "Callback page called.");

	//square_woo_debug_log('info', "Callback page called.");


	if ($_SERVER['REQUEST_METHOD'] == 'GET') {
		//square_woo_debug_log('info', "Callback page called via get request.");
		echo die('Callback request working!');
	}

	$post_data = json_decode(file_get_contents("php://input"));

	if (!$post_data) {
		//	square_woo_debug_log('info', "Callback page called via POST request. but there is no post data.");
		echo die('Callback request working with no post data');
	}

//square_woo_debug_log('info', "Callback page called via POST with post data (json format) " . $HTTP_RAW_POST_DATA);

	if (isset($post_data->event_type) && $post_data->event_type == "TEST_NOTIFICATION") {
		//square_woo_debug_log('error', "This is a test notifications from Square ");
		header("HTTP/1.1 200 OK");
		die();
	}
	if (empty(get_option('square_payment_begin_time'))) {
		// 2013-01-15T00:00:00Z
		update_option('square_payment_begin_time', date("Y-m-d") . "T00:00:00Z");
	}

	if (get_option('square_calback_server') == 'busy') {
		//square_woo_debug_log('info', "Callback page return server busy.");
		delete_option('square_calback_server');
		return false;
	}

	update_option('square_calback_server', 'busy');

	$woocommerce_square_plus_settings = get_option('woocommerce_square_plus_settings');
	if(!empty($woocommerce_square_plus_settings['enable_sandbox'])  && $woocommerce_square_plus_settings['enable_sandbox'] == 'yes' ){
		$woo_square_location_id_for_callback = $woocommerce_square_plus_settings['sandbox_location_id'];
		$woo_square_access_token_for_callback = $woocommerce_square_plus_settings['sandbox_access_token'];
	} else {
		$woo_square_location_id_for_callback = get_option('woo_square_location_id_for_callback');
		$woo_square_access_token_for_callback = get_option('woo_square_access_token_for_callback');
	}
	$square = new Square($woo_square_access_token_for_callback, $woo_square_location_id_for_callback, WOOSQU_PLUS_APPID);
	//$url = "https://connect.squareup.com/v1/".$woo_square_location_id_for_callback."/payments?begin_time=".get_option('square_payment_begin_time')."&end_time=".date('Y-m-d', strtotime(' + 1 days'))."T00:00:00Z&order=DESC";
	$url = "https://connect." . WOOSQU_STAGING_URL . ".com/v2/payments?begin_time=" . get_option('square_payment_begin_time') . "&end_time=" . date('Y-m-d', strtotime(' + 1 days')) . "T00:00:00Z&order=DESC";
	$headers = array(
			'Authorization' => 'Bearer ' . $woo_square_access_token_for_callback, // Use verbose mode in cURL to determine the format you want for this header
			'Content-Type' => 'application/json;'
	);

	$response = array();
	$method = "GET";
	$args = array('');

	$interval = 30;
	$response = $square->wp_remote_woosquare($url,$args,$method,$headers,$response);
	/*if (get_option('_transient_timeout_' . $woo_square_location_id_for_callback . 'transient_callback_square') > time()) {

		$response = get_transient($woo_square_location_id_for_callback . 'transient_callback_square');

	} else {

		$response = $square->wp_remote_woosquare($url, $args, $method, $headers, $response);
		set_transient($woo_square_location_id_for_callback . 'transient_callback_square', $response, $interval);
	}*/
	// $response = $square->wp_remote_woosquare($url,$args,$method,$headers,$response);
	if (!empty($response['response'])) {
		if ($response['response']['code'] == 200 and $response['response']['message'] == 'OK') {
			$payment_obj = json_decode($response['body'], false);
		} else {
			$payment_obj = json_decode($response['body'], false);
			return false;
		}
	} else {
		return false;
	}

// square_woo_debug_log('info', "All transaction response" .json_encode( $response ));
	//square_woo_debug_log('info', "All transaction request : https://connect.squareup.com/v1/".$woo_square_location_id_for_callback."/payments?begin_time=".get_option('square_payment_begin_time')."&end_time=".date('Y-m-d', strtotime(' + 1 days'))."T00:00:00Z&order=DESC");

// $payment_obj = json_decode($response);


	if (empty($payment_obj)) {
		// some kind of an error happened
		//square_woo_debug_log('error', "The response of payment details curl request " . json_encode($err));
		// curl_close($ch);
		return false;
	} else {
		//square_woo_debug_log('info', "The response of payment details curl request " . json_encode($response));
		// curl_close($ch);

		foreach ($payment_obj->payments as $payment) {
			//if (!empty($payment->itemizations) or !empty($payment->refunds)) {


			global $wpdb;
			$checkif_order_not_exist = $wpdb->get_results("SELECT * FROM `" . $wpdb->postmeta . "` WHERE meta_key='square_payment_id' AND meta_value='" . $payment->id . "'");
			if (empty($checkif_order_not_exist[0])) {
				/*foreach ($payment->itemizations as $item) {
                    if($item->name == "Custom Amount"){
                        square_woo_debug_log('info', "Square Custom ammount not supported");
                        continue 2;
                    }
                    if(empty($item->item_detail->sku)){
                        square_woo_debug_log('info', "Square item not found order break");
                        continue 2;
                    }
                }*/

				//if customer exist
				//	$exploded_payment_url = explode('/',$payment->payment_url);

				$url = "https://connect.".WOOSQU_STAGING_URL.".com/v2/locations/" . $woo_square_location_id_for_callback . "/orders/batch-retrieve";

				$response = array();
				$method = "POST";
				//$args =  array('order_ids'=> array($exploded_payment_url[6]));
				$args = array('order_ids' => array($payment->order_id));
				$response = $square->wp_remote_woosquare($url, $args, $method, $headers, $response);
				if (!empty($response['response'])) {
					if ($response['response']['code'] == 200 and $response['response']['message'] == 'OK') {
						$payment_order_obj = json_decode($response['body'], false);
					} /*else {
						square_woo_debug_log('info', "Square order not found order response for " . $payment . " and order error " . json_encode($response));
					}*/
				} /*else {
					square_woo_debug_log('info', "Square order not found order response for " . $payment . " and order error " . json_encode($response));
				}*/
				if (!empty($payment_order_obj->orders[0]->customer_id)) {

					$url = "https://connect.".WOOSQU_STAGING_URL.".com/v2/customers/" . $payment_order_obj->orders[0]->customer_id;

					$response = array();
					$method = "GET";
					$args = array('');


					$response = $square->wp_remote_woosquare($url, $args, $method, $headers, $response);

					if (!empty($response['response'])) {
						if ($response['response']['code'] == 200 and $response['response']['message'] == 'OK') {
							$payment_customer_obj = json_decode($response['body'], false);
							if (!empty($payment_customer_obj->customer)) {
								$email = $payment_customer_obj->customer->email_address;
								$billing_first_name = $payment_customer_obj->customer->given_name;
								$billing_last_name = $payment_customer_obj->customer->family_name;
								$user = get_user_by('email', $email);
								//check if email exist in woocommerce square just get id and link it else creating customer.
								if (empty($user->data->ID)) {
									$user_id = wc_create_new_customer($email, $billing_first_name, wp_generate_password(12));
									update_user_meta($user_id, "billing_first_name", $billing_first_name);
									update_user_meta($user_id, "first_name", $billing_first_name);
									update_user_meta($user_id, "billing_last_name", $billing_last_name);
									update_user_meta($user_id, "last_name", $billing_last_name);
									update_user_meta($user_id, "_square_customer_id", $payment_customer_obj->customer->id);
									update_user_meta($user_id, "billing_phone", $payment_customer_obj->customer->phone_number);
									update_user_meta($user_id, "billing_address_1", $payment_customer_obj->customer->address->address_line_1);
									update_user_meta($user_id, "billing_address_2", $payment_customer_obj->customer->address->address_line_2);
									update_user_meta($user_id, "billing_country", $payment_customer_obj->customer->address->country);
									update_user_meta($user_id, "billing_postcode", $payment_customer_obj->customer->address->postal_code);
									update_user_meta($user_id, "billing_state", $payment_customer_obj->customer->address->administrative_district_level_1);

								} else {
									$user_id = $user->ID;
								}
							} else {
								$user = get_user_by('login', 'square_user');
								$user_id = $user->ID;
							}
						} else {
							square_woo_debug_log('info', "Square customer not found customer response for " . $payment . " and customer error " . json_encode($response));
						}
					} else {
						square_woo_debug_log('info', "Square customer not found customer response for " . $payment . " and customer error " . json_encode($response));
					}


				} else {
					$user = get_user_by('login', 'square_user');
					$user_id = $user->ID;
				}


				$args = array(
						'customer_id' => $user_id,
						'created_via' => 'Square',
				);
				$order = wc_create_order($args);

				//square_woo_debug_log('info', "Creating new order for : ".$order->get_order_number()." and payment id is ".$payment->id);
				$result = '';
				foreach ($payment_order_obj->orders[0]->line_items as $item) {
					// square_woo_debug_log('info', "Square item details: " . json_encode($item));

					$url = "https://connect.".WOOSQU_STAGING_URL.".com/v2/catalog/object/" . $item->catalog_object_id . "?include_related_objects=true";
					$response = array();
					$method = "GET";
					$args = array('');
					$catalog_object = $square->wp_remote_woosquare($url, $args, $method, $headers, $response);
					if (!empty($catalog_object['response'])) {
						if ($catalog_object['response']['code'] == 200 and $catalog_object['response']['message'] == 'OK') {
							$catalog_object_obj = json_decode($catalog_object['body'], false);
						} else {
							// square_woo_debug_log('info', "Square order not found order response for ".$payment." and order error ".json_encode($response));
						}
					} else {
						// square_woo_debug_log('info', "Square order not found order response for ".$payment." and order error ".json_encode($response));
					}

					$sku = $catalog_object_obj->object->item_variation_data->sku;

					global $wpdb;

					$product_id = $wpdb->get_var($wpdb->prepare("SELECT post_id FROM $wpdb->postmeta WHERE meta_key='_sku' AND meta_value='%s' LIMIT 1", $sku));


					//square_woo_debug_log('info', "The result of searching for item on woocommerce: " . $product_id);
					// do something if the meta-key-value-pair exists in another post


					if (!empty($product_id)) {
						$order->add_product(wc_get_product($product_id), $item->quantity); //(get_product with id and next is for quantity)
					} else {
						//square_woo_debug_log('info', "product not found on woocommerce.");

						/* get all items */


						$WooToSquareSynchronizer = new WooToSquareSynchronizer($square);
						/* get Inventory of all items */
						$url = 'https://connect.' . WOOSQU_STAGING_URL . '.com/v2/catalog/search';

						$headers = array(
								'Authorization' => 'Bearer ' . $woo_square_access_token_for_callback, // Use verbose mode in cURL to determine the format you want for this header
								'Content-Type' => 'application/json;',
								'Square-Version' => '2020-12-16'
						);
						$method = "POST";
						$response = array();
						$woo_square_location_id = get_option('woo_square_location_id');
						$args = array(
								'object_types' =>
										array(
												0 => 'CATEGORY',
												1 => 'ITEM_VARIATION',
												2 => 'ITEM',
												3 => 'IMAGE'
										),
								'include_related_objects' => true,
								'query' =>
										array(
												'text_query' =>
														array(
																'keywords' =>
																		array(
																				0 => $sku,
																		),
														),
										),
						);
						$response = $square->wp_remote_woosquare($url, $args, $method, $headers, $response);

						$squareSynchronizer = new SquareToWooSynchronizer($square);

						if (!empty($response['response'])) {
							if ($response['response']['code'] == 200 and $response['response']['message'] == 'OK') {
								$squareProduct = json_decode($response['body'], false);


							}
						}

						if (empty($squareProduct->objects)) {
							//item product deleted in square as well so we will also add as trash in woocommerce.

							$my_post = array(
									'post_title' => $item->name,
									'post_status' => 'trash',
									'post_author' => 1,
									'post_type' => 'product'
							);
							$id = wp_insert_post($my_post, true);
							update_post_meta($id, '_visibility', 'visible');
							/*update_post_meta($id, '_regular_price', $item->single_quantity_money->amount / 100 );
                            update_post_meta($id, '_price', $item->single_quantity_money->amount / 100 );
                            update_post_meta($id, '_sku', $item->item_detail->sku );

                            update_post_meta($id, 'square_id', $item->item_detail->item_id);
                            update_post_meta($id, 'variation_square_id', $item->item_detail->item_variation_id );
*/
							update_post_meta($id, '_regular_price', $item->base_price_money->amount / 100);
							update_post_meta($id, '_price', $item->base_price_money->amount / 100);
							update_post_meta($id, '_sku', $sku);
							update_post_meta($id, 'square_id', $catalog_object_obj->object->item_variation_data->item_id);
							update_post_meta($id, 'variation_square_id', $catalog_object_obj->object->id);


							///$sku = $item->item_detail->sku;

							global $wpdb;

							$product_id = $wpdb->get_var($wpdb->prepare("SELECT post_id FROM $wpdb->postmeta WHERE meta_key='_sku' AND meta_value='%s' LIMIT 1", $sku));

							//square_woo_debug_log('info', "The result of searching AGAIN for item on woocommerce " . $product_id);


							$order->add_product(wc_get_product($product_id), $item->quantity); //(get_product with id and next is for quantity)


						} else {
							// product found in square so create missing product on woocommerce.

							//square_woo_debug_log('info', "new category add if category not exist in woocommerce.");
							// $item->item_detail
							if(!empty($squareProduct->object)){

								if($squareProduct->object->type == "ITEM"){

									foreach($squareProduct->object->item_data->variations as $variation){
										if($variation->item_variation_data->sku == $sku){

											$squareInventoryres = $squareSynchronizer->getSquareInventory(json_decode(json_encode($squareProduct->object->item_data->variations), true));



											foreach($squareProduct->related_objects as $cat_objs){

												if($cat_objs->type == "CATEGORY"){


													if($cat_objs->id == $squareProduct->object->item_data->category_id){

														if(!empty($cat_objs->category_data->name)){
															$squareProduct->object->item_data->category->name = $cat_objs->category_data->name;



															$cat_objs->name = $cat_objs->category_data->name;

															$result = $squareSynchronizer->addCategoryToWoo($cat_objs);
														}
													}

												}
												//for image
												if($cat_objs->type == "IMAGE"){




													if($cat_objs->id == $squareProduct->object->image_id){

														if(!empty($squareProduct->object->image_id)){
															$squareProduct->object->item_data->image_data = $cat_objs;
														}
													}

												}
											}


											$fetchsquareProduct = $squareProduct->object->item_data;
											$fetchsquareProduct->id = $squareProduct->object->id;
											// $fetchsquareProduct->master_image = $squareProduct->object->item_data->image_data;
											$fetchsquareProduct->master_image->url = $squareProduct->object->item_data->image_data->image_data->url;
											$fetchsquareProduct->master_image->id = $squareProduct->object->item_data->image_data->id;
											$fetchsquareProduct->present_at_all_locations = $squareProduct->object->present_at_all_locations;
											$fetchsquareProduct->version = $squareProduct->object->version;
											$fetchsquareProduct->updated_at = $squareProduct->object->updated_at;


										}
									}
								}
							}

							/*if (!empty($squareProduct->objects)) {
								foreach ($squareProduct->objects as $objs) {
									if ($objs->type == "ITEM") {
										foreach ($objs->item_data->variations as $variation) {
											if ($variation->item_variation_data->sku == $sku) {

												$squareInventory = $squareSynchronizer->getSquareInventory(json_decode(json_encode($objs->item_data->variations), true));


												foreach ($squareProduct->related_objects as $cat_objs) {
													if ($cat_objs->type == "CATEGORY") {
														if ($cat_objs->id == $objs->item_data->category_id) {

															if (!empty($objs->item_data->category->name)) {
																$objs->item_data->category->name = $cat_objs->category_data->name;


																$cat_objs->name = $cat_objs->category_data->name;
																$result = $squareSynchronizer->addCategoryToWoo($cat_objs);
															}
														}

													}
													//for image
													if ($cat_objs->type == "IMAGE") {
														if ($cat_objs->id == $objs->image_id) {

															if (!empty($objs->image_id)) {
																$objs->item_data->image_data = $cat_objs;
															}
														}

													}
												}

												$fetchsquareProduct = $objs->item_data;
												$fetchsquareProduct->id = $objs->id;
												$fetchsquareProduct->image_id = $objs->image_id;
												$fetchsquareProduct->present_at_all_locations = $objs->present_at_all_locations;
												$fetchsquareProduct->version = $objs->version;
												$fetchsquareProduct->updated_at = $objs->updated_at;


											}
										}
									}
								}


							}*/


							if ($result !== FALSE) {
								update_option("is_square_sync_{$result}", 1);
							}

							//square_woo_debug_log('info', "new product going to be add in woocommerce.");


//							$squareInventory = $squareSynchronizer->convertSquareInventoryToAssociative($squareInventory->counts);

							if(!empty($squareInventoryres)){
								$squareInvent->counts = $squareInventoryres;
								$squareInventory = $squareSynchronizer->convertSquareInventoryToAssociative($squareInvent->counts);
							}

							$squareSynchronizer->addProductToWoo($fetchsquareProduct, $squareInventory);

							$sku = $item->item_detail->sku;

							global $wpdb;

							$product_id = $wpdb->get_var($wpdb->prepare("SELECT post_id FROM $wpdb->postmeta WHERE meta_key='_sku' AND meta_value='%s' LIMIT 1", $sku));

							//square_woo_debug_log('info', "The result of searching AGAIN for item on woocommerce " . $product_id);
							$current_stock = get_post_meta($product_id, '_stock', true);
							update_post_meta($product_id, '_stock', $current_stock + $item->quantity);

							$order->add_product(wc_get_product($product_id), $item->quantity); //(get_product with id and next is for quantity)
						}


					}
				}


				$order_id = $order->get_order_number();

				add_post_meta($order_id, 'square_payment_id', $payment->id);
				//update order date according to square created date.
				// created_at


				$payment_date = $payment->created_at;
				$payment_date = str_replace("Z", "", $payment_date);
				$payment_date = explode("T", $payment_date);
				//2017-11-08 06:44:00
				$date = $payment_date[0] . " " . $payment_date[1];
				//check if there is discount in the order
				if ($payment->discount_money->amount) {
					$total = $order->calculate_totals();
					$order->set_total(-1 * $payment->discount_money->amount / 100, 'cart_discount');
					$order->set_total($total + ($payment->discount_money->amount / 100), 'total');

				} else {
					$total = $order->calculate_totals();
				}

				//$order->update_status(apply_filters('square_order_status','completed'));

				$message = sprintf(__('Square Order Successfully Created (Payment ID: %s).', 'woosquare'), $payment->id);
				$order->add_order_note($message);
				$order->update_status('completed');

				wc_reduce_stock_levels($order_id);


				global $wpdb;
				$sql = "UPDATE `wp_posts` SET `ID` = '" . $order_id . "', `post_date_gmt` = '" . $date . "', `post_date` = '" . $date . "' WHERE `ID` = '" . $order_id . "'";
				$rez = $wpdb->query($sql);
			}

			//Check if this request is "Refund Request"
			if (count($payment->refunds)) {
				//square_woo_debug_log('info', "Creating new refund and array of refund from square .".json_encode($payment->refunds));
				if (count($payment->refund_ids)) {

					$url = "https://connect." . WOOSQU_STAGING_URL . ".com/v2/refunds/" . $payment->refund_ids[0];
					$response = array();
					$method = "GET";
					$args = array('');
					$refunds_object = $square->wp_remote_woosquare($url, $args, $method, $headers, $response);
					if (!empty($refunds_object['response'])) {
						if ($refunds_object['response']['code'] == 200 and $refunds_object['response']['message'] == 'OK') {
							$refunds_object_json = json_decode($refunds_object['body'], false);
						} else {
							// square_woo_debug_log('info', "Square order not found order response for ".$payment." and order error ".json_encode($response));
						}
					} else {
						// square_woo_debug_log('info', "Square order not found order response for ".$payment." and order error ".json_encode($response));
					}


					//square_woo_debug_log('info', "Creating new refund and array of refund from square ." . json_encode($payment->refund_ids));

					global $wpdb;
					$results = $wpdb->get_results("select post_id, meta_key from $wpdb->postmeta where meta_value = '$payment->id'", ARRAY_A);
					//square_woo_debug_log('info', "refund query result :" . json_encode($results));
					if (count($results)) {
						$order_id = $results[0]['post_id'];
						$created_at = get_post_meta($order_id, "refund_created_at", true);
						$order = new WC_Order($order_id);
						if ($created_at != $refunds_object_json->refund->created_at and !empty($refunds_object_json->refund->created_at)) {// Avoid duplicate insert in case we refund from woo commerce which will fire payment update webhooks, so we need do nothing in this case to avoid dubplicate insertion
							$refund_obj = wc_create_refund(array('order_id' => $order_id, 'amount' => $refunds_object_json->refund->amount_money->amount / 100, 'reason' => $refunds_object_json->refund->reason));
							$user = get_user_by('login', 'square_user');
							wp_update_post(array('ID' => $refund_obj->id, 'post_author' => $user->ID));
							//update_post_meta($order_id, "refund_created_at", $payment->refunds[0]->created_at);
							//increase stock after refund.
							update_post_meta($order_id, "refund_created_at", $refunds_object_json->refund->created_at);
							$refund_message = sprintf(__('Refunded %s - Refund ID: %s - Reason: %s', 'woosquare'), wc_price($refunds_object_json->refund->amount_money->amount / 100), $refunds_object_json->refund->id, $refunds_object_json->refund->reason);

							$order->add_order_note($refund_message);
							foreach ($payment_order_obj->orders[0]->line_items as $item) {
								$variation_id = $item->catalog_object_id;
								$args = array(
										'post_type' => array('product', 'product_variation'),
										'meta_query' => array(array('key' => 'variation_square_id', 'value' => $variation_id)),
										'fields' => 'ids'
								);
								$vid_query = new WP_Query($args);
								$vid_ids = $vid_query->posts;
								if ($vid_ids) {
									$product_id = $vid_ids[0];
									$product = get_product($product_id);
									if ($product && $product->managing_stock()) {
										$old_stock = $product->get_stock_quantity();
										$new_stock = wc_update_product_stock($product, $item->quantity, 'increase');
										$order = new WC_Order($order_id);
										do_action('woocommerce_restock_refunded_item', $product->get_id(), $old_stock, $new_stock, $order, $product);
									}

								}
							}
						}
					}

					//}

				}
			}


//need to uncomment
			update_option('square_payment_begin_time', date("Y-m-d") . "T00:00:00Z");
			delete_option('square_calback_server');
		}

	}
}
function square_order_sync_add_on($order, $woo_square_locations, $currency, $uid, $token, $endpoint, $square_customer_id)
{
	$WooSquare_Plus_Gateway = new WooSquare_Plus_Gateway();
	$line_items_array = array();
	$order_shipping = $order->get_data(); // The Order data
	$discounted_amount = null;

	$totalcartitems = count($order->get_items());
	$total_order_item_qty = null;
	foreach ($order->get_items() as $item_id => $item_data) {
		$total_order_item_qty += $item_data->get_quantity();
	}

	// Coupons used in the order LOOP (as they can be multiple)
	if (!empty($order->get_used_coupons())) {
		foreach ($order->get_used_coupons() as $coupon_name) {

			// Retrieving the coupon ID
			$coupon_post_obj = get_page_by_title($coupon_name, OBJECT, 'shop_coupon');
			$coupon_id = $coupon_post_obj->ID;

			// Get an instance of WC_Coupon object in an array(necesary to use WC_Coupon methods)
			$coupons_obj = new WC_Coupon($coupon_id);

			if (!empty($coupons_obj)) {
				if ($coupons_obj->get_discount_type() == "fixed_product") {
					$discounted_amount_fixed_product = round($coupons_obj->get_amount(), 2);
				}
				if ($coupons_obj->get_discount_type() == "percent") {


					$discounted_amount_for_fixed_cart = round((($order->get_discount_total() + $order->get_total()) * $coupons_obj->get_amount()) / 100, 2);
				}
				if ($coupons_obj->get_discount_type() == "fixed_cart") {
					$discounted_amount_for_fixed_cart = ($coupons_obj->get_amount());
				}
			}
		}
	}

	if (!empty($discounted_amount_fixed_product)) {
		$discounts_for_fixed_product = ',"discounts": [
									{
									   "name": "' . $currency . ' ' . $discounted_amount_fixed_product . ' ' . $coupons_obj->get_discount_type() . '",
									   "amount_money": {
										  "amount": ' . (int)$WooSquare_Plus_Gateway->format_amount($discounted_amount_fixed_product, $currency) . ',
										  "currency": "' . $currency . '"
									   },
									   "scope": "ORDER"
									}
								 ]';
		$discounts = '';

	} else {
		$discounts_for_fixed_product = '';
	}
	if (!empty($discounted_amount_for_fixed_cart)) {

		$discounts_for_fixed_cart = ',"discounts": [
									{
									   "name": "' . $currency . ' ' . $discounted_amount_for_fixed_cart . ' ' . $coupons_obj->get_discount_type() . '",
									   "amount_money": {
										  "amount": ' . (int)$WooSquare_Plus_Gateway->format_amount($discounted_amount_for_fixed_cart, $currency) . ',
										  "currency": "' . $currency . '"
									   },
									   "scope": "ORDER"
									}
								 ]';
		$discounts = '';
	} else {
		$discounts_for_fixed_cart = '';
	}

	$iteration = 0;
	foreach ($order->get_items() as $item_id => $item_data) {
		$discounted_amount = null;
		// Get an instance of corresponding the WC_Product object

		$product = $item_data->get_product();
		$get_id = $product->get_id();
		$product_name = $product->get_name(); // Get the product name

		$item_quantity = $item_data->get_quantity(); // Get the item quantity

		$item_total = $product->get_price(); // Get the item line total
		$tax_rates = WC_Tax::get_rates($product->get_tax_class());

		$tax_data = $item_data->get_data();

		$modifier_object = array();

		foreach ($item_data->get_meta_data() as $Kkey => $meta_data_obj) {
			$meta_data_array = $meta_data_obj->get_data();
			$meta_key = $meta_data_array['key'];
			$meta_value = $meta_data_array['value'];

			if ($meta_key == "Modifier") {
				$modifier_value = get_post_meta($get_id, 'product_modifier_group_name', true);
				foreach ($modifier_value as $key => $mod) {
					global $wpdb;
					$modifier_catlog_id = $wpdb->get_var("SELECT modifier_set_unique_id FROM " . $wpdb->prefix . "woosquare_modifier WHERE modifier_id = '$mod[2]' ");
					$term_query = $wpdb->get_results(("SELECT term_id FROM " . $wpdb->prefix . "term_taxonomy WHERE taxonomy = '$mod'"));
					if (!empty($term_query)) {
						foreach ($term_query as $term) {
							$old_object = get_term_by('id', $term->term_id, $mod);
							if ($old_object->name == $meta_value) {
								$amount_mod = get_term_meta($term->term_id, 'term_meta_price', true);
								$modifier_object[$Kkey] = (object)array(
									//"catalog_object_id"  => $modifier_catlog_id,
										"name" => $meta_value,
										"base_price_money" => (object)array(
												"amount" => (int)$WooSquare_Plus_Gateway->format_amount($amount_mod, $currency),
												"currency" => $currency
										),
								);
							}
						}
					}
				}
			}
		}

		$itemname = str_replace('"', '', $product_name);
		// price without tax - price with tax = xxxx /  price without tax *100
		if (!empty($tax_data['taxes']['total'])) {
			$pricewithouttax = $tax_data['total'];

			$pricewithtax = $tax_data['total'] + round($tax_data['taxes']['total'][key($tax_data['taxes']['total'])], 2);
			$res = $pricewithtax - $pricewithouttax;
			//$perc = ($res/$pricewithouttax )*100;
			if (!empty($tax_rates)) {
				$perc = reset($tax_rates);
				$perc = $perc['rate'];
			} else {
				$perc = ($res / $pricewithouttax) * 100;
				$perc = round($perc, 2);
			}

			if (!empty($tax_data['total_tax'])) {
				$tax_amount = $tax_data['total_tax'];
			}
			//	$pricewithtax = $tax_data['total'] + round($tax_data['taxes']['total'][key($tax_data['taxes']['total'])],2);
			//	$res = $pricewithtax - $pricewithouttax;
			//	$perc = ($res/$pricewithouttax )*100;

			if ($pricewithouttax > 0) {
				$item_tax = ',"taxes": [
    									{
    									   "name": "Sales Tax For ' . $itemname . '",
    									   "type": "ADDITIVE",
    									   "percentage": "' . $perc . '"
    									}
    								 ]';
			} else {
				$item_tax = '';
			}
		} else {
			$item_tax = '';
		}


		$amount = (float)$WooSquare_Plus_Gateway->format_amount($item_total, $currency);

		if ($product->get_type() === "subscription") {
			$_subscription_trial_length = get_post_meta($get_id, '_subscription_trial_length', true);
			if (
					!empty($_subscription_trial_length)
					and
					is_numeric($_subscription_trial_length)
			) {
				$amount = (int)$WooSquare_Plus_Gateway->format_amount(0, $currency);
				$_subscription_trial_period = get_post_meta($get_id, '_subscription_trial_period', true);
				$itemname = $itemname . ' with a ' . $_subscription_trial_length . '-' . $_subscription_trial_period . ' free trial ';
			}

		}

		$note = '';

		$first_name = version_compare(WC_VERSION, '3.0.0', '<') ? $order->billing_first_name : $order->get_billing_first_name();
		$last_name = version_compare(WC_VERSION, '3.0.0', '<') ? $order->billing_last_name : $order->get_billing_last_name();
		if (empty($first_name) and empty($last_name)) {
			$first_name = $last_name = null;
		}


		if (($product->get_type() === "booking")) {
			$column_data = '';
			$booking_data = new WC_Booking_Data_Store();
			$booking_ids = $booking_data->get_booking_ids_from_order_id($order->get_id());
			$booking = new WC_Booking($booking_ids[$iteration]);
			$booker_date = $booking->get_start_date();
			$note .= " " . $booker_date . " - ";

			foreach ($booking->get_persons() as $id => $qty) {
				$note .= get_the_title($id) . ": " . $qty . " , ";
			}

			$note .= "Order #" . $order->get_order_number();


			//if (!empty($order->get_customer_note())) {
			if (!empty($order->get_customer_note())) {
				$remove_special_ch = preg_replace('/[^A-Za-z0-9. -]/', '', $order->get_customer_note());
				$note .= " Order Note: " . $remove_special_ch;
			}
			$amount_total = (int)round($WooSquare_Plus_Gateway->format_amount($order->get_total(), $currency), 1);
			$square = new Square(get_option('woo_square_access_token'.WOOSQU_SUFFIX), get_option('woo_square_location_id'.WOOSQU_SUFFIX), WOOSQU_PLUS_APPID);
			$wooToSquareSynchronizer = new WooToSquareSynchronizer($square);
			$wooToSquareSynchronizerObject = $wooToSquareSynchronizer->paymentReportingWooToSquare($product);

			if (get_option('woocommerce_square_payment_reporting') == 'yes') {
				if (!empty($modifier_object)) {
					$modifier_object = json_encode(($modifier_object));

					if($wooToSquareSynchronizerObject->errors or $wooToSquareSynchronizerObject == 1){
						$line_items_array[] = '{
									 "name": "' . $itemname . '",
									 "note": "' . $note . '",
                                      "quantity": "' . $item_quantity . '",
                                      "modifiers": ' . $modifier_object . ',
                                      "base_price_money": {
							            "amount": ' . $amount . ',
						             	"currency": "' . $currency . '"
						                }' . $item_tax . '
                                        ' . $discounts_for_fixed_product . '
				                     	}';
					} else {
						//"WooCommerce: Order #' . (string) $order->get_order_number().' Customer Name: '. $first_name .' '.$last_name.'"
						$base_price = $product->get_price();
						$product_data = $product->get_data();
						$total_each_product = $item_data->get_total();
						$amount = $tax_data['subtotal'];
						//$amount = (float) $WooSquare_Plus_Gateway->format_amount( $total_each_product, $currency );
						$amount = (float)$WooSquare_Plus_Gateway->format_amount($amount, $currency);
						$line_items_array[] = '{
									"catalog_object_id": "' . $wooToSquareSynchronizerObject . '",
									"note": "' . $note . '",
                                      "quantity": "' . $item_quantity . '",
                                      "modifiers": ' . $modifier_object . ',
                                      "base_price_money": {
							            "amount": ' . $amount . ',
						             	"currency": "' . $currency . '"
						                }' . $item_tax . '
                                        ' . $discounts_for_fixed_product . '
				                     	}';
					}

				} else {
					if ($wooToSquareSynchronizerObject->errors or $wooToSquareSynchronizerObject == 1) {
						$line_items_array[] = '{
									 "name": "' . $itemname . '",
									 "note": "' . $note . '",
                                      "quantity": "' . $item_quantity . '",
                                      "base_price_money": {
							            "amount": ' . $amount . ',
						             	"currency": "' . $currency . '"
						                }' . $item_tax . '
                                        ' . $discounts_for_fixed_product . '
				                     	}';
					} else {
						//"WooCommerce: Order #' . (string) $order->get_order_number().' Customer Name: '. $first_name .' '.$last_name.'"
						$base_price = $product->get_price();
						$product_data = $product->get_data();
						$total_each_product = $item_data->get_total();
						$amount = $tax_data['subtotal'];
						//$amount = (float) $WooSquare_Plus_Gateway->format_amount( $total_each_product, $currency );
						$amount = (float)$WooSquare_Plus_Gateway->format_amount($amount, $currency);
						$line_items_array[] = '{
									"catalog_object_id": "' . $wooToSquareSynchronizerObject . '",
									"note": "' . $note . '",
                                      "quantity": "' . $item_quantity . '",
                                      "base_price_money": {
							            "amount": ' . $amount . ',
						             	"currency": "' . $currency . '"
						                }' . $item_tax . '
                                        ' . $discounts_for_fixed_product . '
				                     	}';
					}

				}

			} else {
				if (!empty($modifier_object)) {
					$modifier_object = json_encode(($modifier_object));
					$line_items_array[] = '{
									 "name": "' . $itemname . '",
									 "note": "' . $note . '",
                                      "quantity": "' . $item_quantity . '",
                                      "modifiers": ' . $modifier_object . ',
                                      "base_price_money": {
							            "amount": ' . $amount . ',
						             	"currency": "' . $currency . '"
						                }' . $item_tax . '
                                        ' . $discounts_for_fixed_product . '
				                     	}';
				} else {
					$line_items_array[] = '{
									 "name": "' . $itemname . '",
									 "note": "' . $note . '",
                                      "quantity": "' . $item_quantity . '",
                                      "base_price_money": {
							            "amount": ' . $amount . ',
						             	"currency": "' . $currency . '"
						                }' . $item_tax . '
                                        ' . $discounts_for_fixed_product . '
				                     	}';
				}

			}
		} else {
			if (get_option('woocommerce_square_payment_reporting') == 'yes') {

				$amount_total = (int)round($WooSquare_Plus_Gateway->format_amount($order->get_total(), $currency), 1);

				$square = new Square(get_option('woo_square_access_token'.WOOSQU_SUFFIX), get_option('woo_square_location_id'.WOOSQU_SUFFIX), WOOSQU_PLUS_APPID);
				$wooToSquareSynchronizer = new WooToSquareSynchronizer($square);
				$wooToSquareSynchronizerObject = $wooToSquareSynchronizer->paymentReportingWooToSquare($product);

				update_post_meta((string) $order->get_order_number(),'WooSquare_Order_wooToSquareSynchronizerObject',json_encode($wooToSquareSynchronizerObject));

				$note .= " ";

				$note .= "Order #" . $order->get_order_number();

				if (!empty($order->get_customer_note())) {
					$remove_special_ch =  preg_replace('/[^A-Za-z0-9. -]/', '', $order->get_customer_note());
					$note .= " Order Note: " . $remove_special_ch;
				}

				if (!empty($modifier_object)) {
					$modifier_object = json_encode(($modifier_object));

					if($wooToSquareSynchronizerObject->errors or $wooToSquareSynchronizerObject == 1){
						$line_items_array[] = '{
									 "name": "' . $itemname . '",
									 "note": "' . $note . '",
                                      "quantity": "' . $item_quantity . '",
                                      "modifiers": ' . $modifier_object . ',
                                      "base_price_money": {
							            "amount": ' . $amount . ',
						             	"currency": "' . $currency . '"
						                }' . $item_tax . '
                                        ' . $discounts_for_fixed_product . '
				                     	}';
					} else {
						//"WooCommerce: Order #' . (string) $order->get_order_number().' Customer Name: '. $first_name .' '.$last_name.'"
						$base_price = $product->get_price();
						$product_data = $product->get_data();
						$total_each_product = $item_data->get_total();
						$amount = $tax_data['subtotal'];
						//$amount = (float) $WooSquare_Plus_Gateway->format_amount( $total_each_product, $currency );
						$amount = (float)$WooSquare_Plus_Gateway->format_amount($amount, $currency);
						$line_items_array[] = '{
									"catalog_object_id": "' . $wooToSquareSynchronizerObject . '",
									"note": "' . $note . '",
                                      "quantity": "' . $item_quantity . '",
                                      "modifiers": ' . $modifier_object . ',
                                      "base_price_money": {
							            "amount": ' . $amount . ',
						             	"currency": "' . $currency . '"
						                }' . $item_tax . '
                                        ' . $discounts_for_fixed_product . '
				                     	}';
					}

				} else {
					if ($wooToSquareSynchronizerObject->errors or $wooToSquareSynchronizerObject == 1) {
						$line_items_array[] = '{
									 "name": "' . $itemname . '",
									 "note": "' . $note . '",
                                      "quantity": "' . $item_quantity . '",
                                      "base_price_money": {
							            "amount": ' . $amount . ',
						             	"currency": "' . $currency . '"
						                }' . $item_tax . '
                                        ' . $discounts_for_fixed_product . '
				                     	}';
					} else {
						//"WooCommerce: Order #' . (string) $order->get_order_number().' Customer Name: '. $first_name .' '.$last_name.'"
						$base_price = $product->get_price();
						$product_data = $product->get_data();
						$total_each_product = $item_data->get_total();
						$amount = $tax_data['subtotal'];
						//$amount = (float) $WooSquare_Plus_Gateway->format_amount( $total_each_product, $currency );
						$amount = (float)$WooSquare_Plus_Gateway->format_amount($amount, $currency);
						$line_items_array[] = '{
									"catalog_object_id": "' . $wooToSquareSynchronizerObject . '",
									"note": "' . $note . '",
                                      "quantity": "' . $item_quantity . '",
                                      "base_price_money": {
							            "amount": ' . $amount . ',
						             	"currency": "' . $currency . '"
						                }' . $item_tax . '
                                        ' . $discounts_for_fixed_product . '
				                     	}';
					}

				}

			} else {
				if (!empty($modifier_object)) {
					$modifier_object = json_encode(($modifier_object));
					$line_items_array[] = '{
									 "name": "' . $itemname . '",
									 "note": "' . $note . '",
                                      "quantity": "' . $item_quantity . '",
                                      "modifiers": ' . $modifier_object . ',
                                      "base_price_money": {
							            "amount": ' . $amount . ',
						             	"currency": "' . $currency . '"
						                }' . $item_tax . '
                                        ' . $discounts_for_fixed_product . '
				                     	}';
				} else {
					$line_items_array[] = '{
									 "name": "' . $itemname . '",
									 "note": "' . $note . '",
                                      "quantity": "' . $item_quantity . '",
                                      "base_price_money": {
							            "amount": ' . $amount . ',
						             	"currency": "' . $currency . '"
						                }' . $item_tax . '
                                        ' . $discounts_for_fixed_product . '
				                     	}';
				}

			}
		}

		$discounts_for_fixed_product = '';

		if ($product->get_type() === "subscription") {
			$_subscription_sign_up_fee = get_post_meta($get_id, '_subscription_sign_up_fee', true);

			if (
					!empty($_subscription_sign_up_fee)
					and
					is_numeric($_subscription_sign_up_fee)
			) {
				$line_items_array[] = '{
							"name": "Sign-up fee for ' . str_replace('"', '', $product_name) . '",
							"note": "",
							"quantity": "' . $item_quantity . '",
							"base_price_money": {
								"amount": ' . (int)$WooSquare_Plus_Gateway->format_amount($_subscription_sign_up_fee, $currency) . ',
								"currency": "' . $currency . '"
							}
						}';
			}
		}

		$line_items = implode(', ', $line_items_array);
		$iteration++;
	}

	// Iterating through order shipping items
	foreach( $order->get_items( 'shipping' ) as $item_id => $shipping_item_obj ){
		// Get the data in an unprotected array
		$shipping_item_data = $shipping_item_obj->get_data();

		$shipping_data_id           = $shipping_item_data['id'];
		$shipping_data_order_id     = $shipping_item_data['order_id'];
		$shipping_data_name         = $shipping_item_data['name'];
		$shipping_data_method_title = $shipping_item_data['method_title'];
		$shipping_data_method_id    = $shipping_item_data['method_id'];
		$shipping_data_instance_id  = $shipping_item_data['instance_id'];
		$shipping_data_total        = $shipping_item_data['total'];
		$shipping_data_total_tax    = $shipping_item_data['total_tax'];
		$shipping_data_taxes        = $shipping_item_data['taxes'];


	}

	if(empty($shipping_data_method_title)){
		if(!empty($order->get_shipping_company )|| !empty($order->get_billing_company)) {
			$shipping_data_method_title = empty($order->get_shipping_company) ? $order->get_billing_company : $order->get_shipping_company();
			if(empty($shipping_data_method_title)){
				$shipping_data_method_title = 'No Shipping Selected';
			}
		}
	}

	if(@$_POST['ship_to_different_address'] == "1"){
		//shipping

		$fulfillments[] = '{
								"shipment_details": {
								"recipient": {
									"address": {
                                    "carrier":  "'.$shipping_data_method_title.'",
									"address_line_1": "'.$order->get_shipping_address_1().'",
									"country":  "'.$order->get_shipping_country().'",
									"first_name": "'.$order->get_shipping_first_name().'",
									"last_name": "'.$order->get_shipping_last_name().'",
									"locality":  "'.$order->get_shipping_city().'",
									"postal_code":  "'.$order->get_shipping_postcode().'"
								  },
								  "display_name": "'.$order->get_shipping_first_name().'",
								  "email_address": "'.$order->get_billing_email().'",
								  "phone_number": "'.$order->get_billing_phone().'"
								}
							  },
							  "state": "PROPOSED",
							  "type": "SHIPMENT"
							}';

	}else{
		//billing
		$fulfillments[] = '{
								"shipment_details":{
								"recipient": {
									"address": {
                                    "carrier":  "'.$shipping_data_method_title.'",
									"address_line_1": "'.$order->get_billing_address_1().'",
									"country":  "'.$order->get_billing_country().'",
									"first_name": "'.$order->get_billing_first_name().'",
									"last_name": "'.$order->get_billing_last_name().'",
									"locality":  "'.$order->get_billing_city().'",
									"postal_code":  "'.$order->get_billing_postcode().'"
								  },
								  "display_name": "'.$order->get_billing_first_name().'",
								  "email_address": "'.$order->get_billing_email().'",
								  "phone_number": "'.$order->get_billing_phone().'"
								}
							  },
							  "state": "PROPOSED",
							  "type": "SHIPMENT"
							}';

	}

	$fulfillmentss = implode( ', ', $fulfillments );

	$webSource[] = '{"name": "Woosquare Plus"}' ;
	$source = implode( ', ', $webSource);

	update_post_meta((string) $order->get_order_number(),'WooSquare_Order_lineitems_request',$line_items);
	update_post_meta((string) $order->get_order_number(),'WooSquare_Order_linediscounts_request',json_encode($discounts_for_fixed_cart));

	//coupon applied on whole cart
	$order_create = '{
							"idempotency_key": "' . $uid . '",
							"order": {
							"reference_id": "' . (string)$order->get_order_number() . '",
							"location_id": "' . $woo_square_locations . '",
							"source": '.$source.',
						    "fulfillments": ['.$fulfillmentss.'],
							"line_items": [' . $line_items . ']
							' . $discounts_for_fixed_cart . '
							}
						}';

	update_post_meta((string) $order->get_order_number(),'WooSquare_Order_request',$order_create);
	$order_forcustomer = json_decode($order_create);
	update_post_meta((string) $order->get_order_number(),'WooSquare_Order_customer',$square_customer_id);
	if (!empty($square_customer_id)) {
		$order_forcustomer->order->customer_id = $square_customer_id;
		$order_create = json_encode($order_forcustomer);
	}



	// $order_create = apply_filters('woosquare_addon_add_shipping',$order_create,$order,$currency,$WooSquare_Plus_Gateway);

	// Iterating through order shipping items
	/*foreach ($order->get_items('shipping') as $item_id => $shipping_item_obj) {
        // Get the data in an unprotected array
        $shipping_item_data = $shipping_item_obj->get_data();

        $shipping_data_id = $shipping_item_data['id'];
        $shipping_data_order_id = $shipping_item_data['order_id'];
        $shipping_data_name = $shipping_item_data['name'];
        $shipping_data_method_title = $shipping_item_data['method_title'];
        $shipping_data_method_id = $shipping_item_data['method_id'];
        $shipping_data_instance_id = $shipping_item_data['instance_id'];
        $shipping_data_total = $shipping_item_data['total'];
        $shipping_data_total_tax = $shipping_item_data['total_tax'];
        $shipping_data_taxes = $shipping_item_data['taxes'];


    }*/


	if (!empty($shipping_data_total)
			and
			!empty($shipping_data_method_title)
	) {
		$add_shipping = json_decode($order_create);

		$shiping_item_array = (object)array(
				'name' => $shipping_data_method_title,
				'note' => 'Shipping Cost',
				'quantity' => '1',
				'base_price_money' => (object)array(
						'amount' => round($WooSquare_Plus_Gateway->format_amount($shipping_data_total, $currency), 2),
						'currency' => $currency
				),
		);

		// price without tax - price with tax = xxxx /  price without tax *100
		if ($shipping_data_total_tax > 0) {
			$pricewithouttax = $shipping_data_total;

			$pricewithtax = $shipping_data_total + round($shipping_data_total_tax, 2);
			$res = $pricewithtax - $pricewithouttax;
			$perc = ($res / $pricewithouttax) * 100;
			$shiping_item_array->taxes = json_decode(
					'[
        									{
        									   "name": "Shipping Sales Tax",
        									   "type": "ADDITIVE",
        									   "percentage": "' . round($perc, 2) . '"
        									}
        								 ]'
			);


		}


		end($add_shipping->order->line_items);// move the internal pointer to the end of the array
		$key = key($add_shipping->order->line_items) + 1;
		$add_shipping->order->line_items[$key] = $shiping_item_array;


		if (!empty($add_shipping->order->discounts)) {

			if ($coupons_obj->get_discount_type() == "percent") {
				$shiptotal = round($shipping_data_total * $coupons_obj->get_amount() / 100, 2);
				$add_shipping->order->discounts[0]->amount_money->amount = $WooSquare_Plus_Gateway->format_amount($discounted_amount_for_fixed_cart - $shiptotal, $currency);
			}

		}

		$order_create = json_encode($add_shipping);
	}

	if (class_exists('WC_Pre_Orders_Order')) {
		if (get_post_meta($get_id, '_wc_pre_orders_enabled', true) && get_post_meta($get_id, '_wc_pre_orders_fee', true) > 0) {


			$pre_order_before = json_decode($order_create);

			$pre_order_before_add = '';
			$pre_order_fee = get_post_meta($get_id, '_wc_pre_orders_fee', true);


			$gettax_rate = (reset(WC_Tax::get_rates())['rate']);


			if ($gettax_rate > 0) {
				$preorder_with_tax = ($gettax_rate / 100) * $pre_order_fee;
				$finalamount = $preorder_with_tax + $pre_order_fee;


				$pre_order_before->order->service_charges[0] = (object)array(

						'name' => 'Pre Order Price',
						'note' => 'Preorder Cost',
						'calculation_phase' => 'SUBTOTAL_PHASE',
						'taxable' => true,
						'amount_money' => (object)array(
								'amount' => round($WooSquare_Plus_Gateway->format_amount($finalamount, $currency), 2),
								'currency' => $currency
						),


				);


				$order_create = json_encode($pre_order_before);

			} else {

				$pre_order_before->order->service_charges[0] = (object)array(

						'name' => 'Pre Order Price',
						'note' => 'Preorder Cost',
						'amount_money' => (object)array(
								'amount' => round($WooSquare_Plus_Gateway->format_amount($pre_order_fee, $currency), 2),
								'currency' => $currency
						),


				);


				$order_create = json_encode($pre_order_before);
			}

		}
	}


	update_post_meta((string)$order->get_order_number(), 'WooSquare_Order_create_request', $order_create);

	$square = new Square(get_option('woo_square_access_token'.WOOSQU_SUFFIX), get_option('woo_square_location_id'.WOOSQU_SUFFIX), WOOSQU_PLUS_APPID);
	$url = "https://connect." . $endpoint . ".com/v2/orders";
	$method = "POST";
	$headers = array(
			'Authorization' => 'Bearer ' . $token, // Use verbose mode in cURL to determine the format you want for this header
			'cache-control' => 'no-cache',
			'Content-Type' => 'application/json'
	);
	// $response = array();
	$response = $square->wp_remote_woosquare($url, json_decode($order_create), $method, $headers, $response);


	if ($response['response']['code'] == 200 and $response['response']['message'] == 'OK') {
		$orderresponse = json_decode($response['body'], false);
		$order_created = sprintf(__('Square order created ( Order ID : %s )', 'wpexpert-square'), $orderresponse->order->id);
		$order->add_order_note($order_created);
		update_post_meta((string)$order->get_order_number(), 'WooSquare_Order_create_response', $response['body']);
		//update_post_meta((string) $order->get_order_number(),'WooSquare_Order_create_response',$response);
	} else {
		$order_created = sprintf(__('Square order created error ( response : %s )', 'wpexpert-square'), $response);
		$order->add_order_note($order_created);
		update_post_meta((string)$order->get_order_number(), 'WooSquare_Order_create_response_error', $response['errors']);
		//update_post_meta((string) $order->get_order_number(),'WooSquare_Order_create_response_error',$response);
	}

	//check if WooCommerce order total is not equal with Square order total skip this order.

	$amount = (int)$WooSquare_Plus_Gateway->format_amount($order->get_total(), $currency);

	/*if (!empty($orderresponse->order)) {
        if ($amount == $orderresponse->order->total_money->amount) {
            return $orderresponse->order->id;
        } else {
            update_post_meta((string)$order->get_order_number(), 'WooSquare_Order_create_status', 'The Square payment total does not match the WooCommerce order total.');
            return;
        }
    }*/

	if(!empty($orderresponse->order)){

		if($amount == $orderresponse->order->total_money->amount){
			return $orderresponse->order->id;
		} else {

			if($amount > $orderresponse->order->total_money->amount){
				$adjustment =  (($amount/100) - ($orderresponse->order->total_money->amount/100));
				$idempotencyKey = (string)rand(10000,200000);
				$order_adjustment ='{
							"idempotency_key": "'.$idempotencyKey.'",
							"order": {
                                       "version": '.$orderresponse->order->version.',
                                         "line_items": [
                                          {
                                           "name":"Adjustment",
                                            "quantity": "1",
									         "base_price_money":{
										     "amount":'.(int)$WooSquare_Plus_Gateway->format_amount( $adjustment, $currency ).',
										     "currency": "'.$currency.'"
									       }
                                        }
                                     ]
                                   }
						        }';

				$square = new Square(get_option('woo_square_access_token'.WOOSQU_SUFFIX), get_option('woo_square_location_id'.WOOSQU_SUFFIX),WOOSQU_PLUS_APPID);
				$url = "https://connect.".$endpoint.".com/v2/orders/".$orderresponse->order->id;
				$method = "PUT";
				$response = array();
				$headers = array(
						'Authorization' => 'Bearer '.$token, // Use verbose mode in cURL to determine the format you want for this header
						'cache-control'  => 'no-cache',
						'Content-Type'  => 'application/json'
				);
				$response = $square->wp_remote_woosquare($url,json_decode($order_adjustment),$method,$headers,$response);
				$orderresponse = json_decode( $response['body'], false );

				if($response['response']['code'] == 200 and $response['response']['message'] == 'OK'){
					update_post_meta((string) $order->get_order_number(),'WooSquare_Order_create_response_discount_adjustment',$response['body']);
					return $orderresponse->order->id;

				} else {
					update_post_meta((string) $order->get_order_number(),'WooSquare_Order_create_response_adjustment_failed',$response['body']);
					update_post_meta((string) $order->get_order_number(),'WooSquare_Order_create_status','The Square payment total does not match the WooCommerce order total.');
					return ;
				}

			}
			else if($amount < $orderresponse->order->total_money->amount){

				$adjustment =  number_format((($orderresponse->order->total_money->amount) / 100) - ($amount/100) , 2 );
				$idempotencyKey = (string)rand(10000,200000);

				$order_adjustment = '{
							"idempotency_key": "'.$idempotencyKey.'",
							"order": {
							"version": '.$orderresponse->order->version.',
                                  "discounts": [
                                          {
                                            "name":"Adjustment",
                                            "type":"FIXED_AMOUNT",
									         "amount_money": {
										     "amount": '.(int) $WooSquare_Plus_Gateway->format_amount( $adjustment, $currency )  .',
										     "currency": "'.$currency.'"
									           },
									          "scope": "ORDER"
                                             }
                                        ]
                                   }
						      }';

				$square = new Square(get_option('woo_square_access_token'.WOOSQU_SUFFIX), get_option('woo_square_location_id'.WOOSQU_SUFFIX),WOOSQU_PLUS_APPID);
				$url = "https://connect.".$endpoint.".com/v2/orders/".$orderresponse->order->id;
				$method = "PUT";
				$response = array();
				$headers = array(
						'Authorization' => 'Bearer '.$token, // Use verbose mode in cURL to determine the format you want for this header
						'cache-control'  => 'no-cache',
						'Content-Type'  => 'application/json'
				);


				$response = $square->wp_remote_woosquare($url,json_decode($order_adjustment),$method,$headers,$response);
				$orderresponse = json_decode( $response['body'], false );

				if($amount > $orderresponse->order->total_money->amount){

					$adjustment =  (($amount/100) - ($orderresponse->order->total_money->amount/100));

					$idempotencyKey = (string)rand(10000,200000);

					$order_adjustment ='{
							"idempotency_key": "'.$idempotencyKey.'",
							"order": {
                                       "version": '.$orderresponse->order->version.',
                                         "line_items": [
                                          {
                                           "name":"Adjustment",
                                            "quantity": "1",
									         "base_price_money":{
										     "amount":'.(int)$WooSquare_Plus_Gateway->format_amount( $adjustment, $currency ).',
										     "currency": "'.$currency.'"
									       }
                                        }
                                     ]
                                   }
						        }';

					$square = new Square(get_option('woo_square_access_token'.WOOSQU_SUFFIX), get_option('woo_square_location_id'.WOOSQU_SUFFIX),WOOSQU_PLUS_APPID);
					$url = "https://connect.".$endpoint.".com/v2/orders/".$orderresponse->order->id;
					$method = "PUT";
					$response = array();
					$headers = array(
							'Authorization' => 'Bearer '.$token, // Use verbose mode in cURL to determine the format you want for this header
							'cache-control'  => 'no-cache',
							'Content-Type'  => 'application/json'
					);
					$response = $square->wp_remote_woosquare($url,json_decode($order_adjustment),$method,$headers,$response);
					$orderresponse = json_decode( $response['body'], false );

					if($response['response']['code'] == 200 and $response['response']['message'] == 'OK'){
						update_post_meta((string) $order->get_order_number(),'WooSquare_Order_create_response_discount_adjustment',$response['body']);
						return $orderresponse->order->id;

					} else {
						update_post_meta((string) $order->get_order_number(),'WooSquare_Order_create_response_adjustment_failed',$response['body']);
						update_post_meta((string) $order->get_order_number(),'WooSquare_Order_create_status','The Square payment total does not match the WooCommerce order total.');
						return ;
					}
				} else{
					if($response['response']['code'] == 200 and $response['response']['message'] == 'OK'){
						update_post_meta((string) $order->get_order_number(),'WooSquare_Order_create_response_discount_adjustment',$response['body']);
						return $orderresponse->order->id;

					} else {
						update_post_meta((string) $order->get_order_number(),'WooSquare_Order_create_response_adjustment_failed',$response['body']);
						update_post_meta((string) $order->get_order_number(),'WooSquare_Order_create_status','The Square payment total does not match the WooCommerce order total.');
						return ;
					}
				}
			}
		}
	}

}


?>