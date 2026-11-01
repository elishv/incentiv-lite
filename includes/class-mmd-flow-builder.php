<?php
/**
 * Incentiv Flow Builder
 *
 * Visualizes every promotion mechanic (rules, gifts, coupons, events) as a
 * customer-journey flow, and compiles flows built on the canvas into real
 * discount rules, gift rules and linked coupons.
 */

if (!defined('ABSPATH')) {
    exit;
}

class MMD_Flow_Builder {

    private static $instance = null;

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_menu', array($this, 'add_menu'), 14);
        add_action('admin_enqueue_scripts', array($this, 'enqueue'));
        add_action('wp_ajax_mmd_create_flow', array($this, 'ajax_create_flow'));
    }

    public function add_menu() {
        add_submenu_page(
            'mix-match-discounts',
            __( 'Flows', 'incentiv' ),
            __( 'Flows', 'incentiv' ),
            'manage_woocommerce',
            'mmd-flows',
            array($this, 'render_page')
        );
    }

    public function enqueue($hook) {
        if (strpos($hook, 'mmd-flows') === false) {
            return;
        }

        wp_enqueue_script('mmd-flow-builder', MMD_PLUGIN_URL . 'assets/flow-builder.js', array('jquery', 'wp-i18n'), MMD_VERSION, true);
        wp_set_script_translations('mmd-flow-builder', 'incentiv', MMD_PLUGIN_DIR . 'languages');
        wp_localize_script('mmd-flow-builder', 'mmdFlow', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mmd_create_flow'),
            'categories' => $this->get_categories(),
            'products' => $this->get_products_sample(),
            'roles' => $this->get_roles(),
            'rulesUrl' => admin_url('admin.php?page=mix-match-discounts'),
            'giftsUrl' => admin_url('admin.php?page=mmd-gift-rules'),
        ));
    }

    public function render_page() {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }

        include MMD_PLUGIN_DIR . 'templates/admin-flows.php';
    }

    private function get_categories() {
        $out = array();
        $terms = get_terms(array('taxonomy' => 'product_cat', 'hide_empty' => false));
        if (!is_wp_error($terms)) {
            foreach ($terms as $term) {
                $out[] = array('id' => $term->term_id, 'name' => $term->name);
            }
        }
        return $out;
    }

    private function get_roles() {
        $out = array();
        if (!function_exists('wp_roles')) {
            return $out;
        }
        $roles = wp_roles()->roles;
        foreach ($roles as $slug => $role) {
            $out[] = array('id' => $slug, 'name' => translate_user_role($role['name']));
        }
        return $out;
    }

    private function get_products_sample() {
        $out = array();
        $ids = get_posts(array('post_type' => 'product', 'post_status' => 'publish', 'posts_per_page' => 100, 'fields' => 'ids'));
        foreach ($ids as $pid) {
            $product = function_exists('wc_get_product') ? wc_get_product($pid) : null;
            if ($product) {
                $out[] = array('id' => $pid, 'name' => $product->get_name(), 'price' => floatval($product->get_price()));
            }
        }
        return $out;
    }

    /**
     * Compile a node flow into real mechanics.
     * Payload: name, nodes (JSON array of {type, cfg}).
     */
    public function ajax_create_flow() {
        check_ajax_referer('mmd_create_flow', 'nonce');
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('forbidden');
        }

        $name = sanitize_text_field(isset($_POST['name']) ? wp_unslash($_POST['name']) : '');
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON; every node field is sanitized (sanitize_text_field / intval / floatval) where it is read below.
        $nodes = json_decode(isset($_POST['nodes']) ? wp_unslash($_POST['nodes']) : '[]', true);

        if (!$name) {
            wp_send_json_error(__( 'Please give the flow a name', 'incentiv' ));
        }
        if (!is_array($nodes) || !count($nodes)) {
            wp_send_json_error(__( 'The flow is empty. Add at least one step', 'incentiv' ));
        }

        // ---- Collect node data ------------------------------------------------
        $scope_method = 'all';
        $scope_categories = array();
        $min_qty = 0;
        $min_total = '';
        $roles = array();
        $coupon_code = '';
        $tiers = array();
        $discount_target = 'cheapest';
        $gift = null;

        foreach ($nodes as $node) {
            $type = isset($node['type']) ? sanitize_text_field($node['type']) : '';
            $cfg = isset($node['cfg']) && is_array($node['cfg']) ? $node['cfg'] : array();

            switch ($type) {
                case 'trigger_qty':
                    $min_qty = max(1, intval($cfg['qty'] ?? 1));
                    if (!empty($cfg['categories'])) {
                        $scope_method = 'category';
                        $scope_categories = array_map('intval', (array) $cfg['categories']);
                    }
                    break;
                case 'trigger_total':
                    $min_total = floatval($cfg['min'] ?? 0);
                    break;
                case 'trigger_coupon':
                    $coupon_code = strtoupper(sanitize_text_field($cfg['code'] ?? ''));
                    break;
                case 'trigger_role':
                    $roles = array_map('sanitize_text_field', (array) ($cfg['roles'] ?? array()));
                    break;
                case 'action_discount':
                    $tiers[] = array(
                        'quantity' => max(1, intval($cfg['qty'] ?? max(1, $min_qty))),
                        'discount' => floatval($cfg['amount'] ?? 10),
                        'type' => ($cfg['kind'] ?? 'percentage') === 'fixed' ? 'fixed' : 'percentage',
                    );
                    if (($cfg['target'] ?? 'cheapest') === 'all') {
                        $discount_target = 'all';
                    }
                    break;
                case 'action_gift_choice':
                case 'action_gift_auto':
                    $gift = array(
                        'mode' => $type === 'action_gift_choice' ? 'product_page' : 'cart_based',
                        'products' => array_map('intval', (array) ($cfg['products'] ?? array())),
                        'limit' => max(1, intval($cfg['limit'] ?? 1)),
                    );
                    break;
            }
        }

        if (!$tiers && !$gift) {
            wp_send_json_error(__( 'Add at least one action: a discount or a gift', 'incentiv' ));
        }

        $coupon_lower = strtolower($coupon_code);
        $result = array(
            'discount_rule_id' => null,
            'gift_rule_id' => null,
            'coupon_id' => null,
            'auto_apply_url' => '',
        );

        // ---- Discount rule ----------------------------------------------------
        if ($tiers) {
            usort($tiers, function ($a, $b) {
                return $a['quantity'] - $b['quantity'];
            });

            $rule_id = 'flow_' . sanitize_title($name) . '_' . wp_rand(100, 999);
            $rule_set = array(
                'id' => $rule_id,
                'name' => $name,
                'enabled' => 'yes',
                'selection_method' => $scope_method,
                'included_categories' => $scope_categories,
                'included_products' => array(),
                'excluded_products' => array(),
                'attribute_slug' => '',
                'attribute_values' => array(),
                'attribute_excluded_values' => array(),
                'discount_mode' => $discount_target === 'all' ? 'global' : 'quantity',
                'apply_to' => 'cheapest',
                'discount_rules' => $tiers,
                'min_cart_total' => $min_total,
                'min_quantity_trigger' => $min_qty > 1 ? $min_qty : '',
                'allowed_user_roles' => $roles,
                'coupon_required' => $coupon_code ? 'yes' : 'no',
                'required_coupon_codes' => $coupon_code ? array($coupon_lower) : array(),
                'notice_enabled' => 'yes',
                'notice_title' => '🎉 ' . $name,
                'notice_color' => '#7C3AED',
            );

            MMD_Discount_Rules::save_rule_set($rule_set);
            $result['discount_rule_id'] = $rule_id;
        }

        // ---- Gift rule --------------------------------------------------------
        if ($gift) {
            $gift_rule_id = 'flow_gift_' . sanitize_title($name) . '_' . wp_rand(100, 999);
            $gift_rule = array(
                'id' => $gift_rule_id,
                'name' => $name . ( ' ' . __( '(gift)', 'incentiv' ) ),
                'enabled' => 'yes',
                'gift_mode' => $gift['mode'],
                'selection_method' => $scope_method,
                'included_categories' => $scope_categories,
                'gift_source' => 'specific',
                'gift_products' => $gift['products'],
                'gift_categories' => array(),
                'gift_type' => 'free',
                'gift_discount' => 0,
                'gift_products_limit' => $gift['limit'],
                'show_gift_selection' => $gift['mode'] === 'product_page' ? 'yes' : 'no',
                'min_cart_total' => $min_total,
                'min_quantity_trigger' => $min_qty > 1 ? $min_qty : '',
                'allowed_user_roles' => $roles,
                'coupon_required' => $coupon_code ? 'yes' : 'no',
                'required_coupon_codes' => $coupon_code ? array($coupon_lower) : array(),
                'cart_gift_notice' => __( 'You have a gift! Choose a product from the list 🎁', 'incentiv' ),
            );

            if (class_exists('MMD_Gift_Rules')) {
                MMD_Gift_Rules::get_instance()->save_rule_set($gift_rule);
                $result['gift_rule_id'] = $gift_rule_id;
            }
        }

        // ---- Coupon (only when the flow includes a coupon trigger) -----------
        if ($coupon_code && class_exists('WC_Coupon')) {
            $existing_id = function_exists('wc_get_coupon_id_by_code') ? wc_get_coupon_id_by_code($coupon_code) : 0;
            $coupon = $existing_id ? new WC_Coupon($existing_id) : new WC_Coupon();
            if (method_exists($coupon, 'set_code')) {
                $coupon->set_code($coupon_lower);
            }
            if (method_exists($coupon, 'set_description')) {
                $coupon->set_description($name . ' (Incentiv flow)');
            }
            if (method_exists($coupon, 'set_discount_type')) {
                $coupon->set_discount_type('percent');
                $coupon->set_amount(0);
            }
            $coupon_id = $coupon->save();

            if ($coupon_id) {
                update_post_meta($coupon_id, '_mmd_managed_by_plugin', 'yes');
                if ($result['discount_rule_id']) {
                    update_post_meta($coupon_id, '_mmd_linked_rule_id', $result['discount_rule_id']);
                }
                if ($result['gift_rule_id']) {
                    update_post_meta($coupon_id, '_mmd_linked_gift_rule_id', $result['gift_rule_id']);
                }
                $result['coupon_id'] = $coupon_id;
                $result['auto_apply_url'] = home_url('/?mmd_coupon=' . rawurlencode($coupon_lower));
            }
        }

        $result['edit_rule_url'] = $result['discount_rule_id']
            ? admin_url('admin.php?page=mix-match-discounts&action=edit&rule_set_id=' . urlencode($result['discount_rule_id']))
            : '';
        $result['edit_gift_url'] = $result['gift_rule_id']
            ? admin_url('admin.php?page=mmd-gift-rules&action=edit&rule_id=' . urlencode($result['gift_rule_id']))
            : '';

        wp_send_json_success($result);
    }

    /**
     * Map every existing mechanic to a flow (array of nodes) for visualization.
     * Node: [icon, kind(quantity|gift|event|coupon|neutral|interaction), title, sub]
     */
    public static function get_existing_flows() {
        $flows = array();

        $method_labels = array(
            'all' => __( 'All products', 'incentiv' ),
            'category' => __( 'Selected categories', 'incentiv' ),
            'product_ids' => __( 'Selected products', 'incentiv' ),
            'attribute' => __( 'By attribute', 'incentiv' ),
        );

        // Discount rules
        foreach ((array) MMD_Discount_Rules::get_all_rule_sets() as $rs) {
            $nodes = array();
            $method = isset($rs['selection_method']) ? $rs['selection_method'] : 'all';
            $tiers = isset($rs['discount_rules']) ? $rs['discount_rules'] : array();
            usort($tiers, function ($a, $b) {
                return intval($a['quantity']) - intval($b['quantity']);
            });

            $first_qty = $tiers ? intval($tiers[0]['quantity']) : 1;
            $nodes[] = array('shopping_cart', 'quantity', ( __( 'Customer adds', 'incentiv' ) . ' ' ) . $first_qty . __( '+ items', 'incentiv' ), $method_labels[$method] ?? $method);

            if (!empty($rs['min_cart_total'])) {
                $nodes[] = array('payments', 'neutral', ( __( 'Cart over', 'incentiv' ) . ' ' ) . wp_strip_all_tags(wc_price($rs['min_cart_total'])), __( 'Threshold', 'incentiv' ));
            }
            if (!empty($rs['coupon_required']) && $rs['coupon_required'] === 'yes' && !empty($rs['required_coupon_codes'])) {
                $nodes[] = array('confirmation_number', 'coupon', ( __( 'Enters code', 'incentiv' ) . ' ' ) . strtoupper(reset($rs['required_coupon_codes'])), __( 'Customer interaction', 'incentiv' ));
            }

            foreach ($tiers as $tier) {
                $disc = floatval($tier['discount']);
                $is_free = (!isset($tier['type']) || $tier['type'] === 'percentage') && $disc >= 100;
                $label = $is_free
                    ? __( 'Cheapest is free! 🎁', 'incentiv' )
                    : ((!isset($tier['type']) || $tier['type'] === 'percentage') ? $disc . __( '% off', 'incentiv' ) : wp_strip_all_tags(wc_price($disc)) . ( ' ' . __( 'Discount', 'incentiv' ) ));
                $nodes[] = array($is_free ? 'redeem' : 'percent', $is_free ? 'gift' : 'success', intval($tier['quantity']) . '+ ' . ( is_rtl() ? '←' : '→' ) . ' ' . $label, isset($rs['discount_mode']) && $rs['discount_mode'] === 'global' ? __( 'on all items', 'incentiv' ) : __( 'on the cheapest item', 'incentiv' ));
            }

            $flows[] = array(
                'kind' => 'quantity',
                'name' => $rs['name'],
                'enabled' => isset($rs['enabled']) && $rs['enabled'] === 'yes',
                'edit_url' => admin_url('admin.php?page=mix-match-discounts&action=edit&rule_set_id=' . urlencode($rs['id'])),
                'nodes' => $nodes,
            );
        }

        // Gift rules
        if (class_exists('MMD_Gift_Rules')) {
            foreach ((array) MMD_Gift_Rules::get_instance()->get_rule_sets() as $gr) {
                $nodes = array();
                if (!empty($gr['min_cart_total'])) {
                    $nodes[] = array('payments', 'neutral', ( __( 'Cart over', 'incentiv' ) . ' ' ) . wp_strip_all_tags(wc_price($gr['min_cart_total'])), __( 'Threshold', 'incentiv' ));
                } else {
                    $nodes[] = array('shopping_cart', 'quantity', __( 'Customer buys a participating product', 'incentiv' ), $method_labels[$gr['selection_method'] ?? 'all'] ?? '');
                }
                if (!empty($gr['coupon_required']) && $gr['coupon_required'] === 'yes' && !empty($gr['required_coupon_codes'])) {
                    $nodes[] = array('confirmation_number', 'coupon', ( __( 'Enters code', 'incentiv' ) . ' ' ) . strtoupper(reset($gr['required_coupon_codes'])), __( 'Customer interaction', 'incentiv' ));
                }
                $gift_count = !empty($gr['gift_products']) ? count($gr['gift_products']) : 0;
                if (($gr['gift_mode'] ?? 'product_page') === 'cart_based') {
                    $nodes[] = array('card_giftcard', 'gift', __( 'Gift added automatically 🎁', 'incentiv' ), $gift_count ? $gift_count . ( ' ' . _x( 'Gift products', 'flow', 'incentiv' ) ) : __( 'By categories', 'incentiv' ));
                } else {
                    $nodes[] = array('touch_app', 'interaction', __( 'Customer chooses a gift', 'incentiv' ), __( 'Customer interaction', 'incentiv' ));
                    $nodes[] = array('redeem', 'gift', __( 'The gift is added for free 🎁', 'incentiv' ), $gift_count ? $gift_count . ( ' ' . __( 'Products to choose from', 'incentiv' ) ) : '');
                }

                $flows[] = array(
                    'kind' => 'gift',
                    'name' => $gr['name'],
                    'enabled' => isset($gr['enabled']) && $gr['enabled'] === 'yes',
                    'edit_url' => admin_url('admin.php?page=mmd-gift-rules&action=edit&rule_id=' . urlencode($gr['id'])),
                    'nodes' => $nodes,
                );
            }
        }

        // Add-on journeys (for example special events) through `mmd_flow_builder_flows`.
        return apply_filters('mmd_flow_builder_flows', $flows);
    }
}

MMD_Flow_Builder::instance();
