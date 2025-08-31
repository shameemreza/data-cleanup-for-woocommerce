<?php
/**
 * WooCommerce Data Cleanup Products Handler
 *
 * @package WC_Data_Cleanup
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Data_Cleanup_Products class
 * 
 * Handles duplicate product detection and removal
 */
class WC_Data_Cleanup_Products {

	/**
	 * Get products by search term for AJAX search
	 *
	 * @param string $search Search term
	 * @return array
	 */
	public function search_products( $search = '' ) {
		global $wpdb;

		$results = array();

		// Build search query
		$query = "SELECT p.ID, p.post_title, pm_sku.meta_value as sku
				  FROM {$wpdb->posts} p
				  LEFT JOIN {$wpdb->postmeta} pm_sku ON p.ID = pm_sku.post_id AND pm_sku.meta_key = '_sku'
				  WHERE p.post_type IN ('product', 'product_variation') 
				  AND p.post_status != 'trash'";

		if ( ! empty( $search ) ) {
			$search_like = '%' . $wpdb->esc_like( $search ) . '%';
			$query .= $wpdb->prepare( " AND (p.post_title LIKE %s OR p.ID = %d OR pm_sku.meta_value LIKE %s)", 
				$search_like, 
				absint( $search ), 
				$search_like 
			);
		}

		$query .= " ORDER BY p.post_date DESC LIMIT 50";

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Complex query with conditional prepare
		$products = $wpdb->get_results( $query );

		if ( $products ) {
			foreach ( $products as $product ) {
				$product_obj = wc_get_product( $product->ID );
				if ( ! $product_obj ) {
					continue;
				}

				$product_info = array(
					'id'    => $product->ID,
					'text'  => sprintf( '#%d - %s', $product->ID, $product->post_title ),
					'title' => $product->post_title,
					'sku'   => $product->sku ? $product->sku : '',
					'type'  => $product_obj->get_type(),
					'price' => $product_obj->get_price(),
					'stock' => $product_obj->get_stock_quantity()
				);

				// Add SKU to display if exists
				if ( ! empty( $product->sku ) ) {
					$product_info['text'] .= ' (SKU: ' . $product->sku . ')';
				}

				$results[] = $product_info;
			}
		}

		return $results;
	}

