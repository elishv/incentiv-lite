<?php
/**
 * Admin Gift Rule Edit Page
 */

if (!defined('ABSPATH')) {
    exit;
}

$is_new = empty($rule_set['id']);
$categories = get_terms(array('taxonomy' => 'product_cat', 'hide_empty' => false));
$tags = get_terms(array('taxonomy' => 'product_tag', 'hide_empty' => false));
$attributes = wc_get_attribute_taxonomies();

// Ensure new fields have defaults for existing rules
$rule_set = wp_parse_args($rule_set, array(
    'gift_mode' => 'product_page',
    'cart_gift_notice' => __( 'You have a gift! Add a product from the participating categories to get a discount', 'incentiv' ),
    'cart_gift_display_mode' => 'inline',
    'show_gift_selection' => 'yes',
    'gift_limit_type' => 'per_cart',
    'gift_source' => 'specific',
    'gift_categories' => array(),
    'gift_excluded_categories' => array(),
    'gift_excluded_tags' => array(),
    'gift_excluded_products' => array(),
    'included_categories' => array(),
    'excluded_categories' => array(),
    'excluded_products_from_categories' => array(),
    'gift_attribute_slug' => '',
    'gift_attribute_values' => array(),
    'gift_additional_attributes' => array(),
    'gift_products_limit' => '0',
    'cart_message' => '',
    'checkout_message' => '',
    'shipping_method_condition' => '',
    'min_cart_total' => '',
    'max_cart_total' => '',
    'allowed_user_roles' => array(),
    'schedule_start' => '',
    'schedule_end' => '',
    'min_quantity_trigger' => '',
    'coupon_required' => 'no',
    'required_coupon_codes' => array(),
    'promo_notice_enabled' => 'no',
    'promo_notice_text' => '',
    'promo_notice_pages' => 'all',
    'promo_notice_link' => '',
    'promo_notice_link_text' => __( 'View products', 'incentiv' ),
    'combined_discount_enabled' => 'no',
    'combined_discount_rules' => array(),
    'combined_discount_mode' => 'quantity',
    'combined_discount_apply_to' => 'cheapest',
    'combined_discount_notice_enabled' => 'yes',
    'combined_discount_notice_title' => __( '💰 Buy in quantity and save!', 'incentiv' ),
    'combined_discount_notice_color' => '#4CAF50',
    'enabled' => 'yes',
    'name' => '',
    'selection_method' => 'all',
    'included_products' => array(),
    'excluded_products' => array(),
    'gift_type' => 'free',
    'gift_discount' => 0,
    'gift_products' => array(),
    'gift_selection_type' => 'single',
    'attribute_slug' => '',
    'allow_quantity' => 'no',
    'max_quantity' => '',
    'override_product_settings' => 'no',
    'total_quantity_limit' => '',
));
?>

