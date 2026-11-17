<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template included from a class method; its variables are local to that method, not globals.
/**
 * Admin App Header + Navigation Tabs — shared across all plugin admin pages.
 * Renders the plugin brand header (logo, title, version, search, wizard CTA)
 * and the main tab bar, per the v1.9 admin redesign.
 */

if (!defined('ABSPATH')) {
    exit;
}

// Determine current page
$current_page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin view parameter.

// Core tabs plus the ones add-ons register (filter `incv_admin_tabs`).
$tabs = INCV_Modules::admin_tabs();
?>

<div class="incv-app-header">
    <div class="incv-app-header-top">
        <div class="incv-app-brand">
            <div class="incv-logo"><img src="<?php echo esc_url(INCV_PLUGIN_URL . 'assets/img/incentiv-tile.svg'); ?>" alt="Incentiv" /></div>
            <div>
                <div class="incv-app-title-row">
                    <h1 class="incv-app-title">Incentiv<span class="incv-wordmark-dot">.</span></h1>
                    <span class="incv-version-chip">v<?php echo esc_html(defined('INCV_VERSION') ? INCV_VERSION : ''); ?></span>
                </div>
                <div class="incv-app-sub"><?php esc_html_e( 'Every reason to buy more', 'incentiv' ); ?></div>
            </div>
        </div>
        <div class="incv-header-actions">
            <div class="incv-header-search">
                <span class="msr">search</span>
                <input type="text" id="incv-global-search" placeholder="<?php esc_attr_e( 'Search rules, gifts…', 'incentiv' ); ?>" autocomplete="off" />
            </div>
            <a href="<?php echo esc_url(admin_url('admin.php?page=incv-discount-rules&action=new')); ?>" class="incv-btn incv-btn-primary">
                <span class="msr">auto_awesome</span><?php echo wp_kses_post( __( 'New rule &middot; wizard', 'incentiv' ) ); ?>
            </a>
        </div>
    </div>

    <nav class="incv-nav-tabs">
        <?php foreach ($tabs as $tab):
            $is_active = in_array($current_page, $tab['match'], true);
        ?>
            <a href="<?php echo esc_url(admin_url('admin.php?page=' . $tab['slug'])); ?>"
               class="incv-nav-tab <?php echo $is_active ? 'incv-nav-tab-active' : ''; ?>">
                <span class="msr"><?php echo esc_html($tab['icon']); ?></span>
                <span><?php echo esc_html($tab['label']); ?></span>
            </a>
        <?php endforeach; ?>
    </nav>
</div>
