<?php
/**
 * Module registry and extension points.
 *
 * Incentiv is complete on its own. Add-ons (for example Incentiv Pro) plug
 * extra modules in through the hooks below instead of the core loading them:
 *
 *  - `incv_register_modules` (action, INCV_Modules $registry): call
 *    $registry->register( 'id', array( 'label' => ..., 'boot' => callable ) ).
 *    Each module's `boot` callback runs once, right after the core is loaded.
 *  - `incv_loaded` (action): the core and every registered module are loaded.
 *  - `incv_admin_tabs` (filter, array $tabs): tabs in the plugin's admin header.
 *  - `incv_admin_screens` (filter, array $page_slugs): admin pages that use the
 *    plugin's admin chrome (body class, footer credit, scripts).
 *  - `incv_promotions_blocked` (filter, bool $blocked, string $context): return
 *    true to give the current shopper no Incentiv promotions.
 *  - `incv_rule_set_matches_audience` (filter, bool $matches, array $rule_set,
 *    WC_Cart|null $cart): audience/segment check for a discount rule.
 *  - `incv_audience_rule_keys` (filter, array $keys): rule fields that hold
 *    audience conditions set by an add-on.
 *  - `incv_sanitize_rule_set` (filter, array $sanitized, array $input,
 *    array|null $existing): add-on fields saved with a discount rule.
 *  - `incv_rule_editor_cards` (action, array $rule_set): extra cards in the
 *    discount rule editor.
 *  - `incv_dashboard_sections` (action, int $period, array $stats): extra
 *    sections at the end of the dashboard.
 *
 * The core never deletes data that an add-on stored: fields of a discount
 * rule that the core does not know are kept when the rule is saved.
 */

if (!defined('ABSPATH')) {
    exit;
}

class INCV_Modules {

    private static $instance = null;

    /** @var array<string,array> */
    private $modules = array();

    private $booted = false;

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {}

    /**
     * Register a module. Call this from the `incv_register_modules` action.
     *
     * @param string $id   Unique module id, for example 'special_events'.
     * @param array  $args label (string), boot (callable), version (string).
     * @return bool
     */
    public function register($id, $args = array()) {
        $id = sanitize_key($id);
        if ($id === '' || isset($this->modules[$id])) {
            return false;
        }
        $this->modules[$id] = wp_parse_args($args, array(
            'label'   => $id,
            'boot'    => null,
            'version' => '',
        ));
        return true;
    }

    /**
     * Fire the registration action and boot every registered module.
     */
    public function boot() {
        if ($this->booted) {
            return;
        }
        $this->booted = true;

        do_action('incv_register_modules', $this);

        foreach ($this->modules as $id => $module) {
            if (is_callable($module['boot'])) {
                call_user_func($module['boot'], $id);
            }
        }

        do_action('incv_loaded');
    }

    public static function is_active($id) {
        return isset(self::instance()->modules[sanitize_key($id)]);
    }

    public static function get_all() {
        return self::instance()->modules;
    }

    /**
     * Admin header tabs. Add-ons add theirs through `incv_admin_tabs`.
     * Each tab: slug, label, icon (Material Symbols name), match (page slugs),
     * position (sort order, lower comes first).
     */
    public static function admin_tabs() {
        $tabs = array(
            array('slug' => 'incv-dashboard', 'label' => __( 'Dashboard', 'incentiv' ), 'icon' => 'insights', 'match' => array('incv-dashboard'), 'position' => 10),
            array('slug' => 'incv-discount-rules', 'label' => __( 'Discount rules', 'incentiv' ), 'icon' => 'sell', 'match' => array('incv-discount-rules'), 'position' => 20),
            array('slug' => 'incv-flows', 'label' => __( 'Flows', 'incentiv' ), 'icon' => 'account_tree', 'match' => array('incv-flows'), 'position' => 40),
            array('slug' => 'incv-gift-rules', 'label' => __( 'Gifts', 'incentiv' ), 'icon' => 'redeem', 'match' => array('incv-gift-rules', 'incv-gift-styling'), 'position' => 50),
            array('slug' => 'incv-documentation', 'label' => __( 'Documentation', 'incentiv' ), 'icon' => 'menu_book', 'match' => array('incv-documentation', 'incv-github-sync'), 'position' => 80),
            array('slug' => 'incv-storefront', 'label' => __( 'Customer view', 'incentiv' ), 'icon' => 'smartphone', 'match' => array('incv-storefront'), 'position' => 100),
        );

        $tabs = apply_filters('incv_admin_tabs', $tabs);

        $clean = array();
        foreach ((array) $tabs as $i => $tab) {
            if (empty($tab['slug']) || empty($tab['label'])) {
                continue;
            }
            $tab['icon']     = isset($tab['icon']) ? $tab['icon'] : 'extension';
            $tab['match']    = isset($tab['match']) ? (array) $tab['match'] : array($tab['slug']);
            $tab['position'] = isset($tab['position']) ? (int) $tab['position'] : 90;
            $tab['_order']   = $i;
            $clean[] = $tab;
        }
        usort($clean, function ($a, $b) {
            return ($a['position'] <=> $b['position']) ?: ($a['_order'] <=> $b['_order']);
        });
        return $clean;
    }

