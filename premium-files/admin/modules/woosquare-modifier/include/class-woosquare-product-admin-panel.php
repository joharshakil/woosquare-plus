<?php

if ( class_exists( 'WooSquare_Product_Modifier', false ) ) {
	return new WooSquare_Product_Modifier();
}

class WooSquare_Product_Modifier
{

	public function __construct()
	{

		add_filter('woocommerce_product_data_tabs', array($this, 'wsm_woosquare_modifier_tab'));

		add_action('woocommerce_product_data_panels', array($this, 'wsm_woosquare_display_fields'));

		//add_action('woocommerce_process_product_meta', array($this, 'wsm_woosquare_save_values'));

		add_action( 'wp_ajax_wsm_woosquare_save_fields',array($this, 'wsm_woosquare_save_fields')  );

		add_action( 'wp_ajax_nopriv_wsm_woosquare_save_fields', array($this, 'wsm_woosquare_save_fields') );

		add_action( 'woocommerce_before_add_to_cart_button', array($this, 'wsm_product_modifier_display') , 10, 0  );

		add_filter( 'woocommerce_add_cart_item_data',array($this, 'wsm_add_custom_field_item_data'), 10, 4 );

		add_action( 'woocommerce_before_calculate_totals', array($this, 'wsm_before_calculate_totals'), 10, 1 );

		add_filter( 'woocommerce_cart_item_name', array($this,'wsm_cart_item_name'), 10, 3 );

		add_action( 'woocommerce_checkout_create_order_line_item', array($this,'wsm_add_custom_data_to_order'), 10, 4 );

	//	add_filter( 'woocommerce_add_to_cart_validation', array($this,'wsm_validation_modifier'), 10, 3 );

	}

	function wsm_validation_modifier( $passed, $product_id, $quantity ) {

		$modifier_value = get_post_meta( $product_id, 'product_modifier_group_name' , true);

		if(!empty($modifier_value)) {
			if (empty($_POST['modifier_field_product']) && empty($_POST['modifier_field_product_one'])) {
				// Fails validation
				$passed = false;
				wc_add_notice(__('Please select some modifier options before adding  product to your cart.', 'woosquare_modifier'), 'error');
			}
		}
		return $passed;
	}


	public	function wsm_add_custom_field_item_data( $cart_item_data, $product_id, $variation_id, $quantity ) {
		if( ! empty( $_POST['modifier_field_product']  )  || ! empty( $_POST['modifier_field_product_one']  ) ) {

			$cart_item_data = array();
			$product = wc_get_product($product_id); // Expanded function
			$amountcal = 0;
			$amountcal_modifier_single = 0;
			if (isset($_POST['modifier_field_product']) ) {
				foreach ($_POST['modifier_field_product'] as $key => $value) {
			
					$cart_item_data[$value] = explode('|', $value);
                   if(explode('|', $value)[1] > 0){ 
					$amountcal = $amountcal + explode('|', $value)[1];
                   } else {
                       $amountcal = $amountcal;
                      }
				}
			}

			if(isset($_POST['modifier_field_product_one'])){
				foreach ($_POST['modifier_field_product_one'] as $key => $value) {
					$cart_item_data[$value] = explode('|', intval($value));
					if(!empty(explode('|', $value)[1])) {
						$amountcal_modifier_single = ($amountcal_modifier_single + explode('|', $value)[1]);
					}
				}
			}



			if(strpos($product->get_price(), '-')) {

				 $price =  str_replace('-', '.', $product->get_price());

				$price = $price + $amountcal + $amountcal_modifier_single;

			} else {

				$price = $product->get_price() + $amountcal + $amountcal_modifier_single;

			}

			///print_r($price);
			$cart_item_data['total_price'] = $price;

			return $cart_item_data;
		}
	}



	public function wsm_before_calculate_totals( $cart_obj ) {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}
		// Iterate through each cart item
		foreach( $cart_obj->get_cart() as $key=>$value ) {
			if( isset( $value['total_price'] ) ) {
				$price = $value['total_price'];
				$value['data']->set_price( ( $price ) );
			}
		}
	}

	public	function wsm_cart_item_name( $name, $cart_item, $cart_item_key ) {

		if(isset($cart_item)) {
			foreach ($cart_item as $key => $value) {

				$cart_item01 = (explode('|', $key));

				if (array_key_exists(1, $cart_item01)) {
					$name .= sprintf(
							'<p>%s</p>',
							esc_html($cart_item01[0])
					);

				}

			}

			return $name;
		}
	}

	public	function wsm_add_custom_data_to_order( $item, $cart_item_key, $values, $order )
	{

		if (isset($values)) {

			foreach ($values as $key => $value) {
				$cart_item_val = (explode('|', $key));
				if (array_key_exists(1, $cart_item_val)) {

					$item->add_meta_data(__('Modifier', 'woosquare_modifier_order'), $cart_item_val[0], false);

				}
			}
		}
	}


