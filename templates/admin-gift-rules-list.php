<?php
/**
 * Admin Gift Rules List Page (v1.9 redesign)
 */

if (!defined('ABSPATH')) {
    exit;
}

$gift_styling = MMD_Gift_Rules::get_instance()->get_styling_options();
$gw_title = !empty($gift_styling['gift_box_title_text']) ? $gift_styling['gift_box_title_text'] : _x( '🎁 Choose your gift!', 'rule', 'incentiv' );
$gw_bg1 = !empty($gift_styling['gift_box_bg_color_1']) ? $gift_styling['gift_box_bg_color_1'] : '#7c3aed';
$gw_bg2 = !empty($gift_styling['gift_box_bg_color_2']) ? $gift_styling['gift_box_bg_color_2'] : '#a855f7';
$gw_btn_bg = !empty($gift_styling['gift_button_bg_color']) ? $gift_styling['gift_button_bg_color'] : '#7c3aed';
$gw_btn_color = !empty($gift_styling['gift_button_text_color']) ? $gift_styling['gift_button_text_color'] : '#ffffff';

$selection_method_labels = array(
    'all' => __( 'All products', 'incentiv' ),
    'product_ids' => __( 'Specific products', 'incentiv' ),
    'category' => __( 'Categories', 'incentiv' ),
    'attribute' => __( 'Product attributes', 'incentiv' ),
);

$gift_type_labels = array(
    'free' => __( 'Free gift', 'incentiv' ),
    'discount' => __( 'Percentage discount', 'incentiv' ),
    'fixed_discount' => __( 'Fixed discount', 'incentiv' ),
);
?>