	/**
	 * Find duplicate products
	 *
	 * @param string $criteria Detection criteria (sku, title, barcode)
	 * @param int    $page     Current page for pagination
	 * @param int    $per_page Items per page
	 * @return array
	 */
	public function find_duplicates( $criteria = 'all', $page = 1, $per_page = 20 ) {
		global $wpdb;

		// For 'all', we scan everything and return combined results
		if ( $criteria === 'all' ) {
			return $this->find_all_duplicates();
		}

		$offset = ( $page - 1 ) * $per_page;
		$duplicates = array();
		$total_groups = 0;

		switch ( $criteria ) {
			case 'sku':
				// Find products with duplicate SKUs
				$query = "SELECT pm.meta_value as duplicate_value, 
						  GROUP_CONCAT(p.ID) as product_ids,
						  COUNT(p.ID) as duplicate_count
						  FROM {$wpdb->posts} p
						  INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
						  WHERE p.post_type IN ('product', 'product_variation')
						  AND p.post_status != 'trash'
						  AND pm.meta_key = '_sku'
						  AND pm.meta_value != ''
						  GROUP BY pm.meta_value
						  HAVING COUNT(p.ID) > 1
						  ORDER BY duplicate_count DESC, pm.meta_value ASC";
				break;

			case 'title':
				// Find products with duplicate titles
				$query = "SELECT p.post_title as duplicate_value,
						  GROUP_CONCAT(p.ID) as product_ids,
						  COUNT(p.ID) as duplicate_count
						  FROM {$wpdb->posts} p
						  WHERE p.post_type IN ('product', 'product_variation')
						  AND p.post_status != 'trash'
						  GROUP BY p.post_title
						  HAVING COUNT(p.ID) > 1
						  ORDER BY duplicate_count DESC, p.post_title ASC";
				break;

			case 'barcode':
				// Find products with duplicate barcodes (any barcode type)
				$query = "SELECT pm.meta_value as duplicate_value,
						  GROUP_CONCAT(p.ID) as product_ids,
						  COUNT(p.ID) as duplicate_count
						  FROM {$wpdb->posts} p
						  INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
						  WHERE p.post_type IN ('product', 'product_variation')
						  AND p.post_status != 'trash'
						  AND pm.meta_key IN ('_barcode', '_ean', '_upc', '_gtin', '_isbn', '_mpn')
						  AND pm.meta_value != ''
						  GROUP BY pm.meta_value
						  HAVING COUNT(p.ID) > 1
						  ORDER BY duplicate_count DESC, pm.meta_value ASC";
				break;

			case 'gtin':
				$query = "SELECT pm.meta_value as duplicate_value,
						  GROUP_CONCAT(p.ID) as product_ids,
						  COUNT(p.ID) as duplicate_count
						  FROM {$wpdb->posts} p
						  INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
						  WHERE p.post_type IN ('product', 'product_variation')
						  AND p.post_status != 'trash'
						  AND pm.meta_key = '_gtin'
						  AND pm.meta_value != ''
						  GROUP BY pm.meta_value
						  HAVING COUNT(p.ID) > 1
						  ORDER BY duplicate_count DESC, pm.meta_value ASC";
				break;

			case 'ean':
				$query = "SELECT pm.meta_value as duplicate_value,
						  GROUP_CONCAT(p.ID) as product_ids,
						  COUNT(p.ID) as duplicate_count
						  FROM {$wpdb->posts} p
						  INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
						  WHERE p.post_type IN ('product', 'product_variation')
						  AND p.post_status != 'trash'
						  AND pm.meta_key = '_ean'
						  AND pm.meta_value != ''
						  GROUP BY pm.meta_value
						  HAVING COUNT(p.ID) > 1
						  ORDER BY duplicate_count DESC, pm.meta_value ASC";
				break;

			case 'upc':
				$query = "SELECT pm.meta_value as duplicate_value,
						  GROUP_CONCAT(p.ID) as product_ids,
						  COUNT(p.ID) as duplicate_count
						  FROM {$wpdb->posts} p
						  INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
						  WHERE p.post_type IN ('product', 'product_variation')
						  AND p.post_status != 'trash'
						  AND pm.meta_key = '_upc'
						  AND pm.meta_value != ''
						  GROUP BY pm.meta_value
						  HAVING COUNT(p.ID) > 1
						  ORDER BY duplicate_count DESC, pm.meta_value ASC";
				break;

			case 'isbn':
				$query = "SELECT pm.meta_value as duplicate_value,
						  GROUP_CONCAT(p.ID) as product_ids,
						  COUNT(p.ID) as duplicate_count
						  FROM {$wpdb->posts} p
						  INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
						  WHERE p.post_type IN ('product', 'product_variation')
						  AND p.post_status != 'trash'
						  AND pm.meta_key = '_isbn'
						  AND pm.meta_value != ''
						  GROUP BY pm.meta_value
						  HAVING COUNT(p.ID) > 1
						  ORDER BY duplicate_count DESC, pm.meta_value ASC";
				break;

			case 'mpn':
				$query = "SELECT pm.meta_value as duplicate_value,
						  GROUP_CONCAT(p.ID) as product_ids,
						  COUNT(p.ID) as duplicate_count
						  FROM {$wpdb->posts} p
						  INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
						  WHERE p.post_type IN ('product', 'product_variation')
						  AND p.post_status != 'trash'
						  AND pm.meta_key = '_mpn'
						  AND pm.meta_value != ''
						  GROUP BY pm.meta_value
						  HAVING COUNT(p.ID) > 1
						  ORDER BY duplicate_count DESC, pm.meta_value ASC";
				break;

			case 'all':
				// Scan all duplicate types and combine results
				$all_duplicates = array();
				
				// Check SKU duplicates
				$sku_dups = $this->find_duplicates( 'sku', 1, 100 );
				if ( ! empty( $sku_dups['groups'] ) ) {
					foreach ( $sku_dups['groups'] as $group ) {
						$group['type'] = 'SKU';
						$all_duplicates[] = $group;
					}
				}
				
				// Check title duplicates
				$title_dups = $this->find_duplicates( 'title', 1, 100 );
				if ( ! empty( $title_dups['groups'] ) ) {
					foreach ( $title_dups['groups'] as $group ) {
						$group['type'] = 'Title';
						$all_duplicates[] = $group;
					}
				}
				
				// Check barcode duplicates
				$barcode_dups = $this->find_duplicates( 'barcode', 1, 100 );
				if ( ! empty( $barcode_dups['groups'] ) ) {
					foreach ( $barcode_dups['groups'] as $group ) {
						$group['type'] = 'Barcode';
						$all_duplicates[] = $group;
					}
				}
				
				return array(
					'groups' => $all_duplicates,
					'total'  => count( $all_duplicates ),
					'pages'  => 1
				);
				break;

			default:
				return array( 'groups' => array(), 'total' => 0, 'pages' => 0 );
		}

		// Get total count
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Dynamic query based on criteria
		$count_results = $wpdb->get_results( $query );
		$total_groups = count( $count_results );

		// Add pagination to query
		$query .= $wpdb->prepare( " LIMIT %d OFFSET %d", $per_page, $offset );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Dynamic query based on criteria
		$results = $wpdb->get_results( $query );

		if ( $results ) {
			foreach ( $results as $group ) {
				$product_ids = explode( ',', $group->product_ids );
				$products_data = array();

				foreach ( $product_ids as $product_id ) {
					$product = wc_get_product( $product_id );
					if ( ! $product ) {
						continue;
					}

					$products_data[] = array(
						'id'          => $product_id,
						'title'       => $product->get_name(),
						'sku'         => $product->get_sku(),
						'price'       => $product->get_price(),
						'stock'       => $product->get_stock_quantity(),
						'type'        => $product->get_type(),
						'status'      => $product->get_status(),
						'created'     => $product->get_date_created() ? $product->get_date_created()->date( 'Y-m-d H:i:s' ) : '',
						'modified'    => $product->get_date_modified() ? $product->get_date_modified()->date( 'Y-m-d H:i:s' ) : '',
						'edit_link'   => admin_url( 'post.php?post=' . $product_id . '&action=edit' ),
						'view_link'   => $product->get_permalink()
					);
				}

				// Sort products by creation date (keep oldest first)
				usort( $products_data, function( $a, $b ) {
					return strcmp( $a['created'], $b['created'] );
				});

				$duplicates[] = array(
					'value'    => $group->duplicate_value,
					'count'    => $group->duplicate_count,
					'products' => $products_data
				);
			}
		}

		return array(
			'groups' => $duplicates,
			'total'  => $total_groups,
			'pages'  => ceil( $total_groups / $per_page )
		);
	}

