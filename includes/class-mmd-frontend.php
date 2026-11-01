<?php
/**
 * Frontend Functionality
 */

if (!defined('ABSPATH')) {
    exit;
}

class MMD_Frontend {

    /**
     * Cache for active override coupons to avoid repeated lookups
     */
    private $active_override_coupons = null;

    /**
     * Cache for active override rules
     */
    private $active_override_rules_cache = null;
    private $is_doing_cart_calculations = false;
    /**
     * Re-entry guard for the price filter callbacks. `get_discounted_price_for_product()`
     * calls `$product->get_price()` / `get_regular_price()` which fire the same filters,
     * leading to infinite recursion (Maximum call stack size fatal) when a product has
     * no regular_price set.
     */
    private $is_calculating_price = false;

    /**
     * Per-cart breakdown of discount effects, populated by apply_rule_set_discount.
     * Map: cart_item_key => array of entries with units_discounted, discount_total, etc.
     */
    private $cart_discount_breakdown = array();

    /**
     * Map of cart-fee label => rule id for the fees added this request, so the
     * resulting order line can be stamped for budget caps and per-rule reporting.
     */
    private $pending_fee_rule_map = array();

    /**
     * How each rule's discount was split across cart lines this request.
     * Map: cart_item_key => [ ['rule_id' =>, 'rule_name' =>, 'amount' =>], ... ]
     */
    private $cart_allocation = array();

    /**
     * Base (pre-allocation) unit price per cart line for this request, captured
     * once so repeated total calculations recompute from the same starting
     * point instead of compounding the discount.
     */
    private $allocation_base_prices = array();

    /**
     * Rule ids whose discount was already pushed onto the product lines this
     * request, so the fee pass doesn't also add a cart fee for them.
     */
    private $allocated_rule_ids = array();

    public function __construct() {
        // Stamp Incentiv fee lines with the rule that produced them.
        add_action('woocommerce_checkout_create_order_fee_item', array($this, 'stamp_fee_rule_id'), 10, 4);
        // Carry the per-product discount allocation onto the order lines.
        add_action('woocommerce_checkout_create_order_line_item', array($this, 'stamp_line_allocation'), 20, 4);
        // Rules that allocate their discount onto products must run before line
        // totals are computed (the fees hook is too late to change prices).
        add_action('woocommerce_before_calculate_totals', array($this, 'apply_allocated_discounts'), 30, 1);
        // Allocated discounts are already inside the line prices (struck through,
        // with the rule name under the line), so they get no extra totals row: a
        // second "-amount" row above the total read like a double discount.
        // render_allocated_discount_rows() stays available for themes that want it:
        // add_action('woocommerce_cart_totals_before_order_total', array(MMD_Frontend instance, 'render_allocated_discount_rows'));
        // Auto-apply coupon from URL parameter - run on multiple hooks to ensure it works on all pages
        add_action('init', array($this, 'maybe_apply_coupon_from_url'), 99);
        add_action('wp_loaded', array($this, 'maybe_apply_coupon_from_url'), 20);
        add_action('template_redirect', array($this, 'maybe_apply_coupon_from_url'), 5);
        add_action('woocommerce_before_single_product', array($this, 'maybe_apply_coupon_from_url'), 5);
        add_action('woocommerce_add_to_cart', array($this, 'apply_pending_coupon_after_add_to_cart'), 10);

        // Add discount calculation hooks
        add_action('woocommerce_cart_calculate_fees', array($this, 'calculate_discount'));
        add_action('woocommerce_before_mini_cart_contents', array($this, 'calculate_discount'));
        add_action('woocommerce_widget_shopping_cart_before_buttons', array($this, 'calculate_discount'));

        // Override mini cart subtotal to ensure it matches the DB regular prices used in cart display
        add_filter('woocommerce_cart_subtotal', array($this, 'override_cart_subtotal_display'), 9999, 3);
        add_filter('woocommerce_widget_cart_get_total_html', array($this, 'override_mini_cart_total'), 9999);

        // Visible debug panel (toggle via ?mmd_debug=1 in URL or define MMD_DEBUG_CART)
        if ($this->is_debug_enabled()) {
            add_action('woocommerce_before_mini_cart_contents', array($this, 'render_debug_panel'), 999);
            add_action('woocommerce_before_cart', array($this, 'render_debug_panel'), 999);
            add_action('woocommerce_before_checkout_form', array($this, 'render_debug_panel'), 999);
        }

        // Reset prices to regular for override rules (runs before totals calculation) - HIGH PRIORITY
        add_action('woocommerce_before_calculate_totals', array($this, 'reset_prices_for_override_rules'), 9999, 1);

        // Price display on product pages - show discounted price when discount rule applies
        add_filter('woocommerce_get_price_html', array($this, 'modify_price_html_for_override'), 9999, 2);

        // Override cart item price display
        add_filter('woocommerce_cart_item_price', array($this, 'override_cart_item_price_display'), 9999, 3);
        add_filter('woocommerce_cart_item_subtotal', array($this, 'override_cart_item_subtotal_display'), 9999, 3);

        // Override mini cart item price display
        add_filter('woocommerce_widget_cart_item_quantity', array($this, 'override_mini_cart_item_quantity'), 9999, 3);

        // Override checkout order review prices
        add_filter('woocommerce_checkout_cart_item_quantity', array($this, 'override_checkout_item_quantity'), 9999, 3);

        // Disable other discount plugins when override rule is active
        add_filter('advanced_woo_discount_rules_applied_rules', array($this, 'disable_advanced_discounts'), 9999, 1);
        add_filter('wdr_process_rules', array($this, 'disable_wdr_rules'), 10, 1);

        // Prevent other coupons when override rule coupon is active
        add_filter('woocommerce_coupon_is_valid', array($this, 'prevent_other_coupons'), 10, 2);

        // Display notices
        add_action('woocommerce_before_cart', array($this, 'display_discount_notice'));
        add_action('woocommerce_before_checkout_form', array($this, 'display_discount_notice'), 5);
        add_action('woocommerce_single_product_summary', array($this, 'display_override_notice_on_product'), 15);

        // Product-page quantity tier table ("קנו יותר, חסכו יותר")
        add_action('woocommerce_single_product_summary', array($this, 'display_product_tier_table'), 25);

        // Debug info for admins
        add_action('woocommerce_single_product_summary', array($this, 'display_override_debug_info'), 99);
        add_action('woocommerce_after_single_product_summary', array($this, 'display_override_debug_info'), 5);
        add_action('wp_footer', array($this, 'display_override_debug_info_footer'));

        // Ensure WooCommerce notices are displayed on product pages
        add_action('woocommerce_before_single_product', array($this, 'display_wc_notices_on_product'), 10);

        // Mini cart
        add_action('woocommerce_widget_shopping_cart_before_buttons', array($this, 'display_discount_in_mini_cart'), 5);

        // AJAX and cart updates
        add_filter('woocommerce_add_to_cart_fragments', array($this, 'update_mini_cart_fragments'));
        add_filter('woodmart_add_to_cart_fragments', array($this, 'update_mini_cart_fragments'));
        add_action('woocommerce_after_cart_item_quantity_update', array($this, 'recalculate_discount'), 20, 2);
        add_action('woocommerce_cart_item_removed', array($this, 'maybe_remove_discount'));
        add_action('woocommerce_before_calculate_totals', array($this, 'maybe_remove_discount'));

        // Clear cache when coupons change
        add_action('woocommerce_applied_coupon', array($this, 'clear_override_cache'));
        add_action('woocommerce_removed_coupon', array($this, 'clear_override_cache'));
        add_action('woocommerce_removed_coupon', array($this, 'clear_active_coupon_session'));

        // Clear session coupon when order is completed
        add_action('woocommerce_thankyou', array($this, 'clear_coupon_session_on_order_complete'));

        // Track rule usage on order completion
        add_action('woocommerce_checkout_order_processed', array($this, 'save_applied_rules_to_order'), 10, 1);
        add_action('woocommerce_order_status_completed', array($this, 'increment_rule_usage'), 10, 1);
        add_action('woocommerce_order_status_processing', array($this, 'increment_rule_usage'), 10, 1);

        // AJAX handler
        add_action('wp_ajax_get_refreshed_fragments', array($this, 'handle_refreshed_fragments'));
        add_action('wp_ajax_nopriv_get_refreshed_fragments', array($this, 'handle_refreshed_fragments'));

        // Scripts and styles
        add_action('wp_footer', array($this, 'add_frontend_scripts'));
        add_action('wp_head', array($this, 'add_frontend_styles'));

        // Discount badge on product page and archive/shop pages
        add_action('woocommerce_before_single_product_summary', array($this, 'display_discount_badge_on_product'), 11);
        add_action('woocommerce_before_shop_loop_item_title', array($this, 'display_discount_badge_on_archive'), 11);

        // Global discount notice bar across the site
        add_action('wp_footer', array($this, 'display_global_discount_notices'), 5);

        // Integration with Unico Elementor add-to-cart widget
        add_action('unico_add_to_cart_notices', array($this, 'display_discount_notice_for_widget'), 10, 1);
    }

    /**
     * Flag to prevent multiple executions of coupon URL handler
     */
    private $coupon_url_handled = false;

    /**
     * Maybe apply coupon from URL parameter
     */
    public function maybe_apply_coupon_from_url() {
        // Prevent running multiple times per request
        if ($this->coupon_url_handled) {
            return;
        }

        // Skip auto-apply during coupon removal requests
        // Read-only checks of WooCommerce's own request (WooCommerce verifies its nonces).
        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        if (
            (isset($_REQUEST['wc-ajax']) && 'remove_coupon' === sanitize_key(wp_unslash($_REQUEST['wc-ajax']))) ||
            !empty($_GET['remove_coupon'])
        ) {
            return;
        }
        // phpcs:enable WordPress.Security.NonceVerification.Recommended

        // Check for coupon in URL (supports multiple parameter names)
        $coupon_code = '';
        $coupon_code = self::get_url_coupon_param();

        if (empty($coupon_code)) {
            return;
        }

        // Ensure WooCommerce session is initialized
        if (!WC()->session) {
            return; // Will try again on next hook
        }

        // Check if this coupon was explicitly removed by user - don't re-apply
        $removed_coupon = WC()->session->get('mmd_coupon_removed');
        if (!empty($removed_coupon) && strtolower($removed_coupon) === strtolower($coupon_code)) {
            // User removed this coupon, don't auto-apply again
            return;
        }

        // Store coupon in session for later use
        $coupon_code_lower = strtolower($coupon_code);
        WC()->session->set('mmd_pending_coupon', $coupon_code_lower);
        WC()->session->set('mmd_active_override_coupon', $coupon_code_lower);
        // Also store as original URL coupon for tracking purposes (never cleared until order complete)
        WC()->session->set('mmd_original_url_coupon', $coupon_code_lower);

        // WC_Cart::get_cart() triggers _doing_it_wrong before wp_loaded. Defer the
        // cart-touching work to a later hook; session writes above are still safe.
        if (!did_action('wp_loaded')) {
            return;
        }

        // Mark as handled
        $this->coupon_url_handled = true;

        // If cart exists and has items, apply the coupon now
        if (WC()->cart && !WC()->cart->is_empty()) {
            $this->apply_coupon_to_cart($coupon_code);
        }

        // Display notice that coupon was applied
        if (function_exists('wc_add_notice') && !wc_has_notice(__('Coupon added - the discount will apply in the cart', 'incentiv'), 'success')) {
            wc_add_notice(
                /* translators: %s: coupon code */
                sprintf(__('Coupon %s added - the discount will apply in the cart', 'incentiv'), '<strong>' . esc_html($coupon_code) . '</strong>'),
                'success'
            );
        }
    }

    /**
     * Apply pending coupon after adding to cart
     */
    public function apply_pending_coupon_after_add_to_cart() {
        if (!WC()->session) {
            return;
        }

        $pending_coupon = WC()->session->get('mmd_pending_coupon');
        if (!empty($pending_coupon) && WC()->cart) {
            $this->apply_coupon_to_cart($pending_coupon);
        }
    }

    /**
     * Apply coupon to cart
     */
    private function apply_coupon_to_cart($coupon_code) {
        if (!WC()->cart) {
            return;
        }

        $coupon_code = strtolower(trim($coupon_code));
        $applied_coupons = array_map('strtolower', WC()->cart->get_applied_coupons());

        if (!in_array($coupon_code, $applied_coupons)) {
            // Verify coupon exists in WooCommerce
            $coupon = new WC_Coupon($coupon_code);
            if ($coupon->get_id() > 0) {
                WC()->cart->apply_coupon($coupon_code);
                // Keep the coupon in session for price display purposes
                // Store it as "active" instead of clearing it
                if (WC()->session) {
                    WC()->session->set('mmd_active_override_coupon', $coupon_code);
                }
            }
        }
    }

    /**
     * Get pending coupon from session or URL
     */
    private function get_pending_coupon() {
        // Get removed coupon to check against
        $removed_coupon = '';
        if (WC()->session) {
            $removed_coupon = strtolower(WC()->session->get('mmd_coupon_removed') ?: '');
        }

        // Check URL first, but respect removed flag
        $url_coupon = '';
        $url_coupon = strtolower(self::get_url_coupon_param());

        // If URL coupon was explicitly removed, don't return it
        if (!empty($url_coupon) && $url_coupon !== $removed_coupon) {
            return $url_coupon;
        }

        // Check session for pending coupon
        if (WC()->session) {
            $pending = WC()->session->get('mmd_pending_coupon');
            if (!empty($pending) && strtolower($pending) !== $removed_coupon) {
                return strtolower($pending);
            }

            // Check for active override coupon (already applied but kept for display)
            $active = WC()->session->get('mmd_active_override_coupon');
            if (!empty($active) && strtolower($active) !== $removed_coupon) {
                return strtolower($active);
            }
        }

        return '';
    }

    /**
     * Clear the override coupons cache
     */
    public function clear_override_cache() {
        $this->active_override_coupons = null;
        $this->active_override_rules_cache = null;
    }

    /**
     * Clear active coupon from session when coupon is removed from cart
     */
    public function clear_active_coupon_session($coupon_code) {
        if (!WC()->session) {
            return;
        }

        $coupon_code_lower = strtolower($coupon_code);

        // Check if this coupon was applied via URL (stored in session)
        $active = WC()->session->get('mmd_active_override_coupon');
        $pending = WC()->session->get('mmd_pending_coupon');
        $was_url_coupon = false;

        if (!empty($active) && strtolower($active) === $coupon_code_lower) {
            WC()->session->set('mmd_active_override_coupon', '');
            $was_url_coupon = true;
        }
        if (!empty($pending) && strtolower($pending) === $coupon_code_lower) {
            WC()->session->set('mmd_pending_coupon', '');
            $was_url_coupon = true;
        }

        // Also check if this coupon matches the original URL coupon (stored separately)
        $original_url_coupon = WC()->session->get('mmd_original_url_coupon');
        if (!empty($original_url_coupon) && strtolower($original_url_coupon) === $coupon_code_lower) {
            $was_url_coupon = true;
        }

        // Check current URL referrer for coupon parameters (in case session was lost)
        $referrer = wp_get_referer();
        if ($referrer) {
            $parsed = wp_parse_url($referrer);
            if (!empty($parsed['query'])) {
                parse_str($parsed['query'], $query_params);
                foreach (array('coupon', 'coupon_code', 'coupon-code') as $param) {
                    if (!empty($query_params[$param]) && strtolower($query_params[$param]) === $coupon_code_lower) {
                        $was_url_coupon = true;
                        break;
                    }
                }
            }
        }

        // If this coupon was applied via URL, mark as removed to prevent re-application
        if ($was_url_coupon) {
            WC()->session->set('mmd_coupon_removed', $coupon_code_lower);
            return;
        }

        // Also check if this coupon is an override rule coupon - if so, mark as removed
        $rule_sets = MMD_Discount_Rules::get_all_rule_sets();
        foreach ($rule_sets as $rule_set) {
            if (empty($rule_set['required_coupon_codes'])) {
                continue;
            }
            $required_coupons = is_array($rule_set['required_coupon_codes'])
                ? $rule_set['required_coupon_codes']
                : array_map('trim', explode(',', $rule_set['required_coupon_codes']));

            foreach ($required_coupons as $required_coupon) {
                if (strtolower(trim($required_coupon)) === $coupon_code_lower) {
                    // This is an override coupon - mark as explicitly removed
                    WC()->session->set('mmd_coupon_removed', $coupon_code_lower);
                    return;
                }
            }
        }
    }

    /**
     * Clear session coupon when order is completed
     */
    public function clear_coupon_session_on_order_complete($order_id) {
        if (!WC()->session) {
            return;
        }

        WC()->session->set('mmd_active_override_coupon', '');
        WC()->session->set('mmd_pending_coupon', '');
        WC()->session->set('mmd_original_url_coupon', '');
        WC()->session->set('mmd_coupon_removed', ''); // Allow coupon to be used again on next visit
    }

    /**
     * Get all coupons that could be active (from cart + URL + session)
     */
    private function get_all_potential_coupons() {
        $coupons = array();

        // Get the removed coupon (if any) to exclude it
        $removed_coupon = '';
        if (WC()->session) {
            $removed_coupon = WC()->session->get('mmd_coupon_removed');
        }

        // Check URL for coupon - but skip if it was explicitly removed
        $url_coupon = '';
        $url_coupon = strtolower(self::get_url_coupon_param());

        // Only include URL coupon if it wasn't removed by user
        if (!empty($url_coupon) && $url_coupon !== $removed_coupon) {
            $coupons[] = $url_coupon;
        }

        // From cart - these are currently applied, so include them
        if (WC()->cart) {
            $coupons = array_merge($coupons, array_map('strtolower', WC()->cart->get_applied_coupons()));
        }

        // From session (as fallback) - but skip if removed
        if (WC()->session) {
            $pending = WC()->session->get('mmd_pending_coupon');
            if (!empty($pending) && strtolower($pending) !== $removed_coupon) {
                $coupons[] = strtolower($pending);
            }

            $active = WC()->session->get('mmd_active_override_coupon');
            if (!empty($active) && strtolower($active) !== $removed_coupon) {
                $coupons[] = strtolower($active);
            }
        }

        return array_unique($coupons);
    }

    /**
     * Get active override rules (checks both cart and URL/session)
     */
    private function get_active_override_rules() {
        // Check if coupon is in URL - if so, don't use cache (state might have changed)
        $coupon_in_url = isset($_GET['coupon-code']) || isset($_GET['coupon_code']) || isset($_GET['coupon']); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Presence check only (public promotion link).

        if ($this->active_override_rules_cache !== null && !$coupon_in_url) {
            return $this->active_override_rules_cache;
        }

        $this->active_override_rules_cache = array();
        $potential_coupons = $this->get_all_potential_coupons();
        $rule_sets = MMD_Discount_Rules::get_all_rule_sets();

        foreach ($rule_sets as $rule_set) {
            // Check enabled - handle various boolean formats
            $is_enabled = isset($rule_set['enabled']) &&
                         ($rule_set['enabled'] === 'yes' || $rule_set['enabled'] === '1' || $rule_set['enabled'] === true || $rule_set['enabled'] === 1);
            if (!$is_enabled) {
                continue;
            }

            // Check override_other_discounts - handle various boolean formats
            $is_override = isset($rule_set['override_other_discounts']) &&
                          ($rule_set['override_other_discounts'] === 'yes' || $rule_set['override_other_discounts'] === '1' || $rule_set['override_other_discounts'] === true || $rule_set['override_other_discounts'] === 1);
            if (!$is_override) {
                continue;
            }

            // Check if this rule is active (coupon requirement check)
            if ($this->is_rule_active($rule_set, $potential_coupons)) {
                $this->active_override_rules_cache[] = $rule_set;
            }
        }

        return $this->active_override_rules_cache;
    }

    /**
     * Check if any override rule is currently active
     */
    private function is_override_active() {
        $active_rules = $this->get_active_override_rules();
        return !empty($active_rules);
    }

    /**
     * Check if a product qualifies for any active override rule
     * @param WC_Product $product The product to check
     * @param array|null $cart_item Optional cart item data (if available)
     * @return array|false The qualifying rule set or false
     */
    private function product_qualifies_for_override($product, $cart_item = null) {
        $active_rules = $this->get_active_override_rules();
        if (empty($active_rules)) {
            return false;
        }

        // Create cart item if not provided
        if ($cart_item === null) {
            $product_id = $product->get_parent_id() ? $product->get_parent_id() : $product->get_id();
            $variation_id = $product->is_type('variation') ? $product->get_id() : 0;
            $cart_item = array(
                'product_id' => $product_id,
                'variation_id' => $variation_id,
                'data' => $product
            );
        }

        // Ensure product_id is set
        if (empty($cart_item['product_id'])) {
            $cart_item['product_id'] = $product->get_parent_id() ? $product->get_parent_id() : $product->get_id();
        }
        if (!isset($cart_item['variation_id'])) {
            $cart_item['variation_id'] = $product->is_type('variation') ? $product->get_id() : 0;
        }

        foreach ($active_rules as $rule_set) {
            $qualifying_rule_sets = MMD_Discount_Rules::get_qualifying_rule_sets($product, $cart_item);
            foreach ($qualifying_rule_sets as $qualifying_set) {
                if ($qualifying_set['id'] === $rule_set['id']) {
                    return $rule_set; // Return the rule set
                }
            }
        }

        return false;
    }

