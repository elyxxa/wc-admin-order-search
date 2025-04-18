var client = algoliasearch(apsOptions.appId, apsOptions.searchApiKey);
var index = client.initIndex(apsOptions.productsIndexName);

// Get the search input ID from options or fallback to default
var searchInputSelector = '#' + (apsOptions.searchInputId || 'post-search-input');

// If the specified input doesn't exist, fall back to alternatives
if (jQuery(searchInputSelector).length === 0) {
    if (jQuery('#post-search-input').length > 0) {
        searchInputSelector = '#post-search-input';
    } else if (jQuery('.search-box input[name="s"]').length > 0) {
        searchInputSelector = '.search-box input[name="s"]';
    } else {
        // Create our own search input if needed
        jQuery('.search-box').prepend(
            '<div class="alignleft actions algolia-search-wrapper">' +
            '<label for="algolia-search-input" class="screen-reader-text">Search Products with Algolia</label>' +
            '<input type="search" id="algolia-search-input" placeholder="Search products with Algolia" class="search-input" style="margin-right: 6px;">' +
            '</div>'
        );
        searchInputSelector = '#algolia-search-input';
    }
}

// Add "Powered by Algolia" text with logo next to the search input
var plugin_url = apsOptions.adminUrl.replace(/\/wp-admin\/$/, '');
jQuery(searchInputSelector).after(
    '<span class="algolia-powered" style="display: inline-block; margin-left: 5px; font-size: 11px; opacity: 0.7; vertical-align: middle;">Powered by ' +
    '<img src="' + plugin_url + '/wp-content/plugins/wc-order-product-admin/assets/images/algolia-mark-square.svg" alt="Algolia" style="height: 16px; vertical-align: middle; margin: 0 2px;"> Algolia</span>'
);

// Apply some specific styling to the standard admin search input
if (searchInputSelector === '#post-search-input') {
    jQuery(searchInputSelector).css({
        'min-width': '300px',
        'transition': 'all 0.2s ease-in-out'
    });

    // Enhance input on focus
    jQuery(searchInputSelector).on('focus', function() {
        jQuery(this).css('min-width', '350px');
    }).on('blur', function() {
        jQuery(this).css('min-width', '300px');
    });
}

autocomplete(searchInputSelector, {
    hint: false,
    debug: apsOptions.debug,
    autoselect: true,
    openOnFocus: true
}, [
    {
        source: function (query, callback) {
            index.search({
                query: query,
                hitsPerPage: 10 // Increased from 7 to 10 for more results
            }).then(function (answer) {
                callback(answer.hits);
                jQuery(".wc-product-search-admin-error").hide();
            }, function () {
                callback([]);
                jQuery(".wc-product-search-admin-error").hide();
                jQuery(".wp-header-end").after(""
                    + '<div class="wc-product-search-admin-error notice notice-error is-dismissible">'
                    + '<p><b>WooCommerce Products Search Admin:</b> An error occurred while fetching results from Algolia.</p>'
                    + '<p>If you are offline, this is expected. If you are online, you might want to <a href="options-general.php?page=wc_psa_options">take a look at your configured Algolia credentials</a>.</p>'
                    + '</div>');
            });
        },
        displayKey: 'title',
        templates: {
            empty: '<div class="aa-empty">No products found for this query.</div>',
            footer: '<div class="aa-footer">Showing top 10 results. Press Enter for full search.</div>',
            suggestion: function (suggestion) {
                return '<a href="post.php?post=' + suggestion.objectID + '&action=edit">'
                    + getBasicInfoLine(suggestion)
                    + getPriceInfoLine(suggestion)
                    + getStockInfoLine(suggestion)
                    + getCategoriesLine(suggestion)
                    + getTagsLine(suggestion)
                    + getTypeInfoLine(suggestion)
                    + "</a>";
            }
        }
    }
]).on('autocomplete:selected', function (event, suggestion, dataset, context) {
    if (context.selectionMethod === 'click') {
        // If the link is clicked, we let the browser do it's job so that users can open in a new tab if they wish.
        // We also prevent event from bubbling up so the dropdown stays open in case user actually choses to open
        // the product in a new tab.
        event.preventDefault();
        return;
    }
    window.location.href = "post.php?post=" + suggestion.objectID + "&action=edit";
});

// Focus on the search input
jQuery(searchInputSelector).select();

// Add enhanced CSS for autocomplete dropdown
jQuery('head').append('<style>' +
    '.algolia-search-wrapper { position: relative; margin-bottom: 10px; }' +
    '.aa-dropdown-menu { background-color: #fff; border: 1px solid #d6d6d6; box-shadow: 0 2px 10px rgba(0,0,0,0.15); margin-top: 3px; width: 100%; border-radius: 3px; }' +
    '.aa-dropdown-menu a { color: #444; display: block; padding: 10px; text-decoration: none; border-bottom: 1px solid #f5f5f5; transition: background-color 0.15s ease; }' +
    '.aa-dropdown-menu a:hover { background-color: #f8f8f8; }' +
    '.aa-dropdown-menu a:last-child { border-bottom: none; }' +
    '.algolia-autocomplete { width: 350px; }' +
    '.aa-empty { padding: 10px; color: #888; text-align: center; }' +
    '.aa-footer { padding: 8px; color: #999; font-size: 11px; text-align: center; border-top: 1px solid #f5f5f5; }' +
    '.wc-psa__line { margin-bottom: 3px; }' +
    '.wc-psa__title { font-weight: bold; margin-right: 10px; }' +
    '.wc-psa__sku { color: #999; margin-left: 6px; }' +
    '.wc-psa__status { background: #f0f0f0; font-size: 11px; padding: 2px 5px; border-radius: 3px; margin-right: 6px; }' +
    '.wc-psa__stock-instock { color: #7ad03a; }' +
    '.wc-psa__stock-outofstock { color: #a44; }' +
    '.wc-psa__stock-onbackorder { color: #dd9933; }' +
    '.algolia-powered img { vertical-align: middle; height: 14px; }' +
    '</style>');

