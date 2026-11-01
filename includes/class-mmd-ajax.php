<?php
/**
 * AJAX Handlers for Product Gifts
 */

if (!defined('ABSPATH')) {
    exit;
}

class MMD_Ajax {

    public function __construct() {
        // Product search
        add_action('wp_ajax_mmd_search_products', array($this, 'search_products'));

        // Get product variations
        add_action('wp_ajax_mmd_get_product_variations', array($this, 'get_product_variations'));

        // Get attribute terms
        add_action('wp_ajax_mmd_get_attribute_terms', array($this, 'get_attribute_terms'));

        // Get gift products variations (for admin default variations UI)
        add_action('wp_ajax_mmd_get_gift_products_variations', array($this, 'get_gift_products_variations'));

        // Get products and variations by categories
        add_action('wp_ajax_mmd_get_products_by_categories', array($this, 'get_products_by_categories'));
    }

    /**
     * Search products for select2
     */
    public function search_products() {
        // Allow multiple nonce names for flexibility
        $nonce_valid = false;
        if (isset($_GET['nonce']) && wp_verify_nonce($_GET['nonce'], 'mmd_search_products')) {
            $nonce_valid = true;
        } elseif (isset($_GET['nonce']) && wp_verify_nonce($_GET['nonce'], 'mmd_gift_rules_nonce')) {
            $nonce_valid = true;
        }

        if (!$nonce_valid) {
            wp_send_json_error('Invalid nonce');
            return;
        }

        $search_term = isset($_GET['q']) ? sanitize_text_field($_GET['q']) : '';
        $include_variations = isset($_GET['include_variations']) ? $_GET['include_variations'] === '1' : true;

        if (empty($search_term)) {
            wp_send_json_success(array());
            return;
        }

        $results = array();

        // Search for parent products
        $args = array(
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => 20,
            's' => $search_term
        );

        $products = get_posts($args);

        foreach ($products as $product_post) {
            $product = wc_get_product($product_post->ID);
            if (!$product) {
                continue;
            }
            $results[] = array(
                'id' => $product_post->ID,
                'name' => $product->get_name(),
                'text' => $product->get_name() . ' (#' . $product_post->ID . ')'
            );

            // For variable products, also add their variations
            if ($include_variations && $product->is_type('variable')) {
                $variations = $product->get_available_variations();
                foreach ($variations as $variation) {
                    $variation_obj = wc_get_product($variation['variation_id']);
                    if (!$variation_obj) {
                        continue;
                    }
                    $variation_name = $variation_obj->get_name();
                    $results[] = array(
                        'id' => $variation['variation_id'],
                        'name' => $variation_name,
                        'text' => '   ↳ ' . $variation_name . ' (#' . $variation['variation_id'] . ')'
                    );
                }
            }
        }

        // Also search directly in variations by title/SKU
        if ($include_variations) {
            $variation_args = array(
                'post_type' => 'product_variation',
                'post_status' => 'publish',
                'posts_per_page' => 20,
                's' => $search_term
            );

            $variations = get_posts($variation_args);
            $existing_ids = array_column($results, 'id');

            foreach ($variations as $variation_post) {
                // Skip if already added (from parent product search)
                if (in_array($variation_post->ID, $existing_ids)) {
                    continue;
                }

                $variation = wc_get_product($variation_post->ID);
                if (!$variation) {
                    continue;
                }
                $variation_name = $variation->get_name();
                $results[] = array(
                    'id' => $variation_post->ID,
                    'name' => $variation_name,
                    'text' => $variation_name . ' (#' . $variation_post->ID . ')'
                );
            }
        }

        wp_send_json_success($results);
    }

    /**
     * Get product variations
     */
    public function get_product_variations() {
        check_ajax_referer('mmd_variations_nonce', 'nonce');

        $product_ids = isset($_POST['product_ids']) && is_array($_POST['product_ids'])
            ? array_map('intval', $_POST['product_ids'])
            : array();

        $selected_variations = isset($_POST['selected_variations']) && is_array($_POST['selected_variations'])
            ? array_map('intval', $_POST['selected_variations'])
            : array();

        if (empty($product_ids)) {
            wp_send_json_error('No products selected');
            return;
        }

        ob_start();

        $has_variations = false;

        foreach ($product_ids as $product_id) {
            $product = wc_get_product($product_id);

            if (!$product || !$product->is_type('variable')) {
                continue;
            }

            $has_variations = true;
            $variations = $product->get_available_variations();

            if (empty($variations)) {
                continue;
            }

            echo '<div class="mmd-product-variations" style="margin-bottom: 20px; padding: 15px; background: white; border-radius: 5px;">';
            echo '<h5 style="margin-top: 0; color: #333;">' . esc_html($product->get_name()) . '</h5>';

            foreach ($variations as $variation) {
                $variation_obj = wc_get_product($variation['variation_id']);
                $variation_name = implode(', ', $variation['attributes']);
                $is_selected = in_array($variation['variation_id'], $selected_variations);

                echo '<div class="mmd-variation-item" style="margin: 8px 0; padding: 8px; border: 1px solid #ddd; border-radius: 3px; background: #fafafa;">';
                echo '<label style="display: flex; align-items: center; cursor: pointer;">';
                echo '<input type="checkbox" name="mmd_allowed_variations[]" value="' . esc_attr($variation['variation_id']) . '"' . checked($is_selected, true, false) . ' style="margin-left: 8px;">';
                echo '<span style="flex: 1;">' . esc_html($variation_name) . ' <small style="color: #666;">(#' . esc_html( $variation['variation_id'] ) . ')</small></span>';

                // Show price
                if ($variation_obj) {
                    echo '<span style="margin: 0 10px; font-weight: bold; color: #2196F3;">' . wp_kses_post( wc_price($variation_obj->get_price()) ) . '</span>';
                }

                // Show stock status
                if ($variation_obj && !$variation_obj->is_in_stock()) {
                    echo ( '<span style="color: #dc3232; font-size: 12px;">' . esc_html__( 'Out of stock', 'incentiv' ) . '</span>' );
                } else {
                    echo ( '<span style="color: #46b450; font-size: 12px;">' . esc_html__( 'In stock', 'incentiv' ) . '</span>' );
                }

                echo '</label>';
                echo '</div>';
            }

            echo '</div>';
        }

        if (!$has_variations) {
            echo ( '<p class="description">' . esc_html__( 'None of the selected products is a variable product', 'incentiv' ) . '</p>' );
        }

        $html = ob_get_clean();

        wp_send_json_success(array('html' => $html));
    }

