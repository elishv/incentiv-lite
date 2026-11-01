<?php
/**
 * Per-rule diagnostic tool.
 *
 * Renders an admin diagnostic panel on the rule edit screen and powers a
 * "test against product" AJAX endpoint. Output is a single JSON blob the
 * user can copy and paste when reporting issues.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class MMD_Rule_Diagnostic {

    public function __construct() {
        add_action( 'wp_ajax_mmd_rule_diagnostic_run', array( $this, 'ajax_run' ) );
        add_action( 'wp_ajax_mmd_rule_diagnostic_product', array( $this, 'ajax_product_test' ) );
    }

    /**
     * Build a diagnostic payload for the given rule set.
     *
     * Safe to call from admin context. No cart/session access; uses static
     * rule helpers and product lookups only.
     *
     * @param array      $rule_set   The rule set configuration.
     * @param int|null   $product_id Optional product ID to test the rule against.
     * @return array
     */
    public static function build_diagnostic( array $rule_set, $product_id = null ) {
        global $wp_version;

        $diagnostic = array(
            'generated_at' => gmdate( 'c' ),
            'environment'  => array(
                'php_version'        => PHP_VERSION,
                'php_max_stack_size' => function_exists( 'ini_get' ) ? ini_get( 'zend.max_allowed_stack_size' ) : null,
                'wp_version'         => $wp_version,
                'wc_version'         => defined( 'WC_VERSION' ) ? WC_VERSION : null,
                'mmd_version'        => defined( 'MMD_VERSION' ) ? MMD_VERSION : null,
                'site_url'           => home_url( '/' ),
                'multisite'          => is_multisite(),
                'object_cache'       => wp_using_ext_object_cache(),
                'locale'             => get_locale(),
                'memory_limit'       => function_exists( 'ini_get' ) ? ini_get( 'memory_limit' ) : null,
            ),
            'rule'         => self::sanitize_rule_for_export( $rule_set ),
            'rule_summary' => self::summarize_rule( $rule_set ),
            'schedule'     => self::evaluate_schedule( $rule_set ),
            'activation'   => self::evaluate_activation( $rule_set ),
            'hooks'        => self::list_registered_hooks(),
            'plugins'      => self::list_relevant_plugins(),
            'warnings'     => self::collect_warnings( $rule_set ),
        );

        if ( $product_id ) {
            $diagnostic['product_test'] = self::test_against_product( $rule_set, (int) $product_id );
        }

        return $diagnostic;
    }

    /**
     * Strip arrays that are huge for export readability but keep enough to
     * understand the rule. Categories/products/variations stay as IDs.
     */
    private static function sanitize_rule_for_export( array $rule_set ) {
        // Casts everything to scalars/arrays. JSON-encoding will catch nested objects.
        $clone = array();
        foreach ( $rule_set as $key => $value ) {
            if ( is_object( $value ) ) {
                $clone[ $key ] = '[object:' . get_class( $value ) . ']';
                continue;
            }
            $clone[ $key ] = $value;
        }
        return $clone;
    }

    /**
     * Human-readable summary of what the rule does.
     */
    private static function summarize_rule( array $rule_set ) {
        $enabled            = self::truthy( $rule_set, 'enabled' );
        $override           = self::truthy( $rule_set, 'override_other_discounts' );
        $coupon_required    = self::truthy( $rule_set, 'coupon_required' );
        $required_coupons   = isset( $rule_set['required_coupon_codes'] ) ? $rule_set['required_coupon_codes'] : array();
        if ( ! is_array( $required_coupons ) && ! empty( $required_coupons ) ) {
            $required_coupons = array_map( 'trim', explode( ',', $required_coupons ) );
        }

        return array(
            'id'                  => isset( $rule_set['id'] ) ? $rule_set['id'] : '',
            'name'                => isset( $rule_set['name'] ) ? $rule_set['name'] : '',
            'enabled'             => $enabled,
            'override_other'      => $override,
            'discount_mode'       => isset( $rule_set['discount_mode'] ) ? $rule_set['discount_mode'] : '',
            'apply_to'            => isset( $rule_set['apply_to'] ) ? $rule_set['apply_to'] : '',
            'selection_method'    => isset( $rule_set['selection_method'] ) ? $rule_set['selection_method'] : '',
            'coupon_required'     => $coupon_required,
            'required_coupons'    => $required_coupons,
            'discount_rules'      => isset( $rule_set['discount_rules'] ) ? $rule_set['discount_rules'] : array(),
            'min_cart_total'      => isset( $rule_set['min_cart_total'] ) ? $rule_set['min_cart_total'] : '',
            'max_cart_total'      => isset( $rule_set['max_cart_total'] ) ? $rule_set['max_cart_total'] : '',
            'allowed_user_roles'  => isset( $rule_set['allowed_user_roles'] ) ? $rule_set['allowed_user_roles'] : array(),
        );
    }

    /**
     * Evaluate the rule's schedule window against current time.
     */
    private static function evaluate_schedule( array $rule_set ) {
        $start = isset( $rule_set['schedule_start'] ) ? $rule_set['schedule_start'] : '';
        $end   = isset( $rule_set['schedule_end'] ) ? $rule_set['schedule_end'] : '';
        $now   = current_time( 'Y-m-d\TH:i' );

        $reasons = array();
        $in_window = true;
        if ( ! empty( $start ) && $now < $start ) {
            $in_window = false;
            $reasons[] = 'before schedule_start';
        }
        if ( ! empty( $end ) && $now > $end ) {
            $in_window = false;
            $reasons[] = 'after schedule_end';
        }

        return array(
            'now'       => $now,
            'start'     => $start,
            'end'       => $end,
            'in_window' => $in_window,
            'reasons'   => $reasons,
        );
    }

    /**
     * Evaluate whether the rule is currently "active" — based on enabled
     * state, schedule, and coupon requirement. We cannot read the user's
     * cart from admin, so coupon-required rules report "would activate if
     * coupon X applied".
     */
    private static function evaluate_activation( array $rule_set ) {
        $enabled         = self::truthy( $rule_set, 'enabled' );
        $coupon_required = self::truthy( $rule_set, 'coupon_required' );
        $required        = isset( $rule_set['required_coupon_codes'] ) ? $rule_set['required_coupon_codes'] : array();
        if ( ! is_array( $required ) && ! empty( $required ) ) {
            $required = array_map( 'trim', explode( ',', $required ) );
        }
        $schedule = self::evaluate_schedule( $rule_set );

        $reasons = array();
        if ( ! $enabled ) {
            $reasons[] = 'rule is disabled (enabled != yes)';
        }
        if ( ! $schedule['in_window'] ) {
            foreach ( $schedule['reasons'] as $r ) {
                $reasons[] = 'schedule: ' . $r;
            }
        }
        if ( $coupon_required && empty( $required ) ) {
            $reasons[] = 'coupon required but no required_coupon_codes specified';
        }

        return array(
            'enabled'                => $enabled,
            'currently_in_schedule'  => $schedule['in_window'],
            'coupon_required'        => $coupon_required,
            'required_coupons'       => $required,
            'always_active'          => $enabled && $schedule['in_window'] && ! $coupon_required,
            'blocking_reasons'       => $reasons,
        );
    }

    /**
     * List the plugin's hook callbacks that are likely to fire when this
     * rule is active. Useful for diagnosing recursion / interaction issues.
     */
    private static function list_registered_hooks() {
        $hooks_of_interest = array(
            'woocommerce_product_get_price',
            'woocommerce_product_get_sale_price',
            'woocommerce_product_get_regular_price',
            'woocommerce_product_variation_get_price',
            'woocommerce_product_variation_get_sale_price',
            'woocommerce_product_is_on_sale',
            'woocommerce_get_price_html',
            'woocommerce_variation_prices_price',
            'woocommerce_variation_prices_sale_price',
            'woocommerce_get_variation_prices_hash',
            'woocommerce_cart_calculate_fees',
            'woocommerce_before_calculate_totals',
            'woocommerce_coupon_is_valid',
            'woocommerce_cart_needs_shipping',
        );

        global $wp_filter;
        $out = array();
        foreach ( $hooks_of_interest as $hook ) {
            if ( ! isset( $wp_filter[ $hook ] ) ) {
                $out[ $hook ] = array();
                continue;
            }
            $callbacks = array();
            foreach ( $wp_filter[ $hook ]->callbacks as $priority => $cb_list ) {
                foreach ( $cb_list as $cb ) {
                    $callbacks[] = array(
                        'priority' => $priority,
                        'callback' => self::callback_to_string( $cb['function'] ),
                    );
                }
            }
            $out[ $hook ] = $callbacks;
        }
        return $out;
    }

    private static function callback_to_string( $cb ) {
        if ( is_string( $cb ) ) {
            return $cb;
        }
        if ( is_array( $cb ) && count( $cb ) === 2 ) {
            $first = is_object( $cb[0] ) ? get_class( $cb[0] ) : (string) $cb[0];
            return $first . '::' . (string) $cb[1];
        }
        if ( $cb instanceof Closure ) {
            return '[Closure]';
        }
        if ( is_object( $cb ) && method_exists( $cb, '__invoke' ) ) {
            return get_class( $cb ) . '::__invoke';
        }
        return '[unknown]';
    }

    /**
     * List active plugins that commonly interfere with cart/pricing logic.
     * Helps reproduce environment issues (EBP, Redis cache, ACF, etc.).
     */
    private static function list_relevant_plugins() {
        if ( ! function_exists( 'get_option' ) ) {
            return array();
        }
        $active = (array) get_option( 'active_plugins', array() );
        if ( is_multisite() ) {
            $network = (array) get_site_option( 'active_sitewide_plugins', array() );
            $active  = array_merge( $active, array_keys( $network ) );
        }
        $interesting_keywords = array(
            'event', 'booking', 'ebp', 'redis', 'object-cache', 'cache',
            'discount', 'coupon', 'gateway', 'payment', 'valuecard', 'loyalty',
            'shipping', 'woocommerce', 'acf', 'advanced-custom-fields',
        );
        $matched = array();
        foreach ( $active as $slug ) {
            $lower = strtolower( $slug );
            foreach ( $interesting_keywords as $kw ) {
                if ( strpos( $lower, $kw ) !== false ) {
                    $matched[] = $slug;
                    break;
                }
            }
        }
        sort( $matched );
        return array_values( array_unique( $matched ) );
    }

    /**
     * Surface common misconfigurations that could cause critical errors.
     */
    private static function collect_warnings( array $rule_set ) {
        $warnings = array();

        $discount_rules = isset( $rule_set['discount_rules'] ) ? $rule_set['discount_rules'] : array();
        if ( empty( $discount_rules ) ) {
            $warnings[] = 'No discount tiers (discount_rules) configured.';
        }

        $selection_method = isset( $rule_set['selection_method'] ) ? $rule_set['selection_method'] : '';
        if ( $selection_method === 'category' ) {
            $included = isset( $rule_set['included_categories'] ) ? (array) $rule_set['included_categories'] : array();
            if ( empty( $included ) ) {
                $warnings[] = 'Selection method is "category" but no included_categories selected — rule will match all products.';
            }
        }
        if ( $selection_method === 'product' ) {
            $included = isset( $rule_set['included_products'] ) ? (array) $rule_set['included_products'] : array();
            if ( empty( $included ) ) {
                $warnings[] = 'Selection method is "product" but no included_products selected.';
            }
        }
        if ( $selection_method === 'attribute' ) {
            $slug = isset( $rule_set['attribute_slug'] ) ? $rule_set['attribute_slug'] : '';
            if ( empty( $slug ) ) {
                $warnings[] = 'Selection method is "attribute" but no attribute_slug set.';
            }
        }

        if ( self::truthy( $rule_set, 'override_other_discounts' ) && ! self::truthy( $rule_set, 'coupon_required' ) ) {
            $warnings[] = 'Rule overrides other discounts and is always active (no coupon required) — verify this is intended.';
        }

        $min = isset( $rule_set['min_cart_total'] ) ? $rule_set['min_cart_total'] : '';
        $max = isset( $rule_set['max_cart_total'] ) ? $rule_set['max_cart_total'] : '';
        if ( $min !== '' && $max !== '' && is_numeric( $min ) && is_numeric( $max ) && (float) $min > (float) $max ) {
            $warnings[] = 'min_cart_total > max_cart_total — rule will never trigger.';
        }

        return $warnings;
    }

    /**
     * Run the rule's qualification logic against a specific product, and
     * report each step. This is the most useful field for reproducing
     * "rule active = critical error" cases.
     */
    private static function test_against_product( array $rule_set, $product_id ) {
        $result = array(
            'product_id' => $product_id,
            'found'      => false,
        );

        if ( ! function_exists( 'wc_get_product' ) ) {
            $result['error'] = 'wc_get_product() not available — WooCommerce not loaded.';
            return $result;
        }

        $product = wc_get_product( $product_id );
        if ( ! $product ) {
            $result['error'] = 'Product not found.';
            return $result;
        }

        $result['found']         = true;
        $result['product_type']  = $product->get_type();
        $result['product_name']  = $product->get_name();
        $result['product_status'] = $product->get_status();

        // Read prices WITHOUT triggering filters where possible.
        // get_regular_price() and get_price() trigger filters; that's exactly
        // what we want to see, but we capture results carefully.
        $result['prices'] = array(
            'regular_price' => self::safe_call( array( $product, 'get_regular_price' ) ),
            'sale_price'    => self::safe_call( array( $product, 'get_sale_price' ) ),
            'price'         => self::safe_call( array( $product, 'get_price' ) ),
            'is_on_sale'    => self::safe_call( array( $product, 'is_on_sale' ) ),
        );

        // Categories assigned to product (for selection_method=category debugging)
        $cat_ids = array();
        if ( $product->get_type() === 'variation' ) {
            $parent_id = $product->get_parent_id();
            if ( $parent_id ) {
                $cat_ids = wc_get_product_term_ids( $parent_id, 'product_cat' );
            }
        } else {
            $cat_ids = wc_get_product_term_ids( $product->get_id(), 'product_cat' );
        }
        $result['product_categories'] = $cat_ids;

        // Run the rule's qualification logic.
        $cart_item = array(
            'product_id'   => $product->get_parent_id() ? $product->get_parent_id() : $product->get_id(),
            'variation_id' => $product->is_type( 'variation' ) ? $product->get_id() : 0,
            'data'         => $product,
        );

        if ( method_exists( 'MMD_Discount_Rules', 'get_qualifying_rule_sets' ) ) {
            $qualifying = MMD_Discount_Rules::get_qualifying_rule_sets( $product, $cart_item );
            $rule_id    = isset( $rule_set['id'] ) ? $rule_set['id'] : '';
            $matched    = false;
            foreach ( $qualifying as $q ) {
                if ( isset( $q['id'] ) && $q['id'] === $rule_id ) {
                    $matched = true;
                    break;
                }
            }
            $result['qualifies_for_this_rule'] = $matched;
            $result['all_qualifying_rule_ids'] = array_values( array_filter( array_map( static function ( $r ) {
                return isset( $r['id'] ) ? $r['id'] : null;
            }, $qualifying ) ) );
        }

        return $result;
    }

    /**
     * Wrap a call in try/catch so diagnostics don't crash on bad data.
     */
    private static function safe_call( $callable ) {
        try {
            $value = call_user_func( $callable );
            if ( is_object( $value ) ) {
                return '[object:' . get_class( $value ) . ']';
            }
            return $value;
        } catch ( \Throwable $e ) {
            return '[error: ' . $e->getMessage() . ']';
        }
    }

    private static function truthy( array $arr, $key ) {
        if ( ! isset( $arr[ $key ] ) ) {
            return false;
        }
        $v = $arr[ $key ];
        return ( $v === 'yes' || $v === '1' || $v === true || $v === 1 );
    }

    /**
     * AJAX: re-run the diagnostic for a rule (without product test).
     * Used when the rule has unsaved edits in the form — the JS posts the
     * current form state.
     */
    public function ajax_run() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => 'Forbidden' ), 403 );
        }
        check_ajax_referer( 'mmd_rule_diagnostic', 'nonce' );

        $rule_set_id = isset( $_POST['rule_set_id'] ) ? sanitize_text_field( wp_unslash( $_POST['rule_set_id'] ) ) : '';
        if ( empty( $rule_set_id ) ) {
            wp_send_json_error( array( 'message' => 'rule_set_id required' ), 400 );
        }

        $rule_set = MMD_Discount_Rules::get_rule_set( $rule_set_id );
        if ( ! $rule_set ) {
            wp_send_json_error( array( 'message' => 'Rule not found' ), 404 );
        }

        wp_send_json_success( self::build_diagnostic( $rule_set ) );
    }

    /**
     * AJAX: test a saved rule against a specific product.
     */
    public function ajax_product_test() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => 'Forbidden' ), 403 );
        }
        check_ajax_referer( 'mmd_rule_diagnostic', 'nonce' );

        $rule_set_id = isset( $_POST['rule_set_id'] ) ? sanitize_text_field( wp_unslash( $_POST['rule_set_id'] ) ) : '';
        $product_id  = isset( $_POST['product_id'] ) ? absint(wp_unslash($_POST['product_id'])) : 0;
        if ( empty( $rule_set_id ) || empty( $product_id ) ) {
            wp_send_json_error( array( 'message' => 'rule_set_id and product_id required' ), 400 );
        }

        $rule_set = MMD_Discount_Rules::get_rule_set( $rule_set_id );
        if ( ! $rule_set ) {
            wp_send_json_error( array( 'message' => 'Rule not found' ), 404 );
        }

        wp_send_json_success( self::build_diagnostic( $rule_set, $product_id ) );
    }
}

new MMD_Rule_Diagnostic();
