<?php
/**
 * Incentiv — Discount allocation
 *
 * Decides how a rule's total discount is broken down onto the individual
 * products in the cart. WooCommerce itself only sees a single cart-level fee
 * unless the discount is pushed onto the lines, which matters for partial
 * refunds, per-product tax and per-product reporting.
 *
 * Modes:
 *   fee          – leave it as one cart-level fee line (default, legacy behaviour)
 *   proportional – split pro-rata by each line's share of the qualifying value
 *   even_line    – the same amount on every qualifying line
 *   even_unit    – the same amount on every qualifying unit
 *   target_line  – all of it on the cheapest / most expensive qualifying line
 *
 * Every mode except `fee` returns a map of cart_item_key => discount amount
 * whose values sum to exactly the requested total (remainder is distributed
 * cent by cent, so rounding never loses or invents money).
 */

if (!defined('ABSPATH')) {
    exit;
}

class MMD_Discount_Allocator {

    const DEFAULT_MODE = 'fee';

    /**
     * All selectable modes with their Hebrew labels + help text.
     */
    public static function get_modes() {
        return array(
            'fee' => array(
                'label' => __( 'Discount line in the cart (default)', 'incentiv' ),
                'help'  => __( 'The discount is shown as a separate line in the cart totals and is not split across products.', 'incentiv' ),
            ),
            'proportional' => array(
                'label' => __( 'Proportional to product value', 'incentiv' ),
                'help'  => __( 'The discount is split across the participating products in proportion to each line\'s value. Recommended for refunds and accurate reports.', 'incentiv' ),
            ),
            'even_line' => array(
                'label' => __( 'Evenly across lines', 'incentiv' ),
                'help'  => __( 'Every participating product line gets the same discount amount.', 'incentiv' ),
            ),
            'even_unit' => array(
                'label' => __( 'Evenly across units', 'incentiv' ),
                'help'  => __( 'Every participating unit gets the same discount amount.', 'incentiv' ),
            ),
            'target_line' => array(
                'label' => __( 'Concentrated on the target product', 'incentiv' ),
                'help'  => __( 'The whole discount is attributed to the cheapest or most expensive product, according to the "Apply discount to" setting.', 'incentiv' ),
            ),
        );
    }

    public static function is_valid_mode($mode) {
        return array_key_exists($mode, self::get_modes());
    }

    /**
     * Resolve the allocation mode for a rule (defaults to the legacy fee line).
     */
    public static function get_mode($rule_set) {
        $mode = isset($rule_set['discount_allocation']) ? $rule_set['discount_allocation'] : self::DEFAULT_MODE;
        return self::is_valid_mode($mode) ? $mode : self::DEFAULT_MODE;
    }

