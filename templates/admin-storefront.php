<?php
/**
 * Storefront showcase — "תצוגת לקוח"
 * Live preview of every customer-facing component, populated from the
 * store's real rules and styling settings.
 */

if (!defined('ABSPATH')) {
    exit;
}

// First active discount rule powers the tier previews
$rule_sets = MMD_Discount_Rules::get_all_rule_sets();
$active_rule = null;
foreach ((array) $rule_sets as $rs) {
    if (isset($rs['enabled']) && $rs['enabled'] === 'yes' && !empty($rs['discount_rules'])) {
        $active_rule = $rs;
        break;
    }
}

$tiers = $active_rule ? $active_rule['discount_rules'] : array(
    array('quantity' => 3, 'discount' => 30, 'type' => 'percentage'),
    array('quantity' => 4, 'discount' => 50, 'type' => 'percentage'),
    array('quantity' => 5, 'discount' => 100, 'type' => 'percentage'),
);
usort($tiers, function ($a, $b) {
    return intval($a['quantity']) - intval($b['quantity']);
});

$rule_name = $active_rule ? $active_rule['name'] : __( 'Example discount rule', 'incentiv' );
$notice_title = $active_rule && !empty($active_rule['notice_title']) ? $active_rule['notice_title'] : __( '☕ Buy more and save!', 'incentiv' );
$notice_content = $active_rule && !empty($active_rule['notice_content']) ? $active_rule['notice_content'] : __( 'Buy one more unit and get the cheapest item free!', 'incentiv' );
$notice_color = $active_rule && !empty($active_rule['notice_color']) ? $active_rule['notice_color'] : '#a16a3f';
$badge_text = $active_rule && !empty($active_rule['badge_text']) ? $active_rule['badge_text'] : __( 'Promotion', 'incentiv' );
$badge_bg = $active_rule && !empty($active_rule['badge_bg_color']) ? $active_rule['badge_bg_color'] : '#e53935';
$badge_color = $active_rule && !empty($active_rule['badge_text_color']) ? $active_rule['badge_text_color'] : '#ffffff';

// Global notice (promo bar) from the first rule that enables it
$promo_text = '';
$promo_bg = '';
$promo_color = '#ffffff';
foreach ((array) $rule_sets as $rs) {
    if (isset($rs['global_notice_enabled']) && $rs['global_notice_enabled'] === 'yes' && !empty($rs['global_notice_text'])) {
        $promo_text = $rs['global_notice_text'];
        $promo_bg = !empty($rs['global_notice_bg_color']) ? $rs['global_notice_bg_color'] : '';
        $promo_color = !empty($rs['global_notice_text_color']) ? $rs['global_notice_text_color'] : '#ffffff';
        break;
    }
}
if (!$promo_text) {
    $promo_text = __( 'Free shipping over ₪250 · Buy in quantity and the cheapest is free! 🎉', 'incentiv' );
}

// Gift styling
$gift_styling = class_exists('MMD_Gift_Rules') ? MMD_Gift_Rules::get_instance()->get_styling_options() : array();
$gw_title = !empty($gift_styling['gift_box_title_text']) ? $gift_styling['gift_box_title_text'] : _x( '🎁 Choose your gift!', 'rule', 'incentiv' );
$gw_subtitle = __( 'Add one product at no cost', 'incentiv' );
$gw_bg1 = !empty($gift_styling['gift_box_bg_color_1']) ? $gift_styling['gift_box_bg_color_1'] : '#7c3aed';
$gw_bg2 = !empty($gift_styling['gift_box_bg_color_2']) ? $gift_styling['gift_box_bg_color_2'] : '#a855f7';

// Discount label for mini-cart badge
$discount_label = $active_rule && !empty($active_rule['discount_label']) ? $active_rule['discount_label'] : __( '☕ Quantity discount', 'incentiv' );

// ---------------------------------------------------------------------------
// Real store data — previews are driven by actual products
// ---------------------------------------------------------------------------

