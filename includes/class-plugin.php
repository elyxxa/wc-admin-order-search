<?php

/*
 * This file is part of WooCommerce Product Search Admin plugin for WordPress.
 * (c) Webkonsulenterne <contact@webkonsulenterne.dk>
 * This source file is subject to the GPLv2 license that is bundled
 * with this source code in the file LICENSE.
 */

namespace WC_Product_Search_Admin;

use WC_Product_Search_Admin\AlgoliaSearch\Client;
use WC_Product_Search_Admin\AlgoliaSearch\Version;

class Plugin
{
    /**
     * @var self
     */
    private static $instance;

    /**
     * @var Options
     */
    private $options;

    /**
     * @var Products_Index
     */
    private $products_index;

    /**
     * @param Options $options
     */
    private function __construct(Options $options)
    {
        global $wp_version;

        $this->options = $options;
        if (! $this->options->has_algolia_account_settings()) {
            add_action('admin_notices', array( $this, 'configure_algolia_settings_notice' ));

            return;
        }

        $algolia_client = new Client($options->get_algolia_app_id(), $options->get_algolia_admin_api_key());

        $integration_name = 'wc-product-search-admin';
        $ua               = '; ' . $integration_name . ' integration (' . WC_PSA_VERSION . ')'
            . '; PHP (' . phpversion() . ')'
            . '; WordPress (' . $wp_version . ')';

        Version::$custom_value = $ua;

        $this->products_index = new Products_Index($options->get_products_index_name(), $algolia_client);
        new Product_Change_Listener($this->products_index);
    }

    /**
     * @param Options $options
     *
     * @return Plugin
     */
    public static function initialize(Options $options)
    {
        if (null !== self::$instance) {
            throw new \LogicException('Plugin has already been initialized!');
        }

        self::$instance = new self($options);

        return self::$instance;
    }

    /**
     * @return Plugin
     */
    public static function get_instance()
    {
        if (null === self::$instance) {
            throw new \LogicException('Plugin::initialize must be called first!');
        }

        return self::$instance;
    }

    /**
     * @return Options
     */
    public function get_options()
    {
        return $this->options;
    }

    /**
     * @return Products_Index
     */
    public function get_products_index()
    {
        if (null === $this->products_index) {
            throw new \LogicException('Products index has not be initialized.');
        }

        return $this->products_index;
    }

    public function configure_algolia_settings_notice()
    {
        $screen = get_current_screen();
        if ('settings_page_wc_psa_options' === $screen->id) {
            return;
        } ?>
		<div class="notice notice-success">
			<p><?php esc_html_e('You are one step away from being able to have fast and relevant search powered by Algolia for finding WooCommerce products.', 'wc-product-search-admin'); ?></p>
			<p><a href="options-general.php?page=wc_psa_options" class="button button-primary"><?php esc_html_e('Setup now', 'wc-product-search-admin'); ?></a></p>
		</div>
		<?php
    }
}