    /**
     * Get the discount for a product based on override rule
     * Uses plugin's discount_rules for display purposes
     */
    private function get_override_discount_for_product($product, $rule_set) {
        // Use plugin's discount_rules for display
        if (empty($rule_set['discount_rules'])) {
            return null;
        }

        // For product page, we assume quantity 1 and show the first applicable rule
        // Sort discount rules by quantity
        $discount_rules = $rule_set['discount_rules'];
        usort($discount_rules, function($a, $b) {
            return $a['quantity'] - $b['quantity'];
        });

        $first_rule = $discount_rules[0];

        // Only return if discount > 0
        if (floatval($first_rule['discount']) <= 0) {
            return null;
        }

        return $first_rule;
    }

    /**
     * Modify price HTML on product pages when a discount rule applies
     */
    public function modify_price_html_for_override($price_html, $product) {
        if (is_admin() || !$product || !is_a($product, 'WC_Product')) {
            return $price_html;
        }

        // Skip variable parent products (let variations handle their own prices)
        if ($product->is_type('variable') || $product->is_type('grouped')) {
            return $price_html;
        }

        // Check override rules first (original behavior)
        $rule_set = $this->product_qualifies_for_override($product);
        if ($rule_set) {
            // If rule requires a coupon, only show override price when coupon is applied
            if (!empty($rule_set['coupon_required']) && $rule_set['coupon_required'] === 'yes') {
                $applied_coupons = WC()->cart ? array_map('strtolower', WC()->cart->get_applied_coupons()) : array();
                if (!$this->is_rule_active($rule_set, $applied_coupons)) {
                    $rule_set = null;
                }
            }
        }
        if ($rule_set) {
            $regular_price = $product->get_regular_price();
            if ($regular_price && $regular_price > 0) {
                $discount_rule = $this->get_override_discount_for_product($product, $rule_set);
                if ($discount_rule) {
                    $discounted_price = $this->calculate_discounted_price($regular_price, $discount_rule);
                    $price_html = '<del>' . wc_price($regular_price) . '</del> ';
                    $price_html .= '<ins>' . wc_price($discounted_price) . '</ins>';
                    return $price_html;
                }
            }
        }

        // Check all rules that have show_sale_price enabled
        // ONLY run on actual product/shop/archive pages, AND not inside a cart/mini-cart/checkout context.
        // (When the mini cart renders on a product page, is_product() is still true — but we must not
        // apply the sale price HTML to cart items, otherwise ALL cart items get the discounted display.)
        $in_cart_context = did_action('woocommerce_before_mini_cart_contents') > 0
            || did_action('woocommerce_before_cart_contents') > 0
            || did_action('woocommerce_before_cart') > 0
            || did_action('woocommerce_before_checkout_form') > 0
            || did_action('woocommerce_review_order_before_cart_contents') > 0
            || (defined('DOING_AJAX') && DOING_AJAX && isset($_REQUEST['wc-ajax'])); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only checks whether this is a WooCommerce AJAX request.

        if (!$in_cart_context && (is_product() || is_shop() || is_product_category() || is_product_tag())) {
            $sale_price_html = $this->get_sale_price_html_for_product($product);
            if ($sale_price_html) {
                return $sale_price_html;
            }
        }

        return $price_html;
    }

    /**
     * Get sale price HTML for a product based on rules with show_sale_price enabled
     */
    private function get_sale_price_html_for_product($product) {
        try {
            $rule_sets = MMD_Discount_Rules::get_all_rule_sets();
            if (empty($rule_sets) || !is_array($rule_sets)) {
                return null;
            }

            $product_id = $product->get_parent_id() ? $product->get_parent_id() : $product->get_id();
            $variation_id = $product->is_type('variation') ? $product->get_id() : 0;
            $cart_item = array(
                'product_id' => $product_id,
                'variation_id' => $variation_id,
                'data' => $product
            );

            // Determine current page context
            $is_product_page = is_product();
            $is_archive_page = is_shop() || is_product_category() || is_product_tag();

            foreach ($rule_sets as $rule_set) {
                if (!is_array($rule_set)) {
                    continue;
                }

                // Must be enabled
                if (empty($rule_set['enabled']) || $rule_set['enabled'] !== 'yes') {
                    continue;
                }

                // If rule requires a coupon, only show sale price when the coupon is applied
                if (!empty($rule_set['coupon_required']) && $rule_set['coupon_required'] === 'yes') {
                    $applied_coupons = WC()->cart ? array_map('strtolower', WC()->cart->get_applied_coupons()) : array();
                    if (!$this->is_rule_active($rule_set, $applied_coupons)) {
                        continue;
                    }
                }

                // Must have show_sale_price turned on
                if (empty($rule_set['show_sale_price']) || $rule_set['show_sale_price'] !== 'yes') {
                    continue;
                }

                // Check where to show
                $show_on = isset($rule_set['show_sale_price_on']) ? $rule_set['show_sale_price_on'] : 'both';
                if ($show_on === 'product' && !$is_product_page) {
                    continue;
                }
                if ($show_on === 'archive' && !$is_archive_page) {
                    continue;
                }

                // Check schedule
                if (!MMD_Discount_Rules::is_rule_in_schedule_public($rule_set)) {
                    continue;
                }

                // Check if product qualifies
                $qualifying = MMD_Discount_Rules::get_qualifying_rule_sets($product, $cart_item);
                $qualifies = false;
                foreach ($qualifying as $qs) {
                    if (isset($qs['id']) && isset($rule_set['id']) && $qs['id'] === $rule_set['id']) {
                        $qualifies = true;
                        break;
                    }
                }
                if (!$qualifies) {
                    continue;
                }

                // Get the discount rule to display
                if (empty($rule_set['discount_rules']) || !is_array($rule_set['discount_rules'])) {
                    continue;
                }

                $discount_rules = $rule_set['discount_rules'];
                usort($discount_rules, function($a, $b) {
                    return (isset($a['quantity']) ? intval($a['quantity']) : 0) - (isset($b['quantity']) ? intval($b['quantity']) : 0);
                });

                // Pick tier based on setting
                $tier = isset($rule_set['sale_price_tier']) ? $rule_set['sale_price_tier'] : 'first';
                $selected_rule = ($tier === 'last') ? end($discount_rules) : reset($discount_rules);

                if (!$selected_rule || !isset($selected_rule['discount']) || floatval($selected_rule['discount']) <= 0) {
                    continue;
                }

                $regular_price = $product->get_regular_price();
                if (!$regular_price || $regular_price <= 0) {
                    $regular_price = $product->get_price();
                }
                if (!$regular_price || $regular_price <= 0) {
                    continue;
                }

                $discounted_price = $this->calculate_discounted_price($regular_price, $selected_rule);
                if ($discounted_price >= $regular_price) {
                    continue;
                }

                return '<del>' . wc_price($regular_price) . '</del> <ins>' . wc_price($discounted_price) . '</ins>';
            }
        } catch (Exception $e) {
            return null;
        }

        return null;
    }

    /**
     * Calculate discounted price based on rule
     */
    private function calculate_discounted_price($price, $discount_rule) {
        if ($discount_rule['type'] === 'percentage') {
            return $price * (1 - ($discount_rule['discount'] / 100));
        } else {
            return max(0, $price - $discount_rule['discount']);
        }
    }

    /**
     * Display notice on product page when override coupon is active
     */
    public function display_override_notice_on_product() {
        if (!is_product()) {
            return;
        }

        global $product;
        if (!$product) {
            return;
        }

        $rule_set = $this->product_qualifies_for_override($product);
        if (!$rule_set) {
            return;
        }

        // If rule requires a coupon, only show when coupon is applied
        if (!empty($rule_set['coupon_required']) && $rule_set['coupon_required'] === 'yes') {
            $applied_coupons = WC()->cart ? array_map('strtolower', WC()->cart->get_applied_coupons()) : array();
            if (!$this->is_rule_active($rule_set, $applied_coupons)) {
                return;
            }
        }

        $discount_rule = $this->get_override_discount_for_product($product, $rule_set);
        if (!$discount_rule) {
            return;
        }

        $discount_text = MMD_Helpers::format_discount($discount_rule);
        $notice_color = !empty($rule_set['notice_color']) ? $rule_set['notice_color'] : '#4CAF50';

        echo '<div class="mmd-override-notice" style="background: #d4edda; border: 2px solid ' . esc_attr($notice_color) . '; padding: 10px; margin: 10px 0; border-radius: 5px; text-align: center;">';
        echo '<strong style="color: ' . esc_attr($notice_color) . ';">🎉 ' . esc_html($rule_set['name']) . '</strong><br>';
        echo ( '<span>' . esc_html__( 'A discount of', 'incentiv' ) . ' ' ) . esc_html($discount_text) . ( ' ' . esc_html__( 'is live!', 'incentiv' ) . '</span>' );
        echo '</div>';
    }

    /**
     * Display discount notices inside Unico Elementor add-to-cart widget
     *
     * @param WC_Product $product The product being displayed
     */
    public function display_discount_notice_for_widget($product) {
        if (!$product) {
            return;
        }

        // Show override notice if applicable
        $rule_set = $this->product_qualifies_for_override($product);
        if ($rule_set) {
            // If rule requires a coupon, skip if not applied
            if (!empty($rule_set['coupon_required']) && $rule_set['coupon_required'] === 'yes') {
                $applied_coupons = WC()->cart ? array_map('strtolower', WC()->cart->get_applied_coupons()) : array();
                if (!$this->is_rule_active($rule_set, $applied_coupons)) {
                    $rule_set = null; // Fall through to quantity notices
                }
            }
        }
        if ($rule_set) {
            $discount_rule = $this->get_override_discount_for_product($product, $rule_set);
            if ($discount_rule) {
                $discount_text = MMD_Helpers::format_discount($discount_rule);
                $notice_color = !empty($rule_set['notice_color']) ? $rule_set['notice_color'] : '#4CAF50';

                echo '<div class="mmd-override-notice" style="background: #d4edda; border: 2px solid ' . esc_attr($notice_color) . '; padding: 10px; margin: 10px 0; border-radius: 5px; text-align: center;">';
                echo '<strong style="color: ' . esc_attr($notice_color) . ';">🎉 ' . esc_html($rule_set['name']) . '</strong><br>';
                echo ( '<span>' . esc_html__( 'A discount of', 'incentiv' ) . ' ' ) . esc_html($discount_text) . ( ' ' . esc_html__( 'is live!', 'incentiv' ) . '</span>' );
                echo '</div>';
                return;
            }
        }

        // Show quantity discount notices for qualifying products
        $product_id = $product->get_id();
        $qualifying_rule_sets = MMD_Discount_Rules::get_qualifying_rule_sets_for_product($product_id);
        if (!empty($qualifying_rule_sets)) {
            foreach ($qualifying_rule_sets as $qs) {
                if (empty($qs['discount_rules'])) {
                    continue;
                }
                if (empty($qs['notice_enabled']) || $qs['notice_enabled'] !== 'yes') {
                    continue;
                }
                // If rule requires a coupon, skip if not applied
                if (!empty($qs['coupon_required']) && $qs['coupon_required'] === 'yes') {
                    $applied_coupons = WC()->cart ? array_map('strtolower', WC()->cart->get_applied_coupons()) : array();
                    if (!$this->is_rule_active($qs, $applied_coupons)) {
                        continue;
                    }
                }

                $notice_color = !empty($qs['notice_color']) ? $qs['notice_color'] : '#4CAF50';
                $notice_title = !empty($qs['notice_title']) ? $qs['notice_title'] : __( '☕ Buy more and save!', 'incentiv' );
                $content = !empty($qs['notice_content'])
                    ? $qs['notice_content']
                    : $this->build_default_notice_content($qs['discount_rules'], $qs);

                echo '<div class="woocommerce-info mmd-discount-notice" style="margin-bottom: 10px; background: #f7f6f7; border-' . ( is_rtl() ? 'right' : 'left' ) . ': 4px solid ' . esc_attr($notice_color) . '; padding: 10px; color: #333;">';
                echo '<strong style="color: ' . esc_attr($notice_color) . '; font-size: 14px;">' . esc_html($notice_title) . '</strong><br>';
                echo '<div style="margin-top: 6px; line-height: 1.5; color: #333; font-size: 13px;">' . wp_kses_post($content) . '</div>';
                echo '</div>';
            }
        }
    }

    /**
     * Display discount badge on single product page (over the product image)
     */
    public function display_discount_badge_on_product() {
        if (!is_product()) {
            return;
        }

        global $product;
        if (!$product) {
            return;
        }

        $this->render_discount_badge($product->get_id(), 'product');
    }

    /**
     * Display discount badge on product archive / shop loop
     */
    public function display_discount_badge_on_archive() {
        global $product;
        if (!$product) {
            return;
        }

        $this->render_discount_badge($product->get_id(), 'archive');
    }

    /**
     * Render discount badge for a product if it qualifies for any badge-enabled rule
     *
     * @param int    $product_id Product ID
     * @param string $context    'product' or 'archive'
     */
    private function render_discount_badge($product_id, $context) {
        static $badge_cache = array();

        $cache_key = $product_id . '_' . $context;
        if (isset($badge_cache[$cache_key])) {
            if ($badge_cache[$cache_key]) {
                echo wp_kses_post( $badge_cache[$cache_key] );
            }
            return;
        }

        // Check both active AND upcoming (scheduled) rules for FOMO badges
        $qualifying_rules = MMD_Discount_Rules::get_qualifying_rule_sets_for_product($product_id);
        $upcoming_rules = MMD_Discount_Rules::get_upcoming_rule_sets_for_product($product_id);
        $all_rules = array_merge($qualifying_rules, $upcoming_rules);

        if (empty($all_rules)) {
            $badge_cache[$cache_key] = false;
            return;
        }

        $output = '';
        foreach ($all_rules as $rule_set) {
            $badge_enabled = isset($rule_set['badge_enabled']) ? $rule_set['badge_enabled'] : 'no';
            if ($badge_enabled !== 'yes') {
                continue;
            }

            // If rule requires a coupon, only show badge when the coupon is applied
            if (!empty($rule_set['coupon_required']) && $rule_set['coupon_required'] === 'yes') {
                $applied_coupons = WC()->cart ? array_map('strtolower', WC()->cart->get_applied_coupons()) : array();
                if (!$this->is_rule_active($rule_set, $applied_coupons)) {
                    continue;
                }
            }

            $show_field = ($context === 'archive') ? 'badge_show_on_archive' : 'badge_show_on_product';
            $show = isset($rule_set[$show_field]) ? $rule_set[$show_field] : 'yes';
            if ($show !== 'yes') {
                continue;
            }

            $text = isset($rule_set['badge_text']) && !empty($rule_set['badge_text']) ? $rule_set['badge_text'] : __( 'Promotion', 'incentiv' );
            $bg = isset($rule_set['badge_bg_color']) ? $rule_set['badge_bg_color'] : '#e53935';
            $color = isset($rule_set['badge_text_color']) ? $rule_set['badge_text_color'] : '#ffffff';

            $output .= '<span class="mmd-discount-badge" style="'
                . 'background:' . esc_attr($bg) . ';'
                . 'color:' . esc_attr($color) . ';'
                . '">' . esc_html($text) . '</span>';
            break; // Show only the first matching badge
        }

        $badge_cache[$cache_key] = $output;
        if ($output) {
            echo wp_kses_post( $output );
        }
    }

    /**
     * Display global discount notice bars across the site.
     * Groups notices by position. Multiple notices in the same position become a carousel.
     */
    public function display_global_discount_notices() {
        if (is_admin()) {
            return;
        }

        $rule_sets = MMD_Discount_Rules::get_all_rule_sets();
        $notices = array();

        foreach ($rule_sets as $rule_set) {
            if ($rule_set['enabled'] !== 'yes') {
                continue;
            }
            // If rule requires a coupon, only show global notice when the coupon is applied
            if (!empty($rule_set['coupon_required']) && $rule_set['coupon_required'] === 'yes') {
                $applied_coupons = WC()->cart ? array_map('strtolower', WC()->cart->get_applied_coupons()) : array();
                if (!$this->is_rule_active($rule_set, $applied_coupons)) {
                    continue;
                }
            }
            if ((isset($rule_set['global_notice_enabled']) ? $rule_set['global_notice_enabled'] : 'no') !== 'yes') {
                continue;
            }
            if (!MMD_Discount_Rules::is_rule_in_schedule_public($rule_set)) {
                continue;
            }
            if (empty($rule_set['global_notice_text'])) {
                continue;
            }

            // Check page visibility
            $pages = isset($rule_set['global_notice_pages']) ? $rule_set['global_notice_pages'] : 'all';
            if ($pages !== 'all') {
                $is_shop = function_exists('is_shop') && (is_shop() || is_product_category() || is_product_tag());
                $is_product = function_exists('is_product') && is_product();
                $is_home = is_front_page();
                $is_checkout = function_exists('is_checkout') && is_checkout();
                if ($pages === 'shop_only' && !$is_shop) continue;
                if ($pages === 'product_only' && !$is_product) continue;
                if ($pages === 'shop_and_product' && !$is_shop && !$is_product) continue;
                if ($pages === 'homepage' && !$is_home) continue;
                if ($pages === 'not_checkout' && $is_checkout) continue;
            }

            $notices[] = $rule_set;
        }

        if (empty($notices)) {
            return;
        }

        // Enqueue dashicons on frontend if any notice uses a dashicon icon
        $needs_dashicons = false;
        foreach ($notices as $n) {
            if (!empty($n['global_notice_icon']) && strpos($n['global_notice_icon'], 'dashicons-') === 0) {
                $needs_dashicons = true;
                break;
            }
        }
        if ($needs_dashicons) {
            wp_enqueue_style('dashicons');
        }

        // Group notices by position
        $grouped = array();
        foreach ($notices as $n) {
            $pos = isset($n['global_notice_position']) ? $n['global_notice_position'] : 'top';
            $grouped[$pos][] = $n;
        }

        // Check dismissal cookie for entire notice bar
        $bar_id = 'mmd_gn_bar';

        foreach ($grouped as $position => $group) {
            $container_id = $bar_id . '_' . $position;
            $is_carousel = count($group) > 1;
            $rotate_speed = 5;
            foreach ($group as $g) {
                $s = isset($g['global_notice_rotate_speed']) ? intval($g['global_notice_rotate_speed']) : 5;
                if ($s > 0) { $rotate_speed = $s; break; }
            }

            // Check if any notice in the group is dismissible
            $any_dismissible = false;
            $dismiss_limit = 0; // 0 = unlimited
            foreach ($group as $g) {
                if ((isset($g['global_notice_dismissible']) ? $g['global_notice_dismissible'] : 'yes') === 'yes') {
                    $any_dismissible = true;
                    $dismiss_limit = isset($g['global_notice_dismiss_limit']) ? intval($g['global_notice_dismiss_limit']) : 0;
                    break;
                }
            }

            // Position CSS
            $is_fixed = in_array($position, array('top', 'bottom'));
            $is_float = in_array($position, array('float_left', 'float_right'));

            $this->render_notice_group($container_id, $position, $group, $is_carousel, $rotate_speed, $any_dismissible, $is_fixed, $is_float, $dismiss_limit);
        }
    }