    /**
     * Split a discount across the qualifying items.
     *
     * @param float  $total     Total discount to distribute (positive).
     * @param array  $items     Flattened qualifying units:
     *                          [ ['cart_item_key' => k, 'price' => p], ... ]
     * @param string $mode      One of the modes above (not 'fee').
     * @param string $apply_to  'cheapest' | 'most_expensive' (for target_line).
     * @return array cart_item_key => discount amount (sums to $total)
     */
    public static function allocate($total, $items, $mode, $apply_to = 'cheapest') {
        $total = round(floatval($total), 2);
        if ($total <= 0 || empty($items)) {
            return array();
        }

        // Collapse the flattened unit list into per-line totals.
        $lines = array();   // key => ['value' => float, 'units' => int]
        foreach ($items as $item) {
            $key = isset($item['cart_item_key']) ? $item['cart_item_key'] : null;
            if ($key === null) {
                continue;
            }
            if (!isset($lines[$key])) {
                $lines[$key] = array('value' => 0.0, 'units' => 0);
            }
            $lines[$key]['value'] += floatval(isset($item['price']) ? $item['price'] : 0);
            $lines[$key]['units']++;
        }

        if (empty($lines)) {
            return array();
        }

        // Each line can only absorb its own value — you cannot take more off a
        // product than it costs.
        $capacity = array();
        foreach ($lines as $key => $line) {
            $capacity[$key] = round($line['value'], 2);
        }

        switch ($mode) {
            case 'target_line':
                $target = self::pick_target_line($lines, $apply_to);
                // Preference, not a hard limit: whatever the target line cannot
                // absorb spills over to the rest so the shopper still gets the
                // full discount.
                $weights = array();
                foreach ($lines as $key => $line) {
                    $weights[$key] = ($key === $target) ? 1 : 0;
                }
                $fallback = array();
                foreach ($lines as $key => $line) {
                    $fallback[$key] = max($line['value'], 0.01);
                }
                return self::distribute_with_capacity($total, $weights, $capacity, $fallback);

            case 'even_line':
                $weights = array();
                foreach ($lines as $key => $line) {
                    $weights[$key] = 1;
                }
                return self::distribute_with_capacity($total, $weights, $capacity);

            case 'even_unit':
                $weights = array();
                foreach ($lines as $key => $line) {
                    $weights[$key] = $line['units'];
                }
                return self::distribute_with_capacity($total, $weights, $capacity);

            case 'proportional':
            default:
                $weights = array();
                $sum = 0.0;
                foreach ($lines as $key => $line) {
                    $weights[$key] = $line['value'];
                    $sum += $line['value'];
                }
                // All-zero value lines (fully discounted already) — fall back to
                // an even split so the discount still lands somewhere sensible.
                if ($sum <= 0) {
                    foreach ($weights as $key => $_) {
                        $weights[$key] = 1;
                    }
                }
                return self::distribute_with_capacity($total, $weights, $capacity);
        }
    }

    /**
     * Weighted distribution that respects each line's capacity.
     *
     * A line never receives more discount than it is worth; anything it cannot
     * absorb is redistributed across the lines that still have headroom, keeping
     * their relative proportions. If the cart simply isn't worth the discount,
     * the result sums to the total capacity instead.
     *
     * @param float $total    Amount to distribute.
     * @param array $weights  key => weight (0 is allowed — see $fallback).
     * @param array $capacity key => maximum this line can absorb.
     * @param array $fallback Optional weights used for the overflow once the
     *                        primary weighted lines are full (used by
     *                        target_line, where only one line is preferred).
     * @return array key => amount
     */
    private static function distribute_with_capacity($total, $weights, $capacity, $fallback = array()) {
        $result = array();
        $remaining = round(floatval($total), 2);
        $pool = $weights;

        // Guard against a pathological loop; each round fills at least one line.
        $guard = 0;
        while ($remaining > 0.001 && $guard++ < 50) {
            // Only lines that still have headroom and a positive weight.
            $active = array();
            foreach ($pool as $key => $weight) {
                $used = isset($result[$key]) ? $result[$key] : 0;
                $head = isset($capacity[$key]) ? round($capacity[$key] - $used, 2) : 0;
                if ($weight > 0 && $head > 0.001) {
                    $active[$key] = $weight;
                }
            }

            if (empty($active)) {
                // Preferred lines are full — fall back to the wider pool (or to
                // any line with headroom) so the discount isn't lost.
                $next = array();
                $source = !empty($fallback) ? $fallback : $capacity;
                foreach ($source as $key => $weight) {
                    $used = isset($result[$key]) ? $result[$key] : 0;
                    $head = isset($capacity[$key]) ? round($capacity[$key] - $used, 2) : 0;
                    if ($head > 0.001) {
                        $next[$key] = max($weight, 0.01);
                    }
                }
                if (empty($next)) {
                    break; // cart cannot absorb any more discount
                }
                $pool = $next;
                $fallback = array(); // already consumed
                continue;
            }

            $round = self::distribute($remaining, $active);
            $placed = 0.0;

            foreach ($round as $key => $amount) {
                $used = isset($result[$key]) ? $result[$key] : 0;
                $head = round($capacity[$key] - $used, 2);
                $take = min($amount, $head);
                if ($take > 0) {
                    $result[$key] = round($used + $take, 2);
                    $placed += $take;
                }
            }

            if ($placed <= 0) {
                break; // nothing could be placed — avoid spinning
            }
            $remaining = round($remaining - $placed, 2);
        }

        // Drop zero entries for a clean map.
        foreach ($result as $key => $amount) {
            if ($amount <= 0) {
                unset($result[$key]);
            }
        }

        return $result;
    }

