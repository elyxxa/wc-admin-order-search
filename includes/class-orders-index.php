<?php

/*
 * This file is part of WooCommerce Order Search Admin plugin for WordPress.
 * (c) Raymond Rutjes <raymond.rutjes@gmail.com>
 * This source file is subject to the GPLv2 license that is bundled
 * with this source code in the file LICENSE.
 */

namespace WC_Order_Search_Admin;

use WC_Order_Search_Admin\Algolia\Index\Index;
use WC_Order_Search_Admin\Algolia\Index\IndexSettings;
use WC_Order_Search_Admin\Algolia\Index\RecordsProvider;
use WC_Order_Search_Admin\AlgoliaSearch\Client;

class Orders_Index extends Index implements RecordsProvider {

	/**
	 * @var string
	 */
	private $name;

	/**
	 * @var Client
	 */
	private $client;

	/**
	 * @param string $name
	 * @param Client $client
	 */
	public function __construct( $name, Client $client ) {
		$this->name   = $name;
		$this->client = $client;
	}

	/**
	 * @return string
	 */
	public function getName() {
		return $this->name;
	}

	/**
	 * @param string $order_id
	 */
	public function deleteRecordsByOrderId( $order_id ) {
		$this->getAlgoliaIndex()->deleteObject( (string) $order_id );
	}

	/**
	 * @param int $per_page
	 *
	 * @return int
	 */
	public function getTotalPagesCount( $per_page ) {
		// Check for HPOS
		$hpos_enabled = false;
		if (class_exists('Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController')) {
			try {
				$hpos_enabled = wc_get_container()->get(\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class)->custom_orders_table_usage_is_enabled();
			} catch (\Exception $e) {
				error_log('WC OSA getTotalPagesCount: Could not determine HPOS status - ' . $e->getMessage());
			}
		}

		if ($hpos_enabled) {
			// Use wc_get_orders for HPOS
			$query_args = array(
				'limit'    => $per_page,
				'offset'   => 0,
				'type'     => wc_get_order_types(),
				'status'   => array_keys(wc_get_order_statuses()),
				'paginate' => true,
			);

			try {
				$results = wc_get_orders($query_args);
				error_log('WC OSA getTotalPagesCount: HPOS found ' . $results->total . ' total orders, ' . $results->max_num_pages . ' pages');
				return (int) $results->max_num_pages;
			} catch (\Exception $e) {
				error_log('WC OSA getTotalPagesCount: Error querying HPOS - ' . $e->getMessage());
				return 0;
			}
		}

		// Fallback to WP_Query for non-HPOS
		$args = array(
			'post_type'      => wc_get_order_types(),
			'post_status'    => array_keys( wc_get_order_statuses() ),
			'posts_per_page' => (int) $per_page,
			'fields'         => 'ids',
			'no_found_rows'  => false,
		);

		$query = new \WP_Query( $args );
		error_log('WC OSA getTotalPagesCount: Posts query found ' . $query->found_posts . ' orders, ' . $query->max_num_pages . ' pages');

		return (int) $query->max_num_pages;
	}

	/**
	 * @param int $page
	 * @param int $per_page
	 *
	 * @return array
	 */
	public function getRecords( $page, $per_page ) {
		error_log('WC OSA getRecords: page=' . $page . ', per_page=' . $per_page);

		// Log HPOS status
		$hpos_enabled = false;
		if (class_exists('Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController')) {
			try {
				$hpos_enabled = wc_get_container()->get(\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class)->custom_orders_table_usage_is_enabled();
				error_log('WC OSA getRecords: HPOS is ' . ($hpos_enabled ? 'enabled' : 'disabled'));
			} catch (\Exception $e) {
				error_log('WC OSA getRecords: Could not determine HPOS status - ' . $e->getMessage());
			}
		} else {
			error_log('WC OSA getRecords: HPOS classes not available (WooCommerce < 7.1)');
		}

		// Calculate offset
		$offset = ($page - 1) * $per_page;

		if ($hpos_enabled) {
			// Use wc_get_orders for HPOS
			$query_args = array(
				'limit'    => $per_page,
				'offset'   => $offset,
				'type'     => wc_get_order_types(),
				'status'   => array_keys(wc_get_order_statuses()),
				'orderby'  => 'ID',
				'order'    => 'DESC',
				'paginate' => true,
			);

			error_log('WC OSA getRecords: Querying HPOS with args: ' . print_r($query_args, true));

			try {
				$results = wc_get_orders($query_args);
				$orders = $results->orders;
				$total_orders = $results->total;
				$total_pages = $results->max_num_pages;

				error_log('WC OSA getRecords: HPOS query found total_orders=' . $total_orders . ', total_pages=' . $total_pages);

				// Convert orders to records
				$records = array();
				foreach ($orders as $order) {
					$records = array_merge($records, $this->getRecordsForOrder($order));
				}

				error_log('WC OSA getRecords: Generated ' . count($records) . ' records from HPOS orders');
				return $records;

			} catch (\Exception $e) {
				error_log('WC OSA getRecords: Error querying orders with HPOS - ' . $e->getMessage());
				return array();
			}
		} else {
			// Fallback to posts table query for non-HPOS
			global $wpdb;
			$post_types = wc_get_order_types();
			$post_statuses = array_keys(wc_get_order_statuses());

			$type_placeholders = implode(',', array_fill(0, count($post_types), '%s'));
			$status_placeholders = implode(',', array_fill(0, count($post_statuses), '%s'));

			$sql = $wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts}
				WHERE post_type IN ($type_placeholders)
				AND post_status IN ($status_placeholders)
				ORDER BY ID DESC
				LIMIT %d OFFSET %d",
				array_merge($post_types, $post_statuses, array($per_page, $offset))
			);

