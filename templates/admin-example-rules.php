<?php
/**
 * Example Rules Section - shown in the rules list page
 */

if (!defined('ABSPATH')) {
    exit;
}

$example_rules = MMD_Example_Rules::get_examples();
// The English sub-lines are only useful when the main text is translated (e.g. Hebrew); in English they would repeat the title.
$mmd_show_en = (0 === strpos(determine_locale(), 'he'));
?>

<div class="mmd-examples-section">
    <h2 class="mmd-examples-title"><span class="msr">bolt</span><?php echo esc_html__('Start from a ready-made template', 'incentiv'); ?></h2>
    <p class="mmd-examples-desc"><?php echo esc_html__('Import a ready-made example rule in one click. You can customize it after importing.', 'incentiv'); ?></p>

    <div class="mmd-examples-grid">
        <?php foreach ($example_rules as $key => $example): ?>
            <div class="mmd-example-card">
                <div class="mmd-example-icon"><?php echo esc_html($example['icon']); ?></div>
                <h3 class="mmd-example-name"><?php echo esc_html($example['name']); ?></h3>
                <?php if ($mmd_show_en) : ?>
                    <p class="mmd-example-name-en"><?php echo esc_html($example['name_en']); ?></p>
                <?php endif; ?>
                <p class="mmd-example-description"><?php echo esc_html($example['description']); ?></p>
                <?php if ($mmd_show_en) : ?>
                    <p class="mmd-example-description-en"><?php echo esc_html($example['description_en']); ?></p>
                <?php endif; ?>
                <div class="mmd-example-details">
                    <?php foreach ($example['highlights'] as $highlight): ?>
                        <span class="mmd-example-highlight"><?php echo esc_html($highlight); ?></span>
                    <?php endforeach; ?>
                </div>
                <button type="button"
                        class="mmd-import-example"
                        data-example="<?php echo esc_attr($key); ?>"
                        data-nonce="<?php echo esc_attr(wp_create_nonce('mmd_import_example')); ?>">
                    <?php echo esc_html__('Import rule', 'incentiv'); ?>
                </button>
            </div>
        <?php endforeach; ?>
    </div>
</div>
