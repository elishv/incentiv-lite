<?php
/**
 * Admin Panel
 */

if (!defined('ABSPATH')) {
    exit;
}

class MMD_Admin {

    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_menu', array($this, 'add_storefront_menu'), 29);
        add_action('admin_menu', array($this, 'add_documentation_menu'), 30);
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_init', array($this, 'handle_rule_set_actions'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_filter('admin_body_class', array($this, 'add_admin_body_class'));
        add_filter('admin_footer_text', array($this, 'admin_footer_credit'));
        add_action('wp_ajax_mmd_import_example_rule', array($this, 'ajax_import_example_rule'));
        add_action('wp_ajax_mmd_preview_products_search', array($this, 'ajax_preview_products_search'));
        add_action('wp_ajax_mmd_toggle_rule', array($this, 'ajax_toggle_rule'));
        add_action('wp_ajax_mmd_save_rule_draft', array($this, 'ajax_save_rule_draft'));
        add_action('wp_ajax_mmd_quick_create_rule', array($this, 'ajax_quick_create_rule'));
    }

    /**
     * Whether the current admin screen belongs to the plugin.
     */
    public static function is_plugin_screen($hook = '') {
        if (!$hook && isset($_GET['page'])) {
            $hook = sanitize_text_field($_GET['page']);
        }
        if (!$hook) {
            return false;
        }
        $needles = MMD_Modules::admin_screens();
        foreach ($needles as $needle) {
            if (strpos($hook, $needle) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Flag plugin pages on the body element so the redesign chrome
     * (page background, notices) can target them.
     */
    public function add_admin_body_class($classes) {
        if (self::is_plugin_screen()) {
            $classes .= ' mmd-admin-body';
        }
        return $classes;
    }

    /**
     * Brand footer credit on plugin screens.
     */
    public function admin_footer_credit($text) {
        if (self::is_plugin_screen()) {
            return ( '<span style="font-family:Assistant,sans-serif;">' . esc_html__( 'Powered by', 'incentiv' ) . ' <strong style="font-family:Sora,Assistant,sans-serif;">Incentiv<span style="color:#F5C451;">.</span></strong> ' . esc_html__( '· Every reason to buy more ·', 'incentiv' ) . ' <a href="https://a-in.co.il/plugins/incentiv/" target="_blank" rel="noopener">a-in.co.il</a></span>' );
        }
        return $text;
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        // Incentiv spark mark as menu icon (grayscale, per WP admin conventions)
        $menu_icon = 'data:image/svg+xml;base64,' . base64_encode(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><path d="M47 8 C50.8 32 62.5 43.7 86 47 C62.5 50.3 50.8 62 47 86 C43.2 62 31.5 50.3 8 47 C31.5 43.7 43.2 32 47 8 Z" fill="#a7aaad"/><circle cx="72" cy="72" r="14" fill="#a7aaad"/><path d="M72 65 v14 M65 72 h14" stroke="#1d2327" stroke-width="4.6" stroke-linecap="round"/></svg>'
        );

        add_menu_page(
            /* translators: Plugin brand name, used as the admin page title. Keep it as is unless the brand has a localized name. */
            _x( 'Incentiv', 'admin menu label (brand name)', 'incentiv' ),
            /* translators: Plugin brand name in the admin menu. Keep it as is unless the brand has a localized name. */
            _x( 'Incentiv', 'admin menu label (brand name)', 'incentiv' ),
            'manage_woocommerce',
            'mix-match-discounts',
            array($this, 'render_admin_page'),
            $menu_icon,
            56
        );

        // Rename the auto-created first submenu to be clear
        add_submenu_page(
            'mix-match-discounts',
            __( 'Quantity discounts', 'incentiv' ),
            __( 'Quantity discounts', 'incentiv' ),
            'manage_woocommerce',
            'mix-match-discounts',
            array($this, 'render_admin_page')
        );

        // Analytics dashboard
        add_submenu_page(
            'mix-match-discounts',
            __( 'Dashboard', 'incentiv' ),
            __( 'Dashboard', 'incentiv' ),
            'manage_woocommerce',
            'mmd-dashboard',
            array($this, 'render_dashboard_page')
        );
    }

    /**
     * Storefront showcase page (registered late so it sits near the end)
     */
    public function add_storefront_menu() {
        add_submenu_page(
            'mix-match-discounts',
            __( 'Customer view', 'incentiv' ),
            __( 'Customer view', 'incentiv' ),
            'manage_woocommerce',
            'mmd-storefront',
            array($this, 'render_storefront_page')
        );
    }

    /**
     * Add documentation menu (at priority 30 so it appears last)
     */
    public function add_documentation_menu() {
        add_submenu_page(
            'mix-match-discounts',
            __( 'Documentation', 'incentiv' ),
            __( 'Documentation', 'incentiv' ),
            'manage_woocommerce',
            'mmd-documentation',
            array($this, 'render_documentation_page')
        );
    }

    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('mmd_settings_group', 'mmd_settings', array($this, 'sanitize_settings'));
    }

    /**
     * Sanitize settings
     */
    public function sanitize_settings($input) {
        $sanitized = array();

        // Basic settings
        $sanitized['enabled'] = isset($input['enabled']) ? 'yes' : 'no';
        $sanitized['selection_method'] = sanitize_text_field($input['selection_method']);
        $sanitized['apply_to'] = sanitize_text_field($input['apply_to']);
        $sanitized['discount_mode'] = isset($input['discount_mode']) ? sanitize_text_field($input['discount_mode']) : 'quantity';

        // Categories
        $sanitized['included_categories'] = isset($input['included_categories']) && is_array($input['included_categories'])
            ? array_map('sanitize_text_field', $input['included_categories'])
            : array();
        $sanitized['excluded_categories'] = isset($input['excluded_categories']) && is_array($input['excluded_categories'])
            ? array_map('sanitize_text_field', $input['excluded_categories'])
            : array();

        // Products
        $sanitized['included_products'] = array();
        if (!empty($input['included_products'])) {
            $products = explode(',', $input['included_products']);
            $sanitized['included_products'] = array_map('intval', array_filter($products));
        }

        $sanitized['excluded_products'] = array();
        if (!empty($input['excluded_products'])) {
            $products = explode(',', $input['excluded_products']);
            $sanitized['excluded_products'] = array_map('intval', array_filter($products));
        }

        // Attributes
        $sanitized['attribute_slug'] = sanitize_text_field($input['attribute_slug']);
        $sanitized['attribute_values'] = array();
        if (!empty($input['attribute_values'])) {
            $values = explode(',', $input['attribute_values']);
            $sanitized['attribute_values'] = array_map('sanitize_text_field', array_filter($values));
        }

        $sanitized['attribute_excluded_values'] = array();
        if (!empty($input['attribute_excluded_values'])) {
            $values = explode(',', $input['attribute_excluded_values']);
            $sanitized['attribute_excluded_values'] = array_map('sanitize_text_field', array_filter($values));
        }

        // Discount rules
        $sanitized['discount_rules'] = array();
        if (isset($input['discount_rules']) && is_array($input['discount_rules'])) {
            foreach ($input['discount_rules'] as $rule) {
                if (!empty($rule['quantity']) && isset($rule['discount'])) {
                    $sanitized['discount_rules'][] = array(
                        'quantity' => intval($rule['quantity']),
                        'discount' => floatval($rule['discount']),
                        'type' => sanitize_text_field($rule['type'])
                    );
                }
            }
        }

        // Notice settings
        $sanitized['notice_enabled'] = isset($input['notice_enabled']) ? 'yes' : 'no';
        $sanitized['notice_title'] = sanitize_text_field($input['notice_title']);
        $sanitized['notice_content'] = wp_kses_post($input['notice_content']);
        $sanitized['notice_color'] = sanitize_hex_color($input['notice_color']);

        // Display settings
        $sanitized['discount_label'] = sanitize_text_field($input['discount_label']);
        $sanitized['show_in_mini_cart'] = isset($input['show_in_mini_cart']) ? 'yes' : 'no';

        // Debug
        $sanitized['debug_mode'] = isset($input['debug_mode']) ? 'yes' : 'no';

        return $sanitized;
    }

    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        // Check if we're on ANY of the plugin's admin pages
        if (!self::is_plugin_screen($hook)) {
            return;
        }

        // Admin design system — fonts are self-hosted via @font-face in the CSS
        wp_enqueue_style('mmd-admin-style', MMD_PLUGIN_URL . 'assets/admin-style.css', array(), MMD_VERSION);

        // Shared light UI behaviors (header search, PJAX nav, FAQ accordion, chips)
        wp_enqueue_script('mmd-admin-ui', MMD_PLUGIN_URL . 'assets/admin-ui.js', array('jquery', 'wp-i18n'), MMD_VERSION, true);
        wp_set_script_translations('mmd-admin-ui', 'incentiv', MMD_PLUGIN_DIR . 'languages');
        wp_localize_script('mmd-admin-ui', 'mmdUi', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mmd-admin-nonce'),
        ));

        // Editor autosave / draft recovery (rule, gift, event editors)
        wp_enqueue_script('mmd-admin-autosave', MMD_PLUGIN_URL . 'assets/admin-autosave.js', array('jquery', 'wp-i18n'), MMD_VERSION, true);
        wp_set_script_translations('mmd-admin-autosave', 'incentiv', MMD_PLUGIN_DIR . 'languages');

        // Enqueue the full asset set on every plugin page so PJAX navigation
        // between screens always has its dependencies loaded.
        // Select2 is bundled locally (no CDN dependency).
        wp_enqueue_script('select2', MMD_PLUGIN_URL . 'assets/vendor/select2/select2.min.js', array('jquery'), '4.0.13', true);
        wp_enqueue_style('select2', MMD_PLUGIN_URL . 'assets/vendor/select2/select2.min.css', array(), '4.0.13');

        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');

        // Media library for banner image uploader
        wp_enqueue_media();

        wp_enqueue_script('mmd-admin-script', MMD_PLUGIN_URL . 'assets/admin-script.js', array('jquery', 'wp-color-picker', 'wp-i18n'), MMD_VERSION, true);
        wp_set_script_translations('mmd-admin-script', 'incentiv', MMD_PLUGIN_DIR . 'languages');

        wp_localize_script('mmd-admin-script', 'mmdAdmin', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mmd-admin-nonce'),
        ));
    }

    /**
     * Handle rule set actions
     */
    public function handle_rule_set_actions() {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }

        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : '';
        $rule_set_id = isset($_GET['rule_set_id']) ? sanitize_text_field($_GET['rule_set_id']) : '';

        // Handle save (publish)
        if (isset($_POST['mmd_save_rule_set']) && check_admin_referer('mmd_save_rule_set')) {
            $rule_set_data = $this->sanitize_rule_set($_POST);
            $rule_set_data['status'] = 'active'; // publishing clears any draft flag
            MMD_Discount_Rules::save_rule_set($rule_set_data);

            wp_redirect(add_query_arg(array(
                'page' => 'mix-match-discounts',
                'message' => 'saved'
            ), admin_url('admin.php')));
            exit;
        }

        // Handle delete
        if ($action === 'delete' && !empty($rule_set_id) && check_admin_referer('mmd_delete_rule')) {
            MMD_Discount_Rules::delete_rule_set($rule_set_id);

            wp_redirect(add_query_arg(array(
                'page' => 'mix-match-discounts',
                'message' => 'deleted'
            ), admin_url('admin.php')));
            exit;
        }

        // Handle duplicate
        if ($action === 'duplicate' && !empty($rule_set_id) && check_admin_referer('mmd_duplicate_rule')) {
            MMD_Discount_Rules::duplicate_rule_set($rule_set_id);

            wp_redirect(add_query_arg(array(
                'page' => 'mix-match-discounts',
                'message' => 'duplicated'
            ), admin_url('admin.php')));
            exit;
        }
    }

    /**
     * AJAX: save a discount rule as a draft (never applied on the storefront).
     * Reuses the same sanitizer as the real save, but forces the rule off and
     * marks it as a draft. Returns the id so the editor can keep updating the
     * same draft instead of creating duplicates.
     */
    public function ajax_save_rule_draft() {
        check_ajax_referer('mmd-admin-nonce', 'nonce');
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => 'forbidden'), 403);
        }

        $data = $this->sanitize_rule_set($_POST);
        $data['enabled'] = 'no';       // a draft is never live
        $data['status']  = 'draft';

        if (empty($data['id'])) {
            $data['id'] = 'rule_' . time() . '_' . wp_rand(1000, 9999);
        }

        MMD_Discount_Rules::save_rule_set($data);
        wp_send_json_success(array('id' => $data['id']));
    }

    /**
     * The quick-create presets: a handful of everyday promotions that only
     * need one or two numbers, so an easy rule never requires the full editor.
     *
     * Each preset declares the inputs it needs; build_quick_rule() turns the
     * submitted values into a complete, valid rule set.
     */
    public static function get_quick_presets() {
        return array(
            'bulk_percent' => array(
                'icon'  => 'stairs',
                'title' => __( 'Buy a quantity, get a percentage off', 'incentiv' ),
                'desc'  => __( 'From a set quantity, the cheapest item gets a percentage off.', 'incentiv' ),
                'fields' => array(
                    'qty'      => array('label' => __( 'From quantity', 'incentiv' ), 'type' => 'number', 'default' => 3, 'min' => 1),
                    'discount' => array('label' => __( 'Discount percentage', 'incentiv' ), 'type' => 'number', 'default' => 25, 'min' => 1, 'max' => 100, 'suffix' => '%'),
                ),
            ),
            'cheapest_free' => array(
                'icon'  => 'redeem',
                'title' => __( 'Cheapest is free', 'incentiv' ),
                'desc'  => __( 'When customers buy the quantity you set, the cheapest item is free.', 'incentiv' ),
                'fields' => array(
                    'qty' => array('label' => __( 'From quantity', 'incentiv' ), 'type' => 'number', 'default' => 3, 'min' => 2),
                ),
            ),
            'storewide' => array(
                'icon'  => 'percent',
                'title' => __( 'Store-wide discount', 'incentiv' ),
                'desc'  => __( 'A percentage off all participating products, regardless of quantity.', 'incentiv' ),
                'fields' => array(
                    'discount' => array('label' => __( 'Discount percentage', 'incentiv' ), 'type' => 'number', 'default' => 10, 'min' => 1, 'max' => 100, 'suffix' => '%'),
                ),
            ),
            'spend_save' => array(
                'icon'  => 'payments',
                'title' => __( 'Over an amount: discount', 'incentiv' ),
                'desc'  => __( 'When the cart total passes a threshold you set, a percentage discount is applied.', 'incentiv' ),
                'fields' => array(
                    'min_total' => array('label' => __( 'Minimum cart total', 'incentiv' ), 'type' => 'number', 'default' => 200, 'min' => 1, 'suffix' => '₪'),
                    'discount'  => array('label' => __( 'Discount percentage', 'incentiv' ), 'type' => 'number', 'default' => 15, 'min' => 1, 'max' => 100, 'suffix' => '%'),
                ),
            ),
        );
    }

    /**
     * Turn a quick preset + its numbers into a complete rule set.
     */
    private function build_quick_rule($preset, $values, $name) {
        $qty       = isset($values['qty']) ? max(1, intval($values['qty'])) : 3;
        $discount  = isset($values['discount']) ? floatval($values['discount']) : 10;
        $min_total = isset($values['min_total']) ? floatval($values['min_total']) : 0;

        $rule = array(
            'id' => 'quick_' . time() . '_' . wp_rand(100, 999),
            'name' => $name,
            'enabled' => 'yes',
            'status' => 'active',
            // Applies to the whole catalogue by default — the point of the quick
            // path is that it works immediately; narrowing is done by editing.
            'selection_method' => 'all',
            'included_categories' => array(),
            'excluded_categories' => array(),
            'included_products' => array(),
            'excluded_products' => array(),
            'excluded_products_from_categories' => array(),
            'limit_to_variations' => 'no',
            'included_variations_from_categories' => array(),
            'attribute_slug' => '',
            'attribute_values' => array(),
            'attribute_excluded_values' => array(),
            'apply_to' => 'cheapest',
            'discount_mode' => 'quantity',
            'discount_rules' => array(),
            'min_cart_total' => '',
            'max_cart_total' => '',
            'coupon_required' => 'no',
            'required_coupon_codes' => array(),
            'override_other_discounts' => 'no',
            'allowed_user_roles' => array(),
            'schedule_start' => '',
            'schedule_end' => '',
            'usage_limit_total' => '',
            'usage_limit_per_user' => '',
            'notice_enabled' => 'yes',
            'notice_title' => $name,
            'notice_color' => '#7C3AED',
            'discount_allocation' => 'proportional',
        );

        switch ($preset) {
            case 'cheapest_free':
                $rule['discount_rules'] = array(
                    array('quantity' => $qty, 'discount' => 100, 'type' => 'percentage'),
                );
                break;

            case 'storewide':
                $rule['discount_mode'] = 'global';
                $rule['discount_rules'] = array(
                    array('quantity' => 1, 'discount' => $discount, 'type' => 'percentage'),
                );
                break;

            case 'spend_save':
                $rule['discount_mode'] = 'global';
                $rule['min_cart_total'] = $min_total;
                $rule['discount_rules'] = array(
                    array('quantity' => 1, 'discount' => $discount, 'type' => 'percentage'),
                );
                break;

            case 'bulk_percent':
            default:
                $rule['discount_rules'] = array(
                    array('quantity' => $qty, 'discount' => $discount, 'type' => 'percentage'),
                );
                break;
        }

        return $rule;
    }

    /**
     * AJAX: create a working rule from a quick preset in one step.
     */
    public function ajax_quick_create_rule() {
        check_ajax_referer('mmd-admin-nonce', 'nonce');
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => 'forbidden'), 403);
        }

        $preset = isset($_POST['preset']) ? sanitize_text_field(wp_unslash($_POST['preset'])) : '';
        $presets = self::get_quick_presets();
        if (!isset($presets[$preset])) {
            wp_send_json_error(array('message' => __( 'Unknown template', 'incentiv' )));
        }

        $name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
        if ($name === '') {
            $name = $presets[$preset]['title'];
        }

        $values = array();
        foreach (array_keys($presets[$preset]['fields']) as $field) {
            if (isset($_POST[$field]) && $_POST[$field] !== '') {
                $values[$field] = floatval(wp_unslash($_POST[$field]));
            }
        }

        $rule = $this->build_quick_rule($preset, $values, $name);

        // Reject a preset that produced nothing to discount rather than saving
        // a rule that would silently never fire.
        if (empty($rule['discount_rules'])) {
            wp_send_json_error(array('message' => __( 'No discount set', 'incentiv' )));
        }

        MMD_Discount_Rules::save_rule_set($rule);

        wp_send_json_success(array(
            'id' => $rule['id'],
            'name' => $rule['name'],
            'edit_url' => admin_url('admin.php?page=mix-match-discounts&action=edit&rule_set_id=' . urlencode($rule['id'])),
        ));
    }

    /**
     * AJAX handler for the rule preview product search.
     * Returns a list of products (id, name, price) for the preview modal.
     */
    public function ajax_preview_products_search() {
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('forbidden', 403);
        }

        $args = array(
            'post_type' => array('product', 'product_variation'),
            'post_status' => 'publish',
            'posts_per_page' => 200,
            'orderby' => 'title',
            'order' => 'ASC',
        );

        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        if ($search) {
            $args['s'] = $search;
        }

        $query = new WP_Query($args);
        $products = array();

        foreach ($query->posts as $post) {
            $product = wc_get_product($post->ID);
            if (!$product) continue;
            if ($product->is_type('variable')) continue; // Skip variable parents — variations are listed instead

            $regular = $product->get_regular_price();
            if (!$regular || floatval($regular) <= 0) {
                $regular = $product->get_price();
            }
            if (!$regular || floatval($regular) <= 0) continue;

            $name = $product->get_name();
            if ($product->is_type('variation')) {
                $parent = wc_get_product($product->get_parent_id());
                if ($parent) $name = $parent->get_name() . ' — ' . wc_get_formatted_variation($product, true);
            }

            $products[] = array(
                'id' => $product->get_id(),
                'name' => wp_strip_all_tags($name),
                'regular_price' => floatval($regular),
                'formatted_price' => wp_strip_all_tags(wc_price($regular)),
            );
        }

        wp_send_json_success($products);
    }

    /**
     * AJAX: toggle a discount rule enabled/disabled (play/pause).
     */
    public function ajax_toggle_rule() {
        check_ajax_referer('mmd-admin-nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => 'forbidden'), 403);
        }

        $rule_id = isset($_POST['rule_id']) ? sanitize_text_field($_POST['rule_id']) : '';
        if (!$rule_id) {
            wp_send_json_error(array('message' => 'missing rule id'));
        }

        $rule_set = MMD_Discount_Rules::get_rule_set($rule_id);
        if (!$rule_set) {
            wp_send_json_error(array('message' => 'rule not found'));
        }

        $new_state = (isset($rule_set['enabled']) && $rule_set['enabled'] === 'yes') ? 'no' : 'yes';
        $rule_set['enabled'] = $new_state;
        MMD_Discount_Rules::save_rule_set($rule_set);

        wp_send_json_success(array('enabled' => $new_state));
    }

    /**
     * AJAX handler for importing example rules
     */
    public function ajax_import_example_rule() {
        check_ajax_referer('mmd_import_example', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        $example_key = isset($_POST['example']) ? sanitize_text_field($_POST['example']) : '';

        if (empty($example_key)) {
            wp_send_json_error(array('message' => 'No example specified'));
        }

        $result = MMD_Example_Rules::import_example($example_key);

        if ($result) {
            wp_send_json_success(array('message' => __( 'Rule imported successfully', 'incentiv' )));
        } else {
            wp_send_json_error(array('message' => __( 'Failed to import rule', 'incentiv' )));
        }
    }

    /**
     * Sanitize rule set data
     */
    private function sanitize_rule_set($input) {
        $sanitized = array();

        $sanitized['id'] = isset($input['rule_set_id']) ? sanitize_text_field($input['rule_set_id']) : '';
        $sanitized['name'] = isset($input['rule_name']) ? sanitize_text_field($input['rule_name']) : '';
        $sanitized['cart_label'] = isset($input['cart_label']) ? sanitize_text_field($input['cart_label']) : '';
        $sanitized['enabled'] = isset($input['enabled']) ? 'yes' : 'no';
        $sanitized['selection_method'] = sanitize_text_field($input['selection_method']);
        $sanitized['apply_to'] = sanitize_text_field($input['apply_to']);
        $sanitized['discount_mode'] = isset($input['discount_mode']) ? sanitize_text_field($input['discount_mode']) : 'quantity';

        // Categories
        $sanitized['included_categories'] = isset($input['included_categories']) && is_array($input['included_categories'])
            ? array_map('sanitize_text_field', $input['included_categories'])
            : array();
        $sanitized['excluded_categories'] = isset($input['excluded_categories']) && is_array($input['excluded_categories'])
            ? array_map('sanitize_text_field', $input['excluded_categories'])
            : array();

        // Products (handle both array from Select2 and comma-separated string for backwards compatibility)
        $sanitized['included_products'] = array();
        if (!empty($input['included_products'])) {
            if (is_array($input['included_products'])) {
                $sanitized['included_products'] = array_map('intval', array_filter($input['included_products']));
            } else {
                $products = explode(',', $input['included_products']);
                $sanitized['included_products'] = array_map('intval', array_filter($products));
            }
        }

        $sanitized['excluded_products'] = array();
        if (!empty($input['excluded_products'])) {
            if (is_array($input['excluded_products'])) {
                $sanitized['excluded_products'] = array_map('intval', array_filter($input['excluded_products']));
            } else {
                $products = explode(',', $input['excluded_products']);
                $sanitized['excluded_products'] = array_map('intval', array_filter($products));
            }
        }

        $sanitized['excluded_products_from_categories'] = array();
        if (!empty($input['excluded_products_from_categories'])) {
            if (is_array($input['excluded_products_from_categories'])) {
                $sanitized['excluded_products_from_categories'] = array_map('intval', array_filter($input['excluded_products_from_categories']));
            } else {
                $products = explode(',', $input['excluded_products_from_categories']);
                $sanitized['excluded_products_from_categories'] = array_map('intval', array_filter($products));
            }
        }

        // Limit to specific variations from categories
        $sanitized['limit_to_variations'] = isset($input['limit_to_variations']) ? 'yes' : 'no';

        $sanitized['included_variations_from_categories'] = array();
        if (!empty($input['included_variations_from_categories'])) {
            if (is_array($input['included_variations_from_categories'])) {
                $sanitized['included_variations_from_categories'] = array_map('intval', array_filter($input['included_variations_from_categories']));
            } else {
                $products = explode(',', $input['included_variations_from_categories']);
                $sanitized['included_variations_from_categories'] = array_map('intval', array_filter($products));
            }
        }

        // Attributes
        $sanitized['attribute_slug'] = sanitize_text_field($input['attribute_slug']);
        $sanitized['attribute_values'] = array();
        if (!empty($input['attribute_values'])) {
            $values = explode(',', $input['attribute_values']);
            $sanitized['attribute_values'] = array_map('sanitize_text_field', array_filter($values));
        }

        $sanitized['attribute_excluded_values'] = array();
        if (!empty($input['attribute_excluded_values'])) {
            $values = explode(',', $input['attribute_excluded_values']);
            $sanitized['attribute_excluded_values'] = array_map('sanitize_text_field', array_filter($values));
        }

        // Discount rules
        $sanitized['discount_rules'] = array();
        $is_global_mode = isset($input['discount_mode']) && $input['discount_mode'] === 'global';
        if (isset($input['discount_rules']) && is_array($input['discount_rules'])) {
            foreach ($input['discount_rules'] as $rule) {
                $has_discount = isset($rule['discount']) && $rule['discount'] !== '';
                $has_quantity = !empty($rule['quantity']);
                // In global mode, quantity defaults to 1 if not set
                if ($has_discount && ($has_quantity || $is_global_mode)) {
                    $sanitized['discount_rules'][] = array(
                        'quantity' => $has_quantity ? intval($rule['quantity']) : 1,
                        'discount' => floatval($rule['discount']),
                        'type' => sanitize_text_field($rule['type'])
                    );
                }
            }
        }

        // GLOBAL gift exclusion list (plugin-wide). These products will
        // never be eligible as a gift, regardless of which rule matched.
        $sanitized['global_gift_excluded_products'] = isset($input['global_gift_excluded_products']) && is_array($input['global_gift_excluded_products'])
            ? array_values(array_filter(array_map('intval', $input['global_gift_excluded_products'])))
            : array();

        // Coupon settings
        $sanitized['coupon_required'] = isset($input['coupon_required']) ? 'yes' : 'no';
        $sanitized['required_coupon_codes'] = array();
        if (!empty($input['required_coupon_codes'])) {
            if (is_array($input['required_coupon_codes'])) {
                $sanitized['required_coupon_codes'] = array_map('sanitize_text_field', array_filter($input['required_coupon_codes']));
            } else {
                $codes = explode(',', $input['required_coupon_codes']);
                $sanitized['required_coupon_codes'] = array_map('sanitize_text_field', array_map('trim', array_filter($codes)));
            }
        }
        $sanitized['override_other_discounts'] = isset($input['override_other_discounts']) ? 'yes' : 'no';

        // Notice settings
        $sanitized['notice_enabled'] = isset($input['notice_enabled']) ? 'yes' : 'no';
        $sanitized['notice_title'] = isset($input['notice_title']) ? sanitize_text_field($input['notice_title']) : '';
        $sanitized['notice_content'] = isset($input['notice_content']) ? wp_kses_post($input['notice_content']) : '';
        $sanitized['notice_color'] = isset($input['notice_color']) ? sanitize_hex_color($input['notice_color']) : '#4CAF50';
        $sanitized['show_in_mini_cart'] = isset($input['show_in_mini_cart']) ? 'yes' : 'no';

        // Custom messages
        $sanitized['cart_message'] = isset($input['cart_message']) ? wp_kses_post($input['cart_message']) : '';
        $sanitized['checkout_message'] = isset($input['checkout_message']) ? wp_kses_post($input['checkout_message']) : '';

        // Shipping method condition
        $sanitized['shipping_method_condition'] = isset($input['shipping_method_condition']) ? sanitize_text_field($input['shipping_method_condition']) : '';

        // Cart total conditions
        $sanitized['min_cart_total'] = isset($input['min_cart_total']) && $input['min_cart_total'] !== '' ? floatval($input['min_cart_total']) : '';
        $sanitized['max_cart_total'] = isset($input['max_cart_total']) && $input['max_cart_total'] !== '' ? floatval($input['max_cart_total']) : '';
        $sanitized['min_quantity_trigger'] = isset($input['min_quantity_trigger']) && $input['min_quantity_trigger'] !== '' ? intval($input['min_quantity_trigger']) : '';

        // User role conditions
        $sanitized['allowed_user_roles'] = isset($input['allowed_user_roles']) && is_array($input['allowed_user_roles'])
            ? array_map('sanitize_text_field', $input['allowed_user_roles'])
            : array();

        // Schedule with timezone
        $sanitized['schedule_start'] = isset($input['schedule_start']) ? sanitize_text_field($input['schedule_start']) : '';
        $sanitized['schedule_end'] = isset($input['schedule_end']) ? sanitize_text_field($input['schedule_end']) : '';
        $sanitized['schedule_timezone'] = isset($input['schedule_timezone']) ? sanitize_text_field($input['schedule_timezone']) : wp_timezone_string();

        // Usage limits
        $sanitized['usage_limit_total'] = isset($input['usage_limit_total']) && $input['usage_limit_total'] !== '' ? intval($input['usage_limit_total']) : '';
        $sanitized['usage_limit_per_user'] = isset($input['usage_limit_per_user']) && $input['usage_limit_per_user'] !== '' ? intval($input['usage_limit_per_user']) : '';

        // Audience/segment fields are an add-on's (filter `mmd_sanitize_rule_set`).
        // Stored values are kept as they are by MMD_Modules::finalize_rule_set().

        // ---- Discount allocation ---------------------------------------
        $allocation = isset($input['discount_allocation']) ? sanitize_text_field($input['discount_allocation']) : '';
        $sanitized['discount_allocation'] = (class_exists('MMD_Discount_Allocator') && MMD_Discount_Allocator::is_valid_mode($allocation))
            ? $allocation
            : MMD_Discount_Allocator::DEFAULT_MODE;

        // ---- Margin guards & limits ------------------------------------
        foreach (array('max_discount_amount', 'max_discount_percent', 'budget_cap', 'margin_floor_percent') as $key) {
            $sanitized[$key] = isset($input[$key]) && $input[$key] !== '' ? floatval($input[$key]) : '';
        }

        // Rule exclusion by other rules
        $sanitized['excluded_by_rules'] = isset($input['excluded_by_rules']) && is_array($input['excluded_by_rules'])
            ? array_map('sanitize_text_field', $input['excluded_by_rules'])
            : array();

        // Discount badge settings
        $sanitized['badge_enabled'] = isset($input['badge_enabled']) ? 'yes' : 'no';
        $sanitized['badge_text'] = isset($input['badge_text']) ? sanitize_text_field($input['badge_text']) : __( 'Promotion', 'incentiv' );
        $sanitized['badge_bg_color'] = isset($input['badge_bg_color']) ? sanitize_hex_color($input['badge_bg_color']) : '#e53935';
        $sanitized['badge_text_color'] = isset($input['badge_text_color']) ? sanitize_hex_color($input['badge_text_color']) : '#ffffff';
        $sanitized['badge_show_on_archive'] = isset($input['badge_show_on_archive']) ? 'yes' : 'no';
        $sanitized['badge_show_on_product'] = isset($input['badge_show_on_product']) ? 'yes' : 'no';

        // Sale price display settings
        $sanitized['show_sale_price'] = isset($input['show_sale_price']) ? 'yes' : 'no';
        $sanitized['show_sale_price_on'] = isset($input['show_sale_price_on']) ? sanitize_text_field($input['show_sale_price_on']) : 'both';
        $sanitized['sale_price_tier'] = isset($input['sale_price_tier']) ? sanitize_text_field($input['sale_price_tier']) : 'first';

        // Cart display settings - controls whether discounted prices show in cart/mini-cart/checkout
        $sanitized['show_discount_in_cart'] = isset($input['show_discount_in_cart']) ? 'yes' : 'no';

        // Banner image settings
        $sanitized['banner_image_id'] = isset($input['banner_image_id']) ? intval($input['banner_image_id']) : 0;
        $sanitized['banner_image_url'] = isset($input['banner_image_url']) ? esc_url_raw($input['banner_image_url']) : '';
        $sanitized['banner_link_url'] = isset($input['banner_link_url']) ? esc_url_raw($input['banner_link_url']) : '';

        // Global notice settings
        $sanitized['global_notice_enabled'] = isset($input['global_notice_enabled']) ? 'yes' : 'no';
        $sanitized['global_notice_text'] = isset($input['global_notice_text']) ? sanitize_text_field($input['global_notice_text']) : '';
        $sanitized['global_notice_bg_color'] = isset($input['global_notice_bg_color']) ? sanitize_hex_color($input['global_notice_bg_color']) : '#2196F3';
        $sanitized['global_notice_text_color'] = isset($input['global_notice_text_color']) ? sanitize_hex_color($input['global_notice_text_color']) : '#ffffff';
        $sanitized['global_notice_position'] = isset($input['global_notice_position']) ? sanitize_text_field($input['global_notice_position']) : 'top';
        $sanitized['global_notice_layout'] = isset($input['global_notice_layout']) ? sanitize_text_field($input['global_notice_layout']) : 'bar';
        $sanitized['global_notice_dismissible'] = isset($input['global_notice_dismissible']) ? 'yes' : 'no';
        $sanitized['global_notice_dismiss_limit'] = isset($input['global_notice_dismiss_limit']) ? max(0, intval($input['global_notice_dismiss_limit'])) : 0;
        $sanitized['global_notice_link_url'] = isset($input['global_notice_link_url']) ? esc_url_raw($input['global_notice_link_url']) : '';
        $sanitized['global_notice_link_text'] = isset($input['global_notice_link_text']) ? sanitize_text_field($input['global_notice_link_text']) : '';
        $sanitized['global_notice_pages'] = isset($input['global_notice_pages']) ? sanitize_text_field($input['global_notice_pages']) : 'all';
        $sanitized['global_notice_icon'] = isset($input['global_notice_icon']) ? sanitize_text_field($input['global_notice_icon']) : '';
        $sanitized['global_notice_rotate_speed'] = isset($input['global_notice_rotate_speed']) ? max(2, min(30, intval($input['global_notice_rotate_speed']))) : 5;
        $sanitized['global_notice_marquee_direction'] = isset($input['global_notice_marquee_direction']) ? sanitize_text_field($input['global_notice_marquee_direction']) : 'rtl';
        $sanitized['global_notice_mobile_position'] = isset($input['global_notice_mobile_position']) ? sanitize_text_field($input['global_notice_mobile_position']) : 'same';

        // Keep fields stored by add-ons, then let active add-ons sanitize theirs.
        return MMD_Modules::finalize_rule_set($sanitized, $input);
    }

    /**
     * Render admin page
     */
    public function render_admin_page() {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }

        // Show messages
        if (isset($_GET['message'])) {
            $message = sanitize_text_field($_GET['message']);
            $messages = array(
                'saved' => __( 'The discount rule was saved.', 'incentiv' ),
                'deleted' => __( 'The discount rule was deleted.', 'incentiv' ),
                'duplicated' => __( 'The discount rule was duplicated.', 'incentiv' ),
                'imported' => __( 'The example rule was imported.', 'incentiv' ),
            );
            if (isset($messages[$message])) {
                echo '<div class="notice notice-success is-dismissible"><p><strong>' . esc_html($messages[$message]) . '</strong></p></div>';
            }
        }

        include MMD_PLUGIN_DIR . 'templates/admin-rule-sets.php';
    }

    /**
     * Render documentation page
     */
    public function render_documentation_page() {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }

        include MMD_PLUGIN_DIR . 'templates/admin-documentation.php';
    }

    /**
     * Render analytics dashboard page
     */
    public function render_dashboard_page() {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }

        include MMD_PLUGIN_DIR . 'templates/admin-dashboard.php';
    }

    /**
     * Render storefront showcase page
     */
    public function render_storefront_page() {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }

        include MMD_PLUGIN_DIR . 'templates/admin-storefront.php';
    }
}
