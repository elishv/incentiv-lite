<?php
/**
 * Discount Rules Manager
 * Manages multiple discount rule sets
 */

if (!defined('ABSPATH')) {
    exit;
}

class MMD_Discount_Rules {

    /**
     * Get all discount rule sets
     */
    /**
     * The label shown to the shopper for this rule's discount.
     *
     * Whatever the merchant typed is used verbatim — nothing is appended — so
     * the cart shows exactly the wording they chose. Falls back to the rule
     * name when no explicit cart label is set.
     *
     * @param array $rule_set
     * @return string
     */
    public static function get_cart_label($rule_set) {
        if (!empty($rule_set['cart_label'])) {
            return $rule_set['cart_label'];
        }
        return isset($rule_set['name']) ? $rule_set['name'] : '';
    }

    public static function get_all_rule_sets() {
        $rule_sets = get_option('mmd_discount_rule_sets', array());

        // Ensure we always have at least the default rule set
        if (empty($rule_sets)) {
            $rule_sets = array(self::get_default_rule_set());
            update_option('mmd_discount_rule_sets', $rule_sets);
        }

        return $rule_sets;
    }

    /**
     * Get default rule set
     */
    private static function get_default_rule_set() {
        return array(
            'id' => 'default_' . time(),
            'name' => __('Default quantity discounts', 'incentiv'),
            'enabled' => 'yes',
            'selection_method' => 'category',
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
            'discount_rules' => array(
                array('quantity' => 3, 'discount' => 30, 'type' => 'percentage'),
                array('quantity' => 4, 'discount' => 50, 'type' => 'percentage'),
                array('quantity' => 5, 'discount' => 100, 'type' => 'percentage'),
            ),
            'apply_to' => 'cheapest',
            'discount_mode' => 'quantity',
            // Coupon settings
            'coupon_required' => 'no',
            'required_coupon_codes' => array(),
            'override_other_discounts' => 'no',
            // Notice settings
            'notice_enabled' => 'yes',
            'notice_title' => __( '☕ Buy more and save!', 'incentiv' ),
            'notice_content' => __( 'Buy more and get a special discount!', 'incentiv' ),
            'notice_color' => '#4CAF50',
            'show_in_mini_cart' => 'yes',
        );
    }

    /**
     * Get single rule set by ID
     */
    public static function get_rule_set($rule_set_id) {
        $rule_sets = self::get_all_rule_sets();

        foreach ($rule_sets as $rule_set) {
            if ($rule_set['id'] === $rule_set_id) {
                return $rule_set;
            }
        }

        return null;
    }

