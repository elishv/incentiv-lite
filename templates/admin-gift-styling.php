<?php
/**
 * Admin Gift Styling Page
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap mmd-admin-wrap">
    <?php include dirname(__FILE__) . '/admin-nav-tabs.php'; ?>
    <h1>
        <span class="dashicons dashicons-art"></span> <?php esc_html_e( 'Design settings', 'incentiv' ); ?>
        <a href="<?php echo esc_url( admin_url('admin.php?page=mmd-gift-rules') ); ?>" class="page-title-action"><?php esc_html_e( '← Back to list', 'incentiv' ); ?></a>
    </h1>
    <p class="mmd-page-lead"><?php esc_html_e( 'Customise how the gift selection box and cart notes look. The preview on the side updates instantly with every change.', 'incentiv' ); ?></p>

    <div class="mmd-styling-layout">

        <!-- ============ CONTROLS ============ -->
        <div class="mmd-styling-controls">

            <!-- Gift Box Styling -->
            <div class="mmd-card">
                <h2 class="mmd-card-title"><span class="dashicons dashicons-gifts"></span> <?php esc_html_e( 'Gift selection box design', 'incentiv' ); ?></h2>

                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="gift_box_title_text"><?php esc_html_e( 'Title text', 'incentiv' ); ?></label></th>
                        <td>
                            <input type="text" name="gift_box_title_text" id="gift_box_title_text" value="<?php echo esc_attr(isset($styling['gift_box_title_text']) ? $styling['gift_box_title_text'] : esc_html__( '🎁 Choose your gift!', 'incentiv' )); ?>" class="regular-text">
                            <p class="description"><?php esc_html_e( 'The title shown in the gift selection box', 'incentiv' ); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="gift_box_subtitle_text"><?php esc_html_e( 'Subtitle text', 'incentiv' ); ?></label></th>
                        <td>
                            <input type="text" name="gift_box_subtitle_text" id="gift_box_subtitle_text" value="<?php echo esc_attr(isset($styling['gift_box_subtitle_text']) ? $styling['gift_box_subtitle_text'] : __( 'Buy this product and get {gift_type} on {selection_type}:', 'incentiv' )); ?>" class="large-text">
                            <div class="mmd-mergetag-wrap">
                                <button type="button" class="mmd-mergetag-btn" data-target="gift_box_subtitle_text" data-tags="gift_type,selection_type"><span class="msr">sell</span> <?php esc_html_e( 'Merge tags', 'incentiv' ); ?></button>
                            </div>
                            <p class="description"><?php esc_html_e( 'The subtitle under the title. Click "Merge tags" to insert dynamic values such as the gift type or the selection type.', 'incentiv' ); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="gift_badge_text"><?php esc_html_e( 'Gift badge text', 'incentiv' ); ?></label></th>
                        <td>
                            <input type="text" name="gift_badge_text" id="gift_badge_text" value="<?php echo esc_attr(isset($styling['gift_badge_text']) ? $styling['gift_badge_text'] : esc_html__( '🎁 Gift', 'incentiv' )); ?>" class="regular-text">
                            <p class="description"><?php esc_html_e( 'The text shown on the gift badge in the cart and at checkout', 'incentiv' ); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="gift_box_bg_color_1"><?php esc_html_e( 'Background colour 1', 'incentiv' ); ?></label></th>
                        <td>
                            <input type="text" name="gift_box_bg_color_1" id="gift_box_bg_color_1" value="<?php echo esc_attr($styling['gift_box_bg_color_1']); ?>" class="mmd-color-picker">
                            <p class="description"><?php esc_html_e( 'First colour of the background gradient', 'incentiv' ); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="gift_box_bg_color_2"><?php esc_html_e( 'Background colour 2', 'incentiv' ); ?></label></th>
                        <td>
                            <input type="text" name="gift_box_bg_color_2" id="gift_box_bg_color_2" value="<?php echo esc_attr($styling['gift_box_bg_color_2']); ?>" class="mmd-color-picker">
                            <p class="description"><?php esc_html_e( 'Second colour of the background gradient', 'incentiv' ); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="gift_box_text_color"><?php esc_html_e( 'Text colour', 'incentiv' ); ?></label></th>
                        <td>
                            <input type="text" name="gift_box_text_color" id="gift_box_text_color" value="<?php echo esc_attr($styling['gift_box_text_color']); ?>" class="mmd-color-picker">
                            <p class="description"><?php esc_html_e( 'The text colour in the gift box', 'incentiv' ); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="gift_box_title_size"><?php esc_html_e( 'Title size', 'incentiv' ); ?></label></th>
                        <td>
                            <input type="number" name="gift_box_title_size" id="gift_box_title_size" value="<?php echo esc_attr(isset($styling['gift_box_title_size']) ? $styling['gift_box_title_size'] : '20'); ?>" min="12" max="48" class="small-text"> px
                            <p class="description"><?php esc_html_e( 'Title font size (12-48)', 'incentiv' ); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="gift_box_subtitle_size"><?php esc_html_e( 'Subtitle size', 'incentiv' ); ?></label></th>
                        <td>
                            <input type="number" name="gift_box_subtitle_size" id="gift_box_subtitle_size" value="<?php echo esc_attr(isset($styling['gift_box_subtitle_size']) ? $styling['gift_box_subtitle_size'] : '14'); ?>" min="10" max="32" class="small-text"> px
                            <p class="description"><?php esc_html_e( 'Subtitle font size (10-32)', 'incentiv' ); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="gift_box_border_radius"><?php esc_html_e( 'Corner radius', 'incentiv' ); ?></label></th>
                        <td>
                            <input type="number" name="gift_box_border_radius" id="gift_box_border_radius" value="<?php echo esc_attr($styling['gift_box_border_radius']); ?>" min="0" max="50" class="small-text"> px
                            <p class="description"><?php esc_html_e( 'Corner radius (0-50)', 'incentiv' ); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="gift_box_padding"><?php esc_html_e( 'Padding', 'incentiv' ); ?></label></th>
                        <td>
                            <input type="number" name="gift_box_padding" id="gift_box_padding" value="<?php echo esc_attr($styling['gift_box_padding']); ?>" min="0" max="100" class="small-text"> px
                            <p class="description"><?php esc_html_e( 'The box\'s inner spacing (padding)', 'incentiv' ); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="gift_box_margin"><?php esc_html_e( 'Margin', 'incentiv' ); ?></label></th>
                        <td>
                            <input type="number" name="gift_box_margin" id="gift_box_margin" value="<?php echo esc_attr(isset($styling['gift_box_margin']) ? $styling['gift_box_margin'] : '20'); ?>" min="0" max="100" class="small-text"> px
                            <p class="description"><?php esc_html_e( 'The outer spacing (margin) around the box', 'incentiv' ); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="gift_box_border_width"><?php esc_html_e( 'Border width', 'incentiv' ); ?></label></th>
                        <td>
                            <input type="number" name="gift_box_border_width" id="gift_box_border_width" value="<?php echo esc_attr(isset($styling['gift_box_border_width']) ? $styling['gift_box_border_width'] : '0'); ?>" min="0" max="10" class="small-text"> px
                            <p class="description"><?php esc_html_e( 'Border width (0 = no border)', 'incentiv' ); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="gift_box_border_color"><?php esc_html_e( 'Border colour', 'incentiv' ); ?></label></th>
                        <td>
                            <input type="text" name="gift_box_border_color" id="gift_box_border_color" value="<?php echo esc_attr(isset($styling['gift_box_border_color']) ? $styling['gift_box_border_color'] : '#ffffff'); ?>" class="mmd-color-picker">
                            <p class="description"><?php esc_html_e( 'Border colour (if set)', 'incentiv' ); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="gift_box_shadow"><?php echo esc_html_x( 'Shadow', 'style', 'incentiv' ); ?></label></th>
                        <td>
                            <select name="gift_box_shadow" id="gift_box_shadow" class="regular-text">
                                <option value="none" <?php selected(isset($styling['gift_box_shadow']) ? $styling['gift_box_shadow'] : 'none', 'none'); ?>><?php esc_html_e( 'No shadow', 'incentiv' ); ?></option>
                                <option value="small" <?php selected(isset($styling['gift_box_shadow']) ? $styling['gift_box_shadow'] : 'none', 'small'); ?>><?php esc_html_e( 'Small shadow', 'incentiv' ); ?></option>
                                <option value="medium" <?php selected(isset($styling['gift_box_shadow']) ? $styling['gift_box_shadow'] : 'none', 'medium'); ?>><?php esc_html_e( 'Medium shadow', 'incentiv' ); ?></option>
                                <option value="large" <?php selected(isset($styling['gift_box_shadow']) ? $styling['gift_box_shadow'] : 'none', 'large'); ?>><?php esc_html_e( 'Large shadow', 'incentiv' ); ?></option>
                            </select>
                            <p class="description"><?php esc_html_e( 'Add a shadow to the box', 'incentiv' ); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="gift_button_bg_color"><?php esc_html_e( 'Button background colour', 'incentiv' ); ?></label></th>
                        <td>
                            <input type="text" name="gift_button_bg_color" id="gift_button_bg_color" value="<?php echo esc_attr(isset($styling['gift_button_bg_color']) ? $styling['gift_button_bg_color'] : '#ffffff'); ?>" class="mmd-color-picker">
                            <p class="description"><?php esc_html_e( 'Background colour of the "Choose" button', 'incentiv' ); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="gift_button_text_color"><?php esc_html_e( 'Button text colour', 'incentiv' ); ?></label></th>
                        <td>
                            <input type="text" name="gift_button_text_color" id="gift_button_text_color" value="<?php echo esc_attr(isset($styling['gift_button_text_color']) ? $styling['gift_button_text_color'] : '#667eea'); ?>" class="mmd-color-picker">
                            <p class="description"><?php esc_html_e( 'Text colour of the "Choose" button', 'incentiv' ); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="gift_button_hover_bg"><?php esc_html_e( 'Button background colour (hover)', 'incentiv' ); ?></label></th>
                        <td>
                            <input type="text" name="gift_button_hover_bg" id="gift_button_hover_bg" value="<?php echo esc_attr(isset($styling['gift_button_hover_bg']) ? $styling['gift_button_hover_bg'] : '#667eea'); ?>" class="mmd-color-picker">
                            <p class="description"><?php esc_html_e( 'Background colour on mouse hover', 'incentiv' ); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="gift_button_hover_text"><?php esc_html_e( 'Button text colour (hover)', 'incentiv' ); ?></label></th>
                        <td>
                            <input type="text" name="gift_button_hover_text" id="gift_button_hover_text" value="<?php echo esc_attr(isset($styling['gift_button_hover_text']) ? $styling['gift_button_hover_text'] : '#ffffff'); ?>" class="mmd-color-picker">
                            <p class="description"><?php esc_html_e( 'Text colour on mouse hover', 'incentiv' ); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="gift_display_hook"><?php esc_html_e( 'Position on the product page', 'incentiv' ); ?></label></th>
                        <td>
                            <select name="gift_display_hook" id="gift_display_hook" class="regular-text">
                                <option value="before_add_to_cart" <?php selected(isset($styling['gift_display_hook']) ? $styling['gift_display_hook'] : 'before_add_to_cart', 'before_add_to_cart'); ?>><?php esc_html_e( 'Before the add to cart button (default)', 'incentiv' ); ?></option>
                                <option value="after_add_to_cart" <?php selected(isset($styling['gift_display_hook']) ? $styling['gift_display_hook'] : '', 'after_add_to_cart'); ?>><?php esc_html_e( 'After the add to cart button', 'incentiv' ); ?></option>
                                <option value="before_summary" <?php selected(isset($styling['gift_display_hook']) ? $styling['gift_display_hook'] : '', 'before_summary'); ?>><?php esc_html_e( 'Inside the product summary (middle)', 'incentiv' ); ?></option>
                                <option value="after_summary" <?php selected(isset($styling['gift_display_hook']) ? $styling['gift_display_hook'] : '', 'after_summary'); ?>><?php esc_html_e( 'After the product summary', 'incentiv' ); ?></option>
                                <option value="after_product_meta" <?php selected(isset($styling['gift_display_hook']) ? $styling['gift_display_hook'] : '', 'after_product_meta'); ?>><?php esc_html_e( 'After the product meta (categories/tags)', 'incentiv' ); ?></option>
                                <option value="before_tabs" <?php selected(isset($styling['gift_display_hook']) ? $styling['gift_display_hook'] : '', 'before_tabs'); ?>><?php esc_html_e( 'Before the tabs (description/additional information)', 'incentiv' ); ?></option>
                                <option value="elementor_widget_only" <?php selected(isset($styling['gift_display_hook']) ? $styling['gift_display_hook'] : '', 'elementor_widget_only'); ?>><?php esc_html_e( 'Only in the Elementor widget (no WooCommerce hook)', 'incentiv' ); ?></option>
                            </select>
                            <p class="description"><?php esc_html_e( 'Where the gift selection box is shown on the product page. Change it if the position doesn\'t suit your theme.', 'incentiv' ); ?></p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Cart Notes Styling -->
            <div class="mmd-card">
                <h2 class="mmd-card-title"><span class="dashicons dashicons-edit"></span> <?php esc_html_e( 'Cart notes design', 'incentiv' ); ?></h2>

                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="cart_note_bg_color"><?php esc_html_e( 'Background colour', 'incentiv' ); ?></label></th>
                        <td>
                            <input type="text" name="cart_note_bg_color" id="cart_note_bg_color" value="<?php echo esc_attr($styling['cart_note_bg_color']); ?>" class="mmd-color-picker">
                            <p class="description"><?php esc_html_e( 'Background colour of the note field', 'incentiv' ); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="cart_note_text_color"><?php esc_html_e( 'Text colour', 'incentiv' ); ?></label></th>
                        <td>
                            <input type="text" name="cart_note_text_color" id="cart_note_text_color" value="<?php echo esc_attr($styling['cart_note_text_color']); ?>" class="mmd-color-picker">
                            <p class="description"><?php esc_html_e( 'Text colour of the note field', 'incentiv' ); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="cart_note_label_size"><?php esc_html_e( 'Label text size', 'incentiv' ); ?></label></th>
                        <td>
                            <input type="number" name="cart_note_label_size" id="cart_note_label_size" value="<?php echo esc_attr(isset($styling['cart_note_label_size']) ? $styling['cart_note_label_size'] : '16'); ?>" min="10" max="32" class="small-text"> px
                            <p class="description"><?php esc_html_e( 'Label font size (10-32)', 'incentiv' ); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="cart_note_input_size"><?php esc_html_e( 'Field text size', 'incentiv' ); ?></label></th>
                        <td>
                            <input type="number" name="cart_note_input_size" id="cart_note_input_size" value="<?php echo esc_attr(isset($styling['cart_note_input_size']) ? $styling['cart_note_input_size'] : '14'); ?>" min="10" max="28" class="small-text"> px
                            <p class="description"><?php esc_html_e( 'Font size inside the field (10-28)', 'incentiv' ); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="cart_note_border_color"><?php echo esc_html_x( 'Border colour', 'note', 'incentiv' ); ?></label></th>
                        <td>
                            <input type="text" name="cart_note_border_color" id="cart_note_border_color" value="<?php echo esc_attr($styling['cart_note_border_color']); ?>" class="mmd-color-picker">
                            <p class="description"><?php esc_html_e( 'The border colour around the field', 'incentiv' ); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="cart_note_border_width"><?php echo esc_html_x( 'Border width', 'note', 'incentiv' ); ?></label></th>
                        <td>
                            <input type="number" name="cart_note_border_width" id="cart_note_border_width" value="<?php echo esc_attr(isset($styling['cart_note_border_width']) ? $styling['cart_note_border_width'] : '1'); ?>" min="0" max="10" class="small-text"> px
                            <p class="description"><?php esc_html_e( 'Border width (0-10)', 'incentiv' ); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="cart_note_border_radius"><?php esc_html_e( 'Corner radius', 'incentiv' ); ?></label></th>
                        <td>
                            <input type="number" name="cart_note_border_radius" id="cart_note_border_radius" value="<?php echo esc_attr($styling['cart_note_border_radius']); ?>" min="0" max="50" class="small-text"> px
                            <p class="description"><?php esc_html_e( 'Corner radius (0-50)', 'incentiv' ); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="cart_note_padding"><?php esc_html_e( 'Padding', 'incentiv' ); ?></label></th>
                        <td>
                            <input type="number" name="cart_note_padding" id="cart_note_padding" value="<?php echo esc_attr(isset($styling['cart_note_padding']) ? $styling['cart_note_padding'] : '10'); ?>" min="0" max="50" class="small-text"> px
                            <p class="description"><?php esc_html_e( 'The field\'s inner spacing', 'incentiv' ); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="cart_note_placeholder_color"><?php esc_html_e( 'Placeholder colour', 'incentiv' ); ?></label></th>
                        <td>
                            <input type="text" name="cart_note_placeholder_color" id="cart_note_placeholder_color" value="<?php echo esc_attr(isset($styling['cart_note_placeholder_color']) ? $styling['cart_note_placeholder_color'] : '#999999'); ?>" class="mmd-color-picker">
                            <p class="description"><?php esc_html_e( 'The placeholder text colour', 'incentiv' ); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="cart_note_focus_border"><?php esc_html_e( 'Border colour (focus)', 'incentiv' ); ?></label></th>
                        <td>
                            <input type="text" name="cart_note_focus_border" id="cart_note_focus_border" value="<?php echo esc_attr(isset($styling['cart_note_focus_border']) ? $styling['cart_note_focus_border'] : '#667eea'); ?>" class="mmd-color-picker">
                            <p class="description"><?php esc_html_e( 'The border colour when the field is active', 'incentiv' ); ?></p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Developer Settings -->
            <div class="mmd-card">
                <h2 class="mmd-card-title"><span class="dashicons dashicons-code-standards"></span> <?php esc_html_e( 'Developer Settings', 'incentiv' ); ?></h2>

                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="debug_mode"><?php esc_html_e( 'Debug mode', 'incentiv' ); ?></label></th>
                        <td>
                            <label>
                                <input type="checkbox" name="debug_mode" id="debug_mode" value="yes" <?php checked(isset($styling['debug_mode']) ? $styling['debug_mode'] : 'no', 'yes'); ?>>
                                <?php esc_html_e( 'Enable verbose error logging', 'incentiv' ); ?>
                            </label>
                            <p class="description"><?php esc_html_e( 'When enabled, the plugin writes detailed debug messages to the error log. Disable on production sites.', 'incentiv' ); ?></p>
                        </td>
                    </tr>
                </table>
            </div>

        </div>

        <!-- ============ STICKY LIVE PREVIEW ============ -->
        <aside class="mmd-styling-preview">
            <div class="mmd-preview-rail">
                <div class="mmd-preview-rail-head">
                    <span class="msr">visibility</span> <?php esc_html_e( 'Preview', 'incentiv' ); ?>
                    <span class="mmd-preview-live">Live</span>
                </div>

                <div class="mmd-preview-stage">
                    <div class="mmd-preview-stage-label"><?php esc_html_e( 'Product page · gift box', 'incentiv' ); ?></div>

                    <div class="mmd-preview-mock-product">
                        <span class="mmd-preview-mock-thumb"><span class="msr">local_cafe</span></span>
                        <span class="mmd-preview-mock-lines">
                            <b><?php esc_html_e( 'Elegant ceramic mug', 'incentiv' ); ?></b>
                            <span class="mmd-preview-mock-price">₪89.00</span>
                        </span>
                    </div>

                    <div class="mmd-gift-box-preview" id="gift-box-preview">
                        <h3 id="gift-preview-title"><?php esc_html_e( '🎁 Choose your gift!', 'incentiv' ); ?></h3>
                        <p id="gift-preview-subtitle"><?php esc_html_e( 'Buy this product and get a gift on a product of your choice:', 'incentiv' ); ?></p>
                        <div class="preview-product">
                            <span class="preview-product-info"><span class="preview-thumb"><span class="msr">redeem</span></span> <?php esc_html_e( 'Thermal jug 500 ml', 'incentiv' ); ?></span>
                            <button class="preview-button"><?php esc_html_e( 'Choose', 'incentiv' ); ?></button>
                        </div>
                        <div class="preview-product">
                            <span class="preview-product-info"><span class="preview-thumb"><span class="msr">redeem</span></span> <?php esc_html_e( 'Wooden coaster set', 'incentiv' ); ?></span>
                            <button class="preview-button"><?php esc_html_e( 'Choose', 'incentiv' ); ?></button>
                        </div>
                    </div>

                    <div class="mmd-preview-stage-label"><?php esc_html_e( 'Cart · order note', 'incentiv' ); ?></div>
                    <div class="mmd-cart-note-preview" id="cart-note-preview">
                        <label><?php esc_html_e( 'Order note', 'incentiv' ); ?></label>
                        <textarea rows="3" placeholder="<?php esc_attr_e( 'Add a special note to your order...', 'incentiv' ); ?>"></textarea>
                    </div>
                </div>

                <div class="mmd-styling-actions-sticky">
                    <button type="button" class="button button-primary button-large" id="save-styling"><?php esc_html_e( 'Save design', 'incentiv' ); ?></button>
                    <button type="button" class="button button-large" id="reset-styling"><?php esc_html_e( 'Reset', 'incentiv' ); ?></button>
                </div>
            </div>
        </aside>

    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Initialize color pickers
    $('.mmd-color-picker').wpColorPicker({
        change: updatePreviews,
        clear: updatePreviews
    });

    // Update previews on any control change
    $('.mmd-styling-controls').on('input change', 'input, select', updatePreviews);

    // Initial preview update
    updatePreviews();

    function updatePreviews() {
        // ---- Gift box ----
        var giftBox = $('#gift-box-preview');
        var bgColor1 = $('#gift_box_bg_color_1').val();
        var bgColor2 = $('#gift_box_bg_color_2').val();
        var textColor = $('#gift_box_text_color').val();
        var titleSize = $('#gift_box_title_size').val() || 20;
        var subtitleSize = $('#gift_box_subtitle_size').val() || 14;
        var borderRadius = $('#gift_box_border_radius').val();
        var padding = $('#gift_box_padding').val();
        var margin = $('#gift_box_margin').val() || 0;
        var borderWidth = $('#gift_box_border_width').val() || 0;
        var borderColor = $('#gift_box_border_color').val() || '#ffffff';
        var shadow = $('#gift_box_shadow').val() || 'none';
        var buttonBg = $('#gift_button_bg_color').val() || '#ffffff';
        var buttonText = $('#gift_button_text_color').val() || '#667eea';

        var boxShadow = 'none';
        if (shadow === 'small') { boxShadow = '0 2px 4px rgba(0,0,0,0.1)'; }
        else if (shadow === 'medium') { boxShadow = '0 4px 8px rgba(0,0,0,0.15)'; }
        else if (shadow === 'large') { boxShadow = '0 8px 16px rgba(0,0,0,0.2)'; }

        giftBox.css({
            'background': 'linear-gradient(135deg, ' + bgColor1 + ' 0%, ' + bgColor2 + ' 100%)',
            'color': textColor,
            'border-radius': borderRadius + 'px',
            'padding': padding + 'px',
            'margin': margin + 'px 0',
            'border': borderWidth + 'px solid ' + borderColor,
            'box-shadow': boxShadow
        });

        // Live text
        var titleText = $('#gift_box_title_text').val() || <?php echo wp_json_encode( __( '🎁 Choose your gift!', 'incentiv' ) ); ?>;
        var subtitleText = ($('#gift_box_subtitle_text').val() || '')
            .replace(/\{gift_type\}/g, <?php echo wp_json_encode( __( 'Gift', 'incentiv' ) ); ?>)
            .replace(/\{selection_type\}/g, <?php echo wp_json_encode( __( 'of your choice', 'incentiv' ) ); ?>);
        giftBox.find('#gift-preview-title').text(titleText).css('font-size', titleSize + 'px');
        giftBox.find('#gift-preview-subtitle').text(subtitleText).css('font-size', subtitleSize + 'px');
        giftBox.find('.preview-button').css({
            'background-color': buttonBg,
            'color': buttonText
        });

        // ---- Cart note ----
        var noteArea = $('#cart-note-preview textarea');
        var noteLabel = $('#cart-note-preview label');
        var noteBg = $('#cart_note_bg_color').val();
        var noteText = $('#cart_note_text_color').val();
        var labelSize = $('#cart_note_label_size').val() || 16;
        var inputSize = $('#cart_note_input_size').val() || 14;
        var noteBorder = $('#cart_note_border_color').val();
        var noteBorderWidth = $('#cart_note_border_width').val() || 1;
        var noteRadius = $('#cart_note_border_radius').val();
        var notePadding = $('#cart_note_padding').val() || 10;
        var placeholderColor = $('#cart_note_placeholder_color').val() || '#999999';
        var focusBorder = $('#cart_note_focus_border').val() || '#667eea';

        noteArea.css({
            'background-color': noteBg,
            'color': noteText,
            'border-color': noteBorder,
            'border-width': noteBorderWidth + 'px',
            'border-style': 'solid',
            'border-radius': noteRadius + 'px',
            'padding': notePadding + 'px',
            'font-size': inputSize + 'px'
        });
        noteLabel.css({ 'color': noteText, 'font-size': labelSize + 'px' });

        // Placeholder + focus colors (injected styles)
        $('#mmd-placeholder-style').remove();
        $('<style id="mmd-placeholder-style">#cart-note-preview textarea::placeholder { color: ' + placeholderColor + '; }</style>').appendTo('head');
        $('#mmd-focus-style').remove();
        $('<style id="mmd-focus-style">#cart-note-preview textarea:focus { border-color: ' + focusBorder + '; outline: none; }</style>').appendTo('head');
    }

    // Save styling
    $('#save-styling').on('click', function() {
        var button = $(this);
        button.prop('disabled', true).text(<?php echo wp_json_encode( __( 'Saving...', 'incentiv' ) ); ?>);

        var stylingData = {
            gift_box_bg_color_1: $('#gift_box_bg_color_1').val(),
            gift_box_bg_color_2: $('#gift_box_bg_color_2').val(),
            gift_box_text_color: $('#gift_box_text_color').val(),
            gift_box_title_size: $('#gift_box_title_size').val(),
            gift_box_subtitle_size: $('#gift_box_subtitle_size').val(),
            gift_box_border_radius: $('#gift_box_border_radius').val(),
            gift_box_padding: $('#gift_box_padding').val(),
            gift_box_margin: $('#gift_box_margin').val(),
            gift_box_border_width: $('#gift_box_border_width').val(),
            gift_box_border_color: $('#gift_box_border_color').val(),
            gift_box_shadow: $('#gift_box_shadow').val(),
            gift_box_title_text: $('#gift_box_title_text').val(),
            gift_box_subtitle_text: $('#gift_box_subtitle_text').val(),
            gift_badge_text: $('#gift_badge_text').val(),
            gift_display_hook: $('#gift_display_hook').val(),
            gift_button_bg_color: $('#gift_button_bg_color').val(),
            gift_button_text_color: $('#gift_button_text_color').val(),
            gift_button_hover_bg: $('#gift_button_hover_bg').val(),
            gift_button_hover_text: $('#gift_button_hover_text').val(),
            cart_note_bg_color: $('#cart_note_bg_color').val(),
            cart_note_text_color: $('#cart_note_text_color').val(),
            cart_note_label_size: $('#cart_note_label_size').val(),
            cart_note_input_size: $('#cart_note_input_size').val(),
            cart_note_border_color: $('#cart_note_border_color').val(),
            cart_note_border_width: $('#cart_note_border_width').val(),
            cart_note_border_radius: $('#cart_note_border_radius').val(),
            cart_note_padding: $('#cart_note_padding').val(),
            cart_note_placeholder_color: $('#cart_note_placeholder_color').val(),
            cart_note_focus_border: $('#cart_note_focus_border').val(),
            debug_mode: $('#debug_mode').is(':checked') ? 'yes' : 'no'
        };

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mmd_save_gift_styling',
                nonce: mmdGiftRulesAjax.nonce,
                styling: JSON.stringify(stylingData)
            },
            success: function(response) {
                if (response.success) {
                    button.text(<?php echo wp_json_encode( __( '✓ Saved!', 'incentiv' ) ); ?>);
                    setTimeout(function() {
                        button.text(<?php echo wp_json_encode( __( 'Save design', 'incentiv' ) ); ?>).prop('disabled', false);
                    }, 2000);
                } else {
                    alert(( <?php echo wp_json_encode( __( 'Error:', 'incentiv' ) ); ?> + ' ' ) + response.data);
                    button.text(<?php echo wp_json_encode( __( 'Save design', 'incentiv' ) ); ?>).prop('disabled', false);
                }
            },
            error: function() {
                alert(<?php echo wp_json_encode( __( 'Network error while saving', 'incentiv' ) ); ?>);
                button.text(<?php echo wp_json_encode( __( 'Save design', 'incentiv' ) ); ?>).prop('disabled', false);
            }
        });
    });

    // Reset styling
    $('#reset-styling').on('click', function() {
        if (!confirm(<?php echo wp_json_encode( __( 'Reset all settings to the defaults?', 'incentiv' ) ); ?>)) { return; }

        $('#gift_box_bg_color_1').val('#667eea').wpColorPicker('color', '#667eea');
        $('#gift_box_bg_color_2').val('#764ba2').wpColorPicker('color', '#764ba2');
        $('#gift_box_text_color').val('#ffffff').wpColorPicker('color', '#ffffff');
        $('#gift_box_title_size').val('20');
        $('#gift_box_subtitle_size').val('14');
        $('#gift_box_border_radius').val('8');
        $('#gift_box_padding').val('20');
        $('#gift_box_margin').val('20');
        $('#gift_box_border_width').val('0');
        $('#gift_box_border_color').val('#ffffff').wpColorPicker('color', '#ffffff');
        $('#gift_box_shadow').val('none');
        $('#gift_button_bg_color').val('#ffffff').wpColorPicker('color', '#ffffff');
        $('#gift_button_text_color').val('#667eea').wpColorPicker('color', '#667eea');
        $('#gift_button_hover_bg').val('#667eea').wpColorPicker('color', '#667eea');
        $('#gift_button_hover_text').val('#ffffff').wpColorPicker('color', '#ffffff');

        $('#cart_note_bg_color').val('#f8f9fa').wpColorPicker('color', '#f8f9fa');
        $('#cart_note_text_color').val('#333333').wpColorPicker('color', '#333333');
        $('#cart_note_label_size').val('16');
        $('#cart_note_input_size').val('14');
        $('#cart_note_border_color').val('#dee2e6').wpColorPicker('color', '#dee2e6');
        $('#cart_note_border_width').val('1');
        $('#cart_note_border_radius').val('4');
        $('#cart_note_padding').val('10');
        $('#cart_note_placeholder_color').val('#999999').wpColorPicker('color', '#999999');
        $('#cart_note_focus_border').val('#667eea').wpColorPicker('color', '#667eea');

        $('#debug_mode').prop('checked', false);

        updatePreviews();
    });
});
</script>
