<?php

/*
 * This file is part of WooCommerce Product Search Admin plugin for WordPress.
 * (c) Webkonsulenterne <contact@webkonsulenterne.dk>
 * This source file is subject to the GPLv2 license that is bundled
 * with this source code in the file LICENSE.
 */

namespace WC_Product_Search_Admin;

class Options
{
    public function has_algolia_account_settings()
    {
        $app_id         = $this->get_algolia_app_id();
        $search_api_key = $this->get_algolia_search_api_key();
        $admin_api_key  = $this->get_algolia_admin_api_key();
        return ! empty($app_id)
            && ! empty($search_api_key)
            && ! empty($admin_api_key);
    }

    public function clear_algolia_account_settings()
    {
        update_option('wc_psa_alg_app_id', '');
        update_option('wc_psa_alg_search_api_key', '');
        update_option('wc_psa_alg_admin_api_key', '');
    }

    public function get_algolia_app_id()
    {
        return defined('WC_PSA_ALGOLIA_APPLICATION_ID') ? WC_PSA_ALGOLIA_APPLICATION_ID : get_option('wc_psa_alg_app_id', '');
    }

    public function get_algolia_search_api_key()
    {
        return defined('WC_PSA_ALGOLIA_SEARCH_API_KEY') ? WC_PSA_ALGOLIA_SEARCH_API_KEY : get_option('wc_psa_alg_search_api_key', '');
    }

    public function get_algolia_admin_api_key()
    {
        return defined('WC_PSA_ALGOLIA_ADMIN_API_KEY') ? WC_PSA_ALGOLIA_ADMIN_API_KEY : get_option('wc_psa_alg_admin_api_key', '');
    }

    public function set_algolia_account_settings($app_id, $search_key, $admin_key)
    {
        $app_id = defined('WC_PSA_ALGOLIA_APPLICATION_ID') ? WC_PSA_ALGOLIA_APPLICATION_ID : trim($app_id);
        $this->assert_not_empty($app_id, 'Algolia application ID');

        $search_key = defined('WC_PSA_ALGOLIA_SEARCH_API_KEY') ? WC_PSA_ALGOLIA_SEARCH_API_KEY : trim($search_key);
        $this->assert_not_empty($search_key, 'Algolia search only API key');

        $admin_key = defined('WC_PSA_ALGOLIA_ADMIN_API_KEY') ? WC_PSA_ALGOLIA_ADMIN_API_KEY : trim($admin_key);
        $this->assert_not_empty($admin_key, 'Algolia admin API key');

        if (! defined('WC_PSA_ALGOLIA_APPLICATION_ID')) {
            update_option('wc_psa_alg_app_id', $app_id);
        }

        if (! defined('WC_PSA_ALGOLIA_SEARCH_API_KEY')) {
            update_option('wc_psa_alg_search_api_key', $search_key);
        }

        if (! defined('WC_PSA_ALGOLIA_ADMIN_API_KEY')) {
            update_option('wc_psa_alg_admin_api_key', $admin_key);
        }
    }

    public function get_products_index_name()
    {
        return defined('WC_PSA_PRODUCTS_INDEX_NAME') ? WC_PSA_PRODUCTS_INDEX_NAME : get_option('wc_psa_products_index_name', 'wc_products');
    }

    public function set_products_index_name($products_index_name)
    {
        $products_index_name = defined('WC_PSA_PRODUCTS_INDEX_NAME') ? WC_PSA_PRODUCTS_INDEX_NAME : trim((string) $products_index_name);
        $this->assert_not_empty($products_index_name, 'Products index name');

        if (! defined('WC_PSA_PRODUCTS_INDEX_NAME')) {
            update_option('wc_psa_products_index_name', $products_index_name);
        }
    }

    /**
     * @return int
     */
    public function get_products_to_index_per_batch_count()
    {
        return (int) (defined('WC_PSA_PRODUCTS_PER_BATCH') ? WC_PSA_PRODUCTS_PER_BATCH : get_option('wc_psa_products_per_batch', 500));
    }

    public function set_products_to_index_per_batch_count($per_batch)
    {
        if (defined('WC_PSA_PRODUCTS_PER_BATCH') && (int) WC_PSA_PRODUCTS_PER_BATCH <= 0) {
            throw new \InvalidArgumentException('Products to index per batch should be greater than 0.');
        }

        $per_batch = (int) $per_batch;
        if ($per_batch <= 0) {
            $per_batch = 500;
        }

        if (! defined('WC_PSA_PRODUCTS_INDEX_NAME')) {
            update_option('wc_psa_products_per_batch', $per_batch);
        }
    }

    private function assert_not_empty($value, $attribute_name)
    {
        if (strlen($value) === 0) {
            throw new \InvalidArgumentException($attribute_name . ' should not be empty.');
        }
    }
}
