<?php
/**
 * Admin Dashboard — analytics overview (v1.9 redesign)
 */

if (!defined('ABSPATH')) {
    exit;
}

$period = isset($_GET['period']) ? intval($_GET['period']) : 30; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin view parameter.
if (!in_array($period, array(7, 30, 365), true)) {
    $period = 30;
}

$analytics = MMD_Analytics::instance();
$stats = $analytics->get_stats($period);
$cur = $stats['current'];
$deltas = $stats['deltas'];
$rule_meta = $analytics->get_rule_meta_map();

$period_labels = array(7 => __( 'Last 7 days', 'incentiv' ), 30 => __( 'Last 30 days', 'incentiv' ), 365 => __( 'Last year', 'incentiv' ));

// Donut split. The "Special events" slice only exists with the add-on that runs events.
$show_events = MMD_Modules::is_active('special_events');
$event_rev = $show_events ? min($cur['event_revenue'], $cur['attributed_revenue']) : 0;
$gift_rev = max(0, min($cur['gift_revenue'], $cur['attributed_revenue'] - $event_rev));
$qty_rev = max(0, $cur['attributed_revenue'] - $event_rev - $gift_rev);
$split_total = $qty_rev + $gift_rev + $event_rev;
$pct_qty = $split_total > 0 ? round($qty_rev / $split_total * 100) : 0;
$pct_gift = $split_total > 0 ? round($gift_rev / $split_total * 100) : 0;
$pct_event = $split_total > 0 ? max(0, 100 - $pct_qty - $pct_gift) : 0;

// Timeline chart scale
$max_bucket = 0;
foreach ($cur['timeline'] as $bucket) {
    $max_bucket = max($max_bucket, $bucket['quantity'] + $bucket['gift']);
}

// Bucket date labels
$bucket_labels = array();
$bucket_count = count($cur['timeline']);
$window_start = current_time('timestamp') - $period * DAY_IN_SECONDS;
for ($i = 0; $i < $bucket_count; $i++) {
    $ts = $window_start + (int) (($period * DAY_IN_SECONDS / $bucket_count) * $i);
    $bucket_labels[] = date_i18n('j.n', $ts);
}

// Funnel
$f_total = $cur['total_orders'];
$f_eligible = $cur['eligible_orders'];
$f_discounted = $cur['discounted_orders'];
$f_gifts = $cur['gift_orders'];
$funnel_max = max(1, $f_total);

// Top rules
$top_rules = array_slice($cur['rule_revenue'], 0, 4, true);
$rule_icons = array('local_cafe', 'redeem', 'celebration', 'sell');

$delta_chip = function ($delta) {
    if ($delta === null) {
        return '';
    }
    $up = $delta >= 0;
    $cls = $up ? 'mmd-kpi-delta-up' : 'mmd-kpi-delta-down';
    $icon = $up ? 'trending_up' : 'trending_down';
    return '<span class="mmd-kpi-delta ' . $cls . '"><span class="msr">' . $icon . '</span>' . esc_html(abs($delta)) . '%</span>';
};
?>