<div class="wrap mmd-admin-wrap">
    <?php include dirname(__FILE__) . '/admin-nav-tabs.php'; ?>
    <div class="mmd-editor-head">
        <a href="<?php echo esc_url( admin_url('admin.php?page=mmd-gift-rules') ); ?>" class="mmd-back-btn" title="<?php esc_attr_e( 'Back to list', 'incentiv' ); ?>"><span class="msr mmd-dir-icon">arrow_forward</span></a>
        <div class="mmd-editor-head-titles">
            <h2><?php if ($is_new): ?><?php esc_html_e( 'New gift rule', 'incentiv' ); ?><?php else: ?><?php echo wp_kses_post( __( 'Edit gift rule &middot;', 'incentiv' ) ); ?> <?php echo esc_html($rule_set['name']); ?><?php endif; ?></h2>
            <?php if (!$is_new && !empty($rule_set['id'])): ?><div class="mmd-editor-id"><?php echo esc_html($rule_set['id']); ?></div><?php endif; ?>
        </div>
    </div>

    <?php
    // Include wizard for new rules (skip with skip_wizard=1 parameter)
    if ($is_new && (!isset($_GET['skip_wizard']) || $_GET['skip_wizard'] !== '1')) {
        include dirname(__FILE__) . '/admin-gift-rule-wizard.php';
    }

    // Styling options power the live preview column
    $mmd_gift_styling = MMD_Gift_Rules::get_instance()->get_styling_options();
    $mmd_gw_title = !empty($mmd_gift_styling['gift_box_title_text']) ? $mmd_gift_styling['gift_box_title_text'] : _x( '🎁 Choose your gift!', 'rule', 'incentiv' );
    $mmd_gw_bg1 = !empty($mmd_gift_styling['gift_box_bg_color_1']) ? $mmd_gift_styling['gift_box_bg_color_1'] : '#7c3aed';
    $mmd_gw_bg2 = !empty($mmd_gift_styling['gift_box_bg_color_2']) ? $mmd_gift_styling['gift_box_bg_color_2'] : '#a855f7';
    ?>

    <div class="mmd-editor-grid">
    <div class="mmd-editor-main">

    <!-- Step navigation (filled by JS) -->
    <div class="mmd-stepper" id="mmd-gift-stepper">
        <div class="mmd-stepper-steps" id="mmd-gift-stepper-steps"></div>
    </div>

    <div class="mmd-admin-container" data-mmd-autosave="gift" data-mmd-record-id="<?php echo esc_attr(isset($rule_set['id']) ? $rule_set['id'] : ''); ?>">

        <!-- Basic Settings -->
        <div class="mmd-card">
            <h2 class="mmd-card-title"><span class="dashicons dashicons-clipboard"></span> <?php esc_html_e( 'Basic settings', 'incentiv' ); ?></h2>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="rule_name"><?php esc_html_e( 'Rule name *', 'incentiv' ); ?></label>
                    </th>
                    <td>
                        <input type="text" name="rule_name" id="rule_name" value="<?php echo esc_attr($rule_set['name']); ?>" class="large-text" required>
                        <p class="description"><?php esc_html_e( 'A descriptive name for the gift rule (internal use only)', 'incentiv' ); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="enabled"><?php esc_html_e( 'Enable this rule', 'incentiv' ); ?></label>
                    </th>
                    <td>
                        <label class="mmd-switch">
                            <input type="checkbox" name="enabled" id="enabled" <?php echo (($rule_set['enabled'] ?? '') === 'yes' || (isset($rule_set['status']) && $rule_set['status'] === 'draft')) ? 'checked' : ''; ?>>
                            <span class="mmd-slider"></span>
                        </label>
                        <p class="description"><?php esc_html_e( 'Turn off to disable the rule temporarily without deleting it', 'incentiv' ); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="override_product_settings"><?php esc_html_e( 'Override product settings', 'incentiv' ); ?></label>
                    </th>
                    <td>
                        <label class="mmd-switch">
                            <input type="checkbox" name="override_product_settings" id="override_product_settings" <?php checked($rule_set['override_product_settings'], 'yes'); ?>>
                            <span class="mmd-slider"></span>
                        </label>
                        <p class="description"><?php esc_html_e( 'If enabled, this rule overrides the gift settings of individual products', 'incentiv' ); ?></p>
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
                        <p class="description"><?php esc_html_e( 'Choose how to identify products that get gifts', 'incentiv' ); ?></p>
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
                                        <input type="checkbox" name="included_categories[]" value="<?php echo esc_attr($category->term_id); ?>" <?php checked(in_array($category->term_id, $rule_set['included_categories'])); ?>>
                                        <?php echo esc_html($category->name); ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <p class="description"><?php esc_html_e( 'Choose categories that are eligible for gifts', 'incentiv' ); ?></p>
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
                                        <input type="checkbox" name="excluded_categories[]" value="<?php echo esc_attr($category->term_id); ?>" <?php checked(in_array($category->term_id, $rule_set['excluded_categories'])); ?>>
                                        <?php echo esc_html($category->name); ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <p class="description"><?php esc_html_e( 'Choose categories that are not eligible for gifts (overrides included)', 'incentiv' ); ?></p>
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
                            <p class="description"><?php esc_html_e( 'Search and select specific products or variations that are not eligible for gifts, even if they are in included categories', 'incentiv' ); ?></p>
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
                            <p class="description"><?php esc_html_e( 'Search and select specific products or variations that are eligible for gifts', 'incentiv' ); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="excluded_products"><?php esc_html_e( 'Excluded products/variations', 'incentiv' ); ?></label>
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
                            <p class="description"><?php esc_html_e( 'Search and select specific products or variations that are not eligible for gifts', 'incentiv' ); ?></p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Attribute Selection -->
            <div id="attribute_section" class="mmd-section" style="display: <?php echo $rule_set['selection_method'] === 'attribute' ? 'block' : 'none'; ?>;">
                <h3><?php esc_html_e( 'Attribute', 'incentiv' ); ?></h3>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="attribute_slug"><?php esc_html_e( 'Attribute', 'incentiv' ); ?></label>
                        </th>
                        <td>
                            <select name="attribute_slug" id="attribute_slug" class="regular-text">
                                <option value=""><?php esc_html_e( '-- Choose an attribute --', 'incentiv' ); ?></option>
                                <?php foreach ($attributes as $attribute): ?>
                                    <option value="<?php echo esc_attr('pa_' . $attribute->attribute_name); ?>" <?php selected($rule_set['attribute_slug'], 'pa_' . $attribute->attribute_name); ?>>
                                        <?php echo esc_html($attribute->attribute_label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php esc_html_e( 'Choose an attribute to filter by', 'incentiv' ); ?></p>
                        </td>
                    </tr>

                    <tr id="attribute_values_row" style="display: <?php echo !empty($rule_set['attribute_slug']) ? 'table-row' : 'none'; ?>;">
                        <th scope="row">
                            <label><?php esc_html_e( 'Attribute values', 'incentiv' ); ?></label>
                        </th>
                        <td>
                            <div id="attribute_values_list" class="mmd-checkbox-group">
                                <!-- Dynamically loaded -->
                            </div>
                            <p class="description"><?php esc_html_e( 'Choose values that are eligible for gifts', 'incentiv' ); ?></p>
                        </td>
                    </tr>

                    <tr id="attribute_excluded_values_row" style="display: <?php echo !empty($rule_set['attribute_slug']) ? 'table-row' : 'none'; ?>;">
                        <th scope="row">
                            <label><?php esc_html_e( 'Excluded attribute values', 'incentiv' ); ?></label>
                        </th>
                        <td>
                            <div id="attribute_excluded_values_list" class="mmd-checkbox-group">
                                <!-- Dynamically loaded -->
                            </div>
                            <p class="description"><?php esc_html_e( 'Choose values that are not eligible for gifts', 'incentiv' ); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Gift Settings -->
        <div class="mmd-card">
            <h2 class="mmd-card-title"><span class="dashicons dashicons-gifts"></span> <?php esc_html_e( 'Gift settings', 'incentiv' ); ?></h2>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="gift_type"><?php echo esc_html_x( 'Gift type', 'summary', 'incentiv' ); ?></label>
                    </th>
                    <td>
                        <div class="mmd-gift-type-selector">
                            <label class="mmd-gift-type-option <?php echo $rule_set['gift_type'] === 'free' ? 'active' : ''; ?>">
                                <input type="radio" name="gift_type" value="free" <?php checked($rule_set['gift_type'], 'free'); ?>>
                                <span class="mmd-gift-type-label">
                                    <span class="dashicons dashicons-cart"></span>
                                    <strong><?php esc_html_e( 'Free gift', 'incentiv' ); ?></strong>
                                    <small><?php esc_html_e( 'The product will be completely free', 'incentiv' ); ?></small>
                                </span>
                            </label>

                            <label class="mmd-gift-type-option <?php echo $rule_set['gift_type'] === 'discount' ? 'active' : ''; ?>">
                                <input type="radio" name="gift_type" value="discount" <?php checked($rule_set['gift_type'], 'discount'); ?>>
                                <span class="mmd-gift-type-label">
                                    <span class="dashicons dashicons-tag"></span>
                                    <strong><?php esc_html_e( 'Percentage discount', 'incentiv' ); ?></strong>
                                    <small><?php echo esc_html_x( 'A percentage off the price', 'gift', 'incentiv' ); ?></small>
                                </span>
                            </label>

                            <label class="mmd-gift-type-option <?php echo $rule_set['gift_type'] === 'fixed_discount' ? 'active' : ''; ?>">
                                <input type="radio" name="gift_type" value="fixed_discount" <?php checked($rule_set['gift_type'], 'fixed_discount'); ?>>
                                <span class="mmd-gift-type-label">
                                    <span class="dashicons dashicons-money"></span>
                                    <strong><?php esc_html_e( 'Fixed discount', 'incentiv' ); ?></strong>
                                    <small><?php esc_html_e( 'Fixed amount discount', 'incentiv' ); ?></small>
                                </span>
                            </label>
                        </div>
                    </td>
                </tr>

                <tr id="discount_amount_row" style="display: <?php echo in_array($rule_set['gift_type'], array('discount', 'fixed_discount')) ? 'table-row' : 'none'; ?>;">
                    <th scope="row">
                        <label for="gift_discount"><?php esc_html_e( 'Discount amount', 'incentiv' ); ?></label>
                    </th>
                    <td>
                        <input type="number" name="gift_discount" id="gift_discount" value="<?php echo esc_attr($rule_set['gift_discount']); ?>" min="0" step="0.01" class="small-text">
                        <span id="discount_unit">%</span>
                        <p class="description" id="discount_description"><?php esc_html_e( 'Enter a discount percentage (0-100)', 'incentiv' ); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label><?php esc_html_e( 'How the gift is given', 'incentiv' ); ?></label>
                    </th>
                    <td>
                        <div class="mmd-gift-mode-selector">
                            <label class="mmd-gift-mode-option <?php echo $rule_set['gift_mode'] === 'product_page' ? 'active' : ''; ?>">
                                <input type="radio" name="gift_mode" value="product_page" <?php checked($rule_set['gift_mode'], 'product_page'); ?>>
                                <span class="mmd-gift-mode-label">
                                    <span class="dashicons dashicons-products"></span>
                                    <strong><?php esc_html_e( 'On the product page', 'incentiv' ); ?></strong>
                                    <small><?php esc_html_e( 'The customer chooses a gift on the product page before adding to the cart', 'incentiv' ); ?></small>
                                </span>
                            </label>

                            <label class="mmd-gift-mode-option <?php echo $rule_set['gift_mode'] === 'cart_based' ? 'active' : ''; ?>">
                                <input type="radio" name="gift_mode" value="cart_based" <?php checked($rule_set['gift_mode'], 'cart_based'); ?>>
                                <span class="mmd-gift-mode-label">
                                    <span class="dashicons dashicons-cart"></span>
                                    <strong><?php esc_html_e( 'Gift in the cart', 'incentiv' ); ?></strong>
                                    <small><?php esc_html_e( 'The customer adds a product from the store and the gift is applied automatically in the cart', 'incentiv' ); ?></small>
                                </span>
                            </label>
                        </div>
                        <p class="description"><?php esc_html_e( 'Choose how the customer gets the gift', 'incentiv' ); ?></p>
                    </td>
                </tr>

                <tr id="cart_gift_notice_row" style="display: <?php echo $rule_set['gift_mode'] === 'cart_based' ? 'table-row' : 'none'; ?>;">
                    <th scope="row">
                        <label for="cart_gift_notice"><?php esc_html_e( 'Cart message', 'incentiv' ); ?></label>
                    </th>
                    <td>
                        <input type="text" name="cart_gift_notice" id="cart_gift_notice" value="<?php echo esc_attr($rule_set['cart_gift_notice']); ?>" class="large-text">
                        <p class="description"><?php esc_html_e( 'A message shown to the customer in the cart when they qualify for a gift. The customer can add a product from the participating categories/products.', 'incentiv' ); ?></p>
                    </td>
                </tr>

                <tr id="cart_gift_display_mode_row" style="display: <?php echo $rule_set['gift_mode'] === 'cart_based' ? 'table-row' : 'none'; ?>;">
                    <th scope="row">
                        <label for="cart_gift_display_mode"><?php esc_html_e( 'Cart selection display', 'incentiv' ); ?></label>
                    </th>
                    <td>
                        <select name="cart_gift_display_mode" id="cart_gift_display_mode" class="regular-text">
                            <option value="inline" <?php selected(isset($rule_set['cart_gift_display_mode']) ? $rule_set['cart_gift_display_mode'] : 'inline', 'inline'); ?>><?php esc_html_e( 'Inline on the page', 'incentiv' ); ?></option>
                            <option value="modal" <?php selected(isset($rule_set['cart_gift_display_mode']) ? $rule_set['cart_gift_display_mode'] : '', 'modal'); ?>><?php esc_html_e( 'Popup (modal)', 'incentiv' ); ?></option>
                        </select>
                        <p class="description">
                            <strong>Inline:</strong> <?php esc_html_e( 'The gift selection is shown directly on the cart/checkout page', 'incentiv' ); ?><br>
                            <strong>Modal:</strong> <?php esc_html_e( 'The customer clicks a "Choose a gift" button and sees a popup with the selection', 'incentiv' ); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="show_gift_selection"><?php esc_html_e( 'Show gift selection on the product page', 'incentiv' ); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" name="show_gift_selection" id="show_gift_selection" value="yes" <?php checked($rule_set['show_gift_selection'], 'yes'); ?>>
                            <?php esc_html_e( 'Show the gift selection window on the product page', 'incentiv' ); ?>
                        </label>
                        <p class="description"><?php esc_html_e( 'When disabled, the customer won\'t see the gift list on the product page. Useful when you want customers to add regular products from the store.', 'incentiv' ); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="gift_limit_type"><?php esc_html_e( 'Gift limit', 'incentiv' ); ?></label>
                    </th>
                    <td>
                        <select name="gift_limit_type" id="gift_limit_type" class="regular-text">
                            <option value="per_cart" <?php selected($rule_set['gift_limit_type'], 'per_cart'); ?>><?php esc_html_e( 'One gift per cart', 'incentiv' ); ?></option>
                            <option value="per_product" <?php selected($rule_set['gift_limit_type'], 'per_product'); ?>><?php esc_html_e( 'A gift for each qualifying product', 'incentiv' ); ?></option>
                            <option value="unlimited" <?php selected($rule_set['gift_limit_type'], 'unlimited'); ?>><?php esc_html_e( 'Unlimited', 'incentiv' ); ?></option>
                        </select>
                        <p class="description">
                            <?php echo wp_kses_post( __( '<strong>One gift per cart:</strong> the customer gets only one gift, no matter how many qualifying products are in the cart<br> <strong>A gift for each qualifying product:</strong> the customer gets a gift for each qualifying product (2 products = 2 gifts)<br> <strong>Unlimited:</strong> every matching product in the cart gets the discount', 'incentiv' ) ); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="gift_source"><?php esc_html_e( 'Gift product source', 'incentiv' ); ?></label>
                    </th>
                    <td>
                        <select name="gift_source" id="gift_source" class="regular-text">
                            <option value="specific" <?php selected($rule_set['gift_source'], 'specific'); ?>><?php esc_html_e( 'Specific products', 'incentiv' ); ?></option>
                            <option value="category" <?php selected($rule_set['gift_source'], 'category'); ?>><?php esc_html_e( 'By categories', 'incentiv' ); ?></option>
                        </select>
                        <p class="description"><?php esc_html_e( 'Choose whether to set specific gift products or allow a choice from categories', 'incentiv' ); ?></p>
                    </td>
                </tr>

                <!-- Specific Products Selection -->
                <tr id="gift_products_row" style="display: <?php echo $rule_set['gift_source'] === 'specific' ? 'table-row' : 'none'; ?>;">
                    <th scope="row">
                        <label for="gift_products"><?php echo esc_html_x( 'Gift products', 'flow', 'incentiv' ); ?></label>
                    </th>
                    <td>
                        <select name="gift_products[]" id="gift_products" class="mmd-product-search-multiple" multiple style="width: 100%;">
                            <?php
                            if (!empty($rule_set['gift_products'])) {
                                foreach ($rule_set['gift_products'] as $product_id) {
                                    $product = wc_get_product($product_id);
                                    if ($product) {
                                        echo '<option value="' . esc_attr($product_id) . '" selected>' . esc_html($product->get_name()) . ' (#' . esc_html( $product_id ) . ')</option>';
                                    }
                                }
                            }
                            ?>
                        </select>
                        <p class="description"><?php esc_html_e( 'Search and select products to offer as gifts', 'incentiv' ); ?></p>
                    </td>
                </tr>
            </table>

            <!-- Category-based Gift Selection -->
            <div id="gift_category_section" class="mmd-section" style="display: <?php echo $rule_set['gift_source'] === 'category' ? 'block' : 'none'; ?>;">
                <h3><span class="dashicons dashicons-tag"></span> <?php esc_html_e( 'Gift selection by categories', 'incentiv' ); ?></h3>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label><?php echo esc_html_x( 'Gift categories', 'rule', 'incentiv' ); ?></label>
                        </th>
                        <td>
                            <div class="mmd-checkbox-group">
                                <?php foreach ($categories as $category): ?>
                                    <label>
                                        <input type="checkbox" name="gift_categories[]" value="<?php echo esc_attr($category->term_id); ?>" <?php checked(in_array($category->term_id, $rule_set['gift_categories'])); ?>>
                                        <?php echo esc_html($category->name); ?> (<?php echo esc_html( $category->count ); ?>)
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <p class="description"><?php esc_html_e( 'Choose categories: the customer can choose one product from all products in these categories', 'incentiv' ); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="gift_attribute_slug"><?php esc_html_e( 'Filter by main attribute', 'incentiv' ); ?></label>
                        </th>
                        <td>
                            <select name="gift_attribute_slug" id="gift_attribute_slug" class="regular-text">
                                <option value=""><?php esc_html_e( '-- No attribute filter --', 'incentiv' ); ?></option>
                                <?php foreach ($attributes as $attribute): ?>
                                    <option value="<?php echo esc_attr('pa_' . $attribute->attribute_name); ?>" <?php selected($rule_set['gift_attribute_slug'], 'pa_' . $attribute->attribute_name); ?>>
                                        <?php echo esc_html($attribute->attribute_label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php esc_html_e( 'Choose a main attribute to filter the gift products by (for example: weight)', 'incentiv' ); ?></p>
                        </td>
                    </tr>

                    <tr id="gift_attribute_values_row" style="display: <?php echo !empty($rule_set['gift_attribute_slug']) ? 'table-row' : 'none'; ?>;">
                        <th scope="row">
                            <label><?php esc_html_e( 'Attribute values for gifts', 'incentiv' ); ?></label>
                        </th>
                        <td>
                            <div id="gift_attribute_values_list" class="mmd-checkbox-group">
                                <!-- Dynamically loaded -->
                            </div>
                            <p class="description"><?php esc_html_e( 'Choose which attribute values are available as gifts (for example: only 100 g)', 'incentiv' ); ?></p>
                        </td>
                    </tr>

                    <!-- Additional attribute filters -->
                    <tr id="gift_additional_attributes_row" style="display: <?php echo !empty($rule_set['gift_attribute_slug']) ? 'table-row' : 'none'; ?>;">
                        <th scope="row">
                            <label><?php esc_html_e( 'Additional filter attributes', 'incentiv' ); ?></label>
                        </th>
                        <td>
                            <div id="gift_additional_attributes_container">
                                <?php
                                $additional_attrs = isset($rule_set['gift_additional_attributes']) ? $rule_set['gift_additional_attributes'] : array();
                                if (!empty($additional_attrs)):
                                    foreach ($additional_attrs as $idx => $add_attr):
                                ?>
                                    <div class="mmd-additional-attr-row" style="display: flex; gap: 10px; margin-bottom: 10px; padding: 10px; background: #f9f9f9; border-radius: 4px; border: 1px solid #e0e0e0; align-items: flex-start;">
                                        <div style="flex: 1;">
                                            <label style="display: block; font-size: 12px; color: #666; margin-bottom: 3px;"><?php esc_html_e( 'Attribute', 'incentiv' ); ?></label>
                                            <select class="gift-additional-attr-slug regular-text" style="width: 100%;">
                                                <option value=""><?php esc_html_e( '-- Choose an attribute --', 'incentiv' ); ?></option>
                                                <?php foreach ($attributes as $attribute): ?>
                                                    <option value="<?php echo esc_attr('pa_' . $attribute->attribute_name); ?>" <?php selected(isset($add_attr['slug']) ? $add_attr['slug'] : '', 'pa_' . $attribute->attribute_name); ?>>
                                                        <?php echo esc_html($attribute->attribute_label); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div style="flex: 2;">
                                            <label style="display: block; font-size: 12px; color: #666; margin-bottom: 3px;"><?php esc_html_e( 'Values', 'incentiv' ); ?></label>
                                            <div class="gift-additional-attr-values mmd-checkbox-group">
                                                <!-- Dynamically loaded -->
                                            </div>
                                        </div>
                                        <div style="padding-top: 20px;">
                                            <button type="button" class="button remove-additional-attr" title="<?php esc_attr_e( 'Remove', 'incentiv' ); ?>"><span class="dashicons dashicons-trash" style="vertical-align: middle;"></span></button>
                                        </div>
                                    </div>
                                <?php
                                    endforeach;
                                endif;
                                ?>
                            </div>
                            <button type="button" class="button" id="add-gift-additional-attr">
                                <span class="dashicons dashicons-plus-alt" style="vertical-align: middle;"></span> <?php esc_html_e( 'Add another attribute', 'incentiv' ); ?>
                            </button>
                            <p class="description"><?php esc_html_e( 'You can filter by several attributes at once (for example: weight=250g and grind=espresso)', 'incentiv' ); ?></p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Gift Products Limit -->
            <div id="gift_products_limit_section" class="mmd-section" style="display: <?php echo $rule_set['gift_source'] === 'category' ? 'block' : 'none'; ?>;">
                <h3><?php esc_html_e( '📊 Limit the products to choose from', 'incentiv' ); ?></h3>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="gift_products_limit"><?php esc_html_e( 'Maximum number of products to show', 'incentiv' ); ?></label>
                        </th>
                        <td>
                            <input type="number" name="gift_products_limit" id="gift_products_limit" value="<?php echo esc_attr($rule_set['gift_products_limit']); ?>" min="0" max="1000" class="small-text">
                            <p class="description"><?php esc_html_e( 'The maximum number of products shown to the customer to choose from (0 = unlimited). With a long list, pagination appears', 'incentiv' ); ?></p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Gift Exclusions Section -->
            <div id="gift_exclusions_section" class="mmd-section" style="display: <?php echo $rule_set['gift_source'] === 'category' ? 'block' : 'none'; ?>;">
                <h3><span class="dashicons dashicons-dismiss"></span> <?php esc_html_e( 'Filter and exclude products', 'incentiv' ); ?></h3>
                <p class="description" style="margin-bottom: 15px;"><?php esc_html_e( 'Set which products are not available as gifts even though they are in the selected categories', 'incentiv' ); ?></p>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label><?php esc_html_e( 'Exclude categories', 'incentiv' ); ?></label>
                        </th>
                        <td>
                            <div class="mmd-checkbox-group">
                                <?php foreach ($categories as $category): ?>
                                    <label>
                                        <input type="checkbox" name="gift_excluded_categories[]" value="<?php echo esc_attr($category->term_id); ?>" <?php checked(in_array($category->term_id, $rule_set['gift_excluded_categories'])); ?>>
                                        <?php echo esc_html($category->name); ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <p class="description"><?php esc_html_e( 'Products in these categories won\'t be available as gifts (overrides included categories)', 'incentiv' ); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label><?php esc_html_e( 'Exclude tags', 'incentiv' ); ?></label>
                        </th>
                        <td>
                            <div class="mmd-checkbox-group">
                                <?php if (!empty($tags) && !is_wp_error($tags)): ?>
                                    <?php foreach ($tags as $tag): ?>
                                        <label>
                                            <input type="checkbox" name="gift_excluded_tags[]" value="<?php echo esc_attr($tag->term_id); ?>" <?php checked(in_array($tag->term_id, $rule_set['gift_excluded_tags'])); ?>>
                                            <?php echo esc_html($tag->name); ?>
                                        </label>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p class="description"><?php esc_html_e( 'No product tags available', 'incentiv' ); ?></p>
                                <?php endif; ?>
                            </div>
                            <p class="description"><?php esc_html_e( 'Products with these tags won\'t be available as gifts', 'incentiv' ); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="gift_excluded_products"><?php esc_html_e( 'Exclude specific products', 'incentiv' ); ?></label>
                        </th>
                        <td>
                            <select name="gift_excluded_products[]" id="gift_excluded_products" class="mmd-product-search-multiple" multiple style="width: 100%;">
                                <?php
                                if (!empty($rule_set['gift_excluded_products'])) {
                                    foreach ($rule_set['gift_excluded_products'] as $product_id) {
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
                            <p class="description"><?php esc_html_e( 'Search and select specific products or variations that will not be available as gifts', 'incentiv' ); ?></p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Custom Messages for Cart & Checkout -->
            <div class="mmd-section">
                <h3><span class="dashicons dashicons-megaphone"></span> <?php esc_html_e( 'Custom messages', 'incentiv' ); ?></h3>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="cart_message"><?php esc_html_e( 'Cart page message', 'incentiv' ); ?></label>
                        </th>
                        <td>
                            <textarea name="cart_message" id="cart_message" rows="3" class="large-text"><?php echo esc_textarea($rule_set['cart_message']); ?></textarea>
                            <p class="description"><?php esc_html_e( 'A custom message shown on the cart page when the gift rule is active. Leave empty to use the default message. Supports basic HTML.', 'incentiv' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="checkout_message"><?php esc_html_e( 'Checkout page message', 'incentiv' ); ?></label>
                        </th>
                        <td>
                            <textarea name="checkout_message" id="checkout_message" rows="3" class="large-text"><?php echo esc_textarea($rule_set['checkout_message']); ?></textarea>
                            <p class="description"><?php esc_html_e( 'A custom message shown on the checkout page when the gift rule is active. Leave empty to use the default message. Supports basic HTML.', 'incentiv' ); ?></p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Shipping Method Condition -->
            <div class="mmd-section">
                <h3><?php esc_html_e( '🚚 Shipping method condition', 'incentiv' ); ?></h3>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="shipping_method_condition"><?php esc_html_e( 'Limit to a shipping method', 'incentiv' ); ?></label>
                        </th>
                        <td>
                            <select name="shipping_method_condition" id="shipping_method_condition" class="regular-text">
                                <option value="" <?php selected($rule_set['shipping_method_condition'], ''); ?>><?php esc_html_e( 'No limit - all shipping methods', 'incentiv' ); ?></option>
                                <option value="_loading_" disabled><?php esc_html_e( 'Loading shipping methods...', 'incentiv' ); ?></option>
                            </select>
                            <p class="description"><?php esc_html_e( 'Choose a specific shipping method: the gift is only available when the customer chooses this shipping method. Leave empty to enable it for all shipping methods.', 'incentiv' ); ?></p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Cart Total Conditions -->
            <div class="mmd-section">
                <h3><span class="dashicons dashicons-money-alt"></span> <?php esc_html_e( 'Cart total conditions', 'incentiv' ); ?></h3>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="min_cart_total"><?php echo esc_html_x( 'Minimum cart total', 'gift', 'incentiv' ); ?></label>
                        </th>
                        <td>
                            <input type="number" name="min_cart_total" id="min_cart_total" value="<?php echo esc_attr($rule_set['min_cart_total']); ?>" min="0" step="0.01" class="small-text">
                            <p class="description"><?php esc_html_e( 'The minimum cart total required before the gift is available. Leave empty for no limit.', 'incentiv' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="max_cart_total"><?php esc_html_e( 'Maximum cart total', 'incentiv' ); ?></label>
                        </th>
                        <td>
                            <input type="number" name="max_cart_total" id="max_cart_total" value="<?php echo esc_attr($rule_set['max_cart_total']); ?>" min="0" step="0.01" class="small-text">
                            <p class="description"><?php esc_html_e( 'The cart total above which the gift isn\'t available. Leave empty for no limit.', 'incentiv' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="min_quantity_trigger"><?php esc_html_e( 'Minimum quantity to qualify', 'incentiv' ); ?></label>
                        </th>
                        <td>
                            <input type="number" name="min_quantity_trigger" id="min_quantity_trigger" value="<?php echo esc_attr($rule_set['min_quantity_trigger']); ?>" min="0" step="1" class="small-text">
                            <p class="description"><?php esc_html_e( 'The minimum quantity of matching products in the cart to get the gift (useful for BOGO). Leave empty for no limit.', 'incentiv' ); ?></p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- User Role Conditions -->
            <div class="mmd-section">
                <h3><span class="dashicons dashicons-admin-users"></span> <?php esc_html_e( 'User role conditions', 'incentiv' ); ?></h3>
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
                            <p class="description"><?php esc_html_e( 'Choose the user roles the gift is available to. Leave empty to enable it for everyone.', 'incentiv' ); ?></p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Coupon requirement -->
            <div class="mmd-section">
                <h3><span class="dashicons dashicons-tickets-alt"></span> <?php esc_html_e( 'Coupon requirement', 'incentiv' ); ?></h3>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="coupon_required"><?php esc_html_e( 'Show the gift only with a coupon', 'incentiv' ); ?></label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" name="coupon_required" id="coupon_required" value="yes" <?php checked($rule_set['coupon_required'], 'yes'); ?>>
                                <?php esc_html_e( 'The gift is shown only when one of the required coupons is applied in the cart', 'incentiv' ); ?>
                            </label>
                            <p class="description">When enabled, the gift selector will only render if one of the listed coupon codes is applied to the cart.</p>
                        </td>
                    </tr>
                    <tr id="required_coupon_codes_row" style="display: <?php echo $rule_set['coupon_required'] === 'yes' ? 'table-row' : 'none'; ?>;">
                        <th scope="row">
                            <label for="required_coupon_codes"><?php esc_html_e( 'Coupon codes', 'incentiv' ); ?></label>
                        </th>
                        <td>
                            <?php
                            $codes_value = $rule_set['required_coupon_codes'];
                            if (is_array($codes_value)) {
                                $codes_value = implode(', ', $codes_value);
                            }
                            ?>
                            <input type="text" name="required_coupon_codes" id="required_coupon_codes" value="<?php echo esc_attr($codes_value); ?>" class="large-text" placeholder="autocoffee, summer25, ...">
                            <p class="description"><?php esc_html_e( 'Separate the coupons with commas. The gift is shown if at least one of the coupons is applied.', 'incentiv' ); ?></p>
                        </td>
                    </tr>
                </table>
                <script>
                jQuery(function($) {
                    $('#coupon_required').on('change', function() {
                        $('#required_coupon_codes_row').toggle($(this).is(':checked'));
                    });
                });
                </script>
            </div>

            <!-- Schedule -->
            <div class="mmd-section">
                <h3><span class="dashicons dashicons-calendar-alt"></span> <?php esc_html_e( 'Scheduling', 'incentiv' ); ?></h3>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="schedule_start"><?php esc_html_e( 'Start date', 'incentiv' ); ?></label>
                        </th>
                        <td>
                            <input type="datetime-local" name="schedule_start" id="schedule_start" value="<?php echo esc_attr($rule_set['schedule_start']); ?>" class="regular-text">
                            <p class="description"><?php esc_html_e( 'The date and time when the gift becomes available. Leave empty to start immediately.', 'incentiv' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="schedule_end"><?php esc_html_e( 'End date', 'incentiv' ); ?></label>
                        </th>
                        <td>
                            <input type="datetime-local" name="schedule_end" id="schedule_end" value="<?php echo esc_attr($rule_set['schedule_end']); ?>" class="regular-text">
                            <p class="description"><?php esc_html_e( 'The date and time when the gift stops being available. Leave empty for no limit.', 'incentiv' ); ?></p>
                        </td>
                    </tr>
                </table>
            </div>

            <table class="form-table">

                <tr>
                    <th scope="row">
                        <label for="gift_selection_type"><?php esc_html_e( 'Selection type', 'incentiv' ); ?></label>
                    </th>
                    <td>
                        <select name="gift_selection_type" id="gift_selection_type" class="regular-text">
                            <option value="single" <?php selected($rule_set['gift_selection_type'], 'single'); ?>><?php esc_html_e( 'Choose one gift', 'incentiv' ); ?></option>
                            <option value="multiple" <?php selected($rule_set['gift_selection_type'], 'multiple'); ?>><?php esc_html_e( 'Choose several gifts', 'incentiv' ); ?></option>
                        </select>
                        <p class="description"><?php esc_html_e( 'Whether the customer can choose one gift or several gifts', 'incentiv' ); ?></p>
                    </td>
                </tr>

                <tr id="quantity_control_row" style="display: <?php echo $rule_set['gift_selection_type'] === 'multiple' ? 'table-row' : 'none'; ?>;">
                    <th scope="row">
                        <label for="allow_quantity"><?php esc_html_e( 'Allow quantity selection', 'incentiv' ); ?></label>
                    </th>
                    <td>
                        <label class="mmd-switch">
                            <input type="checkbox" name="allow_quantity" id="allow_quantity" <?php checked(isset($rule_set['allow_quantity']) ? $rule_set['allow_quantity'] : 'no', 'yes'); ?>>
                            <span class="mmd-slider"></span>
                        </label>
                        <p class="description"><?php esc_html_e( 'Let customers choose how many units of each gift', 'incentiv' ); ?></p>
                    </td>
                </tr>

                <tr id="max_quantity_row" style="display: <?php echo ($rule_set['gift_selection_type'] === 'multiple' && isset($rule_set['allow_quantity']) && $rule_set['allow_quantity'] === 'yes') ? 'table-row' : 'none'; ?>;">
                    <th scope="row">
                        <label for="max_quantity"><?php esc_html_e( 'Maximum quantity', 'incentiv' ); ?></label>
                    </th>
                    <td>
                        <input type="number" name="max_quantity" id="max_quantity" value="<?php echo esc_attr(isset($rule_set['max_quantity']) ? $rule_set['max_quantity'] : '5'); ?>" min="1" max="100" class="small-text">
                        <p class="description"><?php esc_html_e( 'Maximum quantity per gift (1-100)', 'incentiv' ); ?></p>
                    </td>
                </tr>

                <tr id="total_quantity_limit_row" style="display: <?php echo ($rule_set['gift_selection_type'] === 'multiple' && isset($rule_set['allow_quantity']) && $rule_set['allow_quantity'] === 'yes') ? 'table-row' : 'none'; ?>;">
                    <th scope="row">
                        <label for="total_quantity_limit"><?php esc_html_e( 'Total gifts allowed', 'incentiv' ); ?></label>
                    </th>
                    <td>
                        <input type="number" name="total_quantity_limit" id="total_quantity_limit" value="<?php echo esc_attr(isset($rule_set['total_quantity_limit']) ? $rule_set['total_quantity_limit'] : '0'); ?>" min="0" max="100" class="small-text">
                        <p class="description"><?php esc_html_e( 'Total gift units allowed (0 = unlimited; otherwise exactly this number must be chosen)', 'incentiv' ); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Default Variations -->
        <div class="mmd-card" id="default-variations-card" style="display: none;">
            <h2 class="mmd-card-title"><span class="dashicons dashicons-admin-generic"></span> <?php esc_html_e( 'Default variations', 'incentiv' ); ?></h2>
            <p class="description"><?php esc_html_e( 'Set a default variation for each variable product. The customer can change the selection.', 'incentiv' ); ?></p>
            <div id="default-variations-container"></div>
        </div>

        <!-- Promo Notice Settings -->
        <div class="mmd-card">
            <h2 class="mmd-card-title"><span class="dashicons dashicons-megaphone"></span> <?php esc_html_e( 'Promo message', 'incentiv' ); ?></h2>
            <p class="description"><?php esc_html_e( 'Set a promo message shown to customers on the site to promote the gift.', 'incentiv' ); ?></p>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="promo_notice_enabled"><?php esc_html_e( 'Enable promo message', 'incentiv' ); ?></label>
                    </th>
                    <td>
                        <label class="mmd-switch">
                            <input type="checkbox" name="promo_notice_enabled" id="promo_notice_enabled" <?php checked(isset($rule_set['promo_notice_enabled']) ? $rule_set['promo_notice_enabled'] : 'no', 'yes'); ?>>
                            <span class="mmd-slider"></span>
                        </label>
                        <p class="description"><?php esc_html_e( 'Show a promo message on the site to let customers know about the available gift', 'incentiv' ); ?></p>
                    </td>
                </tr>
                <tr class="promo-notice-field" style="display: <?php echo (isset($rule_set['promo_notice_enabled']) && $rule_set['promo_notice_enabled'] === 'yes') ? 'table-row' : 'none'; ?>;">
                    <th scope="row">
                        <label for="promo_notice_text"><?php esc_html_e( 'Promo message text', 'incentiv' ); ?></label>
                    </th>
                    <td>
                        <textarea name="promo_notice_text" id="promo_notice_text" rows="2" class="large-text" placeholder="<?php esc_attr_e( 'Buy selected products and get', 'incentiv' ); ?> {gift_type}!"><?php echo esc_textarea(isset($rule_set['promo_notice_text']) ? $rule_set['promo_notice_text'] : ''); ?></textarea>
                        <div class="mmd-mergetag-wrap">
                            <button type="button" class="mmd-mergetag-btn" data-target="promo_notice_text" data-tags="gift_type,rule_name"><span class="msr">sell</span> <?php esc_html_e( 'Merge tags', 'incentiv' ); ?></button>
                        </div>
                        <p class="description"><?php esc_html_e( 'Click "Merge tags" to insert dynamic values. Leave empty for automatic text.', 'incentiv' ); ?></p>
                    </td>
                </tr>
                <tr class="promo-notice-field" style="display: <?php echo (isset($rule_set['promo_notice_enabled']) && $rule_set['promo_notice_enabled'] === 'yes') ? 'table-row' : 'none'; ?>;">
                    <th scope="row">
                        <label for="promo_notice_pages"><?php esc_html_e( 'Pages to show on', 'incentiv' ); ?></label>
                    </th>
                    <td>
                        <select name="promo_notice_pages" id="promo_notice_pages" class="regular-text">
                            <option value="all" <?php selected(isset($rule_set['promo_notice_pages']) ? $rule_set['promo_notice_pages'] : 'all', 'all'); ?>><?php esc_html_e( 'All pages', 'incentiv' ); ?></option>
                            <option value="shop_only" <?php selected(isset($rule_set['promo_notice_pages']) ? $rule_set['promo_notice_pages'] : '', 'shop_only'); ?>><?php esc_html_e( 'Shop only', 'incentiv' ); ?></option>
                            <option value="product_only" <?php selected(isset($rule_set['promo_notice_pages']) ? $rule_set['promo_notice_pages'] : '', 'product_only'); ?>><?php esc_html_e( 'Product pages only', 'incentiv' ); ?></option>
                            <option value="shop_product" <?php selected(isset($rule_set['promo_notice_pages']) ? $rule_set['promo_notice_pages'] : '', 'shop_product'); ?>><?php esc_html_e( 'Shop + products', 'incentiv' ); ?></option>
                            <option value="cart_checkout" <?php selected(isset($rule_set['promo_notice_pages']) ? $rule_set['promo_notice_pages'] : '', 'cart_checkout'); ?>><?php esc_html_e( 'Cart + checkout', 'incentiv' ); ?></option>
                        </select>
                    </td>
                </tr>
                <tr class="promo-notice-field" style="display: <?php echo (isset($rule_set['promo_notice_enabled']) && $rule_set['promo_notice_enabled'] === 'yes') ? 'table-row' : 'none'; ?>;">
                    <th scope="row">
                        <label for="promo_notice_link"><?php esc_html_e( 'Link', 'incentiv' ); ?></label>
                    </th>
                    <td>
                        <input type="url" name="promo_notice_link" id="promo_notice_link" value="<?php echo esc_attr(isset($rule_set['promo_notice_link']) ? $rule_set['promo_notice_link'] : ''); ?>" class="regular-text" placeholder="https://...">
                        <p class="description"><?php esc_html_e( 'An optional link to a relevant page (product category, promotion, etc.)', 'incentiv' ); ?></p>
                    </td>
                </tr>
                <tr class="promo-notice-field" style="display: <?php echo (isset($rule_set['promo_notice_enabled']) && $rule_set['promo_notice_enabled'] === 'yes') ? 'table-row' : 'none'; ?>;">
                    <th scope="row">
                        <label for="promo_notice_link_text"><?php esc_html_e( 'Link text', 'incentiv' ); ?></label>
                    </th>
                    <td>
                        <input type="text" name="promo_notice_link_text" id="promo_notice_link_text" value="<?php echo esc_attr(isset($rule_set['promo_notice_link_text']) ? $rule_set['promo_notice_link_text'] : esc_html__( 'View products', 'incentiv' )); ?>" class="regular-text">
                    </td>
                </tr>
            </table>
        </div>

        <!-- Combined Discount + Gift (Optional) -->
        <div class="mmd-card">
            <h2 class="mmd-card-title"><span class="dashicons dashicons-money-alt"></span> <?php esc_html_e( 'Combined quantity discount (optional)', 'incentiv' ); ?></h2>
            <p class="description"><?php echo wp_kses_post( __( 'Add quantity discounts on top of the gift: the customer gets both a discount and a gift in the same rule. <br> <em>The quantity discounts apply to the products matched by this rule, and the gift is given in addition.</em>', 'incentiv' ) ); ?></p>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="combined_discount_enabled"><?php esc_html_e( 'Enable combined quantity discount', 'incentiv' ); ?></label>
                    </th>
                    <td>
                        <label class="mmd-switch">
                            <input type="checkbox" name="combined_discount_enabled" id="combined_discount_enabled" <?php checked(isset($rule_set['combined_discount_enabled']) ? $rule_set['combined_discount_enabled'] : 'no', 'yes'); ?>>
                            <span class="mmd-slider"></span>
                        </label>
                        <p class="description"><?php esc_html_e( 'When enabled, the customer gets both a quantity discount on the products and the gift', 'incentiv' ); ?></p>
                    </td>
                </tr>
            </table>
            <div id="combined-discount-rules-section" style="display: <?php echo (isset($rule_set['combined_discount_enabled']) && $rule_set['combined_discount_enabled'] === 'yes') ? 'block' : 'none'; ?>;">

                <!-- Discount Mode -->
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="combined_discount_mode"><?php esc_html_e( 'Discount mode', 'incentiv' ); ?></label>
                        </th>
                        <td>
                            <select name="combined_discount_mode" id="combined_discount_mode" class="regular-text">
                                <option value="quantity" <?php selected(isset($rule_set['combined_discount_mode']) ? $rule_set['combined_discount_mode'] : 'quantity', 'quantity'); ?>><?php esc_html_e( 'By quantity (buy X, get a discount on an item)', 'incentiv' ); ?></option>
                                <option value="global" <?php selected(isset($rule_set['combined_discount_mode']) ? $rule_set['combined_discount_mode'] : '', 'global'); ?>><?php esc_html_e( 'Global (a discount on all matching products)', 'incentiv' ); ?></option>
                            </select>
                            <p class="description">
                                <?php echo wp_kses_post( __( '<strong>By quantity:</strong> the discount applies to one item only (the cheapest/most expensive) when the customer buys a minimum quantity.<br> <strong>Global:</strong> the discount applies to all matching products in the cart.', 'incentiv' ) ); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="combined_discount_apply_to"><?php esc_html_e( 'Apply discount to', 'incentiv' ); ?></label>
                        </th>
                        <td>
                            <select name="combined_discount_apply_to" id="combined_discount_apply_to" class="regular-text">
                                <option value="cheapest" <?php selected(isset($rule_set['combined_discount_apply_to']) ? $rule_set['combined_discount_apply_to'] : 'cheapest', 'cheapest'); ?>><?php esc_html_e( 'The cheapest product', 'incentiv' ); ?></option>
                                <option value="most_expensive" <?php selected(isset($rule_set['combined_discount_apply_to']) ? $rule_set['combined_discount_apply_to'] : '', 'most_expensive'); ?>><?php esc_html_e( 'The most expensive product', 'incentiv' ); ?></option>
                                <option value="all" <?php selected(isset($rule_set['combined_discount_apply_to']) ? $rule_set['combined_discount_apply_to'] : '', 'all'); ?>><?php esc_html_e( 'All matching products', 'incentiv' ); ?></option>
                            </select>
                        </td>
                    </tr>
                </table>

                <!-- Quick Presets -->
                <div style="background: #f0f3ff; padding: 12px 15px; border-radius: 6px; margin: 10px 20px 15px;">
                    <strong><?php esc_html_e( 'Quick fill:', 'incentiv' ); ?></strong>
                    <button type="button" class="button button-small combined-preset-btn" data-preset="bogo50">BOGO 50%</button>
                    <button type="button" class="button button-small combined-preset-btn" data-preset="bogo_free"><?php esc_html_e( 'Buy 1 get 1 free', 'incentiv' ); ?></button>
                    <button type="button" class="button button-small combined-preset-btn" data-preset="buy4get1"><?php esc_html_e( 'Buy 4 get 1 free', 'incentiv' ); ?></button>
                    <button type="button" class="button button-small combined-preset-btn" data-preset="tiered"><?php esc_html_e( 'Tiered discounts', 'incentiv' ); ?></button>
                    <button type="button" class="button button-small combined-preset-btn" data-preset="buy3_30"><?php esc_html_e( 'Buy 3 get 30%', 'incentiv' ); ?></button>
                </div>

                <!-- Discount Rules Table -->
                <div id="combined-discount-rules-container" style="padding: 0 20px;">
                    <?php
                    $combined_rules = isset($rule_set['combined_discount_rules']) ? $rule_set['combined_discount_rules'] : array();
                    if (empty($combined_rules)) {
                        $combined_rules = array(array('quantity' => '', 'discount' => '', 'type' => 'percentage'));
                    }
                    foreach ($combined_rules as $idx => $disc_rule):
                    ?>
                        <div class="mmd-discount-rule combined-discount-row" style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px; padding: 10px; background: #f9f9f9; border-radius: 4px; border: 1px solid #e0e0e0;">
                            <div class="mmd-field" style="flex: 1;">
                                <label style="display: block; font-size: 12px; color: #666; margin-bottom: 3px;"><?php echo esc_html_x( 'Minimum quantity', 'tier', 'incentiv' ); ?></label>
                                <input type="number" name="combined_discount_quantity[]" value="<?php echo esc_attr(isset($disc_rule['quantity']) ? $disc_rule['quantity'] : ''); ?>" min="1" placeholder="2" style="width: 100%; padding: 6px;">
                            </div>
                            <div class="mmd-field" style="flex: 1;">
                                <label style="display: block; font-size: 12px; color: #666; margin-bottom: 3px;"><?php esc_html_e( 'Discount', 'incentiv' ); ?></label>
                                <input type="number" name="combined_discount_amount[]" value="<?php echo esc_attr(isset($disc_rule['discount']) ? $disc_rule['discount'] : ''); ?>" min="0" step="0.01" placeholder="30" style="width: 100%; padding: 6px;">
                            </div>
                            <div class="mmd-field" style="flex: 1;">
                                <label style="display: block; font-size: 12px; color: #666; margin-bottom: 3px;"><?php echo esc_html_x( 'Discount type', 'tier', 'incentiv' ); ?></label>
                                <select name="combined_discount_type[]" style="width: 100%; padding: 6px;">
                                    <option value="percentage" <?php selected(isset($disc_rule['type']) ? $disc_rule['type'] : 'percentage', 'percentage'); ?>><?php esc_html_e( 'Percentage (%)', 'incentiv' ); ?></option>
                                    <option value="fixed" <?php selected(isset($disc_rule['type']) ? $disc_rule['type'] : '', 'fixed'); ?>><?php esc_html_e( 'Fixed amount (₪)', 'incentiv' ); ?></option>
                                </select>
                            </div>
                            <div style="padding-top: 18px;">
                                <button type="button" class="button remove-combined-discount-row" title="<?php esc_attr_e( 'Remove', 'incentiv' ); ?>"><span class="dashicons dashicons-trash" style="vertical-align: middle;"></span></button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div style="padding: 10px 20px 20px;">
                    <button type="button" class="button button-secondary" id="add-combined-discount-row">
                        <span class="dashicons dashicons-plus-alt" style="font-size: 16px; width: 16px; height: 16px; vertical-align: middle;"></span> <?php esc_html_e( 'Add discount row', 'incentiv' ); ?>
                    </button>
                    <p class="description" id="combined-discount-help">
                        <?php echo wp_kses_post( __( 'Set different discounts for different quantities. The highest matching discount applies.<br> <em>Example: buy 2 get 10% off, buy 3 get 20% off, buy 5 get 1 free (100%)</em>', 'incentiv' ) ); ?>
                    </p>
                </div>

                <!-- Combined Discount Notice -->
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="combined_discount_notice_enabled"><?php esc_html_e( 'Show discount message', 'incentiv' ); ?></label>
                        </th>
                        <td>
                            <label class="mmd-switch">
                                <input type="checkbox" name="combined_discount_notice_enabled" id="combined_discount_notice_enabled" <?php checked(isset($rule_set['combined_discount_notice_enabled']) ? $rule_set['combined_discount_notice_enabled'] : 'yes', 'yes'); ?>>
                                <span class="mmd-slider"></span>
                            </label>
                            <p class="description"><?php esc_html_e( 'Show a message about the quantity discount on the product page and in the cart', 'incentiv' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="combined_discount_notice_title"><?php echo esc_html_x( 'Message title', 'gift', 'incentiv' ); ?></label>
                        </th>
                        <td>
                            <input type="text" name="combined_discount_notice_title" id="combined_discount_notice_title" value="<?php echo esc_attr(isset($rule_set['combined_discount_notice_title']) ? $rule_set['combined_discount_notice_title'] : esc_html__( '💰 Buy in quantity and save!', 'incentiv' )); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="combined_discount_notice_color"><?php echo esc_html_x( 'Message colour', 'gift', 'incentiv' ); ?></label>
                        </th>
                        <td>
                            <input type="text" name="combined_discount_notice_color" id="combined_discount_notice_color" value="<?php echo esc_attr(isset($rule_set['combined_discount_notice_color']) ? $rule_set['combined_discount_notice_color'] : '#4CAF50'); ?>" class="mmd-color-picker">
                        </td>
                    </tr>
                </table>
            </div>
        </div>

    </div><!-- /.mmd-admin-container -->

        <!-- Stepper submit bar -->
        <p class="submit mmd-rule-submit-bar">
            <button type="button" class="mmd-btn mmd-btn-outline" id="mmd-gift-step-prev"><span class="msr mmd-dir-icon">arrow_forward</span> <?php esc_html_e( 'Previous', 'incentiv' ); ?></button>
            <span id="mmd-gift-step-indicator" style="font-size:12.5px;font-weight:700;color:var(--mmd-text-muted);"></span>
            <button type="button" class="mmd-btn mmd-btn-primary" id="mmd-gift-step-next"><?php esc_html_e( 'Next', 'incentiv' ); ?> <span class="msr mmd-dir-icon">arrow_back</span></button>
            <span style="flex:1;"></span>
            <span id="mmd-gift-draft-status" class="mmd-draft-status" aria-live="polite"></span>
            <button type="button" id="mmd-gift-save-draft" class="mmd-btn mmd-btn-outline" style="padding:10px 18px;"><span class="msr">save</span> <?php esc_html_e( 'Save draft', 'incentiv' ); ?></button>
            <button type="button" class="mmd-btn mmd-btn-green" id="save-gift-rule" style="padding:10px 20px;">
                <span class="msr">check</span><?php if ($is_new): ?> <?php esc_html_e( 'Publish gift', 'incentiv' ); ?><?php else: ?> <?php esc_html_e( 'Save gift', 'incentiv' ); ?><?php endif; ?>
            </button>
            <a href="<?php echo esc_url( admin_url('admin.php?page=mmd-gift-rules') ); ?>" class="mmd-btn mmd-btn-ghost"><?php esc_html_e( 'Cancel', 'incentiv' ); ?></a>
        </p>

    </div><!-- /.mmd-editor-main -->

    <!-- Sticky live preview -->
    <aside class="mmd-editor-preview" id="mmd-gift-live-preview">
        <div class="mmd-preview-label"><span class="msr">visibility</span><?php echo wp_kses_post( __( 'Preview &middot; Live', 'incentiv' ) ); ?></div>
        <div class="mmd-gift-widget-preview" style="background:linear-gradient(135deg,<?php echo esc_attr($mmd_gw_bg1); ?> 0%,<?php echo esc_attr($mmd_gw_bg2); ?> 100%);">
            <div class="mmd-gw-title"><?php echo esc_html($mmd_gw_title); ?></div>
            <div class="mmd-gw-sub mmd-glp-rule-name"><?php echo esc_html($rule_set['name'] ? $rule_set['name'] : esc_html__( 'New gift rule', 'incentiv' )); ?></div>
            <div class="mmd-gw-list">
                <div class="mmd-gw-item">
                    <span class="mmd-gw-thumb"><span class="msr">image</span></span>
                    <div class="mmd-gw-name"><?php esc_html_e( 'Ceramic mug', 'incentiv' ); ?></div>
                    <button type="button" class="mmd-gw-btn" style="background:<?php echo esc_attr($mmd_gw_bg1); ?>;"><?php esc_html_e( 'Choose', 'incentiv' ); ?></button>
                </div>
                <div class="mmd-gw-item mmd-gw-selected">
                    <span class="mmd-gw-thumb"><span class="msr">check_circle</span></span>
                    <div class="mmd-gw-name"><?php esc_html_e( 'Thermal jug 500 ml', 'incentiv' ); ?></div>
                    <button type="button" class="mmd-gw-btn"><?php esc_html_e( 'Selected ✓', 'incentiv' ); ?></button>
                </div>
            </div>
        </div>
        <a href="<?php echo esc_url(admin_url('admin.php?page=mmd-gift-rules&action=styling')); ?>" class="mmd-btn mmd-btn-soft-purple" style="width:100%;">
            <span class="msr">palette</span><?php esc_html_e( 'Edit the widget design', 'incentiv' ); ?>
        </a>
    </aside>
    </div><!-- /.mmd-editor-grid -->
</div>

<script>
jQuery(document).ready(function($) {
    var ruleSet = <?php echo json_encode($rule_set); ?>;

    console.log('MMD Gift Rules: Loaded rule set:', ruleSet);

    // ==========================================
    // GIFT RULE WIZARD
    // ==========================================

    // Real-field gift wizard: collects actual values and writes them into the form.
    var giftWizardCurrentStep = 1;
    var giftWizardTotalSteps = 4;
    var giftWizardState = {
        name: '',
        gift_type: 'free',
        gift_discount: 20,
        gift_mode: 'product_page',
        min_cart_total: '',
        min_quantity: ''
    };

    var giftTypeCards = [
        { id: 'free',           icon: 'redeem',   title: <?php echo wp_json_encode( __( 'Free gift', 'incentiv' ) ); ?>,       desc: <?php echo wp_json_encode( __( 'The selected item is added to the cart at no cost (100% off).', 'incentiv' ) ); ?> },
        { id: 'discount',       icon: 'percent',  title: <?php echo wp_json_encode( __( 'Percentage discount', 'incentiv' ) ); ?>,    desc: <?php echo wp_json_encode( __( 'The selected item gets a percentage discount you set.', 'incentiv' ) ); ?> },
        { id: 'fixed_discount', icon: 'payments', title: <?php echo wp_json_encode( __( 'Price / fixed discount', 'incentiv' ) ); ?>, desc: <?php echo wp_json_encode( __( 'A fixed shekel amount off the selected item.', 'incentiv' ) ); ?> }
    ];
    var giftModeCards = [
        { id: 'product_page', icon: 'touch_app',    title: <?php echo wp_json_encode( __( 'Customer choice', 'incentiv' ) ); ?>,  desc: <?php echo wp_json_encode( __( 'The customer chooses the gift from a list on the product page.', 'incentiv' ) ); ?> },
        { id: 'cart_based',   icon: 'auto_awesome', title: <?php echo wp_json_encode( __( 'Automatic', 'incentiv' ) ); ?>,     desc: <?php echo wp_json_encode( __( 'The gift is added to the cart automatically when the condition is met.', 'incentiv' ) ); ?> }
    ];

    function giftWizStepHtml(step) {
        var st = giftWizardState, h = '';
        if (step === 1) {
            h += ( '<h3>' + <?php echo wp_json_encode( __( 'What does the gift include?', 'incentiv' ) ); ?> + '<\/h3><p>' + <?php echo wp_json_encode( __( 'Name the rule and choose the gift type.', 'incentiv' ) ); ?> + '<\/p>' );
            h += ( '<label class="mmd-form-label">' + <?php echo wp_json_encode( __( 'Rule name', 'incentiv' ) ); ?> + '<\/label>' ) +
                '<input type="text" id="giftwz-name" value="' + $('<i>').text(st.name).html() + ( '" placeholder="' + <?php echo wp_json_encode( __( 'e.g. A gift over ₪200', 'incentiv' ) ); ?> + '" style="width:100%;padding:10px 12px;border:1px solid var(--mmd-border-input);border-radius:9px;font-size:14px;margin-bottom:16px;">' );
            h += ( '<label class="mmd-form-label">' + <?php echo wp_json_encode( __( 'Gift type', 'incentiv' ) ); ?> + '<\/label><div class="mmd-select-cards mmd-select-cards-3">' );
            giftTypeCards.forEach(function(c){
                var on = st.gift_type===c.id ? ' mmd-active mmd-select-card-purple' : '';
                h += '<div class="mmd-select-card'+on+'" data-gtype="'+c.id+'"><span class="msr" style="font-size:24px;">'+c.icon+'</span><span class="mmd-select-card-label" style="font-weight:700;">'+c.title+'</span><span class="mmd-select-card-hint">'+c.desc+'</span></div>';
            });
            h += '</div>';
            h += '<div id="giftwz-disc" style="margin-top:14px;'+(st.gift_type==='free'?'display:none;':'')+( '"><label class="mmd-form-label">' + <?php echo wp_json_encode( __( 'Discount size', 'incentiv' ) ); ?> + ' ' )+(st.gift_type==='fixed_discount'?'(₪)':'(%)')+'</label><input type="number" id="giftwz-discval" min="0" value="'+st.gift_discount+'" style="width:160px;padding:9px 11px;border:1px solid var(--mmd-border-input);border-radius:8px;"></div>';
        } else if (step === 2) {
            h += ( '<h3>' + <?php echo wp_json_encode( __( 'How is the gift given?', 'incentiv' ) ); ?> + '<\/h3><p>' + <?php echo wp_json_encode( __( 'Choose how the customer gets the gift.', 'incentiv' ) ); ?> + '<\/p>' );
            h += '<div class="mmd-select-cards mmd-select-cards-2">';
            giftModeCards.forEach(function(c){
                var on = st.gift_mode===c.id ? ' mmd-active mmd-select-card-purple' : '';
                h += '<div class="mmd-select-card'+on+'" data-gmode="'+c.id+'"><span class="msr" style="font-size:26px;">'+c.icon+'</span><span class="mmd-select-card-label" style="font-weight:700;">'+c.title+'</span><span class="mmd-select-card-hint">'+c.desc+'</span></div>';
            });
            h += '</div>';
        } else if (step === 3) {
            h += ( '<h3>' + <?php echo wp_json_encode( __( 'When is the gift triggered?', 'incentiv' ) ); ?> + '<\/h3><p>' + <?php echo wp_json_encode( __( 'Set threshold conditions (leave empty for no condition).', 'incentiv' ) ); ?> + '<\/p>' );
            h += '<div class="mmd-form-grid-2">';
            h += ( '<div><label class="mmd-form-label">' + <?php echo wp_json_encode( _x( 'Minimum cart total (₪)', 'gift', 'incentiv' ) ); ?> + '<\/label><input type="number" id="giftwz-mincart" min="0" value="' )+st.min_cart_total+( '" placeholder="' + <?php echo wp_json_encode( __( 'None', 'incentiv' ) ); ?> + '" style="width:100%;padding:9px 11px;border:1px solid var(--mmd-border-input);border-radius:8px;"><\/div>' );
            h += ( '<div><label class="mmd-form-label">' + <?php echo wp_json_encode( __( 'Minimum number of items', 'incentiv' ) ); ?> + '<\/label><input type="number" id="giftwz-minqty" min="0" value="' )+st.min_quantity+( '" placeholder="' + <?php echo wp_json_encode( __( 'None', 'incentiv' ) ); ?> + '" style="width:100%;padding:9px 11px;border:1px solid var(--mmd-border-input);border-radius:8px;"><\/div>' );
            h += '</div>';
            h += ( '<div class="mmd-wizard-tip">' + <?php echo wp_json_encode( __( 'You choose the gift products themselves in the form after the wizard ("Gift setup" step).', 'incentiv' ) ); ?> + '<\/div>' );
        } else if (step === 4) {
            var gt = giftTypeCards.filter(function(c){return c.id===st.gift_type;})[0];
            var gm = giftModeCards.filter(function(c){return c.id===st.gift_mode;})[0];
            h += ( '<h3>' + <?php echo wp_json_encode( __( 'Summary', 'incentiv' ) ); ?> + '<\/h3><p>' + <?php echo wp_json_encode( __( 'Check and click "Create gift" to fill in the form.', 'incentiv' ) ); ?> + '<\/p>' );
            h += '<div style="border:1px solid var(--mmd-border);border-radius:12px;overflow:hidden;">';
            function row(k,v){ return '<div style="display:flex;justify-content:space-between;padding:12px 15px;border-bottom:1px solid var(--mmd-border-row);font-size:13.5px;"><span style="color:var(--mmd-text-muted);">'+k+'</span><strong>'+v+'</strong></div>'; }
            h += row(<?php echo wp_json_encode( __( 'Name', 'incentiv' ) ); ?>, $('<i>').text(st.name||<?php echo wp_json_encode( __( '(no name)', 'incentiv' ) ); ?>).html());
            h += row(<?php echo wp_json_encode( _x( 'Gift type', 'summary', 'incentiv' ) ); ?>, gt.title + (st.gift_type!=='free' ? ' · '+st.gift_discount+(st.gift_type==='fixed_discount'?'₪':'%') : ''));
            h += row(<?php echo wp_json_encode( __( 'How it\'s given', 'incentiv' ) ); ?>, gm.title);
            h += row(<?php echo wp_json_encode( __( 'Condition', 'incentiv' ) ); ?>, (st.min_cart_total?(<?php echo wp_json_encode( __( 'Cart ₪', 'incentiv' ) ); ?>+st.min_cart_total+( ' ' + <?php echo wp_json_encode( __( 'and up', 'incentiv' ) ); ?> )):'') + (st.min_quantity?(st.min_cart_total?' · ':'')+( <?php echo wp_json_encode( __( 'Quantity', 'incentiv' ) ); ?> + ' ' )+st.min_quantity+( ' ' + <?php echo wp_json_encode( __( 'and up', 'incentiv' ) ); ?> ):'') || <?php echo wp_json_encode( __( 'None', 'incentiv' ) ); ?>);
            h += '</div>';
            h += ( '<div class="mmd-wizard-tip" style="background:var(--mmd-success-light);border-color:var(--mmd-success);color:#155724;margin-top:14px;">' + <?php echo wp_json_encode( __( 'All set! Next, choose the gift products in the form.', 'incentiv' ) ); ?> + '<\/div>' );
        }
        return '<div style="animation:mmdRise .25s ease both;">'+h+'</div>';
    }

    function giftWizReadStep() {
        if (giftWizardCurrentStep===1) {
            giftWizardState.name = $('#giftwz-name').val()||'';
            if ($('#giftwz-discval').length) giftWizardState.gift_discount = $('#giftwz-discval').val();
        } else if (giftWizardCurrentStep===3) {
            giftWizardState.min_cart_total = $('#giftwz-mincart').val()||'';
            giftWizardState.min_quantity = $('#giftwz-minqty').val()||'';
        }
    }

    function updateGiftWizardStep() {
        $('#mmd-gift-wizard-title').text(<?php echo wp_json_encode( __( 'Gift rule wizard', 'incentiv' ) ); ?>);
        $('#mmd-gift-wizard-body').html(giftWizStepHtml(giftWizardCurrentStep));
        $('.mmd-gift-wizard-step').each(function(){
            var n = parseInt($(this).data('step'),10);
            $(this).toggleClass('active', n===giftWizardCurrentStep).toggleClass('completed', n<giftWizardCurrentStep);
        });
        $('#mmd-gift-wizard-prev').toggle(giftWizardCurrentStep>1);
        $('#mmd-gift-wizard-next').toggle(giftWizardCurrentStep<giftWizardTotalSteps);
        $('#mmd-gift-wizard-finish').toggle(giftWizardCurrentStep===giftWizardTotalSteps);
    }

    if ($('#mmd-gift-wizard-overlay').length) {
        // steps bar in this wizard has 6 dots in markup; hide extras beyond 4
        $('.mmd-gift-wizard-step').each(function(){ if (parseInt($(this).data('step'),10) > 4) $(this).hide(); });
    }

    function showGiftWizard() {
        giftWizardCurrentStep = 1;
        if (!giftWizardState.name) giftWizardState.name = $('#rule_name').val()||'';
        $('#mmd-gift-wizard-overlay').fadeIn(200);
        updateGiftWizardStep();
    }
    function hideGiftWizard() { $('#mmd-gift-wizard-overlay').fadeOut(200); }

    function giftWizApplyToForm() {
        var st = giftWizardState;
        if (st.name) $('#rule_name').val(st.name).trigger('input');
        $('input[name="gift_type"][value="'+st.gift_type+'"]').prop('checked',true).trigger('change');
        if (st.gift_type!=='free') $('#gift_discount').val(st.gift_discount);
        $('input[name="gift_mode"][value="'+st.gift_mode+'"]').prop('checked',true).trigger('change');
        if (st.min_cart_total!=='') $('#min_cart_total').val(st.min_cart_total);
        if (st.min_quantity!=='') $('#min_quantity_trigger').val(st.min_quantity);
    }

    // Step interactions
    $(document).on('click', '#mmd-gift-wizard-body [data-gtype]', function(){
        giftWizardState.gift_type = $(this).data('gtype');
        $('#mmd-gift-wizard-body [data-gtype]').removeClass('mmd-active mmd-select-card-purple');
        $(this).addClass('mmd-active mmd-select-card-purple');
        $('#giftwz-disc').toggle(giftWizardState.gift_type!=='free');
    });
    $(document).on('click', '#mmd-gift-wizard-body [data-gmode]', function(){
        giftWizardState.gift_mode = $(this).data('gmode');
        $('#mmd-gift-wizard-body [data-gmode]').removeClass('mmd-active mmd-select-card-purple');
        $(this).addClass('mmd-active mmd-select-card-purple');
    });

    $(document).on('click', '#mmd-gift-wizard-next', function() {
        giftWizReadStep();
        if (giftWizardCurrentStep < giftWizardTotalSteps) { giftWizardCurrentStep++; updateGiftWizardStep(); }
    });
    $(document).on('click', '#mmd-gift-wizard-prev', function() {
        giftWizReadStep();
        if (giftWizardCurrentStep > 1) { giftWizardCurrentStep--; updateGiftWizardStep(); }
    });
    $(document).on('click', '#mmd-gift-wizard-finish', function() {
        giftWizReadStep();
        giftWizApplyToForm();
        hideGiftWizard();
        if (typeof window.mmdShowGiftStep === 'function') { window.mmdShowGiftStep(2); }
    });
    $(document).on('click', '#mmd-gift-wizard-overlay .mmd-wizard-close, #mmd-gift-wizard-overlay .mmd-wizard-backdrop', function() { hideGiftWizard(); });
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape' && $('#mmd-gift-wizard-overlay').is(':visible')) { hideGiftWizard(); }
    });
    $(document).on('click', '#mmd-launch-gift-wizard, .mmd-launch-gift-wizard', function(e){ e.preventDefault(); showGiftWizard(); });

    // Auto-open for brand-new rules (unless skipped)
    (function(){
        var params = new URLSearchParams(window.location.search);
        if (params.get('action')==='new' && params.get('skip_wizard')!=='1' && $('#mmd-gift-wizard-overlay').length) {
            showGiftWizard();
        }
    })();

    // ==========================================
    // END GIFT RULE WIZARD
    // ==========================================

    // Initialize UI based on saved values
    function initializeUI() {
        console.log('MMD Gift Rules: Initializing UI');

        // Initialize selection method section
        var selectionMethod = ruleSet.selection_method || 'all';
        console.log('MMD Gift Rules: selection_method =', selectionMethod);
        $('#category_section, #product_ids_section, #attribute_section').hide();
        if (selectionMethod === 'category') {
            $('#category_section').show();
        } else if (selectionMethod === 'product_ids') {
            $('#product_ids_section').show();
        } else if (selectionMethod === 'attribute') {
            $('#attribute_section').show();
        }

        // Initialize gift source section
        var giftSource = ruleSet.gift_source || 'specific';
        console.log('MMD Gift Rules: gift_source =', giftSource);
        if (giftSource === 'specific') {
            $('#gift_products_row').show();
            $('#gift_category_section').hide();
            $('#gift_exclusions_section').hide();
        } else {
            $('#gift_products_row').hide();
            $('#gift_category_section').show();
            $('#gift_exclusions_section').show();
        }

        // Initialize gift mode section
        var giftMode = ruleSet.gift_mode || 'product_page';
        console.log('MMD Gift Rules: gift_mode =', giftMode);
        if (giftMode === 'cart_based') {
            $('#cart_gift_notice_row').show();
            $('#cart_gift_display_mode_row').show();
        } else {
            $('#cart_gift_notice_row').hide();
            $('#cart_gift_display_mode_row').hide();
        }

        // Initialize gift attribute section
        var giftAttributeSlug = ruleSet.gift_attribute_slug || '';
        console.log('MMD Gift Rules: gift_attribute_slug =', giftAttributeSlug);
        if (giftAttributeSlug) {
            $('#gift_attribute_values_row').show();
        } else {
            $('#gift_attribute_values_row').hide();
        }
    }

    // Run initialization
    initializeUI();

    // Selection method toggle (for product eligibility)
    $('#selection_method').on('change', function() {
        var method = $(this).val();
        // Only hide/show product selection sections, not gift sections
        $('#category_section, #product_ids_section, #attribute_section').hide();
        if (method === 'category') {
            $('#category_section').show();
        } else if (method === 'product_ids') {
            $('#product_ids_section').show();
        } else if (method === 'attribute') {
            $('#attribute_section').show();
        }
    });

    // Gift source toggle (for gift products selection)
    $('#gift_source').on('change', function() {
        var source = $(this).val();
        if (source === 'specific') {
            $('#gift_products_row').show();
            $('#gift_category_section').hide();
            $('#gift_exclusions_section').hide();
            $('#gift_products_limit_section').hide();
            // Update default variations UI
            var productIds = $('#gift_products').val() || [];
            if (productIds.length > 0) {
                loadDefaultVariationsUI(productIds);
            } else {
                $('#default-variations-card').hide();
            }
        } else {
            $('#gift_products_row').hide();
            $('#gift_category_section').show();
            $('#gift_exclusions_section').show();
            $('#gift_products_limit_section').show();
            $('#default-variations-card').hide();
        }
    });

    // Load shipping methods dynamically
    function loadShippingMethods() {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mmd_get_shipping_methods',
                nonce: mmdGiftRulesAjax.nonce
            },
            success: function(response) {
                if (response.success && response.data) {
                    var $select = $('#shipping_method_condition');
                    var currentValue = ruleSet.shipping_method_condition || '';
                    $select.empty();
                    $select.append(( '<option value="">' + <?php echo wp_json_encode( __( 'No limit - all shipping methods', 'incentiv' ) ); ?> + '<\/option>' ));
                    response.data.forEach(function(method) {
                        var selected = currentValue === method.id ? 'selected' : '';
                        $select.append('<option value="' + method.id + '" ' + selected + '>' + method.title + '</option>');
                    });
                }
            }
        });
    }
    loadShippingMethods();

    // Gift mode toggle (product page vs cart-based)
    $('input[name="gift_mode"]').on('change', function() {
        var mode = $(this).val();
        $('.mmd-gift-mode-option').removeClass('active');
        $(this).closest('.mmd-gift-mode-option').addClass('active');

        if (mode === 'cart_based') {
            $('#cart_gift_notice_row').show();
            $('#cart_gift_display_mode_row').show();
        } else {
            $('#cart_gift_notice_row').hide();
            $('#cart_gift_display_mode_row').hide();
        }
    });

    // Gift mode visual feedback on click
    $('.mmd-gift-mode-option').on('click', function() {
        $('.mmd-gift-mode-option').removeClass('active');
        $(this).addClass('active');
        $(this).find('input[type="radio"]').prop('checked', true).trigger('change');
    });

    // Gift type change
    $('input[name="gift_type"]').on('change', function() {
        var type = $(this).val();
        $('.mmd-gift-type-option').removeClass('active');
        $(this).closest('.mmd-gift-type-option').addClass('active');

        if (type === 'free') {
            $('#discount_amount_row').hide();
        } else {
            $('#discount_amount_row').show();
            if (type === 'discount') {
                $('#discount_unit').text('%');
                $('#discount_description').text(<?php echo wp_json_encode( __( 'Enter a discount percentage (0-100)', 'incentiv' ) ); ?>);
            } else {
                $('#discount_unit').text('₪');
                $('#discount_description').text(<?php echo wp_json_encode( __( 'Enter a fixed discount amount', 'incentiv' ) ); ?>);
            }
        }
    });

    // Gift selection type change - show/hide quantity controls
    $('#gift_selection_type').on('change', function() {
        var type = $(this).val();
        if (type === 'multiple') {
            $('#quantity_control_row').show();
            // Show max quantity row if allow_quantity is checked
            if ($('#allow_quantity').is(':checked')) {
                $('#max_quantity_row').show();
                $('#total_quantity_limit_row').show();
            }
        } else {
            $('#quantity_control_row').hide();
            $('#max_quantity_row').hide();
            $('#total_quantity_limit_row').hide();
        }
    });

    // Allow quantity toggle
    $('#allow_quantity').on('change', function() {
        if ($(this).is(':checked')) {
            $('#max_quantity_row').show();
            $('#total_quantity_limit_row').show();
        } else {
            $('#max_quantity_row').hide();
            $('#total_quantity_limit_row').hide();
        }
    });

    // Promo notice toggle
    $('#promo_notice_enabled').on('change', function() {
        if ($(this).is(':checked')) {
            $('.promo-notice-field').show();
        } else {
            $('.promo-notice-field').hide();
        }
    });

    // Combined discount toggle
    $('#combined_discount_enabled').on('change', function() {
        if ($(this).is(':checked')) {
            $('#combined-discount-rules-section').show();
        } else {
            $('#combined-discount-rules-section').hide();
        }
    });

    // Combined discount mode change
    $('#combined_discount_mode').on('change', function() {
        var mode = $(this).val();
        if (mode === 'global') {
            $('#combined_discount_apply_to').closest('tr').hide();
            $('#combined-discount-help').html(<?php echo wp_json_encode( __( 'In global mode, the discount applies to <strong>all</strong> matching products in the cart. You can add rows with a quantity to increase the discount.', 'incentiv' ) ); ?>);
        } else {
            $('#combined_discount_apply_to').closest('tr').show();
            $('#combined-discount-help').html(<?php echo wp_json_encode( __( 'Set different discounts for different quantities. The highest matching discount applies.<br><em>Example: buy 2 get 10% off, buy 3 get 20% off, buy 5 get 1 free (100%)</em>', 'incentiv' ) ); ?>);
        }
    });
    // Trigger on load
    $('#combined_discount_mode').trigger('change');

    // Add combined discount row
    var combinedRowIndex = <?php echo count($combined_rules); ?>;
    $('#add-combined-discount-row').on('click', function() {
        combinedRowIndex++;
        var html = '<div class="mmd-discount-rule combined-discount-row" style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px; padding: 10px; background: #f9f9f9; border-radius: 4px; border: 1px solid #e0e0e0;">' +
            ( '<div class="mmd-field" style="flex: 1;"><label style="display: block; font-size: 12px; color: #666; margin-bottom: 3px;">' + <?php echo wp_json_encode( _x( 'Minimum quantity', 'tier', 'incentiv' ) ); ?> + '<\/label>' ) +
            '<input type="number" name="combined_discount_quantity[]" value="" min="1" placeholder="2" style="width: 100%; padding: 6px;"></div>' +
            ( '<div class="mmd-field" style="flex: 1;"><label style="display: block; font-size: 12px; color: #666; margin-bottom: 3px;">' + <?php echo wp_json_encode( __( 'Discount', 'incentiv' ) ); ?> + '<\/label>' ) +
            '<input type="number" name="combined_discount_amount[]" value="" min="0" step="0.01" placeholder="30" style="width: 100%; padding: 6px;"></div>' +
            ( '<div class="mmd-field" style="flex: 1;"><label style="display: block; font-size: 12px; color: #666; margin-bottom: 3px;">' + <?php echo wp_json_encode( _x( 'Discount type', 'tier', 'incentiv' ) ); ?> + '<\/label>' ) +
            '<select name="combined_discount_type[]" style="width: 100%; padding: 6px;">' +
            ( '<option value="percentage">' + <?php echo wp_json_encode( __( 'Percentage (%)', 'incentiv' ) ); ?> + '<\/option>' ) +
            ( '<option value="fixed">' + <?php echo wp_json_encode( __( 'Fixed amount (₪)', 'incentiv' ) ); ?> + '<\/option><\/select><\/div>' ) +
            ( '<div style="padding-top: 18px;"><button type="button" class="button remove-combined-discount-row" title="' + <?php echo wp_json_encode( __( 'Remove', 'incentiv' ) ); ?> + '"><span class="dashicons dashicons-trash" style="vertical-align: middle;"><\/span><\/button><\/div>' ) +
            '</div>';
        $('#combined-discount-rules-container').append(html);
    });

    // Remove combined discount row
    $(document).on('click', '.remove-combined-discount-row', function() {
        $(this).closest('.combined-discount-row').remove();
    });

    // Combined discount presets
    $(document).on('click', '.combined-preset-btn', function() {
        var preset = $(this).data('preset');
        var rules = [];

        switch (preset) {
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

        // Clear existing rows and add preset rows
        $('#combined-discount-rules-container').empty();
        rules.forEach(function(rule, index) {
            var html = '<div class="mmd-discount-rule combined-discount-row" style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px; padding: 10px; background: #f9f9f9; border-radius: 4px; border: 1px solid #e0e0e0;">' +
                ( '<div class="mmd-field" style="flex: 1;"><label style="display: block; font-size: 12px; color: #666; margin-bottom: 3px;">' + <?php echo wp_json_encode( _x( 'Minimum quantity', 'tier', 'incentiv' ) ); ?> + '<\/label>' ) +
                '<input type="number" name="combined_discount_quantity[]" value="' + rule.quantity + '" min="1" style="width: 100%; padding: 6px;"></div>' +
                ( '<div class="mmd-field" style="flex: 1;"><label style="display: block; font-size: 12px; color: #666; margin-bottom: 3px;">' + <?php echo wp_json_encode( __( 'Discount', 'incentiv' ) ); ?> + '<\/label>' ) +
                '<input type="number" name="combined_discount_amount[]" value="' + rule.discount + '" min="0" step="0.01" style="width: 100%; padding: 6px;"></div>' +
                ( '<div class="mmd-field" style="flex: 1;"><label style="display: block; font-size: 12px; color: #666; margin-bottom: 3px;">' + <?php echo wp_json_encode( _x( 'Discount type', 'tier', 'incentiv' ) ); ?> + '<\/label>' ) +
                '<select name="combined_discount_type[]" style="width: 100%; padding: 6px;">' +
                '<option value="percentage"' + (rule.type === 'percentage' ? ' selected' : '') + ( '>' + <?php echo wp_json_encode( __( 'Percentage (%)', 'incentiv' ) ); ?> + '<\/option>' ) +
                '<option value="fixed"' + (rule.type === 'fixed' ? ' selected' : '') + ( '>' + <?php echo wp_json_encode( __( 'Fixed amount (₪)', 'incentiv' ) ); ?> + '<\/option><\/select><\/div>' ) +
                ( '<div style="padding-top: 18px;"><button type="button" class="button remove-combined-discount-row" title="' + <?php echo wp_json_encode( __( 'Remove', 'incentiv' ) ); ?> + '"><span class="dashicons dashicons-trash" style="vertical-align: middle;"><\/span><\/button><\/div>' ) +
                '</div>';
            $('#combined-discount-rules-container').append(html);
        });
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
                    nonce: mmdGiftRulesAjax.nonce,
                    include_variations: '1',
                    q: params.term
                };
            },
            processResults: function(data) {
                return {
                    results: data.data.map(function(product) {
                        return {
                            id: product.id,
                            text: product.text || (product.name + ' (#' + product.id + ')')
                        };
                    })
                };
            }
        },
        minimumInputLength: 2,
        placeholder: <?php echo wp_json_encode( __( 'Search products or variations...', 'incentiv' ) ); ?>
    });

    // Handle gift products change - load default variations UI
    $('#gift_products').on('change', function() {
        var productIds = $(this).val() || [];
        if (productIds.length > 0) {
            loadDefaultVariationsUI(productIds);
        } else {
            $('#default-variations-card').hide();
            $('#default-variations-container').html('');
        }
    });

    // Load default variations UI for selected products
    function loadDefaultVariationsUI(productIds) {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mmd_get_gift_products_variations',
                nonce: mmdGiftRulesAjax.nonce,
                product_ids: productIds
            },
            success: function(response) {
                if (response.success && response.data.has_variable_products) {
                    var html = '<table class="form-table">';

                    response.data.products.forEach(function(product) {
                        if (product.is_variable && product.variations.length > 0) {
                            var defaultVariation = ruleSet.gift_default_variations && ruleSet.gift_default_variations[product.id]
                                ? ruleSet.gift_default_variations[product.id]
                                : '';

                            html += '<tr>';
                            html += '<th scope="row"><label>' + product.name + '</label></th>';
                            html += '<td>';
                            html += '<select name="gift_default_variations[' + product.id + ']" class="regular-text">';
                            html += ( '<option value="">' + <?php echo wp_json_encode( __( 'No default (the customer chooses)', 'incentiv' ) ); ?> + '<\/option>' );

                            product.variations.forEach(function(variation) {
                                var selected = defaultVariation == variation.id ? 'selected' : '';
                                html += '<option value="' + variation.id + '" ' + selected + '>' + variation.name + '</option>';
                            });

                            html += '</select>';
                            html += ( '<p class="description">' + <?php echo wp_json_encode( __( 'This variation will be preselected', 'incentiv' ) ); ?> + '<\/p>' );
                            html += '</td>';
                            html += '</tr>';
                        }
                    });

                    html += '</table>';
                    $('#default-variations-container').html(html);
                    $('#default-variations-card').show();
                } else {
                    $('#default-variations-card').hide();
                    $('#default-variations-container').html('');
                }
            }
        });
    }

    // Load default variations UI on page load if products are selected
    if (ruleSet.gift_products && ruleSet.gift_products.length > 0) {
        loadDefaultVariationsUI(ruleSet.gift_products);
    }

    // Attribute handling
    $('#attribute_slug').on('change', function() {
        var slug = $(this).val();
        if (slug) {
            $('#attribute_values_row, #attribute_excluded_values_row').show();
            loadAttributeTerms(slug);
        } else {
            $('#attribute_values_row, #attribute_excluded_values_row').hide();
        }
    });

    function loadAttributeTerms(slug) {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mmd_get_attribute_terms',
                nonce: mmdGiftRulesAjax.nonce,
                attribute_slug: slug
            },
            success: function(response) {
                if (response.success) {
                    var terms = response.data;
                    var valuesHtml = '';
                    var excludedHtml = '';

                    terms.forEach(function(term) {
                        var checked = ruleSet.attribute_values && ruleSet.attribute_values.indexOf(term.term_id) !== -1 ? 'checked' : '';
                        var excludedChecked = ruleSet.attribute_excluded_values && ruleSet.attribute_excluded_values.indexOf(term.term_id) !== -1 ? 'checked' : '';

                        valuesHtml += '<label><input type="checkbox" name="attribute_values[]" value="' + term.term_id + '" ' + checked + '> ' + term.name + '</label>';
                        excludedHtml += '<label><input type="checkbox" name="attribute_excluded_values[]" value="' + term.term_id + '" ' + excludedChecked + '> ' + term.name + '</label>';
                    });

                    $('#attribute_values_list').html(valuesHtml);
                    $('#attribute_excluded_values_list').html(excludedHtml);
                }
            }
        });
    }

    // Load attribute terms on page load if attribute is selected
    if (ruleSet.attribute_slug) {
        loadAttributeTerms(ruleSet.attribute_slug);
    }

    // Gift attribute handling
    $('#gift_attribute_slug').on('change', function() {
        var slug = $(this).val();
        if (slug) {
            $('#gift_attribute_values_row').show();
            $('#gift_additional_attributes_row').show();
            loadGiftAttributeTerms(slug);
        } else {
            $('#gift_attribute_values_row').hide();
            $('#gift_additional_attributes_row').hide();
        }
    });

    // Additional attribute filters
    $(document).on('click', '#add-gift-additional-attr', function() {
        var attrOptions = ( '<option value="">' + <?php echo wp_json_encode( __( '-- Choose an attribute --', 'incentiv' ) ); ?> + '<\/option>' );
        <?php foreach ($attributes as $attribute): ?>
        attrOptions += '<option value="pa_<?php echo esc_js($attribute->attribute_name); ?>"><?php echo esc_js($attribute->attribute_label); ?></option>';
        <?php endforeach; ?>

        var html = '<div class="mmd-additional-attr-row" style="display: flex; gap: 10px; margin-bottom: 10px; padding: 10px; background: #f9f9f9; border-radius: 4px; border: 1px solid #e0e0e0; align-items: flex-start;">' +
            ( '<div style="flex: 1;"><label style="display: block; font-size: 12px; color: #666; margin-bottom: 3px;">' + <?php echo wp_json_encode( __( 'Attribute', 'incentiv' ) ); ?> + '<\/label>' ) +
            '<select class="gift-additional-attr-slug regular-text" style="width: 100%;">' + attrOptions + '</select></div>' +
            ( '<div style="flex: 2;"><label style="display: block; font-size: 12px; color: #666; margin-bottom: 3px;">' + <?php echo wp_json_encode( __( 'Values', 'incentiv' ) ); ?> + '<\/label>' ) +
            ( '<div class="gift-additional-attr-values mmd-checkbox-group">' + <?php echo wp_json_encode( __( 'Choose an attribute...', 'incentiv' ) ); ?> + '<\/div><\/div>' ) +
            ( '<div style="padding-top: 20px;"><button type="button" class="button remove-additional-attr" title="' + <?php echo wp_json_encode( __( 'Remove', 'incentiv' ) ); ?> + '"><span class="dashicons dashicons-trash" style="vertical-align: middle;"><\/span><\/button><\/div>' ) +
            '</div>';
        $('#gift_additional_attributes_container').append(html);
    });

    $(document).on('click', '.remove-additional-attr', function() {
        $(this).closest('.mmd-additional-attr-row').remove();
    });

    $(document).on('change', '.gift-additional-attr-slug', function() {
        var $row = $(this).closest('.mmd-additional-attr-row');
        var slug = $(this).val();
        var $valuesContainer = $row.find('.gift-additional-attr-values');

        if (!slug) {
            $valuesContainer.html(<?php echo wp_json_encode( __( 'Choose an attribute...', 'incentiv' ) ); ?>);
            return;
        }

        $valuesContainer.html(<?php echo wp_json_encode( __( 'Loading...', 'incentiv' ) ); ?>);
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mmd_get_attribute_terms',
                nonce: mmdGiftRulesAjax.nonce,
                attribute_slug: slug
            },
            success: function(response) {
                if (response.success) {
                    var html = '';
                    response.data.forEach(function(term) {
                        html += '<label><input type="checkbox" class="gift-additional-attr-value" data-slug="' + slug + '" value="' + term.term_id + '"> ' + term.name + '</label>';
                    });
                    $valuesContainer.html(html);
                }
            }
        });
    });

    // Load additional attributes on page load
    var additionalAttrs = ruleSet.gift_additional_attributes || [];
    if (additionalAttrs.length > 0) {
        additionalAttrs.forEach(function(addAttr, idx) {
            var $row = $('#gift_additional_attributes_container .mmd-additional-attr-row').eq(idx);
            if ($row.length && addAttr.slug) {
                var $valuesContainer = $row.find('.gift-additional-attr-values');
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'mmd_get_attribute_terms',
                        nonce: mmdGiftRulesAjax.nonce,
                        attribute_slug: addAttr.slug
                    },
                    success: function(response) {
                        if (response.success) {
                            var html = '';
                            response.data.forEach(function(term) {
                                var checked = addAttr.values && addAttr.values.indexOf(term.term_id) !== -1 ? 'checked' : '';
                                html += '<label><input type="checkbox" class="gift-additional-attr-value" data-slug="' + addAttr.slug + '" value="' + term.term_id + '" ' + checked + '> ' + term.name + '</label>';
                            });
                            $valuesContainer.html(html);
                        }
                    }
                });
            }
        });
    }

    function loadGiftAttributeTerms(slug) {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mmd_get_attribute_terms',
                nonce: mmdGiftRulesAjax.nonce,
                attribute_slug: slug
            },
            success: function(response) {
                if (response.success) {
                    var terms = response.data;
                    var valuesHtml = '';

                    terms.forEach(function(term) {
                        var checked = ruleSet.gift_attribute_values && ruleSet.gift_attribute_values.indexOf(term.term_id) !== -1 ? 'checked' : '';
                        valuesHtml += '<label><input type="checkbox" name="gift_attribute_values[]" value="' + term.term_id + '" ' + checked + '> ' + term.name + '</label>';
                    });

                    $('#gift_attribute_values_list').html(valuesHtml);
                }
            }
        });
    }

    // Load gift attribute terms on page load if attribute is selected
    if (ruleSet.gift_attribute_slug) {
        loadGiftAttributeTerms(ruleSet.gift_attribute_slug);
    }

    // Save rule
    function buildGiftFormData() {
        // Collect form data
        var formData = {
            id: ruleSet.id,
            name: $('#rule_name').val(),
            enabled: $('#enabled').is(':checked') ? 'yes' : 'no',
            selection_method: $('#selection_method').val(),
            included_categories: [],
            excluded_categories: [],
            included_products: $('#included_products').val() || [],
            excluded_products: $('#excluded_products').val() || [],
            excluded_products_from_categories: $('#excluded_products_from_categories').val() || [],
            attribute_slug: $('#attribute_slug').val(),
            attribute_values: [],
            attribute_excluded_values: [],
            gift_type: $('input[name="gift_type"]:checked').val(),
            gift_discount: $('#gift_discount').val(),
            gift_mode: $('input[name="gift_mode"]:checked').val() || 'product_page',
            cart_gift_notice: $('#cart_gift_notice').val() || '',
            cart_gift_display_mode: $('#cart_gift_display_mode').val() || 'inline',
            show_gift_selection: $('#show_gift_selection').is(':checked') ? 'yes' : 'no',
            gift_limit_type: $('#gift_limit_type').val() || 'per_cart',
            gift_source: $('#gift_source').val(),
            gift_products: $('#gift_products').val() || [],
            gift_categories: [],
            gift_excluded_categories: [],
            gift_excluded_tags: [],
            gift_excluded_products: $('#gift_excluded_products').val() || [],
            gift_attribute_slug: $('#gift_attribute_slug').val() || '',
            gift_attribute_values: [],
            gift_selection_type: $('#gift_selection_type').val(),
            override_product_settings: $('#override_product_settings').is(':checked') ? 'yes' : 'no',
            allow_quantity: $('#allow_quantity').is(':checked') ? 'yes' : 'no',
            max_quantity: $('#max_quantity').val(),
            total_quantity_limit: $('#total_quantity_limit').val() || '0',
            gift_default_variations: {},
            gift_products_limit: $('#gift_products_limit').val() || '0',
            cart_message: $('#cart_message').val() || '',
            checkout_message: $('#checkout_message').val() || '',
            shipping_method_condition: $('#shipping_method_condition').val() || '',
            min_cart_total: $('#min_cart_total').val() || '',
            max_cart_total: $('#max_cart_total').val() || '',
            allowed_user_roles: $('#allowed_user_roles').val() || [],
            schedule_start: $('#schedule_start').val() || '',
            schedule_end: $('#schedule_end').val() || '',
            min_quantity_trigger: $('#min_quantity_trigger').val() || '',
            coupon_required: $('#coupon_required').is(':checked') ? 'yes' : 'no',
            required_coupon_codes: (function() {
                var raw = $('#required_coupon_codes').val() || '';
                return raw.split(',').map(function(s){ return s.trim().toLowerCase(); }).filter(function(s){ return s.length > 0; });
            })(),
            promo_notice_enabled: $('#promo_notice_enabled').is(':checked') ? 'yes' : 'no',
            promo_notice_text: $('#promo_notice_text').val() || '',
            promo_notice_pages: $('#promo_notice_pages').val() || 'all',
            promo_notice_link: $('#promo_notice_link').val() || '',
            promo_notice_link_text: $('#promo_notice_link_text').val() || <?php echo wp_json_encode( __( 'View products', 'incentiv' ) ); ?>,
            combined_discount_enabled: $('#combined_discount_enabled').is(':checked') ? 'yes' : 'no',
            combined_discount_rules: [],
            combined_discount_mode: $('#combined_discount_mode').val() || 'quantity',
            combined_discount_apply_to: $('#combined_discount_apply_to').val() || 'cheapest',
            combined_discount_notice_enabled: $('#combined_discount_notice_enabled').is(':checked') ? 'yes' : 'no',
            combined_discount_notice_title: $('#combined_discount_notice_title').val() || <?php echo wp_json_encode( __( '💰 Buy in quantity and save!', 'incentiv' ) ); ?>,
            combined_discount_notice_color: $('#combined_discount_notice_color').val() || '#4CAF50'
        };

        // Collect combined discount rules
        $('#combined-discount-rules-container .combined-discount-row').each(function() {
            var qty = $(this).find('input[name="combined_discount_quantity[]"]').val();
            var disc = $(this).find('input[name="combined_discount_amount[]"]').val();
            var type = $(this).find('select[name="combined_discount_type[]"]').val();
            if (qty && disc) {
                formData.combined_discount_rules.push({
                    quantity: parseInt(qty),
                    discount: parseFloat(disc),
                    type: type
                });
            }
        });

        // Collect product eligibility categories
        $('input[name="included_categories[]"]:checked').each(function() {
            formData.included_categories.push(parseInt($(this).val()));
        });
        $('input[name="excluded_categories[]"]:checked').each(function() {
            formData.excluded_categories.push(parseInt($(this).val()));
        });

        // Collect gift categories
        $('input[name="gift_categories[]"]:checked').each(function() {
            formData.gift_categories.push(parseInt($(this).val()));
        });

        // Collect gift exclusion categories
        $('input[name="gift_excluded_categories[]"]:checked').each(function() {
            formData.gift_excluded_categories.push(parseInt($(this).val()));
        });

        // Collect gift exclusion tags
        $('input[name="gift_excluded_tags[]"]:checked').each(function() {
            formData.gift_excluded_tags.push(parseInt($(this).val()));
        });

        // Collect gift attribute values
        $('input[name="gift_attribute_values[]"]:checked').each(function() {
            formData.gift_attribute_values.push(parseInt($(this).val()));
        });

        // Collect additional gift attribute filters
        formData.gift_additional_attributes = [];
        $('#gift_additional_attributes_container .mmd-additional-attr-row').each(function() {
            var slug = $(this).find('.gift-additional-attr-slug').val();
            if (!slug) return;
            var values = [];
            $(this).find('.gift-additional-attr-value:checked').each(function() {
                values.push(parseInt($(this).val()));
            });
            formData.gift_additional_attributes.push({
                slug: slug,
                values: values
            });
        });

        // Collect attribute values
        $('input[name="attribute_values[]"]:checked').each(function() {
            formData.attribute_values.push(parseInt($(this).val()));
        });
        $('input[name="attribute_excluded_values[]"]:checked').each(function() {
            formData.attribute_excluded_values.push(parseInt($(this).val()));
        });

        // Collect default variations
        $('select[name^="gift_default_variations"]').each(function() {
            var name = $(this).attr('name');
            var match = name.match(/gift_default_variations\[(\d+)\]/);
            if (match && $(this).val()) {
                formData.gift_default_variations[match[1]] = parseInt($(this).val());
            }
        });

        return formData;
    }

    // ---- Publish + draft (server-side) ----
    var giftDraftInitialStatus = <?php echo json_encode(isset($rule_set['status']) ? $rule_set['status'] : ''); ?>;
    var giftDraftContext = <?php echo $is_new ? 'true' : 'false'; ?> || giftDraftInitialStatus === 'draft';
    var $giftForm = $('[data-mmd-autosave="gift"]').first();
    if (giftDraftContext) { $giftForm.data('mmdServerDraftActive', true); }
    var giftSaving = false, giftPublished = false;

    function giftStamp(msg) {
        var d = new Date(), hh = ('0' + d.getHours()).slice(-2), mm = ('0' + d.getMinutes()).slice(-2);
        $('#mmd-gift-draft-status').text(msg + ' · ' + hh + ':' + mm).addClass('is-visible');
    }

    // Publish (full save)
    $('#save-gift-rule').on('click', function() {
        var button = $(this);
        button.prop('disabled', true).text(<?php echo wp_json_encode( __( 'Saving...', 'incentiv' ) ); ?>);
        giftPublished = true;
        $.ajax({
            url: ajaxurl, type: 'POST',
            data: { action: 'mmd_save_gift_rule', nonce: mmdGiftRulesAjax.nonce, rule_set: JSON.stringify(buildGiftFormData()) },
            success: function(response) {
                if (response.success) {
                    if (window.mmdClearAutosave) { window.mmdClearAutosave(); }
                    window.location.href = '<?php echo esc_url( admin_url('admin.php?page=mmd-gift-rules') ); ?>';
                } else {
                    giftPublished = false;
                    alert(( <?php echo wp_json_encode( __( 'Error:', 'incentiv' ) ); ?> + ' ' ) + response.data);
                    button.prop('disabled', false).text('<?php echo $is_new ? esc_html__( '➕ Create gift rule', 'incentiv' ) : esc_html__( '💾 Save changes', 'incentiv' ); ?>');
                }
            },
            error: function(xhr, status, error) {
                giftPublished = false;
                console.error('AJAX Error:', status, error);
                alert(( <?php echo wp_json_encode( __( 'Server error:', 'incentiv' ) ); ?> + ' ' ) + error);
                button.prop('disabled', false).text('<?php echo $is_new ? esc_html__( '➕ Create gift rule', 'incentiv' ) : esc_html__( '💾 Save changes', 'incentiv' ); ?>');
            }
        });
    });

    // Draft (explicit button + automatic for new / draft rules)
    function saveGiftDraft(manual) {
        if (giftSaving || giftPublished) { return; }
        if (!manual && !giftDraftContext) { return; }
        if (!$('#rule_name').val() && !ruleSet.id) { return; }
        giftSaving = true;
        if (manual) { $('#mmd-gift-draft-status').text(<?php echo wp_json_encode( __( 'Saving…', 'incentiv' ) ); ?>).addClass('is-visible'); }
        $.ajax({
            url: ajaxurl, type: 'POST',
            data: { action: 'mmd_save_gift_rule', nonce: mmdGiftRulesAjax.nonce, is_draft: 1, rule_set: JSON.stringify(buildGiftFormData()) },
            success: function(response) {
                giftSaving = false;
                if (!response || !response.success) { if (manual) { giftStamp(<?php echo wp_json_encode( __( 'Save failed', 'incentiv' ) ); ?>); } return; }
                var saved = response.data && response.data.rule_set;
                if (saved && saved.id && !ruleSet.id) {
                    ruleSet.id = saved.id;
                    giftDraftContext = true;
                    $giftForm.data('mmdServerDraftActive', true);
                    try {
                        var u = new URL(window.location.href);
                        u.searchParams.set('action', 'edit');
                        u.searchParams.set('rule_id', saved.id);
                        history.replaceState({}, '', u.toString());
                    } catch (e) {}
                }
                if (window.mmdClearAutosave) { window.mmdClearAutosave(); }
                giftStamp(<?php echo wp_json_encode( __( 'Draft saved', 'incentiv' ) ); ?>);
            },
            error: function() { giftSaving = false; if (manual) { giftStamp(<?php echo wp_json_encode( __( 'Save failed', 'incentiv' ) ); ?>); } }
        });
    }
    $('#mmd-gift-save-draft').on('click', function() { saveGiftDraft(true); });
    (window.mmdSetInterval || setInterval)(function() { saveGiftDraft(false); }, 12000);
});
</script>

