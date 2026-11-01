<?php
/**
 * Rule Creation Wizard Template
 * Step-by-step wizard with pointers for creating discount rules
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div id="mmd-wizard-overlay" class="mmd-wizard-overlay" style="display:none;">
    <div class="mmd-wizard-backdrop"></div>
    <div class="mmd-wizard-dialog">
        <button type="button" class="mmd-wizard-close" title="<?php esc_attr_e('Close wizard', 'incentiv'); ?>">&times;</button>

        <div class="mmd-wizard-header">
            <h2 id="mmd-wizard-title"></h2>
            <div class="mmd-wizard-sub"><?php echo esc_html__('Create a discount rule step by step', 'incentiv'); ?></div>
        </div>

        <div class="mmd-wizard-steps">
            <div class="mmd-wizard-step" data-step="1"><span class="mmd-step-num">1</span><span class="mmd-step-label"><?php echo esc_html__('Discount type', 'incentiv'); ?></span></div>
            <div class="mmd-wizard-step" data-step="2"><span class="mmd-step-num">2</span><span class="mmd-step-label"><?php echo esc_html__('Products', 'incentiv'); ?></span></div>
            <div class="mmd-wizard-step" data-step="3"><span class="mmd-step-num">3</span><span class="mmd-step-label"><?php echo esc_html__('Discount tiers', 'incentiv'); ?></span></div>
            <div class="mmd-wizard-step" data-step="4"><span class="mmd-step-num">4</span><span class="mmd-step-label"><?php echo esc_html__('Summary', 'incentiv'); ?></span></div>
        </div>

        <div class="mmd-wizard-body" id="mmd-wizard-body">
            <!-- Step content injected by JS -->
        </div>

        <div class="mmd-wizard-footer">
            <button type="button" class="button mmd-wizard-btn-prev" id="mmd-wizard-prev" style="display:none;">
                <span class="mmd-dir-icon" aria-hidden="true">&rarr;</span> <?php echo esc_html__('Previous', 'incentiv'); ?>
            </button>
            <div class="mmd-wizard-footer-spacer"></div>
            <button type="button" class="button button-primary mmd-wizard-btn-next" id="mmd-wizard-next">
                <?php echo esc_html__('Continue', 'incentiv'); ?> <span class="mmd-dir-icon" aria-hidden="true">&larr;</span>
            </button>
            <button type="button" class="button button-primary mmd-wizard-btn-finish" id="mmd-wizard-finish" style="display:none;">
                ✓ <?php echo esc_html__('Create rule', 'incentiv'); ?>
            </button>
        </div>
    </div>
</div>

<!-- Pointer tooltip for highlighting fields -->
<div id="mmd-pointer" class="mmd-pointer" style="display:none;">
    <div class="mmd-pointer-content" id="mmd-pointer-content"></div>
    <div class="mmd-pointer-arrow"></div>
</div>
