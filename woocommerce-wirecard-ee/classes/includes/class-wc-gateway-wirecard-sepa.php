<?php
/**
 * Shop System Plugins - Terms of Use
 *
 * The plugins offered are provided free of charge by Wirecard AG and are explicitly not part
 * of the Wirecard AG range of products and services.
 *
 * They have been tested and approved for full functionality in the standard configuration
 * (status on delivery) of the corresponding shop system. They are under General Public
 * License version 3 (GPLv3) and can be used, developed and passed on to third parties under
 * the same terms.
 *
 * However, Wirecard AG does not provide any guarantee or accept any liability for any errors
 * occurring when used in an enhanced, customized shop system configuration.
 *
 * Operation in an enhanced, customized configuration is at your own risk and requires a
 * comprehensive test phase by the user of the plugin.
 *
 * Customers use the plugins at their own risk. Wirecard AG does not guarantee their full
 * functionality neither does Wirecard AG assume liability for any disadvantages related to
 * the use of the plugins. Additionally, Wirecard AG does not guarantee the full functionality
 * for customized shop systems or installed plugins of other vendors of plugins within the same
 * shop system.
 *
 * Customers are responsible for testing the plugin's functionality before starting productive
 * operation.
 *
 * By installing the plugin into the shop system the customer agrees to these terms of use.
 * Please do not use the plugin if you do not agree to these terms of use!
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once( WOOCOMMERCE_GATEWAY_WIRECARD_BASEDIR . 'classes/includes/class-wc-wirecard-payment-gateway.php' );
require_once( WOOCOMMERCE_GATEWAY_WIRECARD_BASEDIR . 'classes/helper/class-additional-information.php' );

use Wirecard\PaymentSdk\Config\Config;
use Wirecard\PaymentSdk\Config\PaymentMethodConfig;
use Wirecard\PaymentSdk\Entity\AccountHolder;
use Wirecard\PaymentSdk\Entity\Amount;
use Wirecard\PaymentSdk\Entity\CustomField;
use Wirecard\PaymentSdk\Entity\CustomFieldCollection;
use Wirecard\PaymentSdk\Entity\Mandate;
use Wirecard\PaymentSdk\Entity\Redirect;
use Wirecard\PaymentSdk\Transaction\SepaTransaction;

/**
 * Class WC_Gateway_Wirecard_Sepa
 *
 * @extends WC_Wirecard_Payment_Gateway
 *
 * @since   1.0.0
 */
class WC_Gateway_Wirecard_Sepa extends WC_Wirecard_Payment_Gateway {

	private $type;

	private $additional_helper;

	public function __construct() {
		$this->type               = 'sepa';
		$this->id                 = 'woocommerce_wirecard_sepa';
		$this->icon               = WOOCOMMERCE_GATEWAY_WIRECARD_URL . 'assets/images/sepa.png';
		$this->method_title       = __( 'Wirecard Payment Processing Gateway SEPA', 'wooocommerce-gateway-wirecard' );
		$this->method_description = __( 'SEPA transactions via Wirecard Payment Processing Gateway', 'woocommerce-gateway-wirecard' );
		$this->has_fields         = true;

		$this->init_form_fields();
		$this->init_settings();

		$this->title   = $this->get_option( 'title' );
		$this->enabled = $this->get_option( 'enabled' );

		$this->additional_helper = new Additional_Information();

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_api_get_sepa_mandate', array( $this, 'get_sepa_mandate' ) );

		parent::add_payment_gateway_actions();
	}

