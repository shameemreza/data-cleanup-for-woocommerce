<?php
/**
 * WooCommerce Data Cleanup - Users Handler
 *
 * @package WC_Data_Cleanup
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Data_Cleanup_Users class
 */
class WC_Data_Cleanup_Users {

	/**
	 * Delete all WordPress users with the customer role
	 *
	 * @param array $options Deletion options.
	 * @return array|WP_Error Result of the operation
	 */
	public function delete_all_customer_users( $options = array() ) {
		// Get all users with customer role
		$users = get_users( array(
			'role'    => 'customer',
			'fields'  => 'ID',
			'number'  => -1,
		) );

		if ( empty( $users ) ) {
			return array(
				'success' => true,
				'deleted' => 0,
				'message' => __( 'No customer users found to delete.', 'data-cleanup-for-woocommerce' ),
			);
		}

		return $this->delete_selected_users( $users, $options );
	}

	/**
	 * Delete selected WordPress users
	 *
	 * @param array $user_ids Array of user IDs to delete.
	 * @param array $options  Deletion options.
	 * @return array|WP_Error Result of the operation
	 */
	public function delete_selected_users( $user_ids, $options = array() ) {
		if ( empty( $user_ids ) ) {
			return new WP_Error( 'no_users', __( 'No users selected for deletion.', 'data-cleanup-for-woocommerce' ) );
		}

		// Default options
		$default_options = array(
			'reassign_posts'    => 0, // 0 means delete posts
			'force_delete'      => false, // If true, delete users even if they have orders
			'delete_orders'     => false, // If true, delete associated orders
			'delete_comments'   => true, // If true, delete user comments
		);

		$options = wp_parse_args( $options, $default_options );

		// Check if current user is in the list
		if ( in_array( get_current_user_id(), $user_ids, true ) ) {
			return new WP_Error( 'self_delete', __( 'You cannot delete your own user account.', 'data-cleanup-for-woocommerce' ) );
		}

		$deleted_count = 0;
		$errors = array();
		$skipped = array();
		$users_with_orders = array();
		$users_with_posts = array();
		$users_with_comments = array();

		// First pass: check for users with orders, posts, or comments
		foreach ( $user_ids as $user_id ) {
			// Skip if user is an administrator
			if ( user_can( $user_id, 'administrator' ) ) {
				$errors[] = sprintf(
					/* translators: %d: user ID */
					__( 'User #%d is an administrator and cannot be deleted.', 'data-cleanup-for-woocommerce' ),
					$user_id
				);
				continue;
			}

			// Check if user has orders
			$has_orders = $this->user_has_orders( $user_id );
			if ( $has_orders && ! $options['force_delete'] && ! $options['delete_orders'] ) {
				$users_with_orders[] = $user_id;
				$skipped[] = $user_id;
				continue;
			}

			// Check if user has posts
			$has_posts = $this->user_has_posts( $user_id );
			if ( $has_posts && $options['reassign_posts'] === 0 ) {
				$users_with_posts[] = $user_id;
			}

			// Check if user has comments
			$has_comments = $this->user_has_comments( $user_id );
			if ( $has_comments && ! $options['delete_comments'] ) {
				$users_with_comments[] = $user_id;
			}
		}

		// Second pass: delete users that passed checks
		foreach ( $user_ids as $user_id ) {
			// Skip if already marked to skip
			if ( in_array( $user_id, $skipped, true ) ) {
				continue;
			}

			// Skip if user is an administrator (double check)
			if ( user_can( $user_id, 'administrator' ) ) {
				continue;
			}

			// Delete associated orders if option is set
			if ( $options['delete_orders'] ) {
				$this->delete_user_orders( $user_id );
			}

			// Attempt to delete the user
			$result = wp_delete_user( $user_id, $options['reassign_posts'] );

			if ( $result ) {
				$deleted_count++;
			} else {
				$errors[] = sprintf(
					/* translators: %d: user ID */
					__( 'Failed to delete user #%d.', 'data-cleanup-for-woocommerce' ),
					$user_id
				);
			}
		}

		// Prepare response
		$response = array(
			'success'           => $deleted_count > 0,
			'deleted'           => $deleted_count,
			'errors'            => $errors,
			'users_with_orders' => $users_with_orders,
			'users_with_posts'  => $users_with_posts,
			'users_with_comments' => $users_with_comments,
			'skipped'           => $skipped,
			// translators: %d is the number of users deleted.
			'message'           => sprintf( __( 'Successfully deleted %d users.', 'data-cleanup-for-woocommerce' ), $deleted_count ),
		);

		// Add additional message if users were skipped
		if ( ! empty( $users_with_orders ) ) {
			$response['message'] .= ' ' . sprintf(
				/* translators: %d: number of users with orders */
				__( '%d users were skipped because they have orders.', 'data-cleanup-for-woocommerce' ),
				count( $users_with_orders )
			);
		}

		return $response;
	}