    /**
     * Render a group of notices for a single position.
     */
    private function render_notice_group($container_id, $position, $group, $is_carousel, $rotate_speed, $any_dismissible, $is_fixed, $is_float, $dismiss_limit = 0) {
        // Mobile position override - get from first notice in group
        $mobile_position = isset($group[0]['global_notice_mobile_position']) ? $group[0]['global_notice_mobile_position'] : 'same';

        // Build position styles
        $pos_styles = 'z-index:99999;direction:' . ( is_rtl() ? 'rtl' : 'ltr' ) . ';';
        if ($is_fixed) {
            $pos_styles .= 'position:fixed;left:0;right:0;';
            $pos_styles .= ($position === 'bottom') ? 'bottom:0;' : 'top:0;';
        } elseif ($is_float) {
            $pos_styles .= 'position:fixed;bottom:20px;max-width:400px;border-radius:10px;';
            $pos_styles .= ($position === 'float_left') ? 'left:20px;' : 'right:20px;';
        } elseif ($position === 'below_header') {
            $pos_styles .= 'position:relative;width:100%;';
        } elseif ($position === 'above_footer') {
            $pos_styles .= 'position:relative;width:100%;';
        }

        // Use first notice's color as container default
        $first = $group[0];
        $bg = isset($first['global_notice_bg_color']) ? $first['global_notice_bg_color'] : '#2196F3';
        $color = isset($first['global_notice_text_color']) ? $first['global_notice_text_color'] : '#ffffff';
        $layout = isset($first['global_notice_layout']) ? $first['global_notice_layout'] : 'bar';

        // Layout-specific styles
        $layout_css = $this->get_layout_css($layout, $bg, $color);
        ?>
        <div id="<?php echo esc_attr($container_id); ?>" class="mmd-global-notice mmd-gn-<?php echo esc_attr($layout); ?>" data-mobile-pos="<?php echo esc_attr($mobile_position); ?>" data-desktop-pos="<?php echo esc_attr($position); ?>" style="<?php echo esc_attr( $pos_styles ); ?><?php echo esc_attr( $layout_css ); ?>display:none;box-shadow:0 2px 8px rgba(0,0,0,0.15);">
            <?php if ($layout === 'marquee'): ?>
            <?php
                // MARQUEE MODE: All notices scroll together in one continuous strip
                $marquee_dir = isset($first['global_notice_marquee_direction']) ? $first['global_notice_marquee_direction'] : 'rtl';
                $marquee_anim = 'mmd-marquee-rtl';
                if ($marquee_dir === 'ltr') {
                    $marquee_anim = 'mmd-marquee-ltr';
                } elseif ($marquee_dir === 'alternate') {
                    $marquee_anim = 'mmd-marquee-alt';
                }

                // Build all notice fragments
                $notice_fragments = array();
                foreach ($group as $rule_set) {
                    $n_icon = isset($rule_set['global_notice_icon']) ? $rule_set['global_notice_icon'] : '';
                    $n_text = esc_html($rule_set['global_notice_text']);
                    $link_url = isset($rule_set['global_notice_link_url']) ? $rule_set['global_notice_link_url'] : '';
                    $link_text = isset($rule_set['global_notice_link_text']) ? $rule_set['global_notice_link_text'] : '';

                    $icon_html = '';
                    if (!empty($n_icon)) {
                        if (strpos($n_icon, 'dashicons-') === 0) {
                            $icon_html = '<span class="dashicons ' . esc_attr($n_icon) . '" style="' . ( is_rtl() ? 'margin-left' : 'margin-right' ) . ':8px;font-size:1.2em;vertical-align:middle;width:auto;height:auto;"></span>';
                        } else {
                            $icon_html = '<span style="' . ( is_rtl() ? 'margin-left' : 'margin-right' ) . ':8px;font-size:1.2em;">' . esc_html($n_icon) . '</span>';
                        }
                    }

                    $frag = $icon_html . $n_text;
                    if (!empty($link_url) && !empty($link_text)) {
                        $frag .= '&nbsp;&nbsp;<a href="' . esc_url($link_url) . '" style="color:inherit;text-decoration:underline;">' . esc_html($link_text) . '</a>';
                    }
                    $notice_fragments[] = $frag;
                }

                // Join all notices with a separator
                $separator = '&nbsp;&nbsp;&nbsp;<span style="opacity:0.5;">|</span>&nbsp;&nbsp;&nbsp;';
                $all_notices = implode($separator, $notice_fragments);

                // Speed scales with number of notices (more content = slower scroll)
                $anim_duration = 15 * max(1, count($group));
            ?>
            <div class="mmd-gn-slides" style="position:relative;overflow:hidden;">
                <div class="mmd-gn-slide" style="text-align:center;padding:10px 20px;">
                    <div class="mmd-gn-marquee" style="overflow:hidden;white-space:nowrap;">
                        <span style="display:inline-block;animation:<?php echo esc_attr( $marquee_anim ); ?> <?php echo esc_attr( $anim_duration ); ?>s linear infinite;">
                            <?php echo wp_kses_post( $all_notices ); ?>
                            <?php echo wp_kses_post( $separator ); ?>
                            <?php echo wp_kses_post( $all_notices ); ?>
                        </span>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <div class="mmd-gn-slides" style="position:relative;overflow:hidden;">
                <?php foreach ($group as $i => $rule_set):
                    $n_bg = isset($rule_set['global_notice_bg_color']) ? $rule_set['global_notice_bg_color'] : '#2196F3';
                    $n_color = isset($rule_set['global_notice_text_color']) ? $rule_set['global_notice_text_color'] : '#ffffff';
                    $n_layout = isset($rule_set['global_notice_layout']) ? $rule_set['global_notice_layout'] : 'bar';
                    $n_icon = isset($rule_set['global_notice_icon']) ? $rule_set['global_notice_icon'] : '';
                    $n_text = esc_html($rule_set['global_notice_text']);
                    $link_url = isset($rule_set['global_notice_link_url']) ? $rule_set['global_notice_link_url'] : '';
                    $link_text = isset($rule_set['global_notice_link_text']) ? $rule_set['global_notice_link_text'] : '';
                    $slide_layout_css = $this->get_layout_css($n_layout, $n_bg, $n_color);

                    // Build icon HTML - supports both emoji and dashicons
                    $icon_html = '';
                    if (!empty($n_icon)) {
                        if (strpos($n_icon, 'dashicons-') === 0) {
                            $icon_html = '<span class="dashicons ' . esc_attr($n_icon) . '" style="' . ( is_rtl() ? 'margin-left' : 'margin-right' ) . ':8px;font-size:1.2em;vertical-align:middle;width:auto;height:auto;"></span>';
                        } else {
                            $icon_html = '<span style="' . ( is_rtl() ? 'margin-left' : 'margin-right' ) . ':8px;font-size:1.2em;">' . esc_html($n_icon) . '</span>';
                        }
                    }
                ?>
                <div class="mmd-gn-slide" data-index="<?php echo esc_attr( $i ); ?>" style="<?php echo ($i > 0) ? 'display:none;' : ''; ?>text-align:center;padding:<?php echo ($n_layout === 'slim') ? '6px 20px' : (($n_layout === 'bold') ? '16px 20px' : '10px 20px'); ?>;<?php echo esc_attr( $slide_layout_css ); ?>transition:opacity 0.4s ease;">
                    <?php echo wp_kses_post( $icon_html ); ?>
                    <span class="mmd-gn-text" style="font-size:<?php echo ($n_layout === 'bold') ? '17px' : (($n_layout === 'slim') ? '13px' : '15px'); ?>;font-weight:<?php echo ($n_layout === 'bold') ? '800' : '600'; ?>;"><?php echo wp_kses_post( $n_text ); ?></span>
                    <?php if (!empty($link_url) && !empty($link_text)): ?>
                        <a href="<?php echo esc_url($link_url); ?>" style="color:<?php echo esc_attr($n_color); ?>;background:rgba(255,255,255,0.2);padding:<?php echo ($n_layout === 'slim') ? '2px 10px' : '4px 14px'; ?>;border-radius:4px;text-decoration:none;margin-right:12px;font-size:13px;border:1px solid rgba(255,255,255,0.4);"><?php echo esc_html($link_text); ?></a>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>

            <?php if ($is_carousel && count($group) > 1): ?>
            <div class="mmd-gn-dots" style="text-align:center;padding:0 0 6px 0;">
                <?php for ($d = 0; $d < count($group); $d++): ?>
                    <span class="mmd-gn-dot" data-slide="<?php echo esc_attr( $d ); ?>" style="display:inline-block;width:8px;height:8px;border-radius:50%;background:<?php echo ($d === 0) ? 'rgba(255,255,255,0.9)' : 'rgba(255,255,255,0.4)'; ?>;margin:0 3px;cursor:pointer;transition:background 0.3s;"></span>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
            <?php endif; ?>

            <?php if ($any_dismissible): ?>
                <button class="mmd-gn-close" style="position:absolute;left:15px;top:50%;transform:translateY(-50%);background:none;border:none;color:inherit;font-size:20px;cursor:pointer;opacity:0.7;padding:0 5px;line-height:1;" aria-label="Close">&times;</button>
            <?php endif; ?>
        </div>

        <style>
            @keyframes mmd-marquee-rtl {
                0% { transform: translateX(100%); }
                100% { transform: translateX(-100%); }
            }
            @keyframes mmd-marquee-ltr {
                0% { transform: translateX(-100%); }
                100% { transform: translateX(100%); }
            }
            @keyframes mmd-marquee-alt {
                0%, 100% { transform: translateX(50%); }
                50% { transform: translateX(-50%); }
            }
            <?php if ($mobile_position === 'hidden'): ?>
            @media (max-width: 768px) {
                #<?php echo esc_attr($container_id); ?> { display: none !important; }
            }
            <?php elseif ($mobile_position !== 'same'): ?>
            @media (max-width: 768px) {
                #<?php echo esc_attr($container_id); ?> {
                    <?php if ($mobile_position === 'top'): ?>
                        position: fixed !important; top: 0 !important; bottom: auto !important;
                        left: 0 !important; right: 0 !important; max-width: none !important; border-radius: 0 !important;
                    <?php elseif ($mobile_position === 'bottom'): ?>
                        position: fixed !important; bottom: 0 !important; top: auto !important;
                        left: 0 !important; right: 0 !important; max-width: none !important; border-radius: 0 !important;
                    <?php elseif ($mobile_position === 'below_header'): ?>
                        position: relative !important; top: auto !important; bottom: auto !important;
                        left: auto !important; right: auto !important; max-width: none !important; border-radius: 0 !important;
                    <?php endif; ?>
                }
            }
            <?php endif; ?>
        </style>