	/**
	 * Load form fields for configuration
	 *
	 * @since 1.0.0
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'             => array(
				'title'   => __( 'Enable/Disable', 'woocommerce-gateway-wirecard' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable Wirecard Payment Processing Gateway SEPA', 'woocommerce-gateway-wirecard' ),
				'default' => 'yes',
			),
			'title'               => array(
				'title'       => __( 'Title', 'woocommerce-gateway-wirecard' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.',
				'woocommerce-gateway-wirecard' ),
				'default'     => __( 'Wirecard Payment Processing Gateway SEPA', 'woocommerce-gateway-wirecard' ),
				'desc_tip'    => true,
			),
			'merchant_account_id' => array(
				'title'   => __( 'Merchant Account ID', 'woocommerce-gateway-wirecard' ),
				'type'    => 'text',
				'default' => '4c901196-eff7-411e-82a3-5ef6b6860d64',
			),
			'secret'              => array(
				'title'   => __( 'Secret Key', 'woocommerce-gateway-wirecard' ),
				'type'    => 'text',
				'default' => 'ecdf5990-0372-47cd-a55d-037dccfe9d25',
			),
			'credentials'         => array(
				'title'       => __( 'Credentials', 'woocommerce-gateway-wirecard' ),
				'type'        => 'title',
				'description' => __( 'Enter your Wirecard Processing Payment Gateway credentials and test it.',
				'woocommerce-gateway-wirecard' ),
			),
			'base_url'            => array(
				'title'       => __( 'Base Url', 'woocommerce-gateway-wirecard' ),
				'type'        => 'text',
				'description' => __( 'The elastic engine base url. (e.g. https://api.wirecard.com)' ),
				'default'     => 'https://api-test.wirecard.com',
				'desc_tip'    => true,
			),
			'http_user'           => array(
				'title'   => __( 'Http User', 'woocommerce-gateway-wirecard' ),
				'type'    => 'text',
				'default' => '70000-APITEST-AP',
			),
			'http_pass'           => array(
				'title'   => __( 'Http Password', 'woocommerce-gateway-wirecard' ),
				'type'    => 'text',
				'default' => 'qD2wzQ_hrc!8',
			),
			'sepa_credentials'    => array(
				'title'       => __( 'SEPA credentials', 'woocommerce-gateway-wirecard' ),
				'type'        => 'title',
				'description' => __( 'Enter your SEPA credentials', 'woocommerce-gateway-wirecard' ),
			),
			'creditor_id'         => array(
				'title'   => __( 'Creditor ID', 'woocommerce-gateway-wirecard' ),
				'type'    => 'text',
				'default' => 'DE98ZZZ09999999999',
			),
			'creditor_name'       => array(
				'title'   => __( 'Creditor Name', 'woocommerce-gateway-wirecard' ),
				'type'    => 'text',
				'default' => '',
			),
			'creditor_city'       => array(
				'title'   => __( 'Creditor City', 'woocommerce-gateway-wirecard' ),
				'type'    => 'text',
				'default' => '',
			),
			'advanced'            => array(
				'title'       => __( 'Advanced options', 'woocommerce-gateway-wirecard' ),
				'type'        => 'title',
				'description' => '',
			),
			'payment_action'      => array(
				'title'   => __( 'Payment Action', 'woocommerce-gateway-wirecard' ),
				'type'    => 'select',
				'default' => 'Authorization',
				'label'   => __( 'Payment Action', 'woocommerce-gateway-wirecard' ),
				'options' => array(
					'reserve' => 'Authorization',
					'pay'     => 'Capture',
				),
			),
			'shopping_basket'     => array(
				'title'   => __( 'Enable/Disable', 'woocommerce-gateway-wirecard' ),
				'type'    => 'checkbox',
				'label'   => __( 'Shopping Basket', 'woocommerce-gateway-wirecard' ),
				'default' => 'no',
			),
			'descriptor'          => array(
				'title'   => __( 'Enable/Disable', 'woocommerce-gateway-wirecard' ),
				'type'    => 'checkbox',
				'label'   => __( 'Descriptor', 'woocommerce-gateway-wirecard' ),
				'default' => 'no',
			),
			'send_additional'     => array(
				'title'   => __( 'Enable/Disable', 'woocommerce-gateway-wirecard' ),
				'type'    => 'checkbox',
				'label'   => __( 'Send additional information', 'woocommerce-gateway-wirecard' ),
				'default' => 'yes',
			),
			'enable_bic'          => array(
				'title'   => __( 'Enable/Disable', 'woocommerce-gateway-wirecard' ),
				'type'    => 'checkbox',
				'label'   => __( 'BIC enabled', 'woocommerce-gateway-wirecard' ),
				'default' => 'no',
			),
		);
	}

	/**
	 * Add payment fields to payment method
	 *
	 * @since 1.0.0
	 */
	public function payment_fields() {
		$html = '
			<script type="application/javascript" src="' . WOOCOMMERCE_GATEWAY_WIRECARD_URL . '/assets/js/sepa.js"></script>
			<p class="form-row form-row-wide validate-required">
				<label for="sepa_firstname">' . __( 'Firstname', 'wooocommerce-gateway-wirecard' ) . '</label>
				<input id="sepa_firstname" class="input-text" type="text" name="sepa_firstname">
			</p>
			<p class="form-row form-row-wide validate-required">
				<label for="sepa_lastname">' . __( 'Lastname', 'wooocommerce-gateway-wirecard' ) . '</label>
				<input id="sepa_lastname" class="input-text" type="text" name="sepa_lastname">
			</p>
			<p class="form-row form-row-wide validate-required">
				<label for="sepa_iban">' . __( 'Iban', 'wooocommerce-gateway-wirecard' ) . '</label>
				<input id="sepa_iban" class="input-text" type="text" name="sepa_iban">
			</p>';

		if ( $this->get_option( 'enable_bic' ) == 'yes' ) {
			$html .= '			
			<p class="form-row form-row-wide validate-required">
				<label for="sepa_bic">' . __( 'Bic', 'wooocommerce-gateway-wirecard' ) . '</label>
				<input id="sepa_bic" class="input-text" type="text" name="sepa_bic">
			</p>';
		}

		echo $html;
	}

