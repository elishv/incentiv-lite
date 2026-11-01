<?php
/**
 * MMD Rule Banner — display rule banners on product pages.
 * Provides:
 *   - get_banner_html_for_product($product_id, $rule_id = '')
 *   - [mmd_rule_banner] shortcode
 *   - Elementor widget registration
 */

if (!defined('ABSPATH')) {
    exit;
}

class MMD_Rule_Banner {

    private static $instance = null;

    public static function instance() {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_shortcode('mmd_rule_banner', array($this, 'shortcode'));

        // Elementor widget registration (only if Elementor is loaded)
        add_action('elementor/widgets/register', array($this, 'register_elementor_widget'));
        // Backward compatibility for older Elementor versions
        add_action('elementor/widgets/widgets_registered', array($this, 'register_elementor_widget'));
    }

    /**
     * Find an active rule with a configured banner that matches a given product.
     *
     * @param int    $product_id Product or variation ID. Defaults to current global $product.
     * @param string $rule_id    Optional specific rule ID. If empty, finds the first matching rule.
     * @return array|null The matching rule set, or null if none.
     */
    public function find_matching_rule($product_id = 0, $rule_id = '') {
        if (!class_exists('MMD_Discount_Rules')) return null;

        if (!$product_id) {
            global $product;
            if ($product && is_object($product)) {
                $product_id = $product->get_id();
            }
        }

        $product = $product_id ? wc_get_product($product_id) : null;
        if (!$product) return null;

        $rule_sets = MMD_Discount_Rules::get_all_rule_sets();
        if (empty($rule_sets)) return null;

        // Build a cart_item-like array so the qualifying check works
        $parent_id = $product->get_parent_id() ? $product->get_parent_id() : $product->get_id();
        $variation_id = $product->is_type('variation') ? $product->get_id() : 0;
        $cart_item = array(
            'product_id' => $parent_id,
            'variation_id' => $variation_id,
            'data' => $product,
        );

        $qualifying = MMD_Discount_Rules::get_qualifying_rule_sets($product, $cart_item);

        foreach ($qualifying as $rule_set) {
            if (!empty($rule_set['enabled']) && $rule_set['enabled'] !== 'yes') continue;
            if (!MMD_Discount_Rules::is_rule_in_schedule_public($rule_set)) continue;

            // If a specific rule_id was requested, only that one matches
            if ($rule_id !== '' && (!isset($rule_set['id']) || $rule_set['id'] !== $rule_id)) continue;

            // Rule must have a banner configured
            $has_image = !empty($rule_set['banner_image_id']) || !empty($rule_set['banner_image_url']);
            if (!$has_image) continue;

            return $rule_set;
        }

        // If a specific rule_id was requested but didn't match qualifying rules, try direct lookup.
        // This lets you manually place a banner shortcode showing a rule even if the current product
        // doesn't strictly qualify (useful for hand-curated landing pages).
        if ($rule_id !== '') {
            foreach ($rule_sets as $rule_set) {
                if (isset($rule_set['id']) && $rule_set['id'] === $rule_id) {
                    $has_image = !empty($rule_set['banner_image_id']) || !empty($rule_set['banner_image_url']);
                    if ($has_image) return $rule_set;
                }
            }
        }

        return null;
    }

    /**
     * Get banner HTML for a product, or empty string if no matching rule has a banner.
     */
    public function get_banner_html_for_product($product_id = 0, $rule_id = '') {
        $rule_set = $this->find_matching_rule($product_id, $rule_id);
        if (!$rule_set) return '';

        $image_url = '';
        if (!empty($rule_set['banner_image_id'])) {
            $image_url = wp_get_attachment_image_url(intval($rule_set['banner_image_id']), 'full');
        }
        if (!$image_url && !empty($rule_set['banner_image_url'])) {
            $image_url = $rule_set['banner_image_url'];
        }
        if (!$image_url) return '';

        $alt = isset($rule_set['name']) ? $rule_set['name'] : '';
        $link = isset($rule_set['banner_link_url']) ? trim($rule_set['banner_link_url']) : '';

        $img = sprintf(
            '<img src="%s" alt="%s" class="mmd-rule-banner-image" style="max-width:100%%;height:auto;display:block;">',
            esc_url($image_url),
            esc_attr($alt)
        );

        if ($link) {
            $img = sprintf(
                '<a href="%s" class="mmd-rule-banner-link">%s</a>',
                esc_url($link),
                $img
            );
        }

        return '<div class="mmd-rule-banner" data-rule-id="' . esc_attr(isset($rule_set['id']) ? $rule_set['id'] : '') . '">' . $img . '</div>';
    }

    /**
     * Shortcode: [mmd_rule_banner] or [mmd_rule_banner rule_id="abc" product_id="123"]
     */
    public function shortcode($atts) {
        $atts = shortcode_atts(array(
            'rule_id' => '',
            'product_id' => 0,
        ), $atts, 'mmd_rule_banner');

        return $this->get_banner_html_for_product(intval($atts['product_id']), $atts['rule_id']);
    }

    /**
     * Register the Elementor widget.
     */
    public function register_elementor_widget($widgets_manager) {
        if (!did_action('elementor/loaded')) return;

        require_once MMD_PLUGIN_DIR . 'includes/class-mmd-rule-banner-elementor-widget.php';

        if (class_exists('MMD_Rule_Banner_Elementor_Widget') && $widgets_manager && method_exists($widgets_manager, 'register')) {
            $widgets_manager->register(new MMD_Rule_Banner_Elementor_Widget());
        } elseif (class_exists('MMD_Rule_Banner_Elementor_Widget') && $widgets_manager && method_exists($widgets_manager, 'register_widget_type')) {
            $widgets_manager->register_widget_type(new MMD_Rule_Banner_Elementor_Widget());
        }
    }
}
