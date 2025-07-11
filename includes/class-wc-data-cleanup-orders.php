<?php
/**
 * WooCommerce Data Cleanup - Orders Handler
 *
 * @package WC_Data_Cleanup
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Data_Cleanup_Orders class
 */
class WC_Data_Cleanup_Orders {

	/**
	 * Delete all WooCommerce orders
	 *
	 * @param array $options Deletion options.
	 * @return array|WP_Error Result of the operation
	 */
	public function delete_all_orders( $options = array() ) {
		// Get all order IDs
		$order_ids = $this->get_all_orders();

		if ( empty( $order_ids ) ) {
			return array(
				'success' => true,
				'deleted' => 0,
				'message' => __( 'No orders found to delete.', 'data-cleanup-for-woocommerce' ),
			);
		}

		return $this->delete_selected_orders( $order_ids, $options );
	}

	/**
	 * Delete selected WooCommerce orders
	 *
	 * @param array $order_ids Array of order IDs to delete.
	 * @param array $options   Deletion options.
	 * @return array|WP_Error Result of the operation
	 */
	public function delete_selected_orders( $order_ids, $options = array() ) {
		if ( empty( $order_ids ) ) {
			return new WP_Error( 'no_orders', __( 'No orders selected for deletion.', 'data-cleanup-for-woocommerce' ) );
		}

		// Default options
		$default_options = array(
			'force_delete' => true, // Whether to bypass trash and force deletion
			'batch_size'   => 20,   // Number of orders to process in each batch
		);

		$options = wp_parse_args( $options, $default_options );

		$deleted_count = 0;
		$errors = array();
		$batch_size = absint( $options['batch_size'] );
		$batch_size = $batch_size > 0 ? $batch_size : 20;

		// Process orders in batches to prevent timeouts
		$batches = array_chunk( $order_ids, $batch_size );
		$total_batches = count( $batches );
		$batch_results = array();

		foreach ( $batches as $batch_index => $batch ) {
			$batch_deleted = 0;
			$batch_errors = array();

			foreach ( $batch as $order_id ) {
				try {
					$order = wc_get_order( $order_id );
					
					if ( $order ) {
						// Force delete the order
						$result = $order->delete( $options['force_delete'] );
						
						if ( $result ) {
							$batch_deleted++;
						} else {
							$batch_errors[] = sprintf(
								/* translators: %d: order ID */
								__( 'Failed to delete order #%d.', 'data-cleanup-for-woocommerce' ),
								$order_id
							);
						}
					} else {
						$batch_errors[] = sprintf(
							/* translators: %d: order ID */
							__( 'Order #%d not found.', 'data-cleanup-for-woocommerce' ),
							$order_id
						);
					}
				} catch ( Exception $e ) {
					$batch_errors[] = sprintf(
						/* translators: 1: order ID, 2: error message */
						__( 'Error deleting order #%1$d: %2$s', 'data-cleanup-for-woocommerce' ),
						$order_id,
						$e->getMessage()
					);
				}
			}

			$deleted_count += $batch_deleted;
			$errors = array_merge( $errors, $batch_errors );

			$batch_results[] = array(
				'batch'   => $batch_index + 1,
				'deleted' => $batch_deleted,
				'errors'  => count( $batch_errors ),
			);
		}

		return array(
			'success'       => $deleted_count > 0,
			'deleted'       => $deleted_count,
			'errors'        => $errors,
			'total_batches' => $total_batches,
			'batch_results' => $batch_results,
			// translators: %d is the number of orders deleted.
			'message'       => sprintf( __( 'Successfully deleted %d orders.', 'data-cleanup-for-woocommerce' ), $deleted_count ),
		);
	}

	/**
	 * Delete all WooCommerce orders except the selected ones
	 *
	 * @param array $order_ids Array of order IDs to keep.
	 * @param array $options   Deletion options.
	 * @return array|WP_Error Result of the operation
	 */
	public function delete_all_except_selected_orders( $order_ids, $options = array() ) {
		if ( empty( $order_ids ) ) {
			return new WP_Error( 'no_orders', __( 'No orders selected to keep.', 'data-cleanup-for-woocommerce' ) );
		}

		// Get all order IDs
		$all_orders = $this->get_all_orders();

		if ( empty( $all_orders ) ) {
			return array(
				'success' => true,
				'deleted' => 0,
				'message' => __( 'No orders found to delete.', 'data-cleanup-for-woocommerce' ),
			);
		}

		// Filter out the orders to keep
		$orders_to_delete = array_diff( $all_orders, $order_ids );

		if ( empty( $orders_to_delete ) ) {
			return array(
				'success' => true,
				'deleted' => 0,
				'message' => __( 'No orders to delete after filtering.', 'data-cleanup-for-woocommerce' ),
			);
		}

		return $this->delete_selected_orders( $orders_to_delete, $options );
	}

	/**
	 * Delete orders by status
	 *
	 * @param array $statuses Array of order statuses to delete.
	 * @param array $options  Deletion options.
	 * @return array|WP_Error Result of the operation
	 */
	public function delete_orders_by_status( $statuses, $options = array() ) {
		if ( empty( $statuses ) ) {
			return new WP_Error( 'no_statuses', __( 'No order statuses selected.', 'data-cleanup-for-woocommerce' ) );
		}

		// Get orders by status
		$order_ids = $this->get_orders_by_status( $statuses );

		if ( empty( $order_ids ) ) {
			return array(
				'success' => true,
				'deleted' => 0,
				'message' => __( 'No orders found with the selected statuses.', 'data-cleanup-for-woocommerce' ),
			);
		}

		return $this->delete_selected_orders( $order_ids, $options );
	}

