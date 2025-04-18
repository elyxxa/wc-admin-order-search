<?php

/*
 * This file is part of WooCommerce Product Search Admin plugin for WordPress.
 * (c) Webkonsulenterne <contact@webkonsulenterne.dk>
 * This source file is subject to the GPLv2 license that is bundled
 * with this source code in the file LICENSE.
 */

namespace WC_Product_Search_Admin;

use WC_Product_Search_Admin\Algolia\Index\Index;
use WC_Product_Search_Admin\Algolia\Index\IndexSettings;
use WC_Product_Search_Admin\Algolia\Index\RecordsProvider;
use WC_Product_Search_Admin\AlgoliaSearch\Client;

class Products_Index extends Index implements RecordsProvider
{
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
    public function __construct($name, Client $client)
    {
        $this->name   = $name;
        $this->client = $client;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $product_id
     */
    public function deleteRecordsByProductId($product_id)
    {
        $this->getAlgoliaIndex()->deleteObject((string) $product_id);
    }

    /**
     * @param int $per_page
     *
     * @return int
     */
    public function getTotalPagesCount($per_page)
    {
        $args = array(
            'post_type'      => 'product',
            'post_status'    => array('publish', 'pending', 'draft', 'private'),
            'posts_per_page' => (int) $per_page,
            'fields'         => 'ids',
            'no_found_rows'  => false,
        );

        $query = new \WP_Query($args);
        error_log('WC PSA getTotalPagesCount: Product query found ' . $query->found_posts . ' products, ' . $query->max_num_pages . ' pages');

        return (int) $query->max_num_pages;
    }

    /**
     * @param int $page
     * @param int $per_page
     *
     * @return array
     */
    public function getRecords($page, $per_page)
    {
        error_log('WC PSA getRecords: page=' . $page . ', per_page=' . $per_page);

        // Calculate offset
        $offset = ($page - 1) * $per_page;

        // Query products
        global $wpdb;
        $post_statuses = array('publish', 'pending', 'draft', 'private');
        $status_placeholders = implode(',', array_fill(0, count($post_statuses), '%s'));

        $sql = $wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts}
			WHERE post_type = 'product'
			AND post_status IN ($status_placeholders)
			ORDER BY ID DESC
			LIMIT %d OFFSET %d",
            array_merge($post_statuses, array($per_page, $offset))
        );

        $product_ids = $wpdb->get_col($sql);

        // Log the count and sample IDs
        error_log('WC PSA getRecords: Found ' . count($product_ids) . ' product IDs for page ' . $page);
        if (count($product_ids) > 0) {
            error_log('WC PSA getRecords: Sample IDs: ' . implode(', ', array_slice($product_ids, 0, 5)) . (count($product_ids) > 5 ? '...' : ''));
        }

        // Create a proper WP_Query object for compatibility
        $args = array(
            'post_type'      => 'product',
            'post_status'    => array('publish', 'pending', 'draft', 'private'),
            'posts_per_page' => -1,
            'post__in'       => empty($product_ids) ? array(0) : $product_ids,
            'orderby'        => 'post__in',
            'fields'         => 'all',
        );

        $query = new \WP_Query($args);
        error_log('WC PSA getRecords: Created WP_Query with ' . count($query->posts) . ' posts');

        $records = $this->getRecordsForQuery($query);
        error_log('WC PSA getRecords: Generated ' . count($records) . ' records');