<script>
jQuery(function($) {
    // ==========================================================================
    // STEPPED GIFT EDITOR — navigable steps, every step freely clickable
    // ==========================================================================
    (function() {
        var STEPS = [
            { id: 1, label: <?php echo wp_json_encode( __( 'Details and products', 'incentiv' ) ); ?>, keywords: [<?php echo wp_json_encode( __( 'Basic settings', 'incentiv' ) ); ?>, <?php echo wp_json_encode( __( 'Product selection', 'incentiv' ) ); ?>] },
            { id: 2, label: <?php echo wp_json_encode( __( 'Gift setup', 'incentiv' ) ); ?>,   keywords: [<?php echo wp_json_encode( __( 'Gift settings', 'incentiv' ) ); ?>, <?php echo wp_json_encode( __( 'Default variations', 'incentiv' ) ); ?>] },
            { id: 3, label: <?php echo wp_json_encode( __( 'Promo and discount', 'incentiv' ) ); ?>,   keywords: [<?php echo wp_json_encode( __( 'Promo message', 'incentiv' ) ); ?>, <?php echo wp_json_encode( __( 'Combined quantity discount', 'incentiv' ) ); ?>] },
            { id: 4, label: <?php echo wp_json_encode( __( 'Summary and save', 'incentiv' ) ); ?>,  keywords: ['__summary__'] }
        ];
        var currentStep = 1;

        function stepForCard($card) {
            var title = $card.find('.mmd-card-title').first().text();
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

        // Summary card
        var $summary = $('<div class="mmd-card" data-mmd-step="4" id="mmd-gift-summary">' +
            ( '<h2 class="mmd-card-title"><span class="msr" style="color:var(--mmd-success);">check_circle<\/span> ' + <?php echo wp_json_encode( __( 'Gift summary', 'incentiv' ) ); ?> + '<\/h2>' ) +
            '<div id="mmd-gift-summary-rows"></div>' +
            ( '<div class="mmd-card-tip" style="margin-top:14px;background:var(--mmd-success-light);border-right-color:var(--mmd-success);">' + <?php echo wp_json_encode( __( 'Check that everything is right, then save. You can go back to any step by clicking its number above.', 'incentiv' ) ); ?> + '<\/div>' ) +
            '</div>');
        $('.mmd-admin-container').append($summary);

        function esc(t) { return $('<i>').text(t == null ? '' : t).html(); }

        function buildSummary() {
            var giftType = { free: <?php echo wp_json_encode( __( 'Free gift', 'incentiv' ) ); ?>, discount: <?php echo wp_json_encode( __( 'Percentage discount', 'incentiv' ) ); ?>, fixed_discount: <?php echo wp_json_encode( __( 'Fixed discount', 'incentiv' ) ); ?> };
            var giftMode = { product_page: <?php echo wp_json_encode( __( 'Customer choice (on the product page)', 'incentiv' ) ); ?>, cart_based: <?php echo wp_json_encode( __( 'Automatic gift (in the cart)', 'incentiv' ) ); ?> };
            var methodLabels = { all: <?php echo wp_json_encode( __( 'All products', 'incentiv' ) ); ?>, category: <?php echo wp_json_encode( __( 'By categories', 'incentiv' ) ); ?>, product_ids: <?php echo wp_json_encode( __( 'Selected products', 'incentiv' ) ); ?>, attribute: <?php echo wp_json_encode( __( 'By attribute', 'incentiv' ) ); ?> };
            var srcLabels = { specific: <?php echo wp_json_encode( __( 'Specific products', 'incentiv' ) ); ?>, category: <?php echo wp_json_encode( __( 'By categories', 'incentiv' ) ); ?> };

            var rows = [
                [<?php echo wp_json_encode( __( 'Rule name', 'incentiv' ) ); ?>, $('#rule_name').val() || <?php echo wp_json_encode( __( '(no name)', 'incentiv' ) ); ?>, 1],
                [<?php echo wp_json_encode( __( 'Status', 'incentiv' ) ); ?>, $('#enabled').is(':checked') ? <?php echo wp_json_encode( __( 'Active', 'incentiv' ) ); ?> : <?php echo wp_json_encode( __( 'Disabled', 'incentiv' ) ); ?>, 1],
                [<?php echo wp_json_encode( __( 'Qualifying products', 'incentiv' ) ); ?>, methodLabels[$('#selection_method').val()] || '', 1],
                [<?php echo wp_json_encode( __( 'Gift type', 'incentiv' ) ); ?>, giftType[$('input[name="gift_type"]:checked').val()] || '—', 2],
                [<?php echo wp_json_encode( __( 'How it\'s given', 'incentiv' ) ); ?>, giftMode[$('input[name="gift_mode"]:checked').val()] || '—', 2],
                [<?php echo wp_json_encode( _x( 'Gift source', 'summary', 'incentiv' ) ); ?>, srcLabels[$('#gift_source').val()] || '—', 2],
                [<?php echo wp_json_encode( __( 'Min. cart total', 'incentiv' ) ); ?>, $('#min_cart_total').val() ? ($('#min_cart_total').val() + ' ₪') : <?php echo wp_json_encode( __( 'None', 'incentiv' ) ); ?>, 2],
                [<?php echo wp_json_encode( __( 'Min. qualifying qty', 'incentiv' ) ); ?>, $('#min_quantity_trigger').val() || <?php echo wp_json_encode( __( 'None', 'incentiv' ) ); ?>, 2],
                [<?php echo wp_json_encode( __( 'Max. gifts', 'incentiv' ) ); ?>, $('#gift_products_limit').val() || <?php echo wp_json_encode( __( 'Unlimited', 'incentiv' ) ); ?>, 2]
            ];
            var html = '';
            rows.forEach(function(r) {
                html += '<div style="display:flex;justify-content:space-between;align-items:center;gap:12px;padding:11px 4px;border-bottom:1px solid var(--mmd-border-lighter);font-size:13.5px;">' +
                    '<span style="color:var(--mmd-text-muted);flex:none;">' + esc(r[0]) + '</span>' +
                    '<span style="font-weight:700;color:var(--mmd-ink);text-align:left;">' + esc(r[1]) + '</span>' +
                    '<button type="button" class="mmd-icon-btn mmd-gift-summary-edit" data-step="' + r[2] + ( '" title="' + <?php echo wp_json_encode( __( 'Edit', 'incentiv' ) ); ?> + '" style="flex:none;width:28px;height:28px;"><span class="msr" style="font-size:15px;">edit<\/span><\/button>' ) +
                    '</div>';
            });
            $('#mmd-gift-summary-rows').html(html);
        }

        function renderStepper() {
            var html = '';
            STEPS.forEach(function(s) {
                var cls = s.id === currentStep ? ' active' : (s.id < currentStep ? ' completed' : '');
                html += '<button type="button" class="mmd-stepper-step' + cls + '" data-step="' + s.id + '">' +
                    '<span class="mmd-step-num">' + (s.id < currentStep ? '✓' : s.id) + '</span>' +
                    '<span class="mmd-step-label">' + s.label + '</span></button>';
            });
            $('#mmd-gift-stepper-steps').html(html);
        }

        function showStep(n, scroll) {
            currentStep = Math.max(1, Math.min(STEPS.length, n));
            $('.mmd-admin-container > .mmd-card[data-mmd-step]').each(function() {
                $(this).toggleClass('is-step-active', parseInt($(this).attr('data-mmd-step'), 10) === currentStep);
            });
            if (currentStep === STEPS.length) buildSummary();
            renderStepper();
            $('#mmd-gift-step-prev').prop('disabled', currentStep === 1);
            $('#mmd-gift-step-next').toggle(currentStep < STEPS.length);
            $('#mmd-gift-step-indicator').text(( <?php echo wp_json_encode( __( 'Step', 'incentiv' ) ); ?> + ' ' ) + currentStep + ( ' ' + <?php echo wp_json_encode( __( 'of', 'incentiv' ) ); ?> + ' ' ) + STEPS.length);
            if (history.replaceState) history.replaceState(null, '', '#step=' + currentStep);
            $(document).trigger('mmd:substep');
            if (scroll !== false) window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        $(document).on('click', '.mmd-stepper-step', function() { showStep(parseInt($(this).data('step'), 10)); });
        $(document).on('click', '.mmd-gift-summary-edit', function() { showStep(parseInt($(this).data('step'), 10)); });
        $(document).on('click', '#mmd-gift-step-next', function() { showStep(currentStep + 1); });
        $(document).on('click', '#mmd-gift-step-prev', function() { showStep(currentStep - 1); });

        // Expose so the gift wizard can land on a step after finishing
        window.mmdShowGiftStep = showStep;

        var m = (window.location.hash || '').match(/step=(\d+)/);
        showStep(m ? parseInt(m[1], 10) : 1, false);
    })();
});
</script>
