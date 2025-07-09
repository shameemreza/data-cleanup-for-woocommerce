<?php
/**
 * Plugin Name: Data Cleanup for WooCommerce
 * Plugin URI: https://github.com/shameemreza/data-cleanup-for-woocommerce
 * Description: Advanced tool for cleaning up WooCommerce data including users, customers, orders, and bookings with selective deletion options.
 * Version: 1.1.0
 * Author: Shameem Reza
 * Author URI: https://shameem.dev
 * Text Domain: data-cleanup-for-woocommerce
 * Domain Path: /languages
 * Requires at least: 6.0
 * Tested up to: 6.8.1
 * Requires PHP: 7.4
 * WC requires at least: 7.0
 * WC tested up to: 9.9.5
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// HPOS Compatibility Declaration
add_action( 'before_woocommerce_init', function() {
	if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
			'custom_order_tables',
			__FILE__,
			true
		);
	}
} );

// Define plugin constants
define( 'WC_DATA_CLEANUP_VERSION', '1.1.0' );
define( 'WC_DATA_CLEANUP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WC_DATA_CLEANUP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Main plugin class
 */
class WC_Data_Cleanup {

	/**
	 * Constructor
	 */
	public function __construct() {
		// Check if WooCommerce is active
		if ( ! $this->is_woocommerce_active() ) {
			add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
			return;
		}

		// Load plugin functionality
		$this->includes();
		$this->init_hooks();
	}

	/**
	 * Check if WooCommerce is active
	 *
	 * @return bool
	 */
	private function is_woocommerce_active() {
		return in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) || 
			( is_multisite() && array_key_exists( 'woocommerce/woocommerce.php', get_site_option( 'active_sitewide_plugins' ) ) );
	}

	/**
	 * Show admin notice if WooCommerce is not active
	 */
	public function woocommerce_missing_notice() {
		?>
		<div class="notice notice-error">
			<p><?php esc_html_e( 'Data Cleanup for WooCommerce requires WooCommerce to be installed and active.', 'data-cleanup-for-woocommerce' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Include required files
	 */
	private function includes() {
		// Admin
		require_once WC_DATA_CLEANUP_PLUGIN_DIR . 'includes/admin/class-wc-data-cleanup-admin.php';
		
		// Core functionality
		require_once WC_DATA_CLEANUP_PLUGIN_DIR . 'includes/class-wc-data-cleanup-users.php';
		require_once WC_DATA_CLEANUP_PLUGIN_DIR . 'includes/class-wc-data-cleanup-customers.php';
		require_once WC_DATA_CLEANUP_PLUGIN_DIR . 'includes/class-wc-data-cleanup-orders.php';
		
		// Always include the bookings class - we'll check for WooCommerce Bookings 
		// functionality at runtime instead of load time
		require_once WC_DATA_CLEANUP_PLUGIN_DIR . 'includes/class-wc-data-cleanup-bookings.php';
	}

	/**
	 * Check if WooCommerce Bookings is active
	 *
	 * @return bool
	 */
	private function is_bookings_active() {
		// Simply check if the post type exists, which is more reliable
		return post_type_exists( 'wc_booking' );
	}

	/**
	 * Initialize hooks
	 */
	private function init_hooks() {
		// Load text domain
		add_action( 'plugins_loaded', array( $this, 'load_plugin_textdomain' ) );
		
		// Register activation hook
		register_activation_hook( __FILE__, array( $this, 'activation' ) );
	}

	/**
	 * Load plugin text domain
	 */
	public function load_plugin_textdomain() {
		load_plugin_textdomain( 'data-cleanup-for-woocommerce', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}

	/**
	 * Plugin activation
	 */
	public function activation() {
		// Create necessary database tables or options
		update_option( 'wc_data_cleanup_version', WC_DATA_CLEANUP_VERSION );
	}
}

// Create plugin directory structure if it doesn't exist
if ( ! file_exists( plugin_dir_path( __FILE__ ) . 'includes/admin' ) ) {
	mkdir( plugin_dir_path( __FILE__ ) . 'includes/admin', 0755, true );
}

/**
 * Initialize the plugin
 */
function wc_data_cleanup_init() {
	global $wc_data_cleanup;
	$wc_data_cleanup = new WC_Data_Cleanup();
}
add_action( 'plugins_loaded', 'wc_data_cleanup_init' ); 