<?php

/*
 * This file is part of WooCommerce Product Search Admin plugin for WordPress.
 * (c) Webkonsulenterne <contact@webkonsulenterne.dk>
 * This source file is subject to the GPLv2 license that is bundled
 * with this source code in the file LICENSE.
 */

namespace WC_Product_Search_Admin\Admin;

use WC_Product_Search_Admin\Options;
use WC_Product_Search_Admin\Products_Index;

class Ajax_Algolia_Account_Settings_Form
{
    /**
     * @var Options
     */
    private $options;

    /**
     * @param Products_Index $productsIndex
     * @param Options     $options
     */
    public function __construct(Options $options)
    {
        $this->options = $options;

        add_action('wp_ajax_wc_psa_save_algolia_settings', array( $this, 'save_algolia_account_settings' ));
    }

    public function save_algolia_account_settings()
    {
        check_ajax_referer('save_algolia_account_settings_nonce');
        if ((! isset($_POST['app_id']) && ! defined('WC_PSA_ALGOLIA_APPLICATION_ID')) ||
            (! isset($_POST['search_api_key']) && ! defined('WC_PSA_ALGOLIA_SEARCH_API_KEY')) ||
            (! isset($_POST['admin_api_key']) && ! defined('WC_PSA_ALGOLIA_ADMIN_API_KEY'))
        ) {
            wp_die('Hacker');
        }

        try {
            $this->options->set_algolia_account_settings(
                isset($_POST['app_id']) ? $_POST['app_id'] : '',
                isset($_POST['search_api_key']) ? $_POST['search_api_key'] : '',
                isset($_POST['admin_api_key']) ? $_POST['admin_api_key'] : ''
            );
        } catch (\InvalidArgumentException $exception) {
            wp_send_json_error(
                array(
                    'message' => $exception->getMessage(),
                )
            );
        }

        $response = array(
            'success' => true,
            'message' => __('Your Algolia account settings have been saved. You can now hit the "re-index products" button.', 'wc-product-search-admin'),
        );

        wp_send_json($response);
    }
}
