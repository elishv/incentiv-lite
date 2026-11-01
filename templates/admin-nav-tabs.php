<?php
/**
 * Admin App Header + Navigation Tabs — shared across all plugin admin pages.
 * Renders the plugin brand header (logo, title, version, search, wizard CTA)
 * and the main tab bar, per the v1.9 admin redesign.
 */

if (!defined('ABSPATH')) {
    exit;
}

// Determine current page
$current_page = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '';

// Core tabs plus the ones add-ons register (filter `mmd_admin_tabs`).
$tabs = MMD_Modules::admin_tabs();
?>

<div class="mmd-app-header">
    <div class="mmd-app-header-top">
        <div class="mmd-app-brand">
            <div class="mmd-logo"><img src="<?php echo esc_url(MMD_PLUGIN_URL . 'assets/img/incentiv-tile.svg'); ?>" alt="Incentiv" /></div>
            <div>
                <div class="mmd-app-title-row">
                    <h1 class="mmd-app-title">Incentiv<span class="mmd-wordmark-dot">.</span></h1>
                    <span class="mmd-version-chip">v<?php echo esc_html(defined('MMD_VERSION') ? MMD_VERSION : ''); ?></span>
                </div>
                <div class="mmd-app-sub"><?php esc_html_e( 'Every reason to buy more', 'incentiv' ); ?></div>
            </div>
        </div>
        <div class="mmd-header-actions">
            <div class="mmd-header-search">
                <span class="msr">search</span>
                <input type="text" id="mmd-global-search" placeholder="<?php esc_attr_e( 'Search rules, gifts…', 'incentiv' ); ?>" autocomplete="off" />
            </div>
            <a href="<?php echo esc_url(admin_url('admin.php?page=mix-match-discounts&action=new')); ?>" class="mmd-btn mmd-btn-primary">
                <span class="msr">auto_awesome</span><?php echo wp_kses_post( __( 'New rule &middot; wizard', 'incentiv' ) ); ?>
            </a>
        </div>
    </div>

    <nav class="mmd-nav-tabs">
        <?php foreach ($tabs as $tab):
            $is_active = in_array($current_page, $tab['match'], true);
        ?>
            <a href="<?php echo esc_url(admin_url('admin.php?page=' . $tab['slug'])); ?>"
               class="mmd-nav-tab <?php echo $is_active ? 'mmd-nav-tab-active' : ''; ?>">
                <span class="msr"><?php echo esc_html($tab['icon']); ?></span>
                <span><?php echo esc_html($tab['label']); ?></span>
            </a>
        <?php endforeach; ?>
    </nav>
</div>
