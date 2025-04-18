<?php

/*
 * This file is part of WooCommerce Product Search Admin plugin for WordPress.
 * (c) Webkonsulenterne <contact@webkonsulenterne.dk>
 * This source file is subject to the GPLv2 license that is bundled
 * with this source code in the file LICENSE.
 */

namespace WC_Product_Search_Admin;

use WC_Product_Search_Admin\AlgoliaSearch\AlgoliaException;

class Product_Change_Listener
{
    /**
     * @var Products_Index
     */
    private $products_index;

    /**
     * @param Products_Index $products_index
     */
    public function __construct(Products_Index $products_index)
    {
        $this->products_index = $products_index;

        // Post actions
        add_action('save_post', array($this, 'push_product_records'), 10, 2);
        add_action('before_delete_post', array($this, 'delete_product_records'));
        add_action('wp_trash_post', array($this, 'delete_product_records'));

        // Product-specific actions
        add_action('woocommerce_update_product', array($this, 'push_product_records_by_id'), 10);
        add_action('woocommerce_create_product', array($this, 'push_product_records_by_id'), 10);
        add_action('woocommerce_delete_product', array($this, 'delete_product_records'));
        add_action('woocommerce_trash_product', array($this, 'delete_product_records'));

        // Product variation actions
        add_action('woocommerce_save_product_variation', array($this, 'push_parent_product_records'), 10);
        add_action('woocommerce_delete_product_variation', array($this, 'push_parent_product_from_variation'), 10);
        add_action('woocommerce_trash_product_variation', array($this, 'push_parent_product_from_variation'), 10);

        // Product meta updates
        add_action('added_post_meta', array($this, 'on_product_meta_change'), 10, 4);
        add_action('updated_post_meta', array($this, 'on_product_meta_change'), 10, 4);
        add_action('deleted_post_meta', array($this, 'on_product_meta_change'), 10, 4);

        // Product terms updates
        add_action('set_object_terms', array($this, 'on_product_terms_change'), 10, 6);
    }

    /**
     * @param mixed $post_id
     * @param mixed $post
     */
    public function push_product_records($post_id, $post)
    {
        if ('product' !== $post->post_type
            || 'auto-draft' === $post->post_status
            || 'trash' === $post->post_status
        ) {
            return;
        }

        $product = wc_get_product($post_id);
        if (!$product) {
            return;
        }

        try {
            $this->products_index->pushRecordsForProduct($product);
        } catch (AlgoliaException $exception) {
            error_log('WC PSA push_product_records: ' . $exception->getMessage());
        }
    }

    /**
     * Push product records when a product is created or updated
     *
     * @param int $product_id
     */
    public function push_product_records_by_id($product_id)
    {
        $product = wc_get_product($product_id);

        if (!$product || 'auto-draft' === $product->get_status() || 'trash' === $product->get_status()) {
            return;
        }

        try {
            $this->products_index->pushRecordsForProduct($product);
        } catch (AlgoliaException $exception) {
            error_log('WC PSA push_product_records_by_id: ' . $exception->getMessage());
        }
    }

    /**
     * Push parent product records when a variation is saved
     *
     * @param int $variation_id
     */
    public function push_parent_product_records($variation_id)
    {
        $variation = wc_get_product($variation_id);
        if (!$variation || !$variation->is_type('variation')) {
            return;
        }

        $parent_id = $variation->get_parent_id();
        if (!$parent_id) {
            return;
        }

        $parent_product = wc_get_product($parent_id);
        if (!$parent_product) {
            return;
        }

        try {
            $this->products_index->pushRecordsForProduct($parent_product);
        } catch (AlgoliaException $exception) {
            error_log('WC PSA push_parent_product_records: ' . $exception->getMessage());
        }
    }

    /**
     * Push parent product records when a variation is deleted/trashed
     *
     * @param int $variation_id
     */
    public function push_parent_product_from_variation($variation_id)
    {
        $parent_id = wp_get_post_parent_id($variation_id);
        if (!$parent_id) {
            return;
        }

        $parent_product = wc_get_product($parent_id);
        if (!$parent_product) {
            return;
        }

        try {
            $this->products_index->pushRecordsForProduct($parent_product);
        } catch (AlgoliaException $exception) {
            error_log('WC PSA push_parent_product_from_variation: ' . $exception->getMessage());
        }
    }

    /**
     * Delete product records from Algolia
     *
     * @param int $post_id
     */
    public function delete_product_records($post_id)
    {
        $post = get_post($post_id);
        if (!$post || 'product' !== $post->post_type) {
            return;
        }

        try {
            $this->products_index->deleteRecordsByProductId($post_id);
        } catch (AlgoliaException $exception) {
            error_log('WC PSA delete_product_records: ' . $exception->getMessage());
        }
    }

    /**
     * Handle product meta changes
     *
     * @param int    $meta_id
     * @param int    $post_id
     * @param string $meta_key
     * @param mixed  $meta_value
     */
    public function on_product_meta_change($meta_id, $post_id, $meta_key, $meta_value)
    {
        $post = get_post($post_id);
        if (!$post || 'product' !== $post->post_type) {
            return;
        }

        // Ignore some internal meta keys
        if (strpos($meta_key, '_edit_') === 0 || strpos($meta_key, '_wp_') === 0) {
            return;
        }

        $product = wc_get_product($post_id);
        if (!$product) {
            return;
        }

        try {
            $this->products_index->pushRecordsForProduct($product);
        } catch (AlgoliaException $exception) {
            error_log('WC PSA on_product_meta_change: ' . $exception->getMessage());
        }
    }

    /**
     * Handle product terms changes (categories, tags, attributes)
     *
     * @param int    $object_id
     * @param array  $terms
     * @param array  $tt_ids
     * @param string $taxonomy
     * @param bool   $append
     * @param array  $old_tt_ids
     */
    public function on_product_terms_change($object_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids)
    {
        $post = get_post($object_id);
        if (!$post || 'product' !== $post->post_type) {
            return;
        }

        // Check if taxonomy is product-related
        if (!in_array($taxonomy, array('product_cat', 'product_tag')) && strpos($taxonomy, 'pa_') !== 0) {
            return;
        }

        $product = wc_get_product($object_id);
        if (!$product) {
            return;
        }

        try {
            $this->products_index->pushRecordsForProduct($product);
        } catch (AlgoliaException $exception) {
            error_log('WC PSA on_product_terms_change: ' . $exception->getMessage());
        }
    }
}