        <script>
        (function(){
            var id = '<?php echo esc_js($container_id); ?>';
            var pos = '<?php echo esc_js($position); ?>';
            var isFixed = <?php echo $is_fixed ? 'true' : 'false'; ?>;
            var mobilePos = '<?php echo esc_js($mobile_position); ?>';
            var dismissLimit = <?php echo intval($dismiss_limit); ?>; // 0 = unlimited, N = max times to show

            // Read dismiss count from cookie
            function getDismissCount() {
                var match = document.cookie.match(new RegExp('(?:^|;\\s*)' + id + '_cnt=(\\d+)'));
                return match ? parseInt(match[1], 10) : 0;
            }

            // Check if permanently dismissed (limit reached)
            var dismissCount = getDismissCount();
            if (dismissLimit > 0 && dismissCount >= dismissLimit) return;

            // Check if temporarily dismissed (within 24h cooldown)
            if (document.cookie.indexOf(id + '_tmp=1') !== -1) return;

            var el = document.getElementById(id);
            if (!el) return;

            el.style.display = 'block';

            // Determine if we need body padding (accounting for mobile override)
            function isMobile() { return window.innerWidth <= 768; }

            function getEffectivePosition() {
                if (isMobile() && mobilePos !== 'same') return mobilePos;
                return pos;
            }

            function updateBodyPadding() {
                var effPos = getEffectivePosition();
                var effFixed = (effPos === 'top' || effPos === 'bottom');

                // Remove old padding
                document.body.style.paddingTop = '';
                document.body.style.paddingBottom = '';

                if (effPos === 'hidden' || el.style.display === 'none') return;

                if (effFixed) {
                    var h = el.offsetHeight;
                    if (effPos === 'top') {
                        document.body.style.paddingTop = h + 'px';
                    } else if (effPos === 'bottom') {
                        document.body.style.paddingBottom = h + 'px';
                    }
                }
            }

            updateBodyPadding();

            // Listen for resize to update padding on orientation change
            var resizeTimer;
            window.addEventListener('resize', function() {
                clearTimeout(resizeTimer);
                resizeTimer = setTimeout(updateBodyPadding, 150);
            });

            // Dismiss button
            var closeBtn = el.querySelector('.mmd-gn-close');
            if (closeBtn) {
                closeBtn.addEventListener('click', function() {
                    el.style.display = 'none';
                    document.body.style.paddingTop = '';
                    document.body.style.paddingBottom = '';

                    // Increment dismiss count (stored for 1 year)
                    var newCount = getDismissCount() + 1;
                    document.cookie = id + '_cnt=' + newCount + ';path=/;max-age=31536000';

                    // Set temporary 24h cooldown cookie so it doesn't reappear immediately
                    document.cookie = id + '_tmp=1;path=/;max-age=86400';
                });
            }

            // Carousel logic (only for non-marquee layouts)
            <?php if ($layout !== 'marquee'): ?>
            var slides = el.querySelectorAll('.mmd-gn-slide');
            var dots = el.querySelectorAll('.mmd-gn-dot');
            if (slides.length > 1) {
                var current = 0;
                var speed = <?php echo intval($rotate_speed); ?> * 1000;

                function showSlide(idx) {
                    for (var i = 0; i < slides.length; i++) {
                        slides[i].style.opacity = '0';
                        setTimeout((function(s){ return function(){ s.style.display = 'none'; }; })(slides[i]), 400);
                        if (dots[i]) dots[i].style.background = 'rgba(255,255,255,0.4)';
                    }
                    setTimeout(function(){
                        slides[idx].style.display = 'block';
                        setTimeout(function(){ slides[idx].style.opacity = '1'; }, 50);
                    }, 400);
                    if (dots[idx]) dots[idx].style.background = 'rgba(255,255,255,0.9)';
                    current = idx;
                }

                // Dot clicks
                for (var d = 0; d < dots.length; d++) {
                    dots[d].addEventListener('click', (function(idx){ return function(){ showSlide(idx); }; })(d));
                }

                // Auto-rotate
                setInterval(function(){
                    var next = (current + 1) % slides.length;
                    showSlide(next);
                }, speed);
            }
            <?php endif; ?>
        })();
        </script>
        <?php
    }

    /**
     * Get CSS for a layout style.
     */
    private function get_layout_css($layout, $bg, $color) {
        switch ($layout) {
            case 'slim':
                return "background:{$bg};color:{$color};font-size:13px;";
            case 'bold':
                return "background:{$bg};color:{$color};font-size:17px;letter-spacing:0.5px;";
            case 'gradient':
                return "background:linear-gradient(135deg, {$bg} 0%, " . $this->adjust_color($bg, -30) . " 100%);color:{$color};";
            case 'marquee':
                return "background:{$bg};color:{$color};";
            default: // 'bar'
                return "background:{$bg};color:{$color};";
        }
    }

    /**
     * Darken/lighten a hex color.
     */
    private function adjust_color($hex, $amount) {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        $r = max(0, min(255, hexdec(substr($hex, 0, 2)) + $amount));
        $g = max(0, min(255, hexdec(substr($hex, 2, 2)) + $amount));
        $b = max(0, min(255, hexdec(substr($hex, 4, 2)) + $amount));
        return '#' . sprintf('%02x%02x%02x', $r, $g, $b);
    }

    /**
     * Display debug info for admins on product pages
     * Helps identify why override rules might not be working
     */
    public function display_override_debug_info() {
        // Check if debug mode is enabled in settings
        $settings = MMD_Helpers::get_settings();
        $debug_enabled = isset($settings['debug_mode']) && ($settings['debug_mode'] === 'yes' || $settings['debug_mode'] === '1' || $settings['debug_mode'] === true);

        if (!$debug_enabled || !is_product() || !current_user_can('manage_woocommerce')) {
            return;
        }

        global $product;
        if (!$product) {
            return;
        }

        $potential_coupons = $this->get_all_potential_coupons();
        $active_override_rules = $this->get_active_override_rules();
        $rule_sets = MMD_Discount_Rules::get_all_rule_sets();
        $qualifies = $this->product_qualifies_for_override($product);

        echo '<div class="mmd-debug-info" style="background: #fff3cd; border: 2px solid #ffc107; padding: 15px; margin: 20px 0; font-size: 12px; direction: ltr; text-align: left;">';
        echo '<strong style="color: #856404;">🔧 MMD Debug Info (Admin Only):</strong><br><br>';

        echo '<strong>URL Coupons Detected:</strong> ';
        echo !empty($potential_coupons) ? esc_html( implode(', ', $potential_coupons) ) : 'None';
        echo '<br><br>';

        echo '<strong>All Rule Sets:</strong><br>';
        foreach ($rule_sets as $rule_set) {
            $is_enabled = isset($rule_set['enabled']) && ($rule_set['enabled'] === 'yes' || $rule_set['enabled'] === '1' || $rule_set['enabled'] === true);
            $is_override = isset($rule_set['override_other_discounts']) && ($rule_set['override_other_discounts'] === 'yes' || $rule_set['override_other_discounts'] === '1' || $rule_set['override_other_discounts'] === true);
            $coupon_required = isset($rule_set['coupon_required']) && ($rule_set['coupon_required'] === 'yes' || $rule_set['coupon_required'] === '1' || $rule_set['coupon_required'] === true);
            $required_coupons = isset($rule_set['required_coupon_codes']) ? $rule_set['required_coupon_codes'] : array();

            echo '- <strong>' . esc_html($rule_set['name']) . '</strong>: ';
            echo 'Enabled=' . ($is_enabled ? 'Yes' : 'No') . ', ';
            echo 'Override=' . ($is_override ? 'Yes' : 'No') . ', ';
            echo 'CouponRequired=' . ($coupon_required ? 'Yes' : 'No') . ', ';
            echo 'RequiredCoupons=' . esc_html( is_array($required_coupons) ? implode(',', $required_coupons) : $required_coupons );
            echo '<br>';
        }

        echo '<br><strong>Active Override Rules:</strong> ';
        if (!empty($active_override_rules)) {
            $names = array_map(function($r) { return $r['name']; }, $active_override_rules);
            echo esc_html( implode(', ', $names) );
        } else {
            echo 'None';
        }
        echo '<br><br>';

        echo '<strong>Product Qualifies for Override:</strong> ';
        echo $qualifies ? 'Yes (' . esc_html( $qualifies['name'] ) . ')' : 'No';
        echo '<br>';

        echo '</div>';
    }

    /**
     * Display debug info in footer (always works regardless of theme)
     */
    public function display_override_debug_info_footer() {
        // Check if debug mode is enabled in settings
        $settings = MMD_Helpers::get_settings();
        $debug_enabled = isset($settings['debug_mode']) && ($settings['debug_mode'] === 'yes' || $settings['debug_mode'] === '1' || $settings['debug_mode'] === true);

        // Only show debug panel if debug mode is enabled AND user is logged in
        if (!$debug_enabled || !is_user_logged_in()) {
            return;
        }

        // Try to get product - works for standard products and bundles
        global $product, $post;
        $current_product = null;

        if ($product && is_a($product, 'WC_Product')) {
            $current_product = $product;
        } elseif ($post && is_singular('product')) {
            $current_product = wc_get_product($post->ID);
        }

        $potential_coupons = $this->get_all_potential_coupons();
        $active_override_rules = $this->get_active_override_rules();
        $rule_sets = MMD_Discount_Rules::get_all_rule_sets();
        $qualifies = $current_product ? $this->product_qualifies_for_override($current_product) : false;

        echo '<div id="mmd-debug-footer" style="position: fixed; bottom: 0; left: 0; right: 0; background: #fff3cd; border-top: 3px solid #ffc107; padding: 15px; font-size: 12px; direction: ltr; text-align: left; z-index: 999999; max-height: 300px; overflow-y: auto;">';
        echo '<button onclick="document.getElementById(\'mmd-debug-footer\').style.display=\'none\'" style="position: absolute; top: 5px; right: 10px; background: #ffc107; border: none; padding: 5px 10px; cursor: pointer;">X Close</button>';
        echo '<strong style="color: #856404; font-size: 14px;">🔧 MMD Debug Info</strong><br><br>';

        echo '<strong>URL Coupons Detected:</strong> ';
        echo !empty($potential_coupons) ? esc_html( implode(', ', $potential_coupons) ) : '<span style="color:red">NONE</span>';
        echo '<br><br>';

        echo '<strong>All Rule Sets (' . count($rule_sets) . '):</strong><br>';
        foreach ($rule_sets as $rule_set) {
            $is_enabled = isset($rule_set['enabled']) && ($rule_set['enabled'] === 'yes' || $rule_set['enabled'] === '1' || $rule_set['enabled'] === true);
            $is_override = isset($rule_set['override_other_discounts']) && ($rule_set['override_other_discounts'] === 'yes' || $rule_set['override_other_discounts'] === '1' || $rule_set['override_other_discounts'] === true);
            $coupon_required = isset($rule_set['coupon_required']) && ($rule_set['coupon_required'] === 'yes' || $rule_set['coupon_required'] === '1' || $rule_set['coupon_required'] === true);
            $required_coupons = isset($rule_set['required_coupon_codes']) ? $rule_set['required_coupon_codes'] : array();
            $req_coupons_str = is_array($required_coupons) ? implode(',', $required_coupons) : $required_coupons;

            $style = ($is_override && $coupon_required) ? 'background: #d4edda; padding: 5px;' : '';
            echo '<div style="margin: 3px 0; ' . esc_attr( $style ) . '">';
            echo '- <strong>' . esc_html($rule_set['name']) . '</strong> (ID: ' . esc_html($rule_set['id']) . ')<br>';
            echo '&nbsp;&nbsp;Enabled=' . ($is_enabled ? '<span style="color:green">Yes</span>' : '<span style="color:red">No</span>') . ', ';
            echo 'Override=' . ($is_override ? '<span style="color:green">Yes</span>' : '<span style="color:red">No</span>') . ', ';
            echo 'CouponRequired=' . ($coupon_required ? '<span style="color:green">Yes</span>' : '<span style="color:red">No</span>') . ', ';
            echo 'RequiredCoupons=[' . esc_html($req_coupons_str) . ']';
            echo '</div>';
        }

        echo '<br><strong>Active Override Rules:</strong> ';
        if (!empty($active_override_rules)) {
            $names = array_map(function($r) { return $r['name']; }, $active_override_rules);
            echo '<span style="color:green; font-weight:bold;">' . esc_html( implode(', ', $names) ) . '</span>';
        } else {
            echo '<span style="color:red; font-weight:bold;">NONE - This is the problem!</span>';
        }
        echo '<br><br>';

        if ($product) {
            echo '<strong>Current Product:</strong> ' . esc_html($product->get_name()) . ' (ID: ' . esc_html( $product->get_id() ) . ')<br>';
            echo '<strong>Product Qualifies for Override:</strong> ';
            echo $qualifies ? '<span style="color:green">Yes (' . esc_html($qualifies['name']) . ')</span>' : '<span style="color:red">No</span>';
        } else {
            echo '<strong>Product:</strong> <span style="color:orange">Not available (not on product page)</span>';
        }

        // Show discount rules for active override rules
        echo '<br><br><strong>Discount Rules Data (for JS):</strong><br>';
        if (!empty($active_override_rules)) {
            foreach ($active_override_rules as $rule) {
                echo '- Rule: ' . esc_html($rule['name']) . '<br>';
                if (!empty($rule['discount_rules'])) {
                    foreach ($rule['discount_rules'] as $dr) {
                        echo '&nbsp;&nbsp;Qty: ' . esc_html($dr['quantity']) . ', Type: ' . esc_html($dr['type']) . ', Discount: ' . esc_html($dr['discount']) . '<br>';
                    }
                } else {
                    echo '&nbsp;&nbsp;<span style="color:red">NO DISCOUNT RULES!</span><br>';
                }
            }
        }

        // Output JS data for debugging
        echo '<br><strong>mmdOverrideData (check console):</strong> <span id="mmd-js-data-status">checking...</span>';
        echo '<script>
            document.addEventListener("DOMContentLoaded", function() {
                var status = document.getElementById("mmd-js-data-status");
                if (typeof mmdOverrideData !== "undefined") {
                    status.innerHTML = "<span style=\'color:green\'>active=" + mmdOverrideData.active + ", rules=" + mmdOverrideData.rules.length + "</span>";
                    console.log("MMD Override Data:", mmdOverrideData);
                    if (mmdOverrideData.rules.length > 0) {
                        console.log("First rule:", mmdOverrideData.rules[0]);
                    }
                } else {
                    status.innerHTML = "<span style=\'color:red\'>NOT DEFINED!</span>";
                }
            });
        </script>';

        echo '</div>';
    }

    /**
     * Display WooCommerce notices on product pages
     * Ensures notices like "coupon applied" are shown when visiting product page with coupon URL
     */
    public function display_wc_notices_on_product() {
        if (!is_product()) {
            return;
        }

        // Display any pending WooCommerce notices
        if (function_exists('wc_print_notices')) {
            wc_print_notices();
        }
    }

    /**
     * Detect if we're rendering inside a cart, mini cart, or checkout.
     * This is used to limit cart-specific price filters to only those contexts.
     */
    /**
     * Calculate the correct cart subtotal based on DB regular prices and
     * any rule's "show_discount_in_cart" setting. Returns the subtotal before
     * fee-mode discounts (those get their own totals row); discounts allocated onto
     * products are already inside the line prices and are subtracted here.
     *
     * @return float Subtotal amount.
     */
    private function calculate_display_subtotal() {
        if (!WC()->cart) return 0;

        $subtotal = 0;
        $breakdown = $this->get_cart_discount_breakdown();

        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
            $product_id = !empty($cart_item['variation_id']) ? $cart_item['variation_id'] : $cart_item['product_id'];
            $regular_price = floatval(get_post_meta($product_id, '_regular_price', true));

            // Skip gifts - they keep their custom prices
            if (isset($cart_item['mmd_is_gift']) && $cart_item['mmd_is_gift']) {
                $subtotal += floatval($cart_item['data']->get_price()) * $cart_item['quantity'];
                continue;
            }
            if (isset($cart_item['mmd_cart_based_gift']) && $cart_item['mmd_cart_based_gift']) {
                $subtotal += floatval($cart_item['data']->get_price()) * $cart_item['quantity'];
                continue;
            }

            if ($regular_price <= 0) {
                $subtotal += floatval($cart_item['data']->get_price()) * $cart_item['quantity'];
                continue;
            }

            // Start with regular line total
            $line_total = $regular_price * $cart_item['quantity'];

            // Only discounts that live inside the line price (allocated rules) belong
            // here. Fee-mode discounts are shown once, as their own totals row.
            $line_total -= $this->get_in_price_discount($breakdown, $cart_item_key);

            $subtotal += max(0, $line_total);
        }

        return $subtotal;
    }

    /**
     * Override the cart subtotal HTML (used in cart page).
     */
    public function override_cart_subtotal_display($cart_subtotal, $compound, $cart) {
        if (!WC()->cart || WC()->cart->is_empty()) return $cart_subtotal;
        $subtotal = $this->calculate_display_subtotal();
        return wc_price($subtotal);
    }

    /**
     * Override the mini cart total/subtotal HTML.
     */
    public function override_mini_cart_total($total_html) {
        if (!WC()->cart || WC()->cart->is_empty()) return $total_html;
        $subtotal = $this->calculate_display_subtotal();
        return wc_price($subtotal);
    }

    /**
     * Check if debug panel should be shown
     */
    /**
     * Coupon code from a shareable promotion link (?coupon-code=, ?coupon_code= or ?coupon=).
     * These are public marketing links, so there is no nonce; the value is only a coupon code
     * that WooCommerce validates before applying it.
     *
     * @return string Sanitized coupon code, or ''.
     */
    private static function get_url_coupon_param() {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        foreach (array('coupon-code', 'coupon_code', 'coupon') as $key) {
            if (isset($_GET[$key])) {
                return sanitize_text_field(wp_unslash($_GET[$key]));
            }
        }
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
        return '';
    }

    private function is_debug_enabled() {
        if (defined('MMD_DEBUG_CART') && MMD_DEBUG_CART) return true;
        // Only store managers can switch the debug panel on from the URL; it is a read-only view toggle.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (isset($_GET['mmd_debug']) && '1' === sanitize_text_field(wp_unslash($_GET['mmd_debug'])) && current_user_can('manage_woocommerce')) return true;
        if (WC()->session && WC()->session->get('mmd_debug_enabled')) return true;
        return false;
    }

    /**
     * Render visible debug panel showing exactly what's affecting prices
     */
    public function render_debug_panel() {
        if (!WC()->cart) return;

        global $wp_filter;
        $hooks_to_inspect = array(
            'woocommerce_before_calculate_totals',
            'woocommerce_cart_calculate_fees',
            'woocommerce_product_get_price',
            'woocommerce_product_variation_get_price',
            'woocommerce_product_get_sale_price',
            'woocommerce_product_variation_get_sale_price',
            'woocommerce_get_price_html',
            'woocommerce_cart_item_price',
            'woocommerce_widget_cart_item_quantity',
        );

        $hook_info = array();
        foreach ($hooks_to_inspect as $hook) {
            $hook_info[$hook] = array();
            if (isset($wp_filter[$hook])) {
                foreach ($wp_filter[$hook]->callbacks as $priority => $callbacks) {
                    foreach ($callbacks as $cb) {
                        $name = 'unknown';
                        if (is_array($cb['function'])) {
                            $name = (is_object($cb['function'][0]) ? get_class($cb['function'][0]) : (string)$cb['function'][0]) . '::' . $cb['function'][1];
                        } elseif (is_string($cb['function'])) {
                            $name = $cb['function'];
                        } elseif (is_object($cb['function'])) {
                            $name = 'Closure';
                        }
                        $hook_info[$hook][] = "P{$priority}: " . $name;
                    }
                }
            }
        }

        $items_info = array();
        foreach (WC()->cart->get_cart() as $key => $item) {
            $pid = !empty($item['variation_id']) ? $item['variation_id'] : $item['product_id'];
            $product = $item['data'];
            $qualifying = MMD_Discount_Rules::get_qualifying_rule_sets($product, $item);
            $items_info[] = array(
                'product_id' => $item['product_id'],
                'variation_id' => $item['variation_id'] ?? 0,
                'qty' => $item['quantity'],
                'db_regular_price' => get_post_meta($pid, '_regular_price', true),
                'db_sale_price' => get_post_meta($pid, '_sale_price', true),
                'db_price' => get_post_meta($pid, '_price', true),
                'obj_get_price_view' => $product->get_price(),
                'obj_get_price_edit' => $product->get_price('edit'),
                'obj_get_regular' => $product->get_regular_price(),
                'obj_get_sale' => $product->get_sale_price(),
                'obj_is_on_sale' => $product->is_on_sale() ? 'YES' : 'no',
                'qualifying_discount_rules' => array_column($qualifying, 'name'),
                'mmd_is_gift' => isset($item['mmd_is_gift']) ? 'yes' : 'no',
            );
        }

        // Add-ons that run time-boxed events report them here.
        $special_events_active = (bool) apply_filters('mmd_debug_special_events_active', false);

        $html = '<div style="background:#fffbe6;border:2px solid #f0c000;padding:15px;margin:10px 0;font-family:monospace;font-size:11px;direction:ltr;text-align:left;max-height:500px;overflow:auto;">';
        $html .= '<strong style="color:#d63638;font-size:14px;">🔍 MMD Cart Debug Panel</strong><br><br>';
        if (has_filter('mmd_debug_special_events_active')) {
            $html .= '<strong>Special Events Active:</strong> ' . ($special_events_active ? '<span style="color:red">YES</span>' : 'no') . '<br>';
        }
        $html .= '<strong>Cart Subtotal:</strong> ' . wc_price(WC()->cart->get_subtotal()) . '<br>';
        $html .= '<strong>Cart Total:</strong> ' . wc_price(WC()->cart->get_total('edit')) . '<br><br>';

        $html .= '<strong>📦 Cart Items:</strong><br>';
        foreach ($items_info as $info) {
            $html .= '<div style="background:white;padding:8px;margin:5px 0;border-left:3px solid #2271b1;">';
            foreach ($info as $k => $v) {
                if (is_array($v)) $v = empty($v) ? '(none)' : implode(', ', $v);
                $highlight = '';
                if ($k === 'obj_get_price_view' && $v != $info['db_regular_price']) $highlight = 'background:#ffcccc;';
                if ($k === 'obj_is_on_sale' && $v === 'YES') $highlight = 'background:#ffcccc;';
                $html .= '<span style="' . $highlight . '"><strong>' . esc_html($k) . ':</strong> ' . esc_html((string)$v) . '</span><br>';
            }
            $html .= '</div>';
        }

        $html .= '<br><strong>🔗 Active Hooks (in priority order):</strong><br>';
        foreach ($hook_info as $hook => $cbs) {
            if (empty($cbs)) continue;
            $html .= '<div style="background:white;padding:6px;margin:3px 0;">';
            $html .= '<strong>' . esc_html($hook) . '</strong><br>';
            foreach ($cbs as $cb) {
                $color = (strpos($cb, 'MMD_') !== false) ? '#2271b1' : '#666';
                $html .= '<span style="color:' . $color . ';margin-left:15px;">↳ ' . esc_html($cb) . '</span><br>';
            }
            $html .= '</div>';
        }

        $html .= '<br><em>Add ?mmd_debug=0 to URL to disable.</em>';
        $html .= '</div>';

        echo wp_kses_post( $html );
    }

    /**
     * Reset product prices to regular prices for override rules
     * This cancels any existing sale prices when an override rule is active
     */
    public function reset_prices_for_override_rules($cart) {
        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }

        if (!$cart || $cart->is_empty()) {
            return;
        }

        // Prevent running multiple times
        if (did_action('woocommerce_before_calculate_totals') >= 2) {
            return;
        }

        // Flag to prevent price display filters from applying during cart calculations
        $this->is_doing_cart_calculations = true;

        $active_rules = $this->get_active_override_rules();
        if (empty($active_rules)) {
            return;
        }

        // Check each cart item and reset prices for items that qualify for override rules
        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            $product = $cart_item['data'];

            // Check if this product qualifies for any active override rule
            foreach ($active_rules as $rule_set) {
                $qualifying_rule_sets = MMD_Discount_Rules::get_qualifying_rule_sets($product, $cart_item);

                foreach ($qualifying_rule_sets as $qualifying_set) {
                    if ($qualifying_set['id'] === $rule_set['id']) {
                        // Product qualifies for this override rule - reset to regular price
                        $_product = wc_get_product($cart_item['product_id']);
                        if ($cart_item['variation_id']) {
                            $_product = wc_get_product($cart_item['variation_id']);
                        }
                        if ($_product) {
                            $regular_price = $_product->get_regular_price();
                            if ($regular_price && $regular_price > 0) {
                                // Force regular price on cart item
                                $product->set_sale_price('');
                                $product->set_regular_price($regular_price);
                                $product->set_price($regular_price);
                            }
                        }
                        break 2; // Exit both loops for this product
                    }
                }
            }
        }

        // Reset flag after cart calculations
        $this->is_doing_cart_calculations = false;
    }

    /**
     * Override cart item price display - show regular price crossed out + discounted price
     */
    /**
     * Get the discounted price to show for a cart item, but ONLY if a qualifying
     * rule has show_discount_in_cart enabled. Returns null if regular price should be shown.
     *
     * @return array|null ['regular' => float, 'discounted' => float] or null
     */
    /**
     * Walk all active discount rules and determine the discount applied to each cart line.
     * Returns a map: cart_item_key => array of discount info entries.
     * Each entry: ['units_discounted' => int, 'discount_per_unit' => float, 'discount_total' => float,
     *              'discounted_unit_price' => float, 'regular_unit_price' => float, 'rule_name' => string,
     *              'rule_set' => array, 'mode' => 'cheapest'|'most_expensive'|'global'|'override']
     *
     * Cached per-request.
     */
    /**
     * Discount on a cart line that is already inside its price (allocated rules).
     * Fee-mode entries are excluded: they are added to the cart as a fee row.
     *
     * @param array  $breakdown     Discount breakdown keyed by cart item key.
     * @param string $cart_item_key Cart item key.
     * @return float
     */
    private function get_in_price_discount($breakdown, $cart_item_key) {
        $sum = 0.0;
        if (!empty($breakdown[$cart_item_key])) {
            foreach ($breakdown[$cart_item_key] as $info) {
                if (isset($info['mode']) && $info['mode'] === 'allocated') {
                    $sum += floatval($info['discount_total']);
                }
            }
        }
        return $sum;
    }

    private function get_cart_discount_breakdown() {
        // Ensure calculate_discount has run to populate the breakdown.
        if (empty($this->cart_discount_breakdown) && WC()->cart && !WC()->cart->is_empty()) {
            // Trigger calculation if not already done
            $this->calculate_discount();
        }
        return $this->cart_discount_breakdown;
    }

    /**
     * Check if any active discount rule has show_discount_in_cart enabled
     * for the given cart item.
     */
    private function rule_should_show_in_cart($cart_item_key) {
        $breakdown = $this->get_cart_discount_breakdown();
        if (empty($breakdown[$cart_item_key])) return false;

        // Find the rule(s) by name and check their show_discount_in_cart setting
        $rule_sets = MMD_Discount_Rules::get_all_rule_sets();
        foreach ($breakdown[$cart_item_key] as $info) {
            foreach ($rule_sets as $rule_set) {
                if ($rule_set['name'] !== $info['rule_name']) continue;
                if (!empty($rule_set['show_discount_in_cart']) && $rule_set['show_discount_in_cart'] === 'yes') {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Build the discount notice HTML for a cart item (shown under the price line).
     */
    private function get_discount_notice_html($cart_item_key) {
        $breakdown = $this->get_cart_discount_breakdown();
        if (empty($breakdown[$cart_item_key])) return '';

        // Only show the per-item notice if at least one applicable rule has
        // show_discount_in_cart=yes. Otherwise, the discount appears only as the
        // cart fee line at the bottom.
        if (!$this->rule_should_show_in_cart($cart_item_key)) return '';

        $messages = array();
        foreach ($breakdown[$cart_item_key] as $info) {
            if (isset($info['mode']) && $info['mode'] === 'allocated') {
                // The price above already includes it; name the promotion and the amount.
                /* translators: 1: rule name, 2: discount amount included in this line. */
                $messages[] = sprintf(__( '%1$s: %2$s off', 'incentiv' ), esc_html($info['rule_name']), wc_price($info['discount_total']));
                continue;
            }
            $unit_price = wc_price($info['discounted_unit_price']);
            $count = intval($info['units_discounted']);

            if ($info['mode'] === 'global' || $info['mode'] === 'override') {
                if ($count === 1) {
                    /* translators: %s: discounted unit price. */
                    $msg = sprintf(__( 'One item at %s', 'incentiv' ), $unit_price);
                } else {
                    /* translators: 1: number of items, 2: discounted unit price. */
                    $msg = sprintf(__( '%1$d items at %2$s each', 'incentiv' ), $count, $unit_price);
                }
            } else {
                // cheapest / most_expensive — always exactly 1 unit
                /* translators: %s: discounted unit price. */
                $msg = sprintf(__( 'One item at %s', 'incentiv' ), $unit_price);
            }

            $messages[] = $msg;
        }

        return '<div class="mmd-cart-item-discount-notice" style="margin-top:4px;font-size:12px;color:#17b26a;font-weight:600;">'
            . implode('<br>', $messages)
            . '</div>';
    }

    private function get_cart_display_discount($cart_item) {
        if (isset($cart_item['mmd_is_gift']) && $cart_item['mmd_is_gift']) return null;
        if (isset($cart_item['mmd_cart_based_gift']) && $cart_item['mmd_cart_based_gift']) return null;

        $product = $cart_item['data'];
        $product_id = !empty($cart_item['variation_id']) ? $cart_item['variation_id'] : $cart_item['product_id'];
        $regular_price = floatval(get_post_meta($product_id, '_regular_price', true));
        if ($regular_price <= 0) return null;

        $applied_coupons = WC()->cart ? array_map('strtolower', WC()->cart->get_applied_coupons()) : array();
        $qualifying = MMD_Discount_Rules::get_qualifying_rule_sets($product, $cart_item);

        foreach ($qualifying as $rule_set) {
            if (empty($rule_set['enabled']) || $rule_set['enabled'] !== 'yes') continue;
            if (empty($rule_set['show_discount_in_cart']) || $rule_set['show_discount_in_cart'] !== 'yes') continue;
            if (!$this->is_rule_active($rule_set, $applied_coupons)) continue;

            // Find the applicable discount tier
            if (empty($rule_set['discount_rules']) || !is_array($rule_set['discount_rules'])) continue;
            $tiers = $rule_set['discount_rules'];
            usort($tiers, function($a, $b) { return intval($a['quantity']) - intval($b['quantity']); });

            // For override rules, use the override discount logic
            $is_override = !empty($rule_set['override_other_discounts']) && $rule_set['override_other_discounts'] === 'yes';
            if ($is_override) {
                $_product = wc_get_product($product_id);
                $discount_rule = $_product ? $this->get_override_discount_for_product($_product, $rule_set) : null;
                if (!$discount_rule) continue;
                $discounted = $this->calculate_discounted_price($regular_price, $discount_rule);
                return array('regular' => $regular_price, 'discounted' => $discounted);
            }

            // Non-override: use the first tier (or whichever is configured)
            $tier = isset($rule_set['sale_price_tier']) ? $rule_set['sale_price_tier'] : 'first';
            $selected = ($tier === 'last') ? end($tiers) : reset($tiers);
            if (!$selected || empty($selected['discount']) || floatval($selected['discount']) <= 0) continue;

            $discounted = $this->calculate_discounted_price($regular_price, $selected);
            if ($discounted >= $regular_price) continue;

            return array('regular' => $regular_price, 'discounted' => $discounted);
        }

        return null;
    }

    public function override_cart_item_price_display($price, $cart_item, $cart_item_key) {
        if (isset($cart_item['mmd_is_gift']) && $cart_item['mmd_is_gift']) return $price;
        if (isset($cart_item['mmd_cart_based_gift']) && $cart_item['mmd_cart_based_gift']) return $price;

        $product_id = !empty($cart_item['variation_id']) ? $cart_item['variation_id'] : $cart_item['product_id'];
        $regular_price = get_post_meta($product_id, '_regular_price', true);
        if ($regular_price === '' || $regular_price === false || floatval($regular_price) <= 0) {
            return $price;
        }

        // Always show the regular price + discount notice underneath
        $price_html = wc_price($regular_price);
        $notice = $this->get_discount_notice_html($cart_item_key);
        return $price_html . $notice;
    }

    /**
     * Override cart item subtotal display - show regular subtotal crossed out + discounted subtotal
     */
    public function override_cart_item_subtotal_display($subtotal, $cart_item, $cart_item_key) {
        if (isset($cart_item['mmd_is_gift']) && $cart_item['mmd_is_gift']) return $subtotal;
        if (isset($cart_item['mmd_cart_based_gift']) && $cart_item['mmd_cart_based_gift']) return $subtotal;

        $product_id = !empty($cart_item['variation_id']) ? $cart_item['variation_id'] : $cart_item['product_id'];
        $regular_price = get_post_meta($product_id, '_regular_price', true);
        if ($regular_price === '' || $regular_price === false || floatval($regular_price) <= 0) {
            return $subtotal;
        }

        $quantity = $cart_item['quantity'];
        $line_regular = floatval($regular_price) * $quantity;

        // Strike the line through only when the discount is really inside the line
        // price (allocated rules). A fee-mode discount already has its own row in
        // the totals; showing it here too made the cart look double-discounted.
        $breakdown = $this->get_cart_discount_breakdown();
        $line_discount = $this->get_in_price_discount($breakdown, $cart_item_key);

        if ($line_discount > 0) {
            return wc_format_sale_price($line_regular, max(0, $line_regular - $line_discount));
        }
        return wc_price($line_regular);
    }

    /**
     * Override mini cart item quantity display - add price info
     */
    public function override_mini_cart_item_quantity($quantity_html, $cart_item, $cart_item_key) {
        if (isset($cart_item['mmd_is_gift']) && $cart_item['mmd_is_gift']) return $quantity_html;
        if (isset($cart_item['mmd_cart_based_gift']) && $cart_item['mmd_cart_based_gift']) return $quantity_html;

        $product_id = !empty($cart_item['variation_id']) ? $cart_item['variation_id'] : $cart_item['product_id'];
        $regular_price = get_post_meta($product_id, '_regular_price', true);
        if ($regular_price === '' || $regular_price === false || floatval($regular_price) <= 0) {
            return $quantity_html;
        }

        $qty = $cart_item['quantity'];
        $price_html = '<span class="quantity">' . $qty . ' &times; ' . wc_price($regular_price) . '</span>';
        $notice = $this->get_discount_notice_html($cart_item_key);
        return $price_html . $notice;
    }

    /**
     * Override checkout order review item quantity display
     */
    public function override_checkout_item_quantity($quantity_html, $cart_item, $cart_item_key) {
        if (isset($cart_item['mmd_is_gift']) && $cart_item['mmd_is_gift']) return $quantity_html;
        if (isset($cart_item['mmd_cart_based_gift']) && $cart_item['mmd_cart_based_gift']) return $quantity_html;

        $product_id = !empty($cart_item['variation_id']) ? $cart_item['variation_id'] : $cart_item['product_id'];
        $regular_price = get_post_meta($product_id, '_regular_price', true);
        if ($regular_price === '' || $regular_price === false || floatval($regular_price) <= 0) {
            return $quantity_html;
        }

        $qty = $cart_item['quantity'];
        $html = ' <strong class="product-quantity">&times;&nbsp;' . $qty . '</strong> ' . wc_price($regular_price);
        $html .= $this->get_discount_notice_html($cart_item_key);
        return $html;
    }

    /**
     * Disable Advanced Woo Discount Rules when override rule is active
     */
    public function disable_advanced_discounts($applied_rules) {
        if ($this->is_override_active()) {
            return array();
        }
        return $applied_rules;
    }

    /**
     * Disable Woo Discount Rules processing when override rule is active
     */
    public function disable_wdr_rules($process) {
        if ($this->is_override_active()) {
            return false;
        }
        return $process;
    }

    /**
     * Prevent other coupons when override rule coupon is active
     */
    public function prevent_other_coupons($valid, $coupon) {
        if (!WC()->cart) {
            return $valid;
        }

        $applied_coupons = array_map('strtolower', WC()->cart->get_applied_coupons());
        $coupon_code = strtolower($coupon->get_code());
        $rule_sets = MMD_Discount_Rules::get_all_rule_sets();

        // Get all coupon codes linked to override rules
        $override_coupon_codes = array();
        foreach ($rule_sets as $rule_set) {
            if ($rule_set['enabled'] !== 'yes') {
                continue;
            }
            if (empty($rule_set['override_other_discounts']) || $rule_set['override_other_discounts'] !== 'yes') {
                continue;
            }
            if (empty($rule_set['coupon_required']) || $rule_set['coupon_required'] !== 'yes') {
                continue;
            }
            $required_coupons = isset($rule_set['required_coupon_codes']) ? $rule_set['required_coupon_codes'] : array();
            foreach ($required_coupons as $code) {
                $override_coupon_codes[] = strtolower(trim($code));
            }
        }

        // Check if an override coupon is already applied
        foreach ($applied_coupons as $applied_coupon) {
            if (in_array($applied_coupon, $override_coupon_codes)) {
                // Override coupon is applied - prevent other coupons
                if (!in_array($coupon_code, $override_coupon_codes)) {
                    throw new Exception(esc_html__('This coupon cannot be combined with the current coupon.', 'incentiv'));
                }
            }
        }

        // If applying an override coupon, remove other coupons
        if (in_array($coupon_code, $override_coupon_codes) && !empty($applied_coupons)) {
            foreach ($applied_coupons as $applied_coupon) {
                if (!in_array($applied_coupon, $override_coupon_codes)) {
                    WC()->cart->remove_coupon($applied_coupon);
                }
            }
        }

        return $valid;
    }

    /**
     * Calculate and apply discount
     */
    /**
     * Apply rules whose discount is allocated onto the products themselves.
     *
     * Runs on woocommerce_before_calculate_totals so the reduced prices are
     * picked up by the line totals (and therefore by tax, refunds and reports).
     * Rules left on the default "cart fee" allocation are untouched here and
     * continue to be handled in the fees pass.
     *
     * @param object $cart WC_Cart
     */
    public function apply_allocated_discounts($cart) {
        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }
        if (!$cart || !class_exists('MMD_Discount_Allocator')) {
            return;
        }
        // The gift engine and price filters call back into pricing; don't re-enter.
        if ($this->is_doing_cart_calculations) {
            return;
        }

        $rule_sets = MMD_Discount_Rules::get_all_rule_sets();
        if (empty($rule_sets)) {
            return;
        }

        // Which rules allocate onto products?
        $allocating = array();
        foreach ($rule_sets as $rule_set) {
            if (!isset($rule_set['enabled']) || $rule_set['enabled'] !== 'yes') {
                continue;
            }
            if (MMD_Discount_Allocator::get_mode($rule_set) !== MMD_Discount_Allocator::DEFAULT_MODE) {
                $allocating[] = $rule_set;
            }
        }
        if (empty($allocating)) {
            return;
        }

        $this->is_doing_cart_calculations = true;

        // Restore the base prices captured on the first pass so recalculating
        // never compounds a discount that is already on the line.
        $this->restore_allocation_base_prices($cart);

        // Fresh state for this calculation pass.
        $this->cart_allocation = array();
        $this->allocated_rule_ids = array();

        $applied_coupons = array_map('strtolower', $cart->get_applied_coupons());
        $claimed_items = array();

        // Same ordering as the fee pass: override rules claim their items first.
        $ordered = array();
        foreach ($allocating as $rule_set) {
            if (!empty($rule_set['override_other_discounts']) && $rule_set['override_other_discounts'] === 'yes') {
                array_unshift($ordered, $rule_set);
            } else {
                $ordered[] = $rule_set;
            }
        }

        foreach ($ordered as $rule_set) {
            if (!$this->is_rule_active($rule_set, $applied_coupons)) {
                continue;
            }
            if (!$this->is_shipping_method_matching($rule_set)) {
                continue;
            }
            if (!$this->check_additional_conditions($rule_set, $cart)) {
                continue;
            }

            $is_override = (!empty($rule_set['override_other_discounts']) && $rule_set['override_other_discounts'] === 'yes');
            $result = $this->apply_rule_set_discount($cart, $rule_set, $claimed_items, $is_override, true);

            if ($result) {
                foreach ($result['claimed_items'] as $item_key) {
                    $claimed_items[$item_key] = $rule_set['id'];
                }
                if (!empty($rule_set['id'])) {
                    $this->allocated_rule_ids[$rule_set['id']] = true;
                }
            }
        }

        $this->is_doing_cart_calculations = false;
    }

    /**
     * Capture (first pass) or restore (later passes) the pre-allocation price of
     * every cart line, so allocation is idempotent within a request.
     */
    private function restore_allocation_base_prices($cart) {
        foreach ($cart->get_cart() as $key => $item) {
            if (empty($item['data']) || !is_object($item['data'])) {
                continue;
            }
            if (!array_key_exists($key, $this->allocation_base_prices)) {
                // First time we see this line this request — whatever price it
                // has now (after gifts/other engines) is our baseline.
                $this->allocation_base_prices[$key] = floatval($item['data']->get_price());
            } else {
                $item['data']->set_price($this->allocation_base_prices[$key]);
            }
        }
    }

    /**
     * Cart subtotal that is safe to read before totals have been calculated.
     */
    private function get_working_subtotal($cart) {
        $subtotal = floatval($cart->get_subtotal());
        if ($subtotal > 0) {
            return $subtotal;
        }
        $sum = 0.0;
        foreach ($cart->get_cart() as $key => $item) {
            if (empty($item['data']) || !is_object($item['data'])) {
                continue;
            }
            $base = array_key_exists($key, $this->allocation_base_prices)
                ? $this->allocation_base_prices[$key]
                : floatval($item['data']->get_price());
            $sum += $base * max(1, intval($item['quantity']));
        }
        return $sum;
    }

    public function calculate_discount() {
        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }

        $cart = WC()->cart;
        if (!$cart) {
            return;
        }

        // Reset breakdown for this calculation pass. The allocation map is kept
        // — it was built in the before_calculate_totals pass and still describes
        // the discount now sitting on the product lines.
        $this->cart_discount_breakdown = array();
        // Put the allocated discounts back into the display breakdown, so the
        // cart lines and subtotal show what is already inside the line prices.
        foreach ($this->cart_allocation as $key => $entries) {
            foreach ($entries as $entry) {
                $this->cart_discount_breakdown[$key][] = array(
                    'units_discounted' => 1,
                    'discount_per_unit' => $entry['amount'],
                    'discount_total' => $entry['amount'],
                    'rule_name' => $entry['rule_name'],
                    'mode' => 'allocated',
                );
            }
        }

        // Get all active rule sets
        $rule_sets = MMD_Discount_Rules::get_all_rule_sets();

        // Get applied coupon codes in cart (lowercase for comparison)
        $applied_coupons = array_map('strtolower', $cart->get_applied_coupons());

        // Track items that are "claimed" by override rules
        $claimed_items = array();

        // Separate rules into override rules and regular rules
        $override_rules = array();
        $regular_rules = array();

        foreach ($rule_sets as $rule_set) {
            if ($rule_set['enabled'] !== 'yes') {
                continue;
            }

            // Rules that allocate onto products already applied their discount
            // to the line prices before totals were calculated — adding a cart
            // fee too would discount twice.
            if (class_exists('MMD_Discount_Allocator')
                && MMD_Discount_Allocator::get_mode($rule_set) !== MMD_Discount_Allocator::DEFAULT_MODE) {
                continue;
            }

            // Check if rule requires coupon and if coupon is applied
            if (!$this->is_rule_active($rule_set, $applied_coupons)) {
                continue;
            }

            // Check shipping method condition
            if (!$this->is_shipping_method_matching($rule_set)) {
                continue;
            }

            // Check additional conditions (cart total, user role, schedule, usage limits)
            if (!$this->check_additional_conditions($rule_set, $cart)) {
                continue;
            }

            if (!empty($rule_set['override_other_discounts']) && $rule_set['override_other_discounts'] === 'yes') {
                $override_rules[] = $rule_set;
            } else {
                $regular_rules[] = $rule_set;
            }
        }

        // Process override rules first
        foreach ($override_rules as $rule_set) {
            $result = $this->apply_rule_set_discount($cart, $rule_set, $claimed_items, true);
            if ($result) {
                // Mark all qualifying items as claimed by this override rule
                foreach ($result['claimed_items'] as $item_key) {
                    $claimed_items[$item_key] = $rule_set['id'];
                }
            }
        }

        // Process regular rules, each tracking claimed items so rules coexist without double-discounting
        foreach ($regular_rules as $rule_set) {
            $result = $this->apply_rule_set_discount($cart, $rule_set, $claimed_items, false);
            if ($result) {
                // Mark discounted items as claimed so other rules won't double-discount them
                foreach ($result['claimed_items'] as $item_key) {
                    $claimed_items[$item_key] = $rule_set['id'];
                }
            }
        }
    }

    /**
     * Check if a rule is active (based on coupon requirements)
     */
    private function is_rule_active($rule_set, $applied_coupons) {
        // Check if coupon is required - handle various boolean formats
        $coupon_required = isset($rule_set['coupon_required']) &&
                          ($rule_set['coupon_required'] === 'yes' || $rule_set['coupon_required'] === '1' || $rule_set['coupon_required'] === true || $rule_set['coupon_required'] === 1);

        // If coupon is not required, rule is always active
        if (!$coupon_required) {
            return true;
        }

        // If coupon is required but no codes specified, rule is inactive
        $required_coupons = isset($rule_set['required_coupon_codes']) ? $rule_set['required_coupon_codes'] : array();

        // Handle if required_coupon_codes is a comma-separated string instead of array
        if (!is_array($required_coupons) && !empty($required_coupons)) {
            $required_coupons = array_map('trim', explode(',', $required_coupons));
        }

        if (empty($required_coupons)) {
            return false;
        }

        // Check if any of the required coupons is applied
        foreach ($required_coupons as $required_coupon) {
            $required_coupon = strtolower(trim($required_coupon));
            if (in_array($required_coupon, $applied_coupons)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Should the MMD rule actually apply its DISCOUNT (cart fee)?
     *
     * The campaign wizard creates both a native WC coupon AND an MMD
     * discount rule for the same campaign. If the WC coupon has a real
     * non-zero amount, WC discounts the cart on its own; running the MMD
     * rule's fee on top of that produces a double 10%.
     *
     * The MMD rule is still rendered for notices, etc. — this only
     * suppresses the cart fee.
     */
    private function wc_coupon_already_discounts($rule_set) {
        $required = isset($rule_set['required_coupon_codes']) ? $rule_set['required_coupon_codes'] : array();
        if (!is_array($required) && !empty($required)) {
            $required = array_map('trim', explode(',', $required));
        }
        if (empty($required)) return false;

        $applied = WC()->cart ? array_map('strtolower', WC()->cart->get_applied_coupons()) : array();
        foreach ($required as $code) {
            $code = strtolower(trim($code));
            if (!in_array($code, $applied, true)) continue;
            $coupon_id = wc_get_coupon_id_by_code($code);
            if (!$coupon_id) continue;
            $wc_coupon = new WC_Coupon($coupon_id);
            $wc_amount = floatval($wc_coupon->get_amount());
            $wc_type   = $wc_coupon->get_discount_type();
            if ($wc_amount > 0 && in_array($wc_type, array('percent', 'fixed_cart', 'fixed_product'), true)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if the selected shipping method matches the rule's shipping condition
     */
    private function is_shipping_method_matching($rule_set) {
        $shipping_condition = isset($rule_set['shipping_method_condition']) ? $rule_set['shipping_method_condition'] : '';

        // If no shipping condition, rule applies to all shipping methods
        if (empty($shipping_condition)) {
            return true;
        }

        // Get the chosen shipping methods from the WC session
        $chosen_methods = WC()->session ? WC()->session->get('chosen_shipping_methods') : array();

        if (empty($chosen_methods)) {
            return false;
        }

        // Check if any chosen method matches the condition
        foreach ($chosen_methods as $chosen_method) {
            if ($chosen_method === $shipping_condition) {
                return true;
            }
            // Also check partial match (e.g., "flat_rate:1" matches "flat_rate:1")
            if (strpos($chosen_method, $shipping_condition) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check additional conditions (cart total, user role, schedule, usage limits, min quantity)
     */
    /**
     * Remember how a rule's discount was split across cart lines, for per-line
     * display and for stamping onto the order at checkout.
     *
     * @param array $rule_set   The rule that produced the discount.
     * @param array $allocation cart_item_key => discount amount.
     */
    private function record_allocation($rule_set, $allocation) {
        $rule_id = isset($rule_set['id']) ? $rule_set['id'] : '';
        $rule_name = isset($rule_set['name']) ? $rule_set['name'] : '';

        foreach ($allocation as $key => $amount) {
            $amount = round(floatval($amount), 2);
            if ($amount <= 0) {
                continue;
            }

            if (!isset($this->cart_allocation[$key])) {
                $this->cart_allocation[$key] = array();
            }
            $this->cart_allocation[$key][] = array(
                'rule_id' => $rule_id,
                'rule_name' => $rule_name,
                'amount' => $amount,
            );

            // Feed the same numbers into the display breakdown so the cart line
            // shows its original price struck through, exactly like fee-mode rules.
            if (!isset($this->cart_discount_breakdown[$key])) {
                $this->cart_discount_breakdown[$key] = array();
            }
            $this->cart_discount_breakdown[$key][] = array(
                'units_discounted' => 1,
                'discount_per_unit' => $amount,
                'discount_total' => $amount,
                'rule_name' => $rule_name,
                'mode' => 'allocated',
            );
        }
    }

    /**
     * Per-rule totals of everything allocated onto products this request.
     *
     * @return array rule_name => amount
     */
    public function get_allocated_rule_totals() {
        $totals = array();
        foreach ($this->cart_allocation as $entries) {
            foreach ($entries as $entry) {
                $label = $entry['rule_name'] !== '' ? $entry['rule_name'] : __('Discount', 'incentiv');
                if (!isset($totals[$label])) {
                    $totals[$label] = 0.0;
                }
                $totals[$label] += floatval($entry['amount']);
            }
        }
        foreach ($totals as $label => $amount) {
            $totals[$label] = round($amount, 2);
        }
        return $totals;
    }

    /**
     * Show a labelled savings row per rule in the cart / checkout totals.
     *
     * The money is already inside the product line prices (that is what an
     * allocation does), so this row is informational: it names the rule that
     * fired and how much it gave, without changing the total.
     */
    public function render_allocated_discount_rows() {
        $totals = $this->get_allocated_rule_totals();
        if (empty($totals)) {
            return;
        }

        foreach ($totals as $label => $amount) {
            if ($amount <= 0) {
                continue;
            }
            ?>
            <tr class="mmd-savings-row">
                <th>
                    <span class="mmd-savings-label"><?php echo esc_html($label); ?></span>
                </th>
                <td data-title="<?php echo esc_attr($label); ?>">
                    <span class="mmd-savings-amount">
                        &minus;<?php echo wp_kses_post(wc_price($amount)); ?>
                    </span>
                    <small class="mmd-savings-note"><?php esc_html_e('Already included in the product prices', 'incentiv'); ?></small>
                </td>
            </tr>
            <?php
        }
    }

    /**
     * Total allocated discount for a cart line (all rules combined).
     */
    public function get_line_allocation($cart_item_key) {
        if (empty($this->cart_allocation[$cart_item_key])) {
            return 0.0;
        }
        $sum = 0.0;
        foreach ($this->cart_allocation[$cart_item_key] as $entry) {
            $sum += $entry['amount'];
        }
        return round($sum, 2);
    }

    /**
     * Copy the allocation onto the order line so refunds, exports and the
     * per-rule report know how much discount each product actually carried.
     *
     * @param object $item          WC_Order_Item_Product
     * @param string $cart_item_key Cart item key
     * @param array  $values        Cart item
     * @param object $order         WC_Order
     */
    public function stamp_line_allocation($item, $cart_item_key, $values, $order) {
        if (empty($this->cart_allocation[$cart_item_key])) {
            return;
        }

        $total = 0.0;
        $rule_ids = array();
        foreach ($this->cart_allocation[$cart_item_key] as $entry) {
            $total += $entry['amount'];
            if (!empty($entry['rule_id'])) {
                $rule_ids[] = $entry['rule_id'];
            }
        }

        if ($total <= 0) {
            return;
        }

        $item->add_meta_data('_mmd_discount_amount', round($total, 2), true);
        if ($rule_ids) {
            // Primary rule for this line (used by budget spend + reporting).
            $item->add_meta_data('_mmd_rule_id', $rule_ids[0], true);
            if (count($rule_ids) > 1) {
                $item->add_meta_data('_mmd_rule_ids', array_values(array_unique($rule_ids)), true);
            }
        }
    }

    /**
     * Tag an order fee line with the Incentiv rule that produced it, so budget
     * caps and per-rule reporting can attribute the discount after checkout.
     *
     * @param object $item      WC_Order_Item_Fee
     * @param string $fee_key   Fee key
     * @param object $fee       Fee object from the cart
     * @param object $order     WC_Order
     */
    public function stamp_fee_rule_id($item, $fee_key, $fee, $order) {
        $label = is_object($fee) && isset($fee->name) ? $fee->name : '';
        if ($label && isset($this->pending_fee_rule_map[$label])) {
            $item->add_meta_data('_mmd_rule_id', $this->pending_fee_rule_map[$label], true);
        }
    }

    /**
     * Convert a discount the shopper should see into the amount to hand
     * WooCommerce as a cart fee.
     *
     * Discounts are calculated from the prices shoppers actually see. A taxable
     * fee is treated by WooCommerce as a NET figure that it then adds tax to,
     * so on a tax-inclusive store the fee has to be the net equivalent or the
     * shopper is over-discounted. When tax is off — or prices are entered
     * excluding tax — the fee is already the final figure and must be passed
     * through untouched, otherwise the discount silently shrinks by the tax
     * rate (a 10% discount arriving as 8.5%).
     *
     * @param float $discount_amount Discount as shown to the shopper.
     * @return float Amount to pass to add_fee().
     */
    private function discount_to_fee_amount($discount_amount) {
        $discount_amount = floatval($discount_amount);

        // No tax at all, or prices entered excluding tax: nothing to strip.
        if (!function_exists('wc_tax_enabled') || !wc_tax_enabled()) {
            return $discount_amount;
        }
        if (!function_exists('wc_prices_include_tax') || !wc_prices_include_tax()) {
            return $discount_amount;
        }

        $rate = $this->get_fee_tax_rate();
        if ($rate <= 0) {
            return $discount_amount;
        }

        return $discount_amount / (1 + ($rate / 100));
    }

    /**
     * Whether a cart fee was created by the given rule set.
     *
     * Matching is by the label printed on the fee line. A rule with no label at
     * all matches nothing — an empty needle would otherwise match every fee in
     * the cart, including fees other plugins added.
     *
     * @param string $fee_name Name of the fee in the cart.
     * @param array  $rule_set Rule set to test.
     * @return bool
     */
    private function fee_belongs_to_rule($fee_name, $rule_set) {
        $label = MMD_Discount_Rules::get_cart_label($rule_set);
        if ($label === '') {
            return false;
        }
        return strpos($fee_name, $label) !== false;
    }

    /**
     * The tax rate (percentage) WooCommerce will apply to a standard-class fee.
     * Returns 0 when it cannot be determined, so callers fall back to no
     * adjustment rather than to a guessed rate.
     */
    private function get_fee_tax_rate() {
        if (!class_exists('WC_Tax')) {
            return 0.0;
        }

        $rates = WC_Tax::get_rates('');
        if (empty($rates)) {
            $rates = WC_Tax::get_base_tax_rates('');
        }
        if (empty($rates) || !is_array($rates)) {
            return 0.0;
        }

        $total = 0.0;
        foreach ($rates as $rate) {
            if (isset($rate['rate'])) {
                $total += floatval($rate['rate']);
            }
        }

        return $total;
    }

    private function check_additional_conditions($rule_set, $cart) {
        // Shoppers blocked by an add-on (filter `mmd_promotions_blocked`) get no promotions.
        if (MMD_Modules::promotions_blocked('discounts')) {
            return false;
        }

        // Budget cap — a campaign that spent its budget stops applying.
        if (class_exists('MMD_Margin_Guards') && MMD_Margin_Guards::budget_exhausted($rule_set)) {
            return false;
        }

        // Audience conditions (segments) come from an add-on through the
        // `mmd_rule_set_matches_audience` filter. Rules without any always match.
        if (!MMD_Modules::rule_matches_audience($rule_set, $cart)) {
            return false;
        }

        // Check cart total conditions. During the allocation pass the cart
        // subtotal has not been calculated yet, so fall back to summing the
        // pre-discount line values.
        $cart_subtotal = $this->get_working_subtotal($cart);

        $min_cart_total = isset($rule_set['min_cart_total']) && $rule_set['min_cart_total'] !== '' ? floatval($rule_set['min_cart_total']) : 0;
        $max_cart_total = isset($rule_set['max_cart_total']) && $rule_set['max_cart_total'] !== '' ? floatval($rule_set['max_cart_total']) : 0;

        if ($min_cart_total > 0 && $cart_subtotal < $min_cart_total) {
            return false;
        }

        if ($max_cart_total > 0 && $cart_subtotal > $max_cart_total) {
            return false;
        }

        // Check user role
        $allowed_roles = isset($rule_set['allowed_user_roles']) && is_array($rule_set['allowed_user_roles']) ? $rule_set['allowed_user_roles'] : array();
        if (!empty($allowed_roles)) {
            if (!is_user_logged_in()) {
                return false;
            }
            $user = wp_get_current_user();
            $user_roles = $user->roles;
            $has_role = array_intersect($allowed_roles, $user_roles);
            if (empty($has_role)) {
                return false;
            }
        }

        // Check schedule
        $schedule_start = isset($rule_set['schedule_start']) ? $rule_set['schedule_start'] : '';
        $schedule_end = isset($rule_set['schedule_end']) ? $rule_set['schedule_end'] : '';
        $now = current_time('Y-m-d\TH:i');

        if (!empty($schedule_start) && $now < $schedule_start) {
            return false;
        }

        if (!empty($schedule_end) && $now > $schedule_end) {
            return false;
        }

        // Check total usage limit
        $usage_limit_total = isset($rule_set['usage_limit_total']) && $rule_set['usage_limit_total'] !== '' ? intval($rule_set['usage_limit_total']) : 0;
        if ($usage_limit_total > 0) {
            $rule_id = isset($rule_set['id']) ? $rule_set['id'] : '';
            $total_usage = $this->get_rule_usage_count($rule_id);
            if ($total_usage >= $usage_limit_total) {
                return false;
            }
        }

        // Check per-user usage limit
        $usage_limit_per_user = isset($rule_set['usage_limit_per_user']) && $rule_set['usage_limit_per_user'] !== '' ? intval($rule_set['usage_limit_per_user']) : 0;
        if ($usage_limit_per_user > 0) {
            if (!is_user_logged_in()) {
                // For guests, allow but limit by session
                return true;
            }
            $rule_id = isset($rule_set['id']) ? $rule_set['id'] : '';
            $user_id = get_current_user_id();
            $user_usage = $this->get_rule_usage_count($rule_id, $user_id);
            if ($user_usage >= $usage_limit_per_user) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get the usage count for a rule (optionally for a specific user)
     */
    private function get_rule_usage_count($rule_id, $user_id = 0) {
        if (empty($rule_id)) {
            return 0;
        }

        $usage_data = get_option('mmd_rule_usage_counts', array());

        if ($user_id > 0) {
            $key = $rule_id . '_user_' . $user_id;
            return isset($usage_data[$key]) ? intval($usage_data[$key]) : 0;
        }

        $key = $rule_id . '_total';
        return isset($usage_data[$key]) ? intval($usage_data[$key]) : 0;
    }

    /**
     * Save applied rule IDs to order meta during checkout
     */
    public function save_applied_rules_to_order($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        $applied_rules = array();
        $fees = WC()->cart ? WC()->cart->get_fees() : array();

        $rule_sets = MMD_Discount_Rules::get_all_rule_sets();
        foreach ($rule_sets as $rule_set) {
            if ($rule_set['enabled'] !== 'yes') {
                continue;
            }
            // Check if this rule generated a fee (matched on the label that is
            // actually printed on the fee line).
            foreach ($fees as $fee) {
                if ($this->fee_belongs_to_rule($fee->name, $rule_set) || strpos($fee->name, $rule_set['id']) !== false) {
                    $applied_rules[] = $rule_set['id'];
                    break;
                }
            }
        }

        if (!empty($applied_rules)) {
            $order->update_meta_data('_mmd_applied_discount_rules', $applied_rules);
            $order->save();
        }
    }

    /**
     * Increment usage count for a rule after order is placed
     */
    public function increment_rule_usage($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        $usage_data = get_option('mmd_rule_usage_counts', array());
        $applied_rules = $order->get_meta('_mmd_applied_discount_rules');

        if (!empty($applied_rules) && is_array($applied_rules)) {
            foreach ($applied_rules as $rule_id) {
                // Increment total count
                $total_key = $rule_id . '_total';
                $usage_data[$total_key] = isset($usage_data[$total_key]) ? $usage_data[$total_key] + 1 : 1;

                // Increment per-user count
                $user_id = $order->get_customer_id();
                if ($user_id > 0) {
                    $user_key = $rule_id . '_user_' . $user_id;
                    $usage_data[$user_key] = isset($usage_data[$user_key]) ? $usage_data[$user_key] + 1 : 1;
                }
            }
            update_option('mmd_rule_usage_counts', $usage_data);
        }
    }

    /**
     * Apply discount for a specific rule set
     */
    private function apply_rule_set_discount($cart, $rule_set, $claimed_items, $is_override = false, $allocate_pass = false) {
        $qualifying_items = array();
        $qualifying_cart_keys = array();
        $discounted_item_key = null; // Track which specific item gets discounted in quantity mode

        // Collect qualifying items for this rule set
        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            // Skip items already claimed by another rule (override or regular)
            if (isset($claimed_items[$cart_item_key])) {
                continue;
            }

            $product = $cart_item['data'];
            $quantity = $cart_item['quantity'];

            // For override rules, use regular price
            if ($is_override) {
                $_product = wc_get_product($cart_item['variation_id'] ? $cart_item['variation_id'] : $cart_item['product_id']);
                $price = $_product ? $_product->get_regular_price() : $product->get_price();
            } else {
                $price = $product->get_price();
            }

            if (!$price || $price <= 0) {
                continue;
            }

            // Check if product qualifies for this specific rule set
            $qualifying_rule_sets = MMD_Discount_Rules::get_qualifying_rule_sets($product, $cart_item);
            $qualifies = false;

            foreach ($qualifying_rule_sets as $qualifying_set) {
                if ($qualifying_set['id'] === $rule_set['id']) {
                    $qualifies = true;
                    break;
                }
            }

            if (!$qualifies) {
                continue;
            }

            $qualifying_cart_keys[] = $cart_item_key;

            // Add each item individually
            for ($i = 0; $i < $quantity; $i++) {
                $qualifying_items[] = array(
                    'cart_item_key' => $cart_item_key,
                    'price' => $price,
                    'name' => $product->get_name(),
                    'index' => $i
                );
            }
        }

        if (empty($qualifying_items)) {
            return null;
        }

        // Check minimum quantity trigger
        $min_quantity_trigger = isset($rule_set['min_quantity_trigger']) && $rule_set['min_quantity_trigger'] !== '' ? intval($rule_set['min_quantity_trigger']) : 0;
        if ($min_quantity_trigger > 0 && count($qualifying_items) < $min_quantity_trigger) {
            return null;
        }

        $total_qualifying = count($qualifying_items);
        $discount_rules = $rule_set['discount_rules'];
        $discount_mode = isset($rule_set['discount_mode']) ? $rule_set['discount_mode'] : 'quantity';

        // Sort discount rules by quantity
        usort($discount_rules, function($a, $b) {
            return $a['quantity'] - $b['quantity'];
        });

        if ($discount_mode === 'global') {
            // GLOBAL MODE: Apply discount to ALL qualifying items
            // Use the first discount rule (or best matching by quantity)
            $applicable_rule = null;
            foreach ($discount_rules as $rule) {
                if ($total_qualifying >= $rule['quantity']) {
                    $applicable_rule = $rule;
                }
            }

            // If no rule matched by quantity, use the first rule regardless (global = always apply)
            if (!$applicable_rule && !empty($discount_rules)) {
                $applicable_rule = $discount_rules[0];
            }

            if (!$applicable_rule) {
                return null;
            }

            // Calculate total discount across ALL qualifying items
            $total_discount = 0;
            foreach ($qualifying_items as $item) {
                $item_discount = MMD_Helpers::calculate_discount_amount($item['price'], $applicable_rule);
                $total_discount += $item_discount;
            }

            if ($total_discount <= 0) {
                return null;
            }

            $discount_amount = $total_discount;

        } else {
            // QUANTITY MODE (original behavior): Apply discount to 1 item only
            // Sort items by price
            if ($rule_set['apply_to'] === 'cheapest') {
                usort($qualifying_items, function($a, $b) {
                    return $a['price'] <=> $b['price'];
                });
            } else {
                usort($qualifying_items, function($a, $b) {
                    return $b['price'] <=> $a['price'];
                });
            }

            // Find applicable rule (highest quantity that qualifies)
            $applicable_rule = null;
            foreach ($discount_rules as $rule) {
                if ($total_qualifying >= $rule['quantity']) {
                    $applicable_rule = $rule;
                }
            }

            if (!$applicable_rule) {
                return null;
            }

            // Calculate discount on the first item (cheapest or most expensive)
            $discount_amount = MMD_Helpers::calculate_discount_amount(
                $qualifying_items[0]['price'],
                $applicable_rule
            );

            // Track which item actually received the discount
            $discounted_item_key = $qualifying_items[0]['cart_item_key'];
        }

        if ($discount_amount <= 0) {
            return null;
        }

        // Cap discount: ensure it doesn't exceed the total price of qualifying items
        $total_qualifying_price = 0;
        foreach ($qualifying_items as $item) {
            $total_qualifying_price += $item['price'];
        }
        if ($discount_amount > $total_qualifying_price) {
            $discount_amount = $total_qualifying_price;
        }

        // Margin guards — respect the rule's per-cart discount ceiling and any
        // remaining campaign budget before the discount reaches the cart.
        if (class_exists('MMD_Margin_Guards')) {
            $discount_amount = MMD_Margin_Guards::cap_cart_discount(
                $discount_amount,
                $rule_set,
                $this->get_working_subtotal($cart)
            );
            $remaining_budget = MMD_Margin_Guards::budget_remaining($rule_set);
            if (!is_null($remaining_budget) && $discount_amount > $remaining_budget) {
                $discount_amount = $remaining_budget;
            }
            if ($discount_amount <= 0) {
                return array('discount' => 0, 'claimed_items' => array());
            }
        }

        // The cart fee must reduce the customer's total by exactly the discount
        // we calculated. How the fee has to be expressed depends on the store's
        // tax setup, so ask WooCommerce instead of assuming a rate.
        $discount_without_vat = $this->discount_to_fee_amount($discount_amount);

        // Check if this override rule is triggered by a coupon
        // If so, the WooCommerce coupon provides the discount - don't add cart fee
        $coupon_required = isset($rule_set['coupon_required']) &&
                          ($rule_set['coupon_required'] === 'yes' || $rule_set['coupon_required'] === '1' || $rule_set['coupon_required'] === true || $rule_set['coupon_required'] === 1);

        // Determine which items to claim:
        // - Override rules: claim ALL qualifying items (block other rules entirely)
        // - Regular + global mode: claim all qualifying items (all got discounted)
        // - Regular + quantity mode: claim only the actually-discounted item (leave others for other rules)
        if ($is_override) {
            $items_to_claim = $qualifying_cart_keys;
        } elseif ($discount_mode === 'global') {
            $items_to_claim = $qualifying_cart_keys;
        } else {
            // Quantity mode regular rule: only claim the item that actually received the discount
            $items_to_claim = $discounted_item_key ? array($discounted_item_key) : $qualifying_cart_keys;
        }

        // Populate the breakdown so cart display filters can show accurate per-line notices.
        // Mirror the actual discount-mode logic — DO NOT include $is_override here, since
        // override rules can run in quantity OR global mode (override is just a coexistence flag).
        if ($discount_mode === 'global') {
            // All qualifying units in each line are discounted
            $units_per_line = array();
            foreach ($qualifying_items as $item) {
                $key = $item['cart_item_key'];
                $units_per_line[$key] = (isset($units_per_line[$key]) ? $units_per_line[$key] : 0) + 1;
            }
            foreach ($units_per_line as $key => $units) {
                $line_regular = 0;
                foreach ($qualifying_items as $item) {
                    if ($item['cart_item_key'] === $key) $line_regular += $item['price'];
                }
                $unit_regular = $units > 0 ? $line_regular / $units : 0;
                $unit_discounted = $this->calculate_discounted_price($unit_regular, $applicable_rule);
                $per_unit_disc = $unit_regular - $unit_discounted;
                if (!isset($this->cart_discount_breakdown[$key])) $this->cart_discount_breakdown[$key] = array();
                $this->cart_discount_breakdown[$key][] = array(
                    'units_discounted' => $units,
                    'discount_per_unit' => $per_unit_disc,
                    'discount_total' => $per_unit_disc * $units,
                    'discounted_unit_price' => $unit_discounted,
                    'regular_unit_price' => $unit_regular,
                    'rule_name' => $rule_set['name'],
                    'mode' => 'global',
                );
            }
        } else if ($discounted_item_key) {
            // Quantity mode: a single unit on one cart line gets the discount
            $unit_regular = $qualifying_items[0]['price'];
            $unit_discounted = $this->calculate_discounted_price($unit_regular, $applicable_rule);
            $per_unit_disc = $unit_regular - $unit_discounted;
            if (!isset($this->cart_discount_breakdown[$discounted_item_key])) $this->cart_discount_breakdown[$discounted_item_key] = array();
            $this->cart_discount_breakdown[$discounted_item_key][] = array(
                'units_discounted' => 1,
                'discount_per_unit' => $per_unit_disc,
                'discount_total' => $per_unit_disc,
                'discounted_unit_price' => $unit_discounted,
                'regular_unit_price' => $unit_regular,
                'rule_name' => $rule_set['name'],
                'mode' => isset($rule_set['apply_to']) ? $rule_set['apply_to'] : 'cheapest',
            );
        }

        if ($is_override && $coupon_required) {
            // Override rule triggered by coupon - coupon provides the discount
            // Don't add cart fee to avoid double discount
            return array(
                'discount' => $discount_amount,
                'claimed_items' => $items_to_claim
            );
        }

        // The cart line shows exactly the label the merchant set — no generated
        // description is appended to it.
        $discount_label = MMD_Discount_Rules::get_cart_label($rule_set);

        // Apply discount via cart fee. The per-item display shows DB regular price
        // (forced by force_db_price_for_cart_items filter), so the fee is the
        // single source of the discount in the cart total.
        //
        // SKIP the fee when this rule's required coupon is a real WC coupon
        // with its own non-zero amount — WooCommerce is already going to
        // discount the cart, and adding our fee on top double-discounts.
        // We still mark items as claimed so other rules don't try to also
        // discount them, and we still return the discount amount so the
        // notice copy shows the right text. Only the cart-fee insertion
        // is suppressed.
        if ($allocate_pass) {
            // ---- Allocation pass -----------------------------------------
            // Push the discount onto the participating products themselves so
            // the line totals (and therefore tax, refunds and reports) carry it.
            // No cart fee is added for these rules.
            $alloc_mode = MMD_Discount_Allocator::get_mode($rule_set);
            $allocation = MMD_Discount_Allocator::allocate(
                $discount_amount,
                $qualifying_items,
                $alloc_mode,
                isset($rule_set['apply_to']) ? $rule_set['apply_to'] : 'cheapest'
            );

            if (!empty($allocation)) {
                // apply_to_cart returns what was really taken off each line,
                // which can be less than requested when a margin floor bites.
                $applied = MMD_Discount_Allocator::apply_to_cart($cart, $allocation, $rule_set);
                if (!empty($applied)) {
                    $this->record_allocation($rule_set, $applied);
                }
            }

            return array(
                'discount' => $discount_amount,
                'claimed_items' => $items_to_claim,
            );
        }

        if (!$this->wc_coupon_already_discounts($rule_set)) {
            $cart->add_fee($discount_label, -$discount_without_vat, true, 'standard');
            // Remember which rule produced this fee so budget spend and
            // per-rule reporting can attribute it after checkout.
            if (!empty($rule_set['id'])) {
                $this->pending_fee_rule_map[$discount_label] = $rule_set['id'];
            }
        }

        return array(
            'discount' => $discount_amount,
            'claimed_items' => $items_to_claim
        );
    }

    /**
     * Display discount notice
     */
    /**
     * Render the "buy more, save more" tier table on the product page.
     * Matches the v1.9 storefront design: header row + tier rows with the
     * best tier highlighted. Disable via the `mmd_show_tier_table` filter.
     */
    public function display_product_tier_table() {
        global $product;

        if (!$product instanceof WC_Product) {
            return;
        }

        if (!apply_filters('mmd_show_tier_table', true, $product)) {
            return;
        }

        $qualifying_rule_sets = MMD_Discount_Rules::get_qualifying_rule_sets_for_product($product->get_id());
        if (empty($qualifying_rule_sets)) {
            return;
        }

        // First quantity-mode rule with tiers and an enabled notice drives the table
        $rule_set = null;
        foreach ($qualifying_rule_sets as $candidate) {
            $mode = isset($candidate['discount_mode']) ? $candidate['discount_mode'] : 'quantity';
            if ($mode !== 'quantity' || empty($candidate['discount_rules'])) {
                continue;
            }
            if (!empty($candidate['coupon_required']) && $candidate['coupon_required'] === 'yes') {
                continue; // Coupon-gated rules aren't advertised on the product page
            }
            $rule_set = $candidate;
            break;
        }

        if (!$rule_set) {
            return;
        }

        $tiers = $rule_set['discount_rules'];
        usort($tiers, function ($a, $b) {
            return intval($a['quantity']) - intval($b['quantity']);
        });

        $price = floatval($product->get_price());
        $tier_count = count($tiers);

        echo '<div class="mmd-tier-table" style="border:1px solid #eef1f6;border-radius:11px;overflow:hidden;margin:14px 0;font-size:13px;">';
        echo ( '<div style="background:#eef2ff;padding:9px 14px;font-size:12.5px;font-weight:700;color:#3a5bff;display:flex;align-items:center;gap:6px;">' . esc_html__( '📊 Buy more, save more', 'incentiv' ) . '</div>' );

        foreach ($tiers as $i => $tier) {
            $qty = intval($tier['quantity']);
            $discount = floatval($tier['discount']);
            $is_percentage = !isset($tier['type']) || $tier['type'] === 'percentage';
            $is_best = ($i === $tier_count - 1);

            if ($is_percentage && $discount >= 100) {
                $value_label = __( 'Cheapest is free! 🎉', 'incentiv' );
            } elseif ($is_percentage) {
                $value_label = $price > 0
                    ? wp_strip_all_tags(wc_price($price * (1 - $discount / 100))) . ( ' ' . __( '· You save', 'incentiv' ) . ' ' ) . rtrim(rtrim(number_format($discount, 1), '0'), '.') . '%'
                    : ( __( 'A discount of', 'incentiv' ) . ' ' ) . rtrim(rtrim(number_format($discount, 1), '0'), '.') . '%';
            } else {
                $value_label = ( __( 'A discount of', 'incentiv' ) . ' ' ) . wp_strip_all_tags(wc_price($discount));
            }

            $row_style = $is_best
                ? 'display:flex;justify-content:space-between;padding:10px 14px;background:#f4eeff;color:#7c3aed;font-weight:800;'
                : 'display:flex;justify-content:space-between;padding:10px 14px;border-bottom:1px solid #f4f6f9;color:#475467;';

            echo '<div style="' . esc_attr($row_style) . '">';
            echo '<span>' . esc_html($qty) . ( ' ' . esc_html__( 'units', 'incentiv' ) . '</span>' );
            echo '<span style="font-weight:700;">' . esc_html($value_label) . '</span>';
            echo '</div>';
        }

        echo '</div>';
    }

    /**
     * Count cart quantity qualifying for a given rule set.
     */
    private function get_rule_qualifying_quantity($rule_set) {
        if (!WC()->cart) {
            return 0;
        }

        $count = 0;
        foreach (WC()->cart->get_cart() as $cart_item) {
            $product = $cart_item['data'];
            if (!$product) {
                continue;
            }
            foreach (MMD_Discount_Rules::get_qualifying_rule_sets($product, $cart_item) as $qualifying_set) {
                if ($qualifying_set['id'] === $rule_set['id']) {
                    $count += intval($cart_item['quantity']);
                    break;
                }
            }
        }

        return $count;
    }

    public function display_discount_notice() {
        static $notice_displayed = false;
        if ($notice_displayed) {
            return;
        }
        $notice_displayed = true;

        // Get all active rule sets
        $rule_sets = MMD_Discount_Rules::get_all_rule_sets();

        // Display notices for each enabled rule set
        foreach ($rule_sets as $rule_set) {
            if ($rule_set['enabled'] !== 'yes') {
                continue;
            }

            // If rule requires a coupon, only show notice when the coupon is applied
            if (!empty($rule_set['coupon_required']) && $rule_set['coupon_required'] === 'yes') {
                $applied_coupons = WC()->cart ? array_map('strtolower', WC()->cart->get_applied_coupons()) : array();
                if (!$this->is_rule_active($rule_set, $applied_coupons)) {
                    continue;
                }
            }

            // Check if rule is within its scheduled time
            if (!MMD_Discount_Rules::is_rule_in_schedule_public($rule_set)) {
                continue;
            }

            // Check if notice is enabled for this rule set
            if (empty($rule_set['notice_enabled']) || $rule_set['notice_enabled'] !== 'yes') {
                continue;
            }

            if (empty($rule_set['discount_rules'])) {
                continue;
            }

            // Only promote a rule the cart can use: at least one item in the
            // cart must belong to it. (Before 2.1.0 every enabled rule's
            // message showed, so a coffee-only cart also showed the shoes
            // rule and an untargeted default rule.) Filterable to restore
            // the old behaviour.
            $show_without_items = (bool) apply_filters('mmd_show_rule_notice_without_items', false, $rule_set);
            if (!$show_without_items && $this->get_rule_qualifying_quantity($rule_set) <= 0) {
                continue;
            }

            $notice_color = !empty($rule_set['notice_color']) ? $rule_set['notice_color'] : '#4CAF50';
            $notice_title = !empty($rule_set['notice_title']) ? $rule_set['notice_title'] : __( '☕ Buy more and save!', 'incentiv' );

            // Build content
            $content = !empty($rule_set['notice_content'])
                ? $rule_set['notice_content']
                : $this->build_default_notice_content($rule_set['discount_rules'], $rule_set);

            echo '<div class="woocommerce-info mmd-discount-notice" style="margin-bottom: 14px; background: ' . esc_attr($notice_color) . '; border: none; border-radius: 10px; padding: 13px 16px; color: #fff;">';
            echo '<strong style="color: #fff; font-size: 15px;">' . esc_html($notice_title) . '</strong>';
            echo '<div style="margin-top: 6px; line-height: 1.6; color: rgba(255,255,255,.94); font-size: 13.5px;">' . wp_kses_post($content) . '</div>';
            echo '</div>';

            // Progress bar toward the next quantity tier (design v1.9)
            $this->render_tier_progress($rule_set);

            // Display custom cart or checkout message if set
            $custom_message = '';
            if (is_cart() && !empty($rule_set['cart_message'])) {
                $custom_message = $rule_set['cart_message'];
            } elseif (is_checkout() && !empty($rule_set['checkout_message'])) {
                $custom_message = $rule_set['checkout_message'];
            }

            if (!empty($custom_message)) {
                echo '<div class="woocommerce-info mmd-custom-rule-message" style="margin-bottom: 20px; background: #e8f5e9; border-left: 4px solid ' . esc_attr($notice_color) . '; padding: 12px; color: #333;">';
                echo '<div style="line-height: 1.6;">' . wp_kses_post($custom_message) . '</div>';
                echo '</div>';
            }
        }
    }

    /**
     * Render a progress bar toward the next quantity tier (cart/checkout).
     * White card, gradient bar and tier step labels, per the v1.9 design.
     */
    private function render_tier_progress($rule_set) {
        $discount_mode = isset($rule_set['discount_mode']) ? $rule_set['discount_mode'] : 'quantity';
        if ($discount_mode !== 'quantity' || empty($rule_set['discount_rules'])) {
            return;
        }

        $tiers = $rule_set['discount_rules'];
        usort($tiers, function ($a, $b) {
            return intval($a['quantity']) - intval($b['quantity']);
        });

        $current_qty = $this->get_rule_qualifying_quantity($rule_set);
        if ($current_qty <= 0) {
            return;
        }

        $max_qty = intval(end($tiers)['quantity']);
        if ($max_qty <= 0) {
            return;
        }

        // Find the next tier the shopper hasn't reached yet
        $next_tier = null;
        foreach ($tiers as $tier) {
            if ($current_qty < intval($tier['quantity'])) {
                $next_tier = $tier;
                break;
            }
        }

        $pct = min(100, round($current_qty / $max_qty * 100));

        if ($next_tier) {
            $missing = intval($next_tier['quantity']) - $current_qty;
            $is_free = (!isset($next_tier['type']) || $next_tier['type'] === 'percentage') && floatval($next_tier['discount']) >= 100;
            if ($is_free) {
                $title = ( __( 'Add', 'incentiv' ) . ' ' ) . $missing . ($missing === 1 ? ( ' ' . __( 'more unit and the cheapest is', 'incentiv' ) . ' ' ) : ( ' ' . __( 'more units and the cheapest is', 'incentiv' ) . ' ' )) . ( '<span style="color:#7c3aed;">' . esc_html__( 'Free!', 'incentiv' ) . '</span>' );
            } else {
                $title = ( __( 'Add', 'incentiv' ) . ' ' ) . $missing . ($missing === 1 ? ( ' ' . __( '1 more unit', 'incentiv' ) ) : ( ' ' . __( 'units', 'incentiv' ) )) . ( ' ' . __( 'to reach the next discount tier!', 'incentiv' ) );
            }
            $title = '⚡ ' . $title;
        } else {
            $title = __( '🎉 You\'ve reached the highest discount tier!', 'incentiv' );
        }

        echo '<div class="mmd-tier-progress" style="margin-bottom:20px;background:#fff;border:1px solid #e3e8ef;border-radius:14px;padding:16px 18px;box-shadow:0 1px 2px rgba(16,24,40,.04);">';
        echo '<div style="font-size:14px;font-weight:700;color:#101828;margin-bottom:11px;">' . wp_kses_post($title) . '</div>';
        echo '<div style="position:relative;height:10px;background:#eef1f6;border-radius:20px;overflow:hidden;margin-bottom:8px;">';
        echo '<span style="display:block;height:100%;width:' . esc_attr($pct) . '%;background:linear-gradient(90deg,#3a5bff,#7c3aed);border-radius:20px;transition:width .4s ease;"></span>';
        echo '</div>';
        echo '<div style="display:flex;justify-content:space-between;font-size:11.5px;color:#98a2b3;font-weight:600;">';
        $tier_count = count($tiers);
        foreach ($tiers as $i => $tier) {
            $is_last = ($i === $tier_count - 1);
            $is_percentage = !isset($tier['type']) || $tier['type'] === 'percentage';
            $disc = floatval($tier['discount']);
            $label = intval($tier['quantity']) . ( ' ' . __( 'pcs ·', 'incentiv' ) . ' ' ) . ($is_percentage ? ($disc >= 100 ? __( 'Free', 'incentiv' ) : rtrim(rtrim(number_format($disc, 1), '0'), '.') . '%') : wp_strip_all_tags(wc_price($disc)));
            echo '<span style="' . ($is_last ? 'color:#7c3aed;font-weight:700;' : '') . '">' . esc_html($label) . '</span>';
        }
        echo '</div>';
        echo '</div>';
    }

    /**
     * Build default notice content
     */
    private function build_default_notice_content($rules, $rule_set) {
        $content = '';
        $discount_mode = isset($rule_set['discount_mode']) ? $rule_set['discount_mode'] : 'quantity';

        if ($discount_mode === 'global') {
            // Global mode - discount applies to ALL matching products
            foreach ($rules as $rule) {
                $discount_text = MMD_Helpers::format_discount($rule);
                $content .= '• <strong>' . $discount_text . ( '</strong> ' . esc_html__( 'Discount on all products in the promotion', 'incentiv' ) . '<br>' );
            }
            $content .= '<small style="opacity:.85; font-style: italic; display: block; margin-top: 8px;">';
            $content .= __( '* The discount applies to all matching products in the cart.', 'incentiv' );
            $content .= '</small>';
        } else {
            // Quantity mode - original behavior
            $apply_to_text = $rule_set['apply_to'] === 'cheapest' ? __( 'the cheapest', 'incentiv' ) : __( 'the most expensive', 'incentiv' );

            foreach ($rules as $rule) {
                $discount_text = MMD_Helpers::format_discount($rule);
                $content .= ( __( '• Buy', 'incentiv' ) . ' ' ) . $rule['quantity'] . ( ' ' . esc_html__( 'items, get', 'incentiv' ) . ' <strong>' ) . $discount_text . ( '</strong> ' . esc_html__( 'off the', 'incentiv' ) . ' ' ) . $apply_to_text . '<br>';
            }
            $content .= '<small style="opacity:.85; font-style: italic; display: block; margin-top: 8px;">';
            $content .= ( __( '* You can mix different products. The discount applies to the item that is', 'incentiv' ) . ' ' ) . $apply_to_text . '.';
            $content .= '</small>';
        }

        return $content;
    }

    /**
     * Display discount in mini cart
     */
    public function display_discount_in_mini_cart() {
        if (!WC()->cart) {
            return;
        }

        WC()->cart->calculate_fees();
        WC()->cart->calculate_totals();

        // Get all active rule sets
        $rule_sets = MMD_Discount_Rules::get_all_rule_sets();
        $fees = WC()->cart->get_fees();

        foreach ($fees as $fee) {
            // Check if this fee matches any rule set
            foreach ($rule_sets as $rule_set) {
                if ($rule_set['enabled'] !== 'yes') {
                    continue;
                }

                // If rule requires a coupon, only show when coupon is applied
                if (!empty($rule_set['coupon_required']) && $rule_set['coupon_required'] === 'yes') {
                    $applied_coupons = array_map('strtolower', WC()->cart->get_applied_coupons());
                    if (!$this->is_rule_active($rule_set, $applied_coupons)) {
                        continue;
                    }
                }

                // Check if rule is within its scheduled time
                if (!MMD_Discount_Rules::is_rule_in_schedule_public($rule_set)) {
                    continue;
                }

                // Check if mini cart display is enabled for this rule set
                if (empty($rule_set['show_in_mini_cart']) || $rule_set['show_in_mini_cart'] !== 'yes') {
                    continue;
                }

                // Check if fee name contains the rule set name
                if ($this->fee_belongs_to_rule($fee->name, $rule_set)) {
                    // Mirror of discount_to_fee_amount(): show what the shopper
                    // actually saves, which only differs from the fee when the
                    // store is tax-inclusive.
                    $discount_with_vat = abs($fee->amount);
                    if (function_exists('wc_tax_enabled') && wc_tax_enabled()
                        && function_exists('wc_prices_include_tax') && wc_prices_include_tax()) {
                        $rate = $this->get_fee_tax_rate();
                        if ($rate > 0) {
                            $discount_with_vat = $discount_with_vat * (1 + ($rate / 100));
                        }
                    }

                    echo '<div class="mmd-mini-cart-discount" style="padding: 12px 14px; background: #fff; border: 1px solid #e3e8ef; margin: 10px 0; border-radius: 12px; box-shadow: 0 1px 2px rgba(16,24,40,.04);">';
                    echo '<div style="display:flex;justify-content:space-between;align-items:center;gap:8px;">';
                    echo '<span style="color: #475467; font-size: 12.5px; font-weight: 600;">✓ ' . esc_html($fee->name) . '</span>';
                    echo '</div>';
                    echo '<div style="display:flex;justify-content:space-between;align-items:center;margin-top:6px;">';
                    echo ( '<span style="color:#475467;font-size:13px;">' . esc_html__( 'You saved', 'incentiv' ) . '</span>' );
                    echo '<span style="color: #17b26a; font-size: 15px; font-weight: 800;">' . wp_kses_post( wc_price($discount_with_vat) ) . '</span>';
                    echo '</div>';
                    echo '</div>';
                    break 2; // Exit both loops
                }
            }
        }
    }

    /**
     * Update mini cart fragments
     */
    public function update_mini_cart_fragments($fragments) {
        if (WC()->cart) {
            WC()->cart->calculate_fees();
            WC()->cart->calculate_totals();
        }

        ob_start();
        woocommerce_mini_cart();
        $mini_cart = ob_get_clean();

        $fragments['div.widget_shopping_cart_content'] = '<div class="widget_shopping_cart_content">' . $mini_cart . '</div>';

        return $fragments;
    }

    /**
     * Recalculate discount on quantity change
     */
    public function recalculate_discount($cart_item_key, $quantity) {
        WC()->cart->calculate_totals();
    }

    /**
     * Remove discount if cart is empty
     */
    public function maybe_remove_discount() {
        $cart = WC()->cart;
        if (!$cart || $cart->is_empty()) {
            return;
        }

        $fees = $cart->get_fees();
        $rule_sets = MMD_Discount_Rules::get_all_rule_sets();

        foreach ($fees as $fee_key => $fee) {
            // Check if fee matches any rule set name
            foreach ($rule_sets as $rule_set) {
                if ($this->fee_belongs_to_rule($fee->name, $rule_set)) {
                    $cart->remove_fee($fee_key);
                    break;
                }
            }
        }
    }

    /**
     * Handle AJAX refreshed fragments
     */
    public function handle_refreshed_fragments() {
        if (WC()->cart) {
            WC()->cart->calculate_fees();
            WC()->cart->calculate_totals();
        }

        WC_AJAX::get_refreshed_fragments();
    }

    /**
     * Add frontend scripts
     */
    public function add_frontend_scripts() {
        // Check for override rules and output data for JavaScript
        $active_rules = $this->get_active_override_rules();
        $override_data = array(
            'active' => !empty($active_rules),
            'rules' => array()
        );

        if (!empty($active_rules)) {
            foreach ($active_rules as $rule) {
                // Use plugin's discount_rules for display purposes only
                if (!empty($rule['discount_rules'])) {
                    $discount_rules = $rule['discount_rules'];
                    usort($discount_rules, function($a, $b) {
                        return $a['quantity'] - $b['quantity'];
                    });
                    $first_rule = $discount_rules[0];
                    $discount = floatval($first_rule['discount']);

                    // Only add rule if we have a discount to apply for display
                    if ($discount > 0) {
                        $override_data['rules'][] = array(
                            'name' => $rule['name'],
                            'discount' => $discount,
                            'type' => $first_rule['type']
                        );
                    }
                }
            }
        }

        // Always output the override script for product pages and cart
        ?>
        <script type="text/javascript">
        var mmdOverrideData = <?php echo json_encode($override_data); ?>;

        // Provide display_modal_message stub if not defined (prevents JS errors from theme)
        if (typeof display_modal_message === 'undefined') {
            window.display_modal_message = function(message, type) {
                // Fallback: show a simple notification or do nothing
                // This prevents "display_modal_message is not defined" errors
                if (typeof console !== 'undefined' && console.log) {
                    console.log('MMD Modal Message:', message, type);
                }
            };
        }

        jQuery(document).ready(function($) {
            // Function to update mini cart with discount
            function updateMiniCartWithDiscount() {
                if (typeof wc_add_to_cart_params === 'undefined') return;

                $.ajax({
                    url: wc_add_to_cart_params.ajax_url,
                    type: 'POST',
                    data: {'action': 'get_refreshed_fragments'},
                    success: function(data) {
                        if (data && data.fragments) {
                            $.each(data.fragments, function(key, value) {
                                $(key).replaceWith(value);
                            });
                            $(document).trigger('wdShopPageInit');
                            $(document).trigger('wood-images-loaded');
                            // Apply override fixes after fragments update
                            setTimeout(applyOverrideFixes, 100);
                        }
                    }
                });
            }

            // Function to apply override-specific fixes
            function applyOverrideFixes() {
                if (!mmdOverrideData.active || mmdOverrideData.rules.length === 0) {
                    return;
                }

                var rule = mmdOverrideData.rules[0];
                var discountPercent = rule.type === 'percentage' ? rule.discount : 0;
                var discountFixed = rule.type === 'fixed' ? rule.discount : 0;

                // 1. Hide the old savings variation text in mini cart
                $('.woocommerce-mini-cart .variation').each(function() {
                    var $variation = $(this);
                    var text = $variation.text();
                    // Hide if it contains the translated "Savings" text from other discounts
                    if (text.indexOf(<?php echo wp_json_encode( __( 'Savings', 'incentiv' ) ); ?>) !== -1) {
                        $variation.find('li').each(function() {
                            var $li = $(this);
                            if ($li.find('.item-variation-name').text().indexOf(<?php echo wp_json_encode( __( 'Savings', 'incentiv' ) ); ?>) !== -1) {
                                $li.hide();
                            }
                        });
                    }
                });

                // 2. Fix mini cart subtotal - recalculate based on displayed item prices
                fixMiniCartSubtotal();

                // 3. Fix bundle product price display on product page
                fixBundleProductPrice(discountPercent, discountFixed);
            }

            // Fix mini cart subtotal to match discounted item prices
            function fixMiniCartSubtotal() {
                var total = 0;
                var hasDiscountedItems = false;

                $('.woocommerce-mini-cart-item').each(function() {
                    var $item = $(this);
                    var $qtySpan = $item.find('.cart-info > .quantity');
                    var qty = 1;

                    // Get quantity from input or span
                    var $qtyInput = $item.find('input.qty');
                    if ($qtyInput.length) {
                        qty = parseInt($qtyInput.val()) || 1;
                    } else {
                        var qtyMatch = $qtySpan.text().match(/(\d+)\s*×/);
                        if (qtyMatch) {
                            qty = parseInt(qtyMatch[1]) || 1;
                        }
                    }

                    // Get the discounted price (from <ins> if available, otherwise regular)
                    var $insPrice = $qtySpan.find('ins .woocommerce-Price-amount');
                    var $regularPrice = $qtySpan.find('.woocommerce-Price-amount').not('del .woocommerce-Price-amount');

                    var priceText = '';
                    if ($insPrice.length) {
                        priceText = $insPrice.first().text();
                        hasDiscountedItems = true;
                    } else if ($regularPrice.length) {
                        priceText = $regularPrice.first().text();
                    }

                    // Parse price (remove currency symbol and parse)
                    var price = parseFloat(priceText.replace(/[^\d.]/g, '')) || 0;
                    total += price * qty;
                });

                // Update the subtotal if we found discounted items
                if (hasDiscountedItems && total > 0) {
                    var $subtotal = $('.woocommerce-mini-cart__total .woocommerce-Price-amount');
                    if ($subtotal.length) {
                        var currencySymbol = $subtotal.find('.woocommerce-Price-currencySymbol').text() || '₪';
                        $subtotal.html('<bdi><span class="woocommerce-Price-currencySymbol">' + currencySymbol + '</span>' + total.toFixed(2) + '</bdi>');
                    }
                }
            }

            // Fix bundle product price display
            function fixBundleProductPrice(discountPercent, discountFixed) {
                // Target bundle price containers - try multiple selectors
                var $bundlePrice = $('.ecosupp-product-bundle-combined .bundle-main-price, .bundle-total-price .bundle-main-price');

                // Also try to find any price on product page
                if ($bundlePrice.length === 0) {
                    // Try alternative selectors for the price display
                    $bundlePrice = $('.product-page .price, .summary .price, .product-info .price').not('.cart .price');
                }

                if ($bundlePrice.length === 0) {
                    return;
                }

                $bundlePrice.each(function() {
                    var $this = $(this);

                    // Skip if already processed
                    if ($this.data('mmd-override-processed')) return;
                    $this.data('mmd-override-processed', true);
                    $this.attr('data-mmd-processed', 'true'); // For CSS visibility

                    var $priceAmount = $this.find('.woocommerce-Price-amount');
                    if ($priceAmount.length === 0) {
                        $this.attr('data-mmd-processed', 'true'); // Show anyway
                        return;
                    }

                    var priceText = $priceAmount.text();
                    var regularPrice = parseFloat(priceText.replace(/[^\d.]/g, '')) || 0;

                    if (regularPrice <= 0) {
                        $this.attr('data-mmd-processed', 'true'); // Show anyway
                        return;
                    }

                    // Calculate discounted price
                    var discountedPrice = regularPrice;
                    if (discountPercent > 0) {
                        discountedPrice = regularPrice * (1 - (discountPercent / 100));
                    } else if (discountFixed > 0) {
                        discountedPrice = Math.max(0, regularPrice - discountFixed);
                    }

                    // Get currency symbol
                    var currencySymbol = $priceAmount.find('.woocommerce-Price-currencySymbol').text() || '₪';

                    // Replace with crossed out regular + discounted price
                    var newHtml = '<del><span class="woocommerce-Price-amount amount">' + currencySymbol + regularPrice.toFixed(2) + '</span></del> ';
                    newHtml += '<ins><span class="woocommerce-Price-amount amount" style="color: #17b26a; font-weight: bold;">' + currencySymbol + discountedPrice.toFixed(2) + '</span></ins>';

                    $this.html(newHtml);
                });

                // Also update any other bundle price displays
                $('.bundle-price-total, .bundle-subtotal').each(function() {
                    var $this = $(this);
                    if ($this.data('mmd-override-processed')) return;
                    $this.data('mmd-override-processed', true);

                    var $priceAmount = $this.find('.woocommerce-Price-amount');
                    if ($priceAmount.length === 0) return;

                    var priceText = $priceAmount.first().text();
                    var regularPrice = parseFloat(priceText.replace(/[^\d.]/g, '')) || 0;

                    if (regularPrice <= 0) return;

                    var discountedPrice = regularPrice;
                    if (discountPercent > 0) {
                        discountedPrice = regularPrice * (1 - (discountPercent / 100));
                    } else if (discountFixed > 0) {
                        discountedPrice = Math.max(0, regularPrice - discountFixed);
                    }

                    var currencySymbol = $priceAmount.find('.woocommerce-Price-currencySymbol').text() || '₪';
                    var newHtml = '<del>' + currencySymbol + regularPrice.toFixed(2) + '</del> <ins style="color: #17b26a; font-weight: bold;">' + currencySymbol + discountedPrice.toFixed(2) + '</ins>';
                    $this.html(newHtml);
                });
            }

            // Apply fixes immediately and on page load
            applyOverrideFixes();
            $(document).ready(function() {
                applyOverrideFixes();
            });
            $(window).on('load', function() {
                applyOverrideFixes();
            });

            // Debounce to prevent multiple AJAX calls in rapid succession.
            var miniCartUpdatePending = false;
            var lastMiniCartUpdate = 0;
            function debouncedMiniCartUpdate() {
                var now = Date.now();
                if (miniCartUpdatePending) return;
                if (now - lastMiniCartUpdate < 800) return; // Throttle: at most once per 800ms
                miniCartUpdatePending = true;
                setTimeout(function() {
                    lastMiniCartUpdate = Date.now();
                    miniCartUpdatePending = false;
                    updateMiniCartWithDiscount();
                }, 100);
            }

            // Apply fixes when cart events occur. NOTE: do NOT listen to
            // wc_fragments_refreshed — that event is fired BY our own
            // updateMiniCartWithDiscount AJAX response and would create
            // an infinite loop of AJAX calls.
            $(document.body).on('added_to_cart removed_from_cart', function() {
                debouncedMiniCartUpdate();
                setTimeout(applyOverrideFixes, 400);
            });

            $(document.body).on('updated_cart_totals', function() {
                // Don't fire AJAX — totals already updated. Just re-apply visual fixes.
                setTimeout(applyOverrideFixes, 100);
            });

            $(document).on('change', '.widget_shopping_cart .qty, .shopping-cart-widget-body .qty', function() {
                debouncedMiniCartUpdate();
                setTimeout(applyOverrideFixes, 500);
            });

            // Don't refresh fragments on cart icon click — fragments are already
            // up-to-date. Just apply visual fixes.
            $(document).on('click', '.wd-header-cart, .cart-widget-opener', function() {
                setTimeout(applyOverrideFixes, 200);
            });

            // Helper function to clean coupon from URL
            function cleanCouponFromUrl(couponToClean) {
                var url = new URL(window.location.href);
                var modified = false;
                var couponLower = couponToClean ? couponToClean.toLowerCase() : '';

                ['coupon', 'coupon_code', 'coupon-code'].forEach(function(param) {
                    if (url.searchParams.has(param)) {
                        var paramValue = url.searchParams.get(param);
                        // If no specific coupon given, or if it matches, remove it
                        if (!couponLower || (paramValue && paramValue.toLowerCase() === couponLower)) {
                            url.searchParams.delete(param);
                            modified = true;
                        }
                    }
                });

                if (modified) {
                    window.history.replaceState({}, document.title, url.toString());
                }
                return modified;
            }

            // Helper function to get URL coupon
            function getUrlCoupon() {
                var url = new URL(window.location.href);
                return url.searchParams.get('coupon') ||
                       url.searchParams.get('coupon_code') ||
                       url.searchParams.get('coupon-code') || '';
            }

            // Helper function to get all applied coupons from the page
            function getAppliedCoupons() {
                var appliedCoupons = [];
                // Check cart coupon list
                $('.woocommerce-remove-coupon, .cart-discount .remove-coupon, [data-coupon]').each(function() {
                    var href = $(this).attr('href') || '';
                    var dataCoupon = $(this).data('coupon') || '';

                    var match = href.match(/remove_coupon=([^&]+)/);
                    if (match) {
                        appliedCoupons.push(decodeURIComponent(match[1]).toLowerCase());
                    }
                    if (dataCoupon) {
                        appliedCoupons.push(dataCoupon.toLowerCase());
                    }
                });
                // Also check checkout coupon display
                $('.cart-discount td, .coupon-label, .woocommerce-checkout-review-order .cart-discount').each(function() {
                    var text = $(this).text();
                    var couponMatch = text.match(/:\s*([^\s\(]+)/);
                    if (couponMatch && couponMatch[1]) {
                        appliedCoupons.push(couponMatch[1].toLowerCase().replace(/[:\s]/g, ''));
                    }
                });
                return [...new Set(appliedCoupons)]; // Remove duplicates
            }

            // Check if URL coupon is still in cart and clean if not
            function checkAndCleanUrlCoupon() {
                var urlCoupon = getUrlCoupon();
                if (!urlCoupon) return;

                var appliedCoupons = getAppliedCoupons();

                // If URL coupon is not in applied coupons, clean it from URL
                if (appliedCoupons.indexOf(urlCoupon.toLowerCase()) === -1) {
                    cleanCouponFromUrl(urlCoupon);
                }
            }

            // Handle coupon removal - strip coupon parameter from URL to prevent re-application
            // Listen to both cart and checkout coupon removal events
            $(document.body).on('removed_coupon removed_coupon_in_checkout', function(e, coupon_code) {
                if (coupon_code) {
                    cleanCouponFromUrl(coupon_code);
                } else {
                    // If no coupon code provided, check and clean
                    setTimeout(checkAndCleanUrlCoupon, 100);
                }
            });

            // Direct click handler on remove coupon link - most reliable method
            // Extended selector to catch various checkout themes
            $(document).on('click', '.woocommerce-remove-coupon, a[href*="remove_coupon"], .remove-coupon, .coupon-remove, [data-coupon-remove]', function(e) {
                var urlCoupon = getUrlCoupon();
                if (!urlCoupon) return;

                // Get the coupon being removed from this link
                var href = $(this).attr('href') || '';
                var dataCoupon = $(this).data('coupon') || $(this).closest('[data-coupon]').data('coupon') || '';
                var removingCoupon = '';

                var match = href.match(/remove_coupon=([^&]+)/);
                if (match) {
                    removingCoupon = decodeURIComponent(match[1]);
                } else if (dataCoupon) {
                    removingCoupon = dataCoupon;
                }

                // If the coupon being removed matches URL coupon, clean up URL immediately
                if (removingCoupon && removingCoupon.toLowerCase() === urlCoupon.toLowerCase()) {
                    cleanCouponFromUrl(removingCoupon);
                }

                // Also schedule a check after the AJAX completes
                setTimeout(checkAndCleanUrlCoupon, 500);
                setTimeout(checkAndCleanUrlCoupon, 1500);
            });

            // Handle WooCommerce AJAX coupon removal events
            $(document.body).on('wc_fragments_refreshed updated_checkout updated_cart_totals checkout_error', function() {
                setTimeout(checkAndCleanUrlCoupon, 100);
            });

            // Intercept AJAX requests for coupon removal
            $(document).ajaxComplete(function(event, xhr, settings) {
                if (settings && settings.data) {
                    var data = settings.data;
                    // Check if this was a coupon removal request
                    if (typeof data === 'string' && data.indexOf('remove_coupon') !== -1) {
                        setTimeout(checkAndCleanUrlCoupon, 100);
                    }
                }
            });

            // Periodic check as a fallback (every 2 seconds for first 10 seconds, then stop)
            var checkCount = 0;
            var urlCouponCheck = setInterval(function() {
                checkAndCleanUrlCoupon();
                checkCount++;
                if (checkCount >= 5 || !getUrlCoupon()) {
                    clearInterval(urlCouponCheck);
                }
            }, 2000);

            // Watch for DOM changes (for dynamic content)
            if (typeof MutationObserver !== 'undefined') {
                var observer = new MutationObserver(function(mutations) {
                    var shouldApply = false;
                    mutations.forEach(function(mutation) {
                        if (mutation.addedNodes.length > 0) {
                            $(mutation.addedNodes).each(function() {
                                if ($(this).hasClass('widget_shopping_cart_content') ||
                                    $(this).find('.widget_shopping_cart_content').length ||
                                    $(this).hasClass('ecosupp-product-bundle-combined') ||
                                    $(this).find('.ecosupp-product-bundle-combined').length) {
                                    shouldApply = true;
                                }
                            });
                        }
                    });
                    if (shouldApply) {
                        setTimeout(applyOverrideFixes, 200);
                    }
                });

                observer.observe(document.body, {
                    childList: true,
                    subtree: true
                });
            }
        });
        </script>
        <?php
    }

    /**
     * Add frontend styles
     */
    public function add_frontend_styles() {
        // Check if override is active to add price hiding CSS
        $override_active = $this->is_override_active();
        ?>
        <style>
            .mmd-discount-notice {
                animation: mmd-fade-in 0.3s ease-in;
            }
            @keyframes mmd-fade-in {
                from { opacity: 0; transform: translateY(-10px); }
                to { opacity: 1; transform: translateY(0); }
            }
            .mmd-mini-cart-discount {
                animation: mmd-slide-down 0.3s ease-out;
            }
            @keyframes mmd-slide-down {
                from { opacity: 0; max-height: 0; }
                to { opacity: 1; max-height: 200px; }
            }
            .widget_shopping_cart .woocommerce-mini-cart__total,
            .shopping-cart-widget-footer .total {
                position: relative;
                padding-bottom: 10px;
            }
            .cart-discount-notice {
                display: block !important;
                visibility: visible !important;
                opacity: 1 !important;
            }
            .mmd-override-notice {
                animation: mmd-fade-in 0.3s ease-in;
            }
            <?php if ($override_active): ?>
            /* Hide prices initially when override is active - prevents flash */
            .ecosupp-product-bundle-combined .bundle-main-price:not([data-mmd-processed]),
            .bundle-total-price .bundle-main-price:not([data-mmd-processed]) {
                opacity: 0;
                transition: opacity 0.2s ease-in;
            }
            .ecosupp-product-bundle-combined .bundle-main-price[data-mmd-processed],
            .bundle-total-price .bundle-main-price[data-mmd-processed] {
                opacity: 1;
            }
            /* Fallback: show price after animation if JS fails */
            @keyframes mmd-show-price {
                to { opacity: 1; }
            }
            .ecosupp-product-bundle-combined .bundle-main-price,
            .bundle-total-price .bundle-main-price {
                animation: mmd-show-price 0.1s ease-in 1.5s forwards;
            }
            <?php endif; ?>
            /* Override discount price styles */
            .bundle-main-price del,
            .bundle-total-price del,
            .ecosupp-product-bundle-combined del {
                color: #999 !important;
                font-size: 0.9em;
                margin-right: 5px;
            }
            .bundle-main-price ins,
            .bundle-total-price ins,
            .ecosupp-product-bundle-combined ins {
                text-decoration: none !important;
                color: #17b26a !important;
                font-weight: bold !important;
            }
            /* Mini cart override styles */
            .woocommerce-mini-cart-item .quantity del {
                color: #999 !important;
                font-size: 0.9em;
            }
            .woocommerce-mini-cart-item .quantity ins {
                text-decoration: none !important;
                color: #17b26a !important;
                font-weight: bold !important;
            }
            /* Debug panel styles */
            #mmd-debug-footer {
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, monospace !important;
                box-shadow: 0 -5px 20px rgba(0,0,0,0.3) !important;
            }
            /* Discount badge on product image */
            .mmd-discount-badge {
                position: absolute;
                top: 10px;
                right: 10px;
                z-index: 9;
                padding: 5px 14px;
                border-radius: 4px;
                font-size: 13px;
                font-weight: 700;
                line-height: 1.4;
                letter-spacing: 0.3px;
                box-shadow: 0 2px 6px rgba(0,0,0,0.18);
                pointer-events: none;
            }
            /* Ensure parent is positioned for absolute badge */
            .woocommerce ul.products li.product .mmd-discount-badge,
            .woocommerce-page ul.products li.product .mmd-discount-badge {
                position: absolute;
                top: 10px;
                right: 10px;
            }
            ul.products li.product {
                position: relative;
            }
            .woocommerce div.product div.images {
                position: relative;
            }
        </style>
        <?php
    }

    /**
     * Display debug info
     */
    public function display_debug_info() {
        if (!current_user_can('administrator')) {
            return;
        }

        $cart = WC()->cart;
        $settings = MMD_Helpers::get_settings();

        echo '<div style="background: #fff3cd; border: 1px solid #ffc107; padding: 15px; margin: 20px 0; font-size: 13px; direction: ' . ( is_rtl() ? 'rtl' : 'ltr' ) . ';">';
        echo ( '<strong style="color: #856404;">' . esc_html__( '🔧 Debug info (admins only):', 'incentiv' ) . '</strong><br><br>' );

        echo ( '<strong>' . esc_html__( 'Selection method:', 'incentiv' ) . '</strong> ' ) . esc_html($settings['selection_method']) . '<br>';
        echo ( '<strong>' . esc_html__( 'Apply to:', 'incentiv' ) . '</strong> ' ) . esc_html($settings['apply_to']) . '<br>';
        echo ( '<strong>' . esc_html__( 'Number of discount tiers:', 'incentiv' ) . '</strong> ' ) . count($settings['discount_rules']) . '<br><br>';

        echo ( '<strong>' . esc_html__( 'Products in cart:', 'incentiv' ) . '</strong><br>' );

        foreach ($cart->get_cart() as $cart_item) {
            $product = $cart_item['data'];
            $product_id = $cart_item['product_id'];
            $variation_id = isset($cart_item['variation_id']) ? $cart_item['variation_id'] : 0;
            $is_qualifying = MMD_Helpers::is_product_qualifying($product, $cart_item);

            echo '<div style="margin-bottom: 10px; padding: 8px; background: white; border-left: 3px solid ' . ($is_qualifying ? '#17b26a' : '#dc3545') . ';">';
            echo '<strong>' . esc_html( $product->get_name() ) . '</strong><br>';
            echo 'Product ID: ' . esc_html( $product_id );
            if ($variation_id) {
                echo ' | Variation ID: ' . esc_html( $variation_id );
            }
            echo ( ' ' . esc_html__( '| Qty:', 'incentiv' ) . ' ' ) . esc_html( $cart_item['quantity'] );
            echo ( ' ' . esc_html__( '| Price:', 'incentiv' ) . ' ' ) . wp_kses_post( wc_price($product->get_price()) );
            echo ( ' | <strong>' . esc_html__( 'Eligible:', 'incentiv' ) . ' ' ) . ($is_qualifying ? esc_html__( 'Yes ✓', 'incentiv' ) : esc_html__( 'No ✗', 'incentiv' )) . '</strong>';

            // Show attributes for variations
            if ($product->is_type('variation')) {
                $attributes = $product->get_variation_attributes();
                if (!empty($attributes)) {
                    echo ( '<br>' . esc_html__( 'Attributes:', 'incentiv' ) . ' ' );
                    foreach ($attributes as $key => $value) {
                        echo '<span style="background: #e9ecef; padding: 2px 5px; margin: 2px; display: inline-block;">' . esc_html( $key ) . ': ' . esc_html( $value ) . '</span>';
                    }
                }
            }

            // Show categories
            $categories = wp_get_post_terms($product_id, 'product_cat', array('fields' => 'slugs'));
            if (!empty($categories)) {
                echo ( '<br>' . esc_html__( 'Categories:', 'incentiv' ) . ' ' ) . esc_html( implode(', ', $categories) );
            }

            echo '</div>';
        }

        echo '</div>';
    }
}