    /**
     * Which line should absorb the whole discount.
     */
    private static function pick_target_line($lines, $apply_to) {
        $target = null;
        $best = null;
        foreach ($lines as $key => $line) {
            // Compare per-unit price so a big multi-unit line isn't mistaken
            // for an expensive product.
            $unit = $line['units'] > 0 ? $line['value'] / $line['units'] : $line['value'];
            if ($best === null
                || ($apply_to === 'most_expensive' && $unit > $best)
                || ($apply_to !== 'most_expensive' && $unit < $best)) {
                $best = $unit;
                $target = $key;
            }
        }
        return $target;
    }

    /**
     * Distribute a total across weighted buckets so the parts sum to exactly
     * the total. Largest-remainder method: floor everything to the cent, then
     * hand the leftover cents to the biggest remainders.
     *
     * @param float $total
     * @param array $weights key => weight (>= 0)
     * @return array key => amount
     */
    private static function distribute($total, $weights) {
        $weight_sum = array_sum($weights);
        if ($weight_sum <= 0) {
            return array();
        }

        // Work in cents to avoid float drift.
        $total_cents = (int) round($total * 100);
        $exact = array();
        $floors = array();
        $assigned = 0;

        foreach ($weights as $key => $weight) {
            $share = ($total_cents * $weight) / $weight_sum;
            $exact[$key] = $share;
            $floors[$key] = (int) floor($share);
            $assigned += $floors[$key];
        }

        $remainder = $total_cents - $assigned;

        if ($remainder > 0) {
            // Order keys by the size of their dropped fraction, biggest first.
            $fractions = array();
            foreach ($exact as $key => $share) {
                $fractions[$key] = $share - floor($share);
            }
            arsort($fractions);
            foreach (array_keys($fractions) as $key) {
                if ($remainder <= 0) {
                    break;
                }
                $floors[$key]++;
                $remainder--;
            }
        }

        $out = array();
        foreach ($floors as $key => $cents) {
            if ($cents > 0) {
                $out[$key] = round($cents / 100, 2);
            }
        }

        return $out;
    }

    /**
     * Apply an allocation to the cart by reducing per-unit prices.
     * Returns the per-line amounts that were actually applied.
     *
     * @param object $cart       WC_Cart
     * @param array  $allocation cart_item_key => discount amount
     * @param array  $rule_set   Rule (for the margin floor)
     * @return array cart_item_key => amount actually discounted
     */
    public static function apply_to_cart($cart, $allocation, $rule_set = array()) {
        $applied = array();
        if (empty($allocation) || !$cart) {
            return $applied;
        }

        $cart_contents = $cart->get_cart();

        foreach ($allocation as $key => $amount) {
            if (!isset($cart_contents[$key]) || $amount <= 0) {
                continue;
            }

            $item = $cart_contents[$key];
            $product = $item['data'];
            $qty = max(1, intval($item['quantity']));
            $price = floatval($product->get_price());

            $per_unit_cut = $amount / $qty;
            $new_price = max(0, $price - $per_unit_cut);

            // Never let an allocation breach the rule's margin floor.
            if (class_exists('MMD_Margin_Guards')) {
                $new_price = MMD_Margin_Guards::enforce_margin_floor($new_price, $product, $rule_set);
            }

            $product->set_price($new_price);

            // Report what was really taken off (the floor may have limited it).
            $applied[$key] = round(($price - $new_price) * $qty, 2);
        }

        return $applied;
    }
}
