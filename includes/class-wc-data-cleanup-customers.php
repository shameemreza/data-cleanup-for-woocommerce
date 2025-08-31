<?php
/**
 * WooCommerce Data Cleanup - Customers Handler
 *
 * @package WC_Data_Cleanup
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Data_Cleanup_Customers class
 */
class WC_Data_Cleanup_Customers {

	/**
	 * Delete all WooCommerce customers
	 *
	 * @param array $options Deletion options.
	 * @return array|WP_Error Result of the operation
	 */
	public function delete_all_customers( $options = array() ) {
		// Get all customers
		$customers = $this->get_all_customers();

		if ( empty( $customers ) ) {
			return array(
				'success' => true,
				'deleted' => 0,
				'message' => __( 'No customers found to delete.', 'data-cleanup-for-woocommerce' ),
			);
		}

		return $this->delete_selected_customers( $customers, $options );
	}

	/**
	 * Delete selected WooCommerce customers
	 *
	 * @param array $customer_ids Array of customer IDs to delete.
	 * @param array $options      Deletion options.
	 * @return array|WP_Error Result of the operation
	 */
	public function delete_selected_customers( $customer_ids, $options = array() ) {
		if ( empty( $customer_ids ) ) {
			return new WP_Error( 'no_customers', __( 'No customers selected for deletion.', 'data-cleanup-for-woocommerce' ) );
		}

		// Default options
		$default_options = array(
			'force_delete'  => false, // If true, delete customers even if they have orders
			'delete_orders' => false, // If true, delete associated orders
		);

		$options = wp_parse_args( $options, $default_options );

		$deleted_count = 0;
		$errors = array();
		$skipped = array();
		$customers_with_orders = array();

		// First pass: check for customers with orders
		foreach ( $customer_ids as $customer_id ) {
			// Check if customer has orders
			$has_orders = $this->customer_has_orders( $customer_id );
			if ( $has_orders && ! $options['force_delete'] && ! $options['delete_orders'] ) {
				$customers_with_orders[] = $customer_id;
				$skipped[] = $customer_id;
				continue;
			}
		}

		// Second pass: delete customers that passed checks
		foreach ( $customer_ids as $customer_id ) {
			// Skip if already marked to skip
			if ( in_array( $customer_id, $skipped, true ) ) {
				continue;
			}

			// Delete associated orders if option is set
			if ( $options['delete_orders'] ) {
				$this->delete_customer_orders( $customer_id );
			}

			// Attempt to delete the customer
			try {
				$customer = new WC_Customer( $customer_id );
				if ( $customer && $customer->get_id() ) {
					$customer->delete( true );
					$deleted_count++;
				}
			} catch ( Exception $e ) {
				$errors[] = sprintf(
					/* translators: 1: customer ID, 2: error message */
					__( 'Failed to delete customer #%1$d: %2$s', 'data-cleanup-for-woocommerce' ),
					$customer_id,
					$e->getMessage()
				);
			}
		}

		// Prepare response
		$response = array(
			'success'              => $deleted_count > 0,
			'deleted'              => $deleted_count,
			'errors'               => $errors,
			'customers_with_orders' => $customers_with_orders,
			'skipped'              => $skipped,
			// translators: %d is the number of customers deleted.
			'message'              => sprintf( __( 'Successfully deleted %d customers.', 'data-cleanup-for-woocommerce' ), $deleted_count ),
		);

		// Add additional message if customers were skipped
		if ( ! empty( $customers_with_orders ) ) {
			$response['message'] .= ' ' . sprintf(
				/* translators: %d: number of customers with orders */
				__( '%d customers were skipped because they have orders.', 'data-cleanup-for-woocommerce' ),
				count( $customers_with_orders )
			);
		}

		return $response;
	}