        return $records;
    }

    /**
     * @param \WC_Product $product
     *
     * @return int
     */
    public function pushRecordsForProduct(\WC_Product $product)
    {
        $records             = $this->getRecordsForProduct($product);
        $total_records_count = count($records);
        if (0 === $total_records_count) {
            return 0;
        }

        $this->getAlgoliaIndex()->addObjects($records);

        return $total_records_count;
    }

    /**
     * @param int $page
     * @param int $per_page
     * @param callable|null $batchCallback
     *
     * @return int
     */
    public function pushRecords($page, $per_page, $batchCallback = null)
    {
        try {
            error_log('WC PSA pushRecords: Starting page ' . $page . ' with ' . $per_page . ' per page');

            $records = $this->getRecords($page, $per_page);

            if (empty($records)) {
                error_log('WC PSA pushRecords: No records found for page ' . $page);
                return 0;
            }

            $this->getAlgoliaIndex()->addObjects($records);

            if (null !== $batchCallback) {
                call_user_func($batchCallback, $records, $page, $this->getTotalPagesCount($per_page));
            }

            error_log('WC PSA pushRecords: Successfully pushed ' . count($records) . ' records for page ' . $page);

            return count($records);
        } catch (\Exception $e) {
            error_log('WC PSA pushRecords error: ' . $e->getMessage());
        }

        return 0;
    }

    /**
     * @param $id
     *
     * @return array
     */
    public function getRecordsForId($id)
    {
        $product = wc_get_product($id);
        if (! $product instanceof \WC_Product) {
            return array();
        }

        return $this->getRecordsForProduct($product);
    }

    /**
     * @inheritdoc
     */
    protected function getRecordsProvider()
    {
        return $this;
    }

    /**
     * @inheritdoc
     */
    protected function getSettings()
    {
        return (new IndexSettings())
            ->setAttributesForFaceting(array())
            ->setSearchableAttributes(array(
                'title',
                'sku',
                'ean',
                'unordered(description)',
                'unordered(short_description)',
                'unordered(categories)',
                'unordered(tags)',
                'meta_data.value'
            ))
            ->setDisableTypoToleranceOnAttributes(array())
            ->setCustomRanking(array('desc(post_date)'))
            ->setReplicas(array());
    }

    /**
     * @inheritdoc
     */
    protected function getAlgoliaClient()
    {
        return $this->client;
    }

    /**
     * @param array $args
     *
     * @return \WP_Query
     */
    private function newQuery(array $args = array())
    {
        $default_args = array(
            'post_type'      => 'product',
            'post_status'    => array('publish', 'pending', 'draft', 'private'),
            'posts_per_page' => -1,
        );

        $args = array_merge($default_args, $args);

        return new \WP_Query($args);
    }

    /**
     * @param \WC_Product $product
     *
     * @return array
     */
    private function getRecordsForProduct(\WC_Product $product)
    {
        $record = array();

        // Get product ID
        $record['objectID'] = (string) $product->get_id();
        $record['id'] = (int) $product->get_id();

        // General product info
        $record['title'] = $product->get_name();
        $record['status'] = $product->get_status();
        $record['sku'] = $product->get_sku();
        $record['ean'] = get_post_meta($product->get_id(), '_alg_ean', true);
        $record['description'] = wp_strip_all_tags($product->get_description());
        $record['short_description'] = wp_strip_all_tags($product->get_short_description());
        $record['permalink'] = get_permalink($product->get_id());
        $record['post_date'] = get_post_field('post_date', $product->get_id());
        $record['featured'] = $product->is_featured();

        // Product type and visibility
        $record['type'] = $product->get_type();
        $record['catalog_visibility'] = $product->get_catalog_visibility();

        // Price information
        $record['price'] = $product->get_price();
        $record['regular_price'] = $product->get_regular_price();
        $record['sale_price'] = $product->get_sale_price();
        $record['on_sale'] = $product->is_on_sale();
        $record['total_sales'] = (int) get_post_meta($product->get_id(), 'total_sales', true);

        // Stock information
        $record['stock_status'] = $product->get_stock_status();
        $record['stock_quantity'] = $product->get_stock_quantity();
        $record['manage_stock'] = $product->get_manage_stock();
        $record['backorders'] = $product->get_backorders();
        $record['backorders_allowed'] = $product->backorders_allowed();
        $record['sold_individually'] = $product->get_sold_individually();

        // Dimensions and shipping
        $record['weight'] = $product->get_weight();
        $record['length'] = $product->get_length();
        $record['width'] = $product->get_width();
        $record['height'] = $product->get_height();
        $record['shipping_class'] = $product->get_shipping_class();
        $record['shipping_class_id'] = $product->get_shipping_class_id();

        // Taxonomies
        $categories = array();
        $category_ids = array();
        foreach ($product->get_category_ids() as $category_id) {
            $term = get_term_by('id', $category_id, 'product_cat');
            if ($term) {
                $categories[] = $term->name;
                $category_ids[] = $category_id;
            }
        }
        $record['categories'] = $categories;
        $record['category_ids'] = $category_ids;

        $tags = array();
        $tag_ids = array();
        foreach ($product->get_tag_ids() as $tag_id) {
            $term = get_term_by('id', $tag_id, 'product_tag');
            if ($term) {
                $tags[] = $term->name;
                $tag_ids[] = $tag_id;
            }
        }
        $record['tags'] = $tags;
        $record['tag_ids'] = $tag_ids;

        // Attributes
        $attributes = array();
        foreach ($product->get_attributes() as $attribute) {
            if ($attribute->is_taxonomy()) {
                $terms = wp_get_post_terms($product->get_id(), $attribute->get_name(), array('fields' => 'names'));
                if (!is_wp_error($terms) && !empty($terms)) {
                    $attributes[$attribute->get_name()] = $terms;
                }
            } else {
                $attributes[$attribute->get_name()] = $attribute->get_options();
            }
        }
        $record['attributes'] = $attributes;

        // Additional metadata
        $meta_data = array();
        foreach ($product->get_meta_data() as $meta) {
            $meta_data[] = array(
                'key' => $meta->key,
                'value' => $meta->value,
            );
        }
        $record['meta_data'] = $meta_data;

        // For variable products
        if ($product->is_type('variable')) {
            $variations = array();
            $children_ids = $product->get_children();

            foreach ($children_ids as $child_id) {
                $variation = wc_get_product($child_id);
                if ($variation) {
                    $variation_data = array(
                        'id' => $variation->get_id(),
                        'sku' => $variation->get_sku(),
                        'price' => $variation->get_price(),
                        'regular_price' => $variation->get_regular_price(),
                        'sale_price' => $variation->get_sale_price(),
                        'on_sale' => $variation->is_on_sale(),
                        'attributes' => array(),
                    );

                    // Get variation attributes
                    foreach ($variation->get_variation_attributes() as $attribute_name => $attribute_value) {
                        $variation_data['attributes'][str_replace('attribute_', '', $attribute_name)] = $attribute_value;
                    }

                    $variations[] = $variation_data;
                }
            }

            $record['variations'] = $variations;
            $record['variation_count'] = count($variations);
        }

        // Downloadable/virtual product information
        $record['downloadable'] = $product->is_downloadable();
        $record['virtual'] = $product->is_virtual();

        // Return the completed record
        return array($record);
    }

    /**
     * @param \WP_Query $query
     *
     * @return array
     */
    private function getRecordsForQuery(\WP_Query $query)
    {
        $records = array();
        foreach ($query->posts as $post) {
            $product = wc_get_product($post);
            if (! $product) {
                continue;
            }
            $records = array_merge($records, $this->getRecordsForProduct($product));
        }

        return $records;
    }
}
