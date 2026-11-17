<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template included from a class method; its variables are local to that method, not globals.
/**
 * Gift Rule Creation Wizard Template
 * Step-by-step wizard for creating gift rules
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div id="incv-gift-wizard-overlay" class="incv-wizard-overlay" style="display:none;">
    <div class="incv-wizard-backdrop"></div>
    <div class="incv-wizard-dialog">
        <button type="button" class="incv-wizard-close" title="<?php esc_attr_e( 'Close wizard', 'incentiv' ); ?>">&times;</button>

        <div class="incv-wizard-header" style="background:var(--incv-grad-purple);">
            <h2 id="incv-gift-wizard-title"></h2>
            <div class="incv-wizard-sub"><?php echo wp_kses_post( __( 'Gift rule wizard &middot; Create a gift rule step by step', 'incentiv' ) ); ?></div>
        </div>

        <div class="incv-wizard-steps">
            <div class="incv-gift-wizard-step" data-step="1"><span class="incv-step-num">1</span><span class="incv-step-label"><?php esc_html_e( 'Gift type', 'incentiv' ); ?></span></div>
            <div class="incv-gift-wizard-step" data-step="2"><span class="incv-step-num">2</span><span class="incv-step-label"><?php esc_html_e( 'How it\'s given', 'incentiv' ); ?></span></div>
            <div class="incv-gift-wizard-step" data-step="3"><span class="incv-step-num">3</span><span class="incv-step-label"><?php esc_html_e( 'Conditions', 'incentiv' ); ?></span></div>
            <div class="incv-gift-wizard-step" data-step="4"><span class="incv-step-num">4</span><span class="incv-step-label"><?php esc_html_e( 'Summary', 'incentiv' ); ?></span></div>
        </div>

        <div class="incv-wizard-body" id="incv-gift-wizard-body">
            <!-- Step content injected by JS -->
        </div>

        <div class="incv-wizard-footer">
            <button type="button" class="button incv-wizard-btn-prev" id="incv-gift-wizard-prev" style="display:none;">
                <span class="incv-dir-icon" aria-hidden="true">&rarr;</span> <?php esc_html_e( 'Previous', 'incentiv' ); ?>
            </button>
            <div class="incv-wizard-footer-spacer"></div>
            <button type="button" class="button button-primary incv-wizard-btn-next" id="incv-gift-wizard-next">
                <?php esc_html_e( 'Next', 'incentiv' ); ?> <span class="incv-dir-icon" aria-hidden="true">&larr;</span>
            </button>
            <button type="button" class="button button-primary incv-wizard-btn-finish" id="incv-gift-wizard-finish" style="display:none;">
                <?php esc_html_e( 'Finish and go to the form', 'incentiv' ); ?>
            </button>
        </div>
    </div>
</div>