<div class="wrap mmd-admin-wrap">
    <?php include dirname(__FILE__) . '/admin-nav-tabs.php'; ?>

    <div class="mmd-page-head">
        <div>
            <h2 class="mmd-page-title"><?php esc_html_e( 'Gift management', 'incentiv' ); ?></h2>
            <div class="mmd-page-sub"><?php esc_html_e( 'Buy-and-get gift', 'incentiv' ); ?></div>
        </div>
        <div class="mmd-head-actions">
            <a href="<?php echo esc_url(admin_url('admin.php?page=mmd-gift-rules&action=styling')); ?>" class="mmd-btn mmd-btn-outline">
                <span class="msr">palette</span><?php esc_html_e( 'Design settings', 'incentiv' ); ?>
            </a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=mmd-gift-rules&action=new')); ?>" class="mmd-btn mmd-btn-outline">
                <span class="msr">auto_awesome</span><?php esc_html_e( 'Wizard', 'incentiv' ); ?>
            </a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=mmd-gift-rules&action=new&skip_wizard=1')); ?>" class="mmd-btn mmd-btn-purple">
                <span class="msr">add</span><?php esc_html_e( 'New gift rule', 'incentiv' ); ?>
            </a>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:1.4fr 1fr;gap:18px;align-items:start;" class="mmd-gifts-layout">

        <!-- Gift rules list -->
        <div style="display:flex;flex-direction:column;gap:14px;min-width:0;">
            <?php if (empty($rule_sets)): ?>
                <div class="mmd-empty-state">
                    <p><?php esc_html_e( 'No gift rules found. Create your first rule to get started!', 'incentiv' ); ?></p>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=mmd-gift-rules&action=new')); ?>" class="mmd-btn mmd-btn-purple">
                        <span class="msr">add</span><?php esc_html_e( 'Add gift rule', 'incentiv' ); ?>
                    </a>
                </div>
            <?php else: ?>
                <?php foreach ($rule_sets as $rule_set):
                    $is_draft = isset($rule_set['status']) && $rule_set['status'] === 'draft';
                    $enabled = !$is_draft && isset($rule_set['enabled']) && $rule_set['enabled'] === 'yes';
                    $selection_method = isset($rule_set['selection_method']) ? $rule_set['selection_method'] : 'all';
                    $gift_type = isset($rule_set['gift_type']) ? $rule_set['gift_type'] : 'free';
                    $gift_source = isset($rule_set['gift_source']) ? $rule_set['gift_source'] : 'specific';
                    $gift_mode = isset($rule_set['gift_mode']) ? $rule_set['gift_mode'] : 'product_page';

                    if ($gift_source === 'specific') {
                        $gift_products_count = isset($rule_set['gift_products']) ? count($rule_set['gift_products']) : 0;
                        $source_label = $gift_products_count . ( ' ' . __( 'Available gift products', 'incentiv' ) );
                    } else {
                        $gift_categories = isset($rule_set['gift_categories']) ? $rule_set['gift_categories'] : array();
                        /* translators: %d: number of gift categories. */
                        $source_label = sprintf( __( 'Gifts from %d categories', 'incentiv' ), count($gift_categories) );
                    }

                    $mode_label = ($gift_mode === 'cart_based') ? __( 'Automatic gift', 'incentiv' ) : __( 'Customer choice', 'incentiv' );
                ?>
                <div class="mmd-card mmd-rule-card" style="margin-bottom:0;padding:18px;">
                    <div style="display:flex;align-items:center;gap:12px;">
                        <span style="width:44px;height:44px;border-radius:11px;background:<?php echo $enabled ? 'var(--mmd-grad-purple)' : 'var(--mmd-bg-chip)'; ?>;display:flex;align-items:center;justify-content:center;flex:none;">
                            <span class="msr" style="font-size:23px;color:<?php echo $enabled ? '#fff' : 'var(--mmd-text-light)'; ?>;"><?php echo $gift_mode === 'cart_based' ? 'card_giftcard' : 'redeem'; ?></span>
                        </span>
                        <div style="flex:1;min-width:0;">
                            <div style="font-weight:700;color:var(--mmd-ink);font-size:15px;"><?php echo esc_html($rule_set['name']); ?></div>
                            <div style="font-size:12.5px;color:var(--mmd-text-muted);">
                                <?php esc_html_e( 'Mode:', 'incentiv' ); ?> <?php echo esc_html($mode_label); ?>
                                &middot; <?php echo esc_html($source_label); ?>
                                &middot; <?php echo esc_html($gift_type_labels[$gift_type] ?? $gift_type); ?>
                                &middot; <?php echo esc_html($selection_method_labels[$selection_method] ?? $selection_method); ?>
                            </div>
                        </div>
                        <?php if ($is_draft): ?>
                            <span class="mmd-pill mmd-pill-draft"><span class="mmd-pill-dot"></span><?php esc_html_e( 'Draft', 'incentiv' ); ?></span>
                        <?php elseif ($enabled): ?>
                            <span class="mmd-pill mmd-pill-success"><span class="mmd-pill-dot"></span><?php esc_html_e( 'Active', 'incentiv' ); ?></span>
                        <?php else: ?>
                            <span class="mmd-pill mmd-pill-muted"><span class="mmd-pill-dot"></span><?php esc_html_e( 'Disabled', 'incentiv' ); ?></span>
                        <?php endif; ?>
                        <div class="mmd-row-actions">
                            <a class="mmd-icon-btn" title="<?php esc_attr_e( 'Edit', 'incentiv' ); ?>"
                               href="<?php echo esc_url(admin_url('admin.php?page=mmd-gift-rules&action=edit&rule_id=' . urlencode($rule_set['id']))); ?>">
                                <span class="msr">edit</span>
                            </a>
                            <button type="button" class="mmd-icon-btn mmd-duplicate-rule" title="<?php esc_attr_e( 'Duplicate', 'incentiv' ); ?>"
                                    data-rule-id="<?php echo esc_attr($rule_set['id']); ?>">
                                <span class="msr">content_copy</span>
                            </button>
                            <button type="button" class="mmd-icon-btn mmd-icon-btn-danger mmd-delete-rule" title="<?php esc_attr_e( 'Delete', 'incentiv' ); ?>"
                                    data-rule-id="<?php echo esc_attr($rule_set['id']); ?>">
                                <span class="msr">delete</span>
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <!-- Gift grant mode explainer -->
            <div class="mmd-card" style="margin-bottom:0;">
                <div class="mmd-card-title"><span class="msr" style="color:var(--mmd-purple);">tune</span><?php esc_html_e( 'Gift award mode', 'incentiv' ); ?></div>
                <div class="mmd-select-cards mmd-select-cards-2">
                    <div class="mmd-select-card mmd-select-card-purple mmd-active" style="cursor:default;">
                        <span class="msr" style="font-size:26px;">touch_app</span>
                        <span class="mmd-select-card-label" style="font-size:13.5px;font-weight:700;"><?php esc_html_e( 'Customer choice', 'incentiv' ); ?></span>
                        <span class="mmd-select-card-hint"><?php esc_html_e( 'The customer chooses from a list', 'incentiv' ); ?></span>
                    </div>
                    <div class="mmd-select-card" style="cursor:default;">
                        <span class="msr" style="font-size:26px;">auto_awesome</span>
                        <span class="mmd-select-card-label" style="font-size:13.5px;font-weight:700;"><?php esc_html_e( 'Automatic', 'incentiv' ); ?></span>
                        <span class="mmd-select-card-hint"><?php esc_html_e( 'Added to the cart automatically', 'incentiv' ); ?></span>
                    </div>
                </div>
                <p style="font-size:12px;color:var(--mmd-text-light);margin:12px 0 0;"><?php esc_html_e( 'The mode is chosen when editing each gift rule.', 'incentiv' ); ?></p>
            </div>
        </div>

        <!-- Sticky live styling preview -->
        <div style="position:sticky;top:46px;">
            <div class="mmd-preview-label" style="margin-bottom:12px;"><span class="msr">palette</span><?php echo wp_kses_post( __( 'Widget design &middot; Live styling', 'incentiv' ) ); ?></div>
            <div class="mmd-gift-widget-preview" style="background:linear-gradient(135deg,<?php echo esc_attr($gw_bg1); ?> 0%,<?php echo esc_attr($gw_bg2); ?> 100%);">
                <div class="mmd-gw-title"><?php echo esc_html($gw_title); ?></div>
                <div class="mmd-gw-sub"><?php esc_html_e( 'Add one product at no cost', 'incentiv' ); ?></div>
                <div class="mmd-gw-list">
                    <div class="mmd-gw-item">
                        <span class="mmd-gw-thumb"><span class="msr">image</span></span>
                        <div class="mmd-gw-name"><?php esc_html_e( 'Printed ceramic mug', 'incentiv' ); ?></div>
                        <button type="button" class="mmd-gw-btn" style="background:<?php echo esc_attr($gw_btn_bg); ?>;color:<?php echo esc_attr($gw_btn_color); ?>;"><?php esc_html_e( 'Choose', 'incentiv' ); ?></button>
                    </div>
                    <div class="mmd-gw-item mmd-gw-selected">
                        <span class="mmd-gw-thumb"><span class="msr">check_circle</span></span>
                        <div class="mmd-gw-name"><?php esc_html_e( 'Thermal jug 500 ml', 'incentiv' ); ?></div>
                        <button type="button" class="mmd-gw-btn"><?php esc_html_e( 'Selected ✓', 'incentiv' ); ?></button>
                    </div>
                </div>
            </div>
            <div class="mmd-card" style="margin:14px 0 0;padding:16px;">
                <div style="font-size:12.5px;font-weight:700;color:var(--mmd-text-label);margin-bottom:10px;"><?php esc_html_e( 'Widget background colour', 'incentiv' ); ?></div>
                <?php
                $gw_presets = array(
                    array('#7c3aed', '#a855f7'),
                    array('#3a5bff', '#5b6cff'),
                    array('#17b26a', '#3fce88'),
                    array('#f79009', '#fdb022'),
                    array('#101828', '#101828'),
                );
                $gw_current = strtolower($gw_bg1) . strtolower($gw_bg2);
                ?>
                <div class="mmd-swatches" id="mmd-gw-swatches">
                    <?php foreach ($gw_presets as $preset):
                        $is_active = (strtolower($preset[0]) . strtolower($preset[1])) === $gw_current; ?>
                        <span class="mmd-swatch<?php echo $is_active ? ' active' : ''; ?>"
                              role="button" tabindex="0"
                              title="<?php esc_attr_e( 'Apply background colour', 'incentiv' ); ?>"
                              data-bg1="<?php echo esc_attr($preset[0]); ?>"
                              data-bg2="<?php echo esc_attr($preset[1]); ?>"
                              style="background:linear-gradient(135deg,<?php echo esc_attr($preset[0]); ?>,<?php echo esc_attr($preset[1]); ?>);"></span>
                    <?php endforeach; ?>
                </div>
                <div id="mmd-gw-swatch-msg" style="display:none;font-size:11.5px;color:var(--mmd-success);font-weight:700;margin-top:8px;"><span class="msr" style="font-size:14px;vertical-align:middle;">check_circle</span> <?php esc_html_e( 'Saved', 'incentiv' ); ?></div>
                <a href="<?php echo esc_url(admin_url('admin.php?page=mmd-gift-rules&action=styling')); ?>" class="mmd-btn mmd-btn-soft-purple" style="margin-top:12px;width:100%;">
                    <span class="msr">palette</span><?php esc_html_e( 'All design settings', 'incentiv' ); ?>
                </a>
            </div>
        </div>
    </div>
