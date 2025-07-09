<?php
/**
 * WooCommerce Data Cleanup - Bookings Handler
 *
 * @package WC_Data_Cleanup
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Data_Cleanup_Bookings class
 */
class WC_Data_Cleanup_Bookings {

	/**
	 * Delete all WooCommerce bookings
	 *
	 * @param array $options Deletion options.
	 * @return array|WP_Error Result of the operation
	 */
	public function delete_all_bookings( $options = array() ) {
		// Get all booking IDs
		$booking_ids = $this->get_all_bookings();

		if ( empty( $booking_ids ) ) {
			return array(
				'success' => true,
				'deleted' => 0,
				'message' => __( 'No bookings found to delete.', 'data-cleanup-for-woocommerce' ),
			);
		}

		return $this->delete_selected_bookings( $booking_ids, $options );
	}

	/**
	 * Get all booking IDs
	 *
	 * @return array
	 */
	private function get_all_bookings() {
		global $wpdb;
		return $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'wc_booking'" );
	}
	
	/**
	 * Delete all WooCommerce bookings except the selected ones
	 *
	 * @param array $booking_ids Array of booking IDs to keep.
	 * @param array $options   Deletion options.
	 * @return array|WP_Error Result of the operation
	 */
	public function delete_all_except_selected_bookings( $booking_ids, $options = array() ) {
		if ( empty( $booking_ids ) ) {
			return new WP_Error( 'no_bookings', __( 'No bookings selected to keep.', 'data-cleanup-for-woocommerce' ) );
		}

		// Get all booking IDs
		$all_bookings = $this->get_all_bookings();

		if ( empty( $all_bookings ) ) {
			return array(
				'success' => true,
				'deleted' => 0,
				'message' => __( 'No bookings found to delete.', 'data-cleanup-for-woocommerce' ),
			);
		}

		// Filter out the bookings to keep
		$bookings_to_delete = array_diff( $all_bookings, $booking_ids );

		if ( empty( $bookings_to_delete ) ) {
			return array(
				'success' => true,
				'deleted' => 0,
				'message' => __( 'No bookings to delete after filtering.', 'data-cleanup-for-woocommerce' ),
			);
		}

		return $this->delete_selected_bookings( $bookings_to_delete, $options );
	}
	
	/**
	 * Delete bookings by status
	 *
	 * @param string $status Booking status.
	 * @param array  $options Deletion options.
	 * @return array|WP_Error Result of the operation
	 */
	public function delete_bookings_by_status( $status, $options = array() ) {
		if ( empty( $status ) ) {
			return new WP_Error( 'no_status', __( 'No booking status selected.', 'data-cleanup-for-woocommerce' ) );
		}

		// Get bookings by status
		$booking_ids = $this->get_bookings_by_status( $status );

		if ( empty( $booking_ids ) ) {
			return array(
				'success' => true,
				'deleted' => 0,
				'message' => __( 'No bookings found with the selected status.', 'data-cleanup-for-woocommerce' ),
			);
		}

		return $this->delete_selected_bookings( $booking_ids, $options );
	}
	
	/**
	 * Get bookings by status
	 *
	 * @param string $status Booking status.
	 * @return array
	 */
	private function get_bookings_by_status( $status ) {
		global $wpdb;
		return $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'wc_booking' AND post_status = %s", $status ) );
	}
	
	/**
	 * Delete bookings by date range
	 *
	 * @param string $start_date Start date in Y-m-d format.
	 * @param string $end_date   End date in Y-m-d format.
	 * @param array  $options    Deletion options.
	 * @return array|WP_Error Result of the operation
	 */
	public function delete_bookings_by_date_range( $start_date, $end_date, $options = array() ) {
		// Validate dates
		if ( ! $this->is_valid_date( $start_date ) || ! $this->is_valid_date( $end_date ) ) {
			return new WP_Error( 'invalid_dates', __( 'Invalid date format. Use YYYY-MM-DD.', 'data-cleanup-for-woocommerce' ) );
		}

		// Convert to timestamp
		$start_timestamp = strtotime( $start_date . ' 00:00:00' );
		$end_timestamp = strtotime( $end_date . ' 23:59:59' );

		// Get bookings by date range
		$booking_ids = $this->get_bookings_by_date_range( $start_timestamp, $end_timestamp );

		if ( empty( $booking_ids ) ) {
			return array(
				'success' => true,
				'deleted' => 0,
				'message' => __( 'No bookings found in the selected date range.', 'data-cleanup-for-woocommerce' ),
			);
		}

		return $this->delete_selected_bookings( $booking_ids, $options );
	}
	
