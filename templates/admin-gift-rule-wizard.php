<?php
/**
 * Gift Rule Creation Wizard Template
 * Step-by-step wizard for creating gift rules
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div id="mmd-gift-wizard-overlay" class="mmd-wizard-overlay" style="display:none;">
    <div class="mmd-wizard-backdrop"></div>
    <div class="mmd-wizard-dialog">
        <button type="button" class="mmd-wizard-close" title="<?php esc_attr_e( 'Close wizard', 'incentiv' ); ?>">&times;</button>

        <div class="mmd-wizard-header" style="background:var(--mmd-grad-purple);">
            <h2 id="mmd-gift-wizard-title"></h2>
            <div class="mmd-wizard-sub"><?php echo wp_kses_post( __( 'Gift rule wizard &middot; Create a gift rule step by step', 'incentiv' ) ); ?></div>
        </div>

        <div class="mmd-wizard-steps">
            <div class="mmd-gift-wizard-step" data-step="1"><span class="mmd-step-num">1</span><span class="mmd-step-label"><?php esc_html_e( 'Gift type', 'incentiv' ); ?></span></div>
            <div class="mmd-gift-wizard-step" data-step="2"><span class="mmd-step-num">2</span><span class="mmd-step-label"><?php esc_html_e( 'How it\'s given', 'incentiv' ); ?></span></div>
            <div class="mmd-gift-wizard-step" data-step="3"><span class="mmd-step-num">3</span><span class="mmd-step-label"><?php esc_html_e( 'Conditions', 'incentiv' ); ?></span></div>
            <div class="mmd-gift-wizard-step" data-step="4"><span class="mmd-step-num">4</span><span class="mmd-step-label"><?php esc_html_e( 'Summary', 'incentiv' ); ?></span></div>
        </div>

        <div class="mmd-wizard-body" id="mmd-gift-wizard-body">
            <!-- Step content injected by JS -->
        </div>

        <div class="mmd-wizard-footer">
            <button type="button" class="button mmd-wizard-btn-prev" id="mmd-gift-wizard-prev" style="display:none;">
                <span class="mmd-dir-icon" aria-hidden="true">&rarr;</span> <?php esc_html_e( 'Previous', 'incentiv' ); ?>
            </button>
            <div class="mmd-wizard-footer-spacer"></div>
            <button type="button" class="button button-primary mmd-wizard-btn-next" id="mmd-gift-wizard-next">
                <?php esc_html_e( 'Next', 'incentiv' ); ?> <span class="mmd-dir-icon" aria-hidden="true">&larr;</span>
            </button>
            <button type="button" class="button button-primary mmd-wizard-btn-finish" id="mmd-gift-wizard-finish" style="display:none;">
                <?php esc_html_e( 'Finish and go to the form', 'incentiv' ); ?>
            </button>
        </div>
    </div>
</div>
