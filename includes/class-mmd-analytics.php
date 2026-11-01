<?php
/**
 * Analytics engine for the admin dashboard.
 *
 * Aggregates real order data: revenue attributed to discount rules
 * (negative fee lines + `_mmd_applied_discount_rules` meta), gifts
 * redeemed, special-event orders and per-rule performance.
 *
 * Also persists gift markers onto order line items at checkout so gift
 * redemptions can be counted accurately going forward (older orders fall
 * back to a zero-total line-item heuristic).
 */

if (!defined('ABSPATH')) {
    exit;
}

class MMD_Analytics {

    const CACHE_TTL = 900; // 15 minutes
    const ORDER_SCAN_LIMIT = 600;

    private static $instance = null;

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Persist gift markers from cart items onto order line items.
        add_action('woocommerce_checkout_create_order_line_item', array($this, 'persist_gift_item_meta'), 10, 4);

        // Per-rule attribution, CSV export and the email digest are add-on
        // features (Incentiv Pro); they read get_stats() from this class.
    }

    /**
     * Copy gift flags from the cart item into order item meta.
     */
    public function persist_gift_item_meta($item, $cart_item_key, $values, $order) {
        if (!empty($values['mmd_is_gift']) || !empty($values['mmd_cart_based_gift'])) {
            $item->update_meta_data('_mmd_is_gift', 'yes');
            if (!empty($values['mmd_gift_rule_id'])) {
                $item->update_meta_data('_mmd_gift_rule_id', sanitize_text_field($values['mmd_gift_rule_id']));
            }
        }
    }

    /**
     * Get dashboard stats for a period, with the previous period for deltas.
     *
     * @param int $days 7 | 30 | 365
     * @return array
     */
    public function get_stats($days = 30) {
        $days = max(1, intval($days));
        $cache_key = 'mmd_analytics_v2_' . $days;
        $cached = get_transient($cache_key);
        if (is_array($cached)) {
            return $cached;
        }

        $now = time();
        $current = $this->aggregate_period($now - $days * DAY_IN_SECONDS, $now);
        $previous = $this->aggregate_period($now - 2 * $days * DAY_IN_SECONDS, $now - $days * DAY_IN_SECONDS);

        $stats = array(
            'days' => $days,
            'current' => $current,
            'deltas' => array(
                'revenue' => $this->delta($previous['attributed_revenue'], $current['attributed_revenue']),
                'gifts' => $this->delta($previous['gifts_redeemed'], $current['gifts_redeemed']),
                'conversion' => $this->delta($previous['conversion_rate'], $current['conversion_rate']),
                'avg_cart' => $this->delta($previous['avg_discounted_cart'], $current['avg_discounted_cart']),
            ),
            'previous_rule_revenue' => $previous['rule_revenue'],
        );

        set_transient($cache_key, $stats, self::CACHE_TTL);

        return $stats;
    }

    /**
     * Percentage change between two values (null when no baseline).
     */
    public function delta($old, $new) {
        if ($old <= 0) {
            return null;
        }
        return round((($new - $old) / $old) * 100, 1);
    }

    /**
     * Aggregate all metrics for a time window.
     */
    private function aggregate_period($from_ts, $to_ts) {
        $agg = array(
            'total_orders' => 0,
            'eligible_orders' => 0,
            'discounted_orders' => 0,
            'attributed_revenue' => 0.0,
            'discount_given' => 0.0,
            'gifts_redeemed' => 0,
            'gift_orders' => 0,
            'event_orders' => 0,
            'event_revenue' => 0.0,
            'quantity_revenue' => 0.0,
            'gift_revenue' => 0.0,
            'avg_discounted_cart' => 0.0,
            'conversion_rate' => 0.0,
            'rule_revenue' => array(),   // rule_id => revenue
            'rule_orders' => array(),    // rule_id => order count
            'rule_discount' => array(),  // rule_id => discount given away
            'rule_cogs' => array(),      // rule_id => cost of goods (when known)
            'rule_cogs_known' => array(),// rule_id => bool, cost data available
            // Baseline = orders with no Incentiv discount, used for incremental lift
            'baseline_orders' => 0,
            'baseline_revenue' => 0.0,
            'baseline_aov' => 0.0,
            'discounted_aov' => 0.0,
            'aov_lift_pct' => 0.0,
            'timeline' => array(),       // 7 buckets: ['quantity' => x, 'gift' => y]
            'scanned' => 0,
            'truncated' => false,
        );

        // 7 timeline buckets across the window
        $bucket_count = 7;
        $window = max(1, $to_ts - $from_ts);
        for ($i = 0; $i < $bucket_count; $i++) {
            $agg['timeline'][$i] = array('quantity' => 0.0, 'gift' => 0.0);
        }

        $orders = wc_get_orders(array(
            'limit' => self::ORDER_SCAN_LIMIT,
            'status' => array('wc-processing', 'wc-completed'),
            'date_created' => $from_ts . '...' . $to_ts,
            'orderby' => 'date',
            'order' => 'DESC',
        ));

        if (!is_array($orders)) {
            return $agg;
        }

        $agg['scanned'] = count($orders);
        $agg['truncated'] = (count($orders) >= self::ORDER_SCAN_LIMIT);

        $eligible = $this->build_eligibility_index();
        $rule_names = $this->get_rule_name_map();
        $rule_labels = $this->get_rule_label_map();
        $discounted_totals = array();

        foreach ($orders as $order) {
            if (!$order instanceof WC_Order) {
                continue;
            }

            $agg['total_orders']++;

            $order_total = (float) $order->get_total();
            $created = $order->get_date_created();
            $created_ts = $created ? $created->getTimestamp() : $from_ts;
            $bucket = min($bucket_count - 1, max(0, (int) floor((($created_ts - $from_ts) / $window) * $bucket_count)));

            // --- Rule attribution -------------------------------------------------
            $applied_rules = $order->get_meta('_mmd_applied_discount_rules');
            $applied_rules = is_array($applied_rules) ? $applied_rules : array();

            // Negative fee lines = discount amount
            $order_discount = 0.0;
            foreach ($order->get_fees() as $fee) {
                $fee_total = (float) $fee->get_total();
                if ($fee_total < 0) {
                    $order_discount += abs($fee_total);

                    // Prefer the rule id stamped on the fee — exact, and immune
                    // to the merchant renaming the label.
                    $tagged = $fee->get_meta('_mmd_rule_id');
                    if ($tagged && !in_array($tagged, $applied_rules, true)) {
                        $applied_rules[] = $tagged;
                        continue;
                    }

                    // Older orders have no tag: fall back to matching the fee
                    // name against the label each rule prints on the cart.
                    $fee_name = $fee->get_name();
                    foreach ($rule_labels as $rule_id => $rule_label) {
                        if ($rule_label && strpos($fee_name, $rule_label) !== false && !in_array($rule_id, $applied_rules, true)) {
                            $applied_rules[] = $rule_id;
                        }
                    }
                }
            }

            // Coupons linked to rules
            foreach ($order->get_coupon_codes() as $code) {
                $coupon_id = wc_get_coupon_id_by_code($code);
                if ($coupon_id) {
                    $linked = get_post_meta($coupon_id, '_mmd_linked_rule_id', true);
                    if ($linked && !in_array($linked, $applied_rules, true)) {
                        $applied_rules[] = $linked;
                    }
                }
            }

            // --- Gift detection ---------------------------------------------------
            $gift_qty = 0;
            foreach ($order->get_items() as $item) {
                if ($item->get_meta('_mmd_is_gift') === 'yes') {
                    $gift_qty += max(1, (int) $item->get_quantity());
                } elseif ((float) $item->get_total() <= 0 && (!empty($applied_rules) || $order_discount > 0)) {
                    // Legacy fallback: zero-priced line inside a discounted order
                    $gift_qty += max(1, (int) $item->get_quantity());
                }
            }

            // --- Event attribution ------------------------------------------------
            $event_id = $order->get_meta('_mmd_event_id');
            $is_event_order = !empty($event_id);

            // --- Eligibility (funnel stage) ---------------------------------------
            if ($this->order_has_eligible_product($order, $eligible)) {
                $agg['eligible_orders']++;
            }

            $is_discounted = (!empty($applied_rules) || $order_discount > 0 || $gift_qty > 0 || $is_event_order);
            if (!$is_discounted) {
                // Baseline cohort — what an undiscounted order looks like.
                $agg['baseline_orders']++;
                $agg['baseline_revenue'] += $order_total;
                continue;
            }

            $agg['discounted_orders']++;
            $agg['attributed_revenue'] += $order_total;
            $agg['discount_given'] += $order_discount;
            $discounted_totals[] = $order_total;

            if ($gift_qty > 0) {
                $agg['gifts_redeemed'] += $gift_qty;
                $agg['gift_orders']++;
            }

            if ($is_event_order) {
                $agg['event_orders']++;
                $agg['event_revenue'] += $order_total;
            }

            // Type split + timeline
            if ($gift_qty > 0 && empty($applied_rules) && !$is_event_order) {
                $agg['gift_revenue'] += $order_total;
                $agg['timeline'][$bucket]['gift'] += $order_total;
            } elseif ($is_event_order && empty($applied_rules)) {
                $agg['timeline'][$bucket]['gift'] += $order_total;
            } else {
                $agg['quantity_revenue'] += $order_total;
                $agg['timeline'][$bucket]['quantity'] += $order_total;
            }

            // Per-rule discount cost — prefer the exact amount tagged on the
            // fee line, and fall back to splitting the order discount evenly
            // across the rules that applied.
            $tagged_discount = array();
            foreach ($order->get_items('fee') as $fee) {
                $fee_rule = $fee->get_meta('_mmd_rule_id');
                if ($fee_rule) {
                    $tagged_discount[$fee_rule] = (isset($tagged_discount[$fee_rule]) ? $tagged_discount[$fee_rule] : 0)
                        + abs(floatval($fee->get_total()));
                }
            }

            // Cost of goods for this order (only counted when every line knows
            // its cost, so a partial figure never masquerades as real margin).
            $order_cogs = 0.0;
            $cogs_known = true;
            foreach ($order->get_items() as $line) {
                $product = $line->get_product();
                $cost = ($product && class_exists('MMD_Margin_Guards'))
                    ? MMD_Margin_Guards::get_product_cost($product)
                    : null;
                if (is_null($cost)) {
                    $cogs_known = false;
                    break;
                }
                $order_cogs += floatval($cost) * intval($line->get_quantity());
            }

            $rule_count = max(1, count($applied_rules));
            foreach ($applied_rules as $rule_id) {
                if (!isset($agg['rule_revenue'][$rule_id])) {
                    $agg['rule_revenue'][$rule_id] = 0.0;
                    $agg['rule_orders'][$rule_id] = 0;
                    $agg['rule_discount'][$rule_id] = 0.0;
                    $agg['rule_cogs'][$rule_id] = 0.0;
                    $agg['rule_cogs_known'][$rule_id] = true;
                }
                $agg['rule_revenue'][$rule_id] += $order_total;
                $agg['rule_orders'][$rule_id]++;
                $agg['rule_discount'][$rule_id] += isset($tagged_discount[$rule_id])
                    ? $tagged_discount[$rule_id]
                    : ($order_discount / $rule_count);

                if ($cogs_known) {
                    $agg['rule_cogs'][$rule_id] += $order_cogs / $rule_count;
                } else {
                    $agg['rule_cogs_known'][$rule_id] = false;
                }
            }
        }

        // Incremental lift: how much bigger a discounted cart is than a normal one.
        if ($agg['baseline_orders'] > 0) {
            $agg['baseline_aov'] = $agg['baseline_revenue'] / $agg['baseline_orders'];
        }
        if ($agg['discounted_orders'] > 0) {
            $agg['discounted_aov'] = $agg['attributed_revenue'] / $agg['discounted_orders'];
        }
        if ($agg['baseline_aov'] > 0) {
            $agg['aov_lift_pct'] = round((($agg['discounted_aov'] - $agg['baseline_aov']) / $agg['baseline_aov']) * 100, 1);
        }

        if ($agg['discounted_orders'] > 0) {
            $agg['avg_discounted_cart'] = $agg['attributed_revenue'] / $agg['discounted_orders'];
        }
        if ($agg['total_orders'] > 0) {
            $agg['conversion_rate'] = round(($agg['discounted_orders'] / $agg['total_orders']) * 100, 1);
        }

        arsort($agg['rule_revenue']);

        return $agg;
    }

    /**
     * Map of rule_id => rule name for all discount rule sets.
     */
    /**
     * rule_id => the label the rule prints on the cart fee line. Used to
     * attribute older orders that predate the rule id being stamped on the fee.
     */
    private function get_rule_label_map() {
        $map = array();
        if (class_exists('MMD_Discount_Rules')) {
            foreach ((array) MMD_Discount_Rules::get_all_rule_sets() as $rule_set) {
                if (!empty($rule_set['id'])) {
                    $map[$rule_set['id']] = MMD_Discount_Rules::get_cart_label($rule_set);
                }
            }
        }
        return $map;
    }

    public function get_rule_name_map() {
        $map = array();
        if (class_exists('MMD_Discount_Rules')) {
            foreach ((array) MMD_Discount_Rules::get_all_rule_sets() as $rule_set) {
                if (!empty($rule_set['id'])) {
                    $map[$rule_set['id']] = isset($rule_set['name']) ? $rule_set['name'] : $rule_set['id'];
                }
            }
        }
        return $map;
    }

    /**
     * Public accessor for rule metadata used by the dashboard template.
     */
    public function get_rule_meta_map() {
        $map = array();
        if (class_exists('MMD_Discount_Rules')) {
            foreach ((array) MMD_Discount_Rules::get_all_rule_sets() as $rule_set) {
                if (empty($rule_set['id'])) {
                    continue;
                }
                $map[$rule_set['id']] = array(
                    'name' => isset($rule_set['name']) ? $rule_set['name'] : $rule_set['id'],
                    'enabled' => isset($rule_set['enabled']) ? $rule_set['enabled'] : 'no',
                    'selection_method' => isset($rule_set['selection_method']) ? $rule_set['selection_method'] : 'all',
                );
            }
        }
        return $map;
    }

    /**
     * Build a lightweight index of "eligible" products from active rules.
     * Returns ['all' => bool, 'products' => ids[], 'categories' => term_ids[]].
     */
    private function build_eligibility_index() {
        $index = array('all' => false, 'products' => array(), 'categories' => array());

        if (!class_exists('MMD_Discount_Rules')) {
            return $index;
        }

        foreach ((array) MMD_Discount_Rules::get_all_rule_sets() as $rule_set) {
            if (empty($rule_set['enabled']) || $rule_set['enabled'] !== 'yes') {
                continue;
            }
            $method = isset($rule_set['selection_method']) ? $rule_set['selection_method'] : 'all';
            if ($method === 'all' || $method === 'attribute') {
                // Attribute matching is per-product-meta; treat as "all" for the funnel.
                $index['all'] = true;
            } elseif ($method === 'category' && !empty($rule_set['included_categories'])) {
                $index['categories'] = array_merge($index['categories'], array_map('intval', (array) $rule_set['included_categories']));
            } elseif ($method === 'product_ids' && !empty($rule_set['included_products'])) {
                $index['products'] = array_merge($index['products'], array_map('intval', (array) $rule_set['included_products']));
            }
        }

        $index['categories'] = array_unique($index['categories']);
        $index['products'] = array_unique($index['products']);

        return $index;
    }

    /**
     * Whether an order contains at least one product eligible for a rule.
     */
    private function order_has_eligible_product($order, $index) {
        if ($index['all']) {
            return true;
        }
        if (empty($index['products']) && empty($index['categories'])) {
            return false;
        }

        foreach ($order->get_items() as $item) {
            $product_id = (int) $item->get_product_id();
            $variation_id = (int) $item->get_variation_id();

            if (in_array($product_id, $index['products'], true) || ($variation_id && in_array($variation_id, $index['products'], true))) {
                return true;
            }

            if (!empty($index['categories'])) {
                $terms = wc_get_product_term_ids($product_id, 'product_cat');
                if (array_intersect($terms, $index['categories'])) {
                    return true;
                }
            }
        }

        return false;
    }
}