	/**
	 * Delete products
	 *
	 * @param array  $product_ids Product IDs to delete
	 * @param bool   $force_delete Force delete instead of trash
	 * @return array Result with success count and errors
	 */
	public function delete_products( $product_ids, $force_delete = false ) {
		$deleted = 0;
		$errors = array();

		if ( ! is_array( $product_ids ) ) {
			$product_ids = array( $product_ids );
		}

		foreach ( $product_ids as $product_id ) {
			$product = wc_get_product( $product_id );
			
			if ( ! $product ) {
				/* translators: %d: product ID */
				$errors[] = sprintf( __( 'Product #%d not found', 'data-cleanup-for-woocommerce' ), $product_id );
				continue;
			}

			// Check if it's a variable product with variations
			if ( $product->is_type( 'variable' ) && $product->has_child() ) {
				$variations = $product->get_children();
				foreach ( $variations as $variation_id ) {
					wp_delete_post( $variation_id, $force_delete );
				}
			}

			// Delete the product
			if ( wp_delete_post( $product_id, $force_delete ) ) {
				$deleted++;
			} else {
				/* translators: %d: product ID */
				$errors[] = sprintf( __( 'Failed to delete product #%d', 'data-cleanup-for-woocommerce' ), $product_id );
			}
		}

		return array(
			'deleted' => $deleted,
			'errors'  => $errors
		);
	}

	/**
	 * Delete duplicate products keeping the oldest/newest
	 *
	 * @param array  $duplicate_groups Array of duplicate groups
	 * @param string $keep            Which product to keep (oldest/newest)
	 * @param bool   $force_delete    Force delete instead of trash
	 * @return array
	 */
	public function delete_duplicates_bulk( $duplicate_groups, $keep = 'oldest', $force_delete = false ) {
		$total_deleted = 0;
		$errors = array();

		foreach ( $duplicate_groups as $group ) {
			if ( empty( $group['products'] ) || count( $group['products'] ) < 2 ) {
				continue;
			}

			$products = $group['products'];

			// Sort by creation date
			usort( $products, function( $a, $b ) use ( $keep ) {
				if ( $keep === 'newest' ) {
					return strcmp( $b['created'], $a['created'] );
				}
				return strcmp( $a['created'], $b['created'] );
			});

			// Keep first one, delete the rest
			$to_delete = array_slice( $products, 1 );
			$delete_ids = array_column( $to_delete, 'id' );

			$result = $this->delete_products( $delete_ids, $force_delete );
			$total_deleted += $result['deleted'];
			$errors = array_merge( $errors, $result['errors'] );
		}

		return array(
			'deleted' => $total_deleted,
			'errors'  => $errors
		);
	}

