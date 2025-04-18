<?php

/*
 * This file is part of WooCommerce Product Search Admin plugin for WordPress.
 * (c) Webkonsulenterne <contact@webkonsulenterne.dk>
 * This source file is subject to the GPLv2 license that is bundled
 * with this source code in the file LICENSE.
 */

namespace WC_Product_Search_Admin\Admin;

use WC_Product_Search_Admin\AlgoliaSearch\AlgoliaException;
use WC_Product_Search_Admin\Options;
use WC_Product_Search_Admin\Products_Index;
use WC_Product_Search_Admin\Plugin;

class Ajax_Reindex
{
    /**
     * @var Products_Index
     */
    private $products_index;

    /**
     * @var Options
     */
    private $options;

    /**
     * @param Products_Index $products_index
     * @param Options      $options
     */
    public function __construct(Products_Index $products_index, Options $options)
    {
        $this->options = $options;
        $this->products_index = $products_index;

        add_action('wp_ajax_wc_psa_reindex', array( $this, 're_index' ));
    }

    public function re_index()
    {
        if (! isset($_POST['_wpnonce'])
            || ! wp_verify_nonce($_POST['_wpnonce'], 'wc_psa_reindex')) {
            wp_send_json_error(array(
                'message' => __('Security check failed.', 'wc-product-search-admin'),
            ));
        }

        $page = isset($_POST['page']) ? (int) $_POST['page'] : 1;
        $per_page = 800;

        try {
            error_log('WC PSA re_index: Starting page ' . $page);

            // Only clear index and push settings on first page
            if ($page === 1) {
                error_log('WC PSA re_index: About to call clear() on products_index');
                $this->products_index->clear();
                error_log('WC PSA re_index: About to call pushSettings() on products_index');
                $this->products_index->pushSettings();
                error_log('WC PSA re_index: Cleared index and pushed settings');
            }

            error_log('WC PSA re_index: per_page = ' . $per_page);

            // Get total products count for progress tracking
            $total_pages = $this->products_index->getTotalPagesCount($per_page);
            $total_products = $total_pages * $per_page; // Approximate total
            error_log('WC PSA re_index: total_pages = ' . $total_pages . ', total_products = ' . $total_products);

            // Push records for current page
            $records_pushed = $this->products_index->pushRecords($page, $per_page);
            error_log('WC PSA re_index: pushed ' . $records_pushed . ' records for page ' . $page);

            wp_send_json(array(
                'success' => true,
                'recordsPushedCount' => $records_pushed,
                'totalPagesCount' => $total_pages,
                'totalProductsCount' => $total_products,
                'currentPage' => $page
            ));

        } catch (\Exception $e) {
            error_log('WC PSA re_index error: ' . $e->getMessage());
            wp_send_json_error(array(
                'message' => $e->getMessage(),
            ));
        }
    }
}