function getBasicInfoLine(suggestion) {
    var titleHighlighted = suggestion._highlightResult && suggestion._highlightResult.title
        ? suggestion._highlightResult.title.value
        : suggestion.title;

    var skuHighlighted = suggestion._highlightResult && suggestion._highlightResult.sku
        ? suggestion._highlightResult.sku.value
        : suggestion.sku;

    return '<div class="wc-psa__line">'
        + '<span class="wc-psa__status">' + suggestion.status + '</span>'
        + '<span class="wc-psa__title">' + titleHighlighted + '</span>'
        + (suggestion.sku ? '<span class="wc-psa__sku">SKU: ' + skuHighlighted + '</span>' : '')
        + '</div>';
}

function getPriceInfoLine(suggestion) {
    var display = '';

    if (suggestion.regular_price && suggestion.sale_price && suggestion.on_sale) {
        display = '<span class="wc-psa__regular-price"><del>' + suggestion.regular_price + '</del></span> '
                + '<span class="wc-psa__sale-price">' + suggestion.sale_price + '</span>';
    } else if (suggestion.price) {
        display = '<span class="wc-psa__price">' + suggestion.price + '</span>';
    }

    if (!display) {
        return '';
    }

    return '<div class="wc-psa__line">'
        + '<span class="wc-psa__price-label">Price: </span>'
        + display
        + '</div>';
}

function getStockInfoLine(suggestion) {
    if (!suggestion.stock_status) {
        return '';
    }

    var stockDisplay = '';
    if (suggestion.stock_status === 'instock') {
        stockDisplay = 'In stock';
        if (suggestion.stock_quantity) {
            stockDisplay += ' (' + suggestion.stock_quantity + ')';
        }
    } else if (suggestion.stock_status === 'outofstock') {
        stockDisplay = 'Out of stock';
    } else if (suggestion.stock_status === 'onbackorder') {
        stockDisplay = 'On backorder';
    }

    return '<div class="wc-psa__line">'
        + '<span class="wc-psa__stock-label">Stock: </span>'
        + '<span class="wc-psa__stock wc-psa__stock-' + suggestion.stock_status + '">' + stockDisplay + '</span>'
        + '</div>';
}

function getCategoriesLine(suggestion) {
    if (!suggestion.categories || suggestion.categories.length === 0) {
        return '';
    }

    var categoriesHighlighted = [];
    if (suggestion._highlightResult && suggestion._highlightResult.categories) {
        for (var i = 0; i < suggestion._highlightResult.categories.length; i++) {
            categoriesHighlighted.push(suggestion._highlightResult.categories[i].value);
        }
    } else {
        categoriesHighlighted = suggestion.categories;
    }

    return '<div class="wc-psa__line">'
        + '<span class="wc-psa__categories-label">Categories: </span>'
        + '<span class="wc-psa__categories">' + categoriesHighlighted.join(', ') + '</span>'
        + '</div>';
}

function getTagsLine(suggestion) {
    if (!suggestion.tags || suggestion.tags.length === 0) {
        return '';
    }

    var tagsHighlighted = [];
    if (suggestion._highlightResult && suggestion._highlightResult.tags) {
        for (var i = 0; i < suggestion._highlightResult.tags.length; i++) {
            tagsHighlighted.push(suggestion._highlightResult.tags[i].value);
        }
    } else {
        tagsHighlighted = suggestion.tags;
    }

    return '<div class="wc-psa__line">'
        + '<span class="wc-psa__tags-label">Tags: </span>'
        + '<span class="wc-psa__tags">' + tagsHighlighted.join(', ') + '</span>'
        + '</div>';
}

function getTypeInfoLine(suggestion) {
    var typeDisplay = suggestion.type;
    if (suggestion.type === 'variable' && suggestion.variation_count) {
        typeDisplay += ' (' + suggestion.variation_count + ' variations)';
    }

    var additionalInfo = [];
    if (suggestion.downloadable) additionalInfo.push('Downloadable');
    if (suggestion.virtual) additionalInfo.push('Virtual');
    if (suggestion.featured) additionalInfo.push('Featured');

    var additionalInfoDisplay = additionalInfo.length > 0 ? ' | ' + additionalInfo.join(', ') : '';

    return '<div class="wc-psa__line">'
        + '<span class="wc-psa__type-label">Type: </span>'
        + '<span class="wc-psa__type">' + typeDisplay + additionalInfoDisplay + '</span>'
        + '</div>';
}