	/**
	 * Get product statistics
	 *
	 * @return array
	 */
	public function get_statistics() {
		global $wpdb;

		$stats = array();

		// Total products
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time statistics query
		$stats['total_products'] = $wpdb->get_var(
			"SELECT COUNT(ID) FROM {$wpdb->posts} 
			 WHERE post_type IN ('product', 'product_variation') 
			 AND post_status != 'trash'"
		);

		// Products with SKU
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time statistics query
		$stats['products_with_sku'] = $wpdb->get_var(
			"SELECT COUNT(DISTINCT p.ID) 
			 FROM {$wpdb->posts} p
			 INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
			 WHERE p.post_type IN ('product', 'product_variation')
			 AND p.post_status != 'trash'
			 AND pm.meta_key = '_sku'
			 AND pm.meta_value != ''"
		);

		// Duplicate SKUs count
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time statistics query
		$stats['duplicate_skus'] = $wpdb->get_var(
			"SELECT COUNT(*) FROM (
				SELECT pm.meta_value
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
				WHERE p.post_type IN ('product', 'product_variation')
				AND p.post_status != 'trash'
				AND pm.meta_key = '_sku'
				AND pm.meta_value != ''
				GROUP BY pm.meta_value
				HAVING COUNT(p.ID) > 1
			) as dup"
		);