    /**
     * Admin page slugs that belong to the plugin.
     */
    public static function admin_screens() {
        $screens = array(
            'incv-discount-rules', 'incv-dashboard', 'incv-gift-rules', 'incv-gift-styling',
            'incv-documentation', 'incv-github-sync', 'incv-storefront', 'incv-flows',
        );
        return array_values(array_unique((array) apply_filters('incv_admin_screens', $screens)));
    }

    /**
     * Whether the current shopper must get no promotions (for example a block
     * list kept by an add-on). The core keeps no block list, so: false.
     *
     * @param string $context Where it is asked: 'discounts', 'gifts', 'gift_picker', ...
     */
    public static function promotions_blocked($context = 'discounts') {
        return (bool) apply_filters('incv_promotions_blocked', false, $context);
    }

    /**
     * Rule fields that hold audience conditions (stored by an add-on).
     */
    public static function audience_rule_keys() {
        $keys = array(
            'segment_customer_type', 'segment_min_orders', 'segment_max_orders',
            'segment_min_items', 'segment_max_items', 'segment_min_ltv', 'segment_max_ltv',
            'segment_countries', 'segment_languages', 'segment_cart_categories',
            'segment_utm_source', 'segment_utm_medium', 'segment_utm_campaign', 'segment_referrer',
        );
        return (array) apply_filters('incv_audience_rule_keys', $keys);
    }

    /**
     * Whether a rule carries audience conditions.
     */
    public static function rule_has_audience_conditions($rule_set) {
        foreach (self::audience_rule_keys() as $key) {
            if (!isset($rule_set[$key])) {
                continue;
            }
            $value = $rule_set[$key];
            if ($key === 'segment_customer_type' && ($value === 'any' || $value === '')) {
                continue;
            }
            if ($value === '' || $value === null || $value === array() || $value === 0 || $value === '0') {
                continue;
            }
            return true;
        }
        return false;
    }

    /**
     * Audience check for a discount rule.
     *
     * Rules without audience conditions always match. A rule that was given
     * audience conditions by an add-on that is no longer active does not
     * apply (fail closed), so a "new customers only" offer never turns into an
     * offer for everyone. Its data is kept, and it applies again as soon as
     * the add-on that understands it is back.
     */
    public static function rule_matches_audience($rule_set, $cart = null) {
        $default = !self::rule_has_audience_conditions($rule_set);
        return (bool) apply_filters('incv_rule_set_matches_audience', $default, $rule_set, $cart);
    }

    /**
     * Keep the fields of an existing rule that the core sanitizer did not
     * produce (fields stored by an add-on), then let add-ons sanitize theirs.
     *
     * @param array      $sanitized Core-sanitized rule.
     * @param array      $input     Raw request data.
     * @return array
     */
    public static function finalize_rule_set($sanitized, $input) {
        $existing = null;
        if (!empty($sanitized['id']) && class_exists('INCV_Discount_Rules')) {
            $existing = INCV_Discount_Rules::get_rule_set($sanitized['id']);
        }
        if (is_array($existing)) {
            foreach ($existing as $key => $value) {
                if (!array_key_exists($key, $sanitized)) {
                    $sanitized[$key] = $value;
                }
            }
        }
        return (array) apply_filters('incv_sanitize_rule_set', $sanitized, $input, $existing);
    }
}
