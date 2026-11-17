<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template included from a class method; its variables are local to that method, not globals.
/**
 * Rule Creation Wizard Template
 * Step-by-step wizard with pointers for creating discount rules
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div id="incv-wizard-overlay" class="incv-wizard-overlay" style="display:none;">
    <div class="incv-wizard-backdrop"></div>
    <div class="incv-wizard-dialog">
        <button type="button" class="incv-wizard-close" title="<?php esc_attr_e('Close wizard', 'incentiv'); ?>">&times;</button>

        <div class="incv-wizard-header">
            <h2 id="incv-wizard-title"></h2>
            <div class="incv-wizard-sub"><?php echo esc_html__('Create a discount rule step by step', 'incentiv'); ?></div>
        </div>

        <div class="incv-wizard-steps">
            <div class="incv-wizard-step" data-step="1"><span class="incv-step-num">1</span><span class="incv-step-label"><?php echo esc_html__('Discount type', 'incentiv'); ?></span></div>
            <div class="incv-wizard-step" data-step="2"><span class="incv-step-num">2</span><span class="incv-step-label"><?php echo esc_html__('Products', 'incentiv'); ?></span></div>
            <div class="incv-wizard-step" data-step="3"><span class="incv-step-num">3</span><span class="incv-step-label"><?php echo esc_html__('Discount tiers', 'incentiv'); ?></span></div>
            <div class="incv-wizard-step" data-step="4"><span class="incv-step-num">4</span><span class="incv-step-label"><?php echo esc_html__('Summary', 'incentiv'); ?></span></div>
        </div>

        <div class="incv-wizard-body" id="incv-wizard-body">
            <!-- Step content injected by JS -->
        </div>

        <div class="incv-wizard-footer">
            <button type="button" class="button incv-wizard-btn-prev" id="incv-wizard-prev" style="display:none;">
                <span class="incv-dir-icon" aria-hidden="true">&rarr;</span> <?php echo esc_html__('Previous', 'incentiv'); ?>
            </button>
            <div class="incv-wizard-footer-spacer"></div>
            <button type="button" class="button button-primary incv-wizard-btn-next" id="incv-wizard-next">
                <?php echo esc_html__('Continue', 'incentiv'); ?> <span class="incv-dir-icon" aria-hidden="true">&larr;</span>
            </button>
            <button type="button" class="button button-primary incv-wizard-btn-finish" id="incv-wizard-finish" style="display:none;">
                ✓ <?php echo esc_html__('Create rule', 'incentiv'); ?>
            </button>
        </div>
    </div>
</div>

<!-- Pointer tooltip for highlighting fields -->
<div id="incv-pointer" class="incv-pointer" style="display:none;">
    <div class="incv-pointer-content" id="incv-pointer-content"></div>
    <div class="incv-pointer-arrow"></div>
</div>