// Hero product: prefer a product that matches the active rule
$hero_product = null;
if ($active_rule) {
    $hero_args = array(
        'post_type' => 'product',
        'post_status' => 'publish',
        'posts_per_page' => 1,
        'fields' => 'ids',
    );
    $rule_method = isset($active_rule['selection_method']) ? $active_rule['selection_method'] : 'all';
    if ($rule_method === 'category' && !empty($active_rule['included_categories'])) {
        $hero_args['tax_query'] = array(array(
            'taxonomy' => 'product_cat',
            'field' => 'term_id',
            'terms' => array_map('intval', (array) $active_rule['included_categories']),
        ));
    } elseif ($rule_method === 'product_ids' && !empty($active_rule['included_products'])) {
        $hero_args['post__in'] = array_map('intval', (array) $active_rule['included_products']);
        $hero_args['orderby'] = 'post__in';
    }
    $hero_ids = get_posts($hero_args);
    if ($hero_ids) {
        $hero_product = wc_get_product($hero_ids[0]);
    }
}
if (!$hero_product) {
    $any_ids = get_posts(array('post_type' => 'product', 'post_status' => 'publish', 'posts_per_page' => 1, 'fields' => 'ids'));
    if ($any_ids) {
        $hero_product = wc_get_product($any_ids[0]);
    }
}

$hero_name = $hero_product ? $hero_product->get_name() : __( 'Sample product', 'incentiv' );
$hero_image = $hero_product ? get_the_post_thumbnail_url($hero_product->get_id(), 'medium') : '';
$sample_price = ($hero_product && floatval($hero_product->get_price()) > 0) ? floatval($hero_product->get_price()) : 42.00;

// Two more products for the card-grid preview
$card_products = array();
$card_ids = get_posts(array(
    'post_type' => 'product',
    'post_status' => 'publish',
    'posts_per_page' => 2,
    'fields' => 'ids',
    'post__not_in' => $hero_product ? array($hero_product->get_id()) : array(),
));
foreach ($card_ids as $cid) {
    $cp = wc_get_product($cid);
    if ($cp) {
        $card_products[] = array(
            'name' => $cp->get_name(),
            'price' => floatval($cp->get_price()),
            'image' => get_the_post_thumbnail_url($cid, 'medium'),
        );
    }
}

// Gift products from the first enabled gift rule
$gift_items = array();
if (class_exists('MMD_Gift_Rules')) {
    foreach ((array) MMD_Gift_Rules::get_instance()->get_rule_sets() as $gr) {
        if (empty($gr['enabled']) || $gr['enabled'] !== 'yes' || empty($gr['gift_products'])) {
            continue;
        }
        foreach (array_slice((array) $gr['gift_products'], 0, 2) as $gid) {
            $gp = wc_get_product($gid);
            if ($gp) {
                $gift_items[] = array(
                    'name' => $gp->get_name(),
                    'price' => floatval($gp->get_price()),
                    'image' => get_the_post_thumbnail_url($gid, 'thumbnail'),
                );
            }
        }
        if ($gift_items) {
            break;
        }
    }
}
if (!$gift_items) {
    $gift_items = array(
        array('name' => __( 'Ceramic mug', 'incentiv' ), 'price' => 45, 'image' => ''),
        array('name' => __( 'Thermal jug 500 ml', 'incentiv' ), 'price' => 89, 'image' => ''),
    );
}

// Tier helpers
$tier_line = function ($tier) use ($sample_price) {
    $qty = intval($tier['quantity']);
    $disc = floatval($tier['discount']);
    if ($tier['type'] === 'fixed') {
        return array($qty . ( ' ' . __( 'units', 'incentiv' ) ), __( 'A discount of ₪', 'incentiv' ) . $disc . ( ' ' . __( '· on the cheapest', 'incentiv' ) ));
    }
    if ($disc >= 100) {
        return array($qty . ( ' ' . __( 'units', 'incentiv' ) ), __( 'Free! · 100% savings 🎉', 'incentiv' ));
    }
    $price = $sample_price * (1 - $disc / 100);
    return array($qty . ( ' ' . __( 'units', 'incentiv' ) ), '₪ ' . number_format($price, 2) . ( ' ' . __( '· You save', 'incentiv' ) . ' ' ) . $disc . '%');
};

