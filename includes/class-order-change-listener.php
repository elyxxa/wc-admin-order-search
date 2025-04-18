<?php

/*
 * This file is part of WooCommerce Order Search Admin plugin for WordPress.
 * (c) Raymond Rutjes <raymond.rutjes@gmail.com>
 * This source file is subject to the GPLv2 license that is bundled
 * with this source code in the file LICENSE.
 */

namespace WC_Order_Search_Admin;

use WC_Order_Search_Admin\AlgoliaSearch\AlgoliaException;

class Order_Change_Listener
{
    /**
     * @var Orders_Index
     */
    private $orders_index;

    /**
     * @var bool
     */
    private $hpos_enabled = false;

    /**
     * @param Orders_Index $orders_index
     */
    public function __construct(Orders_Index $orders_index)
    {
        $this->orders_index = $orders_index;

        // Check if HPOS is enabled
        if (class_exists('Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController')) {
            try {
                $this->hpos_enabled = wc_get_container()->get(\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class)->custom_orders_table_usage_is_enabled();
            } catch (\Exception $e) {
                error_log('WC OSA Order_Change_Listener: Could not determine HPOS status - ' . $e->getMessage());
            }
        }

        // Legacy hooks for non-HPOS
        add_action('save_post', array( $this, 'push_order_records' ), 10, 2);
        add_action('before_delete_post', array( $this, 'delete_order_records' ));
        add_action('wp_trash_post', array( $this, 'delete_order_records' ));

        // HPOS hooks
        if ($this->hpos_enabled) {
            add_action('woocommerce_order_object_updated_props', array( $this, 'push_order_records_hpos' ), 10, 1);
            add_action('woocommerce_new_order', array( $this, 'push_order_records_by_id' ), 10, 1);
            add_action('woocommerce_update_order', array( $this, 'push_order_records_by_id' ), 10, 1);
            add_action('woocommerce_trash_order', array( $this, 'delete_order_records' ));
            add_action('woocommerce_delete_order', array( $this, 'delete_order_records' ));
        }
    }

    /**
     * @param mixed $post_id
     * @param mixed $post
     */
    public function push_order_records($post_id, $post)
    {
        if ('shop_order' !== $post->post_type
            || 'auto-draft' === $post->post_status
            || 'trash' === $post->post_status
        ) {
            return;
        }

        $order = wc_get_order($post_id);
        try {
            $this->orders_index->pushRecordsForOrder($order);
        } catch (AlgoliaException $exception) {
            error_log($exception->getMessage()); // @codingStandardsIgnoreLine
        }
    }

    /**
     * Push order records when an order is updated via HPOS
     *
     * @param \WC_Order $order
     */
    public function push_order_records_hpos($order)
    {
        if ($order->get_status() === 'auto-draft' || $order->get_status() === 'trash') {
            return;
        }

        try {
            $this->orders_index->pushRecordsForOrder($order);
        } catch (AlgoliaException $exception) {
            error_log('WC OSA push_order_records_hpos: ' . $exception->getMessage());
        }
    }

    /**
     * Push order records when a new order is created or updated (for HPOS)
     *
     * @param int $order_id
     */
    public function push_order_records_by_id($order_id)
    {
        $order = wc_get_order($order_id);

        if (! $order || $order->get_status() === 'auto-draft' || $order->get_status() === 'trash') {
            return;
        }

        try {
            $this->orders_index->pushRecordsForOrder($order);
        } catch (AlgoliaException $exception) {
            error_log('WC OSA push_order_records_by_id: ' . $exception->getMessage());
        }
    }

    /**
     * Delete order records from Algolia
     *
     * @param int $post_id_or_order_id
     */
    public function delete_order_records($post_id_or_order_id)
    {
        if ($this->hpos_enabled) {
            // For HPOS, we already have the order ID
            $order_id = $post_id_or_order_id;
        } else {
            // For non-HPOS, we need to check if this is a shop_order post
            $post = get_post($post_id_or_order_id);
            if (!$post || 'shop_order' !== $post->post_type) {
                return;
            }
            $order_id = $post->ID;
        }

        try {
            $this->orders_index->deleteRecordsByOrderId($order_id);
        } catch (AlgoliaException $exception) {
            error_log('WC OSA delete_order_records: ' . $exception->getMessage());
        }
    }
}
