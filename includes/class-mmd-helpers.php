<?php
/**
 * Helper Functions
 */

if (!defined('ABSPATH')) {
    exit;
}

class MMD_Helpers {

    /**
     * Debug logging is off unless the site sets WP_DEBUG and INCENTIV_DEBUG
     * (or the older MMD_DEBUG) to true in wp-config.php.
     */
    public static function debug_enabled() {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return false;
        }
        return (defined('INCENTIV_DEBUG') && INCENTIV_DEBUG) || (defined('MMD_DEBUG') && MMD_DEBUG);
    }

    /**
     * Write a line to the PHP error log, only when debug_enabled().
     *
     * @param string $message Message.
     * @param mixed  $data    Optional data, JSON-encoded.
     */
    public static function log($message, $data = null) {
        if (!self::debug_enabled()) {
            return;
        }
        if (null !== $data) {
            $message .= ' ' . wp_json_encode($data);
        }
        error_log('[Incentiv] ' . $message); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Only runs with WP_DEBUG and INCENTIV_DEBUG on.
    }
    
    /**
     * Get plugin settings
     */
    public static function get_settings() {
        $defaults = array(
            'enabled' => 'yes',
            'selection_method' => 'category',
            'included_categories' => array(),
            'excluded_categories' => array(),
            'included_products' => array(),
            'excluded_products' => array(),
            'attribute_slug' => '',
            'attribute_values' => array(),
            'attribute_excluded_values' => array(),
            'discount_rules' => array(),
            'apply_to' => 'cheapest',
            'notice_enabled' => 'yes',
            'notice_title' => __( '☕ Buy more and save!', 'incentiv' ),
            'notice_content' => '',
            'notice_color' => '#a16a3f',
            'discount_label' => __( '☕ Quantity discount', 'incentiv' ),
            'show_in_mini_cart' => 'yes',
            'debug_mode' => 'no',
        );
        
        $settings = get_option('mmd_settings', $defaults);
        return wp_parse_args($settings, $defaults);
    }
    
    /**
     * Update plugin settings
     */
    public static function update_settings($new_settings) {
        return update_option('mmd_settings', $new_settings);
    }
    
    /**
     * Get discount rules sorted by quantity
     */
    public static function get_discount_rules() {
        $settings = self::get_settings();
        $rules = isset($settings['discount_rules']) ? $settings['discount_rules'] : array();
        
        // Sort by quantity
        usort($rules, function($a, $b) {
            return $a['quantity'] - $b['quantity'];
        });
        
        return $rules;
    }
    
    /**
     * Format discount for display
     */
    public static function format_discount($rule) {
        if ($rule['type'] === 'percentage') {
            if ($rule['discount'] == 100) {
                return __( 'Free', 'incentiv' );
            }
            return $rule['discount'] . '%';
        } else {
            return wc_price($rule['discount']);
        }
    }
    
    /**
     * Calculate discount amount
     */
    public static function calculate_discount_amount($price, $rule) {
        if ($rule['type'] === 'percentage') {
            return $price * ($rule['discount'] / 100);
        } else {
            return min($rule['discount'], $price);
        }
    }
    
    /**
     * Check if product qualifies
     */
    public static function is_product_qualifying($product, $cart_item) {
        $settings = self::get_settings();
        $product_id = isset($cart_item['product_id']) ? $cart_item['product_id'] : 0;
        $variation_id = isset($cart_item['variation_id']) ? $cart_item['variation_id'] : 0;
        
        // Check excluded products first
        if (!empty($settings['excluded_products'])) {
            if (in_array($product_id, $settings['excluded_products']) || 
                ($variation_id && in_array($variation_id, $settings['excluded_products']))) {
                return false;
            }
        }
        
        $method = $settings['selection_method'];
        
        switch ($method) {
            case 'all':
                return true;
                
            case 'category':
                return self::check_by_category($product_id, $settings);
                
            case 'product_ids':
                return self::check_by_product_ids($product_id, $variation_id, $settings);
                
            case 'attribute':
                return self::check_by_attribute($product, $settings);
                
            default:
                return false;
        }
    }
    
    /**
     * Check by category
     */
    private static function check_by_category($product_id, $settings) {
        // Check excluded categories
        if (!empty($settings['excluded_categories'])) {
            foreach ($settings['excluded_categories'] as $cat_slug) {
                if (has_term($cat_slug, 'product_cat', $product_id)) {
                    return false;
                }
            }
        }
        
        // Check included categories
        if (empty($settings['included_categories'])) {
            return false;
        }
        
        foreach ($settings['included_categories'] as $cat_slug) {
            if (has_term($cat_slug, 'product_cat', $product_id)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check by product IDs
     */
    private static function check_by_product_ids($product_id, $variation_id, $settings) {
        $included = $settings['included_products'];
        
        if (empty($included)) {
            return false;
        }
        
        return in_array($product_id, $included) || ($variation_id && in_array($variation_id, $included));
    }
    
    /**
     * Check by attribute
     */
    private static function check_by_attribute($product, $settings) {
        if (empty($settings['attribute_slug']) || empty($settings['attribute_values'])) {
            return false;
        }

        $attribute_slug = $settings['attribute_slug'];
        $target_values = $settings['attribute_values'];
        $excluded_values = isset($settings['attribute_excluded_values']) ? $settings['attribute_excluded_values'] : array();

        if ($product->is_type('variation')) {
            $attributes = $product->get_variation_attributes();

            foreach ($attributes as $attr_key => $attr_value) {
                if (strpos($attr_key, $attribute_slug) !== false) {
                    $attr_value_clean = strtolower(trim($attr_value));

                    // Check excluded values first
                    if (!empty($excluded_values)) {
                        foreach ($excluded_values as $excluded_value) {
                            $excluded_value_clean = strtolower(trim($excluded_value));
                            if ($attr_value_clean === $excluded_value_clean ||
                                stripos($attr_value, $excluded_value) !== false) {
                                return false;
                            }
                        }
                    }

                    // Check included values
                    foreach ($target_values as $target_value) {
                        $target_value_clean = strtolower(trim($target_value));

                        if ($attr_value_clean === $target_value_clean ||
                            stripos($attr_value, $target_value) !== false) {
                            return true;
                        }
                    }
                }
            }
        }

        return false;
    }
}