    /**
     * Save rule set
     */
    public static function save_rule_set($rule_set_data) {
        $rule_sets = self::get_all_rule_sets();

        // If no ID, create new one
        if (empty($rule_set_data['id'])) {
            $rule_set_data['id'] = 'rule_' . time() . '_' . wp_rand(1000, 9999);
            $rule_sets[] = $rule_set_data;
        } else {
            // Update existing
            $found = false;
            foreach ($rule_sets as $index => $rule_set) {
                if ($rule_set['id'] === $rule_set_data['id']) {
                    $rule_sets[$index] = $rule_set_data;
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                $rule_sets[] = $rule_set_data;
            }
        }

        return update_option('mmd_discount_rule_sets', $rule_sets);
    }

    /**
     * Delete rule set
     */
    public static function delete_rule_set($rule_set_id) {
        $rule_sets = self::get_all_rule_sets();

        foreach ($rule_sets as $index => $rule_set) {
            if ($rule_set['id'] === $rule_set_id) {
                unset($rule_sets[$index]);
                break;
            }
        }

        // Re-index array
        $rule_sets = array_values($rule_sets);

        return update_option('mmd_discount_rule_sets', $rule_sets);
    }

    /**
     * Duplicate rule set
     */
    public static function duplicate_rule_set($rule_set_id) {
        $rule_set = self::get_rule_set($rule_set_id);

        if (!$rule_set) {
            return false;
        }

        $rule_set['id'] = 'rule_' . time() . '_' . wp_rand(1000, 9999);
        $rule_set['name'] = $rule_set['name'] . ( ' ' . __( '(copy)', 'incentiv' ) );

        return self::save_rule_set($rule_set);
    }

    /**
     * Check if product qualifies for any discount rule set
     */
    public static function get_qualifying_rule_sets($product, $cart_item) {
        $rule_sets = self::get_all_rule_sets();
        $qualifying = array();

        // First pass: find all qualifying rules
        $all_qualifying_ids = array();
        foreach ($rule_sets as $rule_set) {
            if ($rule_set['enabled'] !== 'yes') {
                continue;
            }

            // Check schedule with timezone
            if (!self::is_rule_in_schedule($rule_set)) {
                continue;
            }

            if (self::is_product_qualifying($product, $cart_item, $rule_set)) {
                $qualifying[] = $rule_set;
                $all_qualifying_ids[] = $rule_set['id'];
            }
        }

        // Second pass: filter out rules excluded by other qualifying rules
        $qualifying = array_filter($qualifying, function($rule_set) use ($all_qualifying_ids) {
            $excluded_by = isset($rule_set['excluded_by_rules']) ? $rule_set['excluded_by_rules'] : array();
            if (empty($excluded_by)) {
                return true;
            }
            // If any excluding rule is currently qualifying this product, exclude this rule
            foreach ($excluded_by as $excluding_rule_id) {
                if (in_array($excluding_rule_id, $all_qualifying_ids)) {
                    return false;
                }
            }
            return true;
        });

        return array_values($qualifying);
    }

    /**
     * Public wrapper for schedule check (used by frontend global notices)
     */
    public static function is_rule_in_schedule_public($rule_set) {
        return self::is_rule_in_schedule($rule_set);
    }

    /**
     * Check if rule is within its scheduled time (with timezone support)
     */
    private static function is_rule_in_schedule($rule_set) {
        $schedule_start = isset($rule_set['schedule_start']) ? $rule_set['schedule_start'] : '';
        $schedule_end = isset($rule_set['schedule_end']) ? $rule_set['schedule_end'] : '';

        if (empty($schedule_start) && empty($schedule_end)) {
            return true;
        }

        $timezone_string = isset($rule_set['schedule_timezone']) && !empty($rule_set['schedule_timezone'])
            ? $rule_set['schedule_timezone']
            : wp_timezone_string();

        try {
            $timezone = new DateTimeZone($timezone_string);
        } catch (Exception $e) {
            $timezone = wp_timezone();
        }

        $now = new DateTime('now', $timezone);

        if (!empty($schedule_start)) {
            try {
                $start = new DateTime($schedule_start, $timezone);
                if ($now < $start) {
                    return false;
                }
            } catch (Exception $e) {
                // Invalid date format, ignore
            }
        }

        if (!empty($schedule_end)) {
            try {
                $end = new DateTime($schedule_end, $timezone);
                if ($now > $end) {
                    return false;
                }
            } catch (Exception $e) {
                // Invalid date format, ignore
            }
        }

        return true;
    }

    /**
     * Check if product qualifies for a specific rule set
     */
    private static function is_product_qualifying($product, $cart_item, $rule_set) {
        $product_id = isset($cart_item['product_id']) ? $cart_item['product_id'] : 0;
        $variation_id = isset($cart_item['variation_id']) ? $cart_item['variation_id'] : 0;

        // Check excluded products first (global exclusions)
        if (!empty($rule_set['excluded_products'])) {
            if (in_array($product_id, $rule_set['excluded_products']) ||
                ($variation_id && in_array($variation_id, $rule_set['excluded_products']))) {
                return false;
            }
        }

        $method = $rule_set['selection_method'];

        switch ($method) {
            case 'all':
                return true;

            case 'category':
                return self::check_by_category($product_id, $variation_id, $rule_set);

            case 'product_ids':
                return self::check_by_product_ids($product_id, $variation_id, $rule_set);

            case 'attribute':
                return self::check_by_attribute($product, $rule_set);

            default:
                return false;
        }
    }

    /**
     * Check by category
     */
    private static function check_by_category($product_id, $variation_id, $rule_set) {
        // Check excluded categories
        if (!empty($rule_set['excluded_categories'])) {
            foreach ($rule_set['excluded_categories'] as $cat_slug) {
                if (has_term($cat_slug, 'product_cat', $product_id)) {
                    return false;
                }
            }
        }

        // Check specific product/variation exclusions from categories
        if (!empty($rule_set['excluded_products_from_categories'])) {
            if (in_array($product_id, $rule_set['excluded_products_from_categories']) ||
                ($variation_id && in_array($variation_id, $rule_set['excluded_products_from_categories']))) {
                return false;
            }
        }

        // Check included categories
        if (empty($rule_set['included_categories'])) {
            return false;
        }

        $in_category = false;
        foreach ($rule_set['included_categories'] as $cat_slug) {
            if (has_term($cat_slug, 'product_cat', $product_id)) {
                $in_category = true;
                break;
            }
        }

        if (!$in_category) {
            return false;
        }

        // If limit to specific variations is enabled, check if this variation is in the list
        if (!empty($rule_set['limit_to_variations']) && $rule_set['limit_to_variations'] === 'yes') {
            $included_variations = isset($rule_set['included_variations_from_categories']) ? $rule_set['included_variations_from_categories'] : array();

            if (empty($included_variations)) {
                return false; // Limit is enabled but no variations selected - don't apply
            }

            // Check if this product/variation is in the included list
            if (in_array($product_id, $included_variations) ||
                ($variation_id && in_array($variation_id, $included_variations))) {
                return true;
            }

            return false; // Not in the included variations list
        }

        return true;
    }

    /**
     * Check by product IDs
     */
    private static function check_by_product_ids($product_id, $variation_id, $rule_set) {
        $included = isset($rule_set['included_products']) ? $rule_set['included_products'] : array();

        if (empty($included)) {
            return false;
        }

        return in_array($product_id, $included) || ($variation_id && in_array($variation_id, $included));
    }

    /**
     * Get qualifying rule sets for a product (without cart context).
     * Used for displaying badges on product pages and archives.
     *
     * @param int $product_id The product ID (can be parent or variation).
     * @return array Qualifying rule sets.
     */
    public static function get_qualifying_rule_sets_for_product($product_id) {
        $rule_sets = self::get_all_rule_sets();
        $qualifying = array();

        foreach ($rule_sets as $rule_set) {
            if ($rule_set['enabled'] !== 'yes') {
                continue;
            }

            if (!self::is_rule_in_schedule($rule_set)) {
                continue;
            }

            if (self::does_product_match_rule($product_id, $rule_set)) {
                $qualifying[] = $rule_set;
            }
        }

        return $qualifying;
    }

    /**
     * Get upcoming (scheduled but not yet active) rule sets that match a product.
     * Used for FOMO badges - show users that a sale is coming.
     */
    public static function get_upcoming_rule_sets_for_product($product_id) {
        $rule_sets = self::get_all_rule_sets();
        $upcoming = array();

        $now = current_time('Y-m-d\TH:i');

        foreach ($rule_sets as $rule_set) {
            if ($rule_set['enabled'] !== 'yes') {
                continue;
            }

            // Only include rules with a future start date
            $schedule_start = isset($rule_set['schedule_start']) ? $rule_set['schedule_start'] : '';
            if (empty($schedule_start) || $now >= $schedule_start) {
                continue;
            }

            // Check end date hasn't passed
            $schedule_end = isset($rule_set['schedule_end']) ? $rule_set['schedule_end'] : '';
            if (!empty($schedule_end) && $now > $schedule_end) {
                continue;
            }

            if (self::does_product_match_rule($product_id, $rule_set)) {
                $upcoming[] = $rule_set;
            }
        }

        return $upcoming;
    }

    /**
     * Check if a product matches a rule set (without cart context).
     * Simplified version of is_product_qualifying for badge display.
     *
     * @param int   $product_id The product ID.
     * @param array $rule_set   The rule set to check against.
     * @return bool
     */
    private static function does_product_match_rule($product_id, $rule_set) {
        $product = wc_get_product($product_id);
        if (!$product) {
            return false;
        }

        $parent_id = $product->get_parent_id();
        $check_id = $parent_id ? $parent_id : $product_id;

        // Check excluded products
        if (!empty($rule_set['excluded_products'])) {
            if (in_array($product_id, $rule_set['excluded_products']) ||
                in_array($check_id, $rule_set['excluded_products'])) {
                return false;
            }
        }

        $method = $rule_set['selection_method'];

        switch ($method) {
            case 'all':
                return true;

            case 'category':
                return self::check_by_category($check_id, $product->is_type('variation') ? $product_id : 0, $rule_set);

            case 'product_ids':
                return self::check_by_product_ids($check_id, $product->is_type('variation') ? $product_id : 0, $rule_set);

            case 'attribute':
                return self::check_by_attribute($product, $rule_set);

            default:
                return false;
        }
    }

    /**
     * Check by attribute
     */
    private static function check_by_attribute($product, $rule_set) {
        if (empty($rule_set['attribute_slug']) || empty($rule_set['attribute_values'])) {
            return false;
        }

        $attribute_slug = $rule_set['attribute_slug'];
        $target_values = $rule_set['attribute_values'];
        $excluded_values = isset($rule_set['attribute_excluded_values']) ? $rule_set['attribute_excluded_values'] : array();

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