		// Duplicate titles count - only count main products, not variations
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time statistics query
		$stats['duplicate_titles'] = $wpdb->get_var(
			"SELECT COUNT(*) FROM (
				SELECT p.post_title
				FROM {$wpdb->posts} p
				WHERE p.post_type = 'product'
				AND p.post_status != 'trash'
				GROUP BY p.post_title
				HAVING COUNT(p.ID) > 1
			) as dup"
		);

		// Products without SKU
		$stats['products_without_sku'] = $stats['total_products'] - $stats['products_with_sku'];

		return $stats;
	}

	/**
	 * Quick edit product SKU
	 *
	 * @param int    $product_id Product ID
	 * @param string $new_sku    New SKU value
	 * @return bool|WP_Error
	 */
	public function update_product_sku( $product_id, $new_sku ) {
		$product = wc_get_product( $product_id );
		
		if ( ! $product ) {
			return new WP_Error( 'product_not_found', __( 'Product not found', 'data-cleanup-for-woocommerce' ) );
		}

		// Check if SKU already exists
		if ( ! empty( $new_sku ) ) {
			$existing_id = wc_get_product_id_by_sku( $new_sku );
			if ( $existing_id && $existing_id !== $product_id ) {
				return new WP_Error( 'sku_exists', __( 'SKU already exists for another product', 'data-cleanup-for-woocommerce' ) );
			}
		}

		$product->set_sku( $new_sku );
		$product->save();

		return true;
	}

	/**
	 * Find all duplicates at once (SKU, Title, Barcode)
	 * Returns a flat list of duplicate products with type information
	 *
	 * @return array
	 */
	public function find_all_duplicates() {
		global $wpdb;
		
		$all_duplicates = array();
		
		// Find SKU duplicates
		$sku_query = "SELECT pm.meta_value as duplicate_value, 
					  GROUP_CONCAT(p.ID) as product_ids
					  FROM {$wpdb->posts} p
					  INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
					  WHERE p.post_type IN ('product', 'product_variation')
					  AND p.post_status != 'trash'
					  AND pm.meta_key = '_sku'
					  AND pm.meta_value != ''
					  GROUP BY pm.meta_value
					  HAVING COUNT(p.ID) > 1";
		
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Static query without user input
		$sku_results = $wpdb->get_results( $sku_query );
		
		if ( ! empty( $sku_results ) ) {
			foreach ( $sku_results as $group ) {
				$product_ids = explode( ',', $group->product_ids );
				foreach ( $product_ids as $product_id ) {
									$product = wc_get_product( $product_id );
				if ( $product ) {
					// Get the correct edit link based on product type
					$edit_link = admin_url( 'post.php?post=' . $product_id . '&action=edit' );
					if ( $product->is_type( 'variation' ) ) {
						$parent_id = $product->get_parent_id();
						$edit_link = admin_url( 'post.php?post=' . $parent_id . '&action=edit' );
					}
					
					// Build product name with parent info for variations
					$product_name = $product->get_name();
					if ( $product->is_type( 'variation' ) ) {
						$parent = wc_get_product( $product->get_parent_id() );
						if ( $parent ) {
							$product_name = $parent->get_name() . ' - ' . $product_name;
						}
					}
					
					$all_duplicates[] = array(
						'id' => $product_id,
						'name' => $product_name,
						'sku' => $product->get_sku(),
						'type' => 'sku',
						'edit_link' => $edit_link,
						'is_variation' => $product->is_type( 'variation' ),
						'product_type' => $product->get_type()
					);
				}
				}
			}
		}
		
		// Find Title duplicates - only check main products, not variations
		// Variations naturally share their parent's title, which is expected
		$title_query = "SELECT p.post_title as duplicate_value,
						GROUP_CONCAT(p.ID) as product_ids
						FROM {$wpdb->posts} p
						WHERE p.post_type = 'product'
						AND p.post_status != 'trash'
						GROUP BY p.post_title
						HAVING COUNT(p.ID) > 1";
		
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Static query without user input
		$title_results = $wpdb->get_results( $title_query );
		
		if ( ! empty( $title_results ) ) {
			foreach ( $title_results as $group ) {
				$product_ids = explode( ',', $group->product_ids );
				foreach ( $product_ids as $product_id ) {
					$product = wc_get_product( $product_id );
					if ( $product ) {
						// Simple products and variable products only - no variations here
						$edit_link = admin_url( 'post.php?post=' . $product_id . '&action=edit' );
						
						$all_duplicates[] = array(
							'id' => $product_id,
							'name' => $product->get_name(),
							'sku' => $product->get_sku(),
							'type' => 'title',
							'edit_link' => $edit_link,
							'is_variation' => false,
							'product_type' => $product->get_type()
						);
					}
				}
			}
		}
		
		// Find Barcode duplicates
		$barcode_query = "SELECT pm.meta_value as duplicate_value,
						  GROUP_CONCAT(p.ID) as product_ids
						  FROM {$wpdb->posts} p
						  INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
						  WHERE p.post_type IN ('product', 'product_variation')
						  AND p.post_status != 'trash'
						  AND pm.meta_key IN ('_barcode', '_ean', '_upc', '_gtin', '_isbn', '_mpn')
						  AND pm.meta_value != ''
						  GROUP BY pm.meta_value
						  HAVING COUNT(p.ID) > 1";
		
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Static query without user input
		$barcode_results = $wpdb->get_results( $barcode_query );
		
		if ( ! empty( $barcode_results ) ) {
			foreach ( $barcode_results as $group ) {
				$product_ids = explode( ',', $group->product_ids );
				foreach ( $product_ids as $product_id ) {
									$product = wc_get_product( $product_id );
				if ( $product ) {
					// Get the correct edit link based on product type
					$edit_link = admin_url( 'post.php?post=' . $product_id . '&action=edit' );
					if ( $product->is_type( 'variation' ) ) {
						$parent_id = $product->get_parent_id();
						$edit_link = admin_url( 'post.php?post=' . $parent_id . '&action=edit' );
					}
					
					// Build product name with parent info for variations
					$product_name = $product->get_name();
					if ( $product->is_type( 'variation' ) ) {
						$parent = wc_get_product( $product->get_parent_id() );
						if ( $parent ) {
							$product_name = $parent->get_name() . ' - ' . $product_name;
						}
					}
					
					$all_duplicates[] = array(
						'id' => $product_id,
						'name' => $product_name,
						'sku' => $product->get_sku(),
						'type' => 'barcode',
						'edit_link' => $edit_link,
						'is_variation' => $product->is_type( 'variation' ),
						'product_type' => $product->get_type()
					);
				}
				}
			}
		}
		
		// Remove duplicates where same product appears in multiple types
		$seen = array();
		$unique_duplicates = array();
		foreach ( $all_duplicates as $item ) {
			$key = $item['type'] . '_' . $item['id'];
			if ( ! isset( $seen[$key] ) ) {
				$unique_duplicates[] = $item;
				$seen[$key] = true;
			}
		}
		
		return array(
			'products' => $unique_duplicates,
			'total' => count( $unique_duplicates )
		);
	}
}
