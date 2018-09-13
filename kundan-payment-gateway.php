<?php

// Either place this code in functions.php or create your custom plugin with this code


function wc_pay_on_account_add_to_gateways( $gateways ) { // Add Payment On Account Payment gateway
	$current_user = wp_get_current_user();
	$user_roles=$current_user->roles;
	if ( in_array("wholesale_customer", $user_roles) || in_array("administrator", $user_roles)){ // Applicable only for user with role wholesale_customer or administrator
		$gateways[] = 'WC_Gateway_pay_on_account';
	}
	return $gateways;
}
add_filter( 'woocommerce_payment_gateways', 'wc_pay_on_account_add_to_gateways' );

add_action( 'plugins_loaded', 'wc_pay_on_account_gateway_init', 11 ); // define Payment On Account Payment gateway
function wc_pay_on_account_gateway_init() {
	$current_user = wp_get_current_user();
	$user_roles=$current_user->roles;
	if ( in_array("wholesale_customer", $user_roles) || in_array("administrator", $user_roles)){ // Applicable only for user with role wholesale_customer or administrator

		class WC_Gateway_pay_on_account extends WC_Payment_Gateway {
	
			/**
			 * Constructor for the gateway.
			 */
			
			public function __construct() {
				$current_user = wp_get_current_user();
				$user_roles=$current_user->roles;
			  
					$this->id                 = 'pay_on_account_gateway';
					$this->icon               = apply_filters('woocommerce_pay_on_account_icon', '');
					$this->has_fields         = false;
					$this->method_title       = __( 'Payment On Account', 'wc-gateway-pay_on_account' );
					$this->method_description = __( 'This payment option is applicable only for Wholesale Customers.', 'wc-gateway-pay_on_account' );
				  
					// Load the settings.
					$this->init_form_fields();
					$this->init_settings();
				  
					// Define user set variables
					$this->title        = $this->get_option( 'title' );
					$this->description  = $this->get_option( 'description' );
					$this->instructions = $this->get_option( 'instructions', $this->description );
				  
					// Actions
					add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
					add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );
				  
					// Customer Emails
					add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );
			}
		
			/**
			 * Initialize Gateway Settings Form Fields
			 */
			public function init_form_fields() {
				$current_user = wp_get_current_user();
				$user_roles=$current_user->roles;
		  
					$this->form_fields = apply_filters( 'wc_pay_on_account_form_fields', array(
				  
						'enabled' => array(
							'title'   => __( 'Enable/Disable', 'wc-gateway-pay_on_account' ),
							'type'    => 'checkbox',
							'label'   => __( 'Payment On Account', 'wc-gateway-pay_on_account' ),
							'default' => 'yes'
						),
						
						'title' => array(
							'title'       => __( 'Title', 'wc-gateway-pay_on_account' ),
							'type'        => 'text',
							'description' => __( 'This controls the title for the payment method the customer sees during checkout.', 'wc-gateway-pay_on_account' ),
							'default'     => __( 'Payment On Account', 'wc-gateway-pay_on_account' ),
							'desc_tip'    => true,
						),
						
						'description' => array(
							'title'       => __( 'Description', 'wc-gateway-pay_on_account' ),
							'type'        => 'textarea',
							'description' => __( 'Payment method description that the customer will see on your checkout.', 'wc-gateway-pay_on_account' ),
							'default'     => __( 'You need to pay this payment with your monthly bill.', 'wc-gateway-pay_on_account' ),
							'desc_tip'    => true,
						),
						
						'instructions' => array(
							'title'       => __( 'Instructions', 'wc-gateway-pay_on_account' ),
							'type'        => 'textarea',
							'description' => __( 'Instructions that will be added to the thank you page and emails.', 'wc-gateway-pay_on_account' ),
							'default'     => __( 'Just checkout with this option without payment. You need to pay it with your monthly bill.', 'wc-gateway-pay_on_account' ),
							'desc_tip'    => true,
						),
					) );
			}
		
		
			/**
			 * Output for the order received page.
			 */
			public function thankyou_page() {
				if ( $this->instructions ) {
					echo wpautop( wptexturize( $this->instructions ) );
				}
			}
		
		
			/**
			 * Add content to the WC emails.
			 *
			 * @access public
			 * @param WC_Order $order
			 * @param bool $sent_to_admin
			 * @param bool $plain_text
			 */
			public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {
			
				if ( $this->instructions && ! $sent_to_admin && $this->id === $order->payment_method && $order->has_status( 'pay_on_account' ) ) {
					echo wpautop( wptexturize( $this->instructions ) ) . PHP_EOL;
				}
			}
		
		
			/**
			 * Process the payment and return the result
			 *
			 * @param int $order_id
			 * @return array
			 */
			public function process_payment( $order_id ) {
		
				$order = wc_get_order( $order_id );
				
				// Mark as on-hold (we're awaiting the payment)
				$order->update_status( 'pay_on_account', __( 'Payment On Account', 'wc-gateway-pay_on_account' ) );
				
				// Reduce stock levels
				$order->reduce_order_stock();
				
				// Remove cart
				WC()->cart->empty_cart();
				
				// Return thankyou redirect
				return array(
					'result' 	=> 'success',
					'redirect'	=> $this->get_return_url( $order )
				);
			}
		
	  } // end \WC_Gateway_pay_on_account class
	}
}

function register_pay_on_account_order_status() { // Add Payment On Account order status
    register_post_status( 'wc-pay_on_account', array(
        'label'                     => 'Payment On Account',
        'public'                    => true,
        'exclude_from_search'       => false,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop( 'Payment On Account <span class="count">(%s)</span>', 'Payment On Account <span class="count">(%s)</span>' )
    ) );
}
add_action( 'init', 'register_pay_on_account_order_status' );
// Add to list of WC Order statuses
function add_awaiting_shipment_to_order_statuses( $order_statuses ) {
    $new_order_statuses = array();
    // add new order status after processing
    foreach ( $order_statuses as $key => $status ) {
        $new_order_statuses[ $key ] = $status;
        if ( 'wc-processing' === $key ) {
            $new_order_statuses['wc-pay_on_account'] = 'Payment On Account';
        }
    }
    return $new_order_statuses;
}
add_filter( 'wc_order_statuses', 'add_awaiting_shipment_to_order_statuses' );

?>