	/**
	 * Delete all WordPress users with the customer role except the selected ones
	 *
	 * @param array $user_ids Array of user IDs to keep.
	 * @param array $options  Deletion options.
	 * @return array|WP_Error Result of the operation
	 */
	public function delete_all_except_selected_users( $user_ids, $options = array() ) {
		if ( empty( $user_ids ) ) {
			return new WP_Error( 'no_users', __( 'No users selected to keep.', 'data-cleanup-for-woocommerce' ) );
		}

		// Get all users with customer role
		$all_customer_users = get_users( array(
			'role'    => 'customer',
			'fields'  => 'ID',
			'number'  => -1,
		) );

		if ( empty( $all_customer_users ) ) {
			return array(
				'success' => true,
				'deleted' => 0,
				'message' => __( 'No customer users found to delete.', 'data-cleanup-for-woocommerce' ),
			);
		}

		// Filter out the users to keep
		$users_to_delete = array_diff( $all_customer_users, $user_ids );

		if ( empty( $users_to_delete ) ) {
			return array(
				'success' => true,
				'deleted' => 0,
				'message' => __( 'No users to delete after filtering.', 'data-cleanup-for-woocommerce' ),
			);
		}

		return $this->delete_selected_users( $users_to_delete, $options );
	}

	/**
	 * Check if user has orders
	 *
	 * @param int $user_id User ID.
	 * @return bool Whether the user has orders
	 */
	public function user_has_orders( $user_id ) {
		$customer_orders = wc_get_orders( array(
			'customer_id' => $user_id,
			'limit'       => 1,
			'return'      => 'ids',
		) );

		return ! empty( $customer_orders );
	}

	/**
	 * Get user orders
	 *
	 * @param int $user_id User ID.
	 * @return array Order IDs
	 */
	public function get_user_orders( $user_id ) {
		return wc_get_orders( array(
			'customer_id' => $user_id,
			'return'      => 'ids',
		) );
	}

	/**
	 * Delete user orders
	 *
	 * @param int $user_id User ID.
	 * @return int Number of deleted orders
	 */
	public function delete_user_orders( $user_id ) {
		$orders = $this->get_user_orders( $user_id );
		
		if ( empty( $orders ) ) {
			return 0;
		}

		$orders_handler = new WC_Data_Cleanup_Orders();
		$result = $orders_handler->delete_selected_orders( $orders );

		return isset( $result['deleted'] ) ? $result['deleted'] : 0;
	}

	/**
	 * Check if user has posts
	 *
	 * @param int $user_id User ID.
	 * @return bool Whether the user has posts
	 */
	public function user_has_posts( $user_id ) {
		$user_posts = get_posts( array(
			'author'         => $user_id,
			'post_type'      => 'any',
			'post_status'    => array( 'publish', 'pending', 'draft', 'future', 'private' ),
			'posts_per_page' => 1,
			'fields'         => 'ids',
		) );
		
		return ! empty( $user_posts );
	}

	/**
	 * Check if user has comments
	 *
	 * @param int $user_id User ID.
	 * @return bool Whether the user has comments
	 */
	public function user_has_comments( $user_id ) {
		$args = array(
			'user_id' => $user_id,
			'count'   => true,
			'number'  => 1,
		);
		
		$comment_count = get_comments( $args );
		
		return $comment_count > 0;
	}