			$order_ids = $wpdb->get_col($sql);

			// Log the count and sample IDs
			error_log('WC OSA getRecords: Found ' . count($order_ids) . ' order IDs for page ' . $page);
			if (count($order_ids) > 0) {
				error_log('WC OSA getRecords: Sample IDs: ' . implode(', ', array_slice($order_ids, 0, 5)) . (count($order_ids) > 5 ? '...' : ''));
			}

			// Create a proper WP_Query object for compatibility
			$args = array(
				'post_type'      => wc_get_order_types(),
				'post_status'    => array_keys(wc_get_order_statuses()),
				'posts_per_page' => -1,
				'post__in'       => empty($order_ids) ? array(0) : $order_ids,
				'orderby'        => 'post__in',
				'fields'         => 'all',
			);

			$query = new \WP_Query($args);
			error_log('WC OSA getRecords: Created WP_Query with ' . count($query->posts) . ' posts');

			$records = $this->getRecordsForQuery($query);
			error_log('WC OSA getRecords: Generated ' . count($records) . ' records');

			return $records;
		}
	}

	/**
	 * @param \WC_Abstract_Order $order
	 *
	 * @return int
	 */
	public function pushRecordsForOrder( \WC_Abstract_Order $order ) {
		$records             = $this->getRecordsForOrder( $order );
		$total_records_count = count( $records );
		if ( 0 === $total_records_count ) {
			return 0;
		}

		$this->getAlgoliaIndex()->addObjects( $records );

		return $total_records_count;
	}

	/**
	 * @param int $page
	 * @param int $per_page
	 * @param callable|null $batchCallback
	 *
	 * @return int
	 */
	public function pushRecords( $page, $per_page, $batchCallback = null ) {
		try {
			error_log('WC OSA pushRecords: Starting page ' . $page . ' with ' . $per_page . ' per page');

			$records = $this->getRecords( $page, $per_page );

			if ( empty( $records ) ) {
				error_log('WC OSA pushRecords: No records found for page ' . $page);
				return 0;
			}

			$this->getAlgoliaIndex()->addObjects( $records );

			if ( null !== $batchCallback ) {
				call_user_func( $batchCallback, $records, $page, $this->getTotalPagesCount( $per_page ) );
			}

			error_log('WC OSA pushRecords: Successfully pushed ' . count($records) . ' records for page ' . $page);
			return count( $records );
		} catch (\Exception $e) {
			error_log('WC OSA pushRecords error: ' . $e->getMessage());
			throw $e;
		}
	}

	/**
	 * @param mixed $id
	 *
	 * @return array
	 */
	public function getRecordsForId( $id ) {
		$factory = new \WC_Order_Factory();
		$order   = $factory->get_order( $id );

		if ( ! $order instanceof \WC_Abstract_Order ) {
			return array();
		}

		return $this->getRecordsForOrder( $order );
	}

	/**
	 * @return RecordsProvider
	 */
	protected function getRecordsProvider() {
		return $this;
	}

	/**
	 * @return IndexSettings
	 */
	protected function getSettings() {
		return new IndexSettings(
			array(
				'searchableAttributes'             => array(
					'id',
					'number',
					'customer.display_name',
					'customer.email',
					'billing.display_name',
					'shipping.display_name',
					'billing.email',
					'billing.phone',
					'billing.company',
					'shipping.company',
					'billing.address_1',
					'shipping.address_1',
					'billing.address_2',
					'shipping.address_2',
					'billing.city',
					'shipping.city',
					'billing.state',
					'shipping.state',
					'billing.postcode',
					'shipping.postcode',
					'billing.country',
					'shipping.country',
					'items.sku',
					'status_name',
					'order_total',
				),
				'disableTypoToleranceOnAttributes' => array(
					'id',
					'number',
					'items.sku',
					'billing.phone',
					'order_total',
					'billing.postcode',
					'shipping.postcode',
				),
				'customRanking'                    => array(
					'desc(date_timestamp)',
				),
				'attributesForFaceting'            => array(
					'customer.display_name',
					'type',
					'items.sku',
					'order_total',
				),
			)
		);
	}

	/**
	 * @return Client
	 */
	protected function getAlgoliaClient() {
		return $this->client;
	}

	/**
	 * @param array $args
	 *
	 * @return \WP_Query
	 */
	private function newQuery( array $args = array() ) {
		$default_args = array(
			'post_type'   => wc_get_order_types(),
			'post_status' => array_keys( wc_get_order_statuses() ),
		);

		$args = array_merge( $default_args, $args );

		// Simple logging without database queries
		error_log('WC OSA newQuery args: ' . print_r($args, true));

		$query = new \WP_Query( $args );

		// Log basic info
		error_log('WC OSA WP_Query found: ' . $query->found_posts . ' posts, max_num_pages: ' . $query->max_num_pages);

		return $query;
	}

	/**
	 * @param \WC_Abstract_Order $order
	 *
	 * @return array
	 */
	private function getRecordsForOrder( \WC_Abstract_Order $order ) {
		if ( ! defined( 'WC_VERSION' ) ) {
			return array();
		}

		if ( ! $order instanceof \WC_Order ) {
			// Only support default order type for now.
			return array();
		}

		// We are dealing with WC 3.x or later
		$date_created = $order->get_date_created();
		$date_created_timestamp = null !== $date_created ? $date_created->getTimestamp() : 0;
		$date_created_i18n = null !== $date_created ? $date_created->date_i18n( get_option( 'date_format' ) ) : '';

		$record = array(
			'objectID'              => (int) $order->get_id(),
			'id'                    => (int) $order->get_id(),
			'type'                  => $order->get_type(),
			'number'                => (string) $order->get_order_number(),
			'status'                => $order->get_status(),
			'status_name'           => wc_get_order_status_name( $order->get_status() ),
			'date_timestamp'        => $date_created_timestamp,
			'date_formatted'        => $date_created_i18n,
			'order_total'           => (float) $order->get_total(),
			'formatted_order_total' => $order->get_formatted_order_total(),
			'items_count'           => (int) $order->get_item_count(),
			'payment_method_title'  => $order->get_payment_method_title(),
			'shipping_method_title' => $order->get_shipping_method(),
		);

		// Add user info.
		$user = $order->get_user();
		if ( $user ) {
			$record['customer'] = array(
				'id'           => (int) $user->ID,
				'display_name' => $user->first_name . ' ' . $user->last_name,
				'email'        => $user->user_email,
			);
		}

		$billing_country = $order->get_billing_country();
		$billing_country = isset( WC()->countries->countries[ $billing_country ] ) ? WC()->countries->countries[ $billing_country ] : $billing_country;
		$record['billing'] = array(
			'display_name' => $order->get_formatted_billing_full_name(),
			'email'        => $order->get_billing_email(),
			'phone'        => $order->get_billing_phone(),
			'company'      => $order->get_billing_company(),
			'address_1'    => $order->get_billing_address_1(),
			'address_2'    => $order->get_billing_address_2(),
			'city'         => $order->get_billing_city(),
			'state'        => $order->get_billing_state(),
			'postcode'     => $order->get_billing_postcode(),
			'country'      => $billing_country,
		);

		$shipping_country = $order->get_shipping_country();
		$shipping_country = isset( WC()->countries->countries[ $shipping_country ] ) ? WC()->countries->countries[ $shipping_country ] : $shipping_country;
		$record['shipping'] = array(
			'display_name' => $order->get_formatted_shipping_full_name(),
			'company'      => $order->get_shipping_company(),
			'address_1'    => $order->get_shipping_address_1(),
			'address_2'    => $order->get_shipping_address_2(),
			'city'         => $order->get_shipping_city(),
			'state'        => $order->get_shipping_state(),
			'postcode'     => $order->get_shipping_postcode(),
			'country'      => $shipping_country,
		);

		// Add items using modern WooCommerce methods
		$record['items'] = array();
		foreach ( $order->get_items() as $item_id => $item ) {
			$product = $item->get_product();
			$record['items'][] = array(
				'id'   => (int) $item_id,
				'name' => apply_filters( 'woocommerce_order_item_name', $item->get_name(), $item, false ),
				'qty'  => (int) $item->get_quantity(),
				'sku'  => $product instanceof \WC_Product ? $product->get_sku() : '',
			);
		}

		return array( $record );
	}

	/**
	 * @param \WP_Query $query
	 *
	 * @return array
	 */
	private function getRecordsForQuery( \WP_Query $query ) {
		$records = array();
		$factory = new \WC_Order_Factory();

		$valid_orders = 0;

		foreach ( $query->posts as $post ) {
			$order = $factory->get_order( $post );
			if ( ! $order instanceof \WC_Abstract_Order ) {
				continue;
			}
			$valid_orders++;
			$records = array_merge( $records, $this->getRecordsForOrder( $order ) );
		}

		error_log('WC OSA getRecordsForQuery: Processed ' . count($query->posts) . ' posts, found ' . $valid_orders . ' valid orders');

		return $records;
	}
}
