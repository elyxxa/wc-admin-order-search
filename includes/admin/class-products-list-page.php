<?php

/*
 * This file is part of WooCommerce Product Search Admin plugin for WordPress.
 * (c) Webkonsulenterne <contact@webkonsulenterne.dk>
 * This source file is subject to the GPLv2 license that is bundled
 * with this source code in the file LICENSE.
 */

namespace WC_Product_Search_Admin\Admin;

use WC_Product_Search_Admin\AlgoliaSearch\AlgoliaException;
use WC_Product_Search_Admin\AlgoliaSearch\Client;
use WC_Product_Search_Admin\Options;

class Products_List_Page
{
    /**
     * @var Options
     */
    private $options;

    /**
     * @var int
     */
    private $nb_hits;

    /**
     * Products_List_Page constructor.
     *
     * @param Options $options
     */
    public function __construct(Options $options)
    {
        add_action('admin_enqueue_scripts', array( $this, 'enqueue_scripts' ));
        add_action('pre_get_posts', array( $this, 'pre_get_posts' ));

        $this->options = $options;
    }

    public function is_current_screen()
    {
        if (! function_exists('get_current_screen')) {
            return false;
        }
        $screen = get_current_screen();

        return (
            !is_null($screen) && (
                // Classic WooCommerce products screen
                'edit-product' === $screen->id ||
                // Modern WooCommerce Admin products page
                (isset($_GET['page']) && $_GET['page'] === 'wc-products') ||
                // Check for other possible WooCommerce admin screens
                (strpos($screen->id, 'woocommerce_page_wc-products') !== false)
            )
        );
    }

    public function enqueue_scripts()
    {
        if (! $this->is_current_screen()) {
            return;
        }

        wp_enqueue_style('wc_psa_products_search', plugin_dir_url(WC_PSA_FILE) . 'assets/css/styles.css', array(), WC_PSA_VERSION);

        $suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '' : '.min';
        $suffix = '';
        wp_enqueue_script('wc_psa_algolia', 'https://cdn.jsdelivr.net/algoliasearch/3/algoliasearch' . $suffix . '.js', array(), false, true);
        wp_enqueue_script('wc_psa_autocomplete', 'https://cdn.jsdelivr.net/autocomplete.js/0/autocomplete' . $suffix . '.js', array(), false, true);
        wp_enqueue_script('wc_psa_products_search', plugin_dir_url(WC_PSA_FILE) . 'assets/js/products-autocomplete.js', array( 'wc_psa_algolia', 'wc_psa_autocomplete', 'jquery' ), WC_PSA_VERSION, true);

        // Add a custom class to the body for styling hooks
        add_filter('admin_body_class', function ($classes) {
            return $classes . ' wc-psa-enabled';
        });

        $index_name            = $this->options->get_products_index_name();
        $search_key            = $this->options->get_algolia_search_api_key();
        $restricted_search_key = Client::generateSecuredApiKey(
            $search_key,
            array(
                'restrictIndices' => $index_name,
                'validUntil'      => time() + 60 * 24, // A day from now.
            )
        );

        wp_localize_script(
            'wc_psa_products_search',
            'apsOptions',
            array(
                'appId'             => $this->options->get_algolia_app_id(),
                'searchApiKey'      => $restricted_search_key,
                'productsIndexName' => $index_name,
                'debug'             => defined('WP_DEBUG') && WP_DEBUG === true,
                'searchInputId'     => 'post-search-input', // Explicitly set the target input ID
                'adminUrl'          => admin_url(),
            )
        );

        // Ensure the search input retains any existing query value
        if (!empty($_GET['s'])) {
            add_action('admin_footer', function () {
                echo '<script type="text/javascript">
                    jQuery(document).ready(function($) {
                        // Ensure search value is preserved
                        if ($("#post-search-input").length && "' . esc_js($_GET['s']) . '" !== "") {
                            $("#post-search-input").val("' . esc_js($_GET['s']) . '");
                        }
                    });
                </script>';
            });
        }
    }

    /**
     * We force the WP_Query to only return records according to Algolia's ranking.
     *
     * @param \WP_Query $query
     */
    public function pre_get_posts(\WP_Query $query)
    {
        if (! $this->should_filter_query($query)) {
            return;
        }
        $current_page = 1;
        if (get_query_var('paged')) {
            $current_page = get_query_var('paged');
        } elseif (get_query_var('page')) {
            $current_page = get_query_var('page');
        }

        $posts_per_page = (int) get_option('posts_per_page');

        if (! $this->options->has_algolia_account_settings()) {
            return;
        }

        $client = new Client($this->options->get_algolia_app_id(), $this->options->get_algolia_search_api_key());
        $index  = $client->initIndex($this->options->get_products_index_name());

        try {
            $results = $index->search(
                $query->query['s'],
                array(
                    'attributesToRetrieve' => 'id',
                    'hitsPerPage'          => $posts_per_page,
                    'page'                 => $current_page - 1, // Algolia pages are zero indexed.
                )
            );
        } catch (AlgoliaException $exception) {
            add_action(
                'admin_notices',
                function () use ($exception) {
                    ?>
				<div class="notice notice-error is-dismissible">
					<p><?php esc_html_e('Unable to fetch results from Algolia. Falling back to native WordPress search.', 'wc-product-search-admin'); ?></p>
					<p><code><?php echo esc_html($exception->getMessage()); ?></code></p>
				</div>
					<?php
                }
            );
            return;
        }

        add_filter('found_posts', array( $this, 'found_posts' ), 10, 2);
        add_filter('posts_search', array( $this, 'posts_search' ), 10, 2);

        // Store the total number of hits, so that we can hook into the `found_posts`.
        // This is useful for pagination.
        $this->nb_hits = $results['nbHits'];
        $post_ids      = array();
        foreach ($results['hits'] as $result) {
            $post_ids[] = $result['id'];
        }

        // Make sure there are not results by tricking WordPress in trying to find
        // a non existing post ID.
        // Otherwise, the query returns all the results.
        if (empty($post_ids)) {
            $post_ids = array( 0 );
        }

        $query->set('posts_per_page', $posts_per_page);
        $query->set('offset', 0);
        $query->set('post__in', $post_ids);
        $query->set('orderby', 'post__in'); // Make sure we preserve Algolia's ranking.
    }

    /**
     * Determines if we should filter the query passed as argument.
     *
     * @param \WP_Query $query
     *
     * @return bool
     */
    private function should_filter_query(\WP_Query $query)
    {
        return $this->is_current_screen()
            && $query->is_admin
            && $query->is_search()
            && $query->is_main_query()
            && apply_filters('wc_psa_enable_backend_search', true, $query);
    }

    /**
     * This hook returns the actual real number of results available in Algolia.
     *
     * @param int      $found_posts
     * @param \WP_Query $query
     *
     * @return int
     */
    public function found_posts($found_posts, \WP_Query $query)
    {
        return $this->should_filter_query($query) ? $this->nb_hits : $found_posts;
    }

    /**
     * Filter the search SQL that is used in the WHERE clause of WP_Query.
     * Removes the where Like part of the queries as we consider Algolia as being the source of truth.
     * We don't want to filter by anything but the actual list of post_ids resulting
     * from the Algolia search.
     *
     * @param string   $search
     * @param \WP_Query $query
     *
     * @return string
     */
    public function posts_search($search, \WP_Query $query)
    {
        return $this->should_filter_query($query) ? '' : $search;
    }
}