$max_disc = 0;
foreach ($tiers as $t) {
    $max_disc = max($max_disc, floatval($t['discount']));
}
$first_tier = reset($tiers);
$last_tier = end($tiers);
?>

<div class="wrap mmd-admin-wrap">
    <?php include MMD_PLUGIN_DIR . 'templates/admin-nav-tabs.php'; ?>

    <div class="mmd-page-head">
        <div>
            <h2 class="mmd-page-title"><?php esc_html_e( 'Customer view', 'incentiv' ); ?></h2>
            <div class="mmd-page-sub"><?php esc_html_e( 'This is how customers see the discounts', 'incentiv' ); ?><?php echo $active_rule ? ' ' . esc_html__( '· Based on the rule "', 'incentiv' ) . esc_html($rule_name) . '"' : ' ' . esc_html__( '· Sample view (no active rule)', 'incentiv' ); ?></div>
        </div>
    </div>

    <div class="mmd-front-grid">

        <!-- Right column: promo bar + product page + product cards -->
        <div class="mmd-front-col">
            <div>
                <div class="mmd-front-label"><?php echo wp_kses_post( __( 'Site-wide promo bar', 'incentiv' ) ); ?></div>
                <div class="mmd-promo-bar" style="<?php echo $promo_bg ? 'background:' . esc_attr($promo_bg) . ';' : ''; ?>color:<?php echo esc_attr($promo_color); ?>;">
                    <span class="msr">local_shipping</span><?php echo esc_html($promo_text); ?>
                </div>
            </div>

            <div>
                <div class="mmd-front-label"><?php echo wp_kses_post( __( 'Product page &middot; tier table', 'incentiv' ) ); ?></div>
                <div class="mmd-preview-product-card" style="box-shadow:var(--mmd-shadow-sm);">
                    <div style="display:flex;gap:14px;padding:16px;">
                        <div style="width:90px;height:90px;border-radius:10px;background:linear-gradient(135deg,#eef1f6,#e3e8ef);display:flex;align-items:center;justify-content:center;flex:none;position:relative;overflow:hidden;">
                            <?php if ($hero_image): ?>
                                <img src="<?php echo esc_url($hero_image); ?>" alt="" style="width:100%;height:100%;object-fit:cover;" />
                            <?php else: ?>
                                <span class="msr" style="font-size:30px;color:#c3cad6;">image</span>
                            <?php endif; ?>
                            <span style="position:absolute;top:6px;right:6px;background:<?php echo esc_attr($badge_bg); ?>;color:<?php echo esc_attr($badge_color); ?>;font-size:9px;font-weight:800;padding:2px 6px;border-radius:5px;">-<?php echo esc_html(intval($first_tier['discount'])); ?>%</span>
                        </div>
                        <div style="flex:1;">
                            <div style="font-weight:700;color:var(--mmd-ink);font-size:15px;"><?php echo esc_html($hero_name); ?></div>
                            <div style="display:flex;align-items:baseline;gap:8px;margin-top:6px;">
                                <span style="font-size:20px;font-weight:800;color:var(--mmd-ink);"><?php echo wp_kses_post(wc_price($sample_price)); ?></span>
                                <span style="font-size:13px;color:var(--mmd-text-light);"><?php esc_html_e( 'per unit', 'incentiv' ); ?></span>
                            </div>
                            <div style="display:inline-flex;align-items:center;gap:4px;font-size:11.5px;font-weight:700;color:var(--mmd-success);background:var(--mmd-success-light);padding:3px 8px;border-radius:20px;margin-top:8px;">
                                <span class="msr" style="font-size:13px;">savings</span><?php esc_html_e( 'To', 'incentiv' ); ?> <?php echo esc_html(intval($max_disc)); ?><?php esc_html_e( '% quantity discount', 'incentiv' ); ?>
                            </div>
                        </div>
                    </div>
                    <div style="border-top:1px solid var(--mmd-border-light);">
                        <div class="mmd-preview-tiers" style="border:none;border-radius:0;">
                            <div class="mmd-preview-tiers-head"><span class="msr">stairs</span><?php echo esc_html_x( 'Buy more, save more', 'preview', 'incentiv' ); ?></div>
                            <?php foreach ($tiers as $i => $tier):
                                list($qty_label, $val_label) = $tier_line($tier);
                                $is_best = ($i === count($tiers) - 1);
                            ?>
                            <div class="mmd-preview-tier-row <?php echo $is_best ? 'mmd-tier-best' : ''; ?>">
                                <span class="mmd-tier-qty"><?php echo esc_html($qty_label); ?></span>
                                <span class="mmd-tier-val"><?php echo esc_html($val_label); ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div>
                <div class="mmd-front-label"><?php echo wp_kses_post( __( 'Product cards &middot; discount badge', 'incentiv' ) ); ?></div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <?php
                    $card_defaults = array(
                        array('name' => __( 'Espresso blend', 'incentiv' ), 'price' => 38, 'image' => ''),
                        array('name' => __( 'Tasting pack', 'incentiv' ), 'price' => 120, 'image' => ''),
                    );
                    $cards_to_show = $card_products ? $card_products : $card_defaults;
                    foreach (array_slice($cards_to_show, 0, 2) as $ci => $card):
                    ?>
                    <div class="mmd-product-mini-card">
                        <div class="mmd-product-mini-thumb" style="overflow:hidden;">
                            <?php if (!empty($card['image'])): ?>
                                <img src="<?php echo esc_url($card['image']); ?>" alt="" style="width:100%;height:100%;object-fit:cover;" />
                            <?php else: ?>
                                <span class="msr">image</span>
                            <?php endif; ?>
                            <?php if ($ci === 0): ?>
                                <span class="mmd-thumb-badge" style="background:<?php echo esc_attr($badge_bg); ?>;color:<?php echo esc_attr($badge_color); ?>;"><?php echo esc_html($badge_text); ?></span>
                            <?php else: ?>
                                <span class="mmd-thumb-badge mmd-thumb-badge-purple"><?php esc_html_e( '🎁 Gift', 'incentiv' ); ?></span>
                            <?php endif; ?>
                        </div>
                        <div style="font-size:13px;font-weight:600;color:var(--mmd-ink);"><?php echo esc_html($card['name']); ?></div>
                        <div style="font-size:13px;font-weight:800;color:var(--mmd-ink);margin-top:2px;"><?php echo wp_kses_post(wc_price($card['price'])); ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Left column: cart progress + gift picker + mini cart -->
        <div class="mmd-front-col">
            <div>
                <div class="mmd-front-label"><?php echo wp_kses_post( __( 'Cart &middot; progress to the next tier', 'incentiv' ) ); ?></div>
                <div class="mmd-progress-card">
                    <div class="mmd-progress-title">
                        <span class="msr">bolt</span>
                        <?php if (floatval($last_tier['discount']) >= 100 && $last_tier['type'] !== 'fixed'): ?>
                            <?php esc_html_e( 'One more unit and the cheapest is', 'incentiv' ); ?> <span style="color:var(--mmd-purple);"><?php esc_html_e( 'Free!', 'incentiv' ); ?></span>
                        <?php else: ?>
                            <?php esc_html_e( 'One more unit to the next discount tier!', 'incentiv' ); ?>
                        <?php endif; ?>
                    </div>
                    <div class="mmd-progressbar"><span style="width:80%;"></span></div>
                    <div class="mmd-progress-steps">
                        <?php foreach ($tiers as $i => $tier):
                            $is_last = ($i === count($tiers) - 1);
                            $label = intval($tier['quantity']) . ( ' ' . __( 'pcs ·', 'incentiv' ) . ' ' ) . ($tier['type'] === 'fixed' ? '₪' . floatval($tier['discount']) : (floatval($tier['discount']) >= 100 ? __( 'Free', 'incentiv' ) : intval($tier['discount']) . '%'));
                        ?>
                            <span class="<?php echo $is_last ? 'mmd-progress-final' : ''; ?>"><?php echo esc_html($label); ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div>
                <div class="mmd-front-label"><?php echo wp_kses_post( __( 'Gift picker', 'incentiv' ) ); ?></div>
                <div class="mmd-gift-widget-preview" style="background:linear-gradient(135deg,<?php echo esc_attr($gw_bg1); ?> 0%,<?php echo esc_attr($gw_bg2); ?> 100%);">
                    <div class="mmd-gw-title"><?php echo esc_html($gw_title); ?></div>
                    <div class="mmd-gw-sub"><?php echo esc_html($gw_subtitle); ?></div>
                    <div class="mmd-gw-list">
                        <?php foreach (array_slice($gift_items, 0, 2) as $gi => $gift):
                            $selected = ($gi === 1);
                        ?>
                        <div class="mmd-gw-item <?php echo $selected ? 'mmd-gw-selected' : ''; ?>">
                            <span class="mmd-gw-thumb" style="overflow:hidden;">
                                <?php if ($selected): ?>
                                    <span class="msr">check_circle</span>
                                <?php elseif (!empty($gift['image'])): ?>
                                    <img src="<?php echo esc_url($gift['image']); ?>" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:8px;" />
                                <?php else: ?>
                                    <span class="msr">image</span>
                                <?php endif; ?>
                            </span>
                            <div class="mmd-gw-name"><?php echo esc_html($gift['name']); ?><small><?php echo $selected ? esc_html__( 'Selected ·', 'incentiv' ) . ' ' : ''; ?><?php esc_html_e( 'Value', 'incentiv' ); ?> <?php echo wp_kses_post(wc_price($gift['price'])); ?></small></div>
                            <?php if ($selected): ?>
                                <button type="button" class="mmd-gw-btn"><?php esc_html_e( 'Selected ✓', 'incentiv' ); ?></button>
                            <?php else: ?>
                                <button type="button" class="mmd-gw-btn" style="background:<?php echo esc_attr($gw_bg1); ?>;"><?php esc_html_e( 'Choose', 'incentiv' ); ?></button>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div>
                <div class="mmd-front-label"><?php echo wp_kses_post( __( 'Cart notice', 'incentiv' ) ); ?></div>
                <div class="mmd-preview-cartmsg">
                    <div class="mmd-preview-cartmsg-title"><span class="msr">shopping_cart</span><?php esc_html_e( 'Cart message', 'incentiv' ); ?></div>
                    <div class="mmd-lp-cartmsg-box mmd-preview-notice" style="background:<?php echo esc_attr($notice_color); ?>;">
                        <strong style="display:block;margin-bottom:3px;"><?php echo esc_html($notice_title); ?></strong>
                        <?php echo wp_kses_post($notice_content); ?>
                    </div>
                </div>
            </div>

            <div>
                <div class="mmd-front-label"><?php echo wp_kses_post( __( 'Mini cart', 'incentiv' ) ); ?></div>
                <div class="mmd-minicart-preview">
                    <div class="mmd-minicart-item">
                        <span class="mmd-gw-thumb" style="width:40px;height:40px;overflow:hidden;"><?php if ($hero_image): ?><img src="<?php echo esc_url($hero_image); ?>" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:8px;" /><?php else: ?><span class="msr" style="font-size:20px;">image</span><?php endif; ?></span>
                        <div style="flex:1;">
                            <div style="font-size:13px;font-weight:600;color:var(--mmd-ink);"><?php echo esc_html($hero_name); ?> ×<?php echo esc_html(intval($last_tier['quantity'])); ?></div>
                            <span class="mmd-mini-badge"><?php echo esc_html($discount_label); ?></span>
                        </div>
                        <div style="text-align:left;">
                            <?php
                            $qty = max(1, intval($last_tier['quantity']));
                            $full = $sample_price * $qty;
                            $disc_val = floatval($last_tier['discount']);
                            $savings = $last_tier['type'] === 'fixed' ? $disc_val : $sample_price * min(100, $disc_val) / 100;
                            ?>
                            <div class="mmd-price-strike"><?php echo wp_kses_post(wc_price($full)); ?></div>
                            <div style="font-size:14px;font-weight:800;color:var(--mmd-ink);"><?php echo wp_kses_post(wc_price($full - $savings)); ?></div>
                        </div>
                    </div>
                    <div class="mmd-minicart-total">
                        <span><?php echo esc_html_x( 'You saved', 'preview', 'incentiv' ); ?></span>
                        <strong><?php echo wp_kses_post(wc_price($savings)); ?></strong>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>