	/**
	 * Delete orders by date range
	 *
	 * @param string $start_date Start date in Y-m-d format.
	 * @param string $end_date   End date in Y-m-d format.
	 * @param array  $options    Deletion options.
	 * @return array|WP_Error Result of the operation
	 */
	public function delete_orders_by_date_range( $start_date, $end_date, $options = array() ) {
		// Validate dates
		if ( ! $this->is_valid_date( $start_date ) || ! $this->is_valid_date( $end_date ) ) {
			return new WP_Error( 'invalid_dates', __( 'Invalid date format. Use YYYY-MM-DD.', 'data-cleanup-for-woocommerce' ) );
		}

		// Get orders by date range
		$order_ids = $this->get_orders_by_date_range( $start_date, $end_date );

		if ( empty( $order_ids ) ) {
			return array(
				'success' => true,
				'deleted' => 0,
				'message' => __( 'No orders found in the selected date range.', 'data-cleanup-for-woocommerce' ),
			);
		}

		return $this->delete_selected_orders( $order_ids, $options );
	}

	/**
	 * Get all WooCommerce order IDs
	 *
	 * @return array Order IDs
	 */
	private function get_all_orders() {
		return wc_get_orders( array(
			'limit'   => -1,
			'return'  => 'ids',
		) );
	}

	/**
	 * Get orders by status
	 *
	 * @param array $statuses Array of order statuses.
	 * @return array Order IDs
	 */
	private function get_orders_by_status( $statuses ) {
		// Ensure statuses is an array
		if (!is_array($statuses)) {
			$statuses = explode(',', $statuses);
		}
		
		// Clean the status values
		$clean_statuses = array();
		foreach ($statuses as $status) {
			$clean_statuses[] = str_replace('wc-', '', $status);
		}
		
		// Use WC API for consistency with the counting method
		return wc_get_orders(array(
			'status'  => $clean_statuses,
			'limit'   => -1,
			'return'  => 'ids',
		));
	}

	/**
	 * Get orders by date range
	 *
	 * @param string $start_date Start date in Y-m-d format.
	 * @param string $end_date   End date in Y-m-d format.
	 * @return array Order IDs
	 */
	private function get_orders_by_date_range( $start_date, $end_date ) {
		return wc_get_orders( array(
			'date_created' => $start_date . '...' . $end_date,
			'limit'        => -1,
			'return'       => 'ids',
		) );
	}

	/**
	 * Validate date format
	 *
	 * @param string $date Date string.
	 * @return bool Whether the date is valid
	 */
	private function is_valid_date( $date ) {
		$d = DateTime::createFromFormat( 'Y-m-d', $date );
		return $d && $d->format( 'Y-m-d' ) === $date;
	}

	/**
	 * Get order statuses for select dropdown
	 *
	 * @param boolean $force_refresh Whether to force a refresh of the counts.
	 * @return array Order statuses
	 */
	public function get_order_statuses( $force_refresh = false ) {
		static $cached_statuses = null;
		
		// Return cached result if available and not forcing refresh
		if ( !$force_refresh && $cached_statuses !== null ) {
			return $cached_statuses;
		}
		
		$statuses = wc_get_order_statuses();
		$result = array();
		
		// Add a small delay to ensure database operations complete
		if ( $force_refresh ) {
			usleep(100000); // 100ms delay
		}

		foreach ( $statuses as $status => $label ) {
			$status_key = str_replace( 'wc-', '', $status );
			$count = $this->count_orders_by_status( $status_key );
			
			$result[] = array(
				'id'    => $status_key,
				'text'  => $label,
				'count' => $count,
			);
		}
		
		// Cache the result
		$cached_statuses = $result;
		
		return $result;
	}