	/**
	 * Delete all WooCommerce customers except the selected ones
	 *
	 * @param array $customer_ids Array of customer IDs to keep.
	 * @param array $options      Deletion options.
	 * @return array|WP_Error Result of the operation
	 */
	public function delete_all_except_selected_customers( $customer_ids, $options = array() ) {
		if ( empty( $customer_ids ) ) {
			return new WP_Error( 'no_customers', __( 'No customers selected to keep.', 'data-cleanup-for-woocommerce' ) );
		}

		// Get all customers
		$all_customers = $this->get_all_customers();

		if ( empty( $all_customers ) ) {
			return array(
				'success' => true,
				'deleted' => 0,
				'message' => __( 'No customers found to delete.', 'data-cleanup-for-woocommerce' ),
			);
		}

		// Filter out the customers to keep
		$customers_to_delete = array_diff( $all_customers, $customer_ids );

		if ( empty( $customers_to_delete ) ) {
			return array(
				'success' => true,
				'deleted' => 0,
				'message' => __( 'No customers to delete after filtering.', 'data-cleanup-for-woocommerce' ),
			);
		}

		return $this->delete_selected_customers( $customers_to_delete, $options );
	}

	/**
	 * Get all WooCommerce customer IDs
	 *
	 * @return array Customer IDs
	 */
	private function get_all_customers() {
		global $wpdb;

		// Query customer IDs directly from the database
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Customer data retrieval
		$customer_ids = $wpdb->get_col(
			"SELECT customer_id FROM {$wpdb->prefix}wc_customer_lookup"
		);

		return $customer_ids;
	}

	/**
	 * Check if a customer has orders
	 *
	 * @param int $customer_id Customer ID
	 * @return boolean
	 */
	public function customer_has_orders( $customer_id ) {
		// Get customer orders count
		$customer_orders = wc_get_orders( array(
			'customer_id' => $customer_id,
			'limit'       => 1,
			'return'      => 'ids',
		) );
		
		return ! empty( $customer_orders );
	}

	/**
	 * Get customer orders
	 *
	 * @param int $customer_id Customer ID.
	 * @return array Order IDs
	 */
	public function get_customer_orders( $customer_id ) {
		return wc_get_orders( array(
			'customer_id' => $customer_id,
			'return'      => 'ids',
		) );
	}

	/**
	 * Delete customer orders
	 *
	 * @param int $customer_id Customer ID.
	 * @return int Number of deleted orders
	 */
	public function delete_customer_orders( $customer_id ) {
		$orders = $this->get_customer_orders( $customer_id );
		
		if ( empty( $orders ) ) {
			return 0;
		}

		$orders_handler = new WC_Data_Cleanup_Orders();
		$result = $orders_handler->delete_selected_orders( $orders );

		return isset( $result['deleted'] ) ? $result['deleted'] : 0;
	}

