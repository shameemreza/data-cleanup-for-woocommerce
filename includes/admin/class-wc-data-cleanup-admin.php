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
	}

	/**
	 * Add admin menu items
	 */
	public function add_admin_menu() {
		add_submenu_page(
			'woocommerce',
			__( 'Data Cleanup', 'wc-data-cleanup' ),
			__( 'Data Cleanup', 'wc-data-cleanup' ),
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
				'confirm_delete_all'        => __( 'Are you sure you want to delete all items? This action cannot be undone!', 'wc-data-cleanup' ),
				'confirm_delete_selected'   => __( 'Are you sure you want to delete the selected items? This action cannot be undone!', 'wc-data-cleanup' ),
				'confirm_delete_except'     => __( 'Are you sure you want to delete all items except the selected ones? This action cannot be undone!', 'wc-data-cleanup' ),
				'error_no_selection'        => __( 'Please select at least one item.', 'wc-data-cleanup' ),
				'processing'                => __( 'Processing...', 'wc-data-cleanup' ),
				'success'                   => __( 'Operation completed successfully.', 'wc-data-cleanup' ),
				'error'                     => __( 'An error occurred.', 'wc-data-cleanup' ),
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
			<h1><?php esc_html_e( 'Data Cleanup for WooCommerce', 'wc-data-cleanup' ); ?></h1>
			
			<nav class="nav-tab-wrapper woo-nav-tab-wrapper">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-data-cleanup&tab=users' ) ); ?>" class="nav-tab <?php echo $current_tab === 'users' ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Users', 'wc-data-cleanup' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-data-cleanup&tab=customers' ) ); ?>" class="nav-tab <?php echo $current_tab === 'customers' ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Customers', 'wc-data-cleanup' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-data-cleanup&tab=orders' ) ); ?>" class="nav-tab <?php echo $current_tab === 'orders' ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Orders', 'wc-data-cleanup' ); ?>
				</a>
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
			<h2><?php esc_html_e( 'WordPress Users Cleanup', 'wc-data-cleanup' ); ?></h2>
			
			<div class="wc-data-cleanup-warning">
				<p><strong><?php esc_html_e( 'Warning: Deleting users is a permanent action and cannot be undone. Please backup your database before proceeding.', 'wc-data-cleanup' ); ?></strong></p>
			</div>
			
			<div class="wc-data-cleanup-selection">
				<h3><?php esc_html_e( 'Select Users', 'wc-data-cleanup' ); ?></h3>
				<p class="description"><?php esc_html_e( 'Type to search for users or leave empty to see recent users. Select one or more users before using the "Delete Selected" or "Delete All Except Selected" options.', 'wc-data-cleanup' ); ?></p>
				<select id="wc-data-cleanup-user-select" class="wc-data-cleanup-select" multiple="multiple" style="width: 100%;" data-placeholder="<?php esc_attr_e( 'Search for users...', 'wc-data-cleanup' ); ?>"></select>
				
							<div class="wc-data-cleanup-legend">
				<div class="wc-data-cleanup-legend-item">
					<span class="wc-data-cleanup-has-data wc-data-cleanup-has-orders"></span> <?php esc_html_e( 'Has orders', 'wc-data-cleanup' ); ?>
				</div>
				<div class="wc-data-cleanup-legend-item">
					<span class="wc-data-cleanup-has-data wc-data-cleanup-has-posts"></span> <?php esc_html_e( 'Has posts', 'wc-data-cleanup' ); ?>
				</div>
				<div class="wc-data-cleanup-legend-item">
					<span class="wc-data-cleanup-has-data wc-data-cleanup-has-comments"></span> <?php esc_html_e( 'Has comments', 'wc-data-cleanup' ); ?>
				</div>
				<div class="wc-data-cleanup-legend-item wc-data-cleanup-legend-note">
					<?php esc_html_e( '(Indicators appear when a user is selected)', 'wc-data-cleanup' ); ?>
				</div>
			</div>
			</div>
			
			<div class="wc-data-cleanup-actions">
				<h3><?php esc_html_e( 'Bulk Actions', 'wc-data-cleanup' ); ?></h3>
				
				<div class="wc-data-cleanup-action-group">
					<button type="button" class="button button-primary wc-data-cleanup-delete-all-users">
						<?php esc_html_e( 'Delete All Users with Customer Role', 'wc-data-cleanup' ); ?>
					</button>
					<span class="wc-data-cleanup-help"><?php esc_html_e( 'Deletes all WordPress users with the "Customer" role.', 'wc-data-cleanup' ); ?></span>
				</div>
				
				<div class="wc-data-cleanup-action-group">
					<button type="button" class="button wc-data-cleanup-delete-selected-users">
						<?php esc_html_e( 'Delete Selected Users', 'wc-data-cleanup' ); ?>
					</button>
					<span class="wc-data-cleanup-help"><?php esc_html_e( 'Deletes only the selected users from the search box above.', 'wc-data-cleanup' ); ?></span>
				</div>
				
				<div class="wc-data-cleanup-action-group">
					<button type="button" class="button wc-data-cleanup-delete-except-users">
						<?php esc_html_e( 'Delete All Except Selected', 'wc-data-cleanup' ); ?>
					</button>
					<span class="wc-data-cleanup-help"><?php esc_html_e( 'Deletes all users except the ones selected in the search box above.', 'wc-data-cleanup' ); ?></span>
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
			<h2><?php esc_html_e( 'WooCommerce Customers Cleanup', 'wc-data-cleanup' ); ?></h2>
			
			<div class="wc-data-cleanup-warning">
				<p><strong><?php esc_html_e( 'Warning: Deleting customers is a permanent action and cannot be undone. Please backup your database before proceeding.', 'wc-data-cleanup' ); ?></strong></p>
			</div>
			
			<div class="wc-data-cleanup-selection">
				<h3><?php esc_html_e( 'Select Customers', 'wc-data-cleanup' ); ?></h3>
				<p class="description"><?php esc_html_e( 'Type to search for customers or leave empty to see recent customers. Select one or more customers before using the "Delete Selected" or "Delete All Except Selected" options.', 'wc-data-cleanup' ); ?></p>
				<select id="wc-data-cleanup-customer-select" class="wc-data-cleanup-select" multiple="multiple" style="width: 100%;" data-placeholder="<?php esc_attr_e( 'Search for customers...', 'wc-data-cleanup' ); ?>"></select>
				
				<div class="wc-data-cleanup-legend">
					<div class="wc-data-cleanup-legend-item">
						<span class="wc-data-cleanup-has-data wc-data-cleanup-has-orders"></span> <?php esc_html_e( 'Has orders', 'wc-data-cleanup' ); ?>
					</div>
					<div class="wc-data-cleanup-legend-item wc-data-cleanup-legend-note">
						<?php esc_html_e( '(Indicator appears when a customer is selected)', 'wc-data-cleanup' ); ?>
					</div>
				</div>
			</div>
			
			<div class="wc-data-cleanup-actions">
				<h3><?php esc_html_e( 'Bulk Actions', 'wc-data-cleanup' ); ?></h3>
				
				<div class="wc-data-cleanup-action-group">
					<button type="button" class="button button-primary wc-data-cleanup-delete-all-customers">
						<?php esc_html_e( 'Delete All Customers', 'wc-data-cleanup' ); ?>
					</button>
					<span class="wc-data-cleanup-help"><?php esc_html_e( 'Deletes all WooCommerce customers.', 'wc-data-cleanup' ); ?></span>
				</div>
				
				<div class="wc-data-cleanup-action-group">
					<button type="button" class="button wc-data-cleanup-delete-selected-customers">
						<?php esc_html_e( 'Delete Selected Customers', 'wc-data-cleanup' ); ?>
					</button>
					<span class="wc-data-cleanup-help"><?php esc_html_e( 'Deletes only the selected customers from the search box above.', 'wc-data-cleanup' ); ?></span>
				</div>
				
				<div class="wc-data-cleanup-action-group">
					<button type="button" class="button wc-data-cleanup-delete-except-customers">
						<?php esc_html_e( 'Delete All Except Selected', 'wc-data-cleanup' ); ?>
					</button>
					<span class="wc-data-cleanup-help"><?php esc_html_e( 'Deletes all customers except the ones selected in the search box above.', 'wc-data-cleanup' ); ?></span>
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
			<h2><?php esc_html_e( 'WooCommerce Orders Cleanup', 'wc-data-cleanup' ); ?></h2>
			
			<div class="wc-data-cleanup-warning">
				<p><strong><?php esc_html_e( 'Warning: Deleting orders is a permanent action and cannot be undone. Please backup your database before proceeding.', 'wc-data-cleanup' ); ?></strong></p>
			</div>
			
			<div class="wc-data-cleanup-selection">
				<h3><?php esc_html_e( 'Select Orders', 'wc-data-cleanup' ); ?></h3>
				<p class="description"><?php esc_html_e( 'Type to search for orders by ID or customer name, or leave empty to see recent orders. Select one or more orders before using the "Delete Selected" or "Delete All Except Selected" options.', 'wc-data-cleanup' ); ?></p>
				<select id="wc-data-cleanup-order-select" class="wc-data-cleanup-select" multiple="multiple" style="width: 100%;" data-placeholder="<?php esc_attr_e( 'Search for orders...', 'wc-data-cleanup' ); ?>"></select>
			</div>
			
			<div class="wc-data-cleanup-actions">
				<h3><?php esc_html_e( 'Bulk Actions', 'wc-data-cleanup' ); ?></h3>
				
				<div class="wc-data-cleanup-action-group">
					<button type="button" class="button button-primary wc-data-cleanup-delete-all-orders">
						<?php esc_html_e( 'Delete All Orders', 'wc-data-cleanup' ); ?>
					</button>
					<span class="wc-data-cleanup-help"><?php esc_html_e( 'Deletes all WooCommerce orders.', 'wc-data-cleanup' ); ?></span>
				</div>
				
				<div class="wc-data-cleanup-action-group">
					<button type="button" class="button wc-data-cleanup-delete-selected-orders">
						<?php esc_html_e( 'Delete Selected Orders', 'wc-data-cleanup' ); ?>
					</button>
					<span class="wc-data-cleanup-help"><?php esc_html_e( 'Deletes only the selected orders from the search box above.', 'wc-data-cleanup' ); ?></span>
				</div>
				
				<div class="wc-data-cleanup-action-group">
					<button type="button" class="button wc-data-cleanup-delete-except-orders">
						<?php esc_html_e( 'Delete All Except Selected', 'wc-data-cleanup' ); ?>
					</button>
					<span class="wc-data-cleanup-help"><?php esc_html_e( 'Deletes all orders except the ones selected in the search box above.', 'wc-data-cleanup' ); ?></span>
				</div>
				
				<div class="wc-data-cleanup-action-group">
					<h4><?php esc_html_e( 'Delete by Status', 'wc-data-cleanup' ); ?></h4>
					<select id="wc-data-cleanup-order-status" class="wc-data-cleanup-select-status">
						<option value=""><?php esc_html_e( 'Select status...', 'wc-data-cleanup' ); ?></option>
						<?php foreach ( $order_statuses as $status ) : ?>
							<option value="<?php echo esc_attr( $status['id'] ); ?>">
								<?php echo esc_html( $status['text'] ); ?> (<?php echo esc_html( $status['count'] ); ?>)
							</option>
						<?php endforeach; ?>
					</select>
					<button type="button" class="button wc-data-cleanup-delete-by-status">
						<?php esc_html_e( 'Delete Orders with Selected Status', 'wc-data-cleanup' ); ?>
					</button>
				</div>
				
				<div class="wc-data-cleanup-action-group">
					<h4><?php esc_html_e( 'Delete by Date Range', 'wc-data-cleanup' ); ?></h4>
					<label for="wc-data-cleanup-date-from"><?php esc_html_e( 'From:', 'wc-data-cleanup' ); ?></label>
					<input type="date" id="wc-data-cleanup-date-from" class="wc-data-cleanup-date" />
					<span class="dashicons dashicons-calendar-alt"></span>
					
					<label for="wc-data-cleanup-date-to"><?php esc_html_e( 'To:', 'wc-data-cleanup' ); ?></label>
					<input type="date" id="wc-data-cleanup-date-to" class="wc-data-cleanup-date" />
					<span class="dashicons dashicons-calendar-alt"></span>
					
					<button type="button" class="button wc-data-cleanup-delete-by-date-range">
						<?php esc_html_e( 'Delete Orders in Date Range', 'wc-data-cleanup' ); ?>
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
	 * AJAX handler for deleting users
	 */
	public function ajax_delete_users() {
		// Check nonce
		if ( ! check_ajax_referer( 'wc-data-cleanup-nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'wc-data-cleanup' ) ) );
		}

		// Check permissions
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'wc-data-cleanup' ) ) );
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
				wp_send_json_error( array( 'message' => __( 'Invalid action.', 'wc-data-cleanup' ) ) );
				break;
		}

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		} else {
			wp_send_json_success( array(
				'message' => sprintf(
					/* translators: %d: number of deleted users */
					__( 'Successfully deleted %d users.', 'wc-data-cleanup' ),
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
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'wc-data-cleanup' ) ) );
		}

		// Check permissions
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'wc-data-cleanup' ) ) );
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
				wp_send_json_error( array( 'message' => __( 'Invalid action.', 'wc-data-cleanup' ) ) );
				break;
		}

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		} else {
			wp_send_json_success( array(
				'message' => sprintf(
					/* translators: %d: number of deleted customers */
					__( 'Successfully deleted %d customers.', 'wc-data-cleanup' ),
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
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'wc-data-cleanup' ) ) );
		}

		// Check permissions
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'wc-data-cleanup' ) ) );
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
				wp_send_json_error( array( 'message' => __( 'Invalid action.', 'wc-data-cleanup' ) ) );
				break;
		}

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		} else {
			wp_send_json_success( array(
				'message' => sprintf(
					/* translators: %d: number of deleted orders */
					__( 'Successfully deleted %d orders.', 'wc-data-cleanup' ),
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
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'wc-data-cleanup' ) ) );
		}

		// Check permissions
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'wc-data-cleanup' ) ) );
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
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'wc-data-cleanup' ) ) );
		}

		// Check permissions
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'wc-data-cleanup' ) ) );
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
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'wc-data-cleanup' ) ) );
		}

		// Check permissions
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'wc-data-cleanup' ) ) );
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
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'wc-data-cleanup' ) ) );
		}

		// Check permissions
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'wc-data-cleanup' ) ) );
		}
		
		// Check if we should force a refresh of the counts
		$force_refresh = isset( $_GET['force_refresh'] ) ? (bool) $_GET['force_refresh'] : false;

		$orders_handler = new WC_Data_Cleanup_Orders();
		$order_statuses = $orders_handler->get_order_statuses( $force_refresh );

		wp_send_json( $order_statuses );
	}
}

// Initialize the admin class
new WC_Data_Cleanup_Admin(); 