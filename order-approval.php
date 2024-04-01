<?php
/**
 * Plugin Name: Opproval - Order Approval by Customer
 * Description: Deliver the order and let your customers mark the delivery as completed after receiving the product.
 * Version:     1.2.3
 * Author:      MS Web Arts
 * Author URI:  https://www.mswebarts.com/
 * License:     GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: opproval
 */
/**
 * @package Opproval
 */

// integrate Appsero
require __DIR__ . '/vendor/autoload.php';
/**
 * Initialize the plugin tracker
 *
 * @return void
 */
function appsero_init_tracker_order_approval_by_customer_for_woocommerce() {

    if ( ! class_exists( 'Appsero\Client' ) ) {
      require_once __DIR__ . '/appsero/src/Client.php';
    }

    $client = new Appsero\Client( '0d9a463a-7106-45e6-a63f-21c831f4e1b2', 'Opproval &#8211; Order Approval by Customer for WooCommerce', __FILE__ );

    // Active insights
    $client->insights()->init();

}
appsero_init_tracker_order_approval_by_customer_for_woocommerce();

// Check if woocommerce is installed.
add_action( 'plugins_loaded', 'msoa_check_for_woocommerce' );
function msoa_check_for_woocommerce() {
	if ( ! defined( 'WC_VERSION' ) ) {
		add_action( 'admin_notices', 'msoa_woocommerce_dependency_error' );
		return;
	}
}

function msoa_woocommerce_dependency_error() {
	$class   = 'notice notice-error';
	$message = __( 'You must need to install and activate woocommerce for Order Approval to work', 'opproval' );

	printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) );
}

// Load plugin textdomain.
add_action( 'init', 'msoa_load_textdomain' );
function msoa_load_textdomain() {
	load_plugin_textdomain( 'opproval', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}

add_action( 'wp_enqueue_scripts', 'msoa_register_styles' );
function msoa_register_styles() {
	wp_enqueue_style( 'msoa_style', plugins_url( 'style.css', __FILE__ ) );
}

/*
================================
	Add Delivered Order Status
==================================*/

add_action( 'init', 'msoa_register_delivered_order_status' );
function msoa_register_delivered_order_status() {
	register_post_status(
		'wc-delivered',
		array(
			'label'                     => 'Session Completed',
			'public'                    => true,
			'show_in_admin_status_list' => true,
			'show_in_admin_all_list'    => true,
			'exclude_from_search'       => false,
			// translators: %s: count of orders.
			'label_count'               => _n_noop( 'Delivered <span class="count">(%s)</span>', 'Delivered <span class="count">(%s)</span>' ),
		)
	);
}

add_filter( 'wc_order_statuses', 'msoa_add_delivered_status_to_order_statuses' );
/**
 * Add "Delivered" order status to WooCommerce.
 *
 * @param array $order_statuses Array of order statuses.
 */
function msoa_add_delivered_status_to_order_statuses( $order_statuses ) {

	$new_order_statuses = array();

	foreach ( $order_statuses as $key => $status ) {

		$new_order_statuses[ $key ] = $status;

		if ( 'wc-processing' === $key ) {
			$new_order_statuses['wc-delivered'] = __( 'Delivered', 'opproval' );
		}
	}

	return $new_order_statuses;
}

add_filter( 'woocommerce_my_account_my_orders_actions', 'msoa_mark_as_received', 10, 2 );
/**
 * Add "Mark as Received" button to my account order actions.
 */
function msoa_mark_as_received( $actions, $order ) {
	$order_id = $order->id;

	if ( ! is_object( $order ) ) {
		$order_id = absint( $order );
		$order    = wc_get_order( $order_id );
	}

	// check if order status delivered and form not submitted.

	if ( ( $order->has_status( 'delivered' ) ) && ( ! isset( $_POST['mark_as_received'] ) ) ) {
		$check_received = ( $order->has_status( 'delivered' ) ) ? 'true' : 'false';
		?>
		<div class="ms-mark-as-received">
			<form method="post">
				<input type="hidden" name="mark_as_received" value="<?php echo esc_attr( $check_received ); ?>">
				<input type="hidden" name="order_id" value="<?php echo esc_attr( $order_id ); ?>">
				<?php wp_nonce_field( 'so_38792085_nonce_action', '_so_38792085_nonce_field' ); ?> 
				<input class="int-button-small" type="submit" value="<?php echo esc_attr_e( 'Mark as Received', 'opproval' ); ?>" data-toggle="tooltip" title="<?php echo esc_attr_e( 'Click to mark the order as complete if you have received the product', 'opproval' ); ?>">
			</form>
		</div>
		<?php
	}

	/**
	* Refresh page if form submitted.
	* fix status not updating
	*/
	if ( isset( $_POST['mark_as_received'] ) ) {
		echo "<meta http-equiv='refresh' content='0'>";
	}

	// not a "mark as received" form submission.
	if ( ! isset( $_POST['mark_as_received'] ) ) {
		return $actions;
	}

	// basic security check.
	if ( isset( $_POST['_so_38792085_nonce_field'] ) ) {
		$nonce_field = sanitize_text_field( wp_unslash( $_POST['_so_38792085_nonce_field'] ) );
		if ( ! wp_verify_nonce( sanitize_text_field( $nonce_field ), 'so_38792085_nonce_action' ) ) {
			return $actions;
		}

		// make sure order id is submitted.
		if ( ! isset( $_POST['order_id'] ) ) {
			$order_id = intval( $_POST['order_id'] );
			$order    = wc_get_order( $order_id );
			$order->update_status( 'completed' );
			return $actions;
		}
		if ( isset( $_POST['mark_as_received'] ) === true ) {
			$order_id = intval( $_POST['order_id'] );
			$order    = wc_get_order( $order_id );
			$order->update_status( 'completed' );
		}

		$actions = array(
			'pay'    => array(
				'url'  => $order->get_checkout_payment_url(),
				'name' => __( 'Pay', 'woocommerce' ),
			),
			'view'   => array(
				'url'  => $order->get_view_order_url(),
				'name' => __( 'View', 'woocommerce' ),
			),
			'cancel' => array(
				'url'  => $order->get_cancel_order_url( wc_get_page_permalink( 'myaccount' ) ),
				'name' => __( 'Cancel', 'woocommerce' ),
			),
		);

		if ( ! $order->needs_payment() ) {
			unset( $actions['pay'] );
		}

		if ( ! in_array( $order->get_status(), apply_filters( 'woocommerce_valid_order_statuses_for_cancel', array( 'pending', 'failed' ), $order ), true ) ) {
			unset( $actions['cancel'] );
		}
	}

	return $actions;
}
