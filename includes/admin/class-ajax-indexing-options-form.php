<?php

/*
 * This file is part of WooCommerce Product Search Admin plugin for WordPress.
 * (c) Webkonsulenterne <contact@webkonsulenterne.dk>
 * This source file is subject to the GPLv2 license that is bundled
 * with this source code in the file LICENSE.
 */

namespace WC_Product_Search_Admin\Admin;

use WC_Product_Search_Admin\Options;

class Ajax_Indexing_Options_Form
{
    /**
     * @var Options
     */
    private $options;

    /**
     * @param Options $options
     */
    public function __construct(Options $options)
    {
        error_log('Ajax_Indexing_Options_Form constructor called');
        $this->options = $options;

        add_action('wp_ajax_wc_psa_save_indexing_options', array($this, 'save_indexing_options'));
    }

    public function save_indexing_options()
    {
        error_log('save_indexing_options called');

        try {
            check_ajax_referer('save_indexing_options_nonce');
            error_log('Nonce check passed');

            if ((!isset($_POST['products_index_name']) && !defined('WC_PSA_PRODUCTS_INDEX_NAME')) ||
                (!isset($_POST['products_per_batch']) && !defined('WC_PSA_PRODUCTS_PER_BATCH'))) {
                error_log('Required parameters missing');
                wp_die('Hacker');
            }

            error_log('About to set products_index_name: ' . (isset($_POST['products_index_name']) ? $_POST['products_index_name'] : ''));
            $this->options->set_products_index_name(isset($_POST['products_index_name']) ? $_POST['products_index_name'] : '');
            error_log('Successfully set products_index_name');

            error_log('About to set products_per_batch: ' . (isset($_POST['products_per_batch']) ? $_POST['products_per_batch'] : '0'));
            $this->options->set_products_to_index_per_batch_count(isset($_POST['products_per_batch']) ? $_POST['products_per_batch'] : 0);
            error_log('Successfully set products_per_batch');

            $response = array(
                'success' => true,
                'message' => __('Your indexing options have been saved. If you changed the index name, you will need to re-index your products.', 'wc-product-search-admin'),
            );

            error_log('Sending JSON response: ' . json_encode($response));
            wp_send_json($response);
        } catch (\Exception $e) {
            error_log('Exception in save_indexing_options: ' . $e->getMessage());
            wp_send_json_error(array(
                'message' => $e->getMessage(),
            ));
        }
    }
}
