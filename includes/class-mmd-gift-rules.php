<?php
/**
 * Central Gift Rules Management
 *
 * Manages global gift rules that can be applied to specific products or categories
 * Similar to discount rules but for gift product management
 */

if (!defined('ABSPATH')) {
    exit;
}

class MMD_Gift_Rules {

    private static $instance = null;
    private $option_name = 'mmd_gift_rule_sets';
    private $styling_option_name = 'mmd_gift_styling';

    private static function debug_log($message) {
        MMD_Helpers::log($message);
    }

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Admin hooks
        add_action('admin_menu', array($this, 'add_admin_menu'), 12);
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));

        // AJAX handlers
        add_action('wp_ajax_mmd_save_gift_rule', array($this, 'ajax_save_gift_rule'));
        add_action('wp_ajax_mmd_delete_gift_rule', array($this, 'ajax_delete_gift_rule'));
        add_action('wp_ajax_mmd_duplicate_gift_rule', array($this, 'ajax_duplicate_gift_rule'));
        add_action('wp_ajax_mmd_save_gift_styling', array($this, 'ajax_save_gift_styling'));
        add_action('wp_ajax_mmd_get_shipping_methods', array($this, 'ajax_get_shipping_methods'));
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'mix-match-discounts',
            __( 'Gift management', 'incentiv' ),
            __( 'Gift management', 'incentiv' ),
            'manage_options',
            'mmd-gift-rules',
            array($this, 'render_admin_page')
        );

        add_submenu_page(
            'mix-match-discounts',
            __( 'Gift design', 'incentiv' ),
            __( 'Gift design', 'incentiv' ),
            'manage_options',
            'mmd-gift-styling',
            array($this, 'render_styling_page')
        );
    }

    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'mmd-gift-rules') === false && strpos($hook, 'mmd-gift-styling') === false) {
            return;
        }

        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');
        wp_enqueue_style('select2', MMD_PLUGIN_URL . 'assets/vendor/select2/select2.min.css', array(), '4.1.0');
        wp_enqueue_script('select2', MMD_PLUGIN_URL . 'assets/vendor/select2/select2.min.js', array('jquery'), '4.1.0', true);

        wp_enqueue_style('mmd-admin-style', plugins_url('assets/admin-style.css', dirname(__FILE__)), array(), MMD_VERSION);
        wp_enqueue_script('mmd-admin-script', plugins_url('assets/admin-script.js', dirname(__FILE__)), array('jquery', 'select2', 'wp-color-picker', 'wp-i18n'), '1.0.0', true);
        wp_set_script_translations('mmd-admin-script', 'incentiv', MMD_PLUGIN_DIR . 'languages');

        wp_localize_script('mmd-admin-script', 'mmdGiftRulesAjax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mmd_gift_rules_nonce')
        ));
    }

    /**
     * Get all gift rule sets
     */
    public function get_rule_sets() {
        $rule_sets = get_option($this->option_name, array());
        if (!is_array($rule_sets)) {
            $rule_sets = array();
        }
        return $rule_sets;
    }

    /**
     * Get a single rule set by ID
     */
    public function get_rule_set($id) {
        $rule_sets = $this->get_rule_sets();
        foreach ($rule_sets as $rule_set) {
            if ($rule_set['id'] === $id) {
                return $rule_set;
            }
        }
        return null;
    }

    /**
     * Save a rule set
     */
    public function save_rule_set($rule_set) {
        $rule_sets = $this->get_rule_sets();

        // Generate ID if new
        if (empty($rule_set['id'])) {
            $rule_set['id'] = 'gift_rule_' . time() . '_' . wp_rand(1000, 9999);
        }

        // Find and update or add new
        $found = false;
        foreach ($rule_sets as $key => $existing) {
            if ($existing['id'] === $rule_set['id']) {
                $rule_sets[$key] = $rule_set;
                $found = true;
                break;
            }
        }

        if (!$found) {
            $rule_sets[] = $rule_set;
        }

        // update_option returns false if value hasn't changed, so we verify by reading back
        update_option($this->option_name, $rule_sets);

        // Verify the save by checking if the rule exists
        $saved_rules = get_option($this->option_name, array());
        foreach ($saved_rules as $saved_rule) {
            if ($saved_rule['id'] === $rule_set['id']) {
                return $rule_set; // Return the saved rule set with ID
            }
        }

        return false;
    }

    /**
     * Delete a rule set
     */
    public function delete_rule_set($id) {
        $rule_sets = $this->get_rule_sets();
        $rule_sets = array_filter($rule_sets, function($rule_set) use ($id) {
            return $rule_set['id'] !== $id;
        });
        return update_option($this->option_name, array_values($rule_sets));
    }

    /**
     * Get styling options
     */
    public function get_styling_options() {
        $defaults = array(
            // Gift box styling (v1.9 design palette)
            'gift_box_bg_color_1' => '#7c3aed',
            'gift_box_bg_color_2' => '#a855f7',
            'gift_box_text_color' => '#ffffff',
            'gift_box_title_size' => '20',
            'gift_box_subtitle_size' => '14',
            'gift_box_border_radius' => '14',
            'gift_box_padding' => '20',
            'gift_box_margin' => '20',
            'gift_box_border_width' => '0',
            'gift_box_border_color' => '#ffffff',
            'gift_box_shadow' => 'large',
            'gift_button_bg_color' => '#ffffff',
            'gift_button_text_color' => '#7c3aed',
            'gift_button_hover_bg' => '#7c3aed',
            'gift_button_hover_text' => '#ffffff',
            // Gift box text content
            'gift_box_title_text' => __( '🎁 Choose your gift!', 'incentiv' ),
            'gift_box_subtitle_text' => __( 'Buy this product and get {gift_type} on {selection_type}:', 'incentiv' ),
            'gift_badge_text' => __( '🎁 Gift', 'incentiv' ),
            // Cart notes styling
            'cart_note_bg_color' => '#f8f9fa',
            'cart_note_text_color' => '#333333',
            'cart_note_label_size' => '16',
            'cart_note_input_size' => '14',
            'cart_note_border_color' => '#dee2e6',
            'cart_note_border_width' => '1',
            'cart_note_border_radius' => '4',
            'cart_note_padding' => '10',
            'cart_note_placeholder_color' => '#999999',
            'cart_note_focus_border' => '#7c3aed',
            // Gift display location
            'gift_display_hook' => 'after_add_to_cart',
            // Debug mode
            'debug_mode' => 'no',
        );

        $options = get_option($this->styling_option_name, array());
        return wp_parse_args($options, $defaults);
    }

    /**
     * Save styling options
     */
    public function save_styling_options($options) {
        // Merge into existing styling so partial saves (e.g. a quick colour
        // swatch) never wipe unrelated settings.
        $existing = get_option($this->styling_option_name, array());
        if (!is_array($existing)) {
            $existing = array();
        }
        $merged = array_merge($existing, (array) $options);
        update_option($this->styling_option_name, $merged);
        return true; // update_option returns false when unchanged — treat persisted state as success
    }

    /**
     * Check if a product matches any gift rule
     *
     * @param int $product_id Product ID to check
     * @return array|null Matching rule set or null
     */
    public function get_matching_rule_for_product($product_id) {
        $rule_sets = $this->get_rule_sets();

        foreach ($rule_sets as $rule_set) {
            // Skip disabled rules
            if (isset($rule_set['enabled']) && $rule_set['enabled'] === 'no') {
                continue;
            }

            // Check if product matches this rule
            if ($this->product_matches_rule($product_id, $rule_set)) {
                return $rule_set;
            }
        }

        return null;
    }

    /**
     * Check if a product matches a specific rule
     *
     * @param int $product_id Product ID
     * @param array $rule_set Rule set to check against
     * @return bool True if matches
     */
    private function product_matches_rule($product_id, $rule_set) {
        $selection_method = isset($rule_set['selection_method']) ? $rule_set['selection_method'] : 'all';

        // Check if product is explicitly excluded
        $excluded_products = isset($rule_set['excluded_products']) ? $rule_set['excluded_products'] : array();
        if (in_array($product_id, $excluded_products)) {
            return false;
        }

        switch ($selection_method) {
            case 'all':
                return true;

            case 'product_ids':
                $included_products = isset($rule_set['included_products']) ? $rule_set['included_products'] : array();
                return in_array($product_id, $included_products);

            case 'category':
                $product = wc_get_product($product_id);
                if (!$product) {
                    return false;
                }

                $product_categories = $product->get_category_ids();
                $included_categories = isset($rule_set['included_categories']) ? $rule_set['included_categories'] : array();
                $excluded_categories = isset($rule_set['excluded_categories']) ? $rule_set['excluded_categories'] : array();

                // Check if in excluded category
                foreach ($excluded_categories as $excluded_cat) {
                    if (in_array($excluded_cat, $product_categories)) {
                        return false;
                    }
                }

                // Check excluded products from categories
                $excluded_from_cats = isset($rule_set['excluded_products_from_categories']) ? $rule_set['excluded_products_from_categories'] : array();
                if (in_array($product_id, $excluded_from_cats)) {
                    return false;
                }

                // Check if in included category
                foreach ($included_categories as $included_cat) {
                    if (in_array($included_cat, $product_categories)) {
                        return true;
                    }
                }
                return false;

            case 'attribute':
                $product = wc_get_product($product_id);
                if (!$product) {
                    return false;
                }

                $attribute_slug = isset($rule_set['attribute_slug']) ? $rule_set['attribute_slug'] : '';
                $attribute_values = isset($rule_set['attribute_values']) ? $rule_set['attribute_values'] : array();
                $excluded_values = isset($rule_set['attribute_excluded_values']) ? $rule_set['attribute_excluded_values'] : array();

                if (empty($attribute_slug)) {
                    return false;
                }

                $product_attributes = $product->get_attributes();
                if (!isset($product_attributes[$attribute_slug])) {
                    return false;
                }

                $product_attr = $product_attributes[$attribute_slug];
                if ($product_attr->is_taxonomy()) {
                    $terms = wp_get_post_terms($product_id, $attribute_slug, array('fields' => 'ids'));

                    // Check excluded values
                    foreach ($excluded_values as $excluded_val) {
                        if (in_array($excluded_val, $terms)) {
                            return false;
                        }
                    }

                    // Check included values
                    foreach ($attribute_values as $value) {
                        if (in_array($value, $terms)) {
                            return true;
                        }
                    }
                }

                return false;
        }

        return false;
    }

    /**
     * AJAX: Save gift rule
     */
    public function ajax_save_gift_rule() {
        check_ajax_referer('mmd_gift_rules_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
            return;
        }

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON; the decoded rule is sanitized field by field in save_rule_set().
        $rule_set = isset($_POST['rule_set']) ? json_decode(wp_unslash($_POST['rule_set']), true) : array();
        $is_draft = !empty($_POST['is_draft']);

        if (empty($rule_set) || !is_array($rule_set)) {
            self::debug_log('MMD Gift Rules: Empty rule set received');
            wp_send_json_error('Invalid rule set - empty data');
            return;
        }

        if ($is_draft) {
            // A draft is never live and may still be a work in progress.
            $rule_set['status']  = 'draft';
            $rule_set['enabled'] = 'no';
            if (empty($rule_set['name'])) {
                $rule_set['name'] = __('Untitled draft', 'incentiv');
            }
        } else {
            $rule_set['status'] = 'active'; // publishing clears any draft flag
            if (empty($rule_set['name'])) {
                self::debug_log('MMD Gift Rules: Rule name is empty');
                wp_send_json_error('Invalid rule set - missing name');
                return;
            }
        }

        self::debug_log('MMD Gift Rules: Attempting to save rule: ' . wp_json_encode($rule_set));

        $result = $this->save_rule_set($rule_set);

        if ($result) {
            self::debug_log('MMD Gift Rules: Rule saved successfully with ID: ' . $result['id']);
            wp_send_json_success(array('message' => 'Rule saved successfully', 'rule_set' => $result));
        } else {
            self::debug_log('MMD Gift Rules: Failed to save rule');
            wp_send_json_error('Failed to save rule - database error');
        }
    }

    /**
     * AJAX: Delete gift rule
     */
    public function ajax_delete_gift_rule() {
        check_ajax_referer('mmd_gift_rules_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
            return;
        }

        $rule_id = isset($_POST['rule_id']) ? sanitize_text_field(wp_unslash($_POST['rule_id'])) : '';

        if (empty($rule_id)) {
            wp_send_json_error('Invalid rule ID');
            return;
        }

        $result = $this->delete_rule_set($rule_id);

        if ($result) {
            wp_send_json_success('Rule deleted successfully');
        } else {
            wp_send_json_error('Failed to delete rule');
        }
    }

    /**
     * AJAX: Duplicate gift rule
     */
    public function ajax_duplicate_gift_rule() {
        check_ajax_referer('mmd_gift_rules_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
            return;
        }

        $rule_id = isset($_POST['rule_id']) ? sanitize_text_field(wp_unslash($_POST['rule_id'])) : '';

        if (empty($rule_id)) {
            wp_send_json_error('Invalid rule ID');
            return;
        }

        $rule_set = $this->get_rule_set($rule_id);
        if (!$rule_set) {
            wp_send_json_error('Rule not found');
            return;
        }

        // Create duplicate with new ID and name
        $rule_set['id'] = '';
        $rule_set['name'] = $rule_set['name'] . ( ' ' . __( '(copy)', 'incentiv' ) );

        $result = $this->save_rule_set($rule_set);

        if ($result) {
            wp_send_json_success(array('message' => 'Rule duplicated successfully', 'rule_set' => $rule_set));
        } else {
            wp_send_json_error('Failed to duplicate rule');
        }
    }

    /**
     * AJAX: Save styling options
     */
    public function ajax_save_gift_styling() {
        check_ajax_referer('mmd_gift_rules_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
            return;
        }

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON; every value is sanitized below before it is saved.
        $styling = isset($_POST['styling']) ? json_decode(wp_unslash($_POST['styling']), true) : array();

        if (empty($styling) || !is_array($styling)) {
            wp_send_json_error('Invalid styling data');
            return;
        }

        // Sanitize every scalar value before persisting.
        $clean = array();
        foreach ($styling as $key => $value) {
            $key = sanitize_key($key);
            $clean[$key] = is_scalar($value) ? sanitize_text_field((string) $value) : '';
        }

        $result = $this->save_styling_options($clean);

        if ($result) {
            wp_send_json_success('Styling saved successfully');
        } else {
            wp_send_json_error('Failed to save styling');
        }
    }

    /**
     * AJAX: Get available shipping methods
     */
    public function ajax_get_shipping_methods() {
        check_ajax_referer('mmd_gift_rules_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
            return;
        }

        $methods = array();

        // Get all shipping zones and their methods
        $zones = WC_Shipping_Zones::get_zones();

        foreach ($zones as $zone_data) {
            $zone = new WC_Shipping_Zone($zone_data['id']);
            $zone_methods = $zone->get_shipping_methods(true);

            foreach ($zone_methods as $method) {
                $methods[] = array(
                    'id' => $method->get_rate_id(),
                    'title' => $zone_data['zone_name'] . ' - ' . $method->get_title(),
                    'method_id' => $method->id,
                    'instance_id' => $method->get_instance_id(),
                );
            }
        }

        // Also get the "Rest of the World" zone (zone 0)
        $default_zone = new WC_Shipping_Zone(0);
        $default_methods = $default_zone->get_shipping_methods(true);

        foreach ($default_methods as $method) {
            $methods[] = array(
                'id' => $method->get_rate_id(),
                'title' => ( __( 'Rest of the world -', 'incentiv' ) . ' ' ) . $method->get_title(),
                'method_id' => $method->id,
                'instance_id' => $method->get_instance_id(),
            );
        }

        wp_send_json_success($methods);
    }

    /**
     * Render admin page
     */
    public function render_admin_page() {
        // Read-only routing between admin views; saving and deleting go through nonce-checked AJAX handlers.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $action = isset($_GET['action']) ? sanitize_text_field(wp_unslash($_GET['action'])) : 'list';
        $rule_id = isset($_GET['rule_id']) ? sanitize_text_field(wp_unslash($_GET['rule_id'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only routing.

        if ($action === 'edit' && !empty($rule_id)) {
            $this->render_edit_page($rule_id);
        } elseif ($action === 'new') {
            $this->render_edit_page('');
        } elseif ($action === 'styling') {
            $this->render_styling_page();
        } else {
            $this->render_list_page();
        }
    }

    /**
     * Render list page
     */
    private function render_list_page() {
        $rule_sets = $this->get_rule_sets();
        include dirname(__FILE__) . '/../templates/admin-gift-rules-list.php';
    }

    /**
     * Render edit page
     */
    private function render_edit_page($rule_id) {
        $rule_set = null;
        if (!empty($rule_id)) {
            $rule_set = $this->get_rule_set($rule_id);
        }

        // Default values for new rule
        if (!$rule_set) {
            $rule_set = array(
                'id' => '',
                'name' => '',
                'enabled' => 'yes',
                'selection_method' => 'all',
                'included_categories' => array(),
                'excluded_categories' => array(),
                'included_products' => array(),
                'excluded_products' => array(),
                'excluded_products_from_categories' => array(),
                'attribute_slug' => '',
                'attribute_values' => array(),
                'attribute_excluded_values' => array(),
                'gift_type' => 'free',
                'gift_discount' => '0',
                'gift_mode' => 'product_page', // 'product_page' or 'cart_based'
                'cart_gift_notice' => __( 'You have a gift! Add a product from the participating categories to get a discount', 'incentiv' ), // Notice text for cart-based gifts
                'show_gift_selection' => 'yes', // Show gift selection modal on product page
                'gift_limit_type' => 'per_cart', // 'per_cart', 'per_product', 'unlimited'
                'gift_source' => 'specific', // 'specific' or 'category'
                'gift_products' => array(),
                'gift_categories' => array(), // Categories to pull gift products from
                'gift_excluded_categories' => array(), // Categories to exclude from gifts
                'gift_excluded_tags' => array(), // Tags to exclude from gifts
                'gift_excluded_products' => array(), // Specific products to exclude from gifts
                'gift_attribute_slug' => '', // Attribute to filter gift products by
                'gift_attribute_values' => array(), // Specific attribute values to include in gifts
                'gift_selection_type' => 'single',
                'override_product_settings' => 'no',
                'allow_quantity' => 'no',
                'max_quantity' => '5',
                'total_quantity_limit' => '0', // 0 = no limit, otherwise enforce exact total
                'gift_default_variations' => array(), // Array of product_id => variation_id
                'gift_products_limit' => '0', // 0 = no limit, otherwise max products to display for selection
                'promo_notice_enabled' => 'no',
                'promo_notice_text' => '',
                'promo_notice_pages' => 'all',
                'promo_notice_link' => '',
                'promo_notice_link_text' => __( 'View products', 'incentiv' ),
                'combined_discount_enabled' => 'no',
                'combined_discount_rules' => array(),
                'combined_discount_mode' => 'quantity',
                'combined_discount_apply_to' => 'cheapest',
                'combined_discount_notice_enabled' => 'yes',
                'combined_discount_notice_title' => __( '💰 Buy in quantity and save!', 'incentiv' ),
                'combined_discount_notice_color' => '#4CAF50',
            );
        }

        include dirname(__FILE__) . '/../templates/admin-gift-rule-edit.php';
    }

    /**
     * Render styling page
     */
    public function render_styling_page() {
        $styling = $this->get_styling_options();
        include dirname(__FILE__) . '/../templates/admin-gift-styling.php';
    }
}

// Initialize
MMD_Gift_Rules::get_instance();