// Show Tab
	public function wsm_woosquare_modifier_tab($tabs)
	{
		$tabs['woosquare_modifier'] = array(
				'label' => __('Woosquare Modifier', 'woosquare_modifier'),
				'target' => 'woosquare_modifier_panel',
				'class' => array('woosquare_modifier_tab', 'show_if_simple', 'show_if_variable'),
				'priority' => 80,
		);
		return $tabs;
	}

	public function wsm_woosquare_display_fields()
	{
		?>
		<div id='woosquare_modifier_panel' class='panel woocommerce_options_panel hidden'>
			<div class="options_group">
				<?php
				include 'view/html-product-data-modifier-panel.php';
				?>
			</div>
		</div>
		<?php
	}

	public function wsm_woosquare_save_values($post_id,$product_modifier_group_name,$required_set){

		$product = wc_get_product($post_id);
		if (isset($product_modifier_group_name) && is_array($product_modifier_group_name)) {
			$product->update_meta_data('product_modifier_group_name', $product_modifier_group_name);
		}

		if (isset($required_set) && is_array($required_set)) {
			$product->update_meta_data('product_required_set', $required_set);
		}

		$product->save();
	}

	public function wsm_woosquare_save_fields()
	{

		if( $_REQUEST['action']  == 'wsm_woosquare_save_fields') {

			$product_id	= $_REQUEST['product_id'];
			$product_modifier_group_name	= $_REQUEST['product_modifier_group_name'];
			$required_set =	$_REQUEST['required_set'];

			$this->wsm_woosquare_save_values($product_id,$product_modifier_group_name ,$required_set);

		}

		else {
			exit('The form is not valid');

		}

	}

	public function wsm_product_modifier_display( ) {
      if ( function_exists( 'wc_get_product' ) ) {
		$product = wc_get_product();
		if (!empty($product)) {
		$id = $product->get_id();
		$modifier_taxonomies  = WooSquare_Modifier::wsm_get_modifier();
		if ( ! empty( $modifier_taxonomies ) ) {
			$taxonomy_terms = array();

		//	print_r($id);
			$modifier_value = get_post_meta($id, 'product_modifier_group_name', true);
       
			if ($modifier_taxonomies) :
				foreach ($modifier_taxonomies as $key => $modifier) :
					$modifier_taxonomy_name = WooSquare_Modifier::wsm_modifier_set_name($modifier->modifier_slug."_".$modifier->modifier_id);
					$label = $modifier->modifier_set_name ? $modifier->modifier_set_name : $modifier->modifier_set_name;

					if(!empty($modifier_taxonomy_name) && !empty($modifier_value) && !empty($label) ){
					if (in_array($modifier_taxonomy_name, $modifier_value) || in_array($label, $modifier_value)) {
						$taxonomy_terms[$modifier->modifier_slug ."_". $modifier->modifier_id] = get_terms(WooSquare_Modifier::wsm_modifier_set_name($modifier->modifier_slug ."_". $modifier->modifier_id), 'orderby=name&hide_empty=0');

						$taxonomy_terms[$modifier->modifier_slug ."_". $modifier->modifier_id]["modifier_public"] = $modifier->modifier_public;

						$taxonomy_terms[$modifier->modifier_slug ."_". $modifier->modifier_id]["modifier_option"] = $modifier->modifier_option;
						
						$taxonomy_terms[$modifier->modifier_slug ."_". $modifier->modifier_id]["modifier_set_name_label"] = $modifier->modifier_set_name;
						
						foreach ($taxonomy_terms as $key => $modifier_public) {

						}
					}

					}

				endforeach;
			endif;

	
			        	
			if (is_array($taxonomy_terms) || is_object($taxonomy_terms)) {
				global  $woocommerce;
				$i = 0;
				foreach ($taxonomy_terms as  $key => $modsets) {
				   

					if (($modsets['modifier_public']) == USER_SELECT_MULTIPLE) {
						echo "<label> ".$modsets['modifier_set_name_label']."</label>";
						if (!empty($modsets)) {
							foreach ($modsets as $modset) {
								if (!empty($modset->term_id) && $modset->name) {
									$modifier_price = get_term_meta($modset->term_id, 'term_meta_price', true); ?>
									<div class="modifier-radio">
										<label>	<input
												type="checkbox"
												id="modifier_field_product"
												class="modifier_field_checkbox"
												name="modifier_field_product[]"
												value="<?php echo $modset->name ?>|<?php echo $modifier_price; ?>">
									 <?php echo $modset->name ?>  (+<?php echo get_woocommerce_currency_symbol(); ?><?php echo number_format((float)$modifier_price, 2, '.', '')  ?>) </label>
										
									</div>

									<?php
								}
							}
						}
					}
					elseif($modsets['modifier_public'] == USER_SELECT_ONE){
						echo "<label> ".$modsets['modifier_set_name_label']."</label>";
						if (!empty($modsets) && !empty($modsets['modifier_option'])) {

							if ($modsets['modifier_option'] == SELECT_FIELD) {
								?>
								<div class="modifier-select">
									<select class="modifier_field_product_one  modifier_field_select" id="modifier_field_product_one"
											name="modifier_field_product_one[]">
										<option value=""> Choose modifier</option>
										<?php foreach ($modsets as $modset) {

											if (!empty($modset->term_id) && $modset->name) {
												$modifier_price = get_term_meta($modset->term_id, 'term_meta_price', true);
												?>
												<option value="<?php echo $modset->name ?>|<?php echo $modifier_price ?> "> <?php echo $modset->name ?>
													- <?php echo get_woocommerce_currency_symbol(); ?><?php echo $modifier_price ?> </option>
												<?php
											}

										} ?>
									</select>
								</div>
							<?php } elseif($modsets['modifier_option'] == RADIO_FIELD){  ?>

								<div class="modifier-select">

									<?php foreach ($modsets as $modset) {

										if (!empty($modset->term_id) && $modset->name) {
											$modifier_price = get_term_meta($modset->term_id, 'term_meta_price', true);
											?>
											<div class="modifier-radio">
												<label>	<input
														type="radio"
														id="modifier_field_product_one"
														class="modifier_field_radio"
														name="modifier_field_product_one[]"
														value="<?php echo $modset->name ?>|<?php echo $modifier_price; ?>">
											 <?php echo $modset->name ?> 	(+<?php echo get_woocommerce_currency_symbol(); ?><?php echo number_format((float)$modifier_price, 2, '.', '')  ?>) </label>
											
											</div>
											<?php
										}

									} ?>

								</div>
							<?php	}
						}
					}
				}
			}
		 }
	    }
	  }
	}

}