</div>

<style>
@media (max-width: 1100px) {
    .mmd-gifts-layout { grid-template-columns: 1fr !important; }
    .mmd-gifts-layout > div[style*="sticky"] { position: static !important; }
}
</style>

<script>
jQuery(document).ready(function($) {
    // Widget background colour swatches — apply to preview + save (merged)
    function applyGwSwatch($sw) {
        var bg1 = $sw.data('bg1');
        var bg2 = $sw.data('bg2');
        $('#mmd-gw-swatches .mmd-swatch').removeClass('active');
        $sw.addClass('active');
        $('.mmd-gift-widget-preview').css('background', 'linear-gradient(135deg,' + bg1 + ' 0%,' + bg2 + ' 100%)');

        $.ajax({
            url: (window.mmdUi && mmdUi.ajaxurl) || ajaxurl,
            type: 'POST',
            data: {
                action: 'mmd_save_gift_styling',
                nonce: mmdGiftRulesAjax.nonce,
                styling: JSON.stringify({ gift_box_bg_color_1: bg1, gift_box_bg_color_2: bg2 })
            },
            success: function(resp) {
                if (resp && resp.success) {
                    var $msg = $('#mmd-gw-swatch-msg').stop(true, true).fadeIn(120);
                    setTimeout(function() { $msg.fadeOut(400); }, 1600);
                }
            }
        });
    }
    $(document).on('click', '#mmd-gw-swatches .mmd-swatch', function() { applyGwSwatch($(this)); });
    $(document).on('keydown', '#mmd-gw-swatches .mmd-swatch', function(e) {
        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); applyGwSwatch($(this)); }
    });

    // Delete rule
    $('.mmd-delete-rule').on('click', function() {
        if (!confirm(<?php echo wp_json_encode( __( 'Are you sure you want to delete this rule?', 'incentiv' ) ); ?>)) {
            return;
        }

        var ruleId = $(this).data('rule-id');
        var $row = $(this).closest('.mmd-rule-card');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mmd_delete_gift_rule',
                nonce: mmdGiftRulesAjax.nonce,
                rule_id: ruleId
            },
            success: function(response) {
                if (response.success) {
                    $row.fadeOut(300, function() {
                        $(this).remove();
                    });
                } else {
                    alert(( <?php echo wp_json_encode( __( 'Error deleting the rule:', 'incentiv' ) ); ?> + ' ' ) + response.data);
                }
            }
        });
    });

    // Duplicate rule
    $('.mmd-duplicate-rule').on('click', function() {
        var ruleId = $(this).data('rule-id');
        var $button = $(this);

        $button.prop('disabled', true);

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mmd_duplicate_gift_rule',
                nonce: mmdGiftRulesAjax.nonce,
                rule_id: ruleId
            },
            success: function(response) {
                if (response.success) {
                    (window.mmdPjaxReload || function() { location.reload(); })();
                } else {
                    alert(( <?php echo wp_json_encode( __( 'Error duplicating the rule:', 'incentiv' ) ); ?> + ' ' ) + response.data);
                    $button.prop('disabled', false);
                }
            }
        });
    });
});
</script>
