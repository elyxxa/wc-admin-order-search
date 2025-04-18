<?php

/*
 * This file is part of WooCommerce Order Search Admin plugin for WordPress.
 * (c) Raymond Rutjes <raymond.rutjes@gmail.com>
 * This source file is subject to the GPLv2 license that is bundled
 * with this source code in the file LICENSE.
 */

namespace WC_Order_Search_Admin\Admin;

use WC_Order_Search_Admin\AlgoliaSearch\AlgoliaException;
use WC_Order_Search_Admin\Options;
use WC_Order_Search_Admin\Orders_Index;
use WC_Order_Search_Admin\Plugin;

class Ajax_Reindex {

	/**
	 * @var Orders_Index
	 */
	private $orders_index;

	/**
	 * @var Options
	 */
	private $options;

	/**
	 * @param Orders_Index $orders_index
	 * @param Options      $options
	 */
	public function __construct( Orders_Index $orders_index, Options $options ) {
		$this->options = $options;
		$this->orders_index = $orders_index;

		add_action( 'wp_ajax_wc_osa_reindex', array( $this, 're_index' ) );
	}

	public function re_index() {
		if ( ! isset( $_POST['_wpnonce'] )
			|| ! wp_verify_nonce( $_POST['_wpnonce'], 'wc_osa_reindex' ) ) {
			wp_send_json_error( array(
				'message' => __( 'Security check failed.', 'wc-order-search-admin' ),
			) );
		}

		$page = isset( $_POST['page'] ) ? (int) $_POST['page'] : 1;
		$per_page = 800;

		try {
			error_log('WC OSA re_index: Starting page ' . $page);

			// Only clear index and push settings on first page
			if ($page === 1) {
				$this->orders_index->clear();
				$this->orders_index->pushSettings();
				error_log('WC OSA re_index: Cleared index and pushed settings');
			}

			error_log('WC OSA re_index: per_page = ' . $per_page);

			// Get total orders count for progress tracking
			$total_pages = $this->orders_index->getTotalPagesCount($per_page);
			$total_orders = $total_pages * $per_page; // Approximate total
			error_log('WC OSA re_index: total_pages = ' . $total_pages . ', total_orders = ' . $total_orders);

			// Push records for current page
			$records_pushed = $this->orders_index->pushRecords($page, $per_page);
			error_log('WC OSA re_index: pushed ' . $records_pushed . ' records for page ' . $page);

			wp_send_json(array(
				'success' => true,
				'recordsPushedCount' => $records_pushed,
				'totalPagesCount' => $total_pages,
				'totalOrdersCount' => $total_orders,
				'currentPage' => $page
			));

		} catch ( \Exception $e ) {
			error_log('WC OSA re_index error: ' . $e->getMessage());
			wp_send_json_error( array(
				'message' => $e->getMessage(),
			) );
		}
	}
}