    /**
     * Get attribute terms for a given attribute
     */
    public function get_attribute_terms() {
        check_ajax_referer('mmd_gift_rules_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
            return;
        }

        $attribute_slug = isset($_POST['attribute_slug']) ? sanitize_text_field($_POST['attribute_slug']) : '';

        if (empty($attribute_slug)) {
            wp_send_json_error('No attribute specified');
            return;
        }

        $terms = get_terms(array(
            'taxonomy' => $attribute_slug,
            'hide_empty' => false,
        ));

        if (is_wp_error($terms)) {
            wp_send_json_error($terms->get_error_message());
            return;
        }

        $results = array();
        foreach ($terms as $term) {
            $results[] = array(
                'term_id' => $term->term_id,
                'name' => $term->name,
                'slug' => $term->slug,
            );
        }

        wp_send_json_success($results);
    }

    /**
     * Get gift products variations for admin UI
     */
    public function get_gift_products_variations() {
        check_ajax_referer('mmd_gift_rules_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
            return;
        }

        $product_ids = isset($_POST['product_ids']) ? array_map('intval', $_POST['product_ids']) : array();

        if (empty($product_ids)) {
            wp_send_json_error('No products specified');
            return;
        }

        $products_data = array();
        $has_variable_products = false;

        foreach ($product_ids as $product_id) {
            $product = wc_get_product($product_id);

            if (!$product) {
                continue;
            }

            $product_info = array(
                'id' => $product_id,
                'name' => $product->get_name(),
                'is_variable' => $product->is_type('variable'),
                'variations' => array()
            );

            if ($product->is_type('variable')) {
                $has_variable_products = true;
                $variations = $product->get_available_variations();

                foreach ($variations as $variation) {
                    $variation_obj = wc_get_product($variation['variation_id']);
                    if ($variation_obj) {
                        $attributes = array();
                        foreach ($variation['attributes'] as $key => $value) {
                            $attributes[] = $value;
                        }

                        $product_info['variations'][] = array(
                            'id' => $variation['variation_id'],
                            'name' => implode(', ', $attributes),
                            'price' => $variation_obj->get_price()
                        );
                    }
                }
            }

            $products_data[] = $product_info;
        }

        wp_send_json_success(array(
            'products' => $products_data,
            'has_variable_products' => $has_variable_products
        ));
    }
    /**
     * Get products and variations by categories for dynamic select
     */
    public function get_products_by_categories() {
        // Verify nonce
        $nonce_valid = false;
        if (isset($_POST['nonce']) && wp_verify_nonce($_POST['nonce'], 'mmd_search_products')) {
            $nonce_valid = true;
        }

        if (!$nonce_valid) {
            wp_send_json_error('Invalid nonce');
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
            return;
        }

        $category_slugs = isset($_POST['categories']) && is_array($_POST['categories'])
            ? array_map('sanitize_text_field', $_POST['categories'])
            : array();

        if (empty($category_slugs)) {
            wp_send_json_success(array('products' => array()));
            return;
        }

        $results = array();

        // Get products from the selected categories
        $args = array(
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'tax_query' => array(
                array(
                    'taxonomy' => 'product_cat',
                    'field' => 'slug',
                    'terms' => $category_slugs,
                ),
            ),
        );

        $products = get_posts($args);

        foreach ($products as $product_post) {
            $product = wc_get_product($product_post->ID);
            if (!$product) {
                continue;
            }

            // For variable products, add each variation
            if ($product->is_type('variable')) {
                $variations = $product->get_available_variations();
                foreach ($variations as $variation) {
                    $variation_obj = wc_get_product($variation['variation_id']);
                    if (!$variation_obj) {
                        continue;
                    }
                    $variation_name = $variation_obj->get_name();
                    $results[] = array(
                        'id' => $variation['variation_id'],
                        'name' => $variation_name,
                        'text' => $variation_name . ' (#' . $variation['variation_id'] . ')',
                        'parent_id' => $product_post->ID,
                        'parent_name' => $product->get_name(),
                        'is_variation' => true,
                        'price' => $variation_obj->get_price(),
                        'in_stock' => $variation_obj->is_in_stock()
                    );
                }
            } else {
                // Simple product
                $results[] = array(
                    'id' => $product_post->ID,
                    'name' => $product->get_name(),
                    'text' => $product->get_name() . ' (#' . $product_post->ID . ')',
                    'parent_id' => null,
                    'parent_name' => null,
                    'is_variation' => false,
                    'price' => $product->get_price(),
                    'in_stock' => $product->is_in_stock()
                );
            }
        }

        wp_send_json_success(array('products' => $results));
    }
}

new MMD_Ajax();
