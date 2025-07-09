<?php
/**
 * WooCommerce Data Cleanup Admin
 *
 * @package WC_Data_Cleanup
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Data_Cleanup_Admin class
 */
class WC_Data_Cleanup_Admin {

	/**
	 * Check if WooCommerce Bookings is active
	 *
	 * @return boolean
	 */
	private function is_bookings_active() {
		// Simply check if the post type exists, which is more reliable
		return post_type_exists( 'wc_booking' );
	}

	/**
	 * Get bookings for listing in the admin UI
	 * 
	 * @param array $args Additional query arguments
	 * @return array List of bookings with details
	 */
	private function get_bookings_for_listing($args = array()) {
		global $wpdb;
		
		$default_args = array(
			'status' => '',
			'date_from' => '',
			'date_to' => '',
			'limit' => 50,
			'offset' => 0
		);
		
		$args = wp_parse_args($args, $default_args);
		
		// Start building the query
		$query = "SELECT p.ID, p.post_date, p.post_status, 
			COALESCE(pm_order.meta_value, 0) as order_id,
			COALESCE(pm_customer.meta_value, 0) as customer_id,
			COALESCE(pm_product.meta_value, 0) as product_id,
			COALESCE(pm_cost.meta_value, 0) as cost,
			COALESCE(pm_start.meta_value, '') as start_date,
			COALESCE(pm_end.meta_value, '') as end_date
			FROM {$wpdb->posts} p
			LEFT JOIN {$wpdb->postmeta} pm_order ON p.ID = pm_order.post_id AND pm_order.meta_key = '_booking_order_id'
			LEFT JOIN {$wpdb->postmeta} pm_customer ON p.ID = pm_customer.post_id AND pm_customer.meta_key = '_booking_customer_id'
			LEFT JOIN {$wpdb->postmeta} pm_product ON p.ID = pm_product.post_id AND pm_product.meta_key = '_booking_product_id'
			LEFT JOIN {$wpdb->postmeta} pm_cost ON p.ID = pm_cost.post_id AND pm_cost.meta_key = '_booking_cost'
			LEFT JOIN {$wpdb->postmeta} pm_start ON p.ID = pm_start.post_id AND pm_start.meta_key = '_booking_start'
			LEFT JOIN {$wpdb->postmeta} pm_end ON p.ID = pm_end.post_id AND pm_end.meta_key = '_booking_end'
			WHERE p.post_type = 'wc_booking'";
		
		// Add status filter if provided
		if (!empty($args['status'])) {
			$query .= $wpdb->prepare(" AND p.post_status = %s", $args['status']);
		}
		
		// Add date range filter if provided
		if (!empty($args['date_from']) && !empty($args['date_to'])) {
			$start_timestamp = strtotime($args['date_from'] . ' 00:00:00');
			$end_timestamp = strtotime($args['date_to'] . ' 23:59:59');
			
			if ($start_timestamp && $end_timestamp) {
				$start_date = date('YmdHis', $start_timestamp);
				$end_date = date('YmdHis', $end_timestamp);
				
				$query .= $wpdb->prepare(" AND pm_start.meta_value BETWEEN %s AND %s", $start_date, $end_date);
			}
		}
		
		// Execute the query with proper preparation
		$bookings = $wpdb->get_results($wpdb->prepare("SELECT p.ID, p.post_date, p.post_status, 
			COALESCE(pm_order.meta_value, 0) as order_id,
			COALESCE(pm_customer.meta_value, 0) as customer_id,
			COALESCE(pm_product.meta_value, 0) as product_id,
			COALESCE(pm_cost.meta_value, 0) as cost,
			COALESCE(pm_start.meta_value, '') as start_date
			FROM {$wpdb->posts} p
			LEFT JOIN {$wpdb->postmeta} pm_order ON p.ID = pm_order.post_id AND pm_order.meta_key = '_booking_order_id'
			LEFT JOIN {$wpdb->postmeta} pm_customer ON p.ID = pm_customer.post_id AND pm_customer.meta_key = '_booking_customer_id'
			LEFT JOIN {$wpdb->postmeta} pm_product ON p.ID = pm_product.post_id AND pm_product.meta_key = '_booking_product_id'
			LEFT JOIN {$wpdb->postmeta} pm_cost ON p.ID = pm_cost.post_id AND pm_cost.meta_key = '_booking_cost'
			LEFT JOIN {$wpdb->postmeta} pm_start ON p.ID = pm_start.post_id AND pm_start.meta_key = '_booking_start'
			WHERE p.post_type = 'wc_booking'
			ORDER BY p.post_date DESC
			LIMIT %d OFFSET %d", 
			$args['limit'], 
			$args['offset']
		));
		
		// Process the results
		$formatted_bookings = array();
		
		foreach ($bookings as $booking) {
			// Get product name
			$product_name = '';
			if ($booking->product_id) {
				$product_post = get_post($booking->product_id);
				$product_name = $product_post ? $product_post->post_title : __('(Unknown product)', 'data-cleanup-for-woocommerce');
			}
			
			// Get customer name
			$customer_name = '';
			if ($booking->customer_id) {
				$customer = get_userdata($booking->customer_id);
				$customer_name = $customer ? $customer->display_name : __('(Guest)', 'data-cleanup-for-woocommerce');
			} else {
				$customer_name = __('Guest', 'data-cleanup-for-woocommerce');
			}
			
			// Format dates
			$start_date_formatted = '';
			$end_date_formatted = '';
			
			if (!empty($booking->start_date)) {
				// Convert numeric timestamp to date
				if (is_numeric($booking->start_date)) {
					$date = new DateTime();
					$date->setTimestamp($booking->start_date);
					$start_date_formatted = $date->format('Y-m-d H:i:s');
				} else {
					// Try to parse as YmdHis format
					$date = DateTime::createFromFormat('YmdHis', $booking->start_date);
					if ($date) {
						$start_date_formatted = $date->format('Y-m-d H:i:s');
					} else {
						$start_date_formatted = $booking->start_date;
					}
				}
			}
			
			if (!empty($booking->end_date)) {
				// Convert numeric timestamp to date
				if (is_numeric($booking->end_date)) {
					$date = new DateTime();
					$date->setTimestamp($booking->end_date);
					$end_date_formatted = $date->format('Y-m-d H:i:s');
				} else {
					// Try to parse as YmdHis format
					$date = DateTime::createFromFormat('YmdHis', $booking->end_date);
					if ($date) {
						$end_date_formatted = $date->format('Y-m-d H:i:s');
					} else {
						$end_date_formatted = $booking->end_date;
					}
				}
			}
			
			// Format status
			$status_label = ucfirst(str_replace('-', ' ', $booking->post_status));
			
			// Format cost
			$cost_formatted = wc_price($booking->cost);
			
			$formatted_bookings[] = array(
				'id' => $booking->ID,
				'date' => $booking->post_date,
				'status' => $booking->post_status,
				'status_label' => $status_label,
				'order_id' => $booking->order_id,
				'customer_id' => $booking->customer_id,
				'customer_name' => $customer_name,
				'product_id' => $booking->product_id,
				'product_name' => $product_name,
				'cost' => $booking->cost,
				'cost_formatted' => $cost_formatted,
				'start_date' => $start_date_formatted,
				'end_date' => $end_date_formatted
			);
		}
		
		return $formatted_bookings;
	}

	/**
	 * Constructor
	 */
	public function __construct() {
		// Add menu items
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		
		// Register admin scripts and styles
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
		
		// Register AJAX handlers
		add_action( 'wp_ajax_wc_data_cleanup_delete_users', array( $this, 'ajax_delete_users' ) );
		add_action( 'wp_ajax_wc_data_cleanup_delete_customers', array( $this, 'ajax_delete_customers' ) );
		add_action( 'wp_ajax_wc_data_cleanup_delete_orders', array( $this, 'ajax_delete_orders' ) );
		add_action( 'wp_ajax_wc_data_cleanup_get_users', array( $this, 'ajax_get_users' ) );
		add_action( 'wp_ajax_wc_data_cleanup_get_customers', array( $this, 'ajax_get_customers' ) );
		add_action( 'wp_ajax_wc_data_cleanup_get_orders', array( $this, 'ajax_get_orders' ) );
		add_action( 'wp_ajax_wc_data_cleanup_get_order_statuses', array( $this, 'ajax_get_order_statuses' ) );
		
		// Add test action for debugging
		add_action( 'wp_ajax_wc_data_cleanup_test_bookings', array( $this, 'ajax_test_bookings' ) );
		
		// Register AJAX handlers for bookings
		add_action( 'wp_ajax_wc_data_cleanup_delete_bookings', array( $this, 'ajax_delete_bookings' ) );
		add_action( 'wp_ajax_wc_data_cleanup_get_bookings', array( $this, 'ajax_get_bookings' ) );
		add_action( 'wp_ajax_wc_data_cleanup_get_booking_statuses', array( $this, 'ajax_get_booking_statuses' ) );
		add_action( 'wp_ajax_wc_data_cleanup_get_bookings_preview', array( $this, 'ajax_get_bookings_preview' ) );
	}