	/**
	 * Get customers for Select2
	 *
	 * @param string  $search       Search term
	 * @param int     $page         Page number
	 * @param boolean $include_data Whether to include data about associated orders
	 * @return array
	 */
	public function get_customers_for_select2( $search = '', $page = 1, $include_data = true ) {
		global $wpdb;
		$per_page = 20; // Increased from 10 to 20
		$offset = ( $page - 1 ) * $per_page;
		
		// Initialize empty results array
		$results = array();
		$total_ids = 0;
		
		// Get all user IDs who have the customer role or have placed orders
		$all_customer_ids = array();
		
		// Sanitize search term for SQL queries
		$search_term = '%' . $wpdb->esc_like( $search ) . '%';
		
		if ( ! empty( $search ) ) {
			// For numeric searches, try direct ID match first
			if ( is_numeric( $search ) ) {
				// Try direct user ID
				$direct_user_id = absint( $search );
				$all_customer_ids[] = $direct_user_id;
				
				// Also try direct customer ID from meta
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Customer search query
				$meta_results = $wpdb->get_col( $wpdb->prepare( "
					SELECT user_id FROM {$wpdb->usermeta}
					WHERE meta_key = 'customer_id' AND meta_value = %s
					LIMIT 5
				", $search ) );
				
				if ( ! empty( $meta_results ) ) {
					$all_customer_ids = array_merge( $all_customer_ids, $meta_results );
				}
			}
			
			// Search in users table
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Customer search query
			$user_results = $wpdb->get_col( $wpdb->prepare( "
				SELECT ID FROM {$wpdb->users}
				WHERE 
					user_email LIKE %s OR
					user_login LIKE %s OR
					display_name LIKE %s OR
					ID LIKE %s
				LIMIT 100
			", $search_term, $search_term, $search_term, $search_term ) );
			
			if ( ! empty( $user_results ) ) {
				$all_customer_ids = array_merge( $all_customer_ids, $user_results );
			}
			
			// Search in user meta
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Customer search query
			$meta_results = $wpdb->get_col( $wpdb->prepare( "
				SELECT DISTINCT user_id FROM {$wpdb->usermeta}
				WHERE 
					(meta_key = 'first_name' AND meta_value LIKE %s) OR
					(meta_key = 'last_name' AND meta_value LIKE %s) OR
					(meta_key = 'billing_email' AND meta_value LIKE %s) OR
					(meta_key = 'billing_first_name' AND meta_value LIKE %s) OR
					(meta_key = 'billing_last_name' AND meta_value LIKE %s) OR
					(meta_key = 'billing_company' AND meta_value LIKE %s) OR
					(meta_key = 'billing_phone' AND meta_value LIKE %s)
				LIMIT 100
			", $search_term, $search_term, $search_term, $search_term, $search_term, $search_term, $search_term ) );
			
			if ( ! empty( $meta_results ) ) {
				$all_customer_ids = array_merge( $all_customer_ids, $meta_results );
			}
			
			// Also search in order meta for customer information
			$is_hpos_enabled = function_exists( 'wc_get_container' ) && 
				class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) && 
				\Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
			
			if ( $is_hpos_enabled ) {
				// HPOS compatible search
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Order customer search
				$order_meta_results = $wpdb->get_col( $wpdb->prepare( "
					SELECT DISTINCT customer_id FROM {$wpdb->prefix}wc_orders
					WHERE 
						billing_email LIKE %s OR
						billing_first_name LIKE %s OR
						billing_last_name LIKE %s OR
						CONCAT(billing_first_name, ' ', billing_last_name) LIKE %s OR
						customer_id LIKE %s
					LIMIT 100
				", $search_term, $search_term, $search_term, $search_term, $search_term ) );
			} else {
				// Traditional order meta search
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Order customer search
				$order_meta_results = $wpdb->get_col( $wpdb->prepare( "
					SELECT DISTINCT meta_value FROM {$wpdb->postmeta}
					WHERE meta_key = '_customer_user' AND meta_value > 0 AND meta_value IN (
						SELECT user_id FROM {$wpdb->usermeta}
						WHERE 
							(meta_key = 'first_name' AND meta_value LIKE %s) OR
							(meta_key = 'last_name' AND meta_value LIKE %s) OR
							(meta_key IN ('billing_first_name', '_billing_first_name') AND meta_value LIKE %s) OR
							(meta_key IN ('billing_last_name', '_billing_last_name') AND meta_value LIKE %s) OR
							(meta_key IN ('billing_email', '_billing_email') AND meta_value LIKE %s)
					)
					LIMIT 100
				", $search_term, $search_term, $search_term, $search_term, $search_term ) );
			}
			
			if ( ! empty( $order_meta_results ) ) {
				$all_customer_ids = array_merge( $all_customer_ids, $order_meta_results );
			}
			
			// De-duplicate and sanitize
			$all_customer_ids = array_map( 'absint', array_unique( array_filter( $all_customer_ids ) ) );
		} else {
			// No search term, just get customers who have placed orders
			if ( function_exists( 'wc_get_orders' ) ) {
				// Get recent customer IDs from orders
				$orders = wc_get_orders( array(
					'limit'       => 100,
					'return'      => 'ids',
					'customer_id' => '>', // Only orders with customer IDs
				) );
				
				foreach ( $orders as $order_id ) {
					$order = wc_get_order( $order_id );
					$customer_id = $order->get_customer_id();
					
					if ( $customer_id && $customer_id > 0 ) {
						$all_customer_ids[] = $customer_id;
					}
				}
				
				// De-duplicate
				$all_customer_ids = array_unique( $all_customer_ids );
			}
			
			// Add users with customer role
			$customer_role_query = new WP_User_Query( array(
				'role'    => 'customer',
				'fields'  => 'ID',
				'number'  => 100,
				'orderby' => 'registered',
				'order'   => 'DESC',
			) );
			
			$customer_role_ids = $customer_role_query->get_results();
			
			if ( ! empty( $customer_role_ids ) ) {
				$all_customer_ids = array_merge( $all_customer_ids, $customer_role_ids );
				$all_customer_ids = array_unique( $all_customer_ids );
			}
		}
		
		// Count total
		$total_ids = count( $all_customer_ids );
		
		// Apply pagination
		$paged_ids = array_slice( $all_customer_ids, $offset, $per_page );
		
		// Load customer data for the paged IDs
		foreach ( $paged_ids as $customer_id ) {
			// Get user data
			$user = get_user_by( 'id', $customer_id );
			if ( ! $user ) {
				continue;
			}
			
			// Check if user is an admin
			$is_admin = false;
			$admin_name = '';
			if ( isset( $user->roles ) && is_array( $user->roles ) ) {
				$is_admin = in_array( 'administrator', $user->roles, true );
			} else if ( user_can( $customer_id, 'administrator' ) ) {
				$is_admin = true;
			}
			
			if ( $is_admin ) {
				// Use display name if available, otherwise use username
				$admin_name = !empty( $user->display_name ) ? $user->display_name : $user->user_login;
			}
			
			// Get name data
			$first_name = get_user_meta( $customer_id, 'first_name', true );
			$last_name = get_user_meta( $customer_id, 'last_name', true );
			
			// Fall back to billing info if no name is set
			if ( empty( $first_name ) ) {
				$first_name = get_user_meta( $customer_id, 'billing_first_name', true );
			}
			
			if ( empty( $last_name ) ) {
				$last_name = get_user_meta( $customer_id, 'billing_last_name', true );
			}
			
			// Use display name as fallback, if that's empty use username
			if ( !empty( $first_name ) || !empty( $last_name ) ) {
				$display_name = trim( $first_name . ' ' . $last_name );
			} else if ( !empty( $user->display_name ) ) {
				$display_name = $user->display_name;
			} else {
				$display_name = $user->user_login;
			}
			
			// Build the customer data
			$customer_data = array(
				'id'   => $customer_id,
				'text' => sprintf(
					'%s (#%d - %s)%s',
					$display_name,
					$customer_id,
					$user->user_email,
					$is_admin ? ' [Admin: ' . $admin_name . ']' : ''
				),
				'is_admin' => $is_admin,
				'admin_name' => $admin_name,
			);
			
			// Include order data if requested
			if ( $include_data ) {
				$has_orders = $this->customer_has_orders( $customer_id );
				$customer_data['has_orders'] = $has_orders;
				
				if ( $has_orders ) {
					$customer_data['order_count'] = $this->count_customer_orders( $customer_id );
				}
			}
			
			$results[] = $customer_data;
		}
		
		return array(
			'results'    => $results,
			'pagination' => array(
				'more' => $total_ids > $offset + $per_page,
			),
		);
	}

	/**
	 * Get customer order count
	 *
	 * @param int $customer_id Customer ID
	 * @return int
	 */
	public function get_customer_order_count( $customer_id ) {
		// Get customer orders count
		$args = array(
			'customer_id' => $customer_id,
			'return'      => 'ids',
			'limit'       => -1,
		);
		
		$orders = wc_get_orders( $args );
		
		return count( $orders );
	}

	/**
	 * Count orders for a customer
	 *
	 * @param int $customer_id Customer ID
	 * @return int Number of orders
	 */
	public function count_customer_orders( $customer_id ) {
		// Check if HPOS is enabled
		$is_hpos_enabled = function_exists( 'wc_get_container' ) && 
			class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) && 
			\Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
		
		if ( $is_hpos_enabled ) {
			// Use HPOS compatible query
			global $wpdb;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Order count query
			$count = $wpdb->get_var( $wpdb->prepare( "
				SELECT COUNT(*) FROM {$wpdb->prefix}wc_orders
				WHERE customer_id = %d
			", $customer_id ) );
			
			return absint( $count );
		} else {
			// Use traditional query
			$args = array(
				'customer_id' => $customer_id,
				'return'      => 'ids',
				'limit'       => -1,
			);
			
			$orders = wc_get_orders( $args );
			return count( $orders );
		}
	}
} 