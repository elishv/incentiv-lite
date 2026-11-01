<?php
/**
 * Incentiv — Margin guards & limits
 *
 * The safety layer that keeps aggressive promotions survivable:
 *  - maximum discount per cart / per order (amount or percent)
 *  - budget caps that pause a campaign once it has given away enough
 *  - product and category exclusions (honoured by the rule engine)
 *  - a minimum margin floor per line, using product cost data
 *
 * Every guard is opt-in — an unset guard never changes existing behaviour.
 */

if (!defined('ABSPATH')) {
    exit;
}

class MMD_Margin_Guards {

    const SPEND_OPTION = 'mmd_rule_discount_spend';

    private static $instance = null;

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Record what each rule actually gave away, so budget caps can work.
        add_action('woocommerce_checkout_order_processed', array($this, 'record_order_spend'), 20, 1);
        add_action('woocommerce_store_api_checkout_order_processed', array($this, 'record_order_spend'), 20, 1);
    }

    /* =====================================================================
     * Product cost / margin
     * ===================================================================== */

    /**
     * Best-effort unit cost for a product.
     * Supports the common "Cost of Goods" meta keys plus our own override.
     */
    public static function get_product_cost($product) {
        if (!is_object($product)) {
            return null;
        }

        $keys = array('_mmd_cost', '_wc_cog_cost', '_wc_cog_cost_variable', '_alg_wc_cog_cost', '_cost_of_goods');
        foreach ($keys as $key) {
            $value = $product->get_meta($key, true);
            if ($value !== '' && $value !== null && is_numeric($value)) {
                return floatval($value);
            }
        }

        // Fall back to the parent product for variations.
        if (method_exists($product, 'get_parent_id') && $product->get_parent_id()) {
            $parent = wc_get_product($product->get_parent_id());
            if ($parent) {
                foreach ($keys as $key) {
                    $value = $parent->get_meta($key, true);
                    if ($value !== '' && $value !== null && is_numeric($value)) {
                        return floatval($value);
                    }
                }
            }
        }

        return null; // unknown cost — the margin floor cannot be enforced
    }

    /**
     * Clamp a discounted unit price so it never breaks the rule's margin floor.
     *
     * @param float  $discounted_price Proposed unit price after discount.
     * @param object $product          WC_Product.
     * @param array  $rule_set         Rule configuration.
     * @return float Price that respects the floor (or the original when unknown).
     */
    public static function enforce_margin_floor($discounted_price, $product, $rule_set) {
        $floor = isset($rule_set['margin_floor_percent']) && $rule_set['margin_floor_percent'] !== ''
            ? floatval($rule_set['margin_floor_percent'])
            : 0;

        if ($floor <= 0) {
            return $discounted_price;
        }

        $cost = self::get_product_cost($product);
        if (is_null($cost) || $cost <= 0) {
            return $discounted_price; // no cost data — nothing to enforce against
        }

        // Minimum price that still leaves `floor`% margin on the sale price:
        //   (price - cost) / price >= floor/100  =>  price >= cost / (1 - floor/100)
        $ratio = 1 - ($floor / 100);
        if ($ratio <= 0) {
            return $discounted_price;
        }

        $min_price = $cost / $ratio;

        return ($discounted_price < $min_price) ? round($min_price, wc_get_price_decimals()) : $discounted_price;
    }

    /* =====================================================================
     * Per-cart discount ceiling
     * ===================================================================== */

    /**
     * Cap a rule's total discount for one cart.
     *
     * @param float $discount      Proposed discount amount.
     * @param array $rule_set      Rule configuration.
     * @param float $cart_subtotal Cart subtotal the percentage applies to.
     * @return float Capped discount.
     */
    public static function cap_cart_discount($discount, $rule_set, $cart_subtotal = 0) {
        $discount = floatval($discount);
        if ($discount <= 0) {
            return $discount;
        }

        $max_amount = isset($rule_set['max_discount_amount']) && $rule_set['max_discount_amount'] !== ''
            ? floatval($rule_set['max_discount_amount']) : 0;
        if ($max_amount > 0 && $discount > $max_amount) {
            $discount = $max_amount;
        }

        $max_percent = isset($rule_set['max_discount_percent']) && $rule_set['max_discount_percent'] !== ''
            ? floatval($rule_set['max_discount_percent']) : 0;
        if ($max_percent > 0 && $cart_subtotal > 0) {
            $ceiling = $cart_subtotal * ($max_percent / 100);
            if ($discount > $ceiling) {
                $discount = $ceiling;
            }
        }

        return round($discount, wc_get_price_decimals());
    }

    /* =====================================================================
     * Budget caps
     * ===================================================================== */

    public static function get_spend_map() {
        $map = get_option(self::SPEND_OPTION, array());
        return is_array($map) ? $map : array();
    }

    public static function get_rule_spend($rule_id) {
        $map = self::get_spend_map();
        return isset($map[$rule_id]) ? floatval($map[$rule_id]) : 0.0;
    }

    public static function add_rule_spend($rule_id, $amount) {
        if (!$rule_id || $amount <= 0) {
            return;
        }
        $map = self::get_spend_map();
        $map[$rule_id] = (isset($map[$rule_id]) ? floatval($map[$rule_id]) : 0) + floatval($amount);
        update_option(self::SPEND_OPTION, $map, false);
    }

    public static function reset_rule_spend($rule_id) {
        $map = self::get_spend_map();
        unset($map[$rule_id]);
        update_option(self::SPEND_OPTION, $map, false);
    }

    /**
     * Has this rule exhausted its discount budget?
     */
    public static function budget_exhausted($rule_set) {
        $cap = isset($rule_set['budget_cap']) && $rule_set['budget_cap'] !== '' ? floatval($rule_set['budget_cap']) : 0;
        if ($cap <= 0) {
            return false;
        }
        $rule_id = isset($rule_set['id']) ? $rule_set['id'] : '';
        if (!$rule_id) {
            return false;
        }
        return self::get_rule_spend($rule_id) >= $cap;
    }

    /**
     * Remaining budget for a rule (null when uncapped).
     */
    public static function budget_remaining($rule_set) {
        $cap = isset($rule_set['budget_cap']) && $rule_set['budget_cap'] !== '' ? floatval($rule_set['budget_cap']) : 0;
        if ($cap <= 0) {
            return null;
        }
        $rule_id = isset($rule_set['id']) ? $rule_set['id'] : '';
        return max(0, $cap - self::get_rule_spend($rule_id));
    }

    /**
     * When an order completes, bank what each Incentiv rule discounted so the
     * budget caps reflect real spend. Also flips the rule off once the cap is
     * reached, so the campaign visibly pauses in the admin.
     */
    public function record_order_spend($order_id) {
        $order = is_object($order_id) ? $order_id : wc_get_order($order_id);
        if (!$order) {
            return;
        }

        // Guard against double-counting if both hooks fire.
        if ($order->get_meta('_mmd_spend_recorded') === 'yes') {
            return;
        }

        $totals = array();
        foreach ($order->get_items('fee') as $fee) {
            $rule_id = $fee->get_meta('_mmd_rule_id');
            $amount = abs(floatval($fee->get_total()));
            if ($rule_id && $amount > 0) {
                $totals[$rule_id] = (isset($totals[$rule_id]) ? $totals[$rule_id] : 0) + $amount;
            }
        }
        foreach ($order->get_items() as $item) {
            $rule_id = $item->get_meta('_mmd_rule_id');
            $saved = floatval($item->get_meta('_mmd_discount_amount'));
            if ($rule_id && $saved > 0) {
                $totals[$rule_id] = (isset($totals[$rule_id]) ? $totals[$rule_id] : 0) + $saved;
            }
        }

        if (empty($totals)) {
            return;
        }

        foreach ($totals as $rule_id => $amount) {
            self::add_rule_spend($rule_id, $amount);
            self::maybe_pause_rule($rule_id);
        }

        $order->update_meta_data('_mmd_spend_recorded', 'yes');
        $order->save();
    }

    /**
     * Disable a rule once it has spent its budget, and leave a note in the
     * rule so the admin shows why it paused.
     */
    public static function maybe_pause_rule($rule_id) {
        if (!class_exists('MMD_Discount_Rules')) {
            return;
        }
        $rule_set = MMD_Discount_Rules::get_rule_set($rule_id);
        if (!$rule_set || !self::budget_exhausted($rule_set)) {
            return;
        }
        if (isset($rule_set['enabled']) && $rule_set['enabled'] === 'no') {
            return; // already paused
        }
        $rule_set['enabled'] = 'no';
        $rule_set['paused_reason'] = 'budget';
        MMD_Discount_Rules::save_rule_set($rule_set);
    }
}

MMD_Margin_Guards::instance();
