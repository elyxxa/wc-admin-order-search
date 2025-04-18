<div class="wrap">
	<h1><?php echo esc_html(get_admin_page_title()); ?></h1>

	<h2><?php esc_html_e('Setup instructions', 'wc-product-search-admin'); ?></h2>
	<p><?php esc_html_e('To power your products search with this plugin you need to:', 'wc-product-search-admin'); ?></p>
	<ol>
		<li><?php esc_html_e('Create an Algolia account', 'wc-product-search-admin'); ?></li>
		<li><?php esc_html_e('Paste the API keys in the Algolia Account settings section of this page', 'wc-product-search-admin'); ?></li>
		<li>Hit this <button class="aos-reindex-button button button-primary" data-nonce="<?php echo esc_attr(wp_create_nonce('wc_psa_reindex')); ?>">Re-index products</button> button</li>
	</ol>
	<p>Once you are all set, the search input on your <a href="edit.php?post_type=product">products list page</a> will be powered by the plugin.</p>
	<p><?php esc_html_e('Feel free to re-index every time you think something went wrong.', 'wc-product-search-admin'); ?></p>


	<h2><?php esc_html_e('Algolia Account settings', 'wc-product-search-admin'); ?></h2>
	<p>This plugin indexes your products in <a href="https://www.algolia.com/" target="_blank">Algolia</a> to get extremely fast an relevant results.</p>
	<p>Algolia is a hosted search service that offers <a href="https://www.algolia.com/pricing" target="_blank">different pricing plans</a> according to your usage.</p>
	<p>In this plugin, every un-trashed product will be stored as one record in Algolia.</p>
	<p>If you <strong>don't have an Algolia account yet</strong>, you can <a href="https://www.algolia.com/users/sign_up" target="_blank">create one in a few minutes</a>.</p>

	<form method="post" class="aos-ajax-form">
		<input type="hidden" name="action" value="wc_psa_save_algolia_settings">
		<?php wp_nonce_field('save_algolia_account_settings_nonce'); ?>
		<table class="form-table">
			<tbody>
				<tr>
					<th>
						<label><?php esc_html_e('Algolia Application ID:', 'wc-product-search-admin'); ?> </label>
					</th>
					<td>
						<input type="text" class="regular-text" name="app_id" value="<?php echo esc_attr($this->options->get_algolia_app_id()); ?>" <?php disabled(defined('WC_PSA_ALGOLIA_APPLICATION_ID')); ?>>
						<p class="description">You can grab it from your <a href="https://www.algolia.com/api-keys" target="_blank">Algolia admin panel</a>.</p>
					</td>
				</tr>
				<tr>
					<th>
						<label><?php esc_html_e('Algolia Search API key:', 'wc-product-search-admin'); ?></label>
					</th>
					<td>
						<input type="text" class="regular-text" name="search_api_key" value="<?php echo esc_attr($this->options->get_algolia_search_api_key()); ?>" <?php disabled(defined('WC_PSA_ALGOLIA_SEARCH_API_KEY')); ?>>
						<p class="description">
							You can grab it from your <a href="https://www.algolia.com/api-keys" target="_blank">Algolia admin panel</a>.
							<br>
							For maximum security, this key should only have "search" permission on the "<?php echo esc_attr($this->options->get_products_index_name()); ?>" index.
							<br>
							Read more about permissions in the <a href="https://www.algolia.com/doc/guides/security/api-keys/" target="_blank">Algolia guide about API keys</a>.
						</p>
					</td>
				</tr>
				<tr>
					<th>
						<label><?php esc_html_e('Algolia Admin API key:', 'wc-product-search-admin'); ?></label>
					</th>
					<td>
						<input type="password" class="regular-text" name="admin_api_key" value="<?php echo esc_attr($this->options->get_algolia_admin_api_key()); ?>" <?php disabled(defined('WC_PSA_ALGOLIA_ADMIN_API_KEY')); ?>>
						<p class="description">You can grab it from your <a href="https://www.algolia.com/api-keys" target="_blank">Algolia admin panel</a>.</p>
					</td>
				</tr>
			</tbody>
		</table>

		<p class="submit">
			<button type="submit" class="button button-primary" <?php disabled(defined('WC_PSA_ALGOLIA_APPLICATION_ID') && defined('WC_PSA_ALGOLIA_SEARCH_API_KEY') && defined('WC_PSA_ALGOLIA_ADMIN_API_KEY')); ?>><?php esc_html_e('Save Algolia account settings', 'wc-product-search-admin'); ?></button>
		</p>
	</form>




	<h2><?php esc_html_e('Products indexing settings', 'wc-product-search-admin'); ?></h2>

	<form method="post" class="aos-ajax-form">
		<input type="hidden" name="action" value="wc_psa_save_indexing_options">
		<?php wp_nonce_field('save_indexing_options_nonce'); ?>
		<table class="form-table">
			<tbody>
			<tr>
				<th>
					<label><?php esc_html_e('Products index name in Algolia:', 'wc-product-search-admin'); ?></label>
				</th>
				<td>
					<input type="text" class="regular-text" name="products_index_name" value="<?php echo esc_attr($this->options->get_products_index_name()); ?>" <?php disabled(defined('WC_PSA_PRODUCTS_INDEX_NAME')); ?>>
				</td>
			</tr>
			<tr>
				<th>
					<label><?php esc_html_e('Products to index per batch:', 'wc-product-search-admin'); ?></label>
				</th>
				<td>
					<input type="number" name="products_per_batch"  value="<?php echo esc_attr($this->options->get_products_to_index_per_batch_count()); ?>" <?php disabled(defined('WC_PSA_PRODUCTS_PER_BATCH')); ?>>
				</td>
			</tr>
			</tbody>
		</table>

		<p class="submit">
			<button type="submit" class="button button-primary" <?php disabled(defined('WC_PSA_PRODUCTS_INDEX_NAME') && defined('WC_PSA_PRODUCTS_PER_BATCH')); ?>><?php esc_html_e('Save products indexing settings', 'wc-product-search-admin'); ?></button>
		</p>
	</form>

</div>