	/**
	 * Get users for Select2
	 *
	 * @param string  $search       Search term
	 * @param int     $page         Page number
	 * @param boolean $include_data Whether to include data about associated content
	 * @return array
	 */
	public function get_users_for_select2( $search = '', $page = 1, $include_data = true ) {
		global $wpdb;
		$per_page = 20; // Increased from 10 to 20
		$offset = ( $page - 1 ) * $per_page;
		
		// Collect all matched user IDs
		$user_ids = array();
		
		// Sanitize search term for SQL queries
		$search_term = '%' . $wpdb->esc_like( $search ) . '%';
		
		if ( ! empty( $search ) ) {
			// For numeric searches, try to find by exact ID first
			if ( is_numeric( $search ) ) {
				$direct_user_id = absint( $search );
				$user_ids[] = $direct_user_id;
			}
			
			// Always include admin users in search results
			$include_admins = true;
			
			// Create a cache key for user search
			$users_cache_key = 'wc_data_cleanup_users_search_' . md5($search_term);
			
			// Try to get cached results first
			$sql_results = wp_cache_get($users_cache_key, 'wc_data_cleanup');
			
			// If not in cache, perform the query
			if (false === $sql_results) {
				// SQL query to search users - directly get results
				// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
				$sql_results = $wpdb->get_col( $wpdb->prepare( "
					SELECT ID FROM {$wpdb->users}
					WHERE (
						ID LIKE %s OR
						user_login LIKE %s OR
						user_email LIKE %s OR
						user_nicename LIKE %s OR
						display_name LIKE %s
					)
					ORDER BY ID DESC
					LIMIT 100
				", $search_term, $search_term, $search_term, $search_term, $search_term ) );
				// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
				
				// Cache the results for 5 minutes
				wp_cache_set($users_cache_key, $sql_results, 'wc_data_cleanup', 5 * MINUTE_IN_SECONDS);
			}
			
			if ( ! empty( $sql_results ) ) {
				$user_ids = array_merge( $user_ids, $sql_results );
			}
			
			// Create a cache key for user meta search
			$usermeta_cache_key = 'wc_data_cleanup_usermeta_search_' . md5($search_term);
			
			// Try to get cached results first
			$meta_results = wp_cache_get($usermeta_cache_key, 'wc_data_cleanup');
			
			// If not in cache, perform the query
			if (false === $meta_results) {
				// Also search in user meta - directly get results
				// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
				$meta_results = $wpdb->get_col( $wpdb->prepare( "
					SELECT DISTINCT user_id FROM {$wpdb->usermeta}
					WHERE (
						(meta_key = 'first_name' AND meta_value LIKE %s) OR
						(meta_key = 'last_name' AND meta_value LIKE %s) OR
						(meta_key = 'nickname' AND meta_value LIKE %s) OR
						(meta_key = 'description' AND meta_value LIKE %s)
					)
					LIMIT 100
				", $search_term, $search_term, $search_term, $search_term ) );
				// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
				
				// Cache the results for 5 minutes
				wp_cache_set($usermeta_cache_key, $meta_results, 'wc_data_cleanup', 5 * MINUTE_IN_SECONDS);
			}
			
			if ( ! empty( $meta_results ) ) {
				$user_ids = array_merge( $user_ids, $meta_results );
			}
			
			// Remove duplicates and ensure all IDs are integers
			$user_ids = array_map( 'absint', array_unique( array_filter( $user_ids ) ) );
		} else {
			// No search term, just get recent users
			$args = array(
				'fields'  => 'ID',
				'orderby' => 'ID',
				'order'   => 'DESC',
				'number'  => 100,
			);
			
			// Get user IDs
			$user_query = new WP_User_Query( $args );
			$user_ids = $user_query->get_results();
		}
		
		// Count total users for pagination
		$total_users = count( $user_ids );
		
		// Apply pagination to the results
		$paged_user_ids = array_slice( $user_ids, $offset, $per_page );
		
		// Get the full user objects for the paginated results
		$users = array();
		if ( ! empty( $paged_user_ids ) ) {
			// Get user objects for the current page
			$users_query = new WP_User_Query( array(
				'include' => $paged_user_ids,
				'orderby' => 'include',
			) );
			$users = $users_query->get_results();
		}
		
		// Format users for Select2
		$results = array();
		foreach ( $users as $user ) {
			// Check if user is an admin
			$is_admin = false;
			$admin_name = '';
			if (isset($user->roles) && is_array($user->roles)) {
				$is_admin = in_array('administrator', $user->roles, true);
			} else if (user_can($user->ID, 'administrator')) {
				$is_admin = true;
			}
			
			if ($is_admin) {
				// Use display name if available, otherwise use username
				$admin_name = !empty($user->display_name) ? $user->display_name : $user->user_login;
			}
			
			$user_data = array(
				'id'   => $user->ID,
				'text' => sprintf( 
					'%s (#%d - %s)%s', 
					!empty($user->display_name) ? $user->display_name : $user->user_login, 
					$user->ID, 
					$user->user_email,
					$is_admin ? ' [Admin: ' . $admin_name . ']' : ''
				),
				'is_admin' => $is_admin,
				'admin_name' => $admin_name,
			);
			
			// Include data about associated content if requested
			if ( $include_data ) {
				$user_data['has_orders'] = $this->user_has_orders( $user->ID );
				$user_data['has_posts'] = $this->user_has_posts( $user->ID );
				$user_data['has_comments'] = $this->user_has_comments( $user->ID );
			}
			
			$results[] = $user_data;
		}
		
		return array(
			'results'    => $results,
			'pagination' => array(
				'more' => $total_users > $offset + $per_page,
			),
		);
	}

	/**
	 * Get available users for reassigning content
	 *
	 * @return array User IDs and names
	 */
	public function get_users_for_reassign() {
		$users = get_users( array(
			'role__not_in' => array( 'customer' ),
			'fields'       => array( 'ID', 'user_login', 'display_name' ),
			'orderby'      => 'display_name',
			'order'        => 'ASC',
		) );

		$result = array();
		foreach ( $users as $user ) {
			$result[] = array(
				'id'   => $user->ID,
				'text' => sprintf( '%s (%s)', $user->display_name, $user->user_login ),
			);
		}

		return $result;
	}
} 