	/**
	 * Count orders by status
	 *
	 * @param string $status Order status.
	 * @return int Number of orders
	 */
	private function count_orders_by_status( $status ) {
		// Make sure we have a properly formatted status
		$status_formatted = strpos($status, 'wc-') === 0 ? $status : 'wc-' . $status;
		$status_clean = str_replace('wc-', '', $status);
		
		// Generate a cache key based on status
		$cache_key = 'wc_data_cleanup_order_count_' . sanitize_key($status);
		
		// Try to get from cache first
		$count = wp_cache_get($cache_key, 'wc_data_cleanup');
		
		// If found in cache, return it
		if (false !== $count) {
			return $count;
		}
		
		// Use WC_Order_Query for compatibility with both HPOS and traditional storage
		$args = array(
			'limit'  => -1, // Get all orders
			'return' => 'ids', // Only need IDs for counting
			'status' => array($status_clean), // Pass as array
		);
		
		// Try the WC API approach first - most reliable across systems
		try {
			$query = new WC_Order_Query($args);
			$orders = $query->get_orders();
			$count = count($orders);
			
			// Save in cache for future use (1 hour cache time)
			wp_cache_set($cache_key, $count, 'wc_data_cleanup', HOUR_IN_SECONDS);
			
			return $count;
		} catch (Exception $e) {
			// Fall back to direct database queries if the API fails
		}
		
		// Check if HPOS is enabled as a fallback method
		$is_hpos_enabled = function_exists('wc_get_container') && 
			class_exists('Automattic\\WooCommerce\\Utilities\\OrderUtil') && 
			Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
		
		// Direct database query as fallback, with caching
		global $wpdb;
		
		if ($is_hpos_enabled) {
			// HPOS direct query - WPCS: unprepared SQL OK - we're using $wpdb->prepare
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
			$count = $wpdb->get_var($wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}wc_orders WHERE status = %s",
				$status_clean
			));
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching
		} else {
			// Traditional storage direct query - WPCS: unprepared SQL OK - we're using $wpdb->prepare
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
			$count = $wpdb->get_var($wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'shop_order' AND post_status = %s",
				$status_formatted
			));
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching
		}
		
		// Convert to integer
		$count = absint($count);
		
		// Save in cache for future use (1 hour cache time)
		wp_cache_set($cache_key, $count, 'wc_data_cleanup', HOUR_IN_SECONDS);
		
		// Return count, fallback to 0 if query failed
		return $count;
	}

	/**
	 * Log debug message if debugging is enabled
	 *
	 * @param string $message Message to log
	 * @return void
	 */
	private function log_debug($message) {
		if (defined('WP_DEBUG') && WP_DEBUG) {
			// Only log if debugging is enabled
			wc_get_logger()->debug($message, array('source' => 'wc-data-cleanup'));
		}
	}

	/**
	 * Get orders for Select2
	 *
	 * @param string  $search          Search term
	 * @param int     $page            Page number
	 * @param boolean $include_details Whether to include order details
	 * @param string  $status          Filter by order status (comma-separated for multiple)
	 * @param string  $date_from       Filter by start date (Y-m-d format)
	 * @param string  $date_to         Filter by end date (Y-m-d format)
	 * @param int     $limit           Maximum number of orders to return (0 for default)
	 * @return array
	 */
	public function get_orders_for_select2( $search = '', $page = 1, $include_details = true, $status = '', $date_from = '', $date_to = '', $limit = 0 ) {
		global $wpdb;
		$per_page = 20; // Increased from 10 to 20
		$offset = ( $page - 1 ) * $per_page;
		
		$this->log_debug('Order Select2 search with params: search=' . $search . ', status=' . $status);
		
		// Begin building WC_Order_Query arguments
		$args = array(
			'limit' => $per_page,
			'offset' => $offset,
			'orderby' => 'date',
			'order' => 'DESC',
			'return' => 'ids',
		);
		
		// Add status filter if provided
		if (!empty($status)) {
			$statuses = explode(',', $status);
			$clean_statuses = array();
			
			foreach ($statuses as $s) {
				$clean_statuses[] = str_replace('wc-', '', $s);
			}
			
			$args['status'] = $clean_statuses;
			$this->log_debug('Filtering by statuses: ' . implode(',', $clean_statuses));
		}
		
		// Add date range filter if provided
		if (!empty($date_from) && !empty($date_to)) {
			$args['date_created'] = $date_from . '...' . $date_to;
		}
		
		// Add search if provided
		if (!empty($search)) {
			if (is_numeric($search)) {
				// If numeric, try to find by ID
				$args['id'] = absint($search);
			} else {
				// Otherwise search customer fields
				$args['customer'] = $search;
			}
		}
		
		// Use WC API to get orders - most reliable approach
		try {
			$order_ids = wc_get_orders($args);
			$this->log_debug('Found ' . count($order_ids) . ' orders with WC API');
		} catch (Exception $e) {
			$this->log_debug('Error in WC API: ' . $e->getMessage());
			$order_ids = array();
		}
		
		// If no results using standard API, try expanded search
		if (empty($order_ids)) {
			$this->log_debug('No results from WC API, trying expanded search');
			
			// First try to match any order number containing the search term
			$numeric_search = preg_replace('/[^0-9]/', '', $search);
			if (!empty($numeric_search)) {
				// Use a more efficient approach instead of meta_query
				// Create a cache key for numeric search
				$cache_key = 'wc_data_cleanup_numeric_search_' . md5($numeric_search . $status);
				$cached_results = wp_cache_get($cache_key, 'wc_data_cleanup');
				
				if (false === $cached_results) {
					$this->log_debug('Cache miss for numeric search, performing direct query');
					$matched_order_ids = array();
					
					// Get all potential order IDs that might match numeric search
					global $wpdb;
					
					// Check if HPOS is enabled
					$is_hpos_enabled = function_exists('wc_get_container') && 
						class_exists('Automattic\\WooCommerce\\Utilities\\OrderUtil') && 
						Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
					
					if ($is_hpos_enabled) {
						// HPOS: Get orders that might match the numeric pattern
						// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
						// Direct query is more efficient than meta_query here
						if (!empty($status)) {
							$statuses = explode(',', $status);
							
							// For common case - single status
							if (count($statuses) === 1) {
								$clean_status = str_replace('wc-', '', $statuses[0]);
								$matched_order_ids = $wpdb->get_col($wpdb->prepare(
									"SELECT id FROM {$wpdb->prefix}wc_orders 
									WHERE id LIKE %s AND status = %s
									LIMIT 100",
									'%' . $wpdb->esc_like($numeric_search) . '%',
									$clean_status
								));
							} else {
								// For multiple statuses, build properly prepared query
								// Create a cache key for this specific combination
								$status_string = implode(',', $statuses);
								$status_cache_key = 'wc_data_cleanup_hpos_status_in_' . md5($numeric_search . $status_string);
								$matched_order_ids = wp_cache_get($status_cache_key, 'wc_data_cleanup');
								
								if (false === $matched_order_ids) {
									// Instead of using a single query with IN clause, use separate queries for each status
									// This avoids the SQL interpolation warning
									$result_ids = array();
									
									// Process each status separately
									foreach ($statuses as $s) {
										$clean_status = str_replace('wc-', '', $s);
										
										// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
										$status_results = $wpdb->get_col($wpdb->prepare(
											"SELECT id FROM {$wpdb->prefix}wc_orders 
											WHERE id LIKE %s AND status = %s
											LIMIT 100",
											'%' . $wpdb->esc_like($numeric_search) . '%',
											$clean_status
										));
										// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
										
										// Add these results to the combined results
										if (!empty($status_results)) {
											$result_ids = array_merge($result_ids, $status_results);
										}
									}
									
									// Remove duplicates and ensure all IDs are integers
									$matched_order_ids = array_unique(array_map('absint', $result_ids));
									
									// Cache the results
									wp_cache_set($status_cache_key, $matched_order_ids, 'wc_data_cleanup', 5 * MINUTE_IN_SECONDS);
								}
							}
						} else {
							// No status filter
							// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
							$matched_order_ids = $wpdb->get_col($wpdb->prepare(
								"SELECT id FROM {$wpdb->prefix}wc_orders 
								WHERE id LIKE %s
								LIMIT 100",
								'%' . $wpdb->esc_like($numeric_search) . '%'
							));
							// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
						}
						// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
					} else {
						// Traditional posts: Get orders that might match the numeric pattern
						// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
						// Direct query is more efficient than meta_query here
						if (!empty($status)) {
							$statuses = explode(',', $status);
							
							// Convert to full status strings
							$formatted_statuses = array_map(function($s) {
								return 'wc-' . str_replace('wc-', '', $s);
							}, $statuses);
							
							// For common case - single status
							if (count($formatted_statuses) === 1) {
								$matched_order_ids = $wpdb->get_col($wpdb->prepare(
									"SELECT ID FROM {$wpdb->posts} 
									WHERE ID LIKE %s AND post_type = 'shop_order' AND post_status = %s
									LIMIT 100",
									'%' . $wpdb->esc_like($numeric_search) . '%',
									$formatted_statuses[0]
								));
							} else {
								// For multiple statuses, build properly prepared query
								// Create a cache key for this specific combination
								$status_string = implode(',', $formatted_statuses);
								$status_cache_key = 'wc_data_cleanup_posts_status_in_' . md5($numeric_search . $status_string);
								$matched_order_ids = wp_cache_get($status_cache_key, 'wc_data_cleanup');
								
								if (false === $matched_order_ids) {
									// Instead of using a single query with IN clause, use separate queries for each status
									// This avoids the SQL interpolation warning
									$result_ids = array();
									
									// Process each status separately
									foreach ($formatted_statuses as $formatted_status) {
										// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
										$status_results = $wpdb->get_col($wpdb->prepare(
											"SELECT ID FROM {$wpdb->posts} 
											WHERE ID LIKE %s AND post_type = 'shop_order' AND post_status = %s
											LIMIT 100",
											'%' . $wpdb->esc_like($numeric_search) . '%',
											$formatted_status
										));
										// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
										
										// Add these results to the combined results
										if (!empty($status_results)) {
											$result_ids = array_merge($result_ids, $status_results);
										}
									}
									
									// Remove duplicates and ensure all IDs are integers
									$matched_order_ids = array_unique(array_map('absint', $result_ids));
									
									// Cache the results
									wp_cache_set($status_cache_key, $matched_order_ids, 'wc_data_cleanup', 5 * MINUTE_IN_SECONDS);
								}
							}
						} else {
							// No status filter
							// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
							$matched_order_ids = $wpdb->get_col($wpdb->prepare(
								"SELECT ID FROM {$wpdb->posts} 
								WHERE ID LIKE %s AND post_type = 'shop_order'
								LIMIT 100",
								'%' . $wpdb->esc_like($numeric_search) . '%'
							));
							// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
						}
						// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
					}
					
					// Also check order numbers in meta
					if ($is_hpos_enabled) {
						// HPOS doesn't store _order_number in the main table, so we need to check the meta table
						// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
						$meta_order_ids = $wpdb->get_col($wpdb->prepare(
							"SELECT order_id FROM {$wpdb->prefix}wc_orders_meta 
							WHERE meta_key = '_order_number' AND meta_value LIKE %s
							LIMIT 100",
							'%' . $wpdb->esc_like($numeric_search) . '%'
						));
						// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
						
						// Merge with direct ID matches
						$matched_order_ids = array_merge($matched_order_ids, $meta_order_ids);
					} else {
						// Traditional posts: Check order numbers in postmeta
						// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
						$meta_order_ids = $wpdb->get_col($wpdb->prepare(
							"SELECT post_id FROM {$wpdb->postmeta} 
							WHERE meta_key = '_order_number' AND meta_value LIKE %s
							LIMIT 100",
							'%' . $wpdb->esc_like($numeric_search) . '%'
						));
						// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
						
						// Merge with direct ID matches
						$matched_order_ids = array_merge($matched_order_ids, $meta_order_ids);
					}
					
					// Remove duplicates
					$matched_order_ids = array_unique(array_map('absint', $matched_order_ids));
					
					// Cache the results
					wp_cache_set($cache_key, $matched_order_ids, 'wc_data_cleanup', 5 * MINUTE_IN_SECONDS);
					$cached_results = $matched_order_ids;
				}
				
				// Merge with existing results
				$order_ids = array_merge($order_ids, $cached_results);
				$this->log_debug('Found ' . count($cached_results) . ' orders by numeric search');
			}
			
			// If still empty and search term exists, try customer search
			if (empty($order_ids) && !empty($search)) {
				// Search in all customer fields
				$search_term = '%' . $wpdb->esc_like($search) . '%';
				
				// Check if HPOS is enabled
				$is_hpos_enabled = function_exists('wc_get_container') && 
					class_exists('Automattic\\WooCommerce\\Utilities\\OrderUtil') && 
					Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
				
				if ($is_hpos_enabled) {
					// HPOS search - handle with separate prepared queries for each scenario
					if (!empty($status)) {
						// With status filter
						$statuses = explode(',', $status);
						
						// For 1 status (common case - avoid complex dynamic SQL)
						if (count($statuses) === 1) {
							$clean_status = str_replace('wc-', '', $statuses[0]);
							
							// Create a cache key for this specific query
							$cache_key = 'wc_data_cleanup_hpos_search_' . md5($search_term . $clean_status);
							
							// Try to get results from cache
							$expanded_results = wp_cache_get($cache_key, 'wc_data_cleanup');
							
							// If not in cache, perform the query
							if (false === $expanded_results) {
								// Direct DB query is necessary here for custom search that WC API doesn't support
								// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
								// We're using caching so this is fine
								$expanded_results = $wpdb->get_col($wpdb->prepare(
									"SELECT id FROM {$wpdb->prefix}wc_orders 
									WHERE (
										billing_email LIKE %s OR
										billing_first_name LIKE %s OR
										billing_last_name LIKE %s OR
										billing_company LIKE %s
									)
									AND status = %s
									LIMIT 100",
									$search_term, $search_term, $search_term, $search_term, $clean_status
								));
								// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
								
								// Cache results for 5 minutes
								wp_cache_set($cache_key, $expanded_results, 'wc_data_cleanup', 5 * MINUTE_IN_SECONDS);
							}
						}
						// For 2 statuses
						else if (count($statuses) === 2) {
							$clean_status1 = str_replace('wc-', '', $statuses[0]);
							$clean_status2 = str_replace('wc-', '', $statuses[1]);
							
							// Create a cache key for this specific query
							$cache_key = 'wc_data_cleanup_hpos_search_' . md5($search_term . $clean_status1 . $clean_status2);
							
							// Try to get results from cache
							$expanded_results = wp_cache_get($cache_key, 'wc_data_cleanup');
							
							// If not in cache, perform the query
							if (false === $expanded_results) {
								// Direct DB query is necessary here for custom search with multiple statuses
								// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
								// We're using caching so this is fine
								$expanded_results = $wpdb->get_col($wpdb->prepare(
									"SELECT id FROM {$wpdb->prefix}wc_orders 
									WHERE (
										billing_email LIKE %s OR
										billing_first_name LIKE %s OR
										billing_last_name LIKE %s OR
										billing_company LIKE %s
									)
									AND (status = %s OR status = %s)
									LIMIT 100",
									$search_term, $search_term, $search_term, $search_term,
									$clean_status1, $clean_status2
								));
								// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
								
								// Cache results for 5 minutes
								wp_cache_set($cache_key, $expanded_results, 'wc_data_cleanup', 5 * MINUTE_IN_SECONDS);
							}
						}
						// For 3 or more statuses, build a fully prepared query
						else {
							// For multiple statuses in HPOS, use individual queries for each status
							// Create a status string for cache key
							$status_string = implode(',', $statuses);
							$cache_key = 'wc_data_cleanup_hpos_multi_' . md5($search_term . $status_string);
							
							// Try to get from cache first
							$expanded_results = wp_cache_get($cache_key, 'wc_data_cleanup');
							
							if (false === $expanded_results) {
								$result_ids = array();
								
								// Loop through each status and run a separate query
								foreach ($statuses as $s) {
									$clean_status = str_replace('wc-', '', $s);
									
									// Create a sub-cache key for this specific status
									$status_cache_key = 'wc_data_cleanup_hpos_status_' . md5($search_term . $clean_status);
									$status_results = wp_cache_get($status_cache_key, 'wc_data_cleanup');
									
									if (false === $status_results) {
										// Use a separate query for each status
										// Direct DB query needed for multi-status search that WC API doesn't handle well
										// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
										$status_results = $wpdb->get_col($wpdb->prepare(
											"SELECT id FROM {$wpdb->prefix}wc_orders 
											WHERE (
												billing_email LIKE %s OR 
												billing_first_name LIKE %s OR 
												billing_last_name LIKE %s OR 
												billing_company LIKE %s
											) 
											AND status = %s
											LIMIT 100",
											$search_term, $search_term, $search_term, $search_term, $clean_status
										));
										// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
										
										// Cache individual status results
										wp_cache_set($status_cache_key, $status_results, 'wc_data_cleanup', 5 * MINUTE_IN_SECONDS);
									}
									
									// Merge results
									if (!empty($status_results)) {
										$result_ids = array_merge($result_ids, $status_results);
									}
								}
								
								// Remove duplicates
								$expanded_results = array_unique($result_ids);
								
								// Cache the combined results
								wp_cache_set($cache_key, $expanded_results, 'wc_data_cleanup', 5 * MINUTE_IN_SECONDS);
							}
						}
					} else {
						// Without status filter - simpler query
						// Create cache key for this search
						$cache_key = 'wc_data_cleanup_hpos_no_status_' . md5($search_term);
						
						// Try to get from cache first
						$expanded_results = wp_cache_get($cache_key, 'wc_data_cleanup');
						
						if (false === $expanded_results) {
							// Direct DB query is needed for custom search across multiple fields
							// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
							$expanded_results = $wpdb->get_col($wpdb->prepare(
								"SELECT id FROM {$wpdb->prefix}wc_orders 
								WHERE (
									billing_email LIKE %s OR
									billing_first_name LIKE %s OR
									billing_last_name LIKE %s OR
									billing_company LIKE %s
								)
								LIMIT 100",
								$search_term, $search_term, $search_term, $search_term
							));
							// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
							
							// Cache results for 5 minutes
							wp_cache_set($cache_key, $expanded_results, 'wc_data_cleanup', 5 * MINUTE_IN_SECONDS);
						}
					}
				} else {
					// Traditional post meta search - with properly prepared queries
					$meta_keys = array(
						'_billing_email',
						'_billing_first_name',
						'_billing_last_name',
						'_billing_company',
					);
					
					// Build a safer approach that avoids interpolation warnings
					// We'll create the meta conditions directly with wpdb->prepare for each condition
					$meta_query_values = array();
					$prepared_meta_conditions = array();
					
					foreach ($meta_keys as $key) {
						// This creates a properly prepared fragment for each meta key condition
						$prepared_meta_conditions[] = $wpdb->prepare("(meta_key = %s AND meta_value LIKE %s)", $key, $search_term);
					}
					
					// Now we have fully prepared fragments that can be safely joined with OR
					
					// Handle status filter cases separately for proper preparation
					if (!empty($status)) {
						$statuses = explode(',', $status);
						
						// Common case - single status
						if (count($statuses) === 1) {
							$formatted_status = 'wc-' . str_replace('wc-', '', $statuses[0]);
							
							// Add the status to the values array first
							$query_values = $meta_query_values;
							$query_values[] = $formatted_status;
							
							// For single status, we'll use a completely different approach to avoid SQL warnings
							// Create a cache key for this query
							$cache_key = 'wc_data_cleanup_meta_single_' . md5($search_term . $formatted_status);
							
							// Try to get from cache first
							$expanded_results = wp_cache_get($cache_key, 'wc_data_cleanup');
							
							if (false === $expanded_results) {
								// Use individual queries for each meta condition and combine results
								$result_ids = array();
								
								// Loop through each meta key and do a separate query
								foreach ($meta_keys as $key) {
									// Create a subcache key for this specific meta key
									$meta_cache_key = 'wc_data_cleanup_meta_key_' . md5($search_term . $formatted_status . $key);
									$query_results = wp_cache_get($meta_cache_key, 'wc_data_cleanup');
									
									if (false === $query_results) {
										// Direct DB query needed for advanced searching across meta fields
										// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
										$query_results = $wpdb->get_col($wpdb->prepare(
											"SELECT DISTINCT p.ID 
											FROM {$wpdb->posts} p
											INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
											WHERE p.post_type = 'shop_order'
											AND p.post_status = %s
											AND pm.meta_key = %s
											AND pm.meta_value LIKE %s
											LIMIT 100",
											$formatted_status,
											$key,
											$search_term
										));
										// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
										
										// Cache individual meta key results
										wp_cache_set($meta_cache_key, $query_results, 'wc_data_cleanup', 5 * MINUTE_IN_SECONDS);
									}
									
									// Merge results
									if (!empty($query_results)) {
										$result_ids = array_merge($result_ids, $query_results);
									}
								}
								
								// Remove duplicates
								$expanded_results = array_unique($result_ids);
								
								// Cache the combined results
								wp_cache_set($cache_key, $expanded_results, 'wc_data_cleanup', 5 * MINUTE_IN_SECONDS);
							}
						}
						// Two statuses
						else if (count($statuses) === 2) {
							$formatted_status1 = 'wc-' . str_replace('wc-', '', $statuses[0]);
							$formatted_status2 = 'wc-' . str_replace('wc-', '', $statuses[1]);
							
							// Add status values to the array first
							$query_values = $meta_query_values;
							$query_values[] = $formatted_status1;
							$query_values[] = $formatted_status2;
							
							// For two statuses, use the same approach with individual queries
							// Create a cache key for this double status query
							$cache_key = 'wc_data_cleanup_meta_two_status_' . md5($search_term . $formatted_status1 . $formatted_status2);
							
							// Try to get from cache first
							$expanded_results = wp_cache_get($cache_key, 'wc_data_cleanup');
							
							if (false === $expanded_results) {
								$result_ids = array();
								
								// Loop through each combination of meta key and status
								foreach ($meta_keys as $key) {
									// Cache key for first status query
									$status1_key = 'wc_data_cleanup_meta_key_status_' . md5($search_term . $formatted_status1 . $key);
									$results1 = wp_cache_get($status1_key, 'wc_data_cleanup');
									
									if (false === $results1) {
										// Query for first status
										// Direct DB query is necessary for complex multi-status search
										// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
										$results1 = $wpdb->get_col($wpdb->prepare(
											"SELECT DISTINCT p.ID 
											FROM {$wpdb->posts} p
											INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
											WHERE p.post_type = 'shop_order'
											AND p.post_status = %s
											AND pm.meta_key = %s
											AND pm.meta_value LIKE %s
											LIMIT 100",
											$formatted_status1,
											$key,
											$search_term
										));
										// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
										
										// Cache individual query results
										wp_cache_set($status1_key, $results1, 'wc_data_cleanup', 5 * MINUTE_IN_SECONDS);
									}
									
									// Cache key for second status query
									$status2_key = 'wc_data_cleanup_meta_key_status_' . md5($search_term . $formatted_status2 . $key);
									$results2 = wp_cache_get($status2_key, 'wc_data_cleanup');
									
									if (false === $results2) {
										// Query for second status
										// Direct DB query is necessary for complex multi-status search
										// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
										$results2 = $wpdb->get_col($wpdb->prepare(
											"SELECT DISTINCT p.ID 
											FROM {$wpdb->posts} p
											INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
											WHERE p.post_type = 'shop_order'
											AND p.post_status = %s
											AND pm.meta_key = %s
											AND pm.meta_value LIKE %s
											LIMIT 100",
											$formatted_status2,
											$key,
											$search_term
										));
										// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
										
										// Cache individual query results
										wp_cache_set($status2_key, $results2, 'wc_data_cleanup', 5 * MINUTE_IN_SECONDS);
									}
									
									// Merge results
									if (!empty($results1)) {
										$result_ids = array_merge($result_ids, $results1);
									}
									
									if (!empty($results2)) {
										$result_ids = array_merge($result_ids, $results2);
									}
								}
								
								// Remove duplicates
								$expanded_results = array_unique($result_ids);
								
								// Cache the combined results
								wp_cache_set($cache_key, $expanded_results, 'wc_data_cleanup', 5 * MINUTE_IN_SECONDS);
							}
						}
						// Multiple statuses
						else {
							// For multiple statuses, construct a safe query with the IN clause
							$status_placeholders = array();
							$status_values = array();
							
							foreach ($statuses as $s) {
								$status_placeholders[] = '%s';
								$status_values[] = 'wc-' . str_replace('wc-', '', $s);
							}
							
							// Build the complete query properly
							$meta_conditions_sql = implode(' OR ', $prepared_meta_conditions);
							
							// The challenge is with the interpolated meta_conditions_sql
							// We need an approach without variable interpolation
							
							// Since the meta conditions are already properly prepared,
							// Create a clean inline query with only placeholders
							$meta_subquery = "SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE " . 
								implode(' OR ', $prepared_meta_conditions);
							
							// For multiple statuses, use the same individual query approach with caching
							// Create a status string for cache key
							$status_string = implode(',', $statuses);
							$cache_key = 'wc_data_cleanup_meta_multi_' . md5($search_term . $status_string);
							
							// Try to get from cache first
							$expanded_results = wp_cache_get($cache_key, 'wc_data_cleanup');
							
							if (false === $expanded_results) {
								$result_ids = array();
								
								// Loop through each combination of meta key and status
								foreach ($meta_keys as $key) {
									foreach ($statuses as $s) {
										$formatted_status = 'wc-' . str_replace('wc-', '', $s);
										
										// Create a cache key for this specific combination
										$combo_key = 'wc_data_cleanup_meta_key_status_' . md5($search_term . $formatted_status . $key);
										$status_results = wp_cache_get($combo_key, 'wc_data_cleanup');
										
										// Direct DB query is necessary only for advanced search patterns
										// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
										
										if (false === $status_results) {
											// Query for each status
											// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
											$status_results = $wpdb->get_col($wpdb->prepare(
												"SELECT DISTINCT p.ID 
												FROM {$wpdb->posts} p
												INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
												WHERE p.post_type = 'shop_order'
												AND p.post_status = %s
												AND pm.meta_key = %s
												AND pm.meta_value LIKE %s
												LIMIT 100",
												$formatted_status,
												$key,
												$search_term
											));
											// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
											
											// Cache individual combination results
											wp_cache_set($combo_key, $status_results, 'wc_data_cleanup', 5 * MINUTE_IN_SECONDS);
										}
										
										// Merge results
										if (!empty($status_results)) {
											$result_ids = array_merge($result_ids, $status_results);
										}
									}
								}
								
								// Remove duplicates
								$expanded_results = array_unique($result_ids);
								
								// Cache the combined results
								wp_cache_set($cache_key, $expanded_results, 'wc_data_cleanup', 5 * MINUTE_IN_SECONDS);
							}
						}
					}
					// No status filter - simpler query
					else {
						// For no status filter, use individual queries for each meta key with caching
						// Create a cache key for this search without status
						$cache_key = 'wc_data_cleanup_meta_no_status_' . md5($search_term);
						
						// Try to get from cache first
						$expanded_results = wp_cache_get($cache_key, 'wc_data_cleanup');
						
						if (false === $expanded_results) {
							$result_ids = array();
							
							// Loop through each meta key separately
							foreach ($meta_keys as $key) {
								// Create a cache key for this specific meta key
								$meta_key_cache = 'wc_data_cleanup_meta_key_only_' . md5($search_term . $key);
								$query_results = wp_cache_get($meta_key_cache, 'wc_data_cleanup');
								
								if (false === $query_results) {
									// Direct DB query needed for custom meta search that WC API doesn't support
									// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
									$query_results = $wpdb->get_col($wpdb->prepare(
										"SELECT DISTINCT p.ID 
										FROM {$wpdb->posts} p
										INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
										WHERE p.post_type = 'shop_order'
										AND pm.meta_key = %s
										AND pm.meta_value LIKE %s
										LIMIT 100",
										$key,
										$search_term
									));
									// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
									
									// Cache individual meta key results
									wp_cache_set($meta_key_cache, $query_results, 'wc_data_cleanup', 5 * MINUTE_IN_SECONDS);
								}
								
								// Merge results
								if (!empty($query_results)) {
									$result_ids = array_merge($result_ids, $query_results);
								}
							}
							
							// Remove duplicates
							$expanded_results = array_unique($result_ids);
							
							// Cache the combined results
							wp_cache_set($cache_key, $expanded_results, 'wc_data_cleanup', 5 * MINUTE_IN_SECONDS);
						}
					}
				}
				
				if (!empty($expanded_results)) {
					$order_ids = array_merge($order_ids, $expanded_results);
					$this->log_debug('Found ' . count($expanded_results) . ' orders by expanded search');
				}
			}
			
			// If status filter is provided but no search, just get orders by status
			if (empty($order_ids) && !empty($status)) {
				$this->log_debug('Getting orders directly by status');
				$order_ids = $this->get_orders_by_status($status);
				
				// Apply pagination
				$order_ids = array_slice($order_ids, $offset, $per_page);
				
				$this->log_debug('Found ' . count($order_ids) . ' orders by status');
			}
		}
		
		// Get total count for pagination - if we have a status filter
		$total_count = 0;
		if (!empty($status)) {
			// Get status counts from each selected status
			$statuses = explode(',', $status);
			foreach ($statuses as $s) {
				$total_count += $this->count_orders_by_status($s);
			}
			$this->log_debug('Total count for status filter: ' . $total_count);
		}
		
		// Remove duplicates and ensure all IDs are integers
		$order_ids = array_map('absint', array_unique(array_filter($order_ids)));
		
		// Load full order data for results
		$results = array();
		foreach ($order_ids as $order_id) {
			$order = wc_get_order($order_id);
			if (!$order) {
				continue;
			}
			
			// Build a descriptive text representation of the order
			$billing_name = $order->get_formatted_billing_full_name();
			
			// If no billing name, try to get customer info differently
			if (empty($billing_name)) {
				$billing_name = trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
				if (empty($billing_name)) {
					$customer_id = $order->get_customer_id();
					if ($customer_id) {
						$user = get_user_by('id', $customer_id);
						if ($user) {
							$billing_name = $user->display_name;
						}
					}
					
					// If still empty, use a placeholder
					if (empty($billing_name)) {
						$billing_name = '(no name)';
					}
				}
			}
			
			// Check if order is placed by an admin
			$is_admin = false;
			$admin_name = '';
			$customer_id = $order->get_customer_id();
			if ($customer_id) {
				if (user_can($customer_id, 'administrator')) {
					$is_admin = true;
					$user = get_user_by('id', $customer_id);
					if ($user) {
						$admin_name = !empty($user->display_name) ? $user->display_name : $user->user_login;
					}
				}
			}
			
			$order_data = array(
				'id'   => $order_id,
				'text' => sprintf(
					'#%s - %s%s',
					$order->get_order_number(),
					$billing_name,
					$is_admin ? ' [Admin: ' . $admin_name . ']' : ''
				),
				'is_admin' => $is_admin,
				'admin_name' => $admin_name,
			);
			
			// Include additional details if requested
			if ($include_details) {
				$order_data['date'] = $order->get_date_created() ? $order->get_date_created()->date_i18n(get_option('date_format') . ' ' . get_option('time_format')) : '';
				$order_data['status'] = wc_get_order_status_name($order->get_status());
				$order_data['total'] = html_entity_decode(wp_strip_all_tags($order->get_formatted_order_total()));
				$order_data['customer_email'] = $order->get_billing_email();
			}
			
			$results[] = $order_data;
		}
		
		// For previews, add the total count
		$pagination = array(
			'more' => ($total_count > 0) ? $total_count > ($offset + $per_page) : count($order_ids) >= $per_page,
		);
		
		return array(
			'results' => $results,
			'pagination' => $pagination,
			'total_count' => $total_count,
		);
	}

	/**
	 * Count matching orders based on filters
	 *
	 * @param string $status    Order status (comma-separated for multiple)
	 * @param string $date_from Start date in Y-m-d format
	 * @param string $date_to   End date in Y-m-d format
	 * @param string $search    Search term
	 * @return int Number of matching orders
	 */
	public function count_matching_orders( $status = '', $date_from = '', $date_to = '', $search = '' ) {
		$args = array(
			'return' => 'ids',
			'limit'  => -1,
		);
		
		// Add status filter if provided
		if ( ! empty( $status ) ) {
			$args['status'] = explode( ',', $status );
		}
		
		// Add date range filter if provided
		if ( ! empty( $date_from ) && ! empty( $date_to ) ) {
			$args['date_created'] = $date_from . '...' . $date_to;
		}
		
		// Add search filter if provided
		if ( ! empty( $search ) ) {
			if ( is_numeric( $search ) ) {
				// If numeric, search by ID or customer ID
				$args['id'] = absint( $search );
			} else {
				// Otherwise search by customer info
				$args['customer'] = $search;
			}
		}
		
		// Get matching order IDs
		$orders = wc_get_orders( $args );
		
		return count( $orders );
	}
} 