	/**
	 * Process payment gateway transactions
	 *
	 * @param int $order_id
	 *
	 * @return array
	 *
	 * @since 1.0.0
	 */
	public function process_payment( $order_id ) {
		if ( ! $_POST['sepa_firstname'] || ! $_POST['sepa_lastname'] || ! $_POST['sepa_iban']
			|| ( $this->get_option( 'enable_bic' ) == 'yes' && ! $_POST['sepa_bic'] ) ) {
			wc_add_notice( __( 'Please fill in the SEPA fields and try again.', 'woocommerce-gateway-wirecard' ), 'error' );
			return false;
		}

		$order = wc_get_order( $order_id );

		$redirect_urls = new Redirect(
			$this->create_redirect_url( $order, 'success', $this->type ),
			$this->create_redirect_url( $order, 'cancel', $this->type ),
			$this->create_redirect_url( $order, 'failure', $this->type )
		);

		$config    = $this->create_payment_config();
		$amount    = new Amount( $order->get_total(), 'EUR' );
		$operation = $this->get_option( 'payment_action' );

		$account_holder = new AccountHolder();
		$account_holder->setFirstName( $_POST['sepa_lastname'] );
		$account_holder->setLastName( $_POST['sepa_firstname'] );

		$transaction = new SepaTransaction();
		$transaction->setNotificationUrl( $this->create_notification_url( $order, $this->type ) );
		$transaction->setRedirect( $redirect_urls );
		$transaction->setAmount( $amount );
		$transaction->setAccountHolder( $account_holder );
		$transaction->setIban( $_POST['sepa_iban'] );

		$mandate = new Mandate( $this->generate_mandate_id( $order_id ) );
		$transaction->setMandate( $mandate );

		$custom_fields = new CustomFieldCollection();
		$custom_fields->add( new CustomField( 'orderId', $order_id ) );
		$transaction->setCustomFields( $custom_fields );

		if ( $this->get_option( 'shopping_basket' ) == 'yes' ) {
			$basket = $this->additional_helper->create_shopping_basket( $order, $transaction );
			$transaction->setBasket( $basket );
		}

		if ( $this->get_option( 'descriptor' ) == 'yes' ) {
			$transaction->setDescriptor( $this->additional_helper->create_descriptor( $order ) );
		}

		if ( $this->get_option( 'send_additional' ) == 'yes' ) {
			$this->additional_helper->set_additional_information( $order, $transaction );
		}

		return $this->execute_transaction( $transaction, $config, $operation, $order, $order_id );
	}

	/**
	 * Create payment method configuration
	 *
	 * @param null $base_url
	 * @param null $http_user
	 * @param null $http_pass
	 *
	 * @return Config
	 */
	public function create_payment_config( $base_url = null, $http_user = null, $http_pass = null ) {
		if ( is_null( $base_url ) ) {
			$base_url  = $this->get_option( 'base_url' );
			$http_user = $this->get_option( 'http_user' );
			$http_pass = $this->get_option( 'http_pass' );
		}

		$config         = parent::create_payment_config( $base_url, $http_user, $http_pass );
		$payment_config = new PaymentMethodConfig( SepaTransaction::NAME, $this->get_option( 'merchant_account_id' ), $this->get_option( 'secret' ) );
		$config->add( $payment_config );

		return $config;
	}

	/**
	 * @param $order_id
	 * @return string
	 */
	private function generate_mandate_id( $order_id ) {
		return $this->get_option( 'enable_bic' ) . '-' . $order_id . '-' . strtotime( date( 'Y-m-d H:i:s' ) );
	}
}
