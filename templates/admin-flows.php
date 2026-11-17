<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template included from a class method; its variables are local to that method, not globals.
/**
 * Flow Builder — "זרימות"
 * Visual customer-journey view of every promotion + interactive flow canvas.
 */

if (!defined('ABSPATH')) {
    exit;
}

$flows = INCV_Flow_Builder::get_existing_flows();

$kind_colors = array(
    'quantity' => 'incv-flownode-quantity',
    'gift' => 'incv-flownode-gift',
    'event' => 'incv-flownode-event',
    'coupon' => 'incv-flownode-coupon',
    'success' => 'incv-flownode-success',
    'interaction' => 'incv-flownode-interaction',
    'neutral' => '',
);
?>

<div class="wrap incv-admin-wrap">
    <?php include INCV_PLUGIN_DIR . 'templates/admin-nav-tabs.php'; ?>

    <div class="incv-page-head">
        <div>
            <h2 class="incv-page-title"><?php esc_html_e( 'Promotion flows', 'incentiv' ); ?></h2>
            <div class="incv-page-sub"><?php esc_html_e( 'See and build the customer\'s discount journey, step by step', 'incentiv' ); ?></div>
        </div>
        <a href="#incv-flow-canvas-card" class="incv-btn incv-btn-brand" id="incv-new-flow-btn">
            <span class="msr">account_tree</span><?php esc_html_e( 'New flow', 'incentiv' ); ?>
        </a>
    </div>

    <!-- ================= Existing flows ================= -->
    <?php if (empty($flows)): ?>
        <div class="incv-empty-state">
            <p><?php esc_html_e( 'No flows yet. Create your first flow in the builder below, or start from a discount rule.', 'incentiv' ); ?></p>
        </div>
    <?php else: ?>
        <div style="display:flex;flex-direction:column;gap:14px;margin-bottom:26px;">
            <?php foreach ($flows as $flow): ?>
            <div class="incv-card incv-flow-card" style="margin-bottom:0;">
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:14px;">
                    <?php if ($flow['enabled']): ?>
                        <span class="incv-pill incv-pill-success"><span class="incv-pill-dot"></span><?php esc_html_e( 'Active', 'incentiv' ); ?></span>
                    <?php else: ?>
                        <span class="incv-pill incv-pill-muted"><span class="incv-pill-dot"></span><?php esc_html_e( 'Disabled', 'incentiv' ); ?></span>
                    <?php endif; ?>
                    <strong style="font-size:14.5px;color:var(--incv-ink);"><?php echo esc_html($flow['name']); ?></strong>
                    <span style="flex:1;"></span>
                    <a class="incv-icon-btn" title="<?php esc_attr_e( 'Edit', 'incentiv' ); ?>" href="<?php echo esc_url($flow['edit_url']); ?>"><span class="msr">edit</span></a>
                </div>
                <div class="incv-flow">
                    <div class="incv-flow-node incv-flownode-start">
                        <span class="msr">person</span>
                        <div><div class="incv-flownode-title"><?php esc_html_e( 'Customer', 'incentiv' ); ?></div><div class="incv-flownode-sub"><?php esc_html_e( 'In the store', 'incentiv' ); ?></div></div>
                    </div>
                    <?php foreach ($flow['nodes'] as $node): list($icon, $kind, $title, $sub) = $node; ?>
                        <span class="incv-flow-arrow"><span class="msr incv-dir-icon">arrow_back</span></span>
                        <div class="incv-flow-node <?php echo esc_attr($kind_colors[$kind] ?? ''); ?>">
                            <span class="msr"><?php echo esc_html($icon); ?></span>
                            <div>
                                <div class="incv-flownode-title"><?php echo esc_html($title); ?></div>
                                <?php if ($sub): ?><div class="incv-flownode-sub"><?php echo esc_html($sub); ?></div><?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <span class="incv-flow-arrow"><span class="msr incv-dir-icon">arrow_back</span></span>
                    <div class="incv-flow-node incv-flownode-end">
                        <span class="msr">check_circle</span>
                        <div><div class="incv-flownode-title"><?php esc_html_e( 'A fuller cart', 'incentiv' ); ?></div><div class="incv-flownode-sub"><?php esc_html_e( 'Every reason to buy more', 'incentiv' ); ?></div></div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- ================= Flow canvas (builder) ================= -->
    <div class="incv-card" id="incv-flow-canvas-card" style="margin-bottom:0;">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;">
            <span class="msr" style="font-size:20px;color:var(--incv-primary);">account_tree</span>
            <h3 style="margin:0;font-size:15.5px;font-weight:800;color:var(--incv-ink);"><?php esc_html_e( 'Flow builder', 'incentiv' ); ?></h3>
        </div>
        <div style="font-size:12.5px;color:var(--incv-text-muted);margin-bottom:16px;">
            <?php esc_html_e( 'Build the customer journey from steps: triggers (what the customer does), interactions and actions (what they get). When you\'re done, Incentiv creates the rules, gifts and coupon automatically.', 'incentiv' ); ?>
        </div>

        <!-- Node palette -->
        <div class="incv-flow-palette">
            <span style="font-size:11.5px;font-weight:700;color:var(--incv-text-faint);text-transform:uppercase;letter-spacing:.3px;"><?php esc_html_e( 'Triggers', 'incentiv' ); ?></span>
            <button type="button" class="incv-chip incv-flow-add" data-node="trigger_qty"><span class="msr" style="font-size:15px;">shopping_cart</span> <?php esc_html_e( 'Product quantity', 'incentiv' ); ?></button>
            <button type="button" class="incv-chip incv-flow-add" data-node="trigger_total"><span class="msr" style="font-size:15px;">payments</span> <?php esc_html_e( 'Cart total', 'incentiv' ); ?></button>
            <button type="button" class="incv-chip incv-flow-add" data-node="trigger_coupon"><span class="msr" style="font-size:15px;">confirmation_number</span> <?php esc_html_e( 'Coupon code', 'incentiv' ); ?></button>
            <button type="button" class="incv-chip incv-flow-add" data-node="trigger_role"><span class="msr" style="font-size:15px;">group</span> <?php esc_html_e( 'Customer type', 'incentiv' ); ?></button>
            <span style="font-size:11.5px;font-weight:700;color:var(--incv-text-faint);text-transform:uppercase;letter-spacing:.3px;margin-right:10px;"><?php esc_html_e( 'Actions', 'incentiv' ); ?></span>
            <button type="button" class="incv-chip incv-flow-add" data-node="action_discount"><span class="msr" style="font-size:15px;">percent</span> <?php esc_html_e( 'Discount', 'incentiv' ); ?></button>
            <button type="button" class="incv-chip incv-flow-add" data-node="action_gift_choice"><span class="msr" style="font-size:15px;">touch_app</span> <?php esc_html_e( 'Gift chosen by the customer', 'incentiv' ); ?></button>
            <button type="button" class="incv-chip incv-flow-add" data-node="action_gift_auto"><span class="msr" style="font-size:15px;">card_giftcard</span> <?php esc_html_e( 'Automatic gift', 'incentiv' ); ?></button>
        </div>
        <div class="incv-card-tip" style="margin-bottom:14px;">
            <?php echo wp_kses_post( __( '<strong>Tip:</strong> add two or more actions to split the flow into parallel paths (for example: a discount <em>and</em> a gift, or different discount tiers). The canvas scrolls sideways and down; drag the bottom corner to make it bigger.', 'incentiv' ) ); ?>
        </div>

        <!-- Canvas -->
        <div id="incv-flow-canvas" class="incv-flow incv-flow-canvas">
            <div class="incv-flow-node incv-flownode-start">
                <span class="msr">person</span>
                <div><div class="incv-flownode-title"><?php esc_html_e( 'Customer', 'incentiv' ); ?></div><div class="incv-flownode-sub"><?php esc_html_e( 'In the store', 'incentiv' ); ?></div></div>
            </div>
            <div id="incv-flow-empty" style="color:var(--incv-text-light);font-size:13px;padding:14px;"><?php esc_html_e( 'Add steps from the palette above ↑', 'incentiv' ); ?></div>
        </div>

        <!-- Config panel (below canvas, always has room) -->
        <div id="incv-flow-config-panel" class="incv-flow-config-panel" style="display:none;">
            <div class="incv-flow-config-head">
                <span class="msr" id="incv-flow-config-icon">tune</span>
                <span id="incv-flow-config-title"><?php esc_html_e( 'Step settings', 'incentiv' ); ?></span>
                <span style="flex:1;"></span>
                <button type="button" class="incv-icon-btn" id="incv-flow-config-close" title="<?php esc_attr_e( 'Close', 'incentiv' ); ?>"><span class="msr">close</span></button>
            </div>
            <div id="incv-flow-config-body" class="incv-flow-config-body"></div>
        </div>

        <!-- Footer: name + create -->
        <div style="display:flex;gap:10px;align-items:flex-end;margin-top:16px;flex-wrap:wrap;">
            <div style="flex:1;min-width:220px;">
                <label class="incv-form-label" for="incv-flow-name"><?php esc_html_e( 'Flow name', 'incentiv' ); ?></label>
                <input type="text" id="incv-flow-name" placeholder="<?php esc_attr_e( 'e.g. Buy 3 coffees, the cheapest is free + a gift', 'incentiv' ); ?>" style="width:100%;padding:10px 12px;border:1px solid var(--incv-border-input);border-radius:9px;font-size:14px;" />
            </div>
            <button type="button" class="incv-btn incv-btn-green" id="incv-flow-create" style="padding:11px 22px;">
                <span class="msr">check</span><?php esc_html_e( 'Create flow', 'incentiv' ); ?>
            </button>
        </div>

        <div id="incv-flow-result" style="display:none;margin-top:16px;"></div>
    </div>
</div>
