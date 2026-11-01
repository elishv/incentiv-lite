<?php
if (!defined('ABSPATH')) {
    exit;
}

$rule_set_id = isset($_GET['rule_set_id']) ? sanitize_text_field(wp_unslash($_GET['rule_set_id'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin view parameter.
$is_new = empty($rule_set_id);

$default_fields = array(
    'id' => '',
    'name' => '',
    'enabled' => 'yes',
    'selection_method' => 'category',
    'included_categories' => array(),
    'excluded_categories' => array(),
    'included_products' => array(),
    'excluded_products' => array(),
    'excluded_products_from_categories' => array(),
    'limit_to_variations' => 'no',
    'included_variations_from_categories' => array(),
    'attribute_slug' => '',
    'attribute_values' => array(),
    'attribute_excluded_values' => array(),
    'discount_rules' => array(),
    'apply_to' => 'cheapest',
    'discount_mode' => 'quantity',
    'coupon_required' => 'no',
    'required_coupon_codes' => array(),
    'override_other_discounts' => 'no',
    'notice_enabled' => 'yes',
    'notice_title' => __( '☕ Buy more and save!', 'incentiv' ),
    'notice_content' => __( 'Buy more and get a special discount!', 'incentiv' ),
    'notice_color' => '#4CAF50',
    'show_in_mini_cart' => 'yes',
    'cart_message' => '',
    'checkout_message' => '',
    'shipping_method_condition' => '',
    'min_cart_total' => '',
    'max_cart_total' => '',
    'allowed_user_roles' => array(),
    'schedule_start' => '',
    'schedule_end' => '',
    'schedule_timezone' => wp_timezone_string(),
    'usage_limit_total' => '',
    'usage_limit_per_user' => '',
    'min_quantity_trigger' => '',
    'excluded_by_rules' => array(),
    'badge_enabled' => 'no',
    'badge_text' => __( 'Promotion', 'incentiv' ),
    'badge_bg_color' => '#e53935',
    'badge_text_color' => '#ffffff',
    'badge_show_on_archive' => 'yes',
    'badge_show_on_product' => 'yes',
    'show_sale_price' => 'no',
    'show_sale_price_on' => 'both',
    'sale_price_tier' => 'first',
    'show_discount_in_cart' => 'no',
    'banner_image_id' => 0,
    'banner_image_url' => '',
    'banner_link_url' => '',
    'global_notice_enabled' => 'no',
    'global_notice_text' => '',
    'global_notice_bg_color' => '#2196F3',
    'global_notice_text_color' => '#ffffff',
    'global_notice_position' => 'top',
    'global_notice_layout' => 'bar',
    'global_notice_dismissible' => 'yes',
    'global_notice_dismiss_limit' => '0',
    'global_notice_link_url' => '',
    'global_notice_link_text' => '',
    'global_notice_pages' => 'all',
    'global_notice_icon' => '',
    'global_notice_rotate_speed' => '5',
    'global_notice_marquee_direction' => 'rtl',
    'global_notice_mobile_position' => 'same',
);

if ($is_new) {
    $rule_set = $default_fields;
} else {
    $rule_set = MMD_Discount_Rules::get_rule_set($rule_set_id);
    if (!$rule_set) {
        wp_die(esc_html__( 'Rule not found', 'incentiv' ));
    }
    $rule_set = wp_parse_args($rule_set, $default_fields);
}

$categories = get_terms(array('taxonomy' => 'product_cat', 'hide_empty' => false));
$attributes = wc_get_attribute_taxonomies();
?>

<div class="wrap mmd-admin-wrap">
    <?php include MMD_PLUGIN_DIR . 'templates/admin-nav-tabs.php'; ?>
    <div class="mmd-editor-head">
        <a href="<?php echo esc_url( admin_url('admin.php?page=mix-match-discounts') ); ?>" class="mmd-back-btn" title="<?php esc_attr_e( 'Back to list', 'incentiv' ); ?>"><span class="msr mmd-dir-icon">arrow_forward</span></a>
        <div class="mmd-editor-head-titles">
            <h2><?php if ($is_new): ?><?php esc_html_e( 'New discount rule', 'incentiv' ); ?><?php else: ?><?php echo wp_kses_post( __( 'Edit rule &middot;', 'incentiv' ) ); ?> <?php echo esc_html($rule_set['name']); ?><?php endif; ?></h2>
            <?php if (!$is_new): ?><div class="mmd-editor-id"><?php echo esc_html($rule_set['id']); ?></div><?php endif; ?>
        </div>
    </div>

    <form method="post" action="" data-mmd-autosave="rule" data-mmd-record-id="<?php echo esc_attr($rule_set['id']); ?>">
        <?php wp_nonce_field('mmd_save_rule_set'); ?>
        <input type="hidden" name="rule_set_id" value="<?php echo esc_attr($rule_set['id']); ?>">

        <div class="mmd-editor-grid">
        <div class="mmd-editor-main">
        <div class="mmd-admin-container">

            <!-- Step navigation (filled by JS) -->
            <div class="mmd-stepper" id="mmd-rule-stepper">
                <div class="mmd-stepper-steps" id="mmd-rule-stepper-steps"></div>
                <div class="mmd-stepper-actions">
                    <button type="button" class="mmd-btn mmd-btn-outline" id="mmd-launch-wizard">
                        <span class="msr">auto_awesome</span> <?php esc_html_e( 'Wizard', 'incentiv' ); ?>
                    </button>
                    <button type="button" class="mmd-btn mmd-btn-neutral" id="mmd-open-preview">
                        <span class="msr">visibility</span> <?php esc_html_e( 'Test on products', 'incentiv' ); ?>
                    </button>
                </div>
            </div>

            <?php
            // Always include the wizard template (overlay starts hidden, opened on demand via the toolbar button)
            include MMD_PLUGIN_DIR . 'templates/admin-rule-wizard.php';
            ?>

            <!-- Basic Settings -->
            <div class="mmd-card">
                <h2 class="mmd-card-title"><span class="dashicons dashicons-clipboard"></span> <?php esc_html_e( 'Basic settings', 'incentiv' ); ?></h2>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="rule_name"><?php echo esc_html_x( 'Rule name *', 'discount', 'incentiv' ); ?></label>
                        </th>
                        <td>
                            <input type="text" name="rule_name" id="rule_name" value="<?php echo esc_attr($rule_set['name']); ?>" class="large-text" required>
                            <p class="description"><?php esc_html_e( 'A descriptive name for the discount rule (internal use only)', 'incentiv' ); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="cart_label"><?php esc_html_e( 'Discount label in the cart', 'incentiv' ); ?></label>
                        </th>
                        <td>
                            <input type="text" name="cart_label" id="cart_label" class="large-text"
                                   value="<?php echo esc_attr(isset($rule_set['cart_label']) ? $rule_set['cart_label'] : ''); ?>"
                                   placeholder="<?php echo esc_attr($rule_set['name'] !== '' ? $rule_set['name'] : esc_html__( 'e.g. Summer sale', 'incentiv' )); ?>">
                            <p class="description">
                                <?php esc_html_e( 'The text shown to the customer on the discount line in the cart and at checkout, exactly as you type it here. Leave empty to use the rule name.', 'incentiv' ); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="enabled"><?php echo esc_html_x( 'Enable this rule', 'discount', 'incentiv' ); ?></label>
                        </th>
                        <td>
                            <label class="mmd-switch">
                                <input type="checkbox" name="enabled" id="enabled" <?php echo (($rule_set['enabled'] ?? '') === 'yes' || (isset($rule_set['status']) && $rule_set['status'] === 'draft')) ? 'checked' : ''; ?>>
                                <span class="mmd-slider"></span>
                            </label>
                            <p class="description"><?php echo esc_html_x( 'Turn off to disable the rule temporarily without deleting it', 'discount', 'incentiv' ); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="discount_mode"><?php esc_html_e( 'Discount mode', 'incentiv' ); ?></label>
                        </th>
                        <td>
                            <select name="discount_mode" id="discount_mode" class="regular-text">
                                <option value="quantity" <?php selected($rule_set['discount_mode'], 'quantity'); ?>><?php esc_html_e( 'Quantity-based (Buy X, get discount on 1 item)', 'incentiv' ); ?></option>
                                <option value="global" <?php selected($rule_set['discount_mode'], 'global'); ?>><?php esc_html_e( 'Global (Discount on ALL matching products)', 'incentiv' ); ?></option>
                            </select>
                            <p class="description">
                                <?php echo wp_kses_post( __( '<strong>By quantity:</strong> the customer needs to buy a certain quantity to get a discount on one item (the cheapest/most expensive).<br> <strong>Global discount:</strong> the discount applies to <em>all</em> matching products in the cart, regardless of quantity. Perfect for category discounts such as "15% off all shoes".', 'incentiv' ) ); ?>
                            </p>
                        </td>
                    </tr>

                    <tr id="apply_to_row" style="<?php echo ($rule_set['discount_mode'] === 'global') ? 'display:none;' : ''; ?>">
                        <th scope="row">
                            <label for="apply_to"><?php esc_html_e( 'Apply discount to', 'incentiv' ); ?></label>
                        </th>
                        <td>
                            <select name="apply_to" id="apply_to" class="regular-text">
                                <option value="cheapest" <?php selected($rule_set['apply_to'], 'cheapest'); ?>><?php esc_html_e( 'The cheapest item', 'incentiv' ); ?></option>
                                <option value="expensive" <?php selected($rule_set['apply_to'], 'expensive'); ?>><?php esc_html_e( 'The most expensive item', 'incentiv' ); ?></option>
                            </select>
                            <p class="description"><?php esc_html_e( 'Choose which item the discount applies to (only relevant in "By quantity" mode)', 'incentiv' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="discount_allocation"><?php esc_html_e( 'Split the discount across products', 'incentiv' ); ?></label>
                        </th>
                        <td>
                            <?php
                            $alloc_modes = class_exists('MMD_Discount_Allocator') ? MMD_Discount_Allocator::get_modes() : array();
                            $alloc_current = isset($rule_set['discount_allocation']) ? $rule_set['discount_allocation'] : 'fee';
                            ?>
                            <select name="discount_allocation" id="discount_allocation" class="regular-text">
                                <?php foreach ($alloc_modes as $mode_key => $mode): ?>
                                    <option value="<?php echo esc_attr($mode_key); ?>" <?php selected($alloc_current, $mode_key); ?>>
                                        <?php echo esc_html($mode['label']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php foreach ($alloc_modes as $mode_key => $mode): ?>
                                <p class="description mmd-alloc-help" data-mode="<?php echo esc_attr($mode_key); ?>"
                                   style="<?php echo $alloc_current === $mode_key ? '' : 'display:none;'; ?>">
                                    <?php echo esc_html($mode['help']); ?>
                                </p>
                            <?php endforeach; ?>
                            <p class="description" style="margin-top:8px;">
                                <?php esc_html_e( 'Sets how the final discount amount is attributed to products. This affects the product line display, partial refunds and the profitability reports. The split always adds up exactly to the discount amount.', 'incentiv' ); ?>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Product Selection -->
            <div class="mmd-card">
                <h2 class="mmd-card-title"><span class="dashicons dashicons-cart"></span> <?php esc_html_e( 'Product selection', 'incentiv' ); ?></h2>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="selection_method"><?php esc_html_e( 'Selection method', 'incentiv' ); ?></label>
                        </th>
                        <td>
                            <select name="selection_method" id="selection_method" class="regular-text">
                                <option value="all" <?php selected($rule_set['selection_method'], 'all'); ?>><?php esc_html_e( 'All products', 'incentiv' ); ?></option>
                                <option value="category" <?php selected($rule_set['selection_method'], 'category'); ?>><?php esc_html_e( 'By categories', 'incentiv' ); ?></option>
                                <option value="product_ids" <?php selected($rule_set['selection_method'], 'product_ids'); ?>><?php esc_html_e( 'By product IDs', 'incentiv' ); ?></option>
                                <option value="attribute" <?php selected($rule_set['selection_method'], 'attribute'); ?>><?php esc_html_e( 'By attribute', 'incentiv' ); ?></option>
                            </select>
                            <p class="description"><?php esc_html_e( 'Choose how to identify products eligible for the discount', 'incentiv' ); ?></p>
                        </td>
                    </tr>
                </table>

                <!-- Category Selection -->
                <div id="category_section" class="mmd-section" style="display: <?php echo $rule_set['selection_method'] === 'category' ? 'block' : 'none'; ?>;">
                    <h3><?php esc_html_e( 'Categories', 'incentiv' ); ?></h3>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label><?php esc_html_e( 'Included categories', 'incentiv' ); ?></label>
                            </th>
                            <td>
                                <div class="mmd-checkbox-group">
                                    <?php foreach ($categories as $category): ?>
                                        <label>
                                            <input type="checkbox" name="included_categories[]" value="<?php echo esc_attr($category->slug); ?>" <?php checked(in_array($category->slug, $rule_set['included_categories'])); ?>>
                                            <?php echo esc_html($category->name); ?>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                                <p class="description"><?php esc_html_e( 'Choose categories that are eligible for the discount', 'incentiv' ); ?></p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label><?php esc_html_e( 'Excluded categories', 'incentiv' ); ?></label>
                            </th>
                            <td>
                                <div class="mmd-checkbox-group">
                                    <?php foreach ($categories as $category): ?>
                                        <label>
                                            <input type="checkbox" name="excluded_categories[]" value="<?php echo esc_attr($category->slug); ?>" <?php checked(in_array($category->slug, $rule_set['excluded_categories'])); ?>>
                                            <?php echo esc_html($category->name); ?>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                                <p class="description"><?php esc_html_e( 'Choose categories that are not eligible for the discount (overrides included)', 'incentiv' ); ?></p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="excluded_products_from_categories"><?php esc_html_e( 'Products/variations excluded from the categories', 'incentiv' ); ?></label>
                            </th>
                            <td>
                                <select name="excluded_products_from_categories[]" id="excluded_products_from_categories" class="mmd-product-search-multiple" multiple style="width: 100%;">
                                    <?php
                                    if (!empty($rule_set['excluded_products_from_categories'])) {
                                        foreach ($rule_set['excluded_products_from_categories'] as $product_id) {
                                            $product = wc_get_product($product_id);
                                            if ($product) {
                                                $display_name = $product->get_name();
                                                $prefix = $product->is_type('variation') ? '↳ ' : '';
                                                echo '<option value="' . esc_attr($product_id) . '" selected>' . esc_html($prefix . $display_name) . ' (#' . esc_html( $product_id ) . ')</option>';
                                            }
                                        }
                                    }
                                    ?>
                                </select>
                                <p class="description"><?php echo wp_kses_post( __( '<strong>New!</strong> Search and select specific products or variations that are not eligible for the discount, even if they are in included categories', 'incentiv' ) ); ?></p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="limit_to_variations"><?php esc_html_e( 'Limit to specific variations', 'incentiv' ); ?></label>
                            </th>
                            <td>
                                <label class="mmd-switch">
                                    <input type="checkbox" name="limit_to_variations" id="limit_to_variations" <?php checked(!empty($rule_set['limit_to_variations']) ? $rule_set['limit_to_variations'] : 'no', 'yes'); ?>>
                                    <span class="mmd-slider"></span>
                                </label>
                                <p class="description"><?php esc_html_e( 'Enable to choose only specific variations from the selected categories', 'incentiv' ); ?></p>
                            </td>
                        </tr>

                        <tr id="included_variations_row" style="display: <?php echo (!empty($rule_set['limit_to_variations']) && $rule_set['limit_to_variations'] === 'yes') ? 'table-row' : 'none'; ?>;">
                            <th scope="row">
                                <label for="included_variations_from_categories"><?php esc_html_e( 'Included variations', 'incentiv' ); ?></label>
                            </th>
                            <td>
                                <div id="category_variations_loading" style="display: none; padding: 10px; color: #666;">
                                    <span class="spinner is-active" style="float: none; margin: 0 5px 0 0;"></span>
                                    <?php esc_html_e( 'Loading products from the categories...', 'incentiv' ); ?>
                                </div>
                                <select name="included_variations_from_categories[]" id="included_variations_from_categories" multiple style="width: 100%;">
                                    <?php
                                    if (!empty($rule_set['included_variations_from_categories'])) {
                                        foreach ($rule_set['included_variations_from_categories'] as $product_id) {
                                            $product = wc_get_product($product_id);
                                            if ($product) {
                                                $display_name = $product->get_name();
                                                $prefix = $product->is_type('variation') ? '↳ ' : '';
                                                echo '<option value="' . esc_attr($product_id) . '" selected>' . esc_html($prefix . $display_name) . ' (#' . esc_html( $product_id ) . ')</option>';
                                            }
                                        }
                                    }
                                    ?>
                                </select>
                                <p class="description"><?php esc_html_e( 'Choose specific variations from the selected categories that are eligible for the discount', 'incentiv' ); ?></p>
                                <button type="button" id="load_category_variations" class="button button-secondary" style="margin-top: 10px;">
                                    <span class="dashicons dashicons-update" style="font-size:14px;width:14px;height:14px;vertical-align:middle;"></span> <?php esc_html_e( 'Load products from the categories', 'incentiv' ); ?>
                                </button>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- Product IDs Selection -->
                <div id="product_ids_section" class="mmd-section" style="display: <?php echo $rule_set['selection_method'] === 'product_ids' ? 'block' : 'none'; ?>;">
                    <h3><?php esc_html_e( 'Product IDs', 'incentiv' ); ?></h3>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="included_products"><?php esc_html_e( 'Included products/variations', 'incentiv' ); ?></label>
                            </th>
                            <td>
                                <select name="included_products[]" id="included_products" class="mmd-product-search-multiple" multiple style="width: 100%;">
                                    <?php
                                    if (!empty($rule_set['included_products'])) {
                                        foreach ($rule_set['included_products'] as $product_id) {
                                            $product = wc_get_product($product_id);
                                            if ($product) {
                                                $display_name = $product->get_name();
                                                $prefix = $product->is_type('variation') ? '↳ ' : '';
                                                echo '<option value="' . esc_attr($product_id) . '" selected>' . esc_html($prefix . $display_name) . ' (#' . esc_html( $product_id ) . ')</option>';
                                            }
                                        }
                                    }
                                    ?>
                                </select>
                                <p class="description"><?php esc_html_e( 'Search and select specific products or variations that are eligible for the discount', 'incentiv' ); ?></p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="excluded_products"><?php echo esc_html_x( 'Excluded products/variations', 'discount', 'incentiv' ); ?></label>
                            </th>
                            <td>
                                <select name="excluded_products[]" id="excluded_products" class="mmd-product-search-multiple" multiple style="width: 100%;">
                                    <?php
                                    if (!empty($rule_set['excluded_products'])) {
                                        foreach ($rule_set['excluded_products'] as $product_id) {
                                            $product = wc_get_product($product_id);
                                            if ($product) {
                                                $display_name = $product->get_name();
                                                $prefix = $product->is_type('variation') ? '↳ ' : '';
                                                echo '<option value="' . esc_attr($product_id) . '" selected>' . esc_html($prefix . $display_name) . ' (#' . esc_html( $product_id ) . ')</option>';
                                            }
                                        }
                                    }
                                    ?>
                                </select>
                                <p class="description"><?php esc_html_e( 'Search and select specific products or variations that are not eligible for the discount', 'incentiv' ); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- Attribute Selection -->
                <div id="attribute_section" class="mmd-section" style="display: <?php echo $rule_set['selection_method'] === 'attribute' ? 'block' : 'none'; ?>;">
                    <h3><?php esc_html_e( 'Attributes', 'incentiv' ); ?></h3>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="attribute_slug"><?php esc_html_e( 'Attribute', 'incentiv' ); ?></label>
                            </th>
                            <td>
                                <select name="attribute_slug" id="attribute_slug" class="regular-text">
                                    <option value=""><?php esc_html_e( 'Choose an attribute...', 'incentiv' ); ?></option>
                                    <?php foreach ($attributes as $attribute): ?>
                                        <option value="<?php echo esc_attr($attribute->attribute_name); ?>" <?php selected($rule_set['attribute_slug'], $attribute->attribute_name); ?>>
                                            <?php echo esc_html($attribute->attribute_label); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description"><?php echo esc_html_x( 'Choose an attribute to filter by', 'settings', 'incentiv' ); ?></p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="attribute_values"><?php esc_html_e( 'Included attribute values', 'incentiv' ); ?></label>
                            </th>
                            <td>
                                <input type="text" name="attribute_values" id="attribute_values" value="<?php echo esc_attr(implode(',', $rule_set['attribute_values'])); ?>" class="large-text">
                                <p class="description"><?php esc_html_e( 'Enter comma-separated values (for example: 1-kg,2-kg or 1kg,2kg)', 'incentiv' ); ?></p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="attribute_excluded_values"><?php echo esc_html_x( 'Excluded attribute values', 'settings', 'incentiv' ); ?></label>
                            </th>
                            <td>
                                <input type="text" name="attribute_excluded_values" id="attribute_excluded_values" value="<?php echo esc_attr(implode(',', $rule_set['attribute_excluded_values'])); ?>" class="large-text">
                                <p class="description"><?php esc_html_e( 'Enter values that are not eligible for the discount (overrides included)', 'incentiv' ); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- Discount Rules (NOT gifts - this is for quantity-based discounts only) -->
            <div class="mmd-card">
                <h2 class="mmd-card-title"><span class="dashicons dashicons-money-alt"></span> <?php esc_html_e( 'Discount rules', 'incentiv' ); ?></h2>

                <!-- Quantity mode description -->
                <div id="discount_rules_quantity_desc" style="<?php echo ($rule_set['discount_mode'] === 'global') ? 'display:none;' : ''; ?>">
                    <p class="description" style="margin: -10px 0 15px;">
                        <?php esc_html_e( 'Set how many items must be bought and what the discount is. The discount applies to the price and doesn\'t add gifts.', 'incentiv' ); ?>
                    </p>

                    <!-- BOGO / Quick Presets -->
                    <div class="mmd-preset-box">
                        <strong><?php esc_html_e( 'Quick fill', 'incentiv' ); ?></strong>
                        <button type="button" class="button button-small mmd-preset-btn" data-preset="bogo50">BOGO 50%</button>
                        <button type="button" class="button button-small mmd-preset-btn" data-preset="bogo_free"><?php esc_html_e( 'Buy 1 get 1 free', 'incentiv' ); ?></button>
                        <button type="button" class="button button-small mmd-preset-btn" data-preset="buy4get1"><?php esc_html_e( 'Buy 4 get 1 free', 'incentiv' ); ?></button>
                        <button type="button" class="button button-small mmd-preset-btn" data-preset="tiered"><?php esc_html_e( 'Tiered discounts', 'incentiv' ); ?></button>
                        <button type="button" class="button button-small mmd-preset-btn" data-preset="buy3_30"><?php esc_html_e( 'Buy 3 get 30%', 'incentiv' ); ?></button>
                    </div>
                </div>

                <!-- Global mode description -->
                <div id="discount_rules_global_desc" style="<?php echo ($rule_set['discount_mode'] === 'global') ? '' : 'display:none;'; ?>">
                    <p class="description" style="margin: -10px 0 15px;">
                        <?php echo wp_kses_post( __( 'Set the discount that applies to <strong>all</strong> matching products in the cart. You can also add tiered discounts by quantity.', 'incentiv' ) ); ?>
                    </p>

                    <!-- Global Quick Presets -->
                    <div class="mmd-preset-box">
                        <strong><?php esc_html_e( 'Quick fill', 'incentiv' ); ?></strong>
                        <button type="button" class="button button-small mmd-global-preset-btn" data-discount="5" data-type="percentage"><?php esc_html_e( '5% off', 'incentiv' ); ?></button>
                        <button type="button" class="button button-small mmd-global-preset-btn" data-discount="10" data-type="percentage"><?php esc_html_e( '10% off', 'incentiv' ); ?></button>
                        <button type="button" class="button button-small mmd-global-preset-btn" data-discount="15" data-type="percentage"><?php esc_html_e( '15% off', 'incentiv' ); ?></button>
                        <button type="button" class="button button-small mmd-global-preset-btn" data-discount="20" data-type="percentage"><?php esc_html_e( '20% off', 'incentiv' ); ?></button>
                        <button type="button" class="button button-small mmd-global-preset-btn" data-discount="25" data-type="percentage"><?php esc_html_e( '25% off', 'incentiv' ); ?></button>
                        <button type="button" class="button button-small mmd-global-preset-btn" data-discount="50" data-type="percentage"><?php esc_html_e( '50% off', 'incentiv' ); ?></button>
                    </div>
                </div>

                <div id="discount_rules_container">
                    <?php
                    $rules = $rule_set['discount_rules'];
                    if (empty($rules)) {
                        $rules = array(array('quantity' => '', 'discount' => '', 'type' => 'percentage'));
                    }
                    foreach ($rules as $index => $rule):
                    ?>
                        <div class="mmd-discount-rule">
                            <div class="mmd-rule-fields">
                                <div class="mmd-field mmd-quantity-field" style="<?php echo ($rule_set['discount_mode'] === 'global') ? 'display:none;' : ''; ?>">
                                    <label><?php esc_html_e( 'Quantity', 'incentiv' ); ?></label>
                                    <input type="number" name="discount_rules[<?php echo esc_html( $index ); ?>][quantity]" value="<?php echo esc_attr($rule['quantity']); ?>" min="1" placeholder="3">
                                </div>

                                <div class="mmd-field">
                                    <label><?php esc_html_e( 'Discount', 'incentiv' ); ?></label>
                                    <input type="number" name="discount_rules[<?php echo esc_html( $index ); ?>][discount]" value="<?php echo esc_attr($rule['discount']); ?>" min="0" step="0.01" placeholder="30">
                                </div>

                                <div class="mmd-field">
                                    <label><?php esc_html_e( 'Type', 'incentiv' ); ?></label>
                                    <select name="discount_rules[<?php echo esc_html( $index ); ?>][type]">
                                        <option value="percentage" <?php selected($rule['type'], 'percentage'); ?>><?php esc_html_e( 'Percentage (%)', 'incentiv' ); ?></option>
                                        <option value="fixed" <?php selected($rule['type'], 'fixed'); ?>><?php esc_html_e( 'Fixed amount (₪)', 'incentiv' ); ?></option>
                                    </select>
                                </div>

                                                <button type="button" class="button mmd-remove-rule"><span class="dashicons dashicons-trash" style="font-size:14px;width:14px;height:14px;vertical-align:middle;" aria-hidden="true"></span><span class="screen-reader-text"><?php esc_html_e( 'Remove', 'incentiv' ); ?></span></button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <button type="button" id="add_discount_rule" class="button button-secondary"><span class="dashicons dashicons-plus-alt" style="font-size:16px;width:16px;height:16px;vertical-align:middle;"></span> <?php esc_html_e( 'Add discount tier', 'incentiv' ); ?></button>
                <p class="description" id="discount_rules_help_text">
                    <?php if ($rule_set['discount_mode'] === 'global'): ?>
                        <?php esc_html_e( 'In global mode, the first discount applies to all products. You can add more tiers with a quantity to increase the discount (for example: buy 3+ get 20% instead of 15%).', 'incentiv' ); ?>
                    <?php else: ?>
                        <?php esc_html_e( 'Set different discounts for different quantities. The highest matching discount applies.', 'incentiv' ); ?>
                    <?php endif; ?>
                </p>
            </div>

            <!-- Coupon Settings -->
            <div class="mmd-card">
                <h2 class="mmd-card-title"><span class="dashicons dashicons-tickets-alt"></span> <?php esc_html_e( 'Coupon settings', 'incentiv' ); ?></h2>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="coupon_required"><?php esc_html_e( 'Requires a coupon', 'incentiv' ); ?></label>
                        </th>
                        <td>
                            <label class="mmd-switch">
                                <input type="checkbox" name="coupon_required" id="coupon_required" <?php checked(!empty($rule_set['coupon_required']) ? $rule_set['coupon_required'] : 'no', 'yes'); ?>>
                                <span class="mmd-slider"></span>
                            </label>
                            <p class="description"><?php esc_html_e( 'Enable if the discount should only apply when a specific coupon is entered', 'incentiv' ); ?></p>
                        </td>
                    </tr>

                    <tr id="coupon_codes_row" style="display: <?php echo (!empty($rule_set['coupon_required']) && $rule_set['coupon_required'] === 'yes') ? 'table-row' : 'none'; ?>;">
                        <th scope="row">
                            <label for="required_coupon_codes"><?php esc_html_e( 'Coupon codes', 'incentiv' ); ?></label>
                        </th>
                        <td>
                            <input type="text" name="required_coupon_codes" id="required_coupon_codes" value="<?php echo esc_attr(implode(', ', isset($rule_set['required_coupon_codes']) ? $rule_set['required_coupon_codes'] : array())); ?>" class="large-text">
                            <p class="description"><?php esc_html_e( 'Enter comma-separated coupon codes. The discount applies when one of the coupons is applied in the cart.', 'incentiv' ); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="override_other_discounts"><?php esc_html_e( 'Cancel other discounts', 'incentiv' ); ?></label>
                        </th>
                        <td>
                            <label class="mmd-switch">
                                <input type="checkbox" name="override_other_discounts" id="override_other_discounts" <?php checked(!empty($rule_set['override_other_discounts']) ? $rule_set['override_other_discounts'] : 'no', 'yes'); ?>>
                                <span class="mmd-slider"></span>
                            </label>
                            <p class="description"><?php esc_html_e( 'When this rule is active, it cancels other quantity discounts on the qualifying products. This discount will be the only one that applies.', 'incentiv' ); ?></p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Customer Notice Settings -->
            <div class="mmd-card">
                <h2 class="mmd-card-title"><span class="dashicons dashicons-megaphone"></span> <?php esc_html_e( 'Customer messages', 'incentiv' ); ?></h2>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="notice_enabled"><?php esc_html_e( 'Show a message to the customer', 'incentiv' ); ?></label>
                        </th>
                        <td>
                            <label class="mmd-switch">
                                <input type="checkbox" name="notice_enabled" id="notice_enabled" <?php checked(!empty($rule_set['notice_enabled']) ? $rule_set['notice_enabled'] : 'yes', 'yes'); ?>>
                                <span class="mmd-slider"></span>
                            </label>
                            <p class="description"><?php esc_html_e( 'Show a discount message on the product page', 'incentiv' ); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="notice_title"><?php esc_html_e( 'Message title', 'incentiv' ); ?></label>
                        </th>
                        <td>
                            <input type="text" name="notice_title" id="notice_title" value="<?php echo esc_attr(!empty($rule_set['notice_title']) ? $rule_set['notice_title'] : esc_html__( '☕ Buy more and save!', 'incentiv' )); ?>" class="large-text">
                            <p class="description"><?php esc_html_e( 'The title shown in the customer message', 'incentiv' ); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="notice_content"><?php esc_html_e( 'Message content', 'incentiv' ); ?></label>
                        </th>
                        <td>
                            <textarea name="notice_content" id="notice_content" rows="3" class="large-text"><?php echo esc_textarea(!empty($rule_set['notice_content']) ? $rule_set['notice_content'] : esc_html__( 'Buy more and get a special discount!', 'incentiv' )); ?></textarea>
                            <p class="description"><?php esc_html_e( 'The message content shown to the customer. Supports basic HTML.', 'incentiv' ); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="notice_color"><?php esc_html_e( 'Message background colour', 'incentiv' ); ?></label>
                        </th>
                        <td>
                            <input type="color" name="notice_color" id="notice_color" value="<?php echo esc_attr(!empty($rule_set['notice_color']) ? $rule_set['notice_color'] : '#4CAF50'); ?>">
                            <p class="description"><?php esc_html_e( 'Choose a background colour for the message', 'incentiv' ); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="show_in_mini_cart"><?php echo esc_html_x( 'Show in the mini cart', 'rule', 'incentiv' ); ?></label>
                        </th>
                        <td>
                            <label class="mmd-switch">
                                <input type="checkbox" name="show_in_mini_cart" id="show_in_mini_cart" <?php checked(!empty($rule_set['show_in_mini_cart']) ? $rule_set['show_in_mini_cart'] : 'yes', 'yes'); ?>>
                                <span class="mmd-slider"></span>
                            </label>
                            <p class="description"><?php esc_html_e( 'Show the discount in the mini cart as well', 'incentiv' ); ?></p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Custom Messages Card -->
            <div class="mmd-card">
                <h2 class="mmd-card-title"><span class="dashicons dashicons-format-chat"></span> <?php esc_html_e( 'Custom messages', 'incentiv' ); ?></h2>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="cart_message"><?php esc_html_e( 'Cart page message', 'incentiv' ); ?></label>
                        </th>
                        <td>
                            <textarea name="cart_message" id="cart_message" rows="3" class="large-text"><?php echo esc_textarea($rule_set['cart_message']); ?></textarea>
                            <p class="description"><?php esc_html_e( 'A custom message shown on the cart page when the discount rule is active and applied to products in the cart. Leave empty if no extra message is needed. Supports basic HTML.', 'incentiv' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="checkout_message"><?php esc_html_e( 'Checkout page message', 'incentiv' ); ?></label>
                        </th>
                        <td>
                            <textarea name="checkout_message" id="checkout_message" rows="3" class="large-text"><?php echo esc_textarea($rule_set['checkout_message']); ?></textarea>
                            <p class="description"><?php esc_html_e( 'A custom message shown on the checkout page when the discount rule is active and applied to products in the cart. Leave empty if no extra message is needed. Supports basic HTML.', 'incentiv' ); ?></p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Shipping Method Condition Card -->
            <div class="mmd-card">
                <h2 class="mmd-card-title"><span class="dashicons dashicons-car"></span> <?php esc_html_e( 'Shipping method condition', 'incentiv' ); ?></h2>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="shipping_method_condition"><?php esc_html_e( 'Limit to a shipping method', 'incentiv' ); ?></label>
                        </th>
                        <td>
                            <select name="shipping_method_condition" id="shipping_method_condition" class="regular-text">
                                <option value=""><?php esc_html_e( 'No limit - all shipping methods', 'incentiv' ); ?></option>
                            </select>
                            <p class="description"><?php esc_html_e( 'Choose a specific shipping method: the discount is only available when the customer chooses this shipping method. Leave empty to enable it for all shipping methods.', 'incentiv' ); ?></p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Cart Total Conditions Card -->
            <div class="mmd-card">
                <h2 class="mmd-card-title"><span class="dashicons dashicons-money-alt"></span> <?php esc_html_e( 'Cart total conditions', 'incentiv' ); ?></h2>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="min_cart_total"><?php echo esc_html_x( 'Minimum cart total', 'gift', 'incentiv' ); ?></label>
                        </th>
                        <td>
                            <input type="number" name="min_cart_total" id="min_cart_total" value="<?php echo esc_attr($rule_set['min_cart_total']); ?>" min="0" step="0.01" class="small-text">
                            <p class="description"><?php esc_html_e( 'The minimum cart total required before the discount applies (excluding VAT). Leave empty for no limit.', 'incentiv' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="max_cart_total"><?php esc_html_e( 'Maximum cart total', 'incentiv' ); ?></label>
                        </th>
                        <td>
                            <input type="number" name="max_cart_total" id="max_cart_total" value="<?php echo esc_attr($rule_set['max_cart_total']); ?>" min="0" step="0.01" class="small-text">
                            <p class="description"><?php esc_html_e( 'The cart total above which the discount doesn\'t apply. Leave empty for no limit.', 'incentiv' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="min_quantity_trigger"><?php esc_html_e( 'Minimum quantity in the cart', 'incentiv' ); ?></label>
                        </th>
                        <td>
                            <input type="number" name="min_quantity_trigger" id="min_quantity_trigger" value="<?php echo esc_attr($rule_set['min_quantity_trigger']); ?>" min="0" step="1" class="small-text">
                            <p class="description"><?php esc_html_e( 'The minimum quantity of matching products in the cart required to activate the discount (0 = no limit). Useful for BOGO and similar deals.', 'incentiv' ); ?></p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- User Role Conditions Card -->
            <div class="mmd-card">
                <h2 class="mmd-card-title"><span class="dashicons dashicons-admin-users"></span> <?php esc_html_e( 'User role conditions', 'incentiv' ); ?></h2>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="allowed_user_roles"><?php esc_html_e( 'Limit to user roles', 'incentiv' ); ?></label>
                        </th>
                        <td>
                            <?php
                            global $wp_roles;
                            $all_roles = $wp_roles->roles;
                            $allowed_roles = is_array($rule_set['allowed_user_roles']) ? $rule_set['allowed_user_roles'] : array();
                            ?>
                            <select name="allowed_user_roles[]" id="allowed_user_roles" multiple class="regular-text" style="min-height: 120px; width: 400px;">
                                <?php foreach ($all_roles as $role_key => $role_data): ?>
                                    <option value="<?php echo esc_attr($role_key); ?>" <?php echo in_array($role_key, $allowed_roles) ? 'selected' : ''; ?>>
                                        <?php echo esc_html(translate_user_role($role_data['name'])); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php esc_html_e( 'Choose the user roles the discount applies to. Leave empty to apply it to everyone. Hold Ctrl to select several roles.', 'incentiv' ); ?></p>
                        </td>
                    </tr>
                </table>
            </div>

            <?php
            // Extra cards from add-ons (for example audience targeting in Incentiv Pro).
            do_action('mmd_rule_editor_cards', $rule_set);
            ?>

            <!-- Margin Guards & Limits Card -->
            <div class="mmd-card">
                <h2 class="mmd-card-title"><span class="dashicons dashicons-shield"></span> <?php esc_html_e( 'Profit guards and caps', 'incentiv' ); ?></h2>
                <div class="mmd-card-tip">
                    <span class="msr">lightbulb</span>
                    <span><?php esc_html_e( 'The safety layer that makes aggressive promotions sustainable. Leave empty for no limit.', 'incentiv' ); ?></span>
                </div>

                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="max_discount_amount"><?php esc_html_e( 'Maximum discount per cart', 'incentiv' ); ?></label></th>
                        <td>
                            <input type="number" min="0" step="0.01" name="max_discount_amount" id="max_discount_amount" class="small-text" value="<?php echo esc_attr(isset($rule_set['max_discount_amount']) ? $rule_set['max_discount_amount'] : ''); ?>"> ₪
                            <input type="number" min="0" max="100" step="0.01" name="max_discount_percent" id="max_discount_percent" class="small-text" style="margin-inline-start:10px;" value="<?php echo esc_attr(isset($rule_set['max_discount_percent']) ? $rule_set['max_discount_percent'] : ''); ?>"> %
                            <p class="description"><?php esc_html_e( 'The cap on the discount the rule can give in one cart: as an amount, as a percentage of the cart, or both (the lower one wins).', 'incentiv' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="budget_cap"><?php esc_html_e( 'Campaign budget (₪)', 'incentiv' ); ?></label></th>
                        <td>
                            <input type="number" min="0" step="0.01" name="budget_cap" id="budget_cap" class="regular-text" style="width:160px;" value="<?php echo esc_attr(isset($rule_set['budget_cap']) ? $rule_set['budget_cap'] : ''); ?>">
                            <?php if (!$is_new && class_exists('MMD_Margin_Guards')):
                                $spent = MMD_Margin_Guards::get_rule_spend($rule_set['id']);
                                $cap = isset($rule_set['budget_cap']) && $rule_set['budget_cap'] !== '' ? floatval($rule_set['budget_cap']) : 0;
                                $pct = $cap > 0 ? min(100, round($spent / $cap * 100)) : 0;
                            ?>
                                <div class="mmd-budget-meter">
                                    <div class="mmd-budget-bar"><span style="width:<?php echo esc_attr($pct); ?>%;"></span></div>
                                    <div class="mmd-budget-text">
                                        <?php esc_html_e( 'Used up', 'incentiv' ); ?> <strong><?php echo esc_html(number_format_i18n($spent, 2)); ?> ₪</strong>
                                        <?php if ($cap > 0): ?> <?php esc_html_e( 'of', 'incentiv' ); ?> <?php echo esc_html(number_format_i18n($cap, 2)); ?> ₪ (<?php echo esc_html($pct); ?>%)<?php endif; ?>
                                    </div>
                                </div>
                                <?php if (!empty($rule_set['paused_reason']) && $rule_set['paused_reason'] === 'budget'): ?>
                                    <p class="description" style="color:var(--mmd-danger);font-weight:700;"><?php esc_html_e( 'The rule was paused automatically: the budget has been used up.', 'incentiv' ); ?></p>
                                <?php endif; ?>
                            <?php endif; ?>
                            <p class="description"><?php esc_html_e( 'When the total discount the rule has given reaches the budget, the rule is paused automatically.', 'incentiv' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="margin_floor_percent"><?php esc_html_e( 'Profit floor per item (%)', 'incentiv' ); ?></label></th>
                        <td>
                            <input type="number" min="0" max="99" step="0.1" name="margin_floor_percent" id="margin_floor_percent" class="small-text" value="<?php echo esc_attr(isset($rule_set['margin_floor_percent']) ? $rule_set['margin_floor_percent'] : ''); ?>">
                            <p class="description">
                                <?php esc_html_e( 'The discount won\'t take an item below this profit margin. Calculated against the product cost (', 'incentiv' ); ?><code>_mmd_cost</code> <?php esc_html_e( 'or a Cost of Goods plugin). An item without cost data isn\'t limited.', 'incentiv' ); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Product and category exclusions', 'incentiv' ); ?></th>
                        <td>
                            <p class="description">
                                <?php echo wp_kses_post( __( 'Exclusions are set in the <strong>Details and products</strong> step. That\'s where you can exclude specific products and categories from the rule.', 'incentiv' ) ); ?>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Schedule Card -->
            <div class="mmd-card">
                <h2 class="mmd-card-title"><span class="dashicons dashicons-calendar-alt"></span> <?php esc_html_e( 'Discount scheduling', 'incentiv' ); ?></h2>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="schedule_timezone"><?php esc_html_e( 'Time zone', 'incentiv' ); ?></label>
                        </th>
                        <td>
                            <select name="schedule_timezone" id="schedule_timezone" class="regular-text" style="min-width: 300px;">
                                <?php
                                $current_tz = !empty($rule_set['schedule_timezone']) ? $rule_set['schedule_timezone'] : wp_timezone_string();
                                $timezone_identifiers = DateTimeZone::listIdentifiers();
                                $common_timezones = array(
                                    'Asia/Jerusalem' => __( 'Israel (Asia/Jerusalem)', 'incentiv' ),
                                    'UTC' => 'UTC',
                                    'America/New_York' => 'New York (EST/EDT)',
                                    'America/Chicago' => 'Chicago (CST/CDT)',
                                    'America/Los_Angeles' => 'Los Angeles (PST/PDT)',
                                    'Europe/London' => 'London (GMT/BST)',
                                    'Europe/Paris' => 'Paris (CET/CEST)',
                                    'Europe/Berlin' => 'Berlin (CET/CEST)',
                                    'Asia/Tokyo' => 'Tokyo (JST)',
                                    'Asia/Dubai' => 'Dubai (GST)',
                                    'Australia/Sydney' => 'Sydney (AEST/AEDT)',
                                );
                                ?>
                                <optgroup label="<?php esc_attr_e( 'Common', 'incentiv' ); ?>">
                                    <?php foreach ($common_timezones as $tz_id => $tz_label): ?>
                                        <option value="<?php echo esc_attr($tz_id); ?>" <?php selected($current_tz, $tz_id); ?>><?php echo esc_html($tz_label); ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                                <optgroup label="<?php esc_attr_e( 'All time zones', 'incentiv' ); ?>">
                                    <?php foreach ($timezone_identifiers as $tz_id): ?>
                                        <?php if (!isset($common_timezones[$tz_id])): ?>
                                            <option value="<?php echo esc_attr($tz_id); ?>" <?php selected($current_tz, $tz_id); ?>><?php echo esc_html($tz_id); ?></option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </optgroup>
                            </select>
                            <?php
                            try {
                                $tz = new DateTimeZone($current_tz);
                                $now = new DateTime('now', $tz);
                                echo ( '<p class="description">' . esc_html__( 'Current time in the selected time zone:', 'incentiv' ) . ' <strong>' ) . esc_html( $now->format('Y-m-d H:i:s') ) . '</strong> (' . esc_html( $current_tz ) . ')</p>';
                            } catch (Exception $e) {
                                echo ( '<p class="description">' . esc_html__( 'Choose the time zone used for the start and end dates.', 'incentiv' ) . '</p>' );
                            }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="schedule_start"><?php esc_html_e( 'Start date and time', 'incentiv' ); ?></label>
                        </th>
                        <td>
                            <input type="datetime-local" name="schedule_start" id="schedule_start" value="<?php echo esc_attr($rule_set['schedule_start']); ?>" class="regular-text">
                            <p class="description"><?php esc_html_e( 'The date and time the discount starts (in the selected time zone). Leave empty to make it active immediately.', 'incentiv' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="schedule_end"><?php esc_html_e( 'End date and time', 'incentiv' ); ?></label>
                        </th>
                        <td>
                            <input type="datetime-local" name="schedule_end" id="schedule_end" value="<?php echo esc_attr($rule_set['schedule_end']); ?>" class="regular-text">
                            <p class="description"><?php esc_html_e( 'The date and time the discount stops (in the selected time zone). Leave empty to keep it active with no end.', 'incentiv' ); ?></p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Usage Limits Card -->
            <div class="mmd-card">
                <h2 class="mmd-card-title"><span class="dashicons dashicons-lock"></span> <?php esc_html_e( 'Usage limits', 'incentiv' ); ?></h2>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="usage_limit_total"><?php echo esc_html_x( 'Total usage limit', 'rule', 'incentiv' ); ?></label>
                        </th>
                        <td>
                            <input type="number" name="usage_limit_total" id="usage_limit_total" value="<?php echo esc_attr($rule_set['usage_limit_total']); ?>" min="0" step="1" class="small-text">
                            <p class="description"><?php esc_html_e( 'The maximum number of times the discount can be applied in total. Leave empty for no limit.', 'incentiv' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="usage_limit_per_user"><?php esc_html_e( 'Usage limit per customer', 'incentiv' ); ?></label>
                        </th>
                        <td>
                            <input type="number" name="usage_limit_per_user" id="usage_limit_per_user" value="<?php echo esc_attr($rule_set['usage_limit_per_user']); ?>" min="0" step="1" class="small-text">
                            <p class="description"><?php esc_html_e( 'The maximum number of times each customer can use the discount. Leave empty for no limit.', 'incentiv' ); ?></p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Discount Badge Card -->
            <div class="mmd-card">
                <h2 class="mmd-card-title"><span class="dashicons dashicons-tag"></span> <?php esc_html_e( 'Discount badge', 'incentiv' ); ?></h2>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="badge_enabled"><?php esc_html_e( 'Show a discount badge on the product', 'incentiv' ); ?></label>
                        </th>
                        <td>
                            <label class="mmd-switch">
                                <input type="checkbox" name="badge_enabled" id="badge_enabled" <?php checked($rule_set['badge_enabled'], 'yes'); ?>>
                                <span class="mmd-slider"></span>
                            </label>
                            <p class="description"><?php esc_html_e( 'Show a badge on the product image when it belongs to an active discount rule', 'incentiv' ); ?></p>
                        </td>
                    </tr>

                    <tr id="badge_settings_rows">
                        <th scope="row">
                            <label for="badge_text"><?php echo esc_html_x( 'Badge text', 'rule', 'incentiv' ); ?></label>
                        </th>
                        <td>
                            <input type="text" name="badge_text" id="badge_text" value="<?php echo esc_attr($rule_set['badge_text']); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Promotion', 'incentiv' ); ?>">
                            <p class="description"><?php esc_html_e( 'The text shown on the badge. For example: "Sale", "Discount", "SALE", "BOGO"', 'incentiv' ); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="badge_bg_color"><?php esc_html_e( 'Badge background colour', 'incentiv' ); ?></label>
                        </th>
                        <td>
                            <input type="color" name="badge_bg_color" id="badge_bg_color" value="<?php echo esc_attr($rule_set['badge_bg_color']); ?>">
                            <input type="color" name="badge_text_color" id="badge_text_color" value="<?php echo esc_attr($rule_set['badge_text_color']); ?>">
                            <span style="margin-right: 10px;"><?php esc_html_e( 'Background / text colour', 'incentiv' ); ?></span>
                            <span class="mmd-badge-preview" style="display: inline-block; padding: 4px 12px; border-radius: 4px; font-size: 12px; font-weight: bold; background: <?php echo esc_attr($rule_set['badge_bg_color']); ?>; color: <?php echo esc_attr($rule_set['badge_text_color']); ?>; margin-right: 10px;"><?php echo esc_html($rule_set['badge_text']); ?></span>
                            <p class="description"><?php esc_html_e( 'Choose a background colour and a text colour for the badge', 'incentiv' ); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label><?php esc_html_e( 'Where to show the badge', 'incentiv' ); ?></label>
                        </th>
                        <td>
                            <label style="margin-left: 20px;">
                                <input type="checkbox" name="badge_show_on_product" <?php checked($rule_set['badge_show_on_product'], 'yes'); ?>>
                                <?php echo esc_html_x( 'On the product page', 'badge', 'incentiv' ); ?>
                            </label>
                            <label>
                                <input type="checkbox" name="badge_show_on_archive" <?php checked($rule_set['badge_show_on_archive'], 'yes'); ?>>
                                <?php esc_html_e( 'On the category/shop page', 'incentiv' ); ?>
                            </label>
                            <p class="description"><?php esc_html_e( 'Choose where to show the discount badge', 'incentiv' ); ?></p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Sale Price Display Card -->
            <div class="mmd-card">
                <h2 class="mmd-card-title"><span class="dashicons dashicons-money-alt"></span> <?php esc_html_e( 'Sale price display', 'incentiv' ); ?></h2>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="show_sale_price"><?php esc_html_e( 'Show sale price', 'incentiv' ); ?></label>
                        </th>
                        <td>
                            <label class="mmd-switch">
                                <input type="checkbox" name="show_sale_price" id="show_sale_price" <?php checked(isset($rule_set['show_sale_price']) ? $rule_set['show_sale_price'] : 'no', 'yes'); ?>>
                                <span class="mmd-slider"></span>
                            </label>
                            <p class="description"><?php esc_html_e( 'Show the regular price struck through + the discounted price on the product (like a regular WooCommerce sale)', 'incentiv' ); ?></p>
                        </td>
                    </tr>

                    <tr class="mmd-sale-price-setting" style="<?php echo (isset($rule_set['show_sale_price']) && $rule_set['show_sale_price'] === 'yes') ? '' : 'display:none;'; ?>">
                        <th scope="row">
                            <label for="show_sale_price_on"><?php esc_html_e( 'Where to show', 'incentiv' ); ?></label>
                        </th>
                        <td>
                            <select name="show_sale_price_on" id="show_sale_price_on" class="regular-text">
                                <option value="both" <?php selected(isset($rule_set['show_sale_price_on']) ? $rule_set['show_sale_price_on'] : 'both', 'both'); ?>><?php esc_html_e( 'Everywhere', 'incentiv' ); ?></option>
                                <option value="product" <?php selected(isset($rule_set['show_sale_price_on']) ? $rule_set['show_sale_price_on'] : '', 'product'); ?>><?php esc_html_e( 'Product page only', 'incentiv' ); ?></option>
                                <option value="archive" <?php selected(isset($rule_set['show_sale_price_on']) ? $rule_set['show_sale_price_on'] : '', 'archive'); ?>><?php echo wp_kses_post( __( 'Shop &amp; Archive Only', 'incentiv' ) ); ?></option>
                            </select>
                        </td>
                    </tr>

                    <tr class="mmd-sale-price-setting" style="<?php echo (isset($rule_set['show_sale_price']) && $rule_set['show_sale_price'] === 'yes') ? '' : 'display:none;'; ?>">
                        <th scope="row">
                            <label for="sale_price_tier"><?php esc_html_e( 'Which discount tier to show', 'incentiv' ); ?></label>
                        </th>
                        <td>
                            <select name="sale_price_tier" id="sale_price_tier" class="regular-text">
                                <option value="first" <?php selected(isset($rule_set['sale_price_tier']) ? $rule_set['sale_price_tier'] : 'first', 'first'); ?>><?php esc_html_e( 'First tier (lowest discount)', 'incentiv' ); ?></option>
                                <option value="last" <?php selected(isset($rule_set['sale_price_tier']) ? $rule_set['sale_price_tier'] : '', 'last'); ?>><?php esc_html_e( 'Last tier (highest discount)', 'incentiv' ); ?></option>
                            </select>
                            <p class="description"><?php esc_html_e( 'Choose which discount tier is shown as the sale price. Only relevant for rules with several tiers', 'incentiv' ); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="show_discount_in_cart"><?php esc_html_e( 'Show discount in cart', 'incentiv' ); ?></label>
                        </th>
                        <td>
                            <label class="mmd-switch">
                                <input type="checkbox" name="show_discount_in_cart" id="show_discount_in_cart" <?php checked(isset($rule_set['show_discount_in_cart']) ? $rule_set['show_discount_in_cart'] : 'no', 'yes'); ?>>
                                <span class="mmd-slider"></span>
                            </label>
                            <p class="description">
                                <?php esc_html_e( 'When off (default): cart items, the mini cart and checkout show the regular price. The discount is only shown as a "You saved" / "Discount" line in the cart totals.', 'incentiv' ); ?><br>
                                When OFF (default): cart, mini-cart and checkout show regular item prices. The discount appears only as a "You saved" notice<br>
                                When ON: cart items show the discounted price (strikethrough regular). Use this only if your theme correctly supports per-item sale display.
                            </p>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="mmd-card">
                <h2 class="mmd-card-title"><span class="dashicons dashicons-format-image"></span> <?php esc_html_e( 'Display banner', 'incentiv' ); ?></h2>
                <p class="description" style="margin-bottom:15px;">
                    <?php esc_html_e( 'A banner image shown for products that match this rule. It can be shown with a shortcode', 'incentiv' ); ?> <code>[mmd_rule_banner]</code> <?php esc_html_e( 'on the product page, or with an Elementor widget.', 'incentiv' ); ?><br>
                    Banner image displayed for products matching this rule. Available via shortcode <code>[mmd_rule_banner]</code> on a product page, or via the Elementor widget.
                </p>

                <table class="form-table">
                    <tr>
                        <th scope="row"><label><?php esc_html_e( 'Banner image', 'incentiv' ); ?></label></th>
                        <td>
                            <?php
                            $banner_id = isset($rule_set['banner_image_id']) ? intval($rule_set['banner_image_id']) : 0;
                            $banner_url_attr = $banner_id ? wp_get_attachment_image_url($banner_id, 'large') : (isset($rule_set['banner_image_url']) ? $rule_set['banner_image_url'] : '');
                            ?>
                            <div class="mmd-banner-image-wrapper">
                                <input type="hidden" name="banner_image_id" id="banner_image_id" value="<?php echo esc_attr($banner_id); ?>">
                                <img id="banner_image_preview" src="<?php echo esc_url($banner_url_attr); ?>" style="max-width:400px;display:<?php echo $banner_url_attr ? 'block' : 'none'; ?>;margin-bottom:10px;border:1px solid #ddd;border-radius:4px;">
                                <p>
                                    <button type="button" class="button" id="banner_image_upload_btn"><?php esc_html_e( 'Choose image', 'incentiv' ); ?></button>
                                    <button type="button" class="button" id="banner_image_remove_btn" style="<?php echo $banner_id ? '' : 'display:none;'; ?>"><?php echo esc_html_x( 'Remove', 'banner', 'incentiv' ); ?></button>
                                </p>
                            </div>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="banner_image_url"><?php esc_html_e( 'or paste a URL', 'incentiv' ); ?></label></th>
                        <td>
                            <input type="url" name="banner_image_url" id="banner_image_url" class="regular-text" value="<?php echo esc_attr(isset($rule_set['banner_image_url']) ? $rule_set['banner_image_url'] : ''); ?>" placeholder="https://example.com/banner.jpg">
                            <p class="description"><?php esc_html_e( 'Used as a fallback if no image is chosen', 'incentiv' ); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="banner_link_url"><?php esc_html_e( 'Link (optional)', 'incentiv' ); ?></label></th>
                        <td>
                            <input type="url" name="banner_link_url" id="banner_link_url" class="regular-text" value="<?php echo esc_attr(isset($rule_set['banner_link_url']) ? $rule_set['banner_link_url'] : ''); ?>" placeholder="https://...">
                            <p class="description"><?php esc_html_e( 'If set, clicking the banner leads to this URL', 'incentiv' ); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label><?php esc_html_e( 'Shortcode', 'incentiv' ); ?></label></th>
                        <td>
                            <code>[mmd_rule_banner]</code>
                            <p class="description"><?php esc_html_e( 'Place the shortcode on a product page. It shows the banner of the first rule that matches the current product.', 'incentiv' ); ?><br>Place this shortcode on a product page. It displays the banner of the first rule that matches the current product.</p>
                            <p>
                                <strong><?php esc_html_e( 'Also available:', 'incentiv' ); ?></strong> <code>[mmd_rule_banner rule_id="<?php echo esc_attr(isset($rule_set['id']) ? $rule_set['id'] : ''); ?>"]</code> <?php esc_html_e( '— shows the banner of this specific rule.', 'incentiv' ); ?><br>
                                Also: pass <code>rule_id</code> to force display of this specific rule's banner.
                            </p>
                        </td>
                    </tr>
                </table>

                <script>
                jQuery(function($) {
                    var frame;
                    $('#banner_image_upload_btn').on('click', function(e) {
                        e.preventDefault();
                        if (frame) { frame.open(); return; }
                        frame = wp.media({
                            title: <?php echo wp_json_encode( __( 'Choose a banner image', 'incentiv' ) ); ?>,
                            button: { text: <?php echo wp_json_encode( __( 'Use image', 'incentiv' ) ); ?> },
                            multiple: false
                        });
                        frame.on('select', function() {
                            var attachment = frame.state().get('selection').first().toJSON();
                            $('#banner_image_id').val(attachment.id);
                            $('#banner_image_preview').attr('src', attachment.url).show();
                            $('#banner_image_remove_btn').show();
                        });
                        frame.open();
                    });
                    $('#banner_image_remove_btn').on('click', function(e) {
                        e.preventDefault();
                        $('#banner_image_id').val('');
                        $('#banner_image_preview').attr('src', '').hide();
                        $(this).hide();
                    });
                });
                </script>
            </div>

            <script>
            jQuery(function($) {
                $('#show_sale_price').on('change', function() {
                    if ($(this).is(':checked')) {
                        $('.mmd-sale-price-setting').show();
                    } else {
                        $('.mmd-sale-price-setting').hide();
                    }
                });
            });
            </script>

            <!-- Global Notice Card -->
            <div class="mmd-card">
                <h2 class="mmd-card-title"><span class="dashicons dashicons-megaphone"></span> <?php esc_html_e( 'Site-wide message', 'incentiv' ); ?></h2>
                <p class="description" style="margin-top:-5px;"><?php esc_html_e( 'When several rules have a site-wide message, the messages rotate automatically in a carousel', 'incentiv' ); ?></p>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="global_notice_enabled"><?php esc_html_e( 'Show message on the site', 'incentiv' ); ?></label>
                        </th>
                        <td>
                            <label class="mmd-switch">
                                <input type="checkbox" name="global_notice_enabled" id="global_notice_enabled" <?php checked($rule_set['global_notice_enabled'], 'yes'); ?>>
                                <span class="mmd-slider"></span>
                            </label>
                            <p class="description"><?php esc_html_e( 'Show a message bar (banner) to all visitors when the discount rule is active', 'incentiv' ); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="global_notice_text"><?php esc_html_e( 'Message text', 'incentiv' ); ?></label>
                        </th>
                        <td>
                            <input type="text" name="global_notice_text" id="global_notice_text" value="<?php echo esc_attr($rule_set['global_notice_text']); ?>" class="large-text" placeholder="<?php esc_attr_e( '🔥 Special offer! Buy 3 and get 30% off', 'incentiv' ); ?>">
                            <p class="description"><?php esc_html_e( 'The text shown in the message bar. Use emoji to grab attention', 'incentiv' ); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="global_notice_icon"><?php esc_html_e( 'Icon', 'incentiv' ); ?></label>
                        </th>
                        <td>
                            <div class="mmd-icon-picker-wrap">
                                <input type="text" name="global_notice_icon" id="global_notice_icon" value="<?php echo esc_attr($rule_set['global_notice_icon']); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Click to pick an icon', 'incentiv' ); ?>" readonly style="cursor:pointer;max-width:300px;">
                                <button type="button" class="button" id="mmd_clear_icon" title="<?php esc_attr_e( 'Clear icon', 'incentiv' ); ?>"><span class="dashicons dashicons-no-alt" style="font-size:16px;width:16px;height:16px;vertical-align:middle;"></span></button>
                                <span id="mmd_icon_preview" style="font-size:24px;vertical-align:middle;margin-right:10px;"><?php echo esc_html($rule_set['global_notice_icon']); ?></span>
                            </div>
                            <div id="mmd_icon_picker" class="mmd-icon-picker" style="display:none;">
                                <div class="mmd-icon-picker-tabs">
                                    <button type="button" class="button mmd-icon-tab active" data-tab="emoji">Emoji</button>
                                    <button type="button" class="button mmd-icon-tab" data-tab="dashicons">Dashicons</button>
                                </div>
                                <div class="mmd-icon-picker-search">
                                    <input type="text" id="mmd_icon_search" placeholder="<?php esc_attr_e( 'Search...', 'incentiv' ); ?>" class="regular-text" style="width:100%;margin:8px 0;">
                                </div>
                                <div class="mmd-icon-tab-content" id="mmd_emoji_tab">
                                    <div class="mmd-icon-category">
                                        <strong><?php esc_html_e( 'Recommended for promotions', 'incentiv' ); ?></strong>
                                        <div class="mmd-icon-grid">
                                            <?php
                                            $popular_emojis = array('🔥','🎁','⚡','🏷️','💰','🛍️','💎','✨','🎉','🎊','💥','🌟','⭐','🏆','🎯','💸','🤑','📢','🔔','💡','❤️','🎀','🎈','🛒','📦','🆓','💯','🔖','🎟️','🏅');
                                            foreach ($popular_emojis as $emoji): ?>
                                                <span class="mmd-icon-option mmd-emoji-option" data-value="<?php echo esc_attr($emoji); ?>" title="<?php echo esc_attr($emoji); ?>"><?php echo esc_html( $emoji ); ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <div class="mmd-icon-category">
                                        <strong><?php esc_html_e( 'Faces', 'incentiv' ); ?></strong>
                                        <div class="mmd-icon-grid">
                                            <?php
                                            $face_emojis = array('😍','🤩','😎','🥳','😊','🤗','😱','🫣','🤫','🤭');
                                            foreach ($face_emojis as $emoji): ?>
                                                <span class="mmd-icon-option mmd-emoji-option" data-value="<?php echo esc_attr($emoji); ?>"><?php echo esc_html( $emoji ); ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <div class="mmd-icon-category">
                                        <strong><?php esc_html_e( 'Arrows', 'incentiv' ); ?></strong>
                                        <div class="mmd-icon-grid">
                                            <?php
                                            $arrow_emojis = array('➡️','⬅️','⬆️','⬇️','↗️','↘️','🔄','↩️','👉','👈','👆','👇');
                                            foreach ($arrow_emojis as $emoji): ?>
                                                <span class="mmd-icon-option mmd-emoji-option" data-value="<?php echo esc_attr($emoji); ?>"><?php echo esc_html( $emoji ); ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <div class="mmd-icon-category">
                                        <strong><?php esc_html_e( 'Symbols', 'incentiv' ); ?></strong>
                                        <div class="mmd-icon-grid">
                                            <?php
                                            $symbol_emojis = array('✅','❌','⚠️','ℹ️','❓','❗','✳️','🔴','🟢','🔵','🟡','🟠','🟣','⚫','⚪','🔶','🔷','🔸','🔹','💠');
                                            foreach ($symbol_emojis as $emoji): ?>
                                                <span class="mmd-icon-option mmd-emoji-option" data-value="<?php echo esc_attr($emoji); ?>"><?php echo esc_html( $emoji ); ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="mmd-icon-tab-content" id="mmd_dashicons_tab" style="display:none;">
                                    <div class="mmd-icon-grid mmd-dashicons-grid">
                                        <?php
                                        $dashicon_list = array(
                                            'megaphone' => 'megaphone', 'cart' => 'cart', 'tag' => 'tag', 'money-alt' => 'money',
                                            'star-filled' => 'star', 'heart' => 'heart', 'awards' => 'award', 'tickets-alt' => 'tickets',
                                            'flag' => 'flag', 'marker' => 'marker', 'info' => 'info', 'warning' => 'warning',
                                            'lightbulb' => 'lightbulb', 'bell' => 'bell', 'yes' => 'checkmark', 'no' => 'x-mark',
                                            'star-empty' => 'star-empty', 'plus-alt' => 'plus', 'minus' => 'minus', 'dismiss' => 'dismiss',
                                            'thumbs-up' => 'thumbs-up', 'thumbs-down' => 'thumbs-down', 'share' => 'share', 'lock' => 'lock',
                                            'unlock' => 'unlock', 'email' => 'email', 'clock' => 'clock', 'calendar-alt' => 'calendar',
                                            'store' => 'store', 'admin-home' => 'home', 'groups' => 'groups', 'visibility' => 'eye',
                                        );
                                        foreach ($dashicon_list as $icon => $label): ?>
                                            <span class="mmd-icon-option mmd-dashicon-option" data-value="dashicons-<?php echo esc_attr($icon); ?>" title="<?php echo esc_attr($label); ?>"><span class="dashicons dashicons-<?php echo esc_attr($icon); ?>"></span></span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                            <p class="description"><?php esc_html_e( 'Choose an emoji or a Dashicons icon to show before the text', 'incentiv' ); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="global_notice_bg_color"><?php esc_html_e( 'Message colours', 'incentiv' ); ?></label>
                        </th>
                        <td>
                            <input type="color" name="global_notice_bg_color" id="global_notice_bg_color" value="<?php echo esc_attr($rule_set['global_notice_bg_color']); ?>">
                            <input type="color" name="global_notice_text_color" id="global_notice_text_color" value="<?php echo esc_attr($rule_set['global_notice_text_color']); ?>">
                            <span style="margin-right: 10px;"><?php esc_html_e( 'Background / text', 'incentiv' ); ?></span>
                            <div class="mmd-global-notice-preview" style="display: inline-block; padding: 8px 20px; border-radius: 0; font-size: 14px; font-weight: 600; background: <?php echo esc_attr($rule_set['global_notice_bg_color']); ?>; color: <?php echo esc_attr($rule_set['global_notice_text_color']); ?>; margin-top: 8px;">
                                <?php echo esc_html($rule_set['global_notice_text'] ?: esc_html__( '🔥 Special offer! Buy 3 and get 30% off', 'incentiv' )); ?>
                            </div>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="global_notice_position"><?php esc_html_e( 'Position', 'incentiv' ); ?></label>
                        </th>
                        <td>
                            <select name="global_notice_position" id="global_notice_position">
                                <option value="top" <?php selected($rule_set['global_notice_position'], 'top'); ?>><?php esc_html_e( 'Sticky Top Bar', 'incentiv' ); ?></option>
                                <option value="bottom" <?php selected($rule_set['global_notice_position'], 'bottom'); ?>><?php esc_html_e( 'Sticky Bottom Bar', 'incentiv' ); ?></option>
                                <option value="below_header" <?php selected($rule_set['global_notice_position'], 'below_header'); ?>><?php esc_html_e( 'Below Header (inline)', 'incentiv' ); ?></option>
                                <option value="above_footer" <?php selected($rule_set['global_notice_position'], 'above_footer'); ?>><?php esc_html_e( 'Above Footer (inline)', 'incentiv' ); ?></option>
                                <option value="float_left" <?php selected($rule_set['global_notice_position'], 'float_left'); ?>><?php esc_html_e( 'Floating Bottom-Left', 'incentiv' ); ?></option>
                                <option value="float_right" <?php selected($rule_set['global_notice_position'], 'float_right'); ?>><?php esc_html_e( 'Floating Bottom-Right', 'incentiv' ); ?></option>
                            </select>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="global_notice_mobile_position"><?php esc_html_e( 'Mobile position', 'incentiv' ); ?></label>
                        </th>
                        <td>
                            <select name="global_notice_mobile_position" id="global_notice_mobile_position">
                                <option value="same" <?php selected($rule_set['global_notice_mobile_position'], 'same'); ?>><?php esc_html_e( 'Same as Desktop', 'incentiv' ); ?></option>
                                <option value="top" <?php selected($rule_set['global_notice_mobile_position'], 'top'); ?>><?php esc_html_e( 'Sticky Top', 'incentiv' ); ?></option>
                                <option value="bottom" <?php selected($rule_set['global_notice_mobile_position'], 'bottom'); ?>><?php esc_html_e( 'Sticky Bottom', 'incentiv' ); ?></option>
                                <option value="below_header" <?php selected($rule_set['global_notice_mobile_position'], 'below_header'); ?>><?php esc_html_e( 'Below Header (inline, mobile)', 'incentiv' ); ?></option>
                                <option value="hidden" <?php selected($rule_set['global_notice_mobile_position'], 'hidden'); ?>><?php esc_html_e( 'Hidden on Mobile', 'incentiv' ); ?></option>
                            </select>
                            <p class="description"><?php esc_html_e( 'A different position on mobile devices. For example, floating in a corner doesn\'t always look good on mobile', 'incentiv' ); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="global_notice_layout"><?php esc_html_e( 'Design', 'incentiv' ); ?></label>
                        </th>
                        <td>
                            <select name="global_notice_layout" id="global_notice_layout">
                                <option value="bar" <?php selected($rule_set['global_notice_layout'], 'bar'); ?>><?php esc_html_e( 'Standard Bar', 'incentiv' ); ?></option>
                                <option value="slim" <?php selected($rule_set['global_notice_layout'], 'slim'); ?>><?php esc_html_e( 'Slim Bar', 'incentiv' ); ?></option>
                                <option value="bold" <?php selected($rule_set['global_notice_layout'], 'bold'); ?>><?php esc_html_e( 'Bold Bar', 'incentiv' ); ?></option>
                                <option value="gradient" <?php selected($rule_set['global_notice_layout'], 'gradient'); ?>><?php echo esc_html_x( 'Gradient', 'style', 'incentiv' ); ?></option>
                                <option value="marquee" <?php selected($rule_set['global_notice_layout'], 'marquee'); ?>><?php esc_html_e( 'Scrolling Marquee', 'incentiv' ); ?></option>
                            </select>
                            <p class="description"><?php esc_html_e( 'With the "Scrolling Marquee" style, the text scrolls continuously from side to side', 'incentiv' ); ?></p>
                        </td>
                    </tr>

                    <tr id="marquee_direction_row" style="display: <?php echo ($rule_set['global_notice_layout'] === 'marquee') ? 'table-row' : 'none'; ?>;">
                        <th scope="row">
                            <label for="global_notice_marquee_direction"><?php esc_html_e( 'Scroll direction', 'incentiv' ); ?></label>
                        </th>
                        <td>
                            <select name="global_notice_marquee_direction" id="global_notice_marquee_direction">
                                <option value="rtl" <?php selected($rule_set['global_notice_marquee_direction'], 'rtl'); ?>><?php esc_html_e( 'Right to Left (RTL)', 'incentiv' ); ?></option>
                                <option value="ltr" <?php selected($rule_set['global_notice_marquee_direction'], 'ltr'); ?>><?php esc_html_e( 'Left to Right (LTR)', 'incentiv' ); ?></option>
                                <option value="alternate" <?php selected($rule_set['global_notice_marquee_direction'], 'alternate'); ?>><?php esc_html_e( 'Alternating (Bounce)', 'incentiv' ); ?></option>
                            </select>
                            <p class="description"><?php esc_html_e( 'The direction the text moves in the "Scrolling Marquee" style', 'incentiv' ); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="global_notice_link_url"><?php esc_html_e( 'Link (optional)', 'incentiv' ); ?></label>
                        </th>
                        <td>
                            <input type="url" name="global_notice_link_url" id="global_notice_link_url" value="<?php echo esc_attr($rule_set['global_notice_link_url']); ?>" class="regular-text" placeholder="https://example.com/shop">
                            <input type="text" name="global_notice_link_text" id="global_notice_link_text" value="<?php echo esc_attr($rule_set['global_notice_link_text']); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'To the shop →', 'incentiv' ); ?>" style="margin-top: 5px;">
                            <p class="description"><?php esc_html_e( 'Add a button/link to the message (optional). First line: URL, second line: button text', 'incentiv' ); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="global_notice_pages"><?php esc_html_e( 'Show on pages', 'incentiv' ); ?></label>
                        </th>
                        <td>
                            <select name="global_notice_pages" id="global_notice_pages">
                                <option value="all" <?php selected($rule_set['global_notice_pages'], 'all'); ?>><?php esc_html_e( 'All Pages', 'incentiv' ); ?></option>
                                <option value="shop_only" <?php selected($rule_set['global_notice_pages'], 'shop_only'); ?>><?php echo wp_kses_post( __( 'Shop & Categories Only', 'incentiv' ) ); ?></option>
                                <option value="product_only" <?php selected($rule_set['global_notice_pages'], 'product_only'); ?>><?php esc_html_e( 'Product Pages Only', 'incentiv' ); ?></option>
                                <option value="shop_and_product" <?php selected($rule_set['global_notice_pages'], 'shop_and_product'); ?>><?php echo wp_kses_post( __( 'Shop & Product Pages', 'incentiv' ) ); ?></option>
                                <option value="homepage" <?php selected($rule_set['global_notice_pages'], 'homepage'); ?>><?php esc_html_e( 'Homepage Only', 'incentiv' ); ?></option>
                                <option value="not_checkout" <?php selected($rule_set['global_notice_pages'], 'not_checkout'); ?>><?php esc_html_e( 'Everywhere except Checkout', 'incentiv' ); ?></option>
                            </select>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="global_notice_rotate_speed"><?php esc_html_e( 'Rotation speed', 'incentiv' ); ?></label>
                        </th>
                        <td>
                            <input type="number" name="global_notice_rotate_speed" id="global_notice_rotate_speed" value="<?php echo esc_attr($rule_set['global_notice_rotate_speed']); ?>" min="2" max="30" step="1" class="small-text"> <?php esc_html_e( 'seconds', 'incentiv' ); ?>
                            <p class="description"><?php esc_html_e( 'How many seconds each message is shown before moving to the next one (when several messages are active)', 'incentiv' ); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="global_notice_dismissible"><?php esc_html_e( 'Dismissible', 'incentiv' ); ?></label>
                        </th>
                        <td>
                            <label class="mmd-switch">
                                <input type="checkbox" name="global_notice_dismissible" id="global_notice_dismissible" <?php checked($rule_set['global_notice_dismissible'], 'yes'); ?>>
                                <span class="mmd-slider"></span>
                            </label>
                            <p class="description"><?php esc_html_e( 'Let visitors close the message', 'incentiv' ); ?></p>
                        </td>
                    </tr>

                    <tr id="dismiss_limit_row" style="display: <?php echo ($rule_set['global_notice_dismissible'] === 'yes') ? 'table-row' : 'none'; ?>;">
                        <th scope="row">
                            <label for="global_notice_dismiss_limit"><?php esc_html_e( 'Show again after closing', 'incentiv' ); ?></label>
                        </th>
                        <td>
                            <select name="global_notice_dismiss_limit" id="global_notice_dismiss_limit">
                                <option value="0" <?php selected($rule_set['global_notice_dismiss_limit'], '0'); ?>><?php esc_html_e( 'Unlimited - always reappear', 'incentiv' ); ?></option>
                                <option value="1" <?php selected($rule_set['global_notice_dismiss_limit'], '1'); ?>><?php esc_html_e( 'Once only - never show again', 'incentiv' ); ?></option>
                                <option value="2" <?php selected($rule_set['global_notice_dismiss_limit'], '2'); ?>><?php esc_html_e( 'Up to 2 times', 'incentiv' ); ?></option>
                                <option value="3" <?php selected($rule_set['global_notice_dismiss_limit'], '3'); ?>><?php esc_html_e( 'Up to 3 times', 'incentiv' ); ?></option>
                                <option value="5" <?php selected($rule_set['global_notice_dismiss_limit'], '5'); ?>><?php esc_html_e( 'Up to 5 times', 'incentiv' ); ?></option>
                                <option value="10" <?php selected($rule_set['global_notice_dismiss_limit'], '10'); ?>><?php esc_html_e( 'Up to 10 times', 'incentiv' ); ?></option>
                            </select>
                            <p class="description"><?php esc_html_e( 'How many times the message reappears after the visitor closes it', 'incentiv' ); ?></p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Rule Exclusion Card -->
            <div class="mmd-card">
                <h2 class="mmd-card-title"><span class="dashicons dashicons-dismiss"></span> <?php esc_html_e( 'Exclude based on other rules', 'incentiv' ); ?></h2>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="excluded_by_rules"><?php esc_html_e( 'Don\'t apply this rule to products that get a discount from:', 'incentiv' ); ?></label>
                        </th>
                        <td>
                            <?php
                            $all_rule_sets = MMD_Discount_Rules::get_all_rule_sets();
                            $current_excluded = isset($rule_set['excluded_by_rules']) && is_array($rule_set['excluded_by_rules']) ? $rule_set['excluded_by_rules'] : array();
                            ?>
                            <select name="excluded_by_rules[]" id="excluded_by_rules" multiple class="regular-text" style="min-height: 120px; width: 100%; max-width: 600px;">
                                <?php foreach ($all_rule_sets as $other_rule):
                                    if ($other_rule['id'] === $rule_set['id']) continue; // Don't show self
                                ?>
                                    <option value="<?php echo esc_attr($other_rule['id']); ?>" <?php echo in_array($other_rule['id'], $current_excluded) ? 'selected' : ''; ?>>
                                        <?php echo esc_html($other_rule['name']); ?> (<?php echo $other_rule['enabled'] === 'yes' ? esc_html__( 'Active', 'incentiv' ) : esc_html__( 'Not active', 'incentiv' ); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">
                                <?php esc_html_e( 'Choose other discount rules. If a product already gets a discount from one of the selected rules, this rule doesn\'t apply to it.', 'incentiv' ); ?><br>
                                <em><?php esc_html_e( 'Example: set a discount for the whole category, but exclude products that already get a discount from another specific rule.', 'incentiv' ); ?></em>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>

        </div>
        </div><!-- /.mmd-editor-main -->

        <!-- Sticky live preview -->
        <aside class="mmd-editor-preview" id="mmd-live-preview">
            <div class="mmd-preview-label"><span class="msr">visibility</span><?php echo wp_kses_post( __( 'Live preview', 'incentiv' ) ); ?></div>

            <div class="mmd-preview-product-card">
                <div class="mmd-preview-hero">
                    <span class="msr">local_cafe</span>
                    <span class="mmd-preview-badge"><?php esc_html_e( 'Quantity deal', 'incentiv' ); ?></span>
                </div>
                <div class="mmd-preview-body">
                    <div class="mmd-preview-name mmd-lp-rule-name"><?php echo esc_html($rule_set['name'] ? $rule_set['name'] : esc_html__( 'My rule', 'incentiv' )); ?></div>
                    <div class="mmd-preview-price"><?php echo function_exists('wc_price') ? wp_kses_post(wc_price(42)) : '42.00'; ?></div>
                    <div class="mmd-lp-tiers-wrap">
                        <div class="mmd-preview-tiers">
                            <div class="mmd-preview-tiers-head"><span class="msr">savings</span><?php echo esc_html_x( 'Buy more, save more', 'preview', 'incentiv' ); ?></div>
                            <div class="mmd-lp-tiers-body"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mmd-preview-cartmsg mmd-lp-cartmsg">
                <div class="mmd-preview-cartmsg-title"><span class="msr">shopping_cart</span><?php esc_html_e( 'Cart message', 'incentiv' ); ?></div>
                <div class="mmd-preview-notice mmd-lp-cartmsg-box"></div>
            </div>
        </aside>
        </div><!-- /.mmd-editor-grid -->

        <p class="submit mmd-rule-submit-bar">
            <button type="button" class="mmd-btn mmd-btn-outline" id="mmd-step-prev"><span class="msr mmd-dir-icon">arrow_forward</span> <?php esc_html_e( 'Previous', 'incentiv' ); ?></button>
            <span id="mmd-step-indicator" style="font-size:12.5px;font-weight:700;color:var(--mmd-text-muted);"></span>
            <button type="button" class="mmd-btn mmd-btn-primary" id="mmd-step-next"><?php esc_html_e( 'Next', 'incentiv' ); ?> <span class="msr mmd-dir-icon">arrow_back</span></button>
            <span style="flex:1;"></span>
            <span id="mmd-draft-status" class="mmd-draft-status" aria-live="polite"></span>
            <button type="button" id="mmd-save-draft" class="mmd-btn mmd-btn-outline" style="padding:10px 18px;"><span class="msr">save</span> <?php esc_html_e( 'Save draft', 'incentiv' ); ?></button>
            <button type="submit" name="mmd_save_rule_set" class="mmd-btn mmd-btn-green" style="padding:10px 20px;"><span class="msr">check</span> <?php esc_html_e( 'Publish rule', 'incentiv' ); ?></button>
            <a href="<?php echo esc_url( admin_url('admin.php?page=mix-match-discounts') ); ?>" class="mmd-btn mmd-btn-ghost"><?php esc_html_e( 'Cancel', 'incentiv' ); ?></a>
        </p>

        <!-- Preview modal -->
        <div id="mmd-preview-modal" class="mmd-preview-modal" style="display:none;" aria-hidden="true">
            <div class="mmd-preview-overlay" data-close></div>
            <div class="mmd-preview-dialog" role="dialog" aria-labelledby="mmd-preview-title">
                <div class="mmd-preview-header">
                    <h2 id="mmd-preview-title"><?php esc_html_e( 'Rule preview', 'incentiv' ); ?></h2>
                    <button type="button" class="mmd-preview-close button-link" data-close aria-label="<?php esc_attr_e( 'Close', 'incentiv' ); ?>">
                        <span class="dashicons dashicons-no-alt"></span>
                    </button>
                </div>
                <div class="mmd-preview-body">
                    <p class="description"><?php esc_html_e( 'Choose one or more products to see how the rule affects them in the cart (before saving).', 'incentiv' ); ?><br>Select one or more products to see how this rule would apply (without saving).</p>
                    <table class="widefat striped" id="mmd-preview-products-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Product', 'incentiv' ); ?></th>
                                <th><?php esc_html_e( 'Quantity', 'incentiv' ); ?></th>
                                <th><?php esc_html_e( 'Unit price', 'incentiv' ); ?></th>
                                <th><?php esc_html_e( 'Price after discount', 'incentiv' ); ?></th>
                                <th><?php esc_html_e( 'Savings', 'incentiv' ); ?></th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="mmd-preview-empty">
                                <td colspan="6" style="text-align:center;color:#646970;padding:24px;"><?php esc_html_e( 'No products selected yet. Click "Add product" to start.', 'incentiv' ); ?><br>No products selected yet. Click "Add product".</td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="3"><?php echo esc_html_x( 'Total', 'preview', 'incentiv' ); ?></th>
                                <th id="mmd-preview-total-after">—</th>
                                <th id="mmd-preview-total-saved">—</th>
                                <th></th>
                            </tr>
                        </tfoot>
                    </table>
                    <p>
                        <button type="button" class="button" id="mmd-preview-add">
                            <span class="dashicons dashicons-plus"></span> <?php esc_html_e( 'Add product', 'incentiv' ); ?>
                        </button>
                    </p>
                    <div class="mmd-preview-summary"></div>
                </div>
                <div class="mmd-preview-footer">
                    <button type="button" class="button" data-close><?php esc_html_e( 'Close', 'incentiv' ); ?></button>
                </div>
            </div>
        </div>

        <style>
        /* Step visibility */
        .mmd-card[data-mmd-step]:not(.is-step-active) { display: none; }

        /* ---- MMD Rule Toolbar ---- */
        .mmd-rule-toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            justify-content: space-between;
            align-items: center;
            margin: 0 0 16px;
            padding: 10px 14px;
            background: #fff;
            border: 1px solid var(--mmd-border);
            border-radius: var(--mmd-radius-md);
            position: sticky;
            top: 32px;
            z-index: 10;
            box-shadow: var(--mmd-shadow-sm);
        }
        .mmd-rule-toolbar-actions { display: flex; gap: 6px; flex-wrap: wrap; align-items: center; }
        .mmd-rule-toolbar-actions .button .dashicons { font-size: 16px; width: 16px; height: 16px; vertical-align: middle; }
        .mmd-rule-toolbar-jump { display: flex; gap: 8px; align-items: center; }
        .mmd-rule-toolbar-jump label { color: var(--mmd-text-muted); font-size: 12.5px; font-weight: 600; }
        .mmd-rule-toolbar-jump select { min-width: 200px; }

        /* ---- Collapsible Cards ---- */
        .mmd-card { padding: 0 !important; overflow: hidden; transition: box-shadow 0.15s ease; }
        .mmd-card:hover { box-shadow: var(--mmd-shadow-md); }
        .mmd-card-title {
            user-select: none;
            display: flex !important;
            align-items: center;
            gap: 10px;
            margin: 0 !important;
            padding: 16px 20px !important;
            background: #fff;
            border-bottom: 1px solid transparent;
            position: relative;
            font-size: 15px;
            font-weight: 700;
        }
        .mmd-card.is-open .mmd-card-title { border-bottom-color: var(--mmd-border-light); }
        .mmd-card-body { padding: 16px 20px 20px; display: none; }
        .mmd-card.is-open .mmd-card-body { display: block; }
        .mmd-card.has-search-hit { box-shadow: 0 0 0 2px var(--mmd-primary); }

        /* Sticky save bar */
        .mmd-rule-submit-bar {
            position: sticky;
            bottom: 0;
            background: rgba(238, 241, 246, .92);
            backdrop-filter: blur(4px);
            padding: 12px 0;
            margin: 20px 0 0;
            border-top: 1px solid var(--mmd-border);
            z-index: 9;
            display: flex;
            gap: 8px;
            align-items: center;
        }

        /* ---- Preview Modal ---- */
        .mmd-preview-modal { position: fixed; inset: 0; z-index: 99999; display: flex; align-items: center; justify-content: center; font-family: var(--mmd-font); }
        .mmd-preview-overlay { position: absolute; inset: 0; background: rgba(16,24,40,0.55); backdrop-filter: blur(2px); }
        .mmd-preview-dialog { position: relative; width: 90%; max-width: 900px; max-height: 90vh; background: #fff; border-radius: 18px; box-shadow: 0 30px 70px rgba(16,24,40,0.4); display: flex; flex-direction: column; overflow: hidden; }
        .mmd-preview-header { display: flex; justify-content: space-between; align-items: center; padding: 18px 22px; background: var(--mmd-grad-brand); color: #fff; }
        .mmd-preview-header h2 { margin: 0; font-size: 17px; font-weight: 800; color: #fff; }
        .mmd-preview-close { padding: 4px; color: #fff !important; }
        .mmd-preview-body { padding: 20px; overflow-y: auto; flex: 1; }
        .mmd-preview-footer { padding: 12px 20px; border-top: 1px solid var(--mmd-border-light); display: flex; justify-content: flex-end; gap: 8px; background: #fafbfc; }
        #mmd-preview-products-table th, #mmd-preview-products-table td { padding: 8px 10px; vertical-align: middle; }
        #mmd-preview-products-table .mmd-preview-product-search { width: 100%; }
        #mmd-preview-products-table input[type="number"] { width: 70px; }
        .mmd-preview-summary { margin-top: 16px; padding: 13px 15px; background: var(--mmd-success-light); border-radius: 10px; font-size: 13px; }
        .mmd-preview-summary strong { color: var(--mmd-success); }

        /* Mobile */
        @media (max-width: 782px) {
            .mmd-rule-toolbar {
                position: static;
                flex-direction: column;
                align-items: stretch;
            }
            .mmd-rule-toolbar-actions, .mmd-rule-toolbar-jump { justify-content: center; }
            .mmd-rule-toolbar-jump select { flex: 1; }
            .mmd-card-title { padding: 12px 14px !important; font-size: 14px; }
            .mmd-card-body { padding: 12px 14px 16px; }
            .form-table th { padding-bottom: 4px; width: auto; display: block; }
            .form-table td { padding-top: 0; display: block; }
            input[type="text"], input[type="number"], input[type="url"], select, textarea {
                width: 100%;
                max-width: 100%;
                box-sizing: border-box;
            }
            .mmd-rule-submit-bar { margin: 16px 0 0; padding: 12px 10px; }
            .mmd-preview-dialog { width: 100%; max-width: 100%; max-height: 100vh; border-radius: 0; }
            #mmd-preview-products-table thead { display: none; }
            #mmd-preview-products-table tr { display: block; padding: 12px 0; border-bottom: 1px solid var(--mmd-border-light); }
            #mmd-preview-products-table td { display: flex; justify-content: space-between; padding: 4px 0; border: 0; }
            #mmd-preview-products-table td::before {
                content: attr(data-label);
                font-weight: 600;
                color: var(--mmd-text-light);
            }
        }
        </style>

        <script>
        jQuery(function($) {
            // ====================================================================
            // Tips per section (matched by title keyword)
            // ====================================================================
            var tipMap = [
                {
                    keywords: [<?php echo wp_json_encode( __( 'Basic settings', 'incentiv' ) ); ?>],
                    title: <?php echo wp_json_encode( __( 'What is this?', 'incentiv' ) ); ?>,
                    text: <?php echo wp_json_encode( __( 'Set the rule name (so you can recognise it easily) and the discount mode: <strong>Quantity</strong> (a discount on one item out of X) or <strong>Global</strong> (a discount on all matching items).', 'incentiv' ) ); ?>
                },
                {
                    keywords: [<?php echo wp_json_encode( __( 'Product selection', 'incentiv' ) ); ?>],
                    title: <?php echo wp_json_encode( __( 'How do I choose products?', 'incentiv' ) ); ?>,
                    text: <?php echo wp_json_encode( __( 'The rule applies only to the products you choose here. You can choose by <strong>category</strong> (easy to maintain), <strong>specific products</strong> (full control) or <strong>attribute</strong> (for example "Weight = 1 kg").', 'incentiv' ) ); ?>
                },
                {
                    keywords: [<?php echo wp_json_encode( __( 'Discount rules', 'incentiv' ) ); ?>, 'Discount Rules'],
                    title: <?php echo wp_json_encode( __( 'How do I build discount tiers?', 'incentiv' ) ); ?>,
                    text: <?php echo wp_json_encode( __( 'Add a row for each tier: quantity = minimum items, discount = % or a fixed ₪ amount. For example: 3 → 30%, 4 → 50%, 5 → free. <strong>Tip:</strong> in Quantity mode + "on the cheapest", the discount applies to one item only (the cheapest of the matching items).', 'incentiv' ) ); ?>
                },
                {
                    keywords: [<?php echo wp_json_encode( __( 'Coupon settings', 'incentiv' ) ); ?>],
                    title: <?php echo wp_json_encode( __( 'When should I use a coupon?', 'incentiv' ) ); ?>,
                    text: <?php echo wp_json_encode( __( 'Enable "Requires a coupon" so the rule only works after the customer enters a coupon code. Useful for targeted campaigns, clubs or exclusive discounts.', 'incentiv' ) ); ?>
                },
                {
                    keywords: [<?php echo wp_json_encode( __( 'Customer messages', 'incentiv' ) ); ?>],
                    title: <?php echo wp_json_encode( __( 'What does the customer see?', 'incentiv' ) ); ?>,
                    text: <?php echo wp_json_encode( __( 'The message appears on the product/category page to encourage adding to the cart. Use a short title ("☕ Buy 3 and save!") and content that briefly explains the offer.', 'incentiv' ) ); ?>
                },
                {
                    keywords: [<?php echo wp_json_encode( __( 'Custom messages', 'incentiv' ) ); ?>],
                    title: <?php echo wp_json_encode( __( 'Customise the cart and checkout', 'incentiv' ) ); ?>,
                    text: <?php echo wp_json_encode( __( 'If you want a different message in the cart than at checkout (for example: "One more item for a bigger discount!" in the cart, "You\'re close to free shipping!" at checkout), set them here.', 'incentiv' ) ); ?>
                },
                {
                    keywords: [<?php echo wp_json_encode( __( 'Shipping method condition', 'incentiv' ) ); ?>],
                    title: <?php echo wp_json_encode( __( 'When should I limit by shipping?', 'incentiv' ) ); ?>,
                    text: <?php echo wp_json_encode( __( 'If the rule should only apply with a certain shipping method (for example "30% off for local pickup only"), choose the relevant shipping methods. Leave empty to apply to all of them.', 'incentiv' ) ); ?>
                },
                {
                    keywords: [<?php echo wp_json_encode( __( 'Cart total conditions', 'incentiv' ) ); ?>],
                    title: <?php echo wp_json_encode( __( 'Discount by cart total', 'incentiv' ) ); ?>,
                    text: <?php echo wp_json_encode( __( 'Set a minimum and/or maximum cart total for the rule to apply. Useful for campaigns such as "10% off over 300₪".', 'incentiv' ) ); ?>
                },
                {
                    keywords: [<?php echo wp_json_encode( __( 'User role conditions', 'incentiv' ) ); ?>],
                    title: <?php echo wp_json_encode( __( 'Limit to customer groups', 'incentiv' ) ); ?>,
                    text: <?php echo wp_json_encode( __( 'The rule only works for users in the roles you choose (for example "Wholesale", "VIP"). If you leave it empty, the rule applies to all users and guests.', 'incentiv' ) ); ?>
                },
                {
                    keywords: [<?php echo wp_json_encode( __( 'Discount scheduling', 'incentiv' ) ); ?>],
                    title: <?php echo wp_json_encode( __( 'Automatic scheduling', 'incentiv' ) ); ?>,
                    text: <?php echo wp_json_encode( __( 'Set a start and end date and time. The rule switches on and off automatically. <strong>Tip:</strong> the time zone comes from the WordPress settings. Leave empty to keep the rule always active.', 'incentiv' ) ); ?>
                },
                {
                    keywords: [<?php echo wp_json_encode( __( 'Usage limits', 'incentiv' ) ); ?>],
                    title: <?php echo wp_json_encode( __( 'Limits on the number of uses', 'incentiv' ) ); ?>,
                    text: <?php echo wp_json_encode( __( '<strong>Total:</strong> how many times in total the rule can run before it switches off. <strong>Per user:</strong> how many times the same customer can trigger the rule. Useful for single-use coupons or limited campaigns.', 'incentiv' ) ); ?>
                },
                {
                    keywords: [<?php echo wp_json_encode( __( 'Discount badge', 'incentiv' ) ); ?>, 'Discount Badge'],
                    title: <?php echo wp_json_encode( __( 'Visual badge', 'incentiv' ) ); ?>,
                    text: <?php echo wp_json_encode( __( 'A small, colourful badge on the product image in the category and on the product page ("30% off!", "Sale!", etc.). It catches the eye and increases conversions.', 'incentiv' ) ); ?>
                },
                {
                    keywords: [<?php echo wp_json_encode( __( 'Sale price display', 'incentiv' ) ); ?>, 'Sale Price Display'],
                    title: <?php echo wp_json_encode( __( 'Struck-through price + price after discount', 'incentiv' ) ); ?>,
                    text: ( <?php echo wp_json_encode( __( 'Shows the price in the WooCommerce sale format (', 'incentiv' ) ); ?> + '<del>' + <?php echo wp_json_encode( __( 'Regular price', 'incentiv' ) ); ?> + '<\/del> ' + <?php echo wp_json_encode( __( 'price after discount) on your products. <strong>Important:</strong> "Show discount in cart" controls whether this also appears in the cart/mini cart (off = only a message at the bottom of the cart).', 'incentiv' ) ); ?> )
                },
                {
                    keywords: [<?php echo wp_json_encode( __( 'Display banner', 'incentiv' ) ); ?>, 'Display Banner'],
                    title: <?php echo wp_json_encode( __( 'A visual banner on the product page', 'incentiv' ) ); ?>,
                    text: ( <?php echo wp_json_encode( __( 'Upload a banner image shown to the customer on the product page. Use the shortcode', 'incentiv' ) ); ?> + ' <code>[mmd_rule_banner]<\/code> ' + <?php echo wp_json_encode( __( 'anywhere, or the Elementor widget.', 'incentiv' ) ); ?> )
                },
                {
                    keywords: [<?php echo wp_json_encode( __( 'Site-wide message', 'incentiv' ) ); ?>, 'Global Site Notice'],
                    title: <?php echo wp_json_encode( __( 'A message for the whole site', 'incentiv' ) ); ?>,
                    text: <?php echo wp_json_encode( __( 'A floating message (at the top or bottom of the page) shown to everyone while the rule is active. Useful for big announcements such as "30% off all weekend!".', 'incentiv' ) ); ?>
                },
                {
                    keywords: [<?php echo wp_json_encode( __( 'Exclude based on other rules', 'incentiv' ) ); ?>, 'Exclude by Other Rules'],
                    title: <?php echo wp_json_encode( __( 'When to exclude rules', 'incentiv' ) ); ?>,
                    text: <?php echo wp_json_encode( __( 'If a rule clashes with another rule (for example one for a narrow promotion and one for a broad promotion), choose here the rules that stop this rule from running <em>when</em> they are active.', 'incentiv' ) ); ?>
                }
            ];

            function getTipForCard($card) {
                var title = $card.find('.mmd-card-title').first().text();
                for (var i = 0; i < tipMap.length; i++) {
                    for (var j = 0; j < tipMap[i].keywords.length; j++) {
                        if (title.indexOf(tipMap[i].keywords[j]) !== -1) {
                            return tipMap[i];
                        }
                    }
                }
                return null;
            }

            // Build card structure (tip box + body wrapper) for each card
            var cardIndex = 0;
            $('.mmd-admin-container > .mmd-card').each(function() {
                var $card = $(this);
                cardIndex++;

                // Skip the toolbar (it's a different element)
                if ($card.hasClass('mmd-rule-toolbar')) return;

                // Get title and content
                var $title = $card.find('.mmd-card-title').first();
                if (!$title.length) return;

                // Wrap everything after the title in .mmd-card-body
                var $body = $('<div class="mmd-card-body"></div>');
                $title.nextAll().appendTo($body);

                // Add tip box at the top of body
                var tip = getTipForCard($card);
                if (tip) {
                    var $tip = $('<div class="mmd-card-tip"><span class="dashicons dashicons-lightbulb"></span> <strong>' + tip.title + '</strong> ' + tip.text + '</div>');
                    $body.prepend($tip);
                }

                // Generate stable ID
                var titleText = $title.text().trim();
                var sectionId = 'mmd-section-' + cardIndex;
                $card.attr('id', sectionId);
                $card.attr('data-mmd-card-title', titleText);

                $card.append($body);
            });

            // ================================================================
            // STEPPED EDITOR — the form is split into navigable steps.
            // Every step is freely clickable so values can always be changed.
            // ================================================================
            var STEPS = [
                { id: 1, label: <?php echo wp_json_encode( __( 'Details and products', 'incentiv' ) ); ?>, icon: 'info',
                  keywords: [<?php echo wp_json_encode( __( 'Basic settings', 'incentiv' ) ); ?>, <?php echo wp_json_encode( __( 'Product selection', 'incentiv' ) ); ?>] },
                { id: 2, label: <?php echo wp_json_encode( _x( 'Discount tiers', 'step', 'incentiv' ) ); ?>, icon: 'stairs',
                  keywords: [<?php echo wp_json_encode( __( 'Discount rules', 'incentiv' ) ); ?>, 'Discount Rules', <?php echo wp_json_encode( __( 'Coupon settings', 'incentiv' ) ); ?>] },
                { id: 3, label: <?php echo wp_json_encode( MMD_Modules::is_active( 'segments' ) ? __( 'Conditions and audiences', 'incentiv' ) : __( 'Conditions', 'incentiv' ) ); ?>, icon: 'rule',
                  keywords: [<?php echo wp_json_encode( __( 'Shipping method condition', 'incentiv' ) ); ?>, <?php echo wp_json_encode( __( 'Cart total conditions', 'incentiv' ) ); ?>, <?php echo wp_json_encode( __( 'User role conditions', 'incentiv' ) ); ?>, <?php if ( MMD_Modules::is_active( 'segments' ) ) { echo wp_json_encode( __( 'Target audiences and segmentation', 'incentiv' ) ) . ', '; } ?><?php echo wp_json_encode( __( 'Profit guards and caps', 'incentiv' ) ); ?>, <?php echo wp_json_encode( __( 'Discount scheduling', 'incentiv' ) ); ?>, <?php echo wp_json_encode( __( 'Usage limits', 'incentiv' ) ); ?>] },
                { id: 4, label: <?php echo wp_json_encode( __( 'Messages and display', 'incentiv' ) ); ?>, icon: 'campaign',
                  keywords: [<?php echo wp_json_encode( __( 'Customer messages', 'incentiv' ) ); ?>, <?php echo wp_json_encode( __( 'Custom messages', 'incentiv' ) ); ?>, <?php echo wp_json_encode( __( 'Discount badge', 'incentiv' ) ); ?>, 'Discount Badge', <?php echo wp_json_encode( __( 'Sale price display', 'incentiv' ) ); ?>, 'Sale Price Display', <?php echo wp_json_encode( __( 'Display banner', 'incentiv' ) ); ?>, 'Display Banner', <?php echo wp_json_encode( __( 'Site-wide message', 'incentiv' ) ); ?>, 'Global Site Notice'] },
                { id: 5, label: <?php echo wp_json_encode( __( 'Summary and save', 'incentiv' ) ); ?>, icon: 'check_circle',
                  keywords: [<?php echo wp_json_encode( __( 'Exclude based on other rules', 'incentiv' ) ); ?>, 'Exclude by Other Rules'] }
            ];
            var currentStep = 1;

            // All cards stay open inside their step
            $('.mmd-admin-container > .mmd-card').addClass('is-open');

            function stepForCard($card) {
                var title = $card.attr('data-mmd-card-title') || '';
                for (var i = 0; i < STEPS.length; i++) {
                    for (var j = 0; j < STEPS[i].keywords.length; j++) {
                        if (title.indexOf(STEPS[i].keywords[j]) !== -1) return STEPS[i].id;
                    }
                }
                return 1;
            }

            $('.mmd-admin-container > .mmd-card').each(function() {
                $(this).attr('data-mmd-step', stepForCard($(this)));
            });

            // Summary card (step 5) — built from live form values
            var $summaryCard = $('<div class="mmd-card is-open" data-mmd-step="5" id="mmd-step-summary">' +
                ( '<h2 class="mmd-card-title"><span class="msr" style="color:var(--mmd-success);">check_circle<\/span> ' + <?php echo wp_json_encode( __( 'Rule summary', 'incentiv' ) ); ?> + '<\/h2>' ) +
                '<div class="mmd-card-body"><div id="mmd-summary-rows"></div>' +
                ( '<div class="mmd-card-tip" style="margin-top:14px;background:var(--mmd-success-light);border-right-color:var(--mmd-success);">' + <?php echo wp_json_encode( _x( 'Check that everything is right, then save. You can go back to any step by clicking its number above.', 'rule', 'incentiv' ) ); ?> + '<\/div>' ) +
                '</div></div>');
            $('.mmd-admin-container').append($summaryCard);

            function esc(t) { return $('<i>').text(t == null ? '' : t).html(); }

            function buildSummary() {
                var mode = $('#discount_mode').val() === 'global' ? <?php echo wp_json_encode( __( 'Global: on all items', 'incentiv' ) ); ?> : <?php echo wp_json_encode( __( 'Tiered: by quantity', 'incentiv' ) ); ?>;
                var methodLabels = { all: <?php echo wp_json_encode( __( 'All products', 'incentiv' ) ); ?>, category: <?php echo wp_json_encode( __( 'By categories', 'incentiv' ) ); ?>, product_ids: <?php echo wp_json_encode( __( 'Selected products', 'incentiv' ) ); ?>, attribute: <?php echo wp_json_encode( __( 'By attribute', 'incentiv' ) ); ?> };
                var tiers = [];
                $('#discount_rules_container .mmd-discount-rule').each(function() {
                    var q = $(this).find('input[name*="[quantity]"]').val();
                    var d = $(this).find('input[name*="[discount]"]').val();
                    var t = $(this).find('select[name*="[type]"]').val();
                    if (q && d !== '') tiers.push(q + '+' + (document.documentElement.dir === 'rtl' ? ' ← ' : ' → ') + d + (t === 'fixed' ? ' ₪' : '%'));
                });
                var cats = $('input[name="included_categories[]"]:checked').closest('label').map(function() { return $(this).text().trim(); }).get();
                var sched = ($('#schedule_start').val() || '') + ((($('#schedule_start').val() || '') || ($('#schedule_end').val() || '')) ? (document.documentElement.dir === 'rtl' ? ' ← ' : ' → ') + ($('#schedule_end').val() || <?php echo wp_json_encode( __( 'No end', 'incentiv' ) ); ?>) : '');

                var rows = [
                    [<?php echo wp_json_encode( __( 'Rule name', 'incentiv' ) ); ?>, $('#rule_name').val() || <?php echo wp_json_encode( __( '(no name)', 'incentiv' ) ); ?>, 1],
                    [<?php echo wp_json_encode( __( 'Status', 'incentiv' ) ); ?>, $('#enabled').is(':checked') ? <?php echo wp_json_encode( __( 'Active', 'incentiv' ) ); ?> : <?php echo wp_json_encode( __( 'Disabled', 'incentiv' ) ); ?>, 1],
                    [<?php echo wp_json_encode( __( 'Discount mode', 'incentiv' ) ); ?>, mode, 1],
                    [<?php echo wp_json_encode( __( 'Selection method', 'incentiv' ) ); ?>, methodLabels[$('#selection_method').val()] || '', 1],
                    [<?php echo wp_json_encode( __( 'Categories', 'incentiv' ) ); ?>, cats.length ? cats.join(', ') : '—', 1],
                    [<?php echo wp_json_encode( _x( 'Discount tiers', 'step', 'incentiv' ) ); ?>, tiers.length ? tiers.join(' · ') : '—', 2],
                    [<?php echo wp_json_encode( __( 'Coupon requirement', 'incentiv' ) ); ?>, $('#coupon_required').is(':checked') ? (( <?php echo wp_json_encode( __( 'Yes ·', 'incentiv' ) ); ?> + ' ' ) + ($('#required_coupon_codes').val() || '')) : <?php echo wp_json_encode( __( 'No', 'incentiv' ) ); ?>, 2],
                    [<?php echo wp_json_encode( __( 'Cart total', 'incentiv' ) ); ?>, ($('#min_cart_total').val() || $('#max_cart_total').val()) ? (($('#min_cart_total').val() || '0') + ' ₪ — ' + ($('#max_cart_total').val() || '∞')) : <?php echo wp_json_encode( __( 'Unlimited', 'incentiv' ) ); ?>, 3],
                    [<?php echo wp_json_encode( __( 'Scheduling', 'incentiv' ) ); ?>, sched || <?php echo wp_json_encode( __( 'Always active', 'incentiv' ) ); ?>, 3],
                    [<?php echo wp_json_encode( __( 'Cart message', 'incentiv' ) ); ?>, $('#notice_enabled').is(':checked') ? ($('#notice_title').val() || <?php echo wp_json_encode( __( 'On', 'incentiv' ) ); ?>) : <?php echo wp_json_encode( __( 'Off', 'incentiv' ) ); ?>, 4]
                ];

                var html = '';
                rows.forEach(function(r) {
                    html += '<div style="display:flex;justify-content:space-between;align-items:center;gap:12px;padding:11px 4px;border-bottom:1px solid var(--mmd-border-lighter);font-size:13.5px;">' +
                        '<span style="color:var(--mmd-text-muted);flex:none;">' + esc(r[0]) + '</span>' +
                        '<span style="font-weight:700;color:var(--mmd-ink);text-align:left;">' + esc(r[1]) + '</span>' +
                        '<button type="button" class="mmd-icon-btn mmd-summary-edit" data-step="' + r[2] + ( '" title="' + <?php echo wp_json_encode( __( 'Edit', 'incentiv' ) ); ?> + '" style="flex:none;width:28px;height:28px;"><span class="msr" style="font-size:15px;">edit<\/span><\/button>' ) +
                        '</div>';
                });
                $('#mmd-summary-rows').html(html);
            }

            function renderStepper() {
                var html = '';
                STEPS.forEach(function(s) {
                    var cls = s.id === currentStep ? ' active' : (s.id < currentStep ? ' completed' : '');
                    html += '<button type="button" class="mmd-stepper-step' + cls + '" data-step="' + s.id + '">' +
                        '<span class="mmd-step-num">' + (s.id < currentStep ? '✓' : s.id) + '</span>' +
                        '<span class="mmd-step-label">' + s.label + '</span></button>';
                });
                $('#mmd-rule-stepper-steps').html(html);
            }

            function showStep(n, scroll) {
                currentStep = Math.max(1, Math.min(STEPS.length, n));
                $('.mmd-admin-container > .mmd-card[data-mmd-step]').each(function() {
                    $(this).toggleClass('is-step-active', parseInt($(this).attr('data-mmd-step'), 10) === currentStep);
                });
                if (currentStep === STEPS.length) buildSummary();
                renderStepper();
                $('#mmd-step-prev').prop('disabled', currentStep === 1);
                $('#mmd-step-next').toggle(currentStep < STEPS.length);
                $('#mmd-step-indicator').text(( <?php echo wp_json_encode( __( 'Step', 'incentiv' ) ); ?> + ' ' ) + currentStep + ( ' ' + <?php echo wp_json_encode( __( 'of', 'incentiv' ) ); ?> + ' ' ) + STEPS.length);
                if (history.replaceState) history.replaceState(null, '', '#step=' + currentStep);
                $(document).trigger('mmd:substep');
                if (scroll !== false) window.scrollTo({ top: 0, behavior: 'smooth' });
            }

            $(document).on('click', '.mmd-stepper-step', function() { showStep(parseInt($(this).data('step'), 10)); });
            $(document).on('click', '.mmd-summary-edit', function() { showStep(parseInt($(this).data('step'), 10)); });
            $(document).on('click', '#mmd-step-next', function() { showStep(currentStep + 1); });
            $(document).on('click', '#mmd-step-prev', function() { showStep(currentStep - 1); });

            // Jump to the step that contains an invalid field (native capture)
            document.querySelectorAll('form').forEach(function(formEl) {
                formEl.addEventListener('invalid', function(e) {
                    var card = e.target.closest('.mmd-card[data-mmd-step]');
                    if (card) showStep(parseInt(card.getAttribute('data-mmd-step'), 10), false);
                }, true);
            });

            // Initial step from URL hash
            var stepMatch = (window.location.hash || '').match(/step=(\d+)/);
            showStep(stepMatch ? parseInt(stepMatch[1], 10) : 1, false);

            // Let the guided wizard (admin-script.js) drive the stepper
            window.mmdShowEditorStep = showStep;

            // ====================================================================
            // Preview modal
            // ====================================================================
            var $modal = $('#mmd-preview-modal');
            var $tbody = $modal.find('tbody');
            var allProducts = []; // Will be loaded via AJAX
            var productSearchPromise = null;
            var productLoadError = null;

            function loadProducts() {
                if (productSearchPromise) return productSearchPromise;
                productSearchPromise = $.Deferred();
                $.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'mmd_preview_products_search',
                        nonce: '<?php echo esc_attr( wp_create_nonce('mmd_preview_products_search') ); ?>'
                    },
                    success: function(resp) {
                        if (resp && resp.success && Array.isArray(resp.data)) {
                            allProducts = resp.data;
                            productSearchPromise.resolve(allProducts);
                        } else {
                            productLoadError = (resp && resp.data) ? String(resp.data) : <?php echo wp_json_encode( __( 'Invalid response from the server', 'incentiv' ) ); ?>;
                            productSearchPromise.reject(productLoadError);
                        }
                    },
                    error: function(xhr, status, err) {
                        productLoadError = ( <?php echo wp_json_encode( __( 'AJAX error:', 'incentiv' ) ); ?> + ' ' ) + (err || status || <?php echo wp_json_encode( __( 'Unknown', 'incentiv' ) ); ?>) + ' (HTTP ' + (xhr ? xhr.status : '?') + ')';
                        console.error('MMD preview AJAX failed', xhr, status, err);
                        productSearchPromise.reject(productLoadError);
                    }
                });
                return productSearchPromise.promise();
            }

            $('#mmd-open-preview').on('click', function() {
                $modal.show().attr('aria-hidden', 'false');
                if ($tbody.find('tr.mmd-preview-row').length === 0) {
                    addPreviewRow();
                }
            });

            $modal.on('click', '[data-close]', function() {
                $modal.hide().attr('aria-hidden', 'true');
            });

            $(document).on('keydown', function(e) {
                if (e.key === 'Escape' && $modal.is(':visible')) {
                    $modal.hide();
                }
            });

            function buildRuleSnapshot() {
                var $form = $('form').first();
                var discount_mode = $form.find('select[name="discount_mode"]').val() || $form.find('input[name="discount_mode"]:checked').val() || 'quantity';
                var apply_to = $form.find('select[name="apply_to"]').val() || $form.find('input[name="apply_to"]:checked').val() || 'cheapest';
                return {
                    discount_mode: discount_mode,
                    apply_to: apply_to,
                    discount_rules: collectDiscountTiers(),
                    min_quantity_trigger: parseInt($form.find('[name="min_quantity_trigger"]').val() || 0, 10)
                };
            }

            function collectDiscountTiers() {
                var tiers = [];
                $('input[name^="discount_rules"][name$="[quantity]"]').each(function() {
                    var $row = $(this).closest('tr, .mmd-discount-row');
                    var qty = parseInt($(this).val() || 0, 10);
                    var $discountInput = $row.find('input[name$="[discount]"]').first();
                    var $typeInput = $row.find('select[name$="[type]"], input[name$="[type]"]').first();
                    var discount = parseFloat($discountInput.val() || 0);
                    var type = $typeInput.val() || 'percentage';
                    if (qty > 0 && discount > 0) {
                        tiers.push({ quantity: qty, discount: discount, type: type });
                    }
                });
                tiers.sort(function(a, b) { return a.quantity - b.quantity; });
                return tiers;
            }

            function showLoadError($select, msg) {
                $select.empty().append($('<option>').val('').text(( <?php echo wp_json_encode( __( 'Error:', 'incentiv' ) ); ?> + ' ' ) + msg));
            }

            function fillProductSelect($select) {
                $select.empty().append($('<option>').val('').text(<?php echo wp_json_encode( __( '— Choose a product —', 'incentiv' ) ); ?>));
                allProducts.forEach(function(p) {
                    var $opt = $('<option>').val(p.id).text(p.name + ' (' + p.formatted_price + ')');
                    $opt.attr('data-price', p.regular_price);
                    $select.append($opt);
                });
                // Initialize Select2 if available
                if (typeof $.fn.select2 === 'function') {
                    $select.select2({
                        width: '100%',
                        placeholder: <?php echo wp_json_encode( __( '— Choose a product —', 'incentiv' ) ); ?>,
                        allowClear: true,
                        dropdownParent: $modal,
                    });
                }
            }

            function addPreviewRow() {
                $tbody.find('.mmd-preview-empty').remove();
                var $row = $('<tr class="mmd-preview-row">' +
                    ( '<td data-label="' + <?php echo wp_json_encode( __( 'Product', 'incentiv' ) ); ?> + '"><select class="mmd-preview-product-search" style="width:100%;"><option value="">' + <?php echo wp_json_encode( __( '— Loading products —', 'incentiv' ) ); ?> + '<\/option><\/select><\/td>' ) +
                    ( '<td data-label="' + <?php echo wp_json_encode( __( 'Quantity', 'incentiv' ) ); ?> + '"><input type="number" class="mmd-preview-qty" min="1" value="1"><\/td>' ) +
                    ( '<td data-label="' + <?php echo wp_json_encode( __( 'Unit price', 'incentiv' ) ); ?> + '" class="mmd-preview-unit-price">—<\/td>' ) +
                    ( '<td data-label="' + <?php echo wp_json_encode( __( 'After discount', 'incentiv' ) ); ?> + '" class="mmd-preview-after">—<\/td>' ) +
                    ( '<td data-label="' + <?php echo wp_json_encode( __( 'Savings', 'incentiv' ) ); ?> + '" class="mmd-preview-savings">—<\/td>' ) +
                    ( '<td><button type="button" class="button button-link-delete mmd-preview-remove" aria-label="' + <?php echo wp_json_encode( __( 'Remove', 'incentiv' ) ); ?> + '"><span class="dashicons dashicons-trash"><\/span><\/button><\/td>' ) +
                    '</tr>');
                $tbody.append($row);

                var $select = $row.find('.mmd-preview-product-search');
                loadProducts().done(function() {
                    fillProductSelect($select);
                }).fail(function(err) {
                    showLoadError($select, err || <?php echo wp_json_encode( __( 'Products could not be loaded', 'incentiv' ) ); ?>);
                });
            }

            $('#mmd-preview-add').on('click', addPreviewRow);

            $tbody.on('click', '.mmd-preview-remove', function() {
                $(this).closest('tr').remove();
                if ($tbody.find('tr.mmd-preview-row').length === 0) {
                    $tbody.append(( '<tr class="mmd-preview-empty"><td colspan="6" style="text-align:center;color:#646970;padding:24px;">' + <?php echo wp_json_encode( __( 'No products selected yet.', 'incentiv' ) ); ?> + '<\/td><\/tr>' ));
                }
                recalculatePreview();
            });

            // Listen to native change AND select2 change events
            $tbody.on('change input', '.mmd-preview-product-search, .mmd-preview-qty', function() {
                recalculatePreview();
            });

            function getPriceForRow($row) {
                var $sel = $row.find('.mmd-preview-product-search');
                var val = $sel.val();
                if (!val) return 0;
                // Read data-price from the matching <option>, fall back to attr (in case Select2 wraps the select differently)
                var $opt = $sel.find('option[value="' + val + '"]');
                var price = parseFloat($opt.attr('data-price') || 0);
                return isNaN(price) ? 0 : price;
            }

            function recalculatePreview() {
                var rule = buildRuleSnapshot();

                var units = [];
                $tbody.find('.mmd-preview-row').each(function() {
                    var $row = $(this);
                    var price = getPriceForRow($row);
                    var qty = parseInt($row.find('.mmd-preview-qty').val() || 0, 10);
                    if (price > 0 && qty > 0) {
                        $row.find('.mmd-preview-unit-price').text(price.toFixed(2) + ' ₪');
                        for (var i = 0; i < qty; i++) {
                            units.push({ rowKey: $row, price: price });
                        }
                    } else {
                        $row.find('.mmd-preview-unit-price').text('—');
                        $row.find('.mmd-preview-after').text('—');
                        $row.find('.mmd-preview-savings').text('—');
                    }
                });

                if (units.length === 0) {
                    $('#mmd-preview-total-after').text('—');
                    $('#mmd-preview-total-saved').text('—');
                    $('.mmd-preview-summary').empty();
                    return;
                }

                // Validate rule has tiers
                if (!rule.discount_rules.length) {
                    showSummary(<?php echo wp_json_encode( __( 'No discount tiers are set. Add at least one tier in the "Discounts" tab.', 'incentiv' ) ); ?>, 0, 0);
                    clearRowDiscounts();
                    return;
                }

                var totalQty = units.length;
                if (rule.min_quantity_trigger > 0 && totalQty < rule.min_quantity_trigger) {
                    showSummary(( <?php echo wp_json_encode( __( 'The rule won\'t apply: minimum quantity required', 'incentiv' ) ); ?> + ' ' ) + rule.min_quantity_trigger + ( ' ' + <?php echo wp_json_encode( __( 'items, selected', 'incentiv' ) ); ?> + ' ' ) + totalQty + '.', 0, 0);
                    clearRowDiscounts();
                    return;
                }
                var applicable = null;
                rule.discount_rules.forEach(function(tier) {
                    if (totalQty >= tier.quantity) applicable = tier;
                });
                if (!applicable) {
                    var minTier = rule.discount_rules[0];
                    showSummary(( <?php echo wp_json_encode( __( 'The rule won\'t apply: at least', 'incentiv' ) ); ?> + ' ' ) + minTier.quantity + ( ' ' + <?php echo wp_json_encode( __( 'items are needed. Currently there are', 'incentiv' ) ); ?> + ' ' ) + totalQty + '.', 0, 0);
                    clearRowDiscounts();
                    return;
                }

                function calcDiscounted(price, tier) {
                    if (tier.type === 'percentage') return Math.max(0, price * (1 - tier.discount / 100));
                    return Math.max(0, price - tier.discount);
                }

                clearRowDiscounts();
                var totalSaved = 0;
                var totalAfter = 0;

                if (rule.discount_mode === 'global') {
                    units.forEach(function(u) {
                        var disc = calcDiscounted(u.price, applicable);
                        totalSaved += (u.price - disc);
                        totalAfter += disc;
                    });
                    distributeRowSavings(units, applicable);
                } else {
                    var sortedUnits = units.slice().sort(function(a, b) {
                        return rule.apply_to === 'cheapest' ? a.price - b.price : b.price - a.price;
                    });
                    var target = sortedUnits[0];
                    var disc = calcDiscounted(target.price, applicable);
                    totalSaved = target.price - disc;
                    totalAfter = units.reduce(function(s, u) { return s + u.price; }, 0) - totalSaved;
                    // Mark target row's first unit
                    var $tRow = target.rowKey;
                    var rowQty = $tRow.find('.mmd-preview-qty').val();
                    var rowPrice = getPriceForRow($tRow);
                    var lineRegular = rowPrice * rowQty;
                    var lineAfter = lineRegular - (target.price - disc);
                    $tRow.find('.mmd-preview-after').text((lineAfter / rowQty).toFixed(2) + ( ' ' + <?php echo wp_json_encode( __( '₪ (1 item)', 'incentiv' ) ); ?> ));
                    $tRow.find('.mmd-preview-savings').text((target.price - disc).toFixed(2) + ' ₪');
                }

                $('#mmd-preview-total-after').text(totalAfter.toFixed(2) + ' ₪');
                $('#mmd-preview-total-saved').text(totalSaved.toFixed(2) + ' ₪');

                var modeText = rule.discount_mode === 'global'
                    ? <?php echo wp_json_encode( __( 'Global (on all items)', 'incentiv' ) ); ?>
                    : <?php echo wp_json_encode( __( 'Quantity (', 'incentiv' ) ); ?> + (rule.apply_to === 'cheapest' ? <?php echo wp_json_encode( __( 'the cheapest', 'incentiv' ) ); ?> : <?php echo wp_json_encode( __( 'the most expensive', 'incentiv' ) ); ?>) + ')';
                showSummary(
                    ( <?php echo wp_json_encode( __( 'Mode:', 'incentiv' ) ); ?> + ' ' ) + modeText + ( <?php echo wp_json_encode( __( '. Active tier:', 'incentiv' ) ); ?> + ' ' ) + applicable.quantity + ( <?php echo wp_json_encode( __( '+ items =', 'incentiv' ) ); ?> + ' ' ) +
                    (applicable.type === 'percentage' ? applicable.discount + <?php echo wp_json_encode( __( '% off', 'incentiv' ) ); ?> : applicable.discount + ( ' ' + <?php echo wp_json_encode( __( '₪ off', 'incentiv' ) ); ?> )),
                    totalAfter, totalSaved
                );
            }

            function clearRowDiscounts() {
                $tbody.find('.mmd-preview-row').each(function() {
                    var $row = $(this);
                    var price = getPriceForRow($row);
                    if (price > 0) {
                        $row.find('.mmd-preview-after').text(price.toFixed(2) + ' ₪');
                        $row.find('.mmd-preview-savings').text('0 ₪');
                    }
                });
            }

            function distributeRowSavings(units, tier) {
                var rows = new Map();
                units.forEach(function(u) {
                    if (!rows.has(u.rowKey[0])) rows.set(u.rowKey[0], { $row: u.rowKey, qty: 0, price: u.price });
                    rows.get(u.rowKey[0]).qty++;
                });
                rows.forEach(function(info) {
                    var perUnitAfter = (function(p) {
                        if (tier.type === 'percentage') return Math.max(0, p * (1 - tier.discount / 100));
                        return Math.max(0, p - tier.discount);
                    })(info.price);
                    info.$row.find('.mmd-preview-after').text(perUnitAfter.toFixed(2) + ' ₪');
                    info.$row.find('.mmd-preview-savings').text(((info.price - perUnitAfter) * info.qty).toFixed(2) + ' ₪');
                });
            }

            function showSummary(text, total, saved) {
                var html = '<div>' + text + '</div>';
                if (saved > 0) {
                    html += ( '<div style="margin-top:6px;"><strong>' + <?php echo wp_json_encode( _x( 'You saved', 'preview', 'incentiv' ) ); ?> + ' ' ) + saved.toFixed(2) + ( ' ₪.<\/strong> ' + <?php echo wp_json_encode( __( 'Total to pay:', 'incentiv' ) ); ?> + ' ' ) + total.toFixed(2) + ' ₪.</div>';
                }
                $('.mmd-preview-summary').html(html);
            }
        });
        </script>
    </form>

    <?php if ( ! $is_new && class_exists( 'MMD_Rule_Diagnostic' ) ) : ?>
    <div id="mmd-rule-diagnostic" class="mmd-card mmd-diag-card">
        <div class="mmd-diag-head">
            <span class="msr">bug_report</span>
            <div class="mmd-diag-head-text">
                <strong><?php esc_html_e( 'Rule diagnostics tool', 'incentiv' ); ?></strong>
                <span><?php esc_html_e( 'A developer tool. Only enable it to troubleshoot problems.', 'incentiv' ); ?></span>
            </div>
            <label class="mmd-switch" title="<?php esc_attr_e( 'Turn the diagnostics tool on/off', 'incentiv' ); ?>">
                <input type="checkbox" id="mmd-diag-toggle">
                <span class="mmd-slider"></span>
            </label>
        </div>
        <div id="mmd-diag-body" class="mmd-diag-body" style="display:none;">
            <p class="description"><?php esc_html_e( 'If the rule causes a critical error on the site, click "Run diagnostics" to get a JSON with all the relevant information, and paste it when you report the problem.', 'incentiv' ); ?></p>
            <div class="mmd-diag-actions">
                <button type="button" class="mmd-btn mmd-btn-primary" id="mmd-diag-run"><span class="msr">search</span> <?php esc_html_e( 'Run diagnostics', 'incentiv' ); ?></button>
                <button type="button" class="mmd-btn mmd-btn-outline" id="mmd-diag-copy" style="display:none;"><span class="msr">content_copy</span> <?php esc_html_e( 'Copy JSON', 'incentiv' ); ?></button>
                <span id="mmd-diag-status"></span>
            </div>

            <div id="mmd-diag-product-test" class="mmd-diag-subcard" style="display:none;">
                <strong><?php esc_html_e( 'Test against a specific product', 'incentiv' ); ?></strong>
                <div class="mmd-diag-product-row">
                    <input type="number" id="mmd-diag-product-id" placeholder="<?php esc_attr_e( 'Product ID', 'incentiv' ); ?>" min="1">
                    <button type="button" class="mmd-btn mmd-btn-outline" id="mmd-diag-product-run"><?php esc_html_e( 'Test product', 'incentiv' ); ?></button>
                </div>
                <p class="description"><?php esc_html_e( 'Enter a product ID to see whether the rule applies to it and what its price is.', 'incentiv' ); ?></p>
            </div>

            <div id="mmd-diag-summary" style="display:none;"></div>

            <textarea id="mmd-diag-output" rows="16" readonly class="mmd-diag-output" style="display:none;"></textarea>
        </div>
    </div>

    <script>
    (function ($) {
        var ruleSetId = <?php echo wp_json_encode( $rule_set['id'] ); ?>;
        var nonce     = <?php echo wp_json_encode( wp_create_nonce( 'mmd_rule_diagnostic' ) ); ?>;
        var $output   = $('#mmd-diag-output');
        var $status   = $('#mmd-diag-status');
        var $summary  = $('#mmd-diag-summary');
        var $copy     = $('#mmd-diag-copy');
        var $productPanel = $('#mmd-diag-product-test');
        var lastJson  = '';

        // Enable/disable (collapse) the diagnostic tool
        $('#mmd-diag-toggle').on('change', function () {
            $('#mmd-diag-body').stop(true, true).slideToggle(150, this.checked ? undefined : function () {});
        });

        function setStatus(text, isError) {
            $status.text(text || '').css('color', isError ? '#b32d2e' : '#1d2327');
        }

        function renderSummary(data) {
            if (!data) {
                $summary.hide().empty();
                return;
            }
            var parts = [];
            parts.push('<div style="padding:8px;background:#f0f6fc;border-left:4px solid #2271b1;">');
            parts.push(( '<strong>' + <?php echo wp_json_encode( __( 'Summary:', 'incentiv' ) ); ?> + '<\/strong><br>' ));
            if (data.activation) {
                parts.push('Enabled: <code>' + (data.activation.enabled ? 'yes' : 'no') + '</code> | ');
                parts.push('In schedule: <code>' + (data.activation.currently_in_schedule ? 'yes' : 'no') + '</code> | ');
                parts.push('Coupon required: <code>' + (data.activation.coupon_required ? 'yes' : 'no') + '</code>');
                if (data.activation.blocking_reasons && data.activation.blocking_reasons.length) {
                    parts.push('<br><strong style="color:#b32d2e;">Blocking reasons:</strong> ' + data.activation.blocking_reasons.join('; '));
                }
            }
            if (data.warnings && data.warnings.length) {
                parts.push('<br><strong style="color:#996800;">Warnings:</strong><ul style="margin:4px 18px;">');
                data.warnings.forEach(function (w) {
                    parts.push('<li>' + $('<div>').text(w).html() + '</li>');
                });
                parts.push('</ul>');
            }
            if (data.product_test) {
                parts.push('<br><strong>Product test:</strong> ');
                if (data.product_test.error) {
                    parts.push('<span style="color:#b32d2e;">' + $('<div>').text(data.product_test.error).html() + '</span>');
                } else {
                    parts.push('Product #' + data.product_test.product_id + ' (' + (data.product_test.product_type || '?') + ')');
                    if (typeof data.product_test.qualifies_for_this_rule !== 'undefined') {
                        parts.push(' — qualifies: <code>' + (data.product_test.qualifies_for_this_rule ? 'yes' : 'no') + '</code>');
                    }
                    if (data.product_test.prices) {
                        parts.push(' | regular: <code>' + data.product_test.prices.regular_price + '</code>');
                        parts.push(' | price: <code>' + data.product_test.prices.price + '</code>');
                    }
                }
            }
            parts.push('</div>');
            $summary.html(parts.join('')).show();
        }

        function showResult(data) {
            lastJson = JSON.stringify(data, null, 2);
            $output.val(lastJson).show();
            $copy.show();
            $productPanel.show();
            renderSummary(data);
        }

        function runRequest(action, extra) {
            setStatus('Running…');
            var payload = $.extend({
                action: action,
                nonce: nonce,
                rule_set_id: ruleSetId
            }, extra || {});
            return $.post(ajaxurl, payload).done(function (resp) {
                if (resp && resp.success) {
                    setStatus('Done.');
                    showResult(resp.data);
                } else {
                    var msg = (resp && resp.data && resp.data.message) ? resp.data.message : 'Unknown error';
                    setStatus('Error: ' + msg, true);
                }
            }).fail(function (xhr) {
                setStatus('Request failed (' + xhr.status + ').', true);
            });
        }

        $('#mmd-diag-run').on('click', function () {
            runRequest('mmd_rule_diagnostic_run');
        });

        $('#mmd-diag-product-run').on('click', function () {
            var pid = parseInt($('#mmd-diag-product-id').val(), 10);
            if (!pid) {
                setStatus('Enter a product ID first.', true);
                return;
            }
            runRequest('mmd_rule_diagnostic_product', { product_id: pid });
        });

        $copy.on('click', function () {
            if (!lastJson) { return; }
            // Prefer the modern clipboard API; fall back to selection.
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(lastJson).then(function () {
                    setStatus('JSON copied to clipboard.');
                }, function () {
                    legacyCopy();
                });
            } else {
                legacyCopy();
            }
        });

        function legacyCopy() {
            $output[0].select();
            try {
                document.execCommand('copy');
                setStatus('JSON copied to clipboard.');
            } catch (e) {
                setStatus('Copy failed — select the textarea manually.', true);
            }
        }
    })(jQuery);
    </script>
    <?php endif; ?>
</div>

<script>
jQuery(document).ready(function($) {
    // Load shipping methods dynamically
    (function() {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mmd_get_shipping_methods',
                nonce: '<?php echo esc_attr( wp_create_nonce('mmd_gift_rules_nonce') ); ?>'
            },
            success: function(response) {
                if (response.success && response.data) {
                    var $select = $('#shipping_method_condition');
                    var currentValue = '<?php echo esc_js($rule_set['shipping_method_condition']); ?>';
                    $select.empty();
                    $select.append(( '<option value="">' + <?php echo wp_json_encode( __( 'No limit - all shipping methods', 'incentiv' ) ); ?> + '<\/option>' ));
                    response.data.forEach(function(method) {
                        var selected = currentValue === method.id ? 'selected' : '';
                        $select.append('<option value="' + method.id + '" ' + selected + '>' + method.title + '</option>');
                    });
                }
            }
        });
    })();

    // Toggle apply_to row and discount rules UI based on discount mode
    function updateDiscountModeUI() {
        var mode = $('#discount_mode').val();
        if (mode === 'global') {
            $('#apply_to_row').hide();
            $('#discount_rules_quantity_desc').hide();
            $('#discount_rules_global_desc').show();
            $('.mmd-quantity-field').hide();
            $('#discount_rules_help_text').html(<?php echo wp_json_encode( __( 'In global mode, the first discount applies to all products. You can add more tiers with a quantity to increase the discount (for example: buy 3+ get 20% instead of 15%).', 'incentiv' ) ); ?>);
        } else {
            $('#apply_to_row').show();
            $('#discount_rules_quantity_desc').show();
            $('#discount_rules_global_desc').hide();
            $('.mmd-quantity-field').show();
            $('#discount_rules_help_text').html(<?php echo wp_json_encode( __( 'Set different discounts for different quantities. The highest matching discount applies.', 'incentiv' ) ); ?>);
        }
    }
    $('#discount_mode').on('change', updateDiscountModeUI);

    // Allocation selector — show only the active mode's explanation.
    $('#discount_allocation').on('change', function () {
        var mode = $(this).val();
        $('.mmd-alloc-help').each(function () {
            $(this).toggle($(this).data('mode') === mode);
        });
    });

    // Global preset buttons
    $(document).on('click', '.mmd-global-preset-btn', function() {
        var discount = $(this).data('discount');
        var type = $(this).data('type');

        // Clear existing rules and set single rule
        $('#discount_rules_container').html('');
        var ruleHtml = '<div class="mmd-discount-rule"><div class="mmd-rule-fields">' +
            '<div class="mmd-field mmd-quantity-field" style="display:none;">' +
            ( '<label>' + <?php echo wp_json_encode( __( 'Quantity', 'incentiv' ) ); ?> + '<\/label>' ) +
            '<input type="number" name="discount_rules[0][quantity]" value="1" min="1" placeholder="1">' +
            '</div>' +
            ( '<div class="mmd-field"><label>' + <?php echo wp_json_encode( __( 'Discount', 'incentiv' ) ); ?> + '<\/label>' ) +
            '<input type="number" name="discount_rules[0][discount]" value="' + discount + '" min="0" step="0.01">' +
            '</div>' +
            ( '<div class="mmd-field"><label>' + <?php echo wp_json_encode( __( 'Type', 'incentiv' ) ); ?> + '<\/label>' ) +
            '<select name="discount_rules[0][type]">' +
            '<option value="percentage"' + (type === 'percentage' ? ' selected' : '') + ( '>' + <?php echo wp_json_encode( __( 'Percentage (%)', 'incentiv' ) ); ?> + '<\/option>' ) +
            '<option value="fixed"' + (type === 'fixed' ? ' selected' : '') + ( '>' + <?php echo wp_json_encode( __( 'Fixed amount (₪)', 'incentiv' ) ); ?> + '<\/option>' ) +
            '</select></div>' +
            ( '<button type="button" class="button mmd-remove-rule"><span class="dashicons dashicons-trash" style="font-size:14px;width:14px;height:14px;vertical-align:middle;"><\/span><span class="screen-reader-text">' + <?php echo wp_json_encode( __( 'Remove', 'incentiv' ) ); ?> + '<\/span><\/button>' ) +
            '</div></div>';
        $('#discount_rules_container').html(ruleHtml);
    });

    // Show/hide sections based on selection method
    $('#selection_method').on('change', function() {
        $('.mmd-section').hide();
        var method = $(this).val();
        if (method === 'category') {
            $('#category_section').show();
        } else if (method === 'product_ids') {
            $('#product_ids_section').show();
        } else if (method === 'attribute') {
            $('#attribute_section').show();
        }
    });

    // Add discount rule
    $('#add_discount_rule').on('click', function() {
        var index = $('.mmd-discount-rule').length;
        var isGlobal = $('#discount_mode').val() === 'global';
        var qtyDisplay = isGlobal ? ' style="display:none;"' : '';
        var html = '<div class="mmd-discount-rule">' +
            '<div class="mmd-rule-fields">' +
            '<div class="mmd-field mmd-quantity-field"' + qtyDisplay + ( '><label>' + <?php echo wp_json_encode( __( 'Quantity', 'incentiv' ) ); ?> + '<\/label><input type="number" name="discount_rules[' ) + index + '][quantity]" min="1" placeholder="' + (isGlobal ? '1' : '3') + '" value="' + (isGlobal ? '1' : '') + '"></div>' +
            ( '<div class="mmd-field"><label>' + <?php echo wp_json_encode( __( 'Discount', 'incentiv' ) ); ?> + '<\/label><input type="number" name="discount_rules[' ) + index + '][discount]" min="0" step="0.01" placeholder="30"></div>' +
            ( '<div class="mmd-field"><label>' + <?php echo wp_json_encode( __( 'Type', 'incentiv' ) ); ?> + '<\/label><select name="discount_rules[' ) + index + ( '][type]"><option value="percentage">' + <?php echo wp_json_encode( __( 'Percentage (%)', 'incentiv' ) ); ?> + '<\/option><option value="fixed">' + <?php echo wp_json_encode( __( 'Fixed amount (₪)', 'incentiv' ) ); ?> + '<\/option><\/select><\/div>' ) +
            ( '<button type="button" class="button mmd-remove-rule"><span class="dashicons dashicons-trash" style="font-size:14px;width:14px;height:14px;vertical-align:middle;"><\/span><span class="screen-reader-text">' + <?php echo wp_json_encode( __( 'Remove', 'incentiv' ) ); ?> + '<\/span><\/button>' ) +
            '</div></div>';
        $('#discount_rules_container').append(html);
    });

    // Remove discount rule
    $(document).on('click', '.mmd-remove-rule', function() {
        if ($('.mmd-discount-rule').length > 1) {
            $(this).closest('.mmd-discount-rule').remove();
        } else {
            alert(<?php echo wp_json_encode( __( 'There must be at least one discount tier', 'incentiv' ) ); ?>);
        }
    });

    // Initialize Select2 for product search (includes variations)
    $('.mmd-product-search-multiple').select2({
        ajax: {
            url: ajaxurl,
            dataType: 'json',
            delay: 250,
            data: function(params) {
                return {
                    action: 'mmd_search_products',
                    q: params.term,
                    include_variations: '1',
                    nonce: '<?php echo esc_attr( wp_create_nonce('mmd_search_products') ); ?>'
                };
            },
            processResults: function(data) {
                return {
                    results: data.data
                };
            }
        },
        minimumInputLength: 2,
        placeholder: <?php echo wp_json_encode( __( 'Search products or variations...', 'incentiv' ) ); ?>,
        width: '100%',
        dir: 'rtl'
    });

    // Initialize Select2 for category variations (non-AJAX, populated dynamically)
    $('#included_variations_from_categories').select2({
        placeholder: <?php echo wp_json_encode( __( 'Choose variations from the categories...', 'incentiv' ) ); ?>,
        width: '100%',
        dir: 'rtl',
        allowClear: true
    });

    // Toggle limit to variations row
    $('#limit_to_variations').on('change', function() {
        if ($(this).is(':checked')) {
            $('#included_variations_row').show();
            // Auto-load variations if categories are selected
            var selectedCategories = getSelectedCategories();
            if (selectedCategories.length > 0) {
                loadCategoryVariations(selectedCategories);
            }
        } else {
            $('#included_variations_row').hide();
        }
    });

    // Get selected included categories
    function getSelectedCategories() {
        var categories = [];
        $('input[name="included_categories[]"]:checked').each(function() {
            categories.push($(this).val());
        });
        return categories;
    }

    // Load variations from selected categories
    function loadCategoryVariations(categories) {
        if (categories.length === 0) {
            $('#included_variations_from_categories').empty().trigger('change');
            return;
        }

        $('#category_variations_loading').show();
        $('#included_variations_from_categories').prop('disabled', true);

        // Remember currently selected values
        var selectedValues = $('#included_variations_from_categories').val() || [];

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mmd_get_products_by_categories',
                nonce: '<?php echo esc_attr( wp_create_nonce('mmd_search_products') ); ?>',
                categories: categories
            },
            success: function(response) {
                $('#category_variations_loading').hide();
                $('#included_variations_from_categories').prop('disabled', false);

                if (response.success && response.data.products) {
                    var $select = $('#included_variations_from_categories');

                    // Clear and rebuild options
                    $select.empty();

                    // Group products by parent
                    var grouped = {};
                    var simpleProducts = [];

                    response.data.products.forEach(function(product) {
                        if (product.is_variation && product.parent_name) {
                            if (!grouped[product.parent_name]) {
                                grouped[product.parent_name] = [];
                            }
                            grouped[product.parent_name].push(product);
                        } else {
                            simpleProducts.push(product);
                        }
                    });

                    // Add simple products first
                    simpleProducts.forEach(function(product) {
                        var isSelected = selectedValues.indexOf(String(product.id)) !== -1;
                        var stockText = product.in_stock ? '' : ( ' ' + <?php echo wp_json_encode( __( '(out of stock)', 'incentiv' ) ); ?> );
                        $select.append(new Option(
                            product.name + ' (#' + product.id + ')' + stockText,
                            product.id,
                            isSelected,
                            isSelected
                        ));
                    });

                    // Add grouped variations
                    for (var parentName in grouped) {
                        var $optgroup = $('<optgroup label="' + parentName + '"></optgroup>');
                        grouped[parentName].forEach(function(variation) {
                            var isSelected = selectedValues.indexOf(String(variation.id)) !== -1;
                            var stockText = variation.in_stock ? '' : ( ' ' + <?php echo wp_json_encode( __( '(out of stock)', 'incentiv' ) ); ?> );
                            $optgroup.append(new Option(
                                '↳ ' + variation.name + ' (#' + variation.id + ')' + stockText,
                                variation.id,
                                isSelected,
                                isSelected
                            ));
                        });
                        $select.append($optgroup);
                    }

                    $select.trigger('change');
                }
            },
            error: function() {
                $('#category_variations_loading').hide();
                $('#included_variations_from_categories').prop('disabled', false);
                alert(<?php echo wp_json_encode( __( 'Error loading products', 'incentiv' ) ); ?>);
            }
        });
    }

    // Load variations button click
    $('#load_category_variations').on('click', function() {
        var categories = getSelectedCategories();
        if (categories.length === 0) {
            alert(<?php echo wp_json_encode( __( 'Please choose categories first', 'incentiv' ) ); ?>);
            return;
        }
        loadCategoryVariations(categories);
    });

    // Auto-reload when categories change (if limit is enabled)
    $('input[name="included_categories[]"]').on('change', function() {
        if ($('#limit_to_variations').is(':checked')) {
            var categories = getSelectedCategories();
            loadCategoryVariations(categories);
        }
    });

    // Toggle coupon codes row based on coupon_required checkbox
    $('#coupon_required').on('change', function() {
        if ($(this).is(':checked')) {
            $('#coupon_codes_row').show();
        } else {
            $('#coupon_codes_row').hide();
        }
    });

    // Initialize Select2 for rule exclusion
    $('#excluded_by_rules').select2({
        placeholder: <?php echo wp_json_encode( __( 'Choose rules to exclude...', 'incentiv' ) ); ?>,
        width: '100%',
        dir: 'rtl',
        allowClear: true
    });

    // BOGO / Quick Presets
    $(document).on('click', '.mmd-preset-btn', function() {
        var preset = $(this).data('preset');
        var rules = [];

        switch(preset) {
            case 'bogo50':
                rules = [{quantity: 2, discount: 50, type: 'percentage'}];
                break;
            case 'bogo_free':
                rules = [{quantity: 2, discount: 100, type: 'percentage'}];
                break;
            case 'buy4get1':
                rules = [{quantity: 5, discount: 100, type: 'percentage'}];
                break;
            case 'tiered':
                rules = [
                    {quantity: 2, discount: 10, type: 'percentage'},
                    {quantity: 3, discount: 20, type: 'percentage'},
                    {quantity: 4, discount: 30, type: 'percentage'},
                    {quantity: 5, discount: 100, type: 'percentage'}
                ];
                break;
            case 'buy3_30':
                rules = [{quantity: 3, discount: 30, type: 'percentage'}];
                break;
        }

        if (rules.length > 0) {
            // Clear existing rules
            $('#discount_rules_container').empty();

            // Add preset rules
            rules.forEach(function(rule, index) {
                var html = '<div class="mmd-discount-rule">' +
                    '<div class="mmd-rule-fields">' +
                    ( '<div class="mmd-field"><label>' + <?php echo wp_json_encode( __( 'Quantity', 'incentiv' ) ); ?> + '<\/label><input type="number" name="discount_rules[' ) + index + '][quantity]" value="' + rule.quantity + '" min="1"></div>' +
                    ( '<div class="mmd-field"><label>' + <?php echo wp_json_encode( __( 'Discount', 'incentiv' ) ); ?> + '<\/label><input type="number" name="discount_rules[' ) + index + '][discount]" value="' + rule.discount + '" min="0" step="0.01"></div>' +
                    ( '<div class="mmd-field"><label>' + <?php echo wp_json_encode( __( 'Type', 'incentiv' ) ); ?> + '<\/label><select name="discount_rules[' ) + index + '][type]"><option value="percentage"' + (rule.type === 'percentage' ? ' selected' : '') + ( '>' + <?php echo wp_json_encode( __( 'Percentage (%)', 'incentiv' ) ); ?> + '<\/option><option value="fixed"' ) + (rule.type === 'fixed' ? ' selected' : '') + ( '>' + <?php echo wp_json_encode( __( 'Fixed amount (₪)', 'incentiv' ) ); ?> + '<\/option><\/select><\/div>' ) +
                    ( '<button type="button" class="button mmd-remove-rule"><span class="dashicons dashicons-trash" style="font-size:14px;width:14px;height:14px;vertical-align:middle;"><\/span><span class="screen-reader-text">' + <?php echo wp_json_encode( __( 'Remove', 'incentiv' ) ); ?> + '<\/span><\/button>' ) +
                    '</div></div>';
                $('#discount_rules_container').append(html);
            });
        }
    });

    // Badge preview update
    function updateBadgePreview() {
        var text = $('#badge_text').val() || <?php echo wp_json_encode( __( 'Promotion', 'incentiv' ) ); ?>;
        var bg = $('#badge_bg_color').val();
        var color = $('#badge_text_color').val();
        $('.mmd-badge-preview').text(text).css({ background: bg, color: color });
    }
    $('#badge_text, #badge_bg_color, #badge_text_color').on('input change', updateBadgePreview);

    // Global notice preview update
    function updateGlobalNoticePreview() {
        var text = $('#global_notice_text').val() || <?php echo wp_json_encode( __( '🔥 Special offer! Buy 3 and get 30% off', 'incentiv' ) ); ?>;
        var bg = $('#global_notice_bg_color').val();
        var color = $('#global_notice_text_color').val();
        $('.mmd-global-notice-preview').text(text).css({ background: bg, color: color });
    }
    $('#global_notice_text, #global_notice_bg_color, #global_notice_text_color').on('input change', updateGlobalNoticePreview);

    // Show/hide dismiss limit row based on dismissible checkbox
    $('#global_notice_dismissible').on('change', function() {
        if ($(this).is(':checked')) {
            $('#dismiss_limit_row').show();
        } else {
            $('#dismiss_limit_row').hide();
        }
    });

    // Show/hide marquee direction row based on layout selection
    $('#global_notice_layout').on('change', function() {
        if ($(this).val() === 'marquee') {
            $('#marquee_direction_row').show();
        } else {
            $('#marquee_direction_row').hide();
        }
    });

    // Icon Picker
    (function() {
        var $input = $('#global_notice_icon');
        var $picker = $('#mmd_icon_picker');
        var $preview = $('#mmd_icon_preview');
        var $search = $('#mmd_icon_search');

        // Toggle picker on input click
        $input.on('click', function(e) {
            e.stopPropagation();
            $picker.toggle();
        });

        // Clear icon
        $('#mmd_clear_icon').on('click', function() {
            $input.val('');
            $preview.html('');
            $picker.hide();
        });

        // Tab switching
        $('.mmd-icon-tab').on('click', function() {
            var tab = $(this).data('tab');
            $('.mmd-icon-tab').removeClass('active');
            $(this).addClass('active');
            $('.mmd-icon-tab-content').hide();
            if (tab === 'emoji') {
                $('#mmd_emoji_tab').show();
            } else {
                $('#mmd_dashicons_tab').show();
            }
        });

        // Emoji selection
        $(document).on('click', '.mmd-emoji-option', function() {
            var val = $(this).data('value');
            $input.val(val);
            $preview.html(val);
            $picker.hide();
            updateGlobalNoticePreview();
        });

        // Dashicon selection
        $(document).on('click', '.mmd-dashicon-option', function() {
            var val = $(this).data('value');
            $input.val(val);
            $preview.html('<span class="dashicons ' + val + '"></span>');
            $picker.hide();
            updateGlobalNoticePreview();
        });

        // Search filter
        $search.on('input', function() {
            var query = $(this).val().toLowerCase();
            $('.mmd-icon-option').each(function() {
                var text = ($(this).data('value') + ' ' + ($(this).attr('title') || '')).toLowerCase();
                $(this).toggle(text.indexOf(query) !== -1 || query === '');
            });
        });

        // Close picker on outside click
        $(document).on('click', function(e) {
            if (!$(e.target).closest('#mmd_icon_picker, #global_notice_icon').length) {
                $picker.hide();
            }
        });

        // Prevent picker clicks from closing
        $picker.on('click', function(e) {
            e.stopPropagation();
        });
    })();
});
</script>


<script>
// ====================================================================
// Server-side drafts — "<?php echo esc_js( __( 'Save draft', 'incentiv' ) ); ?>" + automatic draft save for new /
// draft rules. Publishing (the green button) clears the draft flag.
// ====================================================================
jQuery(function ($) {
    var $form = $('form[data-mmd-autosave="rule"]').first();
    if (!$form.length) { return; }

    var initialStatus = <?php echo json_encode(isset($rule_set['status']) ? $rule_set['status'] : ''); ?>;
    var isNew = !$('input[name="rule_set_id"]').val();
    // Auto-draft only when nothing live is at stake: a brand-new rule or one
    // already in draft. Editing a live rule keeps the localStorage safety net.
    var draftContext = isNew || initialStatus === 'draft';
    if (draftContext) { $form.data('mmdServerDraftActive', true); }

    var $status = $('#mmd-draft-status');
    var saving = false, published = false;

    function stamp(msg) {
        var d = new Date();
        var hh = ('0' + d.getHours()).slice(-2), mm = ('0' + d.getMinutes()).slice(-2);
        $status.text(msg + ' · ' + hh + ':' + mm).addClass('is-visible');
    }

    function saveDraft(manual) {
        if (saving || published) { return; }
        if (!manual && !draftContext) { return; }         // never auto-downgrade a live rule
        if (!$('#rule_name').val() && !$('input[name="rule_set_id"]').val()) { return; } // nothing yet
        saving = true;
        if (manual) { $status.text(<?php echo wp_json_encode( __( 'Saving…', 'incentiv' ) ); ?>).addClass('is-visible'); }

        var payload = $form.serialize()
            + '&action=mmd_save_rule_draft'
            + '&nonce=' + encodeURIComponent((window.mmdUi && mmdUi.nonce) || '');

        $.post((window.mmdUi && mmdUi.ajaxurl) || ajaxurl, payload)
            .done(function (resp) {
                saving = false;
                if (!resp || !resp.success) { if (manual) { stamp(<?php echo wp_json_encode( __( 'Save failed', 'incentiv' ) ); ?>); } return; }
                var id = resp.data.id;
                if (id && !$('input[name="rule_set_id"]').val()) {
                    $('input[name="rule_set_id"]').val(id);
                    $form.attr('data-mmd-record-id', id);
                    draftContext = true;
                    $form.data('mmdServerDraftActive', true);
                    // Reflect in the URL so a reload re-opens this draft
                    try {
                        var u = new URL(window.location.href);
                        u.searchParams.set('action', 'edit');
                        u.searchParams.set('rule_set_id', id);
                        history.replaceState({}, '', u.toString());
                    } catch (e) {}
                }
                // Real draft persisted — drop the localStorage fallback copy
                if (window.mmdClearAutosave) { window.mmdClearAutosave(); }
                stamp(<?php echo wp_json_encode( __( 'Draft saved', 'incentiv' ) ); ?>);
            })
            .fail(function () { saving = false; if (manual) { stamp(<?php echo wp_json_encode( __( 'Save failed', 'incentiv' ) ); ?>); } });
    }

    $('#mmd-save-draft').on('click', function () { saveDraft(true); });

    // Publishing clears everything so we don't keep re-saving a draft
    $form.on('submit', function () { published = true; if (window.mmdClearAutosave) { window.mmdClearAutosave(); } });

    // Automatic drafts for new / draft rules (registered so PJAX clears it)
    (window.mmdSetInterval || setInterval)(function () { saveDraft(false); }, 12000);
    $(window).on('beforeunload.mmdpjax', function () { if (draftContext) { saveDraft(false); } });
});
</script>