	/**
	 * Add admin menu items
	 */
	public function add_admin_menu() {
		add_submenu_page(
			'woocommerce',
			__( 'Data Cleanup', 'data-cleanup-for-woocommerce' ),
			__( 'Data Cleanup', 'data-cleanup-for-woocommerce' ),
			'manage_woocommerce',
			'wc-data-cleanup',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Enqueue admin scripts and styles
	 *
	 * @param string $hook Current admin page hook
	 */
	public function enqueue_admin_scripts( $hook ) {
		if ( 'woocommerce_page_wc-data-cleanup' !== $hook ) {
			return;
		}

		// Enqueue Select2
		wp_enqueue_style( 'select2', WC()->plugin_url() . '/assets/css/select2.css', array(), WC_VERSION );
		wp_enqueue_script( 'select2', WC()->plugin_url() . '/assets/js/select2/select2.full.min.js', array( 'jquery' ), WC_VERSION, true );

		// Enqueue admin scripts
		wp_enqueue_script(
			'wc-data-cleanup-admin',
			WC_DATA_CLEANUP_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery', 'select2' ),
			WC_DATA_CLEANUP_VERSION,
			true
		);

		// Localize script
		wp_localize_script(
			'wc-data-cleanup-admin',
			'wc_data_cleanup_params',
			array(
				'ajax_url'                  => admin_url( 'admin-ajax.php' ),
				'nonce'                     => wp_create_nonce( 'wc-data-cleanup-nonce' ),
				'confirm_delete_all'        => __( 'Are you sure you want to delete all items? This action cannot be undone!', 'data-cleanup-for-woocommerce' ),
				'confirm_delete_selected'   => __( 'Are you sure you want to delete the selected items? This action cannot be undone!', 'data-cleanup-for-woocommerce' ),
				'confirm_delete_except'     => __( 'Are you sure you want to delete all items except the selected ones? This action cannot be undone!', 'data-cleanup-for-woocommerce' ),
				'error_no_selection'        => __( 'Please select at least one item.', 'data-cleanup-for-woocommerce' ),
				'processing'                => __( 'Processing...', 'data-cleanup-for-woocommerce' ),
				'success'                   => __( 'Operation completed successfully.', 'data-cleanup-for-woocommerce' ),
				'error'                     => __( 'An error occurred.', 'data-cleanup-for-woocommerce' ),
			)
		);

		// Enqueue admin styles
		wp_enqueue_style(
			'wc-data-cleanup-admin',
			WC_DATA_CLEANUP_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			WC_DATA_CLEANUP_VERSION
		);
	}

	/**
	 * Render admin page
	 */
	public function render_admin_page() {
		// Get current tab
		$current_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'users';
		?>
		<div class="wrap wc-data-cleanup">
			<h1><?php esc_html_e( 'Data Cleanup for WooCommerce', 'data-cleanup-for-woocommerce' ); ?></h1>
			
			<nav class="nav-tab-wrapper woo-nav-tab-wrapper">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-data-cleanup&tab=users' ) ); ?>" class="nav-tab <?php echo $current_tab === 'users' ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Users', 'data-cleanup-for-woocommerce' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-data-cleanup&tab=customers' ) ); ?>" class="nav-tab <?php echo $current_tab === 'customers' ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Customers', 'data-cleanup-for-woocommerce' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-data-cleanup&tab=orders' ) ); ?>" class="nav-tab <?php echo $current_tab === 'orders' ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Orders', 'data-cleanup-for-woocommerce' ); ?>
				</a>
				<?php if ( $this->is_bookings_active() ) : ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-data-cleanup&tab=bookings' ) ); ?>" class="nav-tab <?php echo $current_tab === 'bookings' ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Bookings', 'data-cleanup-for-woocommerce' ); ?>
				</a>
				<?php endif; ?>
			</nav>
			
			<div class="wc-data-cleanup-content">
				<?php
				// Display tab content
				switch ( $current_tab ) {
					case 'customers':
						$this->render_customers_tab();
						break;
									case 'orders':
					$this->render_orders_tab();
					break;
				case 'bookings':
					if ( $this->is_bookings_active() ) {
						$this->render_bookings_tab();
					} else {
						$this->render_users_tab();
					}
					break;
				case 'users':
				default:
					$this->render_users_tab();
					break;
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render users tab
	 */
	public function render_users_tab() {
		?>
		<div class="wc-data-cleanup-tab-content">
			<h2><?php esc_html_e( 'WordPress Users Cleanup', 'data-cleanup-for-woocommerce' ); ?></h2>
			
			<div class="wc-data-cleanup-warning">
				<p><strong><?php esc_html_e( 'Warning: Deleting users is a permanent action and cannot be undone. Please backup your database before proceeding.', 'data-cleanup-for-woocommerce' ); ?></strong></p>
			</div>
			
			<div class="wc-data-cleanup-selection">
				<h3><?php esc_html_e( 'Select Users', 'data-cleanup-for-woocommerce' ); ?></h3>
				<p class="description"><?php esc_html_e( 'Type to search for users or leave empty to see recent users. Select one or more users before using the "Delete Selected" or "Delete All Except Selected" options.', 'data-cleanup-for-woocommerce' ); ?></p>
				<select id="wc-data-cleanup-user-select" class="wc-data-cleanup-select" multiple="multiple" style="width: 100%;" data-placeholder="<?php esc_attr_e( 'Search for users...', 'data-cleanup-for-woocommerce' ); ?>"></select>
				
							<div class="wc-data-cleanup-legend">
				<div class="wc-data-cleanup-legend-item">
					<span class="wc-data-cleanup-has-data wc-data-cleanup-has-orders"></span> <?php esc_html_e( 'Has orders', 'data-cleanup-for-woocommerce' ); ?>
				</div>
				<div class="wc-data-cleanup-legend-item">
					<span class="wc-data-cleanup-has-data wc-data-cleanup-has-posts"></span> <?php esc_html_e( 'Has posts', 'data-cleanup-for-woocommerce' ); ?>
				</div>
				<div class="wc-data-cleanup-legend-item">
					<span class="wc-data-cleanup-has-data wc-data-cleanup-has-comments"></span> <?php esc_html_e( 'Has comments', 'data-cleanup-for-woocommerce' ); ?>
				</div>
				<div class="wc-data-cleanup-legend-item wc-data-cleanup-legend-note">
					<?php esc_html_e( '(Indicators appear when a user is selected)', 'data-cleanup-for-woocommerce' ); ?>
				</div>
			</div>
			</div>
			
			<div class="wc-data-cleanup-actions">
				<h3><?php esc_html_e( 'Bulk Actions', 'data-cleanup-for-woocommerce' ); ?></h3>
				
				<div class="wc-data-cleanup-action-group">
					<button type="button" class="button button-primary wc-data-cleanup-delete-all-users">
						<?php esc_html_e( 'Delete All Users with Customer Role', 'data-cleanup-for-woocommerce' ); ?>
					</button>
					<span class="wc-data-cleanup-help"><?php esc_html_e( 'Deletes all WordPress users with the "Customer" role.', 'data-cleanup-for-woocommerce' ); ?></span>
				</div>
				
				<div class="wc-data-cleanup-action-group">
					<button type="button" class="button wc-data-cleanup-delete-selected-users">
						<?php esc_html_e( 'Delete Selected Users', 'data-cleanup-for-woocommerce' ); ?>
					</button>
					<span class="wc-data-cleanup-help"><?php esc_html_e( 'Deletes only the selected users from the search box above.', 'data-cleanup-for-woocommerce' ); ?></span>
				</div>
				
				<div class="wc-data-cleanup-action-group">
					<button type="button" class="button wc-data-cleanup-delete-except-users">
						<?php esc_html_e( 'Delete All Except Selected', 'data-cleanup-for-woocommerce' ); ?>
					</button>
					<span class="wc-data-cleanup-help"><?php esc_html_e( 'Deletes all users except the ones selected in the search box above.', 'data-cleanup-for-woocommerce' ); ?></span>
				</div>
			</div>
			
			<div class="wc-data-cleanup-results">
				<div class="wc-data-cleanup-spinner spinner"></div>
				<div class="wc-data-cleanup-message"></div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render customers tab
	 */
	public function render_customers_tab() {
		?>
		<div class="wc-data-cleanup-tab-content">
			<h2><?php esc_html_e( 'WooCommerce Customers Cleanup', 'data-cleanup-for-woocommerce' ); ?></h2>
			
			<div class="wc-data-cleanup-warning">
				<p><strong><?php esc_html_e( 'Warning: Deleting customers is a permanent action and cannot be undone. Please backup your database before proceeding.', 'data-cleanup-for-woocommerce' ); ?></strong></p>
			</div>
			
			<div class="wc-data-cleanup-selection">
				<h3><?php esc_html_e( 'Select Customers', 'data-cleanup-for-woocommerce' ); ?></h3>
				<p class="description"><?php esc_html_e( 'Type to search for customers or leave empty to see recent customers. Select one or more customers before using the "Delete Selected" or "Delete All Except Selected" options.', 'data-cleanup-for-woocommerce' ); ?></p>
				<select id="wc-data-cleanup-customer-select" class="wc-data-cleanup-select" multiple="multiple" style="width: 100%;" data-placeholder="<?php esc_attr_e( 'Search for customers...', 'data-cleanup-for-woocommerce' ); ?>"></select>
				
				<div class="wc-data-cleanup-legend">
					<div class="wc-data-cleanup-legend-item">
						<span class="wc-data-cleanup-has-data wc-data-cleanup-has-orders"></span> <?php esc_html_e( 'Has orders', 'data-cleanup-for-woocommerce' ); ?>
					</div>
					<div class="wc-data-cleanup-legend-item wc-data-cleanup-legend-note">
						<?php esc_html_e( '(Indicator appears when a customer is selected)', 'data-cleanup-for-woocommerce' ); ?>
					</div>
				</div>
			</div>
			
			<div class="wc-data-cleanup-actions">
				<h3><?php esc_html_e( 'Bulk Actions', 'data-cleanup-for-woocommerce' ); ?></h3>
				
				<div class="wc-data-cleanup-action-group">
					<button type="button" class="button button-primary wc-data-cleanup-delete-all-customers">
						<?php esc_html_e( 'Delete All Customers', 'data-cleanup-for-woocommerce' ); ?>
					</button>
					<span class="wc-data-cleanup-help"><?php esc_html_e( 'Deletes all WooCommerce customers.', 'data-cleanup-for-woocommerce' ); ?></span>
				</div>
				
				<div class="wc-data-cleanup-action-group">
					<button type="button" class="button wc-data-cleanup-delete-selected-customers">
						<?php esc_html_e( 'Delete Selected Customers', 'data-cleanup-for-woocommerce' ); ?>
					</button>
					<span class="wc-data-cleanup-help"><?php esc_html_e( 'Deletes only the selected customers from the search box above.', 'data-cleanup-for-woocommerce' ); ?></span>
				</div>
				
				<div class="wc-data-cleanup-action-group">
					<button type="button" class="button wc-data-cleanup-delete-except-customers">
						<?php esc_html_e( 'Delete All Except Selected', 'data-cleanup-for-woocommerce' ); ?>
					</button>
					<span class="wc-data-cleanup-help"><?php esc_html_e( 'Deletes all customers except the ones selected in the search box above.', 'data-cleanup-for-woocommerce' ); ?></span>
				</div>
			</div>
			
			<div class="wc-data-cleanup-results">
				<div class="wc-data-cleanup-spinner spinner"></div>
				<div class="wc-data-cleanup-message"></div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render orders tab
	 */
	public function render_orders_tab() {
		// Get order statuses
		$orders_handler = new WC_Data_Cleanup_Orders();
		$order_statuses = $orders_handler->get_order_statuses();
		?>
		<div class="wc-data-cleanup-tab-content">
			<h2><?php esc_html_e( 'WooCommerce Orders Cleanup', 'data-cleanup-for-woocommerce' ); ?></h2>
			
			<div class="wc-data-cleanup-warning">
				<p><strong><?php esc_html_e( 'Warning: Deleting orders is a permanent action and cannot be undone. Please backup your database before proceeding.', 'data-cleanup-for-woocommerce' ); ?></strong></p>
			</div>
			
			<div class="wc-data-cleanup-selection">
				<h3><?php esc_html_e( 'Select Orders', 'data-cleanup-for-woocommerce' ); ?></h3>
				<p class="description"><?php esc_html_e( 'Type to search for orders by ID or customer name, or leave empty to see recent orders. Select one or more orders before using the "Delete Selected" or "Delete All Except Selected" options.', 'data-cleanup-for-woocommerce' ); ?></p>
				<select id="wc-data-cleanup-order-select" class="wc-data-cleanup-select" multiple="multiple" style="width: 100%;" data-placeholder="<?php esc_attr_e( 'Search for orders...', 'data-cleanup-for-woocommerce' ); ?>"></select>
			</div>
			
			<div class="wc-data-cleanup-actions">
				<h3><?php esc_html_e( 'Bulk Actions', 'data-cleanup-for-woocommerce' ); ?></h3>
				
				<div class="wc-data-cleanup-action-group">
					<button type="button" class="button button-primary wc-data-cleanup-delete-all-orders">
						<?php esc_html_e( 'Delete All Orders', 'data-cleanup-for-woocommerce' ); ?>
					</button>
					<span class="wc-data-cleanup-help"><?php esc_html_e( 'Deletes all WooCommerce orders.', 'data-cleanup-for-woocommerce' ); ?></span>
				</div>
				
				<div class="wc-data-cleanup-action-group">
					<button type="button" class="button wc-data-cleanup-delete-selected-orders">
						<?php esc_html_e( 'Delete Selected Orders', 'data-cleanup-for-woocommerce' ); ?>
					</button>
					<span class="wc-data-cleanup-help"><?php esc_html_e( 'Deletes only the selected orders from the search box above.', 'data-cleanup-for-woocommerce' ); ?></span>
				</div>
				
				<div class="wc-data-cleanup-action-group">
					<button type="button" class="button wc-data-cleanup-delete-except-orders">
						<?php esc_html_e( 'Delete All Except Selected', 'data-cleanup-for-woocommerce' ); ?>
					</button>
					<span class="wc-data-cleanup-help"><?php esc_html_e( 'Deletes all orders except the ones selected in the search box above.', 'data-cleanup-for-woocommerce' ); ?></span>
				</div>
				
				<div class="wc-data-cleanup-action-group">
					<h4><?php esc_html_e( 'Delete by Status', 'data-cleanup-for-woocommerce' ); ?></h4>
					<select id="wc-data-cleanup-order-status" class="wc-data-cleanup-select-status">
						<option value=""><?php esc_html_e( 'Select status...', 'data-cleanup-for-woocommerce' ); ?></option>
						<?php foreach ( $order_statuses as $status ) : ?>
							<option value="<?php echo esc_attr( $status['id'] ); ?>">
								<?php echo esc_html( $status['text'] ); ?> (<?php echo esc_html( $status['count'] ); ?>)
							</option>
						<?php endforeach; ?>
					</select>
					<button type="button" class="button wc-data-cleanup-delete-by-status">
						<?php esc_html_e( 'Delete Orders with Selected Status', 'data-cleanup-for-woocommerce' ); ?>
					</button>
				</div>
				
				<div class="wc-data-cleanup-action-group">
					<h4><?php esc_html_e( 'Delete by Date Range', 'data-cleanup-for-woocommerce' ); ?></h4>
					<label for="wc-data-cleanup-date-from"><?php esc_html_e( 'From:', 'data-cleanup-for-woocommerce' ); ?></label>
					<input type="date" id="wc-data-cleanup-date-from" class="wc-data-cleanup-date" />
					<span class="dashicons dashicons-calendar-alt"></span>
					
					<label for="wc-data-cleanup-date-to"><?php esc_html_e( 'To:', 'data-cleanup-for-woocommerce' ); ?></label>
					<input type="date" id="wc-data-cleanup-date-to" class="wc-data-cleanup-date" />
					<span class="dashicons dashicons-calendar-alt"></span>
					
					<button type="button" class="button wc-data-cleanup-delete-by-date-range">
						<?php esc_html_e( 'Delete Orders in Date Range', 'data-cleanup-for-woocommerce' ); ?>
					</button>
				</div>
			</div>
			
			<div class="wc-data-cleanup-results">
				<div class="wc-data-cleanup-spinner spinner"></div>
				<div class="wc-data-cleanup-message"></div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render bookings tab
	 */
	public function render_bookings_tab() {
		// Instead of using complex booking objects, let's use a direct database approach
		global $wpdb;
		
		// Get counts of bookings by status
		$booking_counts = $wpdb->get_results("
			SELECT post_status, COUNT(*) as count 
			FROM {$wpdb->posts} 
			WHERE post_type = 'wc_booking' 
			GROUP BY post_status
		");
		
		// Calculate total bookings
		$total_bookings = 0;
		$status_options = array();
		
		if (!empty($booking_counts)) {
			foreach ($booking_counts as $status) {
				$total_bookings += $status->count;
				$status_label = ucfirst(str_replace('-', ' ', $status->post_status));
				$status_options[] = array(
					'id' => $status->post_status,
					'text' => $status_label,
					'count' => $status->count
				);
			}
		}
		?>
		<div class="wc-data-cleanup-tab-content">
			<h2><?php esc_html_e( 'WooCommerce Bookings Cleanup', 'data-cleanup-for-woocommerce' ); ?></h2>
			
			<div class="wc-data-cleanup-warning">
				<p><strong><?php esc_html_e( 'Warning: Deleting bookings is a permanent action and cannot be undone. Please backup your database before proceeding.', 'data-cleanup-for-woocommerce' ); ?></strong></p>
			</div>
			
			<div class="wc-data-cleanup-status-summary">
				<h3><?php esc_html_e( 'Booking Status Summary', 'data-cleanup-for-woocommerce' ); ?></h3>
				<p>
					<?php
					// translators: %d is the total number of bookings.
					printf( esc_html__( 'Total Bookings: %d', 'data-cleanup-for-woocommerce' ), absint( $total_bookings ) );
					?>
				</p>
				
				<?php if (!empty($booking_counts)): ?>
					<table class="wp-list-table widefat fixed striped bookings-status-summary">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Status', 'data-cleanup-for-woocommerce' ); ?></th>
								<th><?php esc_html_e( 'Count', 'data-cleanup-for-woocommerce' ); ?></th>
								<th><?php esc_html_e( 'Action', 'data-cleanup-for-woocommerce' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php
							// Get booking statuses
							$bookings_handler = new WC_Data_Cleanup_Bookings();
							$statuses = $bookings_handler->get_booking_statuses();
							
							if ( empty( $statuses ) ) {
								echo '<tr><td colspan="3">' . esc_html__( 'No bookings found.', 'data-cleanup-for-woocommerce' ) . '</td></tr>';
							} else {
								foreach ( $statuses as $status ) {
									?>
									<tr>
										<td><?php echo esc_html( $status['text'] ); ?></td>
										<td><?php echo esc_html( $status['count'] ); ?></td>
										<td>
											<button type="button" class="button wc-data-cleanup-preview-bookings-by-status" data-status="<?php echo esc_attr( $status['id'] ); ?>">
												<?php esc_html_e( 'List Bookings', 'data-cleanup-for-woocommerce' ); ?>
											</button>
										</td>
									</tr>
									<?php
								}
							}
							?>
						</tbody>
					</table>
				<?php else: ?>
					<p><?php esc_html_e( 'No bookings found.', 'data-cleanup-for-woocommerce' ); ?></p>
				<?php endif; ?>
				
				<!-- Bookings Preview Section -->
				<div class="wc-data-cleanup-bookings-preview">
					<div class="wc-data-cleanup-preview-header">
						<h4 class="wc-data-cleanup-preview-title"><?php esc_html_e( 'Bookings List', 'data-cleanup-for-woocommerce' ); ?> <span class="wc-data-cleanup-preview-badge">0</span></h4>
						<div class="wc-data-cleanup-preview-controls">
							<div class="wc-data-cleanup-checkbox-controls">
								<label><input type="checkbox" id="booking-select-all"> <?php esc_html_e( 'Select All', 'data-cleanup-for-woocommerce' ); ?></label>
								<span class="selection-count">0</span>
							</div>
							<button type="button" class="button button-small wc-data-cleanup-close-preview"><?php esc_html_e( 'Close', 'data-cleanup-for-woocommerce' ); ?></button>
						</div>
					</div>
					
					<div class="wc-data-cleanup-bookings-list">
						<div class="wc-data-cleanup-loading">
							<span class="spinner is-active"></span>
							<p><?php esc_html_e( 'Loading bookings...', 'data-cleanup-for-woocommerce' ); ?></p>
						</div>
						<div class="wc-data-cleanup-no-bookings" style="display:none;">
							<p><?php esc_html_e( 'No bookings found matching your criteria.', 'data-cleanup-for-woocommerce' ); ?></p>
						</div>
						<table class="wc-data-cleanup-bookings-table widefat striped" style="display:none;">
							<thead>
								<tr>
									<th width="30"><input type="checkbox" id="booking-select-all"></th>
									<th width="60"><?php esc_html_e( 'ID', 'data-cleanup-for-woocommerce' ); ?></th>
									<th width="130"><?php esc_html_e( 'Date', 'data-cleanup-for-woocommerce' ); ?></th>
									<th width="130"><?php esc_html_e( 'Status', 'data-cleanup-for-woocommerce' ); ?></th>
									<th><?php esc_html_e( 'Product', 'data-cleanup-for-woocommerce' ); ?></th>
									<th width="150"><?php esc_html_e( 'Customer', 'data-cleanup-for-woocommerce' ); ?></th>
									<th width="100"><?php esc_html_e( 'Cost', 'data-cleanup-for-woocommerce' ); ?></th>
									<th width="150"><?php esc_html_e( 'Start Date', 'data-cleanup-for-woocommerce' ); ?></th>
								</tr>
							</thead>
							<tbody>
							</tbody>
						</table>
					</div>
					<div class="wc-data-cleanup-bookings-pagination"></div>
					<div class="wc-data-cleanup-action-buttons">
						<button type="button" class="button button-primary wc-data-cleanup-delete-selected-bookings" disabled><?php esc_html_e( 'Delete Selected Bookings', 'data-cleanup-for-woocommerce' ); ?></button>
					</div>
				</div>
			</div>
			
			<div class="wc-data-cleanup-actions">
				<h3><?php esc_html_e( 'Bulk Actions', 'data-cleanup-for-woocommerce' ); ?></h3>
				
				<div class="wc-data-cleanup-action-group">
					<button type="button" class="button button-primary wc-data-cleanup-delete-all-bookings" <?php echo $total_bookings > 0 ? '' : 'disabled'; ?>>
						<?php esc_html_e( 'Delete All Bookings', 'data-cleanup-for-woocommerce' ); ?>
					</button>
					<span class="wc-data-cleanup-help"><?php esc_html_e( 'Deletes all WooCommerce bookings.', 'data-cleanup-for-woocommerce' ); ?></span>
				</div>
				
				<div class="wc-data-cleanup-action-group">
					<h4><?php esc_html_e( 'Delete by Date Range', 'data-cleanup-for-woocommerce' ); ?></h4>
					<div class="date-range-selector">
						<label for="wc-data-cleanup-booking-date-from"><?php esc_html_e( 'From:', 'data-cleanup-for-woocommerce' ); ?></label>
						<input type="date" id="wc-data-cleanup-booking-date-from" class="wc-data-cleanup-date" />
						<span class="dashicons dashicons-calendar-alt"></span>
						
						<label for="wc-data-cleanup-booking-date-to"><?php esc_html_e( 'To:', 'data-cleanup-for-woocommerce' ); ?></label>
						<input type="date" id="wc-data-cleanup-booking-date-to" class="wc-data-cleanup-date" />
						<span class="dashicons dashicons-calendar-alt"></span>
						
						<button type="button" class="button wc-data-cleanup-preview-bookings-by-date-range">
							<?php esc_html_e( 'Show Bookings', 'data-cleanup-for-woocommerce' ); ?>
						</button>
						
						<button type="button" class="button wc-data-cleanup-delete-bookings-by-date-range">
							<?php esc_html_e( 'Delete Bookings in Date Range', 'data-cleanup-for-woocommerce' ); ?>
						</button>
					</div>
					
					<!-- Date range preview section -->
					<div class="wc-data-cleanup-date-bookings-preview" style="display:none;">
						<div class="wc-data-cleanup-preview-header">
							<h4 class="wc-data-cleanup-date-preview-title"><?php esc_html_e( 'Bookings in Selected Date Range', 'data-cleanup-for-woocommerce' ); ?> <span class="wc-data-cleanup-date-preview-badge">0</span></h4>
							<div class="wc-data-cleanup-preview-controls">
								<div class="wc-data-cleanup-checkbox-controls">
									<label><input type="checkbox" id="date-booking-select-all"> <?php esc_html_e( 'Select All', 'data-cleanup-for-woocommerce' ); ?></label>
									<span class="date-selection-count">0</span>
								</div>
								<button type="button" class="button button-small wc-data-cleanup-close-date-preview"><?php esc_html_e( 'Close', 'data-cleanup-for-woocommerce' ); ?></button>
							</div>
						</div>
						
						<div class="wc-data-cleanup-date-bookings-list">
							<div class="wc-data-cleanup-loading">
								<span class="spinner is-active"></span>
								<p><?php esc_html_e( 'Loading bookings...', 'data-cleanup-for-woocommerce' ); ?></p>
							</div>
							<div class="wc-data-cleanup-no-bookings" style="display:none;">
								<p><?php esc_html_e( 'No bookings found in this date range.', 'data-cleanup-for-woocommerce' ); ?></p>
							</div>
							<table class="wc-data-cleanup-date-bookings-table widefat striped" style="display:none;">
								<thead>
									<tr>
										<th width="30"><input type="checkbox" id="date-range-select-all"></th>
										<th width="60"><?php esc_html_e( 'ID', 'data-cleanup-for-woocommerce' ); ?></th>
										<th width="130"><?php esc_html_e( 'Date', 'data-cleanup-for-woocommerce' ); ?></th>
										<th width="130"><?php esc_html_e( 'Status', 'data-cleanup-for-woocommerce' ); ?></th>
										<th><?php esc_html_e( 'Product', 'data-cleanup-for-woocommerce' ); ?></th>
										<th width="150"><?php esc_html_e( 'Customer', 'data-cleanup-for-woocommerce' ); ?></th>
										<th width="100"><?php esc_html_e( 'Cost', 'data-cleanup-for-woocommerce' ); ?></th>
										<th width="150"><?php esc_html_e( 'Start Date', 'data-cleanup-for-woocommerce' ); ?></th>
									</tr>
								</thead>
								<tbody>
								</tbody>
							</table>
						</div>
						<div class="wc-data-cleanup-date-bookings-pagination"></div>
						<div class="wc-data-cleanup-date-action-buttons">
							<button type="button" class="button button-primary wc-data-cleanup-delete-selected-date-bookings" disabled><?php esc_html_e( 'Delete Selected Bookings', 'data-cleanup-for-woocommerce' ); ?></button>
						</div>
					</div>
				</div>
				
				<div class="wc-data-cleanup-action-group">
					<h4><?php esc_html_e( 'Related Orders', 'data-cleanup-for-woocommerce' ); ?></h4>
					<label>
						<input type="checkbox" id="wc-data-cleanup-booking-delete-orders" />
						<?php esc_html_e( 'Also delete related orders when deleting bookings', 'data-cleanup-for-woocommerce' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'Note: An order will only be deleted if all its associated bookings are being deleted.', 'data-cleanup-for-woocommerce' ); ?></p>
				</div>
			</div>
			
			<div class="wc-data-cleanup-results">
				<div class="wc-data-cleanup-spinner spinner"></div>
				<div class="wc-data-cleanup-message"></div>
			</div>
		</div>
		
		<!-- Custom Confirmation Modal -->
		<div class="wc-data-cleanup-confirm-modal" style="display: none;">
			<div class="wc-data-cleanup-confirm-modal-content">
				<div class="wc-data-cleanup-confirm-modal-header">
					<h3 class="wc-data-cleanup-confirm-modal-title">Confirm Action</h3>
					<span class="wc-data-cleanup-confirm-modal-close">&times;</span>
				</div>
				<div class="wc-data-cleanup-confirm-modal-body">
					<p class="wc-data-cleanup-confirm-modal-message"></p>
				</div>
				<div class="wc-data-cleanup-confirm-modal-footer">
					<button type="button" class="button wc-data-cleanup-confirm-modal-cancel">Cancel</button>
					<button type="button" class="button button-primary wc-data-cleanup-confirm-modal-proceed">Proceed</button>
				</div>
			</div>
		</div>
		
		<?php
	}

	/**
	 * AJAX handler for deleting users
	 */
	public function ajax_delete_users() {
		// Check nonce
		if ( ! check_ajax_referer( 'wc-data-cleanup-nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'data-cleanup-for-woocommerce' ) ) );
		}

		// Check permissions
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'data-cleanup-for-woocommerce' ) ) );
		}

		$action = isset( $_POST['action_type'] ) ? sanitize_text_field( wp_unslash( $_POST['action_type'] ) ) : '';
		$user_ids = isset( $_POST['user_ids'] ) ? array_map( 'absint', wp_unslash( $_POST['user_ids'] ) ) : array();
		
		// Get additional options
		$options = isset( $_POST['options'] ) ? wp_unslash( $_POST['options'] ) : array();
		$options = is_array( $options ) ? $options : array();
		
		// Sanitize options
		$sanitized_options = array(
			'force_delete'   => isset( $options['force_delete'] ) ? (bool) $options['force_delete'] : false,
			'delete_orders'  => isset( $options['delete_orders'] ) ? (bool) $options['delete_orders'] : false,
			'reassign_posts' => isset( $options['reassign_posts'] ) ? absint( $options['reassign_posts'] ) : 0,
		);

		$users_handler = new WC_Data_Cleanup_Users();
		$result = array();

		switch ( $action ) {
			case 'delete_all':
				$result = $users_handler->delete_all_customer_users( $sanitized_options );
				break;
			case 'delete_selected':
				$result = $users_handler->delete_selected_users( $user_ids, $sanitized_options );
				break;
			case 'delete_except':
				$result = $users_handler->delete_all_except_selected_users( $user_ids, $sanitized_options );
				break;
			default:
				wp_send_json_error( array( 'message' => __( 'Invalid action.', 'data-cleanup-for-woocommerce' ) ) );
				break;
		}

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		} else {
			wp_send_json_success( array(
				'message' => sprintf(
					/* translators: %d: number of deleted users */
					__( 'Successfully deleted %d users.', 'data-cleanup-for-woocommerce' ),
					$result['deleted']
				),
				'count' => $result['deleted'],
				'skipped' => isset( $result['skipped'] ) ? $result['skipped'] : 0,
				'skipped_message' => isset( $result['skipped_message'] ) ? $result['skipped_message'] : ''
			) );
		}
	}

	/**
	 * AJAX handler for deleting customers
	 */
	public function ajax_delete_customers() {
		// Check nonce
		if ( ! check_ajax_referer( 'wc-data-cleanup-nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'data-cleanup-for-woocommerce' ) ) );
		}

		// Check permissions
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'data-cleanup-for-woocommerce' ) ) );
		}

		$action = isset( $_POST['action_type'] ) ? sanitize_text_field( wp_unslash( $_POST['action_type'] ) ) : '';
		$customer_ids = isset( $_POST['customer_ids'] ) ? array_map( 'absint', wp_unslash( $_POST['customer_ids'] ) ) : array();
		
		// Get additional options
		$options = isset( $_POST['options'] ) ? wp_unslash( $_POST['options'] ) : array();
		$options = is_array( $options ) ? $options : array();
		
		// Sanitize options
		$sanitized_options = array(
			'force_delete'   => isset( $options['force_delete'] ) ? (bool) $options['force_delete'] : false,
			'delete_orders'  => isset( $options['delete_orders'] ) ? (bool) $options['delete_orders'] : false,
		);

		$customers_handler = new WC_Data_Cleanup_Customers();
		$result = array();

		switch ( $action ) {
			case 'delete_all':
				$result = $customers_handler->delete_all_customers( $sanitized_options );
				break;
			case 'delete_selected':
				$result = $customers_handler->delete_selected_customers( $customer_ids, $sanitized_options );
				break;
			case 'delete_except':
				$result = $customers_handler->delete_all_except_selected_customers( $customer_ids, $sanitized_options );
				break;
			default:
				wp_send_json_error( array( 'message' => __( 'Invalid action.', 'data-cleanup-for-woocommerce' ) ) );
				break;
		}

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		} else {
			wp_send_json_success( array(
				'message' => sprintf(
					/* translators: %d: number of deleted customers */
					__( 'Successfully deleted %d customers.', 'data-cleanup-for-woocommerce' ),
					$result['deleted']
				),
				'count' => $result['deleted'],
				'skipped' => isset( $result['skipped'] ) ? $result['skipped'] : 0,
				'skipped_message' => isset( $result['skipped_message'] ) ? $result['skipped_message'] : ''
			) );
		}
	}

	/**
	 * AJAX handler for deleting orders
	 */
	public function ajax_delete_orders() {
		// Check nonce
		if ( ! check_ajax_referer( 'wc-data-cleanup-nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'data-cleanup-for-woocommerce' ) ) );
		}

		// Check permissions
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'data-cleanup-for-woocommerce' ) ) );
		}

		$action = isset( $_POST['action_type'] ) ? sanitize_text_field( wp_unslash( $_POST['action_type'] ) ) : '';
		$order_ids = isset( $_POST['order_ids'] ) ? array_map( 'absint', wp_unslash( $_POST['order_ids'] ) ) : array();
		$order_status = isset( $_POST['order_status'] ) ? sanitize_text_field( wp_unslash( $_POST['order_status'] ) ) : '';
		$date_from = isset( $_POST['date_from'] ) ? sanitize_text_field( wp_unslash( $_POST['date_from'] ) ) : '';
		$date_to = isset( $_POST['date_to'] ) ? sanitize_text_field( wp_unslash( $_POST['date_to'] ) ) : '';
		
		// Get additional options
		$options = isset( $_POST['options'] ) ? wp_unslash( $_POST['options'] ) : array();
		$options = is_array( $options ) ? $options : array();
		
		// Sanitize options
		$sanitized_options = array(
			'batch_size' => isset( $options['batch_size'] ) ? absint( $options['batch_size'] ) : 20,
		);

		$orders_handler = new WC_Data_Cleanup_Orders();
		$result = array();

		switch ( $action ) {
			case 'delete_all':
				$result = $orders_handler->delete_all_orders( $sanitized_options );
				break;
			case 'delete_selected':
				$result = $orders_handler->delete_selected_orders( $order_ids, $sanitized_options );
				break;
			case 'delete_except':
				$result = $orders_handler->delete_all_except_selected_orders( $order_ids, $sanitized_options );
				break;
			case 'delete_by_status':
				$result = $orders_handler->delete_orders_by_status( $order_status, $sanitized_options );
				break;
			case 'delete_by_date_range':
				$result = $orders_handler->delete_orders_by_date_range( $date_from, $date_to, $sanitized_options );
				break;
			default:
				wp_send_json_error( array( 'message' => __( 'Invalid action.', 'data-cleanup-for-woocommerce' ) ) );
				break;
		}

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		} else {
			wp_send_json_success( array(
				'message' => sprintf(
					/* translators: %d: number of deleted orders */
					__( 'Successfully deleted %d orders.', 'data-cleanup-for-woocommerce' ),
					$result['deleted']
				),
				'count' => $result['deleted'],
				'total' => isset( $result['total'] ) ? $result['total'] : $result['deleted'],
				'batch_complete' => isset( $result['batch_complete'] ) ? $result['batch_complete'] : true
			) );
		}
	}

	/**
	 * AJAX handler for getting users
	 */
	public function ajax_get_users() {
		// Check nonce
		if ( ! check_ajax_referer( 'wc-data-cleanup-nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'data-cleanup-for-woocommerce' ) ) );
		}

		// Check permissions
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'data-cleanup-for-woocommerce' ) ) );
		}

		$search = isset( $_GET['search'] ) ? sanitize_text_field( wp_unslash( $_GET['search'] ) ) : '';
		$page = isset( $_GET['page'] ) ? absint( $_GET['page'] ) : 1;
		$include_data = isset( $_GET['include_data'] ) ? (bool) $_GET['include_data'] : true;

		$users_handler = new WC_Data_Cleanup_Users();
		$users = $users_handler->get_users_for_select2( $search, $page, $include_data );

		wp_send_json( $users );
	}

	/**
	 * AJAX handler for getting customers
	 */
	public function ajax_get_customers() {
		// Check nonce
		if ( ! check_ajax_referer( 'wc-data-cleanup-nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'data-cleanup-for-woocommerce' ) ) );
		}

		// Check permissions
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'data-cleanup-for-woocommerce' ) ) );
		}

		$search = isset( $_GET['search'] ) ? sanitize_text_field( wp_unslash( $_GET['search'] ) ) : '';
		$page = isset( $_GET['page'] ) ? absint( $_GET['page'] ) : 1;
		$include_data = isset( $_GET['include_data'] ) ? (bool) $_GET['include_data'] : true;

		$customers_handler = new WC_Data_Cleanup_Customers();
		$customers = $customers_handler->get_customers_for_select2( $search, $page, $include_data );

		wp_send_json( $customers );
	}

	/**
	 * AJAX handler for getting orders
	 */
	public function ajax_get_orders() {
		// Check nonce
		if ( ! check_ajax_referer( 'wc-data-cleanup-nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'data-cleanup-for-woocommerce' ) ) );
		}

		// Check permissions
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'data-cleanup-for-woocommerce' ) ) );
		}

		$search = isset( $_GET['search'] ) ? sanitize_text_field( wp_unslash( $_GET['search'] ) ) : '';
		$page = isset( $_GET['page'] ) ? absint( $_GET['page'] ) : 1;
		$include_details = isset( $_GET['include_details'] ) ? (bool) $_GET['include_details'] : true;
		$preview = isset( $_GET['preview'] ) ? (bool) $_GET['preview'] : false;
		$limit = isset( $_GET['limit'] ) ? absint( $_GET['limit'] ) : 0;
		
		// Support status filtering
		$status = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '';
		
		// Support date range filtering
		$date_from = isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : '';
		$date_to = isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : '';
		
		$orders_handler = new WC_Data_Cleanup_Orders();
		$orders = $orders_handler->get_orders_for_select2( $search, $page, $include_details, $status, $date_from, $date_to, $limit );
		
		// If this is for preview, add total count
		if ( $preview ) {
			// Get the total count of matching orders
			$total_count = $orders_handler->count_matching_orders( $status, $date_from, $date_to, $search );
			$orders['total_count'] = $total_count;
		}

		wp_send_json( $orders );
	}

	/**
	 * AJAX handler for getting order statuses
	 */
	public function ajax_get_order_statuses() {
		// Check nonce
		if ( ! check_ajax_referer( 'wc-data-cleanup-nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'data-cleanup-for-woocommerce' ) ) );
		}

		// Check permissions
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'data-cleanup-for-woocommerce' ) ) );
		}
		
		// Check if we should force a refresh of the counts
		$force_refresh = isset( $_GET['force_refresh'] ) ? (bool) $_GET['force_refresh'] : false;

		$orders_handler = new WC_Data_Cleanup_Orders();
		$order_statuses = $orders_handler->get_order_statuses( $force_refresh );

		wp_send_json( $order_statuses );
	}
	
	/**
	 * AJAX handler for deleting bookings
	 */
	public function ajax_delete_bookings() {
		// Check nonce
		if ( ! check_ajax_referer( 'wc-data-cleanup-nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'data-cleanup-for-woocommerce' ) ) );
		}

		// Check permissions
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'data-cleanup-for-woocommerce' ) ) );
		}

		// Check for direct booking IDs from the preview selection
		if (isset($_POST['booking_ids']) && !isset($_POST['action_type'])) {
			$booking_ids = array_map('absint', wp_unslash($_POST['booking_ids']));
			$delete_order = isset($_POST['delete_order']) && $_POST['delete_order'] ? true : false;
			
			// Sanitize options
			$sanitized_options = array(
				'batch_size' => 20,
				'delete_order' => $delete_order,
			);
			
			// Use the handler to delete the selected bookings
			$bookings_handler = new WC_Data_Cleanup_Bookings();
			$result = $bookings_handler->delete_selected_bookings($booking_ids, $sanitized_options);
			
			// Handle result
			if (is_wp_error($result)) {
				wp_send_json_error(array('message' => $result->get_error_message()));
			} else {
				wp_send_json_success(array(
					'message' => __('Successfully deleted bookings.', 'data-cleanup-for-woocommerce'),
					'count' => $result['deleted'],
					'orders_deleted' => isset($result['orders_deleted']) ? $result['orders_deleted'] : 0,
				));
			}
			
			return;
		}

		// Original code for other delete actions
		$action = isset( $_POST['action_type'] ) ? sanitize_text_field( wp_unslash( $_POST['action_type'] ) ) : '';
		$booking_ids = isset( $_POST['booking_ids'] ) ? array_map( 'absint', wp_unslash( $_POST['booking_ids'] ) ) : array();
		$booking_status = isset( $_POST['booking_status'] ) ? sanitize_text_field( wp_unslash( $_POST['booking_status'] ) ) : '';
		$date_from = isset( $_POST['date_from'] ) ? sanitize_text_field( wp_unslash( $_POST['date_from'] ) ) : '';
		$date_to = isset( $_POST['date_to'] ) ? sanitize_text_field( wp_unslash( $_POST['date_to'] ) ) : '';
		
		// Get additional options
		$options = isset( $_POST['options'] ) ? wp_unslash( $_POST['options'] ) : array();
		$options = is_array( $options ) ? $options : array();
		
		// Sanitize options
		$sanitized_options = array(
			'batch_size' => isset( $options['batch_size'] ) ? absint( $options['batch_size'] ) : 20,
			'delete_order' => isset( $options['delete_order'] ) ? (bool) $options['delete_order'] : false,
		);

		$bookings_handler = new WC_Data_Cleanup_Bookings();
		$result = array();

		switch ( $action ) {
			case 'delete_all':
				$result = $bookings_handler->delete_all_bookings( $sanitized_options );
				break;
			case 'delete_selected':
				$result = $bookings_handler->delete_selected_bookings( $booking_ids, $sanitized_options );
				break;
			case 'delete_except':
				$result = $bookings_handler->delete_all_except_selected_bookings( $booking_ids, $sanitized_options );
				break;
			case 'delete_by_status':
				$result = $bookings_handler->delete_bookings_by_status( $booking_status, $sanitized_options );
				break;
			case 'delete_by_date_range':
				$result = $bookings_handler->delete_bookings_by_date_range( $date_from, $date_to, $sanitized_options );
				break;
			default:
				wp_send_json_error( array( 'message' => __( 'Invalid action.', 'data-cleanup-for-woocommerce' ) ) );
				break;
		}

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		} else {
			wp_send_json_success( array(
				'message' => __( 'Successfully deleted bookings.', 'data-cleanup-for-woocommerce' ),
				'count' => $result['deleted'],
				'total' => isset( $result['total'] ) ? $result['total'] : $result['deleted'],
				'batch_complete' => isset( $result['batch_complete'] ) ? $result['batch_complete'] : true
			) );
		}
	}
	
	/**
	 * AJAX handler for getting bookings
	 */
	public function ajax_get_bookings() {
		// Check nonce
		if ( ! check_ajax_referer( 'wc-data-cleanup-nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'data-cleanup-for-woocommerce' ) ) );
			return;
		}

		// Check permissions
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'data-cleanup-for-woocommerce' ) ) );
			return;
		}
		
		// First, verify that WooCommerce Bookings is active
		if ( ! $this->is_bookings_active() ) {
			wp_send_json( array(
				'results' => array(),
				'pagination' => array( 'more' => false ),
				'error' => 'WooCommerce Bookings is not active or not properly installed.'
			) );
			return;
		}

		$search = isset( $_GET['search'] ) ? sanitize_text_field( wp_unslash( $_GET['search'] ) ) : '';
		$page = isset( $_GET['page'] ) ? absint( $_GET['page'] ) : 1;
		$include_details = isset( $_GET['include_details'] ) ? (bool) $_GET['include_details'] : true;
		$status = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '';
		$date_from = isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : '';
		$date_to = isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : '';
		
		try {
			$bookings_handler = new WC_Data_Cleanup_Bookings();
			$bookings = $bookings_handler->get_bookings_for_select2( $search, $page, $include_details, $status, $date_from, $date_to );
			
			wp_send_json( $bookings );
		} catch ( Exception $e ) {
			wp_send_json( array(
				'results' => array(),
				'pagination' => array( 'more' => false ),
				'error' => $e->getMessage()
			) );
		}
	}
	
	/**
	 * AJAX handler for getting booking statuses
	 */
	public function ajax_get_booking_statuses() {
		// Check nonce
		if ( ! check_ajax_referer( 'wc-data-cleanup-nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'data-cleanup-for-woocommerce' ) ) );
		}

		// Check permissions
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'data-cleanup-for-woocommerce' ) ) );
		}
		
		$bookings_handler = new WC_Data_Cleanup_Bookings();
		$booking_statuses = $bookings_handler->get_booking_statuses();
		
		wp_send_json( $booking_statuses );
	}

	/**
	 * AJAX handler for getting bookings preview
	 */
	public function ajax_get_bookings_preview() {
		// Check nonce
		if ( ! check_ajax_referer( 'wc-data-cleanup-nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'data-cleanup-for-woocommerce' ) ) );
			return;
		}

		// Check permissions
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'data-cleanup-for-woocommerce' ) ) );
			return;
		}
		
		// Get parameters
		$status = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '';
		$date_from = isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : '';
		$date_to = isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : '';
		$page = isset( $_GET['page'] ) ? max(1, absint( $_GET['page'] )) : 1;
		$limit = isset( $_GET['limit'] ) ? absint( $_GET['limit'] ) : 20;
		$offset = ( $page - 1 ) * $limit;
		
		// Debug log
		if ( defined('WP_DEBUG') && WP_DEBUG ) {
			error_log('WC Data Cleanup - Bookings Preview Request');
			error_log('Status: ' . $status);
			error_log('Date From: ' . $date_from);
			error_log('Date To: ' . $date_to);
			error_log('Page: ' . $page);
			error_log('Limit: ' . $limit);
			error_log('Offset: ' . $offset);
		}
		
		global $wpdb;
		
		// Start building the query
		$query = "SELECT p.ID, p.post_date, p.post_status, 
			COALESCE(pm_order.meta_value, 0) as order_id,
			COALESCE(pm_customer.meta_value, 0) as customer_id,
			COALESCE(pm_product.meta_value, 0) as product_id,
			COALESCE(pm_cost.meta_value, 0) as cost,
			COALESCE(pm_start.meta_value, '') as start_date
			FROM {$wpdb->posts} p
			LEFT JOIN {$wpdb->postmeta} pm_order ON p.ID = pm_order.post_id AND pm_order.meta_key = '_booking_order_id'
			LEFT JOIN {$wpdb->postmeta} pm_customer ON p.ID = pm_customer.post_id AND pm_customer.meta_key = '_booking_customer_id'
			LEFT JOIN {$wpdb->postmeta} pm_product ON p.ID = pm_product.post_id AND pm_product.meta_key = '_booking_product_id'
			LEFT JOIN {$wpdb->postmeta} pm_cost ON p.ID = pm_cost.post_id AND pm_cost.meta_key = '_booking_cost'
			LEFT JOIN {$wpdb->postmeta} pm_start ON p.ID = pm_start.post_id AND pm_start.meta_key = '_booking_start'
			WHERE p.post_type = 'wc_booking'";
		
		// Add status filter if provided
		if (!empty($status)) {
			$query .= $wpdb->prepare(" AND p.post_status = %s", $status);
		}
		
		// Add date range filter if provided
		if (!empty($date_from) && !empty($date_to)) {
			$start_timestamp = strtotime($date_from . ' 00:00:00');
			$end_timestamp = strtotime($date_to . ' 23:59:59');
			
			if ($start_timestamp && $end_timestamp) {
				$start_date = date('YmdHis', $start_timestamp);
				$end_date = date('YmdHis', $end_timestamp);
				
				$query .= $wpdb->prepare(" AND pm_start.meta_value BETWEEN %s AND %s", $start_date, $end_date);
			}
		}
		
		// Get total count for pagination using properly prepared queries without conditionals in SQL
		if (!empty($status) && !empty($date_from) && !empty($date_to)) {
			// Both status and date filters
			$total_count = $wpdb->get_var($wpdb->prepare(
				"SELECT COUNT(*) 
				FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->postmeta} pm_start ON p.ID = pm_start.post_id AND pm_start.meta_key = '_booking_start'
				WHERE p.post_type = 'wc_booking'
				AND p.post_status = %s
				AND pm_start.meta_value BETWEEN %s AND %s",
				$status,
				$start_date,
				$end_date
			));
		} elseif (!empty($status)) {
			// Only status filter
			$total_count = $wpdb->get_var($wpdb->prepare(
				"SELECT COUNT(*) 
				FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->postmeta} pm_start ON p.ID = pm_start.post_id AND pm_start.meta_key = '_booking_start'
				WHERE p.post_type = 'wc_booking'
				AND p.post_status = %s",
				$status
			));
		} elseif (!empty($date_from) && !empty($date_to)) {
			// Only date filter
			$total_count = $wpdb->get_var($wpdb->prepare(
				"SELECT COUNT(*) 
				FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->postmeta} pm_start ON p.ID = pm_start.post_id AND pm_start.meta_key = '_booking_start'
				WHERE p.post_type = 'wc_booking'
				AND pm_start.meta_value BETWEEN %s AND %s",
				$start_date,
				$end_date
			));
		} else {
			// No filters
			$total_count = $wpdb->get_var(
				"SELECT COUNT(*) 
				FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->postmeta} pm_start ON p.ID = pm_start.post_id AND pm_start.meta_key = '_booking_start'
				WHERE p.post_type = 'wc_booking'"
			);
		}
		
		if ( defined('WP_DEBUG') && WP_DEBUG ) {
			error_log('Count Query: ' . $count_query);
			error_log('Total Count: ' . $total_count);
		}
		
		// Execute query based on the filter conditions using full SQL statements in each prepare call
		if (!empty($status) && !empty($date_from) && !empty($date_to)) {
			// Both status and date filters
			$bookings = $wpdb->get_results($wpdb->prepare(
				"SELECT p.ID, p.post_date, p.post_status, 
				COALESCE(pm_order.meta_value, 0) as order_id,
				COALESCE(pm_customer.meta_value, 0) as customer_id,
				COALESCE(pm_product.meta_value, 0) as product_id,
				COALESCE(pm_cost.meta_value, 0) as cost,
				COALESCE(pm_start.meta_value, '') as start_date
				FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->postmeta} pm_order ON p.ID = pm_order.post_id AND pm_order.meta_key = '_booking_order_id'
				LEFT JOIN {$wpdb->postmeta} pm_customer ON p.ID = pm_customer.post_id AND pm_customer.meta_key = '_booking_customer_id'
				LEFT JOIN {$wpdb->postmeta} pm_product ON p.ID = pm_product.post_id AND pm_product.meta_key = '_booking_product_id'
				LEFT JOIN {$wpdb->postmeta} pm_cost ON p.ID = pm_cost.post_id AND pm_cost.meta_key = '_booking_cost'
				LEFT JOIN {$wpdb->postmeta} pm_start ON p.ID = pm_start.post_id AND pm_start.meta_key = '_booking_start'
				WHERE p.post_type = 'wc_booking'
				AND p.post_status = %s 
				AND pm_start.meta_value BETWEEN %s AND %s
				ORDER BY p.post_date DESC
				LIMIT %d OFFSET %d",
				$status,
				$start_date,
				$end_date,
				$limit,
				$offset
			));
		} elseif (!empty($status)) {
			// Only status filter
			$bookings = $wpdb->get_results($wpdb->prepare(
				"SELECT p.ID, p.post_date, p.post_status, 
				COALESCE(pm_order.meta_value, 0) as order_id,
				COALESCE(pm_customer.meta_value, 0) as customer_id,
				COALESCE(pm_product.meta_value, 0) as product_id,
				COALESCE(pm_cost.meta_value, 0) as cost,
				COALESCE(pm_start.meta_value, '') as start_date
				FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->postmeta} pm_order ON p.ID = pm_order.post_id AND pm_order.meta_key = '_booking_order_id'
				LEFT JOIN {$wpdb->postmeta} pm_customer ON p.ID = pm_customer.post_id AND pm_customer.meta_key = '_booking_customer_id'
				LEFT JOIN {$wpdb->postmeta} pm_product ON p.ID = pm_product.post_id AND pm_product.meta_key = '_booking_product_id'
				LEFT JOIN {$wpdb->postmeta} pm_cost ON p.ID = pm_cost.post_id AND pm_cost.meta_key = '_booking_cost'
				LEFT JOIN {$wpdb->postmeta} pm_start ON p.ID = pm_start.post_id AND pm_start.meta_key = '_booking_start'
				WHERE p.post_type = 'wc_booking'
				AND p.post_status = %s
				ORDER BY p.post_date DESC
				LIMIT %d OFFSET %d",
				$status,
				$limit,
				$offset
			));
		} elseif (!empty($date_from) && !empty($date_to)) {
			// Only date filter
			$bookings = $wpdb->get_results($wpdb->prepare(
				"SELECT p.ID, p.post_date, p.post_status, 
				COALESCE(pm_order.meta_value, 0) as order_id,
				COALESCE(pm_customer.meta_value, 0) as customer_id,
				COALESCE(pm_product.meta_value, 0) as product_id,
				COALESCE(pm_cost.meta_value, 0) as cost,
				COALESCE(pm_start.meta_value, '') as start_date
				FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->postmeta} pm_order ON p.ID = pm_order.post_id AND pm_order.meta_key = '_booking_order_id'
				LEFT JOIN {$wpdb->postmeta} pm_customer ON p.ID = pm_customer.post_id AND pm_customer.meta_key = '_booking_customer_id'
				LEFT JOIN {$wpdb->postmeta} pm_product ON p.ID = pm_product.post_id AND pm_product.meta_key = '_booking_product_id'
				LEFT JOIN {$wpdb->postmeta} pm_cost ON p.ID = pm_cost.post_id AND pm_cost.meta_key = '_booking_cost'
				LEFT JOIN {$wpdb->postmeta} pm_start ON p.ID = pm_start.post_id AND pm_start.meta_key = '_booking_start'
				WHERE p.post_type = 'wc_booking'
				AND pm_start.meta_value BETWEEN %s AND %s
				ORDER BY p.post_date DESC
				LIMIT %d OFFSET %d",
				$start_date,
				$end_date,
				$limit,
				$offset
			));
		} else {
			// No filters
			$bookings = $wpdb->get_results($wpdb->prepare(
				"SELECT p.ID, p.post_date, p.post_status, 
				COALESCE(pm_order.meta_value, 0) as order_id,
				COALESCE(pm_customer.meta_value, 0) as customer_id,
				COALESCE(pm_product.meta_value, 0) as product_id,
				COALESCE(pm_cost.meta_value, 0) as cost,
				COALESCE(pm_start.meta_value, '') as start_date
				FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->postmeta} pm_order ON p.ID = pm_order.post_id AND pm_order.meta_key = '_booking_order_id'
				LEFT JOIN {$wpdb->postmeta} pm_customer ON p.ID = pm_customer.post_id AND pm_customer.meta_key = '_booking_customer_id'
				LEFT JOIN {$wpdb->postmeta} pm_product ON p.ID = pm_product.post_id AND pm_product.meta_key = '_booking_product_id'
				LEFT JOIN {$wpdb->postmeta} pm_cost ON p.ID = pm_cost.post_id AND pm_cost.meta_key = '_booking_cost'
				LEFT JOIN {$wpdb->postmeta} pm_start ON p.ID = pm_start.post_id AND pm_start.meta_key = '_booking_start'
				WHERE p.post_type = 'wc_booking'
				ORDER BY p.post_date DESC
				LIMIT %d OFFSET %d",
				$limit,
				$offset
			));
		}
		
		if ( defined('WP_DEBUG') && WP_DEBUG ) {
			error_log('Query executed with prepared statement');
		}
		
		if ( defined('WP_DEBUG') && WP_DEBUG ) {
			error_log('Bookings found: ' . count($bookings));
		}
		
		$formatted_bookings = array();
		
		foreach ($bookings as $booking) {
			// Get product name
			$product_name = '';
			if ($booking->product_id) {
				$product_post = get_post($booking->product_id);
				$product_name = $product_post ? $product_post->post_title : __('(Unknown product)', 'data-cleanup-for-woocommerce');
			}
			
			// Get customer name
			$customer_name = '';
			if ($booking->customer_id) {
				$customer = get_userdata($booking->customer_id);
				$customer_name = $customer ? $customer->display_name : __('(Guest)', 'data-cleanup-for-woocommerce');
			} else {
				$customer_name = __('Guest', 'data-cleanup-for-woocommerce');
			}
			
			// Format date
			$start_date_formatted = '';
			if (!empty($booking->start_date)) {
				// Try to parse as YmdHis format
				$date = DateTime::createFromFormat('YmdHis', $booking->start_date);
				if ($date) {
					$start_date_formatted = $date->format('Y-m-d H:i:s');
				} else {
					$start_date_formatted = $booking->start_date;
				}
			}
			
			// Format status
			$status_label = ucfirst(str_replace('-', ' ', $booking->post_status));
			
			$formatted_bookings[] = array(
				'id' => $booking->ID,
				'date' => $booking->post_date,
				'status' => $booking->post_status,
				'status_label' => $status_label,
				'order_id' => $booking->order_id,
				'customer_name' => $customer_name,
				'product_name' => $product_name,
				'cost' => wc_price($booking->cost),
				'start_date' => $start_date_formatted
			);
		}
		
		$response_data = array(
			'bookings' => $formatted_bookings,
			'total' => (int) $total_count,
			'has_more' => ($offset + $limit) < (int) $total_count,
			'page' => $page,
			'pages' => ceil((int) $total_count / $limit),
			'query_info' => [
				'status' => $status,
				'date_from' => $date_from,
				'date_to' => $date_to
			]
		);
		
		if ( defined('WP_DEBUG') && WP_DEBUG ) {
			error_log('Response data: ' . json_encode($response_data));
		}
		
		wp_send_json_success($response_data);
	}

	/**
	 * Test AJAX handler for bookings
	 */
	public function ajax_test_bookings() {
		// Check permissions
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => 'No permission' ) );
			return;
		}
		
		// Test data
		$data = array(
			'bookings_class_exists' => class_exists( 'WC_Booking' ),
			'post_type_exists' => post_type_exists( 'wc_booking' ),
			'function_exists' => array(
				'wc_get_booking_status_name' => function_exists( 'wc_get_booking_status_name' ),
				'wc_booking_get_status_labels' => function_exists( 'wc_booking_get_status_labels' ),
				'wc_create_booking' => function_exists( 'wc_create_booking' ),
				'wc_get_booking' => function_exists( 'wc_get_booking' ),
			),
			'is_bookings_active' => $this->is_bookings_active(),
			'ajax_handler' => 'Working',
			'sample_booking' => array()
		);
		
		// Check if the WC_Data_Cleanup_Bookings class is loaded
		$data['bookings_class_loaded'] = class_exists( 'WC_Data_Cleanup_Bookings' );
		
		// Try to get a sample booking
		if ( class_exists( 'WC_Booking' ) ) {
			try {
				$args = array(
					'post_type' => 'wc_booking',
					'posts_per_page' => 1,
					'fields' => 'ids'
				);
				
				$query = new WP_Query( $args );
				
				if ( ! empty( $query->posts ) ) {
					$booking_id = reset( $query->posts );
					
					try {
						$booking = new WC_Booking( $booking_id );
						
						$data['sample_booking'] = array(
							'id' => $booking_id,
							'status' => $booking->get_status(),
							'customer_id' => $booking->get_customer_id(),
							'start_date' => $booking->get_start_date(),
						);
					} catch (Exception $e) {
						$data['sample_booking'] = array(
							'error' => 'Error loading booking: ' . $e->getMessage()
						);
					}
				} else {
					$data['sample_booking'] = 'No bookings found';
				}
			} catch (Exception $e) {
				$data['sample_booking'] = array(
					'error' => 'Error in query: ' . $e->getMessage()
				);
			}
		}
		
		// Test compatibility
		if ($data['bookings_class_loaded']) {
			$bookings_handler = new WC_Data_Cleanup_Bookings();
			$data['compatibility_check'] = $bookings_handler->check_bookings_compatibility();
		}
		
		wp_send_json_success( $data );
	}

	/**
	 * AJAX handler for deleting bookings by date range
	 */
	public function ajax_delete_bookings_by_date_range() {
		// Check nonce
		if ( ! check_ajax_referer( 'wc-data-cleanup-nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'data-cleanup-for-woocommerce' ) ) );
		}

		// Check permissions
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'data-cleanup-for-woocommerce' ) ) );
		}
		
		// Get date range
		$date_from = isset( $_POST['date_from'] ) ? sanitize_text_field( wp_unslash( $_POST['date_from'] ) ) : '';
		$date_to = isset( $_POST['date_to'] ) ? sanitize_text_field( wp_unslash( $_POST['date_to'] ) ) : '';
		$delete_order = isset( $_POST['delete_order'] ) && $_POST['delete_order'] ? true : false;
		
		// Validate dates
		if ( empty( $date_from ) || empty( $date_to ) ) {
			wp_send_json_error( array( 'message' => __( 'Please select both start and end dates.', 'data-cleanup-for-woocommerce' ) ) );
			return;
		}
		
		// Process deletion
		$options = array(
			'batch_size'   => 20,
			'delete_order' => $delete_order,
		);
		
		$bookings_handler = new WC_Data_Cleanup_Bookings();
		$result = $bookings_handler->delete_bookings_by_date_range( $date_from, $date_to, $options );
		
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		} else {
			wp_send_json_success( array(
				'message' => __( 'Successfully deleted bookings.', 'data-cleanup-for-woocommerce' ),
				'count' => $result['deleted']
			) );
		}
	}
}

// Initialize the admin class
new WC_Data_Cleanup_Admin(); 