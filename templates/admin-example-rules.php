<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template included from a class method; its variables are local to that method, not globals.
/**
 * Example Rules Section - shown in the rules list page
 */

if (!defined('ABSPATH')) {
    exit;
}

$example_rules = INCV_Example_Rules::get_examples();
// The English sub-lines are only useful when the main text is translated (e.g. Hebrew); in English they would repeat the title.
$incv_show_en = (0 === strpos(determine_locale(), 'he'));
?>

<div class="incv-examples-section">
    <h2 class="incv-examples-title"><span class="msr">bolt</span><?php echo esc_html__('Start from a ready-made template', 'incentiv'); ?></h2>
    <p class="incv-examples-desc"><?php echo esc_html__('Import a ready-made example rule in one click. You can customize it after importing.', 'incentiv'); ?></p>

    <div class="incv-examples-grid">
        <?php foreach ($example_rules as $key => $example): ?>
            <div class="incv-example-card">
                <div class="incv-example-icon"><?php echo esc_html($example['icon']); ?></div>
                <h3 class="incv-example-name"><?php echo esc_html($example['name']); ?></h3>
                <?php if ($incv_show_en) : ?>
                    <p class="incv-example-name-en"><?php echo esc_html($example['name_en']); ?></p>
                <?php endif; ?>
                <p class="incv-example-description"><?php echo esc_html($example['description']); ?></p>
                <?php if ($incv_show_en) : ?>
                    <p class="incv-example-description-en"><?php echo esc_html($example['description_en']); ?></p>
                <?php endif; ?>
                <div class="incv-example-details">
                    <?php foreach ($example['highlights'] as $highlight): ?>
                        <span class="incv-example-highlight"><?php echo esc_html($highlight); ?></span>
                    <?php endforeach; ?>
                </div>
                <button type="button"
                        class="incv-import-example"
                        data-example="<?php echo esc_attr($key); ?>"
                        data-nonce="<?php echo esc_attr(wp_create_nonce('incv_import_example')); ?>">
                    <?php echo esc_html__('Import rule', 'incentiv'); ?>
                </button>
            </div>
        <?php endforeach; ?>
    </div>
</div>
