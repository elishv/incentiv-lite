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

class INCV_Gift_Rules {

    private static $instance = null;
    private $option_name = 'incv_gift_rule_sets';
    private $styling_option_name = 'incv_gift_styling';

    private static function debug_log($message) {
        INCV_Helpers::log($message);
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
        add_action('wp_ajax_incv_save_gift_rule', array($this, 'ajax_save_gift_rule'));
        add_action('wp_ajax_incv_delete_gift_rule', array($this, 'ajax_delete_gift_rule'));
        add_action('wp_ajax_incv_duplicate_gift_rule', array($this, 'ajax_duplicate_gift_rule'));
        add_action('wp_ajax_incv_save_gift_styling', array($this, 'ajax_save_gift_styling'));
        add_action('wp_ajax_incv_get_shipping_methods', array($this, 'ajax_get_shipping_methods'));
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'incv-discount-rules',
            __( 'Gift management', 'incentiv' ),
            __( 'Gift management', 'incentiv' ),
            'manage_options',
            'incv-gift-rules',
            array($this, 'render_admin_page')
        );

        add_submenu_page(
            'incv-discount-rules',
            __( 'Gift design', 'incentiv' ),
            __( 'Gift design', 'incentiv' ),
            'manage_options',
            'incv-gift-styling',
            array($this, 'render_styling_page')
        );
    }

    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'incv-gift-rules') === false && strpos($hook, 'incv-gift-styling') === false) {
            return;
        }

        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');
        wp_enqueue_style('select2', INCV_PLUGIN_URL . 'assets/vendor/select2/select2.min.css', array(), '4.0.13');
        wp_enqueue_script('select2', INCV_PLUGIN_URL . 'assets/vendor/select2/select2.min.js', array('jquery'), '4.0.13', true);

        wp_enqueue_style('incv-admin-style', plugins_url('assets/admin-style.css', dirname(__FILE__)), array(), INCV_VERSION);
        wp_enqueue_script('incv-admin-script', plugins_url('assets/admin-script.js', dirname(__FILE__)), array('jquery', 'select2', 'wp-color-picker', 'wp-i18n'), '1.0.0', true);
        wp_set_script_translations('incv-admin-script', 'incentiv', INCV_PLUGIN_DIR . 'languages');

        wp_localize_script('incv-admin-script', 'incvGiftRulesAjax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('incv_gift_rules_nonce')
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
     * Validate and sanitize one gift rule set, field by field.
     *
     * Only known fields are kept. Each one is cast to its type, enums are checked
     * against an allow-list, numbers are clamped to a range and text is sanitized.
     *
     * @param mixed $input Decoded rule set.
     * @return array Clean rule set.
     */
    public static function sanitize_rule_set($input) {
        $input = is_array($input) ? $input : array();
        $out   = array();

        $text = function ($key, $default = '') use ($input) {
            return isset($input[$key]) && is_scalar($input[$key]) ? sanitize_text_field((string) $input[$key]) : $default;
        };
        $textarea = function ($key) use ($input) {
            return isset($input[$key]) && is_scalar($input[$key]) ? sanitize_textarea_field((string) $input[$key]) : '';
        };
        $yes_no = function ($key, $default) use ($input) {
            if (!isset($input[$key]) || !is_scalar($input[$key])) {
                return $default;
            }
            return in_array($input[$key], array('yes', '1', 1, true), true) ? 'yes' : 'no';
        };
        $enum = function ($key, array $allowed, $default) use ($input) {
            $value = isset($input[$key]) && is_scalar($input[$key]) ? (string) $input[$key] : '';
            return in_array($value, $allowed, true) ? $value : $default;
        };
        // Number kept as a string ('' = not set), clamped to [min, max].
        $number = function ($key, $min, $max, $default = '') use ($input) {
            if (!isset($input[$key]) || !is_scalar($input[$key]) || '' === trim((string) $input[$key])) {
                return $default;
            }
            if (!is_numeric($input[$key])) {
                return $default;
            }
            $value = max($min, min($max, (float) $input[$key]));
            return (string) (floor($value) == $value ? (int) $value : round($value, 4));
        };
        $ids = function ($key) use ($input) {
            if (!isset($input[$key]) || !is_array($input[$key])) {
                return array();
            }
            $values = array_map('intval', array_filter($input[$key], 'is_numeric'));
            return array_values(array_unique(array_filter($values, function ($v) { return $v > 0; })));
        };
        $slug = function ($value) {
            return is_scalar($value) ? substr(sanitize_title((string) $value), 0, 200) : '';
        };
        // Attribute taxonomy names (pa_color, or non-Latin names such as pa_צבע).
        $taxonomy = function ($value) {
            if (!is_scalar($value)) {
                return '';
            }
            $value = function_exists('wc_sanitize_taxonomy_name') ? wc_sanitize_taxonomy_name((string) $value) : sanitize_key((string) $value);
            return substr(sanitize_text_field($value), 0, 200);
        };

        $id = $text('id');
        $out['id']      = preg_match('/^[A-Za-z0-9_\-]{1,100}$/', $id) ? $id : '';
        $out['name']    = substr($text('name'), 0, 200);
        $out['enabled'] = $yes_no('enabled', 'yes');
        if (isset($input['status'])) {
            $out['status'] = $enum('status', array('active', 'draft'), 'active');
        }

        // Which products trigger the gift.
        $out['selection_method']                  = $enum('selection_method', array('all', 'product_ids', 'category', 'attribute'), 'all');
        $out['included_categories']               = $ids('included_categories');
        $out['excluded_categories']               = $ids('excluded_categories');
        $out['included_products']                 = $ids('included_products');
        $out['excluded_products']                 = $ids('excluded_products');
        $out['excluded_products_from_categories'] = $ids('excluded_products_from_categories');
        $out['attribute_slug']                    = isset($input['attribute_slug']) ? $taxonomy($input['attribute_slug']) : '';
        $out['attribute_values']                  = $ids('attribute_values');
        $out['attribute_excluded_values']         = $ids('attribute_excluded_values');

        // The gift itself.
        $out['gift_type']              = $enum('gift_type', array('free', 'discount', 'fixed_discount'), 'free');
        $gift_discount_max             = 'discount' === $out['gift_type'] ? 100 : 1000000;
        $out['gift_discount']          = $number('gift_discount', 0, $gift_discount_max, '0');
        $out['gift_mode']              = $enum('gift_mode', array('product_page', 'cart_based'), 'product_page');
        $out['cart_gift_notice']       = substr($text('cart_gift_notice'), 0, 500);
        $out['cart_gift_display_mode'] = $enum('cart_gift_display_mode', array('inline', 'modal'), 'inline');
        $out['show_gift_selection']    = $yes_no('show_gift_selection', 'yes');
        $out['gift_limit_type']        = $enum('gift_limit_type', array('per_cart', 'per_product', 'unlimited'), 'per_cart');
        $out['gift_source']            = $enum('gift_source', array('specific', 'category'), 'specific');
        $out['gift_products']          = $ids('gift_products');
        $out['gift_categories']        = $ids('gift_categories');
        $out['gift_excluded_categories'] = $ids('gift_excluded_categories');
        $out['gift_excluded_tags']     = $ids('gift_excluded_tags');
        $out['gift_excluded_products'] = $ids('gift_excluded_products');
        $out['gift_attribute_slug']    = isset($input['gift_attribute_slug']) ? $taxonomy($input['gift_attribute_slug']) : '';
        $out['gift_attribute_values']  = $ids('gift_attribute_values');

        $out['gift_additional_attributes'] = array();
        if (isset($input['gift_additional_attributes']) && is_array($input['gift_additional_attributes'])) {
            foreach ($input['gift_additional_attributes'] as $row) {
                if (!is_array($row) || empty($row['slug']) || !is_scalar($row['slug'])) {
                    continue;
                }
                $row_slug = $taxonomy($row['slug']);
                if ('' === $row_slug) {
                    continue;
                }
                $values = isset($row['values']) && is_array($row['values']) ? $row['values'] : array();
                $out['gift_additional_attributes'][] = array(
                    'slug'   => $row_slug,
                    'values' => array_values(array_filter(array_map('intval', array_filter($values, 'is_numeric')), function ($v) { return $v > 0; })),
                );
                if (count($out['gift_additional_attributes']) >= 20) {
                    break;
                }
            }
        }

        $out['gift_selection_type']       = $enum('gift_selection_type', array('single', 'multiple'), 'single');
        $out['override_product_settings'] = $yes_no('override_product_settings', 'no');
        $out['allow_quantity']            = $yes_no('allow_quantity', 'no');
        $out['max_quantity']              = $number('max_quantity', 1, 1000, '5');
        $out['total_quantity_limit']      = $number('total_quantity_limit', 0, 1000, '0');
        $out['gift_products_limit']       = $number('gift_products_limit', 0, 1000, '0');

        // product_id => variation_id
        $out['gift_default_variations'] = array();
        if (isset($input['gift_default_variations']) && is_array($input['gift_default_variations'])) {
            foreach ($input['gift_default_variations'] as $product_id => $variation_id) {
                $product_id   = absint($product_id);
                $variation_id = is_scalar($variation_id) ? absint($variation_id) : 0;
                if ($product_id && $variation_id) {
                    $out['gift_default_variations'][$product_id] = $variation_id;
                }
            }
        }
        if (isset($input['gift_default_attr_slug'])) {
            $out['gift_default_attr_slug'] = $taxonomy($input['gift_default_attr_slug']);
        }
        if (isset($input['gift_default_attr_value'])) {
            $out['gift_default_attr_value'] = $slug($input['gift_default_attr_value']);
        }
        if (isset($input['gift_display_location'])) {
            $out['gift_display_location'] = $enum('gift_display_location', array('both', 'cart', 'checkout', 'mini_cart'), 'both');
        }

        // Conditions.
        $out['cart_message']              = wp_kses_post(isset($input['cart_message']) && is_scalar($input['cart_message']) ? (string) $input['cart_message'] : '');
        $out['checkout_message']          = wp_kses_post(isset($input['checkout_message']) && is_scalar($input['checkout_message']) ? (string) $input['checkout_message'] : '');
        $shipping                         = $text('shipping_method_condition');
        $out['shipping_method_condition'] = preg_match('/^[A-Za-z0-9_\-:]{0,100}$/', $shipping) ? $shipping : '';
        $out['min_cart_total']            = $number('min_cart_total', 0, 100000000, '');
        $out['max_cart_total']            = $number('max_cart_total', 0, 100000000, '');
        $out['allowed_user_roles']        = array();
        if (isset($input['allowed_user_roles']) && is_array($input['allowed_user_roles'])) {
            $out['allowed_user_roles'] = array_values(array_filter(array_map('sanitize_key', array_filter($input['allowed_user_roles'], 'is_scalar'))));
        }
        foreach (array('schedule_start', 'schedule_end') as $date_key) {
            $date = $text($date_key);
            // datetime-local value: YYYY-MM-DDTHH:MM (seconds optional), or a date.
            $out[$date_key] = preg_match('/^\d{4}-\d{2}-\d{2}([T ]\d{2}:\d{2}(:\d{2})?)?$/', $date) ? $date : '';
        }
        $out['min_quantity_trigger']  = $number('min_quantity_trigger', 0, 100000, '');
        $out['coupon_required']       = $yes_no('coupon_required', 'no');
        $out['required_coupon_codes'] = array();
        if (isset($input['required_coupon_codes'])) {
            $codes = is_array($input['required_coupon_codes']) ? $input['required_coupon_codes'] : explode(',', is_scalar($input['required_coupon_codes']) ? (string) $input['required_coupon_codes'] : '');
            foreach ($codes as $code) {
                if (!is_scalar($code)) {
                    continue;
                }
                $code = function_exists('wc_format_coupon_code') ? wc_format_coupon_code(sanitize_text_field((string) $code)) : strtolower(sanitize_text_field((string) $code));
                if ('' !== $code) {
                    $out['required_coupon_codes'][] = strtolower($code);
                }
            }
            $out['required_coupon_codes'] = array_values(array_unique($out['required_coupon_codes']));
        }

        // Promo notice.
        $out['promo_notice_enabled']   = $yes_no('promo_notice_enabled', 'no');
        $out['promo_notice_text']      = substr($textarea('promo_notice_text'), 0, 1000);
        $out['promo_notice_pages']     = $enum('promo_notice_pages', array('all', 'cart_checkout', 'product_only', 'shop_only', 'shop_product'), 'all');
        $out['promo_notice_link']      = isset($input['promo_notice_link']) && is_scalar($input['promo_notice_link']) ? esc_url_raw((string) $input['promo_notice_link']) : '';
        $out['promo_notice_link_text'] = substr($text('promo_notice_link_text'), 0, 200);

        // Quantity discounts on top of the gift.
        $out['combined_discount_enabled'] = $yes_no('combined_discount_enabled', 'no');
        $out['combined_discount_rules']   = array();
        if (isset($input['combined_discount_rules']) && is_array($input['combined_discount_rules'])) {
            foreach ($input['combined_discount_rules'] as $tier) {
                if (!is_array($tier) || !isset($tier['quantity'], $tier['discount']) || !is_numeric($tier['quantity']) || !is_numeric($tier['discount'])) {
                    continue;
                }
                $type = isset($tier['type']) && 'fixed' === $tier['type'] ? 'fixed' : 'percentage';
                $out['combined_discount_rules'][] = array(
                    'quantity' => max(1, min(100000, (int) $tier['quantity'])),
                    'discount' => max(0, min('percentage' === $type ? 100 : 100000000, (float) $tier['discount'])),
                    'type'     => $type,
                );
                if (count($out['combined_discount_rules']) >= 50) {
                    break;
                }
            }
        }
        $out['combined_discount_mode']           = $enum('combined_discount_mode', array('quantity', 'global'), 'quantity');
        $out['combined_discount_apply_to']       = $enum('combined_discount_apply_to', array('cheapest', 'most_expensive', 'all'), 'cheapest');
        $out['combined_discount_notice_enabled'] = $yes_no('combined_discount_notice_enabled', 'yes');
        $out['combined_discount_notice_title']   = substr($text('combined_discount_notice_title'), 0, 200);
        $color                                   = isset($input['combined_discount_notice_color']) && is_scalar($input['combined_discount_notice_color']) ? sanitize_hex_color((string) $input['combined_discount_notice_color']) : '';
        $out['combined_discount_notice_color']   = $color ? $color : '#4CAF50';

        /**
         * Lets an add-on keep (and sanitize) its own gift rule fields.
         *
         * @param array $out   Sanitized rule set.
         * @param array $input Decoded, unsanitized input. Sanitize anything you read from it.
         */
        return (array) apply_filters('incv_sanitize_gift_rule_set', $out, $input);
    }

    /**
     * Validate and sanitize gift styling options against the styling schema.
     * Unknown keys are dropped; invalid values are left out so the stored value stays.
     *
     * @param mixed $input Decoded styling options (may be partial).
     * @return array Clean options.
     */
    public static function sanitize_styling_options($input) {
        $input = is_array($input) ? $input : array();
        $colors = array(
            'gift_box_bg_color_1', 'gift_box_bg_color_2', 'gift_box_text_color', 'gift_box_border_color',
            'gift_button_bg_color', 'gift_button_text_color', 'gift_button_hover_bg', 'gift_button_hover_text',
            'cart_note_bg_color', 'cart_note_text_color', 'cart_note_border_color', 'cart_note_placeholder_color', 'cart_note_focus_border',
        );
        // key => array(min, max) in px.
        $sizes = array(
            'gift_box_title_size'     => array(8, 100),
            'gift_box_subtitle_size'  => array(8, 100),
            'gift_box_border_radius'  => array(0, 100),
            'gift_box_padding'        => array(0, 200),
            'gift_box_margin'         => array(0, 200),
            'gift_box_border_width'   => array(0, 50),
            'cart_note_label_size'    => array(8, 100),
            'cart_note_input_size'    => array(8, 100),
            'cart_note_border_width'  => array(0, 50),
            'cart_note_border_radius' => array(0, 100),
            'cart_note_padding'       => array(0, 200),
        );
        $texts = array('gift_box_title_text', 'gift_box_subtitle_text', 'gift_badge_text');
        $enums = array(
            'gift_box_shadow'   => array('none', 'small', 'medium', 'large'),
            'gift_display_hook' => array('before_add_to_cart', 'after_add_to_cart', 'before_summary', 'after_summary', 'after_product_meta', 'before_tabs', 'elementor_widget_only'),
            'debug_mode'        => array('yes', 'no'),
        );

        $out = array();
        foreach ($input as $key => $value) {
            if (!is_string($key) || !is_scalar($value)) {
                continue;
            }
            $value = (string) $value;
            if (in_array($key, $colors, true)) {
                $color = sanitize_hex_color($value);
                if ($color) {
                    $out[$key] = $color;
                }
            } elseif (isset($sizes[$key])) {
                if (is_numeric($value)) {
                    $out[$key] = (string) max($sizes[$key][0], min($sizes[$key][1], (int) $value));
                }
            } elseif (in_array($key, $texts, true)) {
                $out[$key] = substr(sanitize_text_field($value), 0, 300);
            } elseif (isset($enums[$key])) {
                if (in_array($value, $enums[$key], true)) {
                    $out[$key] = $value;
                }
            }
        }
        return $out;
    }

    /**
     * Save a rule set
     */
    public function save_rule_set($rule_set) {
        // Every field is validated and sanitized against the rule schema before it can be stored.
        $rule_set = self::sanitize_rule_set($rule_set);

        $rule_sets = $this->get_rule_sets();

        // Generate ID if new
        if (empty($rule_set['id'])) {
            $rule_set['id'] = 'gift_rule_' . time() . '_' . wp_rand(1000, 9999);
        }

        // Find and update or add new
        $found = false;
        foreach ($rule_sets as $key => $existing) {
            if (isset($existing['id']) && $existing['id'] === $rule_set['id']) {
                // Keep stored fields this schema does not know (e.g. written by an add-on). They come from the database, not the request.
                foreach ((array) $existing as $existing_key => $existing_value) {
                    if (!array_key_exists($existing_key, $rule_set)) {
                        $rule_set[$existing_key] = $existing_value;
                    }
                }
                $rule_sets[$key] = $rule_set;
                $found = true;
                break;
            }
        }

        if (!$found) {
            $rule_sets[] = $rule_set;
        }

        // update_option returns false if value hasn't changed, so we verify by reading back.
        // $rule_set was sanitized field by field above; stored rules were sanitized when they were saved.
        update_option($this->option_name, array_values($rule_sets));

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
        $merged = array_merge($existing, self::sanitize_styling_options((array) $options));
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
        check_ajax_referer('incv_gift_rules_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
            return;
        }

        // The rule arrives as one JSON string. It is decoded and then every field is
        // validated and sanitized against the rule schema (sanitize_rule_set()) right away.
        $rule_set = array();
        if (isset($_POST['rule_set']) && is_string($_POST['rule_set'])) {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON string; json_decode() output is sanitized key by key on the next line.
            $decoded  = json_decode(wp_unslash($_POST['rule_set']), true);
            $rule_set = is_array($decoded) ? self::sanitize_rule_set($decoded) : array();
        }
        $is_draft = !empty($_POST['is_draft']);

        if (empty($rule_set) || !is_array($rule_set)) {
            self::debug_log('INCV Gift Rules: Empty rule set received');
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
                self::debug_log('INCV Gift Rules: Rule name is empty');
                wp_send_json_error('Invalid rule set - missing name');
                return;
            }
        }

        self::debug_log('INCV Gift Rules: Attempting to save rule: ' . (isset($rule_set['id']) && $rule_set['id'] !== '' ? $rule_set['id'] : '(new)'));

        $result = $this->save_rule_set($rule_set);

        if ($result) {
            self::debug_log('INCV Gift Rules: Rule saved successfully with ID: ' . $result['id']);
            wp_send_json_success(array('message' => 'Rule saved successfully', 'rule_set' => $result));
        } else {
            self::debug_log('INCV Gift Rules: Failed to save rule');
            wp_send_json_error('Failed to save rule - database error');
        }
    }

    /**
     * AJAX: Delete gift rule
     */
    public function ajax_delete_gift_rule() {
        check_ajax_referer('incv_gift_rules_nonce', 'nonce');

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
        check_ajax_referer('incv_gift_rules_nonce', 'nonce');

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
        check_ajax_referer('incv_gift_rules_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
            return;
        }

        // The styling arrives as one JSON string. It is decoded and every known key is
        // validated against the styling schema right away; unknown keys are dropped.
        $styling = array();
        if (isset($_POST['styling']) && is_string($_POST['styling'])) {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON string; json_decode() output is sanitized key by key on the next line.
            $decoded = json_decode(wp_unslash($_POST['styling']), true);
            $styling = is_array($decoded) ? self::sanitize_styling_options($decoded) : array();
        }

        if (empty($styling)) {
            wp_send_json_error('Invalid styling data');
            return;
        }

        $result = $this->save_styling_options($styling);

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
        check_ajax_referer('incv_gift_rules_nonce', 'nonce');

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
INCV_Gift_Rules::get_instance();
