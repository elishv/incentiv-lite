<?php
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap mmd-admin-wrap">
    <h1><span class="dashicons dashicons-admin-settings"></span> <?php echo wp_kses_post( __( 'Quantity discount settings - Mix & Match', 'incentiv' ) ); ?></h1>
    
    <form method="post" action="">
        <?php wp_nonce_field('mmd_settings_nonce'); ?>
        
        <div class="mmd-admin-container">
            
            <!-- General Settings -->
            <div class="mmd-card">
                <h2 class="mmd-card-title"><span class="dashicons dashicons-admin-settings"></span> <?php esc_html_e( 'General settings', 'incentiv' ); ?></h2>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="enabled"><?php esc_html_e( 'Enable quantity discounts', 'incentiv' ); ?></label>
                        </th>
                        <td>
                            <label class="mmd-switch">
                                <input type="checkbox" name="mmd_settings[enabled]" id="enabled" <?php checked($settings['enabled'], 'yes'); ?>>
                                <span class="mmd-slider"></span>
                            </label>
                            <p class="description"><?php esc_html_e( 'Enable or disable the quantity discount system', 'incentiv' ); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="apply_to"><?php esc_html_e( 'Apply discount to', 'incentiv' ); ?></label>
                        </th>
                        <td>
                            <select name="mmd_settings[apply_to]" id="apply_to" class="regular-text">
                                <option value="cheapest" <?php selected($settings['apply_to'], 'cheapest'); ?>><?php esc_html_e( 'The cheapest item', 'incentiv' ); ?></option>
                                <option value="expensive" <?php selected($settings['apply_to'], 'expensive'); ?>><?php esc_html_e( 'The most expensive item', 'incentiv' ); ?></option>
                            </select>
                            <p class="description"><?php esc_html_e( 'Choose which item the discount applies to', 'incentiv' ); ?></p>
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
                            <select name="mmd_settings[selection_method]" id="selection_method" class="regular-text">
                                <option value="all" <?php selected($settings['selection_method'], 'all'); ?>><?php esc_html_e( 'All products', 'incentiv' ); ?></option>
                                <option value="category" <?php selected($settings['selection_method'], 'category'); ?>><?php esc_html_e( 'By categories', 'incentiv' ); ?></option>
                                <option value="product_ids" <?php selected($settings['selection_method'], 'product_ids'); ?>><?php esc_html_e( 'By product IDs', 'incentiv' ); ?></option>
                                <option value="attribute" <?php selected($settings['selection_method'], 'attribute'); ?>><?php esc_html_e( 'By attribute', 'incentiv' ); ?></option>
                            </select>
                            <p class="description"><?php esc_html_e( 'Choose how to identify products eligible for the discount', 'incentiv' ); ?></p>
                        </td>
                    </tr>
                </table>
                
                <!-- Category Selection -->
                <div id="category_section" class="mmd-section" style="display: <?php echo $settings['selection_method'] === 'category' ? 'block' : 'none'; ?>;">
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
                                            <input type="checkbox" name="mmd_settings[included_categories][]" value="<?php echo esc_attr($category->slug); ?>" <?php checked(in_array($category->slug, $settings['included_categories'])); ?>>
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
                                            <input type="checkbox" name="mmd_settings[excluded_categories][]" value="<?php echo esc_attr($category->slug); ?>" <?php checked(in_array($category->slug, $settings['excluded_categories'])); ?>>
                                            <?php echo esc_html($category->name); ?>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                                <p class="description"><?php esc_html_e( 'Choose categories that are not eligible for the discount (overrides included)', 'incentiv' ); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- Product IDs Selection -->
                <div id="product_ids_section" class="mmd-section" style="display: <?php echo $settings['selection_method'] === 'product_ids' ? 'block' : 'none'; ?>;">
                    <h3><?php esc_html_e( 'Product IDs', 'incentiv' ); ?></h3>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="included_products"><?php esc_html_e( 'Included products', 'incentiv' ); ?></label>
                            </th>
                            <td>
                                <input type="text" name="mmd_settings[included_products]" id="included_products" value="<?php echo esc_attr(implode(',', $settings['included_products'])); ?>" class="large-text">
                                <p class="description"><?php esc_html_e( 'Enter comma-separated product IDs (for example: 123,456,789)', 'incentiv' ); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="excluded_products"><?php echo esc_html_x( 'Excluded products', 'settings', 'incentiv' ); ?></label>
                            </th>
                            <td>
                                <input type="text" name="mmd_settings[excluded_products]" id="excluded_products" value="<?php echo esc_attr(implode(',', $settings['excluded_products'])); ?>" class="large-text">
                                <p class="description"><?php esc_html_e( 'Enter comma-separated product IDs that are not eligible for the discount', 'incentiv' ); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- Attribute Selection -->
                <div id="attribute_section" class="mmd-section" style="display: <?php echo $settings['selection_method'] === 'attribute' ? 'block' : 'none'; ?>;">
                    <h3><?php esc_html_e( 'Attributes', 'incentiv' ); ?></h3>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="attribute_slug"><?php esc_html_e( 'Attribute', 'incentiv' ); ?></label>
                            </th>
                            <td>
                                <select name="mmd_settings[attribute_slug]" id="attribute_slug" class="regular-text">
                                    <option value=""><?php esc_html_e( 'Choose an attribute...', 'incentiv' ); ?></option>
                                    <?php foreach ($attributes as $attribute): ?>
                                        <option value="<?php echo esc_attr($attribute->attribute_name); ?>" <?php selected($settings['attribute_slug'], $attribute->attribute_name); ?>>
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
                                <input type="text" name="mmd_settings[attribute_values]" id="attribute_values" value="<?php echo esc_attr(implode(',', $settings['attribute_values'])); ?>" class="large-text">
                                <p class="description"><?php esc_html_e( 'Enter comma-separated values (for example: 1-kg,2-kg or 1kg,2kg)', 'incentiv' ); ?></p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="attribute_excluded_values"><?php echo esc_html_x( 'Excluded attribute values', 'settings', 'incentiv' ); ?></label>
                            </th>
                            <td>
                                <input type="text" name="mmd_settings[attribute_excluded_values]" id="attribute_excluded_values" value="<?php echo esc_attr(implode(',', isset($settings['attribute_excluded_values']) ? $settings['attribute_excluded_values'] : array())); ?>" class="large-text">
                                <p class="description"><?php esc_html_e( 'Enter values that are not eligible for the discount (overrides included)', 'incentiv' ); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
            
            <!-- Discount Rules -->
            <div class="mmd-card">
                <h2 class="mmd-card-title"><span class="dashicons dashicons-money-alt"></span> <?php esc_html_e( 'Discount rules', 'incentiv' ); ?></h2>
                
                <div id="discount_rules_container">
                    <?php 
                    $rules = $settings['discount_rules'];
                    if (empty($rules)) {
                        $rules = array(array('quantity' => '', 'discount' => '', 'type' => 'percentage'));
                    }
                    foreach ($rules as $index => $rule): 
                    ?>
                        <div class="mmd-discount-rule">
                            <div class="mmd-rule-fields">
                                <div class="mmd-field">
                                    <label><?php esc_html_e( 'Quantity', 'incentiv' ); ?></label>
                                    <input type="number" name="mmd_settings[discount_rules][<?php echo esc_html( $index ); ?>][quantity]" value="<?php echo esc_attr($rule['quantity']); ?>" min="1" placeholder="3">
                                </div>
                                
                                <div class="mmd-field">
                                    <label><?php esc_html_e( 'Discount', 'incentiv' ); ?></label>
                                    <input type="number" name="mmd_settings[discount_rules][<?php echo esc_html( $index ); ?>][discount]" value="<?php echo esc_attr($rule['discount']); ?>" min="0" step="0.01" placeholder="30">
                                </div>
                                
                                <div class="mmd-field">
                                    <label><?php esc_html_e( 'Type', 'incentiv' ); ?></label>
                                    <select name="mmd_settings[discount_rules][<?php echo esc_html( $index ); ?>][type]">
                                        <option value="percentage" <?php selected($rule['type'], 'percentage'); ?>><?php esc_html_e( 'Percentage (%)', 'incentiv' ); ?></option>
                                        <option value="fixed" <?php selected($rule['type'], 'fixed'); ?>><?php esc_html_e( 'Fixed amount (₪)', 'incentiv' ); ?></option>
                                    </select>
                                </div>
                                
                                <button type="button" class="button mmd-remove-rule"><span class="dashicons dashicons-trash" style="font-size:14px;width:14px;height:14px;vertical-align:middle;"></span> <?php esc_html_e( 'Remove', 'incentiv' ); ?></button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <button type="button" id="add_discount_rule" class="button button-secondary"><span class="dashicons dashicons-plus-alt" style="font-size:16px;width:16px;height:16px;vertical-align:middle;"></span> <?php esc_html_e( 'Add discount tier', 'incentiv' ); ?></button>
                <p class="description"><?php esc_html_e( 'Set different discounts for different quantities. The highest matching discount applies.', 'incentiv' ); ?></p>
            </div>
            
            <!-- Notice Settings -->
            <div class="mmd-card">
                <h2 class="mmd-card-title"><span class="dashicons dashicons-megaphone"></span> <?php esc_html_e( 'Customer messages', 'incentiv' ); ?></h2>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="notice_enabled"><?php esc_html_e( 'Show message', 'incentiv' ); ?></label>
                        </th>
                        <td>
                            <label class="mmd-switch">
                                <input type="checkbox" name="mmd_settings[notice_enabled]" id="notice_enabled" <?php checked($settings['notice_enabled'], 'yes'); ?>>
                                <span class="mmd-slider"></span>
                            </label>
                            <p class="description"><?php esc_html_e( 'Show a discount message on the cart and checkout pages', 'incentiv' ); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="notice_title"><?php echo esc_html_x( 'Message title', 'gift', 'incentiv' ); ?></label>
                        </th>
                        <td>
                            <input type="text" name="mmd_settings[notice_title]" id="notice_title" value="<?php echo esc_attr($settings['notice_title']); ?>" class="large-text">
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="notice_content"><?php echo esc_html_x( 'Message content', 'settings', 'incentiv' ); ?></label>
                        </th>
                        <td>
                            <textarea name="mmd_settings[notice_content]" id="notice_content" rows="5" class="large-text"><?php echo esc_textarea($settings['notice_content']); ?></textarea>
                            <p class="description"><?php esc_html_e( 'Leave empty for automatic content based on the discount tiers. HTML is allowed.', 'incentiv' ); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="notice_color"><?php echo esc_html_x( 'Message colour', 'gift', 'incentiv' ); ?></label>
                        </th>
                        <td>
                            <input type="text" name="mmd_settings[notice_color]" id="notice_color" value="<?php echo esc_attr($settings['notice_color']); ?>" class="mmd-color-picker">
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="discount_label"><?php esc_html_e( 'Discount label', 'incentiv' ); ?></label>
                        </th>
                        <td>
                            <input type="text" name="mmd_settings[discount_label]" id="discount_label" value="<?php echo esc_attr($settings['discount_label']); ?>" class="large-text">
                            <p class="description"><?php esc_html_e( 'The text shown in the cart for the discount', 'incentiv' ); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="show_in_mini_cart"><?php esc_html_e( 'Show in mini cart', 'incentiv' ); ?></label>
                        </th>
                        <td>
                            <label class="mmd-switch">
                                <input type="checkbox" name="mmd_settings[show_in_mini_cart]" id="show_in_mini_cart" <?php checked($settings['show_in_mini_cart'], 'yes'); ?>>
                                <span class="mmd-slider"></span>
                            </label>
                            <p class="description"><?php esc_html_e( 'Show the discount in the mini cart', 'incentiv' ); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Debug Mode -->
            <div class="mmd-card">
                <h2 class="mmd-card-title"><span class="dashicons dashicons-admin-tools"></span> <?php esc_html_e( 'Debug mode', 'incentiv' ); ?></h2>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="debug_mode"><?php esc_html_e( 'Enable debug mode', 'incentiv' ); ?></label>
                        </th>
                        <td>
                            <label class="mmd-switch">
                                <input type="checkbox" name="mmd_settings[debug_mode]" id="debug_mode" <?php checked($settings['debug_mode'], 'yes'); ?>>
                                <span class="mmd-slider"></span>
                            </label>
                            <p class="description"><?php esc_html_e( 'Show technical information on the cart page (admins only)', 'incentiv' ); ?></p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Global gift exclusions -->
            <div class="mmd-section">
                <h2><span class="dashicons dashicons-shield-alt"></span> <?php esc_html_e( 'Globally exclude products from any gift', 'incentiv' ); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="global_gift_excluded_products"><?php esc_html_e( 'Products excluded from all gifts', 'incentiv' ); ?></label>
                        </th>
                        <td>
                            <?php
                            $gge = isset($settings['global_gift_excluded_products']) && is_array($settings['global_gift_excluded_products'])
                                ? $settings['global_gift_excluded_products']
                                : array();
                            ?>
                            <select name="mmd_settings[global_gift_excluded_products][]" id="global_gift_excluded_products" multiple class="mmd-product-search-global-excluded" style="width: 100%; max-width: 700px;">
                                <?php foreach ($gge as $pid):
                                    $p = wc_get_product((int) $pid);
                                    if (!$p) continue;
                                    ?>
                                    <option value="<?php echo esc_attr($pid); ?>" selected>
                                        <?php echo esc_html($p->get_name() . ' — ' . wp_strip_all_tags(wc_price($p->get_price()))); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">
                                <?php esc_html_e( 'Any product selected here is never offered as a gift option, no matter which gift rule runs, which coupon is active, or which category it comes from.', 'incentiv' ); ?><br>
                                Any product picked here will never appear as a gift option — no matter which gift rule fires, which coupon is active, or which category sourced it.
                            </p>
                            <script>
                            jQuery(function($) {
                                if (typeof $.fn.select2 !== 'function') return;
                                $('#global_gift_excluded_products').select2({
                                    width: '100%',
                                    placeholder: <?php echo wp_json_encode( _x( 'Search products to exclude...', 'global exclusions', 'incentiv' ) ); ?>,
                                    allowClear: true,
                                    minimumInputLength: 0,
                                    ajax: {
                                        url: ajaxurl,
                                        type: 'POST',
                                        dataType: 'json',
                                        delay: 200,
                                        data: function(p){ return { action: 'mmd_preview_products_search', nonce: '<?php echo esc_attr( wp_create_nonce('mmd_preview_products_search') ); ?>', search: p.term || '' }; },
                                        processResults: function(resp){
                                            if (!resp || !resp.success || !Array.isArray(resp.data)) return { results: [] };
                                            return { results: resp.data.map(function(p){ return { id: p.id, text: p.name + ' — ' + p.formatted_price }; }) };
                                        },
                                        cache: true
                                    }
                                });
                            });
                            </script>
                        </td>
                    </tr>
                </table>
            </div>

        </div>

        <p class="submit">
            <button type="submit" name="mmd_save_settings" class="button button-primary button-hero"><span class="dashicons dashicons-saved" style="font-size:18px;width:18px;height:18px;vertical-align:middle;margin-left:4px;"></span> <?php echo esc_html_x( 'Save settings', 'gifts', 'incentiv' ); ?></button>
        </p>
    </form>
</div>
