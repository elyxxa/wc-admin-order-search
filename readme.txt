=== WooCommerce Product Search Admin ===
Contributors: Webksonulenterne, Shakir
Tags: search, products, woocommerce, algolia, admin, autocomplete, products search, search as you type, instant search, ajax search, ajax
Requires at least: 4.6
Tested up to: 6.2
Requires PHP: 5.3
Stable tag: 1.0.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Search for WooCommerce products in the admin at the speed of thought with Algolia.

== Description ==

This plugin will power the WooCommerce products search input with an autocompleted search field providing results as you type in milliseconds regardless of how many products you have in your database.

When you start having lots of products in WooCommerce, searching for a specific product can become very slow and time-consuming.

Fun fact is also that the more you have products, the more you will need to search for a specific one.

We have seen users wait for over a minute for WooCommerce to return the search results in the admin.
And even after that long waiting time, given that the default search mechanism uses SQL queries, the relevancy isn't optimal and you often need to adjust your search query and wait again.

By installing this plugin, you will be able to index all your products into Algolia and be able to find products at the speed of thought, right from your usual products list in the admin screen of your WordPress website.

You can find products by typing just a few characters.
The search engine will search on the following fields:

* Product Title
* Product SKU
* Product Description
* Product Short Description
* Product Categories
* Product Tags
* Product Meta Data

As you start typing in the search input, you will see instant results popping up inside of a dropdown menu and you will
be able to find the proper product in milliseconds.

Also note that by leveraging Algolia as a search engine, in addition to super fast results as you type, you will
also benefit from all the other features like typo tolerance that will make sure that if you misspell for example the product name, you will still get the relevant products displayed as part of the results.

= Automatic synchronization =

After you correctly provided the plugin with your Algolia credentials, the plugin will take care of making sure
the search index stays up to date with your WooCommerce products.

Every time a product is added, updated, trashed or deleted, it will synchronize with Algolia.

**Note, however, that when you first initialize the plugin, you need to index your existing products.**

= WP-CLI command =

The plugin also offers a [WP-CLI](http://wp-cli.org/) command to allow you to reindex your products directly from the
terminal.

Here is how to use it:

`wp products reindex`

Please note that at no point you are forced to use the command line tool and that the admin settings screen
of the plugin also allows you to reindex all your products.

The command line approach is an excellent technical alternative though if you have over 50 thousands of records and you want to speed up the indexing.

Note that there is no limit to how many products this plugin can handle, and indexing will work with both indexing methods;
powered by the UI or by using the WP-CLI command line tool.

The only limitation of the admin UI reindexing is that you have to leave the page open during the reindexing
process.

= Backend Product Search =

By default, the plugin enhances the default backend search behavior by using Algolia.
This ensures a consistency between results you see in the list and the ones coming from the autocomplete dropdown.
If for whatever reason you want to restore the default backend search behavior, you can use the `wc_psa_enable_backend_search` filter hook.

`
function should_enable_backend_search( $value, WP_Query $query ) {
    return false;
}

add_filter( 'wc_psa_enable_backend_search', 'should_enable_backend_search', 10, 2 );
`

= Configuration constants =

By default, you can configure the plugin on the included options page, but you can also configure the plugin by using one (or more) of the following constants in your `wp-config.php`.
When you use constants, the corresponding option fields will be disabled on the options page.

`
define( 'WC_PSA_ALGOLIA_APPLICATION_ID', '<value>' );
define( 'WC_PSA_ALGOLIA_SEARCH_API_KEY', '<value>' );
define( 'WC_PSA_ALGOLIA_ADMIN_API_KEY', '<value>' );
define( 'WC_PSA_PRODUCTS_INDEX_NAME', 'wc_products' );
define( 'WC_PSA_PRODUCTS_PER_BATCH', 200 );
`

= About Algolia =

This plugin relies on the Algolia service which requires you to [create an account](https://www.algolia.com/getstarted/pass?redirect=true).
Algolia offers its Search as a Service provider on an incremental payment program, including a free plan which includes 10,000 records & 100,000 operations per month.
Beyond that, make sure you [check out the pricing](https://www.algolia.com/pricing).

This plugin will create precisely one record per product to index. We index every product that is not flagged as trashed.

Algolia does not support this plugin.

== Installation ==

The plugin works with WooCommerce 2.x & 3.x

1. Upload the plugin files to the `/wp-content/plugins/plugin-name` directory,
or install the plugin through the WordPress plugins screen directly.
1. Activate the plugin through the 'Plugins' screen in WordPress
1. Hit the "Setup" button that will appear at the top of every page of your admin,
or directly access the plugin settings page under the `Settings` tab
1. Provide the plugin with your Algolia settings, and
[create an Algolia account](https://www.algolia.com/getstarted/pass?redirect=true) if you haven't got one yet
1. Now click on the `re-index products` button to start indexing your existing products.
WARNING: don't leave the page until the progress reaches 100%
1. Once indexing has finished, head to `WooCommerce -> Products` and enjoy the products appearing as you type when using the search input.

== Screenshots ==

1. The slick autocomplete search results dropdown.
2. Setup instructions steps.
3. Algolia account settings.
4. Indexing settings.

== Changelog ==

= 1.0.0 =
* Initial release