	/**
	 * Get bookings by date range
	 *
	 * @param int $start_timestamp Start date timestamp.
	 * @param int $end_timestamp   End date timestamp.
	 * @return array
	 */
	private function get_bookings_by_date_range( $start_timestamp, $end_timestamp ) {
		global $wpdb;
		
		// Format dates for database query
		$start_date = gmdate( 'YmdHis', $start_timestamp );
		$end_date = gmdate( 'YmdHis', $end_timestamp );
		
		// Prepare the SQL query properly
		return $wpdb->get_col( $wpdb->prepare( 
			"SELECT p.ID 
			FROM {$wpdb->posts} p
			JOIN {$wpdb->postmeta} pm_start ON p.ID = pm_start.post_id AND pm_start.meta_key = '_booking_start'
			WHERE p.post_type = 'wc_booking'
			AND pm_start.meta_value BETWEEN %s AND %s",
			$start_date, 
			$end_date 
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
	 * Get booking statuses for select dropdown
	 *
	 * @return array Booking statuses
	 */
	public function get_booking_statuses() {
		global $wpdb;
		
		// Get all booking statuses from the database
		$statuses = $wpdb->get_col( "
			SELECT DISTINCT post_status 
			FROM {$wpdb->posts} 
			WHERE post_type = 'wc_booking'
		" );
		
		// Format statuses for Select2
		$result = array();
		foreach ( $statuses as $status ) {
			// Count bookings with this status
			$count = $wpdb->get_var( $wpdb->prepare( "
				SELECT COUNT(*) 
				FROM {$wpdb->posts} 
				WHERE post_type = 'wc_booking' 
				AND post_status = %s
			", $status ) );
			
			$result[] = array(
				'id'    => $status,
				'text'  => $this->get_booking_status_name( $status ),
				'count' => $count,
			);
		}
		
		return $result;
	}
	
	/**
	 * Count bookings by status
	 *
	 * @param string $status Booking status.
	 * @return int Number of bookings
	 */
	private function count_bookings_by_status( $status ) {
		global $wpdb;
		return absint( $wpdb->get_var( $wpdb->prepare( 
			"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'wc_booking' AND post_status = %s", 
			$status 
		) ) );
	}
	
	/**
	 * Get bookings for Select2 dropdown
	 *
	 * @param string  $search          Search term
	 * @param int     $page            Page number
	 * @param boolean $include_details Whether to include booking details
	 * @param string  $status          Filter by booking status
	 * @param string  $date_from       Filter by start date (Y-m-d format)
	 * @param string  $date_to         Filter by end date (Y-m-d format)
	 * @return array
	 */
	public function get_bookings_for_select2( $search = '', $page = 1, $include_details = true, $status = '', $date_from = '', $date_to = '' ) {
		global $wpdb;
		
		$per_page = 20;
		$offset = ( $page - 1 ) * $per_page;
		
		// Build WP_Query args
		$args = array(
			'post_type'      => 'wc_booking',
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'post_status'    => 'any',
			'fields'         => 'ids',
		);
		
		// Add status filter if provided
		if ( ! empty( $status ) ) {
			$args['post_status'] = $status;
		}
		
		// Add search condition if provided
		if ( ! empty( $search ) ) {
			if ( is_numeric( $search ) ) {
				// If numeric, search by ID
				$args['p'] = absint( $search );
			} else {
				// Otherwise search by customer name/email
				$search_term = '%' . $wpdb->esc_like( $search ) . '%';
				$customer_ids = $wpdb->get_col( $wpdb->prepare( 
					"SELECT ID FROM {$wpdb->users} 
					WHERE display_name LIKE %s 
					OR user_email LIKE %s 
					LIMIT 20",
					$search_term, $search_term
				) );
				
				if ( ! empty( $customer_ids ) ) {
					$args['meta_query'] = array(
						array(
							'key'     => '_booking_customer_id',
							'value'   => $customer_ids,
							'compare' => 'IN',
						)
					);
				} else {
					// If no customers found, search by post title
					$args['s'] = $search;
				}
			}
		}
		
		// Add date range filter if provided
		if ( ! empty( $date_from ) && ! empty( $date_to ) ) {
			$start_timestamp = strtotime( $date_from . ' 00:00:00' );
			$end_timestamp = strtotime( $date_to . ' 23:59:59' );
			
			if ( $start_timestamp && $end_timestamp ) {
				$start_date = gmdate( 'YmdHis', $start_timestamp );
				$end_date = gmdate( 'YmdHis', $end_timestamp );
				
				if ( ! isset( $args['meta_query'] ) ) {
					$args['meta_query'] = array();
				} else {
					$args['meta_query']['relation'] = 'AND';
				}
				
				$args['meta_query'][] = array(
					'key'     => '_booking_start',
					'value'   => array( $start_date, $end_date ),
					'compare' => 'BETWEEN',
					'type'    => 'NUMERIC',
				);
			}
		}
		
		// Get booking IDs
		$query = new WP_Query( $args );
		$booking_ids = $query->posts;
		$total_found = $query->found_posts;
		
		// Format results for Select2
		$results = array();
		
		foreach ( $booking_ids as $booking_id ) {
			if (!class_exists('WC_Booking')) {
				continue;
			}
			
			try {
				$booking = new WC_Booking( $booking_id );
				
				// Get product info
				$product_id = $booking->get_product_id();
				$product = wc_get_product( $product_id );
				$product_title = $product ? $product->get_name() : __( '(No product)', 'data-cleanup-for-woocommerce' );
				
				// Get customer info
				$customer_id = $booking->get_customer_id();
				$customer = $customer_id ? get_userdata( $customer_id ) : false;
				$customer_name = $customer ? $customer->display_name : __( 'Guest', 'data-cleanup-for-woocommerce' );
				
				// Format the booking text
				$booking_data = array(
					'id'   => $booking_id,
					'text' => sprintf(
						'#%s - %s (%s)',
						$booking_id,
						$product_title,
						$customer_name
					),
				);
				
				// Include additional details if requested
				if ( $include_details ) {
					$booking_data['status'] = $booking->get_status();
					$booking_data['status_label'] = $this->get_booking_status_name( $booking->get_status() );
					$booking_data['start_date'] = $booking->get_start_date();
					$booking_data['end_date'] = $booking->get_end_date();
					$booking_data['customer_id'] = $customer_id;
					$booking_data['order_id'] = $booking->get_order_id();
				}
				
				$results[] = $booking_data;
			} catch (Exception $e) {
				// Log the error but don't stop processing
				if (function_exists('wc_get_logger')) {
					wc_get_logger()->error(
						sprintf('Error processing booking #%d: %s', $booking_id, $e->getMessage()),
						array('source' => 'wc-data-cleanup')
					);
				}
				
				// Add a basic entry for this booking so it's still visible in the UI
				$results[] = array(
					'id'   => $booking_id,
					'text' => sprintf('#%s - %s', $booking_id, __('Error loading booking details', 'data-cleanup-for-woocommerce')),
					'error' => true,
				);
			}
		}
		
		// Prepare pagination data
		$pagination = array(
			'more' => $total_found > ($offset + $per_page),
		);
		
		return array(
			'results'    => $results,
			'pagination' => $pagination,
		);
	}
	
	/**
	 * Search customers by name
	 *
	 * @param string $search Search term
	 * @return array Customer IDs
	 */
	private function search_customers_by_name( $search ) {
		global $wpdb;
		
		// Search users by display name or email
		$search_term = '%' . $wpdb->esc_like( $search ) . '%';
		
		return $wpdb->get_col( $wpdb->prepare(
			"SELECT ID FROM {$wpdb->users} 
			WHERE display_name LIKE %s 
			OR user_email LIKE %s 
			LIMIT 50",
			$search_term,
			$search_term
		) );
	}

	/**
	 * Delete selected WooCommerce bookings
	 *
	 * @param array $booking_ids Array of booking IDs to delete.
	 * @param array $options   Deletion options.
	 * @return array|WP_Error Result of the operation
	 */
	public function delete_selected_bookings( $booking_ids, $options = array() ) {
		global $wpdb;
		
		if ( empty( $booking_ids ) ) {
			return new WP_Error( 'no_bookings', __( 'No bookings selected for deletion.', 'data-cleanup-for-woocommerce' ) );
		}

		// Default options
		$default_options = array(
			'force_delete' => true, // Whether to bypass trash and force deletion
			'batch_size'   => 20,   // Number of bookings to process in each batch
			'delete_order' => false, // Whether to delete related orders
		);

		$options = wp_parse_args( $options, $default_options );

		$deleted_count = 0;
		$errors = array();
		$batch_size = absint( $options['batch_size'] );
		$batch_size = $batch_size > 0 ? $batch_size : 20;

		// Process bookings in batches to prevent timeouts
		$batches = array_chunk( $booking_ids, $batch_size );
		$total_batches = count( $batches );
		$batch_results = array();

		foreach ( $batches as $batch_index => $batch ) {
			$batch_deleted = 0;
			$batch_errors = array();

			foreach ( $batch as $booking_id ) {
				try {
					// Get order ID before deleting the booking
					$order_id = 0;
					if ( $options['delete_order'] ) {
						$order_id = $wpdb->get_var( $wpdb->prepare(
							"SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_booking_order_id'",
							$booking_id
						) );
					}
					
					// Delete the booking directly using wp_delete_post
					$force = (bool) $options['force_delete'];
					$result = wp_delete_post( $booking_id, $force );
					
					if ( $result ) {
						$batch_deleted++;
						
						// Delete order if required and no other bookings are associated
						if ( $options['delete_order'] && $order_id ) {
							// Check if there are other bookings for this order
							$other_bookings = $wpdb->get_var( $wpdb->prepare(
								"SELECT COUNT(*) FROM {$wpdb->postmeta} 
								WHERE meta_key = '_booking_order_id' AND meta_value = %s AND post_id != %d",
								$order_id, $booking_id
							) );
							
							// Only delete if this was the only booking for the order
							if ( $other_bookings == 0 ) {
								wp_delete_post( $order_id, $force );
							}
						}
					} else {
						$batch_errors[] = sprintf(
							/* translators: %d: booking ID */
							__( 'Failed to delete booking #%d.', 'data-cleanup-for-woocommerce' ),
							$booking_id
						);
					}
				} catch ( Exception $e ) {
					$batch_errors[] = sprintf(
						/* translators: 1: booking ID, 2: error message */
						__( 'Error deleting booking #%1$d: %2$s', 'data-cleanup-for-woocommerce' ),
						$booking_id,
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
			// translators: %d is the number of bookings deleted.
			'message'       => sprintf( __( 'Successfully deleted %d bookings.', 'data-cleanup-for-woocommerce' ), $deleted_count ),
		);
	}

	/**
	 * Check if WooCommerce Bookings is properly installed and configured
	 * 
	 * @return boolean True if compatible
	 */
	public function check_bookings_compatibility() {
		// Check for basic requirements
		if ( ! class_exists( 'WC_Booking' ) || ! post_type_exists( 'wc_booking' ) ) {
			return false;
		}
		
		// Verify essential functions
		$required_functions = array(
			'wc_booking_get_status_labels',
			'wc_create_booking',
			'wc_get_booking'
		);
		
		foreach ( $required_functions as $function ) {
			if ( ! function_exists( $function ) ) {
				return false;
			}
		}
		
		return true;
	}

	/**
	 * Get booking status name
	 *
	 * @param string $status Booking status
	 * @return string Booking status name
	 */
	public function get_booking_status_name( $status ) {
		// If empty status, return default
		if ( empty( $status ) ) {
			return __( 'Unknown', 'data-cleanup-for-woocommerce' );
		}
		
		// If WooCommerce Bookings function exists, use it
		if ( function_exists( 'wc_get_booking_status_name' ) ) {
			return wc_get_booking_status_name( $status );
		}
		
		// Otherwise, use our own mapping
		$status_names = array(
			'unpaid'              => __( 'Unpaid', 'data-cleanup-for-woocommerce' ),
			'pending-confirmation' => __( 'Pending Confirmation', 'data-cleanup-for-woocommerce' ),
			'confirmed'           => __( 'Confirmed', 'data-cleanup-for-woocommerce' ),
			'paid'                => __( 'Paid', 'data-cleanup-for-woocommerce' ),
			'cancelled'           => __( 'Cancelled', 'data-cleanup-for-woocommerce' ),
			'complete'            => __( 'Complete', 'data-cleanup-for-woocommerce' ),
			'in-cart'             => __( 'In Cart', 'data-cleanup-for-woocommerce' ),
			'was-in-cart'         => __( 'Was In Cart', 'data-cleanup-for-woocommerce' ),
		);
		
		return isset( $status_names[ $status ] ) ? $status_names[ $status ] : ucfirst( $status );
	}
} 