<div class="wrap mmd-admin-wrap">
    <?php include MMD_PLUGIN_DIR . 'templates/admin-nav-tabs.php'; ?>

    <div class="mmd-page-head">
        <div>
            <h2 class="mmd-page-title"><?php esc_html_e( 'Dashboard', 'incentiv' ); ?></h2>
            <div class="mmd-page-sub"><?php echo wp_kses_post( __( 'Discount performance overview &middot;', 'incentiv' ) ); ?> <?php echo esc_html($period_labels[$period]); ?></div>
        </div>
        <div class="mmd-period-switch">
            <?php foreach (array(30 => __( '30 days', 'incentiv' ), 7 => __( '7 days', 'incentiv' ), 365 => __( 'Year', 'incentiv' )) as $days => $label): ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=mmd-dashboard&period=' . $days)); ?>"
                   class="<?php echo $period === $days ? 'mmd-period-active' : ''; ?>"><?php echo esc_html($label); ?></a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- KPI row -->
    <div class="mmd-kpi-grid">
        <div class="mmd-kpi">
            <div class="mmd-kpi-top">
                <div class="mmd-kpi-icon"><span class="msr">payments</span></div>
                <?php echo $delta_chip($deltas['revenue']); // phpcs:ignore ?>
            </div>
            <div class="mmd-kpi-value"><?php echo wp_kses_post(wc_price($cur['attributed_revenue'])); ?></div>
            <div class="mmd-kpi-label"><?php esc_html_e( 'Sales attributed to discounts', 'incentiv' ); ?></div>
        </div>
        <div class="mmd-kpi">
            <div class="mmd-kpi-top">
                <div class="mmd-kpi-icon mmd-kpi-icon-purple"><span class="msr">redeem</span></div>
                <?php echo $delta_chip($deltas['gifts']); // phpcs:ignore ?>
            </div>
            <div class="mmd-kpi-value"><?php echo esc_html(number_format_i18n($cur['gifts_redeemed'])); ?></div>
            <div class="mmd-kpi-label"><?php esc_html_e( 'Gifts redeemed', 'incentiv' ); ?></div>
        </div>
        <div class="mmd-kpi">
            <div class="mmd-kpi-top">
                <div class="mmd-kpi-icon mmd-kpi-icon-green"><span class="msr">percent</span></div>
                <?php echo $delta_chip($deltas['conversion']); // phpcs:ignore ?>
            </div>
            <div class="mmd-kpi-value"><?php echo esc_html($cur['conversion_rate']); ?>%</div>
            <div class="mmd-kpi-label"><?php esc_html_e( 'Conversion rate to orders with a discount', 'incentiv' ); ?></div>
        </div>
        <div class="mmd-kpi">
            <div class="mmd-kpi-top">
                <div class="mmd-kpi-icon mmd-kpi-icon-orange"><span class="msr">shopping_basket</span></div>
                <?php echo $delta_chip($deltas['avg_cart']); // phpcs:ignore ?>
            </div>
            <div class="mmd-kpi-value"><?php echo wp_kses_post(wc_price($cur['avg_discounted_cart'])); ?></div>
            <div class="mmd-kpi-label"><?php esc_html_e( 'Average cart with a discount', 'incentiv' ); ?></div>
        </div>
    </div>

    <?php if ($cur['total_orders'] === 0): ?>
        <div class="mmd-empty-state">
            <p><?php esc_html_e( 'There is no order data for this period yet. As soon as orders with discounts come in, the dashboard fills up with real data.', 'incentiv' ); ?></p>
            <a href="<?php echo esc_url(admin_url('admin.php?page=mix-match-discounts&action=new')); ?>" class="mmd-btn mmd-btn-primary">
                <span class="msr">auto_awesome</span><?php esc_html_e( 'Create your first discount rule', 'incentiv' ); ?>
            </a>
        </div>
    <?php else: ?>

    <!-- Chart + donut row -->
    <div class="mmd-dash-row">
        <div class="mmd-chart-card">
            <div class="mmd-chart-head">
                <div>
                    <h3 class="mmd-chart-title"><?php esc_html_e( 'Sales from discounts over time', 'incentiv' ); ?></h3>
                    <div class="mmd-chart-sub"><?php esc_html_e( 'Revenue driven by discounts', 'incentiv' ); ?></div>
                </div>
                <div class="mmd-legend">
                    <span><span class="mmd-legend-dot" style="background:#3a5bff;"></span><?php esc_html_e( 'Quantity discounts', 'incentiv' ); ?></span>
                    <span><span class="mmd-legend-dot" style="background:#7c3aed;"></span><?php esc_html_e( 'Gifts and promotions', 'incentiv' ); ?></span>
                </div>
            </div>
            <div class="mmd-barchart">
                <?php foreach ($cur['timeline'] as $i => $bucket):
                    $total = $bucket['quantity'] + $bucket['gift'];
                    $qty_h = $max_bucket > 0 ? round($bucket['quantity'] / $max_bucket * 100) : 0;
                    $gift_h = $max_bucket > 0 ? round($bucket['gift'] / $max_bucket * 100) : 0;
                    $delay = $i * 0.06;
                ?>
                <div class="mmd-bar-col" title="<?php echo esc_attr(wp_strip_all_tags(wc_price($total))); ?>">
                    <?php if ($gift_h > 0): ?>
                        <div class="mmd-bar-seg mmd-bar-seg-top" style="height:<?php echo esc_attr($gift_h); ?>%;animation-delay:<?php echo esc_attr($delay); ?>s;"></div>
                    <?php endif; ?>
                    <div class="mmd-bar-seg <?php echo $gift_h <= 0 ? 'mmd-bar-seg-solo' : ''; ?>" style="height:<?php echo esc_attr(max(1, $qty_h)); ?>%;animation-delay:<?php echo esc_attr($delay + 0.05); ?>s;"></div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="mmd-barchart-labels">
                <?php foreach ($bucket_labels as $label): ?>
                    <span><?php echo esc_html($label); ?></span>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="mmd-chart-card">
            <h3 class="mmd-chart-title" style="margin:0 0 2px;"><?php esc_html_e( 'Discount type breakdown', 'incentiv' ); ?></h3>
            <div class="mmd-chart-sub" style="margin-bottom:8px;"><?php esc_html_e( 'Breakdown by benefit type', 'incentiv' ); ?></div>
            <div class="mmd-donut-wrap">
                <svg viewBox="0 0 42 42" class="mmd-donut">
                    <circle cx="21" cy="21" r="15.9" fill="none" stroke="#eef1f6" stroke-width="6"></circle>
                    <?php if ($split_total > 0): ?>
                        <circle cx="21" cy="21" r="15.9" fill="none" stroke="#3a5bff" stroke-width="6"
                                stroke-dasharray="<?php echo esc_attr($pct_qty); ?> <?php echo esc_attr(100 - $pct_qty); ?>" stroke-dashoffset="0"
                                style="--len:100;animation:mmdDash 1s ease both;"></circle>
                        <circle cx="21" cy="21" r="15.9" fill="none" stroke="#7c3aed" stroke-width="6"
                                stroke-dasharray="<?php echo esc_attr($pct_gift); ?> <?php echo esc_attr(100 - $pct_gift); ?>"
                                stroke-dashoffset="<?php echo esc_attr(-$pct_qty); ?>"></circle>
                        <?php if ($show_events): ?>
                        <circle cx="21" cy="21" r="15.9" fill="none" stroke="#f79009" stroke-width="6"
                                stroke-dasharray="<?php echo esc_attr($pct_event); ?> <?php echo esc_attr(100 - $pct_event); ?>"
                                stroke-dashoffset="<?php echo esc_attr(-($pct_qty + $pct_gift)); ?>"></circle>
                        <?php endif; ?>
                    <?php endif; ?>
                </svg>
            </div>
            <div class="mmd-donut-legend">
                <div>
                    <span class="mmd-legend-name"><span class="mmd-legend-sq" style="background:#3a5bff;"></span><?php esc_html_e( 'Quantity discount', 'incentiv' ); ?></span>
                    <strong><?php echo esc_html($pct_qty); ?>%</strong>
                </div>
                <div>
                    <span class="mmd-legend-name"><span class="mmd-legend-sq" style="background:#7c3aed;"></span><?php esc_html_e( 'Buy and get a gift', 'incentiv' ); ?></span>
                    <strong><?php echo esc_html($pct_gift); ?>%</strong>
                </div>
                <?php if ($show_events): ?>
                <div>
                    <span class="mmd-legend-name"><span class="mmd-legend-sq" style="background:#f79009;"></span><?php esc_html_e( 'Special events', 'incentiv' ); ?></span>
                    <strong><?php echo esc_html($pct_event); ?>%</strong>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Funnel + top rules -->
    <div class="mmd-dash-row mmd-dash-row-2">
        <div class="mmd-chart-card">
            <h3 class="mmd-chart-title" style="margin:0 0 2px;"><?php esc_html_e( 'Discount funnel', 'incentiv' ); ?></h3>
            <div class="mmd-chart-sub" style="margin-bottom:16px;"><?php esc_html_e( 'Based on orders', 'incentiv' ); ?></div>
            <div class="mmd-funnel">
                <div class="mmd-funnel-row">
                    <div class="mmd-funnel-meta">
                        <span class="mmd-funnel-name"><?php esc_html_e( 'Orders in the period', 'incentiv' ); ?></span>
                        <span class="mmd-funnel-count"><?php echo esc_html(number_format_i18n($f_total)); ?></span>
                    </div>
                    <div class="mmd-funnel-bar" style="width:100%;"></div>
                </div>
                <div class="mmd-funnel-row">
                    <div class="mmd-funnel-meta">
                        <span class="mmd-funnel-name"><?php esc_html_e( 'Contained participating products', 'incentiv' ); ?></span>
                        <span class="mmd-funnel-count"><?php echo esc_html(number_format_i18n($f_eligible)); ?> &middot; <?php echo esc_html($f_total ? round($f_eligible / $funnel_max * 100) : 0); ?>%</span>
                    </div>
                    <div class="mmd-funnel-bar" style="width:<?php echo esc_attr(max(8, round($f_eligible / $funnel_max * 100))); ?>%;"></div>
                </div>
                <div class="mmd-funnel-row">
                    <div class="mmd-funnel-meta">
                        <span class="mmd-funnel-name"><?php esc_html_e( 'Redeemed a discount', 'incentiv' ); ?></span>
                        <span class="mmd-funnel-count mmd-funnel-count-final"><?php echo esc_html(number_format_i18n($f_discounted)); ?> &middot; <?php echo esc_html($cur['conversion_rate']); ?>%</span>
                    </div>
                    <div class="mmd-funnel-bar mmd-funnel-bar-final" style="width:<?php echo esc_attr(max(8, round($f_discounted / $funnel_max * 100))); ?>%;"></div>
                </div>
                <div class="mmd-funnel-row">
                    <div class="mmd-funnel-meta">
                        <span class="mmd-funnel-name"><?php esc_html_e( 'Included a gift 🎁', 'incentiv' ); ?></span>
                        <span class="mmd-funnel-count"><?php echo esc_html(number_format_i18n($f_gifts)); ?></span>
                    </div>
                    <div class="mmd-funnel-bar" style="width:<?php echo esc_attr(max(8, round($f_gifts / $funnel_max * 100))); ?>%;background:linear-gradient(90deg,#7c3aed,#a855f7);"></div>
                </div>
            </div>
        </div>

        <div class="mmd-chart-card">
            <div class="mmd-chart-head" style="margin-bottom:14px;">
                <div>
                    <h3 class="mmd-chart-title"><?php esc_html_e( 'Top rules', 'incentiv' ); ?></h3>
                    <div class="mmd-chart-sub"><?php esc_html_e( 'The best-performing rules', 'incentiv' ); ?></div>
                </div>
                <a href="<?php echo esc_url(admin_url('admin.php?page=mix-match-discounts')); ?>" style="font-size:12.5px;font-weight:700;"><?php esc_html_e( 'Show all →', 'incentiv' ); ?></a>
            </div>
            <?php if (empty($top_rules)): ?>
                <p style="color:var(--mmd-text-light);font-size:13px;"><?php esc_html_e( 'There is no performance data for rules in this period yet.', 'incentiv' ); ?></p>
            <?php else: ?>
                <div style="display:flex;flex-direction:column;gap:4px;">
                    <div class="mmd-ranklist-head"><span><?php esc_html_e( 'Rule', 'incentiv' ); ?></span><span><?php esc_html_e( 'Sales', 'incentiv' ); ?></span><span><?php esc_html_e( 'Trend', 'incentiv' ); ?></span></div>
                    <?php $i = 0; foreach ($top_rules as $rule_id => $revenue):
                        $name = isset($rule_meta[$rule_id]['name']) ? $rule_meta[$rule_id]['name'] : $rule_id;
                        $prev = isset($stats['previous_rule_revenue'][$rule_id]) ? $stats['previous_rule_revenue'][$rule_id] : 0;
                        $trend = $prev > 0 ? round((($revenue - $prev) / $prev) * 100) : null;
                        $icon = $rule_icons[$i % count($rule_icons)];
                        $icon_cls = ($i % 3 === 1) ? 'mmd-row-icon-purple' : (($i % 3 === 2) ? 'mmd-row-icon-orange' : '');
                        $i++;
                    ?>
                    <div class="mmd-ranklist-row">
                        <div class="mmd-rank-name">
                            <span class="mmd-rank-icon <?php echo esc_attr($icon_cls); ?>"><span class="msr"><?php echo esc_html($icon); ?></span></span>
                            <span><?php echo esc_html($name); ?></span>
                        </div>
                        <span class="mmd-rank-val"><?php echo wp_kses_post(wc_price($revenue)); ?></span>
                        <?php if ($trend === null): ?>
                            <span style="color:var(--mmd-text-light);font-size:12px;"><?php esc_html_e( 'New', 'incentiv' ); ?></span>
                        <?php elseif ($trend >= 0): ?>
                            <span class="mmd-trend-up"><span class="msr">trending_up</span>+<?php echo esc_html($trend); ?>%</span>
                        <?php else: ?>
                            <span class="mmd-trend-down"><span class="msr">trending_down</span><?php echo esc_html($trend); ?>%</span>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php
    // Extra dashboard sections from add-ons (for example per-rule attribution in Incentiv Pro).
    do_action('mmd_dashboard_sections', $period, $stats);
    ?>

    <?php if (!empty($cur['truncated'])): ?>
        <p style="font-size:12px;color:var(--mmd-text-light);margin-top:12px;">
            <?php esc_html_e( '* The data is based on the', 'incentiv' ); ?> <?php echo esc_html(number_format_i18n($cur['scanned'])); ?> <?php esc_html_e( 'most recent orders in the period.', 'incentiv' ); ?>
        </p>
    <?php endif; ?>

    <?php endif; ?>
</div>
