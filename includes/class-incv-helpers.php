<?php
/**
 * Helper Functions
 */

if (!defined('ABSPATH')) {
    exit;
}

class INCV_Helpers {

    /**
     * Number of inline script handles queued on this request.
     *
     * @var int
     */
    private static $inline_scripts = 0;

    /**
     * JSON flags for values placed inside inline scripts (no "</script>" break-out).
     */
    const JSON_INLINE = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;

    /**
     * Queue page-specific JavaScript through the script API instead of
     * printing a <script> tag in the markup.
     *
     * The code goes on its own footer handle (no file) with wp_add_inline_script().
     * The handles are placed at the front of the footer queue, in call order,
     * so they still run before the plugin's footer files, as the markup
     * blocks they replace did. Used only for code that carries per-page data.
     *
     * During AJAX requests, or once the footer scripts have printed, the code is
     * printed with wp_print_inline_script_tag() instead.
     *
     * @param string   $js   JavaScript, without <script> tags.
     * @param string[] $deps Script dependencies.
     * @return bool Whether the code was queued.
     */
    /**
     * Run a callback that prints markup and return what it printed.
     * The output buffer is opened and closed here, in the same scope.
     *
     * @param callable $callback Prints the inline CSS or JS.
     * @return string
     */
    public static function capture($callback) {
        ob_start();
        try {
            call_user_func($callback);
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string) ob_get_clean();
    }

    public static function add_inline_script($js, $deps = array('jquery')) {
        $js = trim((string) $js);
        if ('' === $js) {
            return false;
        }
        // AJAX-rendered fragments (quick views, Elementor previews) and markup printed
        // after the footer scripts have no queue to join: use core's inline tag API.
        if (wp_doing_ajax() || did_action('wp_print_footer_scripts') || did_action('admin_print_footer_scripts')) {
            wp_print_inline_script_tag($js);
            return true;
        }
        $handle = 'incv-inline-' . (++self::$inline_scripts);
        wp_register_script($handle, false, (array) $deps, INCV_VERSION, true);
        wp_add_inline_script($handle, $js);
        wp_enqueue_script($handle);

        // Move the handle to the front of the queue (after earlier inline handles).
        $scripts = wp_scripts();
        $scripts->queue = array_values(array_diff($scripts->queue, array($handle)));
        array_splice($scripts->queue, self::$inline_scripts - 1, 0, array($handle));
        return true;
    }

    /**
     * Small front-end helper script (close buttons on notices and the debug panel).
     * Enqueued only when markup that needs it is printed.
     */
    public static function enqueue_frontend_ui() {
        wp_enqueue_script('incv-frontend-ui', INCV_PLUGIN_URL . 'assets/frontend-ui.js', array(), INCV_VERSION, true);
    }

    /**
     * Queue page-specific CSS through the style API instead of a <style> tag.
     * Styles queued after <head> was printed are printed in the footer by WordPress.
     *
     * @param string $css CSS, without <style> tags.
     * @return bool Whether the CSS was queued.
     */
    public static function add_inline_style($css) {
        $css = trim((string) $css);
        if ('' === $css || wp_doing_ajax()) {
            return false;
        }
        $handle = 'incv-inline-css-' . substr(md5($css), 0, 12);
        if (!wp_style_is($handle, 'registered')) {
            wp_register_style($handle, false, array(), INCV_VERSION);
            wp_add_inline_style($handle, $css);
        }
        wp_enqueue_style($handle);
        return true;
    }

    /**
     * Debug logging is off unless the site sets WP_DEBUG and INCENTIV_DEBUG
     * (or INCV_DEBUG) to true in wp-config.php.
     */
    public static function debug_enabled() {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return false;
        }
        return (defined('INCENTIV_DEBUG') && INCENTIV_DEBUG) || (defined('INCV_DEBUG') && INCV_DEBUG);
    }

    /**
     * Front-end debug output (browser console lines, the cart debug panel) is only
     * ever printed for a logged-in user who can manage WooCommerce, never for visitors.
     */
    public static function can_see_frontend_debug() {
        return is_user_logged_in() && current_user_can('manage_woocommerce');
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
        
        $settings = get_option('incv_settings', $defaults);
        return wp_parse_args($settings, $defaults);
    }
    
    /**
     * Update plugin settings
     */
    public static function update_settings($new_settings) {
        return update_option('incv_settings', $new_settings);
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
