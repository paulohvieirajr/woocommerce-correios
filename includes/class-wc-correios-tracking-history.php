<?php
/**
 * Correios Tracking History.
 *
 * @package WooCommerce_Correios/Classes/Tracking
 * @since   3.0.0
 * @version 3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Correios tracking history class.
 */
class WC_Correios_Tracking_History {

	/**
	 * Tracking API URL.
	 *
	 * @var string
	 */
	private $_api_url = 'http://websro.correios.com.br/sro_bin/sroii_xml.eventos';

	/**
	 * Initialize the order actions.
	 */
	public function __construct() {
		add_action( 'woocommerce_order_details_after_order_table', array( $this, 'view' ), 1 );
	}

	/**
	 * Get the tracing history API URL.
	 *
	 * @return string
	 */
	protected function get_tracking_history_api_url() {
		return apply_filters( 'woocommerce_correios_tracking_api_url', $this->_api_url );
	}

	/**
	 * Get user data.
	 *
	 * @return array
	 */
	protected function get_user_data() {
		$user_data = apply_filters( 'woocommerce_correios_tracking_user_data', array( 'login' => 'ECT', 'password' => 'SRO' ) );

		return $user_data;
	}

	/**
	 * Logger.
	 *
	 * @param string $data Data to log.
	 */
	protected function logger( $data ) {
		if ( apply_filters( 'woocommerce_correios_enable_tracking_debug', false ) ) {
			$logger = new WC_Logger();
			$logger->add( 'correios-tracking-history', $data );
		}
	}

	/**
	 * Access API Correios.
	 *
	 * @param  string $tracking_code Tracking code.
	 *
	 * @return SimpleXMLElement|stdClass History Tracking code.
	 */
	protected function get_tracking_history( $tracking_code ) {
		$user_data   = $this->get_user_data();
		$args        = apply_filters( 'woocommerce_correios_tracking_args', array(
			'Usuario'   => $user_data['login'],
			'Senha'     => $user_data['password'],
			'Tipo'      => 'L', /* L - List of objects | F - Object Range */
			'Resultado' => 'T', /* T - Returns all the object's events | U - Returns only last event object */
			'Objetos'   => $tracking_code,
		) );
		$api_url     = $this->get_tracking_history_api_url();
		$request_url = add_query_arg( $args, $api_url );
		$params      = array(
			'timeout' => 30,
		);

		$this->logger( 'Requesting tracking history in: ' . print_r( $request_url, true ) );

		$response = wp_safe_remote_get( $request_url, $params );

		if ( ! is_wp_error( $response ) && $response['response']['code'] >= 200 && $response['response']['code'] < 300 ) {
			try {
				$tracking_history = wc_correios_safe_load_xml( $response['body'], LIBXML_NOCDATA );
			} catch ( Exception $e ) {
				$this->logger( 'Tracking history invalid XML: ' . $e->getMessage() );
			}
		} else {
			$tracking_history = new stdClass();
			$tracking_history->error = true;
		}

		$this->logger( 'Tracking history response: ' . print_r( $tracking_history, true ) );

		return $tracking_history;
	}

	/**
	 * Display the order tracking code in order details and the tracking history.
	 *
	 * @param WC_Order $order Order data.
	 */
	public function view( $order ) {
		$events        = false;
		$tracking_code = get_post_meta( $order->id, 'correios_tracking', true );

		// Check if exist a tracking code for the order.
		if ( ! $tracking_code ) {
			return;
		}

		// Try to connect to Correios Webservices and get the tracking history.
		if ( apply_filters( 'woocommerce_correios_enable_tracking_history', false ) ) {
			$tracking = $this->get_tracking_history( $tracking_code );
			$events   = isset( $tracking->objeto->evento ) ? $tracking->objeto->evento : false;
		}

		// Display the right template for show the tracking code or tracking history.
		if ( $events ) {
			wc_get_template(
				'myaccount/tracking-history-table.php',
				array(
					'events' => $events,
					'code'   => $tracking_code,
				),
				'',
				WC_Correios::get_templates_path()
			);
		} else {
			wc_get_template(
				'myaccount/tracking-code.php',
				array(
					'code' => $tracking_code,
				),
				'',
				WC_Correios::get_templates_path()
			);
		}
	}
}

new WC_Correios_Tracking_History();
