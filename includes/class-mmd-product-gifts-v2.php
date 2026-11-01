<?php
/**
 * Product Add-on Gifts/Discounts V2 - With User Selection
 * Allows customers to choose their free gift or discounted product
 */

if (!defined('ABSPATH')) {
    exit;
}

class MMD_Product_Gifts_V2 {

    private static $instance = null;

    /**
     * Keep only gift products a customer can get now: existing and in stock.
     * A variable product counts as in stock while any variation is.
     *
     * @param array $product_ids Gift product or variation ids.
     * @return array
     */
    public static function filter_in_stock_gift_products($product_ids) {
        $in_stock = array();
        foreach ((array) $product_ids as $product_id) {
            $product = wc_get_product($product_id);
            if ($product && $product->is_in_stock()) {
                $in_stock[] = $product_id;
            }
        }
        return array_values($in_stock);
    }

    /**
     * Gift products a product's own gift settings offer now (in stock only).
     * Used by the Elementor Gift notice widget, so it lists the same gifts the pickers offer.
     *
     * @param int $product_id Product id.
     * @return array
     */
    public static function get_notice_gift_products($product_id) {
        $gift_products = get_post_meta($product_id, '_mmd_gift_products', true);
        if (empty($gift_products)) {
            return array();
        }
        return self::filter_in_stock_gift_products($gift_products);
    }

    /**
     * Whether gift debug mode is on (MMD_DEBUG or the gift styling setting).
     */
    private static function is_debug_enabled() {
        if (defined('MMD_DEBUG') && MMD_DEBUG) {
            return true;
        }
        $styling = get_option('mmd_gift_styling', array());
        return !empty($styling['debug_mode']) && $styling['debug_mode'] === 'yes';
    }

    /**
     * Log debug message only when debug mode is enabled
     */
    private static function debug_log($message) {
        if (defined('MMD_DEBUG') && MMD_DEBUG) {
            error_log($message);
            return;
        }
        $styling = get_option('mmd_gift_styling', array());
        if (!empty($styling['debug_mode']) && $styling['debug_mode'] === 'yes') {
            error_log($message);
        }
    }

    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        // Add meta box to product pages
        add_action('add_meta_boxes', array($this, 'add_gift_meta_box'));

        // Save meta box data
        add_action('save_post_product', array($this, 'save_gift_meta_box'), 10, 2);

        // Enqueue admin scripts
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));

        // Frontend hooks
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
        // Display gift selection on product page - configurable hook location
        $gift_display_hook = 'woocommerce_after_add_to_cart_button';
        $gift_display_priority = 10;
        if (class_exists('MMD_Gift_Rules')) {
            $styling = MMD_Gift_Rules::get_instance()->get_styling_options();
            $hook_setting = isset($styling['gift_display_hook']) ? $styling['gift_display_hook'] : 'after_add_to_cart';
            switch ($hook_setting) {
                case 'before_add_to_cart':
                    $gift_display_hook = 'woocommerce_before_add_to_cart_button';
                    break;
                case 'before_summary':
                    $gift_display_hook = 'woocommerce_single_product_summary';
                    $gift_display_priority = 25;
                    break;
                case 'after_summary':
                    $gift_display_hook = 'woocommerce_single_product_summary';
                    $gift_display_priority = 60;
                    break;
                case 'after_product_meta':
                    $gift_display_hook = 'woocommerce_product_meta_end';
                    break;
                case 'before_tabs':
                    $gift_display_hook = 'woocommerce_after_single_product_summary';
                    $gift_display_priority = 5;
                    break;
                case 'elementor_widget_only':
                    // Only display via Elementor widget / unico hook, skip WC hooks
                    $gift_display_hook = '';
                    break;
                default:
                    $gift_display_hook = 'woocommerce_after_add_to_cart_button';
                    break;
            }
        }
        if ($gift_display_hook) {
            add_action($gift_display_hook, array($this, 'display_gift_selection'), $gift_display_priority);
        }
        // Also hook into Unico Elementor add-to-cart widget (displays after the add-to-cart button)
        add_action('unico_after_add_to_cart_form', array($this, 'display_gift_selection_for_widget'));
        add_action('woocommerce_add_to_cart', array($this, 'handle_gift_selection'), 10, 6);
        add_action('woocommerce_before_calculate_totals', array($this, 'apply_gift_pricing'), 20);

        // AJAX compatibility filters
        add_filter('woocommerce_add_to_cart_validation', array($this, 'capture_ajax_gift_data'), 10, 3);
        add_filter('woocommerce_add_cart_item_data', array($this, 'preserve_gift_data_in_session'), 10, 3);

        // Display gift in cart
        add_filter('woocommerce_get_item_data', array($this, 'display_gift_in_cart'), 10, 2);
        add_filter('woocommerce_cart_item_name', array($this, 'display_gift_badge_in_cart'), 10, 3);
        add_filter('woocommerce_cart_item_remove_link', array($this, 'prevent_gift_removal'), 10, 2);
        
        // Lock gift quantity - Standard Cart & Checkout
        add_filter('woocommerce_cart_item_quantity', array($this, 'lock_gift_quantity'), 9999, 3);
        add_filter('woocommerce_checkout_cart_item_quantity', array($this, 'lock_gift_quantity_checkout'), 9999, 3);
        add_filter('woocommerce_widget_shopping_cart_item_quantity', array($this, 'lock_gift_quantity_checkout'), 9999, 3);
        
        // Add class to gift rows for CSS targeting
        add_filter('woocommerce_cart_item_class', array($this, 'add_gift_cart_item_class'), 10, 3);
        add_filter('woocommerce_mini_cart_item_class', array($this, 'add_gift_mini_cart_item_class'), 10, 3);
        
        add_action('woocommerce_cart_item_removed', array($this, 'remove_associated_gifts'), 10, 2);

        // AJAX handlers
        add_action('wp_ajax_mmd_get_gift_variations', array($this, 'ajax_get_variations'));
        add_action('wp_ajax_nopriv_mmd_get_gift_variations', array($this, 'ajax_get_variations'));
        add_action('wp_ajax_mmd_add_gift_to_cart', array($this, 'ajax_add_gift_to_cart'));
        add_action('wp_ajax_nopriv_mmd_add_gift_to_cart', array($this, 'ajax_add_gift_to_cart'));

        // Cart-based gift functionality
        add_action('woocommerce_before_cart', array($this, 'display_cart_gift_notice'));
        add_action('woocommerce_before_checkout_form', array($this, 'display_cart_gift_notice'));
        add_action('woocommerce_before_calculate_totals', array($this, 'apply_cart_based_gift_discounts'), 15);

        // Display gift notice on product page for cart-based gifts
        add_action('woocommerce_before_add_to_cart_form', array($this, 'display_product_page_gift_notice'));

        // Display gift notice in mini cart
        add_action('woocommerce_before_mini_cart', array($this, 'display_cart_gift_notice'));
        add_action('woocommerce_widget_shopping_cart_before_buttons', array($this, 'display_mini_cart_gift_notice'));

        // Cart item price display for cart-based gifts
        add_filter('woocommerce_cart_item_price', array($this, 'display_cart_based_gift_price'), 20, 3);
        add_filter('woocommerce_cart_item_subtotal', array($this, 'display_cart_based_gift_subtotal'), 20, 3);

        // Shortcode for gift selection (works on any page including custom Elementor pages)
        add_shortcode('mmd_gift_selection', array($this, 'gift_selection_shortcode'));

        // Inject gift display on Elementor-rendered product pages via wp_footer
        add_action('wp_footer', array($this, 'inject_gift_selection_elementor'), 5);

        // Cart/checkout interactive gift selection
        add_action('woocommerce_before_cart', array($this, 'display_cart_gift_selection'), 5);
        add_action('woocommerce_before_checkout_form', array($this, 'display_cart_gift_selection'), 3);
        add_action('mmd_render_cart_gift_selection', array($this, 'display_cart_gift_selection'));

        // AJAX handler for adding cart-based gift selection
        add_action('wp_ajax_mmd_add_cart_gift', array($this, 'ajax_add_cart_gift'));
        add_action('wp_ajax_nopriv_mmd_add_cart_gift', array($this, 'ajax_add_cart_gift'));
        add_action('wp_ajax_mmd_switch_cart_gift', array($this, 'ajax_switch_cart_gift'));
        add_action('wp_ajax_nopriv_mmd_switch_cart_gift', array($this, 'ajax_switch_cart_gift'));

        // Gift promo notices in global notice system
        add_action('wp_footer', array($this, 'display_gift_promo_notices'), 15);

        // Combined discount + gift rules
        add_action('woocommerce_before_calculate_totals', array($this, 'apply_combined_discount_rules'), 25);

        // Display combined discount notice on product pages
        add_action('woocommerce_single_product_summary', array($this, 'display_combined_discount_notice'), 25);
        add_action('woocommerce_before_cart', array($this, 'display_cart_combined_discount_notice'), 8);
        add_action('woocommerce_before_checkout_form', array($this, 'display_cart_combined_discount_notice'), 8);
    }

    /**
     * Add meta box to product pages
     */
    public function add_gift_meta_box() {
        add_meta_box(
            'mmd_product_gifts_v2',
            __( '🎁 Gift and discounted product settings', 'incentiv' ),
            array($this, 'render_gift_meta_box'),
            'product',
            'normal',
            'high'
        );
    }

    /**
     * Render gift meta box with improved styling
     */
    public function render_gift_meta_box($post) {
        wp_nonce_field('mmd_gift_meta_box_v2', 'mmd_gift_meta_box_v2_nonce');

        $enabled = get_post_meta($post->ID, '_mmd_gift_enabled', true);
        $gift_products = get_post_meta($post->ID, '_mmd_gift_products', true);
        $gift_type = get_post_meta($post->ID, '_mmd_gift_type', true);
        $gift_discount = get_post_meta($post->ID, '_mmd_gift_discount', true);
        $gift_selection_type = get_post_meta($post->ID, '_mmd_gift_selection_type', true);
        $limit_variations = get_post_meta($post->ID, '_mmd_gift_limit_variations', true);
        $allowed_variations = get_post_meta($post->ID, '_mmd_gift_allowed_variations', true);

        // New fields for category-based gift selection
        $gift_source = get_post_meta($post->ID, '_mmd_gift_source', true);
        $gift_categories = get_post_meta($post->ID, '_mmd_gift_categories', true);
        $gift_excluded_categories = get_post_meta($post->ID, '_mmd_gift_excluded_categories', true);
        $gift_excluded_tags = get_post_meta($post->ID, '_mmd_gift_excluded_tags', true);
        $gift_excluded_products = get_post_meta($post->ID, '_mmd_gift_excluded_products', true);

        // Gift mode fields
        $gift_mode = get_post_meta($post->ID, '_mmd_gift_mode', true);
        $cart_gift_notice = get_post_meta($post->ID, '_mmd_cart_gift_notice', true);

        // Gift attribute filter fields
        $gift_attribute_slug = get_post_meta($post->ID, '_mmd_gift_attribute_slug', true);
        $gift_attribute_values = get_post_meta($post->ID, '_mmd_gift_attribute_values', true);

        $gift_products = !empty($gift_products) ? $gift_products : array();
        $gift_type = !empty($gift_type) ? $gift_type : 'free';
        $gift_discount = !empty($gift_discount) ? $gift_discount : 0;
        $gift_selection_type = !empty($gift_selection_type) ? $gift_selection_type : 'single';
        $allowed_variations = !empty($allowed_variations) ? $allowed_variations : array();

        // Defaults for new fields
        $gift_source = !empty($gift_source) ? $gift_source : 'specific';
        $gift_categories = !empty($gift_categories) ? $gift_categories : array();
        $gift_excluded_categories = !empty($gift_excluded_categories) ? $gift_excluded_categories : array();
        $gift_excluded_tags = !empty($gift_excluded_tags) ? $gift_excluded_tags : array();
        $gift_excluded_products = !empty($gift_excluded_products) ? $gift_excluded_products : array();
        $gift_mode = !empty($gift_mode) ? $gift_mode : 'product_page';
        $cart_gift_notice = !empty($cart_gift_notice) ? $cart_gift_notice : __( 'You have a gift! Add a product from the participating categories to get a discount', 'incentiv' );
        $gift_attribute_slug = !empty($gift_attribute_slug) ? $gift_attribute_slug : '';
        $gift_attribute_values = !empty($gift_attribute_values) ? $gift_attribute_values : array();

        // Get categories, tags and attributes for selection
        $categories = get_terms(array('taxonomy' => 'product_cat', 'hide_empty' => false));
        $tags = get_terms(array('taxonomy' => 'product_tag', 'hide_empty' => false));
        $attributes = wc_get_attribute_taxonomies();

        ?>
        <div class="mmd-gift-settings-v2">
            <style>
                .mmd-gift-settings-v2 { padding: 20px; background: #f9f9f9; border-radius: 8px; }
                .mmd-gift-field-v2 { margin-bottom: 25px; background: white; padding: 20px; border-radius: 5px; border: 1px solid #ddd; }
                .mmd-gift-field-v2 label.main-label { display: block; font-weight: bold; font-size: 14px; margin-bottom: 10px; color: #333; }
                .mmd-gift-field-v2 input[type="text"],
                .mmd-gift-field-v2 select.full-width { width: 100%; padding: 8px; font-size: 14px; border: 1px solid #ddd; border-radius: 4px; }
                .mmd-gift-field-v2 input[type="number"] { padding: 8px; font-size: 14px; border: 1px solid #ddd; border-radius: 4px; width: 200px; }
                .mmd-gift-field-v2 .description { margin: 8px 0 0 0; color: #666; font-size: 13px; line-height: 1.5; }
                .mmd-switch-v2 { position: relative; display: inline-block; width: 60px; height: 30px; margin-left: 10px; }
                .mmd-switch-v2 input { opacity: 0; width: 0; height: 0; }
                .mmd-switch-slider-v2 { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .4s; border-radius: 30px; }
                .mmd-switch-slider-v2:before { position: absolute; content: ""; height: 22px; width: 22px; left: 4px; bottom: 4px; background-color: white; transition: .4s; border-radius: 50%; }
                .mmd-switch-v2 input:checked + .mmd-switch-slider-v2 { background-color: #4CAF50; }
                .mmd-switch-v2 input:checked + .mmd-switch-slider-v2:before { transform: translateX(30px); }
                .mmd-gift-header { background: linear-gradient(135deg, #7c3aed 0%, #a855f7 100%); color: white; padding: 15px 20px; border-radius: 5px; margin-bottom: 20px; }
                .mmd-gift-header h3 { margin: 0; font-size: 16px; }
                .mmd-type-selector { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-top: 10px; }
                .mmd-type-option { padding: 15px; border: 2px solid #ddd; border-radius: 5px; text-align: center; cursor: pointer; transition: all 0.3s; }
                .mmd-type-option:hover { border-color: #7c3aed; }
                .mmd-type-option.selected { border-color: #7c3aed; background: #f4eeff; }
                .mmd-type-option input[type="radio"] { margin-bottom: 5px; }
            </style>

            <div class="mmd-gift-header">
                <h3><?php esc_html_e( '⚙️ Set up a gift or discounted product for this product', 'incentiv' ); ?></h3>
            </div>

            <div class="mmd-gift-field-v2">
                <label class="main-label">
                    <label class="mmd-switch-v2">
                        <input type="checkbox" name="mmd_gift_enabled" value="1" <?php checked($enabled, '1'); ?>>
                        <span class="mmd-switch-slider-v2"></span>
                    </label>
                    <?php esc_html_e( 'Enable gifts/discounts for this product', 'incentiv' ); ?>
                </label>
                <p class="description"><?php esc_html_e( 'When a customer buys this product, they can choose a gift or a discounted product from the list you set', 'incentiv' ); ?></p>
            </div>

            <div class="mmd-gift-field-v2">
                <label class="main-label"><?php esc_html_e( 'Choose a benefit type', 'incentiv' ); ?></label>
                <div class="mmd-type-selector">
                    <div class="mmd-type-option <?php echo $gift_type === 'free' ? 'selected' : ''; ?>">
                        <input type="radio" name="mmd_gift_type" value="free" <?php checked($gift_type, 'free'); ?>>
                        <div style="font-size: 24px;">🎁</div>
                        <strong><?php esc_html_e( 'Free gift', 'incentiv' ); ?></strong>
                        <div style="font-size: 12px; color: #666;"><?php esc_html_e( 'The customer gets a product at no charge', 'incentiv' ); ?></div>
                    </div>
                    <div class="mmd-type-option <?php echo $gift_type === 'discount' ? 'selected' : ''; ?>">
                        <input type="radio" name="mmd_gift_type" value="discount" <?php checked($gift_type, 'discount'); ?>>
                        <div style="font-size: 24px;">💰</div>
                        <strong><?php esc_html_e( 'Percentage discount', 'incentiv' ); ?></strong>
                        <div style="font-size: 12px; color: #666;"><?php esc_html_e( 'A percentage off the product', 'incentiv' ); ?></div>
                    </div>
                    <div class="mmd-type-option <?php echo $gift_type === 'fixed_discount' ? 'selected' : ''; ?>">
                        <input type="radio" name="mmd_gift_type" value="fixed_discount" <?php checked($gift_type, 'fixed_discount'); ?>>
                        <div style="font-size: 24px;">🏷️</div>
                        <strong><?php esc_html_e( 'Fixed discount', 'incentiv' ); ?></strong>
                        <div style="font-size: 12px; color: #666;"><?php esc_html_e( 'A fixed amount in shekels', 'incentiv' ); ?></div>
                    </div>
                </div>
            </div>

            <div class="mmd-gift-field-v2" id="mmd_discount_field_v2" style="display: <?php echo ($gift_type !== 'free') ? 'block' : 'none'; ?>;">
                <label class="main-label" for="mmd_gift_discount"><?php esc_html_e( 'Discount rate', 'incentiv' ); ?></label>
                <input type="number" name="mmd_gift_discount" id="mmd_gift_discount" value="<?php echo esc_attr($gift_discount); ?>" min="0" step="0.01">
                <p class="description"><?php esc_html_e( 'Enter a discount percentage (50 = 50%) or a fixed amount in shekels', 'incentiv' ); ?></p>
            </div>

            <!-- Gift Mode Selection -->
            <div class="mmd-gift-field-v2">
                <label class="main-label"><?php esc_html_e( 'How the gift is given', 'incentiv' ); ?></label>
                <div style="display: flex; gap: 20px; margin-top: 10px;">
                    <label style="display: flex; align-items: center; cursor: pointer; padding: 10px; border: 2px solid <?php echo $gift_mode === 'product_page' ? '#7c3aed' : '#ddd'; ?>; border-radius: 5px; flex: 1; background: <?php echo $gift_mode === 'product_page' ? '#f4eeff' : 'white'; ?>;">
                        <input type="radio" name="mmd_gift_mode" value="product_page" <?php checked($gift_mode, 'product_page'); ?> style="margin-left: 8px;">
                        <div>
                            <strong><?php esc_html_e( 'On the product page', 'incentiv' ); ?></strong>
                            <div style="font-size: 12px; color: #666;"><?php esc_html_e( 'The customer chooses a gift on the product page', 'incentiv' ); ?></div>
                        </div>
                    </label>
                    <label style="display: flex; align-items: center; cursor: pointer; padding: 10px; border: 2px solid <?php echo $gift_mode === 'cart_based' ? '#7c3aed' : '#ddd'; ?>; border-radius: 5px; flex: 1; background: <?php echo $gift_mode === 'cart_based' ? '#f4eeff' : 'white'; ?>;">
                        <input type="radio" name="mmd_gift_mode" value="cart_based" <?php checked($gift_mode, 'cart_based'); ?> style="margin-left: 8px;">
                        <div>
                            <strong><?php esc_html_e( 'Gift in the cart', 'incentiv' ); ?></strong>
                            <div style="font-size: 12px; color: #666;"><?php esc_html_e( 'The customer adds a product from the store and the discount is applied automatically', 'incentiv' ); ?></div>
                        </div>
                    </label>
                </div>
            </div>

            <!-- Cart Gift Notice -->
            <div class="mmd-gift-field-v2" id="mmd_cart_gift_notice_field" style="display: <?php echo $gift_mode === 'cart_based' ? 'block' : 'none'; ?>;">
                <label class="main-label" for="mmd_cart_gift_notice"><?php esc_html_e( 'Cart message', 'incentiv' ); ?></label>
                <input type="text" name="mmd_cart_gift_notice" id="mmd_cart_gift_notice" value="<?php echo esc_attr($cart_gift_notice); ?>" style="width: 100%;">
                <p class="description"><?php esc_html_e( 'A message shown to the customer in the cart when they qualify for a gift', 'incentiv' ); ?></p>
            </div>

            <!-- Gift Source Selection -->
            <div class="mmd-gift-field-v2">
                <label class="main-label"><?php esc_html_e( 'Gift product source', 'incentiv' ); ?></label>
                <div style="display: flex; gap: 20px; margin-top: 10px;">
                    <label style="display: flex; align-items: center; cursor: pointer; padding: 10px; border: 2px solid <?php echo $gift_source === 'specific' ? '#7c3aed' : '#ddd'; ?>; border-radius: 5px; flex: 1; background: <?php echo $gift_source === 'specific' ? '#f4eeff' : 'white'; ?>;">
                        <input type="radio" name="mmd_gift_source" value="specific" <?php checked($gift_source, 'specific'); ?> style="margin-left: 8px;">
                        <div>
                            <strong><?php esc_html_e( 'Specific products', 'incentiv' ); ?></strong>
                            <div style="font-size: 12px; color: #666;"><?php esc_html_e( 'Choose specific products as gifts', 'incentiv' ); ?></div>
                        </div>
                    </label>
                    <label style="display: flex; align-items: center; cursor: pointer; padding: 10px; border: 2px solid <?php echo $gift_source === 'category' ? '#7c3aed' : '#ddd'; ?>; border-radius: 5px; flex: 1; background: <?php echo $gift_source === 'category' ? '#f4eeff' : 'white'; ?>;">
                        <input type="radio" name="mmd_gift_source" value="category" <?php checked($gift_source, 'category'); ?> style="margin-left: 8px;">
                        <div>
                            <strong><?php esc_html_e( 'By categories', 'incentiv' ); ?></strong>
                            <div style="font-size: 12px; color: #666;"><?php esc_html_e( 'The customer chooses from categories', 'incentiv' ); ?></div>
                        </div>
                    </label>
                </div>
            </div>

            <!-- Specific Products Selection -->
            <div class="mmd-gift-field-v2" id="mmd_gift_products_field" style="display: <?php echo $gift_source === 'specific' ? 'block' : 'none'; ?>;">
                <label class="main-label" for="mmd_gift_products"><?php esc_html_e( 'Choose products available as a gift/discount', 'incentiv' ); ?></label>
                <select name="mmd_gift_products[]" id="mmd_gift_products" class="mmd-product-search" multiple style="width: 100%;">
                    <?php
                    if (!empty($gift_products)) {
                        foreach ($gift_products as $product_id) {
                            $product = wc_get_product($product_id);
                            if ($product) {
                                echo '<option value="' . esc_attr($product_id) . '" selected>' . esc_html($product->get_name()) . ' (#' . esc_html( $product_id ) . ')</option>';
                            }
                        }
                    }
                    ?>
                </select>
                <p class="description"><?php esc_html_e( 'For variable products, the customer chooses the variation they want.', 'incentiv' ); ?></p>
            </div>

            <!-- Category-based Gift Selection -->
            <div class="mmd-gift-field-v2" id="mmd_gift_categories_field" style="display: <?php echo $gift_source === 'category' ? 'block' : 'none'; ?>;">
                <label class="main-label"><?php esc_html_e( '🏷️ Gift categories', 'incentiv' ); ?></label>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 8px; max-height: 200px; overflow-y: auto; padding: 10px; border: 1px solid #ddd; border-radius: 5px; background: #fafafa;">
                    <?php if (!empty($categories) && !is_wp_error($categories)): ?>
                        <?php foreach ($categories as $category): ?>
                            <label style="display: flex; align-items: center; gap: 5px;">
                                <input type="checkbox" name="mmd_gift_categories[]" value="<?php echo esc_attr($category->term_id); ?>" <?php checked(in_array($category->term_id, $gift_categories)); ?>>
                                <?php echo esc_html($category->name); ?> (<?php echo esc_html( $category->count ); ?>)
                            </label>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="description"><?php esc_html_e( 'No product categories available', 'incentiv' ); ?></p>
                    <?php endif; ?>
                </div>
                <p class="description"><?php esc_html_e( 'The customer can choose one product from all products in these categories', 'incentiv' ); ?></p>

                <!-- Attribute Filter -->
                <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #e0e0e0;">
                    <strong style="display: block; margin-bottom: 8px;"><?php esc_html_e( '🔍 Filter by attribute (optional):', 'incentiv' ); ?></strong>
                    <select name="mmd_gift_attribute_slug" id="mmd_gift_attribute_slug" style="width: 100%; max-width: 300px;">
                        <option value=""><?php esc_html_e( '-- No attribute filter --', 'incentiv' ); ?></option>
                        <?php foreach ($attributes as $attribute): ?>
                            <option value="<?php echo esc_attr('pa_' . $attribute->attribute_name); ?>" <?php selected($gift_attribute_slug, 'pa_' . $attribute->attribute_name); ?>>
                                <?php echo esc_html($attribute->attribute_label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description"><?php esc_html_e( 'Choose an attribute to filter the gift products by (for example: weight, size)', 'incentiv' ); ?></p>
                </div>

                <div id="mmd_gift_attribute_values_field" style="margin-top: 10px; display: <?php echo !empty($gift_attribute_slug) ? 'block' : 'none'; ?>;">
                    <strong style="display: block; margin-bottom: 8px;"><?php esc_html_e( 'Attribute values for gifts:', 'incentiv' ); ?></strong>
                    <div id="mmd_gift_attribute_values_list" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 8px; max-height: 150px; overflow-y: auto; padding: 10px; border: 1px solid #ddd; border-radius: 5px; background: white;">
                        <!-- Dynamically loaded via JavaScript -->
                    </div>
                    <p class="description"><?php esc_html_e( 'Choose which attribute values are available as gifts (for example: only 100 g)', 'incentiv' ); ?></p>
                </div>
            </div>

            <!-- Gift Exclusions Section -->
            <div class="mmd-gift-field-v2" id="mmd_gift_exclusions_field" style="display: <?php echo $gift_source === 'category' ? 'block' : 'none'; ?>; background: #fff5f5; border: 1px solid #ffcccc;">
                <label class="main-label"><?php esc_html_e( '🚫 Exclude products from gifts', 'incentiv' ); ?></label>
                <p class="description" style="margin-bottom: 15px;"><?php esc_html_e( 'Set which products are not available as gifts even though they are in the selected categories', 'incentiv' ); ?></p>

                <div style="margin-bottom: 15px;">
                    <strong style="display: block; margin-bottom: 8px;"><?php esc_html_e( 'Exclude categories:', 'incentiv' ); ?></strong>
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 8px; max-height: 150px; overflow-y: auto; padding: 10px; border: 1px solid #ddd; border-radius: 5px; background: white;">
                        <?php if (!empty($categories) && !is_wp_error($categories)): ?>
                            <?php foreach ($categories as $category): ?>
                                <label style="display: flex; align-items: center; gap: 5px;">
                                    <input type="checkbox" name="mmd_gift_excluded_categories[]" value="<?php echo esc_attr($category->term_id); ?>" <?php checked(in_array($category->term_id, $gift_excluded_categories)); ?>>
                                    <?php echo esc_html($category->name); ?>
                                </label>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div style="margin-bottom: 15px;">
                    <strong style="display: block; margin-bottom: 8px;"><?php esc_html_e( 'Exclude tags:', 'incentiv' ); ?></strong>
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 8px; max-height: 150px; overflow-y: auto; padding: 10px; border: 1px solid #ddd; border-radius: 5px; background: white;">
                        <?php if (!empty($tags) && !is_wp_error($tags)): ?>
                            <?php foreach ($tags as $tag): ?>
                                <label style="display: flex; align-items: center; gap: 5px;">
                                    <input type="checkbox" name="mmd_gift_excluded_tags[]" value="<?php echo esc_attr($tag->term_id); ?>" <?php checked(in_array($tag->term_id, $gift_excluded_tags)); ?>>
                                    <?php echo esc_html($tag->name); ?>
                                </label>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="description"><?php esc_html_e( 'No product tags available', 'incentiv' ); ?></p>
                        <?php endif; ?>
                    </div>
                </div>

                <div>
                    <strong style="display: block; margin-bottom: 8px;"><?php esc_html_e( 'Exclude specific products:', 'incentiv' ); ?></strong>
                    <select name="mmd_gift_excluded_products[]" id="mmd_gift_excluded_products" class="mmd-product-search-excluded" multiple style="width: 100%;">
                        <?php
                        if (!empty($gift_excluded_products)) {
                            foreach ($gift_excluded_products as $product_id) {
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
                </div>
            </div>

            <div class="mmd-gift-field-v2">
                <label class="main-label"><?php esc_html_e( 'Selection type', 'incentiv' ); ?></label>
                <div style="display: flex; gap: 20px; margin-top: 10px;">
                    <label style="display: flex; align-items: center; cursor: pointer; padding: 10px; border: 2px solid <?php echo $gift_selection_type === 'single' ? '#7c3aed' : '#ddd'; ?>; border-radius: 5px; flex: 1; background: <?php echo $gift_selection_type === 'single' ? '#f4eeff' : 'white'; ?>;">
                        <input type="radio" name="mmd_gift_selection_type" value="single" <?php checked($gift_selection_type, 'single'); ?> style="margin-left: 8px;">
                        <div>
                            <strong><?php esc_html_e( 'Single product selection', 'incentiv' ); ?></strong>
                            <div style="font-size: 12px; color: #666;"><?php esc_html_e( 'The customer can choose only one product', 'incentiv' ); ?></div>
                        </div>
                    </label>
                    <label style="display: flex; align-items: center; cursor: pointer; padding: 10px; border: 2px solid <?php echo $gift_selection_type === 'multiple' ? '#7c3aed' : '#ddd'; ?>; border-radius: 5px; flex: 1; background: <?php echo $gift_selection_type === 'multiple' ? '#f4eeff' : 'white'; ?>;">
                        <input type="radio" name="mmd_gift_selection_type" value="multiple" <?php checked($gift_selection_type, 'multiple'); ?> style="margin-left: 8px;">
                        <div>
                            <strong><?php esc_html_e( 'Multiple selection', 'incentiv' ); ?></strong>
                            <div style="font-size: 12px; color: #666;"><?php esc_html_e( 'The customer can choose several products', 'incentiv' ); ?></div>
                        </div>
                    </label>
                </div>
                <p class="description" style="margin-top: 10px;"><?php esc_html_e( 'Choose whether the customer can pick only one product or several products from the list', 'incentiv' ); ?></p>
            </div>

            <div class="mmd-gift-field-v2">
                <label class="main-label">
                    <input type="checkbox" name="mmd_gift_allow_quantity" value="yes" <?php checked(get_post_meta($post->ID, '_mmd_gift_allow_quantity', true), 'yes'); ?> id="mmd_allow_quantity_v2">
                    <?php esc_html_e( 'Allow quantity selection', 'incentiv' ); ?>
                </label>
                <p class="description"><?php esc_html_e( 'When enabled, the customer can choose a quantity for each gift product', 'incentiv' ); ?></p>
            </div>

            <div class="mmd-gift-field-v2" id="mmd_max_quantity_field_v2" style="display: <?php echo (get_post_meta($post->ID, '_mmd_gift_allow_quantity', true) === 'yes') ? 'block' : 'none'; ?>;">
                <label class="main-label" for="mmd_gift_max_quantity"><?php esc_html_e( 'Maximum quantity', 'incentiv' ); ?></label>
                <input type="number" name="mmd_gift_max_quantity" id="mmd_gift_max_quantity" value="<?php echo esc_attr(get_post_meta($post->ID, '_mmd_gift_max_quantity', true) ?: '5'); ?>" min="1" max="100" step="1">
                <p class="description"><?php esc_html_e( 'The maximum quantity the customer can choose for each gift product (1-100)', 'incentiv' ); ?></p>
            </div>

            <div class="mmd-gift-field-v2">
                <label class="main-label">
                    <input type="checkbox" name="mmd_gift_limit_variations" value="1" <?php checked($limit_variations, '1'); ?> id="mmd_limit_variations_v2">
                    <?php esc_html_e( 'Limit to specific variations of the gift products', 'incentiv' ); ?>
                </label>
                <p class="description"><?php esc_html_e( 'Tick this option to choose manually which variations are available as gifts. If unticked, all variations are available.', 'incentiv' ); ?></p>
            </div>

            <div class="mmd-gift-field-v2" id="mmd_variations_container_v2" style="display: <?php echo ($limit_variations == '1') ? 'block' : 'none'; ?>; background: #f0f0f1; border: 2px dashed #999;">
                <h4 style="margin-top: 0;"><?php esc_html_e( '🎯 Choose allowed variations', 'incentiv' ); ?></h4>
                <div id="mmd_variations_list_v2">
                    <p class="description" style="color: #666;"><?php esc_html_e( 'Select a variable product above to show its variations', 'incentiv' ); ?></p>
                </div>
            </div>

            <div style="background: #e7f3ff; padding: 15px; border-right: 4px solid #2196F3; border-radius: 4px;">
                <strong><?php esc_html_e( '💡 How does it work?', 'incentiv' ); ?></strong>
                <ul style="margin: 10px 0 0 20px; line-height: 1.8;">
                    <li><?php esc_html_e( 'The customer sees the available products on the product page', 'incentiv' ); ?></li>
                    <li><?php esc_html_e( 'They choose one product, and if it is variable, the variation they want', 'incentiv' ); ?></li>
                    <li><?php esc_html_e( 'The chosen product is added to the cart automatically with the configured discount', 'incentiv' ); ?></li>
                    <li><?php esc_html_e( 'The gift is shown in the cart, the mini cart and the checkout page', 'incentiv' ); ?></li>
                </ul>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            // Toggle discount field based on type
            $('input[name="mmd_gift_type"]').on('change', function() {
                if ($(this).val() === 'free') {
                    $('#mmd_discount_field_v2').hide();
                } else {
                    $('#mmd_discount_field_v2').show();
                }
            });

            // Visual feedback for type selection
            $('.mmd-type-option').on('click', function() {
                $('.mmd-type-option').removeClass('selected');
                $(this).addClass('selected');
                $(this).find('input[type="radio"]').prop('checked', true).trigger('change');
            });

            // Gift mode toggle (product_page vs cart_based)
            $('input[name="mmd_gift_mode"]').on('change', function() {
                var mode = $(this).val();
                // Update visual styling
                $('input[name="mmd_gift_mode"]').each(function() {
                    const label = $(this).closest('label');
                    if ($(this).is(':checked')) {
                        label.css({'border-color': '#7c3aed', 'background': '#f4eeff'});
                    } else {
                        label.css({'border-color': '#ddd', 'background': 'white'});
                    }
                });
                // Toggle visibility
                if (mode === 'cart_based') {
                    $('#mmd_cart_gift_notice_field').show();
                } else {
                    $('#mmd_cart_gift_notice_field').hide();
                }
            });

            // Gift source toggle (specific vs category)
            $('input[name="mmd_gift_source"]').on('change', function() {
                var source = $(this).val();
                // Update visual styling
                $('input[name="mmd_gift_source"]').each(function() {
                    const label = $(this).closest('label');
                    if ($(this).is(':checked')) {
                        label.css({'border-color': '#7c3aed', 'background': '#f4eeff'});
                    } else {
                        label.css({'border-color': '#ddd', 'background': 'white'});
                    }
                });
                // Toggle visibility
                if (source === 'specific') {
                    $('#mmd_gift_products_field').show();
                    $('#mmd_gift_categories_field').hide();
                    $('#mmd_gift_exclusions_field').hide();
                } else {
                    $('#mmd_gift_products_field').hide();
                    $('#mmd_gift_categories_field').show();
                    $('#mmd_gift_exclusions_field').show();
                }
            });

            // Gift attribute filter
            var currentGiftAttributeValues = <?php echo json_encode($gift_attribute_values); ?>;

            $('#mmd_gift_attribute_slug').on('change', function() {
                var slug = $(this).val();
                if (slug) {
                    $('#mmd_gift_attribute_values_field').show();
                    loadGiftAttributeTerms(slug);
                } else {
                    $('#mmd_gift_attribute_values_field').hide();
                    $('#mmd_gift_attribute_values_list').html('');
                }
            });

            function loadGiftAttributeTerms(slug) {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'mmd_get_attribute_terms',
                        attribute_slug: slug,
                        nonce: '<?php echo esc_attr( wp_create_nonce('mmd_gift_rules_nonce') ); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            var terms = response.data;
                            var html = '';
                            terms.forEach(function(term) {
                                var checked = currentGiftAttributeValues.indexOf(term.term_id) !== -1 ? 'checked' : '';
                                html += '<label style="display: flex; align-items: center; gap: 5px;">';
                                html += '<input type="checkbox" name="mmd_gift_attribute_values[]" value="' + term.term_id + '" ' + checked + '>';
                                html += term.name + '</label>';
                            });
                            $('#mmd_gift_attribute_values_list').html(html);
                        }
                    }
                });
            }

            // Load gift attribute terms on page load if attribute is selected
            <?php if (!empty($gift_attribute_slug)): ?>
            loadGiftAttributeTerms('<?php echo esc_js($gift_attribute_slug); ?>');
            <?php endif; ?>

            // Initialize Select2 for excluded products (with variations support)
            $('.mmd-product-search-excluded').select2({
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
                placeholder: <?php echo wp_json_encode( __( 'Search products to exclude...', 'incentiv' ) ); ?>,
                width: '100%'
            });

            // Visual feedback for selection type
            $('input[name="mmd_gift_selection_type"]').on('change', function() {
                $('input[name="mmd_gift_selection_type"]').each(function() {
                    const label = $(this).closest('label');
                    if ($(this).is(':checked')) {
                        label.css({'border-color': '#7c3aed', 'background': '#f4eeff'});
                    } else {
                        label.css({'border-color': '#ddd', 'background': 'white'});
                    }
                });
            });

            // Toggle max quantity field
            $('#mmd_allow_quantity_v2').on('change', function() {
                if ($(this).is(':checked')) {
                    $('#mmd_max_quantity_field_v2').show();
                } else {
                    $('#mmd_max_quantity_field_v2').hide();
                }
            });

            // Toggle variations container
            $('#mmd_limit_variations_v2').on('change', function() {
                if ($(this).is(':checked')) {
                    $('#mmd_variations_container_v2').show();
                    loadVariations();
                } else {
                    $('#mmd_variations_container_v2').hide();
                }
            });

            // Load variations when products change
            $('#mmd_gift_products').on('change', function() {
                if ($('#mmd_limit_variations_v2').is(':checked')) {
                    loadVariations();
                }
            });

            function loadVariations() {
                var productIds = $('#mmd_gift_products').val();
                if (!productIds || productIds.length === 0) {
                    $('#mmd_variations_list_v2').html(( '<p class="description" style="color: #666;">' + <?php echo wp_json_encode( __( 'Select a variable product above to show its variations', 'incentiv' ) ); ?> + '<\/p>' ));
                    return;
                }

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'mmd_get_product_variations',
                        product_ids: productIds,
                        selected_variations: <?php echo json_encode($allowed_variations); ?>,
                        nonce: '<?php echo esc_attr( wp_create_nonce('mmd_variations_nonce') ); ?>'
                    },
                    beforeSend: function() {
                        $('#mmd_variations_list_v2').html(( '<p style="text-align: center;"><span class="spinner is-active" style="float: none; margin: 0;"><\/span> ' + <?php echo wp_json_encode( __( 'Loading variations...', 'incentiv' ) ); ?> + '<\/p>' ));
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#mmd_variations_list_v2').html(response.data.html);
                        } else {
                            $('#mmd_variations_list_v2').html(( '<p style="color: red;">' + <?php echo wp_json_encode( __( 'Error:', 'incentiv' ) ); ?> + ' ' ) + response.data + '</p>');
                        }
                    },
                    error: function() {
                        $('#mmd_variations_list_v2').html(( '<p style="color: red;">' + <?php echo wp_json_encode( __( 'Error loading variations', 'incentiv' ) ); ?> + '<\/p>' ));
                    }
                });
            }

            // Initialize if variations are already limited
            <?php if ($limit_variations == '1'): ?>
            loadVariations();
            <?php endif; ?>
        });
        </script>
        <?php
    }

    /**
     * Save meta box data
     */
    public function save_gift_meta_box($post_id, $post) {
        if (!isset($_POST['mmd_gift_meta_box_v2_nonce']) || !wp_verify_nonce($_POST['mmd_gift_meta_box_v2_nonce'], 'mmd_gift_meta_box_v2')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $enabled = isset($_POST['mmd_gift_enabled']) ? '1' : '0';
        update_post_meta($post_id, '_mmd_gift_enabled', $enabled);

        $gift_type = isset($_POST['mmd_gift_type']) ? sanitize_text_field($_POST['mmd_gift_type']) : 'free';
        update_post_meta($post_id, '_mmd_gift_type', $gift_type);

        $gift_discount = isset($_POST['mmd_gift_discount']) ? floatval($_POST['mmd_gift_discount']) : 0;
        update_post_meta($post_id, '_mmd_gift_discount', $gift_discount);

        // Gift mode (product_page or cart_based)
        $gift_mode = isset($_POST['mmd_gift_mode']) ? sanitize_text_field($_POST['mmd_gift_mode']) : 'product_page';
        update_post_meta($post_id, '_mmd_gift_mode', $gift_mode);

        // Cart gift notice
        $cart_gift_notice = isset($_POST['mmd_cart_gift_notice']) ? sanitize_text_field($_POST['mmd_cart_gift_notice']) : '';
        update_post_meta($post_id, '_mmd_cart_gift_notice', $cart_gift_notice);

        // Gift source (specific or category)
        $gift_source = isset($_POST['mmd_gift_source']) ? sanitize_text_field($_POST['mmd_gift_source']) : 'specific';
        update_post_meta($post_id, '_mmd_gift_source', $gift_source);

        $gift_products = isset($_POST['mmd_gift_products']) && is_array($_POST['mmd_gift_products'])
            ? array_map('intval', $_POST['mmd_gift_products'])
            : array();
        update_post_meta($post_id, '_mmd_gift_products', $gift_products);

        // Gift categories
        $gift_categories = isset($_POST['mmd_gift_categories']) && is_array($_POST['mmd_gift_categories'])
            ? array_map('intval', $_POST['mmd_gift_categories'])
            : array();
        update_post_meta($post_id, '_mmd_gift_categories', $gift_categories);

        // Gift exclusions
        $gift_excluded_categories = isset($_POST['mmd_gift_excluded_categories']) && is_array($_POST['mmd_gift_excluded_categories'])
            ? array_map('intval', $_POST['mmd_gift_excluded_categories'])
            : array();
        update_post_meta($post_id, '_mmd_gift_excluded_categories', $gift_excluded_categories);

        $gift_excluded_tags = isset($_POST['mmd_gift_excluded_tags']) && is_array($_POST['mmd_gift_excluded_tags'])
            ? array_map('intval', $_POST['mmd_gift_excluded_tags'])
            : array();
        update_post_meta($post_id, '_mmd_gift_excluded_tags', $gift_excluded_tags);

        $gift_excluded_products = isset($_POST['mmd_gift_excluded_products']) && is_array($_POST['mmd_gift_excluded_products'])
            ? array_map('intval', $_POST['mmd_gift_excluded_products'])
            : array();
        update_post_meta($post_id, '_mmd_gift_excluded_products', $gift_excluded_products);

        // Gift attribute filter
        $gift_attribute_slug = isset($_POST['mmd_gift_attribute_slug']) ? sanitize_text_field($_POST['mmd_gift_attribute_slug']) : '';
        update_post_meta($post_id, '_mmd_gift_attribute_slug', $gift_attribute_slug);

        $gift_attribute_values = isset($_POST['mmd_gift_attribute_values']) && is_array($_POST['mmd_gift_attribute_values'])
            ? array_map('intval', $_POST['mmd_gift_attribute_values'])
            : array();
        update_post_meta($post_id, '_mmd_gift_attribute_values', $gift_attribute_values);

        $gift_selection_type = isset($_POST['mmd_gift_selection_type']) ? sanitize_text_field($_POST['mmd_gift_selection_type']) : 'single';
        update_post_meta($post_id, '_mmd_gift_selection_type', $gift_selection_type);

        $allow_quantity = isset($_POST['mmd_gift_allow_quantity']) && $_POST['mmd_gift_allow_quantity'] === 'yes' ? 'yes' : 'no';
        update_post_meta($post_id, '_mmd_gift_allow_quantity', $allow_quantity);

        $max_quantity = isset($_POST['mmd_gift_max_quantity']) ? intval($_POST['mmd_gift_max_quantity']) : 5;
        update_post_meta($post_id, '_mmd_gift_max_quantity', $max_quantity);

        $limit_variations = isset($_POST['mmd_gift_limit_variations']) ? '1' : '0';
        update_post_meta($post_id, '_mmd_gift_limit_variations', $limit_variations);

        $allowed_variations = isset($_POST['mmd_allowed_variations']) && is_array($_POST['mmd_allowed_variations'])
            ? array_map('intval', $_POST['mmd_allowed_variations'])
            : array();
        update_post_meta($post_id, '_mmd_gift_allowed_variations', $allowed_variations);
    }

    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        global $post_type;

        if ($post_type !== 'product' || ($hook !== 'post.php' && $hook !== 'post-new.php')) {
            return;
        }

        wp_enqueue_script('select2', MMD_PLUGIN_URL . 'assets/vendor/select2/select2.min.js', array('jquery'), '4.1.0', true);
        wp_enqueue_style('select2', MMD_PLUGIN_URL . 'assets/vendor/select2/select2.min.css', array(), '4.1.0');

        wp_add_inline_script('select2', "
            jQuery(document).ready(function($) {
                $('.mmd-product-search').select2({
                    ajax: {
                        url: ajaxurl,
                        dataType: 'json',
                        delay: 250,
                        data: function(params) {
                            return {
                                action: 'mmd_search_products',
                                q: params.term,
                                nonce: '" . wp_create_nonce('mmd_search_products') . "'
                            };
                        },
                        processResults: function(data) {
                            return {
                                results: data.data
                            };
                        }
                    },
                    minimumInputLength: 2,
                    placeholder: " . wp_json_encode( __( 'Search products...', 'incentiv' ) ) . ",
                    width: '100%'
                });
            });
        ");
    }

    /**
     * Enqueue frontend scripts
     */
    public function enqueue_frontend_scripts() {
        // Load on product pages, cart, checkout, and any page with Elementor
        $is_elementor_page = false;
        if (class_exists('\Elementor\Plugin')) {
            $post_id = get_the_ID();
            if (!$post_id) {
                $queried = get_queried_object();
                if ($queried && isset($queried->ID)) {
                    $post_id = $queried->ID;
                }
            }
            if ($post_id) {
                // Check if built with Elementor
                $doc = \Elementor\Plugin::$instance->documents->get($post_id);
                if ($doc) {
                    $is_elementor_page = $doc->is_built_with_elementor();
                }
                // Also check if it's a product post type (Elementor single product template)
                if (!$is_elementor_page && get_post_type($post_id) === 'product') {
                    $is_elementor_page = true;
                }
            }
        }

        if (!is_product() && !is_cart() && !is_checkout() && !$is_elementor_page) {
            return;
        }

        // Use file modification time for cache busting
        $js_version = filemtime(MMD_PLUGIN_DIR . 'assets/gift-selection.js');
        $css_version = filemtime(MMD_PLUGIN_DIR . 'assets/gift-selection.css');

        wp_enqueue_style('mmd-gift-selection', MMD_PLUGIN_URL . 'assets/gift-selection.css', array(), $css_version);
        wp_enqueue_script('mmd-gift-selection', MMD_PLUGIN_URL . 'assets/gift-selection.js', array('jquery', 'wp-i18n'), $js_version, true);
        wp_set_script_translations('mmd-gift-selection', 'incentiv', MMD_PLUGIN_DIR . 'languages');

        wp_localize_script('mmd-gift-selection', 'mmdGiftData', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mmd_gift_nonce'),
        ));

        // Get styling options
        $styling = array();
        if (class_exists('MMD_Gift_Rules')) {
            $gift_rules = MMD_Gift_Rules::get_instance();
            $styling = $gift_rules->get_styling_options();
        }

        // Build dynamic inline styles
        $inline_css = "
            /* Force hide quantity inputs/buttons for gift items in mini-cart and checkout */
            .mmd-gift-mini-cart-item .quantity,
            .mmd-gift-item-row .quantity,
            .mmd-gift-item-row .product-quantity .quantity,
            tr.mmd-gift-item-row td.product-quantity div.quantity {
                display: none !important;
            }

            /* Modern CSS - target any list item that contains the lock icon */
            li.woocommerce-mini-cart-item:has(.mmd-gift-locked) .quantity,
            tr.cart_item:has(.mmd-gift-locked) .product-quantity .quantity,
            tr.cart_item:has(.mmd-gift-locked) .quantity {
                display: none !important;
            }

            /* JS Fallback classes */
            .mmd-gift-row-locked .quantity,
            .mmd-gift-row-locked .product-quantity .quantity {
                display: none !important;
            }

            /* Make sure we show '1' if the input is hidden */
            .mmd-gift-item-row .product-quantity:after,
            .mmd-gift-row-locked .product-quantity:after {
                content: '1';
                display: block;
            }
        ";

        // Add custom gift box styling if available
        if (!empty($styling)) {
            // Calculate box shadow
            $box_shadow = 'none';
            if (isset($styling['gift_box_shadow'])) {
                switch ($styling['gift_box_shadow']) {
                    case 'small':
                        $box_shadow = '0 2px 4px rgba(0,0,0,0.1)';
                        break;
                    case 'medium':
                        $box_shadow = '0 4px 8px rgba(0,0,0,0.15)';
                        break;
                    case 'large':
                        $box_shadow = '0 8px 16px rgba(0,0,0,0.2)';
                        break;
                }
            }

            $inline_css .= "
                /* Custom gift box styling */
                .mmd-gift-selection-container {
                    background: linear-gradient(135deg, " . esc_attr($styling['gift_box_bg_color_1']) . " 0%, " . esc_attr($styling['gift_box_bg_color_2']) . " 100%) !important;
                    color: " . esc_attr($styling['gift_box_text_color']) . " !important;
                    border-radius: " . esc_attr($styling['gift_box_border_radius']) . "px !important;
                    padding: " . esc_attr($styling['gift_box_padding']) . "px !important;
                    margin: " . esc_attr(isset($styling['gift_box_margin']) ? $styling['gift_box_margin'] : '20') . "px 0 !important;
                    border: " . esc_attr(isset($styling['gift_box_border_width']) ? $styling['gift_box_border_width'] : '0') . "px solid " . esc_attr(isset($styling['gift_box_border_color']) ? $styling['gift_box_border_color'] : '#ffffff') . " !important;
                    box-shadow: " . $box_shadow . " !important;
                }

                .mmd-gift-title {
                    color: " . esc_attr($styling['gift_box_text_color']) . " !important;
                    font-size: " . esc_attr(isset($styling['gift_box_title_size']) ? $styling['gift_box_title_size'] : '20') . "px !important;
                }

                .mmd-gift-subtitle {
                    color: " . esc_attr($styling['gift_box_text_color']) . " !important;
                    font-size: " . esc_attr(isset($styling['gift_box_subtitle_size']) ? $styling['gift_box_subtitle_size'] : '14') . "px !important;
                }

                /* Gift selection buttons */
                .mmd-gift-select-btn {
                    background-color: " . esc_attr(isset($styling['gift_button_bg_color']) ? $styling['gift_button_bg_color'] : '#ffffff') . " !important;
                    color: " . esc_attr(isset($styling['gift_button_text_color']) ? $styling['gift_button_text_color'] : '#7c3aed') . " !important;
                    border: none !important;
                    padding: 8px 16px !important;
                    border-radius: 4px !important;
                    cursor: pointer !important;
                    transition: all 0.3s ease !important;
                }

                .mmd-gift-select-btn:hover {
                    background-color: " . esc_attr(isset($styling['gift_button_hover_bg']) ? $styling['gift_button_hover_bg'] : '#7c3aed') . " !important;
                    color: " . esc_attr(isset($styling['gift_button_hover_text']) ? $styling['gift_button_hover_text'] : '#ffffff') . " !important;
                }

                .mmd-gift-select-btn.selected {
                    background-color: " . esc_attr(isset($styling['gift_button_hover_bg']) ? $styling['gift_button_hover_bg'] : '#7c3aed') . " !important;
                    color: " . esc_attr(isset($styling['gift_button_hover_text']) ? $styling['gift_button_hover_text'] : '#ffffff') . " !important;
                }

                /* Gift items in cart, mini-cart, and checkout */
                .mmd-gift-item-row,
                .mmd-gift-mini-cart-item {
                    background: linear-gradient(135deg, " . esc_attr($styling['gift_box_bg_color_1']) . " 0%, " . esc_attr($styling['gift_box_bg_color_2']) . " 100%) !important;
                    position: relative;
                }

                .mmd-gift-item-row td,
                .mmd-gift-mini-cart-item,
                .mmd-gift-mini-cart-item a {
                    color: " . esc_attr($styling['gift_box_text_color']) . " !important;
                }

                /* Gift badge styling */
                .mmd-gift-badge {
                    background: linear-gradient(135deg, " . esc_attr($styling['gift_box_bg_color_1']) . " 0%, " . esc_attr($styling['gift_box_bg_color_2']) . " 100%) !important;
                    color: " . esc_attr($styling['gift_box_text_color']) . " !important;
                    border-radius: " . esc_attr(isset($styling['gift_box_border_radius']) ? $styling['gift_box_border_radius'] : '8') . "px !important;
                }

                /* Make gift rows stand out with subtle styling */
                .mmd-gift-item-row {
                    border-left: " . esc_attr(isset($styling['gift_box_border_width']) ? max(3, $styling['gift_box_border_width']) : '3') . "px solid " . esc_attr($styling['gift_box_bg_color_1']) . " !important;
                }

                .mmd-gift-mini-cart-item {
                    border-left: " . esc_attr(isset($styling['gift_box_border_width']) ? max(3, $styling['gift_box_border_width']) : '3') . "px solid " . esc_attr($styling['gift_box_bg_color_1']) . " !important;
                    padding-left: 10px !important;
                }
            ";
        }

        wp_add_inline_style('mmd-gift-selection', $inline_css);
        
        // Add inline JS to add classes to gift rows (fallback for theme overrides)
        wp_add_inline_script('mmd-gift-selection', "
            jQuery(document).ready(function($) {
                // Function to process rows
                function tagGiftRows() {
                    // Find lock icons and add class to parent row/item
                    $('.mmd-gift-locked').each(function() {
                        // For checkout tables
                        $(this).closest('tr').addClass('mmd-gift-row-locked mmd-gift-item-row');
                        // For mini-cart items
                        $(this).closest('li').addClass('mmd-gift-row-locked mmd-gift-mini-cart-item');
                    });
                }
                
                // Run on load
                tagGiftRows();
                
                // Run on AJAX updates
                $(document.body).on('updated_cart_totals updated_checkout wc_fragments_refreshed', function() {
                    setTimeout(tagGiftRows, 100);
                });
                
                // Robust Observer for mini-cart changes (WoodMart/Elementor side carts)
                const observer = new MutationObserver(function(mutations) {
                    mutations.forEach(function(mutation) {
                        if (mutation.addedNodes.length) {
                            tagGiftRows();
                        }
                    });
                });
                
                // Observe the body for cart widgets being added/updated
                observer.observe(document.body, { childList: true, subtree: true });
            });
        ");
    }

    /**
     * Get gift settings for a product (checks rules first, then product meta)
     *
     * @param int $product_id Product ID
     * @return array|null Gift settings or null if none found
     */
    private function get_gift_settings($product_id) {
        // Check if gift rules class exists
        if (class_exists('MMD_Gift_Rules')) {
            $gift_rules = MMD_Gift_Rules::get_instance();
            $matching_rule = $gift_rules->get_matching_rule_for_product($product_id);

            // If a rule matches and override is enabled, use rule settings
            if ($matching_rule && isset($matching_rule['override_product_settings']) && $matching_rule['override_product_settings'] === 'yes') {
                $gift_products = $this->get_gift_products_from_rule($matching_rule);
                return array(
                    'enabled' => true,
                    'gift_products' => $gift_products,
                    'gift_type' => isset($matching_rule['gift_type']) ? $matching_rule['gift_type'] : 'free',
                    'gift_discount' => isset($matching_rule['gift_discount']) ? $matching_rule['gift_discount'] : 0,
                    'gift_selection_type' => isset($matching_rule['gift_selection_type']) ? $matching_rule['gift_selection_type'] : 'single',
                    'gift_mode' => isset($matching_rule['gift_mode']) ? $matching_rule['gift_mode'] : 'product_page',
                    'show_gift_selection' => isset($matching_rule['show_gift_selection']) ? $matching_rule['show_gift_selection'] : 'yes',
                    'gift_limit_type' => isset($matching_rule['gift_limit_type']) ? $matching_rule['gift_limit_type'] : 'per_cart',
                    'allow_quantity' => isset($matching_rule['allow_quantity']) ? $matching_rule['allow_quantity'] : 'no',
                    'max_quantity' => isset($matching_rule['max_quantity']) ? $matching_rule['max_quantity'] : 5,
                    'total_quantity_limit' => isset($matching_rule['total_quantity_limit']) ? intval($matching_rule['total_quantity_limit']) : 0,
                    'gift_default_variations' => isset($matching_rule['gift_default_variations']) ? $matching_rule['gift_default_variations'] : array(),
                    'gift_products_limit' => isset($matching_rule['gift_products_limit']) ? intval($matching_rule['gift_products_limit']) : 0,
                    'cart_message' => isset($matching_rule['cart_message']) ? $matching_rule['cart_message'] : '',
                    'checkout_message' => isset($matching_rule['checkout_message']) ? $matching_rule['checkout_message'] : '',
                    'shipping_method_condition' => isset($matching_rule['shipping_method_condition']) ? $matching_rule['shipping_method_condition'] : '',
                    'allowed_user_roles' => isset($matching_rule['allowed_user_roles']) ? $matching_rule['allowed_user_roles'] : array(),
                    'schedule_start' => isset($matching_rule['schedule_start']) ? $matching_rule['schedule_start'] : '',
                    'schedule_end' => isset($matching_rule['schedule_end']) ? $matching_rule['schedule_end'] : '',
                    'min_cart_total' => isset($matching_rule['min_cart_total']) ? $matching_rule['min_cart_total'] : '',
                    'max_cart_total' => isset($matching_rule['max_cart_total']) ? $matching_rule['max_cart_total'] : '',
                    'min_quantity_trigger' => isset($matching_rule['min_quantity_trigger']) ? $matching_rule['min_quantity_trigger'] : '',
                    'coupon_required' => isset($matching_rule['coupon_required']) ? $matching_rule['coupon_required'] : 'no',
                    'required_coupon_codes' => isset($matching_rule['required_coupon_codes']) ? $matching_rule['required_coupon_codes'] : array(),
                    'from_rule' => true,
                );
            }
        }

        // Fall back to product meta
        $enabled = get_post_meta($product_id, '_mmd_gift_enabled', true);

        if ($enabled !== '1') {
            // Check if there's a matching rule without override
            if (isset($matching_rule) && $matching_rule) {
                $gift_products = $this->get_gift_products_from_rule($matching_rule);
                return array(
                    'enabled' => true,
                    'gift_products' => $gift_products,
                    'gift_type' => isset($matching_rule['gift_type']) ? $matching_rule['gift_type'] : 'free',
                    'gift_discount' => isset($matching_rule['gift_discount']) ? $matching_rule['gift_discount'] : 0,
                    'gift_selection_type' => isset($matching_rule['gift_selection_type']) ? $matching_rule['gift_selection_type'] : 'single',
                    'gift_mode' => isset($matching_rule['gift_mode']) ? $matching_rule['gift_mode'] : 'product_page',
                    'show_gift_selection' => isset($matching_rule['show_gift_selection']) ? $matching_rule['show_gift_selection'] : 'yes',
                    'gift_limit_type' => isset($matching_rule['gift_limit_type']) ? $matching_rule['gift_limit_type'] : 'per_cart',
                    'allow_quantity' => isset($matching_rule['allow_quantity']) ? $matching_rule['allow_quantity'] : 'no',
                    'max_quantity' => isset($matching_rule['max_quantity']) ? $matching_rule['max_quantity'] : 5,
                    'total_quantity_limit' => isset($matching_rule['total_quantity_limit']) ? intval($matching_rule['total_quantity_limit']) : 0,
                    'gift_default_variations' => isset($matching_rule['gift_default_variations']) ? $matching_rule['gift_default_variations'] : array(),
                    'gift_products_limit' => isset($matching_rule['gift_products_limit']) ? intval($matching_rule['gift_products_limit']) : 0,
                    'coupon_required' => isset($matching_rule['coupon_required']) ? $matching_rule['coupon_required'] : 'no',
                    'required_coupon_codes' => isset($matching_rule['required_coupon_codes']) ? $matching_rule['required_coupon_codes'] : array(),
                    'cart_message' => isset($matching_rule['cart_message']) ? $matching_rule['cart_message'] : '',
                    'checkout_message' => isset($matching_rule['checkout_message']) ? $matching_rule['checkout_message'] : '',
                    'shipping_method_condition' => isset($matching_rule['shipping_method_condition']) ? $matching_rule['shipping_method_condition'] : '',
                    'allowed_user_roles' => isset($matching_rule['allowed_user_roles']) ? $matching_rule['allowed_user_roles'] : array(),
                    'schedule_start' => isset($matching_rule['schedule_start']) ? $matching_rule['schedule_start'] : '',
                    'schedule_end' => isset($matching_rule['schedule_end']) ? $matching_rule['schedule_end'] : '',
                    'min_cart_total' => isset($matching_rule['min_cart_total']) ? $matching_rule['min_cart_total'] : '',
                    'max_cart_total' => isset($matching_rule['max_cart_total']) ? $matching_rule['max_cart_total'] : '',
                    'min_quantity_trigger' => isset($matching_rule['min_quantity_trigger']) ? $matching_rule['min_quantity_trigger'] : '',
                    'from_rule' => true,
                );
            }
            return null;
        }

        // Get gift products from product meta (handles both specific and category sources)
        $gift_products = $this->get_gift_products_from_product_meta($product_id);
        $gift_type = get_post_meta($product_id, '_mmd_gift_type', true);
        $gift_discount = get_post_meta($product_id, '_mmd_gift_discount', true);
        $gift_selection_type = get_post_meta($product_id, '_mmd_gift_selection_type', true);
        $allow_quantity = get_post_meta($product_id, '_mmd_gift_allow_quantity', true);
        $max_quantity = get_post_meta($product_id, '_mmd_gift_max_quantity', true);

        if (empty($gift_products)) {
            return null;
        }

        $gift_mode = get_post_meta($product_id, '_mmd_gift_mode', true);

        return array(
            'enabled' => true,
            'gift_products' => $gift_products,
            'gift_type' => !empty($gift_type) ? $gift_type : 'free',
            'gift_discount' => !empty($gift_discount) ? $gift_discount : 0,
            'gift_selection_type' => !empty($gift_selection_type) ? $gift_selection_type : 'single',
            'gift_mode' => !empty($gift_mode) ? $gift_mode : 'product_page',
            'allow_quantity' => !empty($allow_quantity) ? $allow_quantity : 'no',
            'max_quantity' => !empty($max_quantity) ? intval($max_quantity) : 5,
            'gift_default_variations' => array(), // Product-level doesn't have this setting yet
            'from_rule' => false,
        );
    }

    /**
     * Get gift products from a global rule (handles both specific and category sources)
     *
     * @param array $rule The gift rule configuration
     * @return array Array of product IDs
     */
    private function get_gift_products_from_rule($rule) {
        $gift_source = isset($rule['gift_source']) ? $rule['gift_source'] : 'specific';

        self::debug_log('MMD get_gift_products_from_rule: gift_source=' . $gift_source);
        self::debug_log('MMD get_gift_products_from_rule: rule keys=' . implode(', ', array_keys($rule)));

        if ($gift_source === 'specific') {
            $products = isset($rule['gift_products']) ? $rule['gift_products'] : array();
            self::debug_log('MMD get_gift_products_from_rule: specific mode, returning ' . count($products) . ' products');
            return $products;
        }

        // Category-based gift selection
        $gift_categories = isset($rule['gift_categories']) ? $rule['gift_categories'] : array();
        $excluded_categories = isset($rule['gift_excluded_categories']) ? $rule['gift_excluded_categories'] : array();
        $excluded_tags = isset($rule['gift_excluded_tags']) ? $rule['gift_excluded_tags'] : array();
        $excluded_products = isset($rule['gift_excluded_products']) ? $rule['gift_excluded_products'] : array();
        $attribute_slug = isset($rule['gift_attribute_slug']) ? $rule['gift_attribute_slug'] : '';
        $attribute_values = isset($rule['gift_attribute_values']) ? $rule['gift_attribute_values'] : array();

        self::debug_log('MMD get_gift_products_from_rule: category mode');
        self::debug_log('MMD get_gift_products_from_rule: gift_categories=' . print_r($gift_categories, true));
        self::debug_log('MMD get_gift_products_from_rule: attribute_slug=' . $attribute_slug);
        self::debug_log('MMD get_gift_products_from_rule: attribute_values=' . print_r($attribute_values, true));

        $products = $this->get_products_from_categories($gift_categories, $excluded_categories, $excluded_tags, $excluded_products, $attribute_slug, $attribute_values);

        // Apply additional attribute filters
        $additional_attrs = isset($rule['gift_additional_attributes']) ? $rule['gift_additional_attributes'] : array();
        if (!empty($additional_attrs) && !empty($products)) {
            foreach ($additional_attrs as $add_attr) {
                $add_slug = isset($add_attr['slug']) ? $add_attr['slug'] : '';
                $add_values = isset($add_attr['values']) ? $add_attr['values'] : array();
                if (empty($add_slug) || empty($add_values)) continue;

                // Filter existing products by this additional attribute
                $add_values = array_map('intval', $add_values);
                $filtered = array();
                foreach ($products as $pid) {
                    $p = wc_get_product($pid);
                    if (!$p) continue;

                    if ($p->is_type('variation')) {
                        // Check variation attribute
                        $attr_value = $p->get_attribute($add_slug);
                        if (!empty($attr_value)) {
                            $term = get_term_by('slug', sanitize_title($attr_value), $add_slug);
                            if (!$term) $term = get_term_by('name', $attr_value, $add_slug);
                            if ($term && in_array($term->term_id, $add_values)) {
                                $filtered[] = $pid;
                            }
                        } else {
                            // Empty = "any" - keep it (user will select on frontend)
                            $filtered[] = $pid;
                        }
                    } else {
                        // Simple/variable product - check if it has the attribute
                        $product_attrs = $p->get_attributes();
                        if (isset($product_attrs[$add_slug]) && $product_attrs[$add_slug]->is_taxonomy()) {
                            $terms = wp_get_post_terms($pid, $add_slug, array('fields' => 'ids'));
                            foreach ($add_values as $av) {
                                if (in_array($av, $terms)) {
                                    $filtered[] = $pid;
                                    break;
                                }
                            }
                        } else {
                            $filtered[] = $pid; // No attribute = no filter
                        }
                    }
                }
                $products = $filtered;
            }
        }

        // Apply the GLOBAL gift exclusion list set in plugin settings.
        // These products can never be a gift, regardless of which rule
        // matched or which source was configured.
        $products = self::filter_global_gift_exclusions($products);

        self::debug_log('MMD get_gift_products_from_rule: returned ' . count($products) . ' products: ' . implode(',', $products));

        return $products;
    }

    /**
     * Subtract the plugin-wide "never as a gift" list from the given
     * product-id array. Used at the end of every gift-pool builder so a
     * product the admin globally excluded can't slip through any rule.
     */
    public static function filter_global_gift_exclusions($product_ids) {
        $settings = get_option('mmd_settings', array());
        $globally_excluded = isset($settings['global_gift_excluded_products']) && is_array($settings['global_gift_excluded_products'])
            ? array_map('intval', $settings['global_gift_excluded_products'])
            : array();
        if (empty($globally_excluded) || empty($product_ids)) return $product_ids;
        return array_values(array_diff($product_ids, $globally_excluded));
    }

    /**
     * Get gift products from product meta (handles both specific and category sources)
     *
     * @param int $product_id The product ID
     * @return array Array of product IDs
     */
    private function get_gift_products_from_product_meta($product_id) {
        $gift_source = get_post_meta($product_id, '_mmd_gift_source', true);

        if (empty($gift_source) || $gift_source === 'specific') {
            $specific = get_post_meta($product_id, '_mmd_gift_products', true) ?: array();
            return self::filter_global_gift_exclusions($specific);
        }

        // Category-based gift selection
        $gift_categories = get_post_meta($product_id, '_mmd_gift_categories', true) ?: array();
        $excluded_categories = get_post_meta($product_id, '_mmd_gift_excluded_categories', true) ?: array();
        $excluded_tags = get_post_meta($product_id, '_mmd_gift_excluded_tags', true) ?: array();
        $excluded_products = get_post_meta($product_id, '_mmd_gift_excluded_products', true) ?: array();
        $attribute_slug = get_post_meta($product_id, '_mmd_gift_attribute_slug', true) ?: '';
        $attribute_values = get_post_meta($product_id, '_mmd_gift_attribute_values', true) ?: array();

        return self::filter_global_gift_exclusions(
            $this->get_products_from_categories($gift_categories, $excluded_categories, $excluded_tags, $excluded_products, $attribute_slug, $attribute_values)
        );
    }

    /**
     * Get products from categories with exclusion filters
     *
     * @param array $categories Category IDs to include
     * @param array $excluded_categories Category IDs to exclude
     * @param array $excluded_tags Tag IDs to exclude
     * @param array $excluded_products Product IDs to exclude
     * @param string $attribute_slug Optional attribute slug to filter by (e.g., 'pa_weight')
     * @param array $attribute_values Optional attribute term IDs to filter variations by
     * @return array Array of product IDs (or variation IDs when filtering by attribute)
     */
    private function get_products_from_categories($categories, $excluded_categories = array(), $excluded_tags = array(), $excluded_products = array(), $attribute_slug = '', $attribute_values = array()) {
        if (empty($categories)) {
            return array();
        }

        // Build query arguments
        $args = array(
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'tax_query' => array(
                array(
                    'taxonomy' => 'product_cat',
                    'field' => 'term_id',
                    'terms' => $categories,
                    'operator' => 'IN',
                ),
            ),
        );

        // Add excluded categories
        if (!empty($excluded_categories)) {
            $args['tax_query'][] = array(
                'taxonomy' => 'product_cat',
                'field' => 'term_id',
                'terms' => $excluded_categories,
                'operator' => 'NOT IN',
            );
        }

        // Add excluded tags
        if (!empty($excluded_tags)) {
            $args['tax_query'][] = array(
                'taxonomy' => 'product_tag',
                'field' => 'term_id',
                'terms' => $excluded_tags,
                'operator' => 'NOT IN',
            );
        }

        // Set tax query relation
        if (count($args['tax_query']) > 1) {
            $args['tax_query']['relation'] = 'AND';
        }

        // Add excluded specific products
        if (!empty($excluded_products)) {
            $args['post__not_in'] = $excluded_products;
        }

        // Run query
        $query = new WP_Query($args);
        $product_ids = $query->posts;

        // If no attribute filter, return all products
        if (empty($attribute_slug) || empty($attribute_values)) {
            return $product_ids;
        }

        // Filter by attribute — but return PARENT product IDs (not variation IDs).
        // The cart/checkout gift UI needs parent products so it can show variation
        // selectors (weight, grinding, etc.) to the customer.
        $filtered_parent_ids = array();

        // Ensure attribute_values are integers for proper comparison
        $attribute_values = array_map('intval', $attribute_values);

        foreach ($product_ids as $product_id) {
            $product = wc_get_product($product_id);
            if (!$product) continue;

            if ($product->is_type('variable')) {
                // Check if ANY variation matches the attribute filter
                $has_matching_variation = false;
                foreach ($product->get_children() as $variation_id) {
                    if (in_array($variation_id, $excluded_products)) continue;
                    $variation = wc_get_product($variation_id);
                    if (!$variation || !$variation->is_type('variation')) continue;

                    $attr_value = $variation->get_attribute($attribute_slug);
                    if (empty($attr_value)) {
                        // Empty = "any" — this variation accepts any value, so the parent qualifies
                        $has_matching_variation = true;
                        break;
                    }

                    $term = get_term_by('slug', sanitize_title($attr_value), $attribute_slug);
                    if (!$term) $term = get_term_by('name', $attr_value, $attribute_slug);
                    if ($term && in_array($term->term_id, $attribute_values)) {
                        $has_matching_variation = true;
                        break;
                    }
                }

                if ($has_matching_variation) {
                    $filtered_parent_ids[] = $product_id; // Return the PARENT, not the variation
                }
            } elseif ($product->is_type('simple')) {
                // Simple product — check if it has the attribute
                $product_attrs = $product->get_attributes();
                if (isset($product_attrs[$attribute_slug]) && $product_attrs[$attribute_slug]->is_taxonomy()) {
                    $terms = wp_get_post_terms($product_id, $attribute_slug, array('fields' => 'ids'));
                    foreach ($attribute_values as $av) {
                        if (in_array($av, $terms)) {
                            $filtered_parent_ids[] = $product_id;
                            break;
                        }
                    }
                } else {
                    // No such attribute = include it (no filter to apply)
                    $filtered_parent_ids[] = $product_id;
                }
            }
        }

        return $filtered_parent_ids;
    }

    /**
     * Display gift selection on product page
     */
    private static $gift_selection_rendered = false;

    public function display_gift_selection() {
        // Shoppers blocked by an add-on (filter `mmd_promotions_blocked`) get no promotions.
        if (MMD_Modules::promotions_blocked('gifts')) {
            return;
        }

        global $product;

        if (!$product) {
            return;
        }

        // Only show on single product pages — NOT on shop/archive/grid
        if (!is_product()) {
            return;
        }

        // Prevent double rendering
        if (self::$gift_selection_rendered) {
            return;
        }

        // Get gift settings from rules or product meta
        $gift_settings = $this->get_gift_settings($product->get_id());

        if (!$gift_settings || empty($gift_settings['gift_products'])) {
            return;
        }

        // If the gift rule requires a coupon, only show gift selection when coupon is applied
        if (!empty($gift_settings['coupon_required']) && $gift_settings['coupon_required'] === 'yes') {
            $applied_coupons = WC()->cart ? array_map('strtolower', WC()->cart->get_applied_coupons()) : array();
            $required_coupons = isset($gift_settings['required_coupon_codes']) ? $gift_settings['required_coupon_codes'] : array();
            if (!is_array($required_coupons)) {
                $required_coupons = array_map('trim', explode(',', $required_coupons));
            }
            $coupon_active = false;
            foreach ($required_coupons as $rc) {
                if (in_array(strtolower(trim($rc)), $applied_coupons)) {
                    $coupon_active = true;
                    break;
                }
            }
            if (!$coupon_active) {
                return;
            }
        }

        // Check if gift selection should be shown on product page
        $show_gift_selection = isset($gift_settings['show_gift_selection']) ? $gift_settings['show_gift_selection'] : 'yes';
        if ($show_gift_selection !== 'yes') {
            return;
        }

        // Check shipping method condition (for cart/checkout context)
        $shipping_condition = isset($gift_settings['shipping_method_condition']) ? $gift_settings['shipping_method_condition'] : '';
        if (!empty($shipping_condition) && WC()->session) {
            $chosen_methods = WC()->session->get('chosen_shipping_methods', array());
            $shipping_match = false;
            foreach ($chosen_methods as $chosen_method) {
                if ($chosen_method === $shipping_condition || strpos($chosen_method, $shipping_condition) === 0) {
                    $shipping_match = true;
                    break;
                }
            }
            if (!$shipping_match) {
                return;
            }
        }

        // Check additional conditions from the rule
        if (isset($gift_settings['from_rule']) && $gift_settings['from_rule']) {
            // Check user role
            $allowed_roles = isset($gift_settings['allowed_user_roles']) && is_array($gift_settings['allowed_user_roles']) ? $gift_settings['allowed_user_roles'] : array();
            if (!empty($allowed_roles)) {
                if (!is_user_logged_in()) {
                    return;
                }
                $user = wp_get_current_user();
                if (empty(array_intersect($allowed_roles, $user->roles))) {
                    return;
                }
            }

            // Check schedule
            $now = current_time('Y-m-d\TH:i');
            $schedule_start = isset($gift_settings['schedule_start']) ? $gift_settings['schedule_start'] : '';
            $schedule_end = isset($gift_settings['schedule_end']) ? $gift_settings['schedule_end'] : '';
            if (!empty($schedule_start) && $now < $schedule_start) {
                return;
            }
            if (!empty($schedule_end) && $now > $schedule_end) {
                return;
            }

            // Check min cart total (only relevant in cart context)
            if (WC()->cart && !WC()->cart->is_empty()) {
                $cart_subtotal = WC()->cart->get_subtotal();
                $min_cart_total = isset($gift_settings['min_cart_total']) && $gift_settings['min_cart_total'] !== '' ? floatval($gift_settings['min_cart_total']) : 0;
                $max_cart_total = isset($gift_settings['max_cart_total']) && $gift_settings['max_cart_total'] !== '' ? floatval($gift_settings['max_cart_total']) : 0;

                if ($min_cart_total > 0 && $cart_subtotal < $min_cart_total) {
                    return;
                }
                if ($max_cart_total > 0 && $cart_subtotal > $max_cart_total) {
                    return;
                }
            }
        }

        // Same as the cart picker: out-of-stock gifts are not offered.
        $gift_products = self::filter_in_stock_gift_products($gift_settings['gift_products']);
        if (empty($gift_products)) {
            return;
        }
        $gift_type = $gift_settings['gift_type'];
        $gift_discount = $gift_settings['gift_discount'];
        $gift_selection_type = $gift_settings['gift_selection_type'];
        $allow_quantity = isset($gift_settings['allow_quantity']) ? $gift_settings['allow_quantity'] : 'no';
        $max_quantity = isset($gift_settings['max_quantity']) ? $gift_settings['max_quantity'] : 5;
        $total_quantity_limit = isset($gift_settings['total_quantity_limit']) ? intval($gift_settings['total_quantity_limit']) : 0;
        $default_variations = isset($gift_settings['gift_default_variations']) ? $gift_settings['gift_default_variations'] : array();
        $gift_products_limit = isset($gift_settings['gift_products_limit']) ? intval($gift_settings['gift_products_limit']) : 0;

        // NOTE: gift_products_limit controls how many items the customer can SELECT,
        // NOT how many products to SHOW. All qualifying products should be displayed
        // so the customer has full choice. The JS enforces the selection limit.

        // Pagination settings for long lists
        $products_per_page = 8;
        $total_products = count($gift_products);
        $use_pagination = ($total_products > $products_per_page);

        // Prepare gift text
        $gift_text = '';
        if ($gift_type === 'free') {
            $gift_text = __( 'Free gift', 'incentiv' );
        } elseif ($gift_type === 'discount') {
            $gift_text = ( __( 'A discount of', 'incentiv' ) . ' ' ) . $gift_discount . '%';
        } elseif ($gift_type === 'fixed_discount') {
            $gift_text = ( __( 'A discount of', 'incentiv' ) . ' ' ) . wc_price($gift_discount);
        }

        $selection_text = $gift_selection_type === 'multiple' ? __( 'one or more products of your choice', 'incentiv' ) : __( 'a product of your choice', 'incentiv' );
        // The subtitle reads the gift type mid-sentence ("get a free gift on ...").
        if ($gift_type === 'free') {
            $gift_text = _x( 'a free gift', 'gift box subtitle', 'incentiv' );
        }

        // Get text customization from styling options
        $styling = array();
        if (class_exists('MMD_Gift_Rules')) {
            $gift_rules = MMD_Gift_Rules::get_instance();
            $styling = $gift_rules->get_styling_options();
        }

        $title_text = isset($styling['gift_box_title_text']) ? $styling['gift_box_title_text'] : __( '🎁 Choose your gift!', 'incentiv' );
        $subtitle_template = isset($styling['gift_box_subtitle_text']) ? $styling['gift_box_subtitle_text'] : __( 'Buy this product and get {gift_type} on {selection_type}:', 'incentiv' );

        // Replace placeholders in subtitle
        $subtitle_text = str_replace(
            array('{gift_type}', '{selection_type}'),
            array($gift_text, $selection_text),
            $subtitle_template
        );

        self::$gift_selection_rendered = true;
        ?>
        <div class="mmd-gift-selection-container woocommerce-product-addons"
             style="display: block !important; position: static !important; visibility: visible !important;"
             data-selection-type="<?php echo esc_attr($gift_selection_type); ?>"
             data-parent-product-id="<?php echo esc_attr($product->get_id()); ?>"
             data-allow-quantity="<?php echo esc_attr($allow_quantity); ?>"
             data-max-quantity="<?php echo esc_attr($max_quantity); ?>"
             data-total-quantity-limit="<?php echo esc_attr($total_quantity_limit); ?>"
             data-default-variations="<?php echo esc_attr(json_encode($default_variations)); ?>"
             data-products-per-page="<?php echo esc_attr($products_per_page); ?>"
             data-total-products="<?php echo esc_attr($total_products); ?>"
             data-use-pagination="<?php echo $use_pagination ? '1' : '0'; ?>">
            <div class="mmd-gift-title">
                <?php echo esc_html($title_text); ?>
            </div>
            <p class="mmd-gift-subtitle"><?php echo esc_html($subtitle_text); ?></p>

            <?php if ($use_pagination): ?>
            <div class="mmd-gift-pagination-controls mmd-gift-pagination-top">
                <button type="button" class="mmd-pagination-btn mmd-pagination-prev" disabled><?php echo wp_kses_post( __( '&laquo; Previous', 'incentiv' ) ); ?></button>
                <span class="mmd-pagination-info"><?php esc_html_e( 'Page', 'incentiv' ); ?> <span class="mmd-current-page">1</span> <?php esc_html_e( 'of', 'incentiv' ); ?> <span class="mmd-total-pages"><?php echo esc_html( ceil($total_products / $products_per_page) ); ?></span></span>
                <button type="button" class="mmd-pagination-btn mmd-pagination-next"><?php echo wp_kses_post( __( 'Next &raquo;', 'incentiv' ) ); ?></button>
            </div>
            <?php endif; ?>

            <div class="mmd-gift-products-table">
                <table class="mmd-gift-table">
                    <tbody>
                        <?php $product_index = 0; ?>
                        <?php foreach ($gift_products as $gift_product_id):
                            $gift_product = wc_get_product($gift_product_id);
                            if (!$gift_product) continue;

                            $is_variable = $gift_product->is_type('variable');
                            $is_variation = $gift_product->is_type('variation');

                            // For variation products: get parent info and determine remaining selectable attributes
                            $parent_product_id = $gift_product_id;
                            $preset_variation_id = 0;
                            $preset_attributes = array();
                            $remaining_attributes = array(); // Attributes that still need user selection

                            if ($is_variation) {
                                $parent_product_id = $gift_product->get_parent_id();
                                $preset_variation_id = $gift_product_id;
                                $raw_preset_attributes = $gift_product->get_variation_attributes();
                                // Decode URL-encoded keys (Hebrew attributes get encoded by WooCommerce)
                                $preset_attributes = array();
                                foreach ($raw_preset_attributes as $k => $v) {
                                    $preset_attributes[urldecode($k)] = $v;
                                }

                                // Get rule's attribute filters to auto-fill "any" values
                                $rule_attr_slug = isset($gift_settings['gift_attribute_slug']) ? $gift_settings['gift_attribute_slug'] : '';
                                $rule_attr_values = isset($gift_settings['gift_attribute_values']) ? $gift_settings['gift_attribute_values'] : array();
                                $rule_additional_attrs = array();
                                if (isset($gift_settings['from_rule']) && $gift_settings['from_rule'] && class_exists('MMD_Gift_Rules')) {
                                    $matching_rule = MMD_Gift_Rules::get_instance()->get_matching_rule_for_product($product->get_id());
                                    if ($matching_rule) {
                                        $rule_additional_attrs = isset($matching_rule['gift_additional_attributes']) ? $matching_rule['gift_additional_attributes'] : array();
                                    }
                                }

                                // Build a map of rule-filtered attributes (slug => [term_ids])
                                $rule_filtered_attrs = array();
                                if (!empty($rule_attr_slug) && !empty($rule_attr_values)) {
                                    $rule_filtered_attrs[$rule_attr_slug] = $rule_attr_values;
                                }
                                foreach ($rule_additional_attrs as $add_attr) {
                                    if (!empty($add_attr['slug']) && !empty($add_attr['values'])) {
                                        $rule_filtered_attrs[$add_attr['slug']] = $add_attr['values'];
                                    }
                                }

                                // Find attributes with "any" value (empty) that need user selection
                                $parent_var_product = wc_get_product($parent_product_id);
                                if ($parent_var_product && $parent_var_product->is_type('variable')) {
                                    $parent_attrs = $parent_var_product->get_variation_attributes();
                                    foreach ($preset_attributes as $attr_key => $attr_value) {
                                        if ($attr_value === '' || $attr_value === null) {
                                            // This attribute needs user selection
                                            // Decode URL-encoded attribute keys (Hebrew attribute names get encoded)
                                            $decoded_key = urldecode($attr_key);
                                            $attr_name = str_replace('attribute_', '', $decoded_key);
                                            $label = wc_attribute_label($attr_name);

                                            // If label is still the slug, try to get from taxonomy
                                            if ($label === $attr_name && strpos($attr_name, 'pa_') === 0) {
                                                $taxonomy_obj = get_taxonomy($attr_name);
                                                if ($taxonomy_obj && isset($taxonomy_obj->labels->singular_name)) {
                                                    $label = $taxonomy_obj->labels->singular_name;
                                                } else {
                                                    $attr_tax = wc_get_attribute(wc_attribute_taxonomy_id_by_name($attr_name));
                                                    if ($attr_tax && !empty($attr_tax->name)) {
                                                        $label = $attr_tax->name;
                                                    }
                                                }
                                            }

                                            // Try both decoded and original key to find options
                                            $options = array();
                                            if (isset($parent_attrs[$attr_name])) {
                                                $options = $parent_attrs[$attr_name];
                                            } else {
                                                // Try URL-encoded version
                                                $encoded_name = str_replace('attribute_', '', $attr_key);
                                                if (isset($parent_attrs[$encoded_name])) {
                                                    $options = $parent_attrs[$encoded_name];
                                                }
                                                // Try all parent attrs for a fuzzy match
                                                if (empty($options)) {
                                                    foreach ($parent_attrs as $pa_key => $pa_vals) {
                                                        if (urldecode($pa_key) === $attr_name || $pa_key === $attr_name) {
                                                            $options = $pa_vals;
                                                            break;
                                                        }
                                                    }
                                                }
                                            }

                                            // Get term labels for display
                                            $option_labels = array();
                                            $taxonomy = strpos($attr_name, 'pa_') === 0 ? $attr_name : '';
                                            foreach ($options as $opt_val) {
                                                $opt_label = $opt_val;
                                                if ($taxonomy) {
                                                    $term = get_term_by('slug', $opt_val, $taxonomy);
                                                    if ($term) $opt_label = $term->name;
                                                }
                                                $option_labels[] = array('value' => $opt_val, 'label' => $opt_label);
                                            }

                                            // Check if this attribute was filtered by the rule
                                            // If so, auto-fill it instead of showing a selector
                                            $auto_filled = false;
                                            if (isset($rule_filtered_attrs[$attr_name]) && count($rule_filtered_attrs[$attr_name]) === 1) {
                                                // Rule filtered to exactly one value - auto-fill
                                                $term_id = $rule_filtered_attrs[$attr_name][0];
                                                $term = get_term($term_id, $attr_name);
                                                if ($term && !is_wp_error($term)) {
                                                    $preset_attributes[$decoded_key] = $term->slug;
                                                    $auto_filled = true;
                                                }
                                            }

                                            if (!$auto_filled) {
                                                // If rule filtered to multiple values, only show those as options
                                                if (isset($rule_filtered_attrs[$attr_name]) && !empty($rule_filtered_attrs[$attr_name])) {
                                                    $filtered_options = array();
                                                    foreach ($option_labels as $opt) {
                                                        $opt_term = get_term_by('slug', $opt['value'], $attr_name);
                                                        if ($opt_term && in_array($opt_term->term_id, $rule_filtered_attrs[$attr_name])) {
                                                            $filtered_options[] = $opt;
                                                        }
                                                    }
                                                    if (!empty($filtered_options)) {
                                                        $option_labels = $filtered_options;
                                                        // If only one option left, auto-fill it
                                                        if (count($filtered_options) === 1) {
                                                            $preset_attributes[$decoded_key] = $filtered_options[0]['value'];
                                                            $auto_filled = true;
                                                        }
                                                    }
                                                }
                                            }

                                            if (!$auto_filled) {
                                                $remaining_attributes[] = array(
                                                    'key' => $decoded_key,
                                                    'name' => $attr_name,
                                                    'label' => $label,
                                                    'options' => $option_labels,
                                                );
                                            }
                                        }
                                    }
                                }
                            }

                            // In quantity mode with variable products, show each variation as a separate row
                            if ($allow_quantity === 'yes' && $gift_selection_type === 'multiple' && $is_variable) {
                                $variations = $gift_product->get_available_variations();
                                foreach ($variations as $variation_data):
                                    $variation = wc_get_product($variation_data['variation_id']);
                                    if (!$variation || !$variation->is_in_stock()) continue;

                                    $variation_name = $gift_product->get_name() . ' - ' . wc_get_formatted_variation($variation, true);
                                    ?>
                                    <tr class="mmd-gift-product-row mmd-variation-row"
                                        data-product-id="<?php echo esc_attr($gift_product_id); ?>"
                                        data-variation-id="<?php echo esc_attr($variation_data['variation_id']); ?>"
                                        data-variable="1"
                                        data-product-index="<?php echo esc_attr( $product_index ); ?>"
                                        style="<?php echo ($use_pagination && $product_index >= $products_per_page) ? 'display:none;' : ''; ?>"><?php $product_index++; ?>
                                        <td class="mmd-gift-image-cell">
                                            <?php echo wp_kses_post( $variation->get_image('thumbnail') ); ?>
                                        </td>
                                        <td class="mmd-gift-name-cell" colspan="2">
                                            <?php echo esc_html($variation_name); ?>
                                        </td>
                                        <td class="mmd-gift-quantity-cell">
                                            <div class="mmd-quantity-control">
                                                <button type="button" class="mmd-quantity-btn mmd-quantity-minus" aria-label="<?php esc_attr_e( 'Decrease quantity', 'incentiv' ); ?>">−</button>
                                                <div class="mmd-quantity-input-wrapper">
                                                    <input type="number"
                                                           class="mmd-gift-variation-quantity-input"
                                                           min="0"
                                                           max="<?php echo esc_attr($max_quantity); ?>"
                                                           value="0"
                                                           data-product-id="<?php echo esc_attr($gift_product_id); ?>"
                                                           data-variation-id="<?php echo esc_attr($variation_data['variation_id']); ?>"
                                                           data-attributes="<?php echo esc_attr(json_encode($variation->get_variation_attributes())); ?>">
                                                </div>
                                                <button type="button" class="mmd-quantity-btn mmd-quantity-plus" aria-label="<?php esc_attr_e( 'Increase quantity', 'incentiv' ); ?>">+</button>
                                            </div>
                                        </td>
                                        <td class="mmd-gift-action-cell">
                                            <span style="color: #666; font-size: 12px;"><?php esc_html_e( 'Choose quantity', 'incentiv' ); ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach;
                            } elseif ($is_variation) {
                                // This is a pre-filtered variation (e.g., from attribute filter)
                                // Show as a row with remaining attribute selectors if any
                                $display_name = $gift_product->get_name();
                                ?>
                                <tr class="mmd-gift-product-row"
                                    data-product-id="<?php echo esc_attr($parent_product_id); ?>"
                                    data-variation-id="<?php echo esc_attr($preset_variation_id); ?>"
                                    data-variable="<?php echo !empty($remaining_attributes) ? '1' : '0'; ?>"
                                    data-is-prefiltered-variation="1"
                                    data-preset-attributes="<?php echo esc_attr(wp_json_encode($preset_attributes)); ?>"
                                    data-product-index="<?php echo esc_attr( $product_index ); ?>"
                                    style="<?php echo ($use_pagination && $product_index >= $products_per_page) ? 'display:none;' : ''; ?>"><?php $product_index++; ?>
                                    <td class="mmd-gift-image-cell">
                                        <?php echo wp_kses_post( $gift_product->get_image('thumbnail') ); ?>
                                    </td>
                                    <td class="mmd-gift-name-cell">
                                        <?php echo esc_html($display_name); ?>
                                    </td>
                                    <td class="mmd-gift-variations-cell">
                                        <?php if (!empty($remaining_attributes)): ?>
                                            <div class="mmd-variation-remaining-attrs">
                                                <?php foreach ($remaining_attributes as $rem_attr): ?>
                                                    <div class="mmd-attribute-field" style="margin-bottom: 6px;">
                                                        <label style="display: block; font-size: 12px; color: #666; margin-bottom: 2px;"><?php echo esc_html($rem_attr['label']); ?></label>
                                                        <select class="mmd-variation-remaining-select"
                                                                data-attribute-key="<?php echo esc_attr($rem_attr['key']); ?>"
                                                                style="width: 100%; padding: 6px; border: 1px solid #ddd; border-radius: 4px; font-size: 13px; color: #000;">
                                                            <option value=""><?php esc_html_e( 'Choose', 'incentiv' ); ?> <?php echo esc_html($rem_attr['label']); ?>...</option>
                                                            <?php foreach ($rem_attr['options'] as $opt): ?>
                                                                <option value="<?php echo esc_attr($opt['value']); ?>"><?php echo esc_html($opt['label']); ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <?php if ($allow_quantity === 'yes'): ?>
                                    <td class="mmd-gift-quantity-cell">
                                        <div class="mmd-quantity-control">
                                            <button type="button" class="mmd-quantity-btn mmd-quantity-minus" aria-label="<?php esc_attr_e( 'Decrease quantity', 'incentiv' ); ?>">−</button>
                                            <div class="mmd-quantity-input-wrapper">
                                                <input type="number"
                                                       class="mmd-gift-quantity-input"
                                                       min="<?php echo $gift_selection_type === 'multiple' ? '0' : '1'; ?>"
                                                       max="<?php echo esc_attr($max_quantity); ?>"
                                                       value="<?php echo $gift_selection_type === 'multiple' ? '0' : '1'; ?>"
                                                       data-product-id="<?php echo esc_attr($parent_product_id); ?>"
                                                       data-variation-id="<?php echo esc_attr($preset_variation_id); ?>">
                                            </div>
                                            <button type="button" class="mmd-quantity-btn mmd-quantity-plus" aria-label="<?php esc_attr_e( 'Increase quantity', 'incentiv' ); ?>">+</button>
                                        </div>
                                    </td>
                                    <?php endif; ?>
                                    <td class="mmd-gift-action-cell">
                                        <?php if ($allow_quantity === 'yes' && $gift_selection_type === 'multiple'): ?>
                                            <span style="color: #666; font-size: 12px;"><?php esc_html_e( 'Choose quantity', 'incentiv' ); ?></span>
                                        <?php else: ?>
                                            <button type="button" class="mmd-gift-select-btn mmd-gift-variation-select-btn"
                                                    data-product-id="<?php echo esc_attr($parent_product_id); ?>"
                                                    data-variation-id="<?php echo esc_attr($preset_variation_id); ?>"
                                                    data-preset-attributes="<?php echo esc_attr(wp_json_encode($preset_attributes)); ?>">
                                                <?php esc_html_e( 'Choose', 'incentiv' ); ?>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php } else {
                                // Regular mode: one row per product (variable or simple)
                                $variation_data = array();
                                if ($is_variable) {
                                    // Pass the gift rule's attribute filter through so
                                    // get_variation_data() drops variations whose values
                                    // don't satisfy it (e.g. only weight=100g survives
                                    // when the rule restricts weight to {100g, 100 גרם}).
                                    // The JS then auto-hides any attribute that ends up
                                    // with a single value across the surviving variations.
                                    $rule_attr_filter_for_var = array();
                                    if (!empty($gift_settings['gift_attribute_slug']) && !empty($gift_settings['gift_attribute_values'])) {
                                        $rule_attr_filter_for_var[$gift_settings['gift_attribute_slug']] = (array) $gift_settings['gift_attribute_values'];
                                    }
                                    // Honor `gift_additional_attributes` (extra filters
                                    // from the wizard's category_attribute mode).
                                    if (isset($gift_settings['from_rule']) && $gift_settings['from_rule'] && class_exists('MMD_Gift_Rules')) {
                                        $matching = MMD_Gift_Rules::get_instance()->get_matching_rule_for_product($product->get_id());
                                        if ($matching && !empty($matching['gift_additional_attributes']) && is_array($matching['gift_additional_attributes'])) {
                                            foreach ($matching['gift_additional_attributes'] as $add) {
                                                if (!empty($add['slug']) && !empty($add['values'])) {
                                                    $rule_attr_filter_for_var[$add['slug']] = (array) $add['values'];
                                                }
                                            }
                                        }
                                    }
                                    $variation_data = $this->get_variation_data($gift_product_id, $product->get_id(), $rule_attr_filter_for_var);
                                }
                                $default_variation_id = isset($default_variations[$gift_product_id]) ? $default_variations[$gift_product_id] : '';
                                ?>
                                <tr class="mmd-gift-product-row"
                                    data-product-id="<?php echo esc_attr($gift_product_id); ?>"
                                    data-variable="<?php echo $is_variable ? '1' : '0'; ?>"
                                    data-product-index="<?php echo esc_attr( $product_index ); ?>"
                                    style="<?php echo ($use_pagination && $product_index >= $products_per_page) ? 'display:none;' : ''; ?>"
                                    <?php if ($is_variable): ?>data-variation-info="<?php echo esc_attr(json_encode($variation_data)); ?>"<?php endif; ?>
                                    <?php if ($default_variation_id): ?>data-default-variation="<?php echo esc_attr($default_variation_id); ?>"<?php endif; ?>><?php $product_index++; ?>
                                    <td class="mmd-gift-image-cell">
                                        <?php echo wp_kses_post( $gift_product->get_image('thumbnail') ); ?>
                                    </td>
                                    <td class="mmd-gift-name-cell">
                                        <?php echo esc_html($gift_product->get_name()); ?>
                                    </td>
                                    <td class="mmd-gift-variations-cell">
                                        <?php if ($is_variable): ?>
                                            <div class="mmd-variation-selectors" style="display: none;"></div>
                                        <?php endif; ?>
                                    </td>
                                    <?php if ($allow_quantity === 'yes'): ?>
                                    <td class="mmd-gift-quantity-cell">
                                        <div class="mmd-quantity-control">
                                            <button type="button" class="mmd-quantity-btn mmd-quantity-minus" aria-label="<?php esc_attr_e( 'Decrease quantity', 'incentiv' ); ?>">−</button>
                                            <div class="mmd-quantity-input-wrapper">
                                                <input type="number"
                                                       class="mmd-gift-quantity-input"
                                                       min="<?php echo $gift_selection_type === 'multiple' ? '0' : '1'; ?>"
                                                       max="<?php echo esc_attr($max_quantity); ?>"
                                                       value="<?php echo $gift_selection_type === 'multiple' ? '0' : '1'; ?>"
                                                       data-product-id="<?php echo esc_attr($gift_product_id); ?>">
                                            </div>
                                            <button type="button" class="mmd-quantity-btn mmd-quantity-plus" aria-label="<?php esc_attr_e( 'Increase quantity', 'incentiv' ); ?>">+</button>
                                        </div>
                                    </td>
                                    <?php endif; ?>
                                    <td class="mmd-gift-action-cell">
                                        <?php if ($allow_quantity === 'yes' && $gift_selection_type === 'multiple'): ?>
                                            <span style="color: #666; font-size: 12px;"><?php esc_html_e( 'Choose quantity', 'incentiv' ); ?></span>
                                        <?php else: ?>
                                            <?php if ($is_variable): ?>
                                                <button type="button" class="mmd-gift-select-btn mmd-gift-variable-btn">
                                                    <?php esc_html_e( 'Choose', 'incentiv' ); ?>
                                                </button>
                                            <?php else: ?>
                                                <button type="button" class="mmd-gift-select-btn" data-simple-product="<?php echo esc_attr($gift_product_id); ?>">
                                                    <?php esc_html_e( 'Choose', 'incentiv' ); ?>
                                                </button>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php } ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($use_pagination): ?>
            <div class="mmd-gift-pagination-controls mmd-gift-pagination-bottom">
                <button type="button" class="mmd-pagination-btn mmd-pagination-prev" disabled><?php echo wp_kses_post( __( '&laquo; Previous', 'incentiv' ) ); ?></button>
                <span class="mmd-pagination-info"><?php esc_html_e( 'Page', 'incentiv' ); ?> <span class="mmd-current-page">1</span> <?php esc_html_e( 'of', 'incentiv' ); ?> <span class="mmd-total-pages"><?php echo esc_html( ceil($total_products / $products_per_page) ); ?></span></span>
                <button type="button" class="mmd-pagination-btn mmd-pagination-next"><?php echo wp_kses_post( __( 'Next &raquo;', 'incentiv' ) ); ?></button>
            </div>
            <?php endif; ?>

        </div>

        <script>
        jQuery(document).ready(function($) {
            // Ensure hidden fields are in the correct form
            var form = $('form.cart');
            if (form.length) {
                <?php if ($gift_selection_type === 'multiple'): ?>
                    if (!form.find('#mmd_selected_gifts_container').length) {
                        form.append('<div id="mmd_selected_gifts_container"></div>');
                    }
                <?php else: ?>
                    if (!form.find('#mmd_selected_gift_product').length) {
                        form.append('<input type="hidden" name="mmd_selected_gift_product" id="mmd_selected_gift_product" value="">');
                    }
                    if (!form.find('#mmd_selected_gift_variation').length) {
                        form.append('<input type="hidden" name="mmd_selected_gift_variation" id="mmd_selected_gift_variation" value="">');
                    }
                    if (!form.find('#mmd_selected_gift_attributes').length) {
                        form.append('<input type="hidden" name="mmd_selected_gift_attributes" id="mmd_selected_gift_attributes" value="">');
                    }
                <?php endif; ?>
            }
        });
        </script>
        <?php
    }

    /**
     * Display gift selection inside Unico Elementor add-to-cart widget.
     * Receives the product object from the widget action hook.
     *
     * @param WC_Product $widget_product The product from the Elementor widget.
     */
    public function display_gift_selection_for_widget($widget_product = null) {
        // Shoppers blocked by an add-on (filter `mmd_promotions_blocked`) get no promotions.
        if (MMD_Modules::promotions_blocked('gifts')) {
            return;
        }

        if ($widget_product) {
            global $product;
            $original_product = $product;
            $product = $widget_product;
            $this->display_gift_selection();
            $product = $original_product;
        } else {
            $this->display_gift_selection();
        }
    }

    /**
     * Get variation data for a product (reusable method for both AJAX and preloading)
     */
    private function get_variation_data($product_id, $parent_product_id, $rule_attr_filter = array()) {
        $product = wc_get_product($product_id);

        if (!$product || !$product->is_type('variable')) {
            return array('attributes' => array(), 'variations' => array());
        }

        // Get variation limiting settings from parent product
        $limit_variations = get_post_meta($parent_product_id, '_mmd_gift_limit_variations', true);
        $allowed_variations = get_post_meta($parent_product_id, '_mmd_gift_allowed_variations', true);
        $allowed_variations = !empty($allowed_variations) ? $allowed_variations : array();

        // Build a slug-whitelist per attribute from $rule_attr_filter (which
        // maps attribute slug => list of term IDs). Any variation whose value
        // for that attribute isn't in the whitelist (and isn't "any") is
        // dropped, so the customer can only pick gifts that satisfy the
        // wizard's "Filter by Attribute" setting.
        $allowed_slugs_by_attr = array();
        foreach ((array) $rule_attr_filter as $attr_slug => $term_ids) {
            if (empty($attr_slug) || empty($term_ids)) continue;
            $slugs = array();
            foreach ((array) $term_ids as $tid) {
                $term = get_term((int) $tid, $attr_slug);
                if ($term && !is_wp_error($term)) {
                    $slugs[] = $term->slug;
                }
            }
            if (!empty($slugs)) $allowed_slugs_by_attr[$attr_slug] = $slugs;
        }

        // Get parent product to filter gift attributes based on what the customer is buying
        $parent_product = wc_get_product($parent_product_id);
        $parent_product_attributes = array();

        if ($parent_product && $parent_product->is_type('variable')) {
            // Get attribute slugs from parent variable product
            foreach ($parent_product->get_variation_attributes() as $attr_name => $attr_values) {
                if (!empty($attr_values)) {
                    $parent_product_attributes[$attr_name] = $attr_values;
                }
            }
        } elseif ($parent_product) {
            // Simple product - get its attributes
            foreach ($parent_product->get_attributes() as $attr) {
                if ($attr->get_variation()) {
                    $attr_name = $attr->get_name();
                    $parent_product_attributes[$attr_name] = $attr->get_options();
                }
            }
        }

        // Get all available variations
        $available_variations = $product->get_available_variations();
        $filtered_variations = array();

        // Filter variations based on backend settings
        foreach ($available_variations as $variation) {
            // If limiting is enabled and allowed variations are specified, check if this variation is allowed
            if ($limit_variations == '1' && !empty($allowed_variations)) {
                if (!in_array($variation['variation_id'], $allowed_variations)) {
                    continue;
                }
            }

            // Normalize attribute keys - decode URL-encoded attribute names
            // This ensures consistency between selector attribute names and variation attribute names
            $normalized_attributes = array();
            foreach ($variation['attributes'] as $attr_key => $attr_value) {
                // Decode URL-encoded keys (e.g., attribute_pa_%d7%9e%d7%a9%d7%a7%d7%9c -> attribute_pa_משקל)
                $decoded_key = urldecode($attr_key);
                $normalized_attributes[$decoded_key] = $attr_value;
            }
            $variation['attributes'] = $normalized_attributes;

            // Drop variations that don't match the rule's attribute filter.
            // Empty value ("any") passes; the customer will pick a value
            // from the dropdown's restricted option list.
            if (!empty($allowed_slugs_by_attr)) {
                $passes = true;
                foreach ($allowed_slugs_by_attr as $attr_slug => $allowed_slugs) {
                    $key_with_prefix = 'attribute_' . $attr_slug;
                    $val = '';
                    if (isset($normalized_attributes[$key_with_prefix])) {
                        $val = $normalized_attributes[$key_with_prefix];
                    } elseif (isset($normalized_attributes[$attr_slug])) {
                        $val = $normalized_attributes[$attr_slug];
                    }
                    if ($val !== '' && !in_array($val, $allowed_slugs, true)) {
                        $passes = false;
                        break;
                    }
                }
                if (!$passes) continue;
            }

            $filtered_variations[] = $variation;
        }

        // Get product attributes and filter to show only those that need selection
        $attributes = $product->get_variation_attributes();
        $attribute_data = array();

        // If there's only one variation, show all attributes (customer needs to select them)
        $total_variations = count($filtered_variations);
        $show_all_attributes = ($total_variations === 1);

        foreach ($attributes as $attribute_name => $options) {
            // Get proper attribute label
            $attribute_label = wc_attribute_label($attribute_name);

            // If label is same as name (slug), try to get it from taxonomy
            if ($attribute_label === $attribute_name && strpos($attribute_name, 'pa_') === 0) {
                $taxonomy = $attribute_name;
                $taxonomy_object = get_taxonomy($taxonomy);
                if ($taxonomy_object && isset($taxonomy_object->labels->singular_name)) {
                    $attribute_label = $taxonomy_object->labels->singular_name;
                } else {
                    // Fallback: try to get from attribute taxonomy
                    $attribute_taxonomy = wc_get_attribute(wc_attribute_taxonomy_id_by_name($attribute_name));
                    if ($attribute_taxonomy && !empty($attribute_taxonomy->name)) {
                        $attribute_label = $attribute_taxonomy->name;
                    }
                }
            }

            $available_options = array();
            $values_in_variations = array();

            // WooCommerce uses 'attribute_' prefix in variation data
            $attribute_key_variations = 'attribute_' . $attribute_name;

            // Collect all values for this attribute across filtered variations
            foreach ($filtered_variations as $variation) {
                $option_value = null;

                // Try with 'attribute_' prefix first (standard WooCommerce format)
                if (isset($variation['attributes'][$attribute_key_variations])) {
                    $option_value = $variation['attributes'][$attribute_key_variations];
                }
                // Try without prefix (some custom attributes)
                elseif (isset($variation['attributes'][$attribute_name])) {
                    $option_value = $variation['attributes'][$attribute_name];
                }

                // Track all values including empty
                $values_in_variations[] = $option_value;

                // Skip empty values for available options
                if (empty($option_value) || $option_value === '') {
                    continue;
                }

                if (!in_array($option_value, $available_options)) {
                    $available_options[] = $option_value;
                }
            }

            // Check if this attribute needs customer selection
            // An attribute needs selection if:
            // 1. There's only one variation (show all attributes), OR
            // 2. It has multiple different values across variations, OR
            // 3. Some variations have empty/null values (meaning customer must select)
            $unique_values = array_unique(array_filter($values_in_variations, function($v) {
                return $v !== '' && $v !== null;
            }));
            $has_empty_values = in_array('', $values_in_variations, true) || in_array(null, $values_in_variations, true);

            // Only show this attribute if it needs customer selection
            // Skip if multiple variations exist AND all have the same pre-filled value (not applicable for single variation)
            if (!$show_all_attributes && count($unique_values) === 1 && !$has_empty_values) {
                continue; // This attribute is pre-filled with same value everywhere, skip it
            }

            // If variations have empty values (customer must select), use product's attribute options
            // Filter options based on the parent product being purchased
            if (empty($available_options) && $has_empty_values && !empty($options)) {
                // Check if parent product has this same attribute
                if (isset($parent_product_attributes[$attribute_name]) && !empty($parent_product_attributes[$attribute_name])) {
                    // Filter gift options to only show values that exist in parent product
                    $parent_values = $parent_product_attributes[$attribute_name];
                    $filtered_options = array_intersect($options, $parent_values);

                    if (!empty($filtered_options)) {
                        $available_options = $filtered_options;
                    } else {
                        // No intersection, use all gift product options
                        $available_options = $options;
                    }
                } else {
                    // Parent doesn't have this attribute, show all gift options
                    $available_options = $options;
                }
            }

            // Only add attributes that have available options
            if (empty($available_options)) {
                continue;
            }

            // Get proper term names for options
            $options_with_labels = array();

            // Get the taxonomy name for this attribute
            $taxonomy = '';
            if (strpos($attribute_name, 'pa_') === 0) {
                // Global attribute
                $taxonomy = $attribute_name;
            }

            if (!empty($taxonomy)) {
                // For global attributes, get term names
                foreach ($available_options as $option_value) {
                    $term = get_term_by('slug', $option_value, $taxonomy);
                    if ($term) {
                        $options_with_labels[] = array(
                            'value' => $option_value,
                            'label' => $term->name
                        );
                    } else {
                        // Fallback to raw value if term not found
                        $options_with_labels[] = array(
                            'value' => $option_value,
                            'label' => $option_value
                        );
                    }
                }
            } else {
                // Custom product attribute - use raw values
                foreach ($available_options as $option_value) {
                    $options_with_labels[] = array(
                        'value' => $option_value,
                        'label' => $option_value
                    );
                }
            }

            if (!empty($options_with_labels)) {
                $attribute_data[] = array(
                    'name' => $attribute_key_variations,
                    'label' => $attribute_label,
                    'options' => $options_with_labels
                );
            }
        }

        // FALLBACK: If no attributes found but we have variations, extract attributes from variations
        if (empty($attribute_data) && !empty($filtered_variations)) {
            $attributes_from_variations = array();

            foreach ($filtered_variations as $variation) {
                foreach ($variation['attributes'] as $attr_key => $attr_value) {
                    // Get attribute name without 'attribute_' prefix for labeling
                    $attr_name_clean = str_replace('attribute_', '', $attr_key);
                    $attr_label = wc_attribute_label($attr_name_clean);

                    // If label is same as name (slug), try to get it from taxonomy
                    if ($attr_label === $attr_name_clean && strpos($attr_name_clean, 'pa_') === 0) {
                        $taxonomy_test = $attr_name_clean;
                        $taxonomy_object = get_taxonomy($taxonomy_test);
                        if ($taxonomy_object && isset($taxonomy_object->labels->singular_name)) {
                            $attr_label = $taxonomy_object->labels->singular_name;
                        } else {
                            // Fallback: try to get from attribute taxonomy
                            $attribute_taxonomy = wc_get_attribute(wc_attribute_taxonomy_id_by_name($attr_name_clean));
                            if ($attribute_taxonomy && !empty($attribute_taxonomy->name)) {
                                $attr_label = $attribute_taxonomy->name;
                            }
                        }
                    }

                    // Get taxonomy name for global attributes
                    $taxonomy = strpos($attr_name_clean, 'pa_') === 0 ? $attr_name_clean : '';

                    // Initialize if not exists
                    if (!isset($attributes_from_variations[$attr_key])) {
                        $attributes_from_variations[$attr_key] = array(
                            'name' => $attr_key,
                            'label' => $attr_label,
                            'options' => array(),
                            'values' => array(),
                            'taxonomy' => $taxonomy
                        );
                    }

                    // Track all values including empty
                    $attributes_from_variations[$attr_key]['values'][] = $attr_value;

                    // Skip empty values for available options
                    if (empty($attr_value) || $attr_value === '') {
                        continue;
                    }

                    // Add unique option value
                    if (!in_array($attr_value, $attributes_from_variations[$attr_key]['options'])) {
                        $attributes_from_variations[$attr_key]['options'][] = $attr_value;
                    }
                }
            }

            // Process attributes - filter out pre-filled ones and add labels
            foreach ($attributes_from_variations as $attr_key => $attr_info) {
                // Check if this attribute needs customer selection
                $unique_values = array_unique(array_filter($attr_info['values'], function($v) {
                    return $v !== '' && $v !== null;
                }));
                $has_empty_values = in_array('', $attr_info['values'], true) || in_array(null, $attr_info['values'], true);

                // Skip if pre-filled with same value everywhere (but not for single variation products)
                if (!$show_all_attributes && count($unique_values) === 1 && !$has_empty_values) {
                    continue;
                }

                // Get proper labels for options
                $options_with_labels = array();
                foreach ($attr_info['options'] as $option_value) {
                    if (!empty($attr_info['taxonomy'])) {
                        // Global attribute - get term name
                        $term = get_term_by('slug', $option_value, $attr_info['taxonomy']);
                        if ($term) {
                            $options_with_labels[] = array(
                                'value' => $option_value,
                                'label' => $term->name
                            );
                        } else {
                            $options_with_labels[] = array(
                                'value' => $option_value,
                                'label' => $option_value
                            );
                        }
                    } else {
                        // Custom attribute - use raw value
                        $options_with_labels[] = array(
                            'value' => $option_value,
                            'label' => $option_value
                        );
                    }
                }

                if (!empty($options_with_labels)) {
                    $attribute_data[] = array(
                        'name' => $attr_info['name'],
                        'label' => $attr_info['label'],
                        'options' => $options_with_labels
                    );
                }
            }
        }

        return array(
            'attributes' => $attribute_data,
            'variations' => $filtered_variations
        );
    }

    /**
     * AJAX get variations (uses the reusable method)
     */
    public function ajax_get_variations() {
        self::debug_log('MMD: ajax_get_variations called');
        self::debug_log('MMD: POST data: ' . print_r($_POST, true));

        // Check nonce
        if (!check_ajax_referer('mmd_gift_nonce', 'nonce', false)) {
            self::debug_log('MMD: Nonce verification failed');
            wp_send_json_error(array('message' => 'Nonce verification failed'));
            return;
        }

        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
        $parent_product_id = isset($_POST['parent_product_id']) ? intval($_POST['parent_product_id']) : 0;

        self::debug_log('MMD: Product ID: ' . $product_id . ', Parent ID: ' . $parent_product_id);

        if (!$product_id) {
            self::debug_log('MMD: No product ID provided');
            wp_send_json_error(array('message' => 'Invalid product ID'));
            return;
        }

        $product = wc_get_product($product_id);

        if (!$product) {
            self::debug_log('MMD: Product not found: ' . $product_id);
            wp_send_json_error(array('message' => 'Product not found'));
            return;
        }

        if (!$product->is_type('variable')) {
            self::debug_log('MMD: Product is not variable: ' . $product_id . ' (type: ' . $product->get_type() . ')');
            wp_send_json_error(array('message' => 'Product is not variable'));
            return;
        }

        // Use the reusable method to get variation data
        $response_data = $this->get_variation_data($product_id, $parent_product_id);

        self::debug_log('MMD: Sending successful response with ' . count($response_data['attributes']) . ' attributes and ' . count($response_data['variations']) . ' variations');

        wp_send_json_success($response_data);
    }

    /**
     * Capture gift data from AJAX requests and store in session
     * This ensures gift data is available even during AJAX add to cart
     */
    public function capture_ajax_gift_data($passed, $product_id, $quantity) {
        self::debug_log('MMD Gift: capture_ajax_gift_data called for product ' . $product_id);
        self::debug_log('MMD Gift: Is AJAX: ' . (defined('DOING_AJAX') && DOING_AJAX ? 'YES' : 'NO'));

        // Check all gift-related keys in the request
        $gift_keys_found = array();
        foreach (array_merge($_POST, $_REQUEST) as $key => $value) {
            if (strpos($key, 'mmd_') === 0 || strpos($key, 'gift_') === 0) {
                $gift_keys_found[$key] = is_array($value) ? 'Array' : substr($value, 0, 50);
            }
        }
        self::debug_log('MMD Gift: Gift-related keys in request: ' . print_r($gift_keys_found, true));

        // Check if this is an AJAX request
        if (defined('DOING_AJAX') && DOING_AJAX) {
            // Check if gift data is present in the request (try both $_REQUEST and $_POST)
            $gift_product = '';
            $gift_variation = '';
            $gift_attributes = '';

            // Try to get gift product ID
            if (isset($_POST['mmd_selected_gift_product'])) {
                $gift_product = sanitize_text_field($_POST['mmd_selected_gift_product']);
            } elseif (isset($_REQUEST['mmd_selected_gift_product'])) {
                $gift_product = sanitize_text_field($_REQUEST['mmd_selected_gift_product']);
            }

            // Try to get gift variation ID
            if (isset($_POST['mmd_selected_gift_variation'])) {
                $gift_variation = sanitize_text_field($_POST['mmd_selected_gift_variation']);
            } elseif (isset($_REQUEST['mmd_selected_gift_variation'])) {
                $gift_variation = sanitize_text_field($_REQUEST['mmd_selected_gift_variation']);
            }

            // Try to get gift attributes JSON
            if (isset($_POST['mmd_selected_gift_attributes'])) {
                $gift_attributes = sanitize_text_field($_POST['mmd_selected_gift_attributes']);
            } elseif (isset($_REQUEST['mmd_selected_gift_attributes'])) {
                $gift_attributes = sanitize_text_field($_REQUEST['mmd_selected_gift_attributes']);
            }

            if (!empty($gift_product)) {
                self::debug_log('MMD Gift: AJAX gift data captured - Product: ' . $gift_product . ', Variation: ' . $gift_variation);
                self::debug_log('MMD Gift: Attributes JSON length: ' . strlen($gift_attributes));

                // Store in session for use in handle_gift_selection
                WC()->session->set('mmd_temp_gift_product', $gift_product);
                WC()->session->set('mmd_temp_gift_variation', $gift_variation);
                WC()->session->set('mmd_temp_gift_attributes', $gift_attributes);
                self::debug_log('MMD Gift: Stored gift data in session successfully');
            } else {
                self::debug_log('MMD Gift: WARNING - No gift product found in AJAX request, but this product may have gifts enabled');
                self::debug_log('MMD Gift: This could be because:');
                self::debug_log('MMD Gift: 1. User did not select a gift');
                self::debug_log('MMD Gift: 2. Gift data was lost during AJAX transmission');
                self::debug_log('MMD Gift: 3. This product does not have gifts enabled');
            }
        } else {
            self::debug_log('MMD Gift: Not an AJAX request - gift data will be read directly from POST in handle_gift_selection');
        }

        return $passed;
    }

    /**
     * Preserve gift selection data in cart item data
     * This allows the gift data to be passed to handle_gift_selection
     */
    public function preserve_gift_data_in_session($cart_item_data, $product_id, $variation_id) {
        self::debug_log('MMD Gift: preserve_gift_data_in_session called');

        // Check if we have gift data in session
        $gift_product = WC()->session->get('mmd_temp_gift_product');
        $gift_variation = WC()->session->get('mmd_temp_gift_variation');
        $gift_attributes = WC()->session->get('mmd_temp_gift_attributes');

        if ($gift_product) {
            self::debug_log('MMD Gift: Found gift data in session, adding to $_POST for handle_gift_selection');

            // Add to $_POST so handle_gift_selection can find it
            $_POST['mmd_selected_gift_product'] = $gift_product;
            $_POST['mmd_selected_gift_variation'] = $gift_variation;
            $_POST['mmd_selected_gift_attributes'] = $gift_attributes;

            // Clear from session
            WC()->session->__unset('mmd_temp_gift_product');
            WC()->session->__unset('mmd_temp_gift_variation');
            WC()->session->__unset('mmd_temp_gift_attributes');
        }

        return $cart_item_data;
    }

    /**
     * Handle gift selection when adding to cart (LEGACY - for non-AJAX scenarios)
     * For AJAX scenarios, the chained ajax_add_gift_to_cart method handles it
     */
    public function handle_gift_selection($cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data) {
        // Skip if this item IS a gift (prevent recursion)
        if (isset($cart_item_data['mmd_is_gift']) || isset($cart_item_data['mmd_cart_based_gift'])) {
            return;
        }

        // For AJAX requests, check if gift data is in session (set by capture_ajax_gift_data)
        if (defined('DOING_AJAX') && DOING_AJAX) {
            $session_gift = WC()->session ? WC()->session->get('mmd_temp_gift_product') : '';
            if (empty($session_gift) && empty($_POST['mmd_selected_gift_product'])) {
                // No gift data available - the chained JS method will handle it
                return;
            }
            // Gift data available in session/POST - process it here
        }

        // Check if gifts are enabled - either via product meta or global rules
        $gift_settings = $this->get_gift_settings($product_id);
        if (!$gift_settings) {
            self::debug_log('MMD Gift: No gift settings found for product ' . $product_id);
            self::debug_log('===== MMD Gift: handle_gift_selection END (not enabled) =====');
            return;
        }

        $gift_type = $gift_settings['gift_type'];
        $selection_type = isset($gift_settings['gift_selection_type']) ? $gift_settings['gift_selection_type'] : 'single';

        // Prevent duplicate gifts: check if a gift for this parent is already in cart
        foreach (WC()->cart->get_cart() as $existing_key => $existing_item) {
            if (isset($existing_item['mmd_is_gift']) && $existing_item['mmd_is_gift']
                && isset($existing_item['mmd_parent_key']) && $existing_item['mmd_parent_key'] === $cart_item_key) {
                return;
            }
        }

        if ($selection_type === 'multiple') {
            // Handle multiple gift selection
            $selected_gifts = isset($_POST['mmd_selected_gift_products']) && is_array($_POST['mmd_selected_gift_products'])
                ? $_POST['mmd_selected_gift_products']
                : array();

            self::debug_log('MMD Gift: Multiple selection - gifts: ' . print_r($selected_gifts, true));

            if (empty($selected_gifts)) {
                self::debug_log('MMD Gift: No gifts selected in multiple mode');
                self::debug_log('===== MMD Gift: handle_gift_selection END (no selection) =====');
                return;
            }

            foreach ($selected_gifts as $gift_data) {
                $gift_product_id = isset($gift_data['product_id']) ? intval($gift_data['product_id']) : 0;
                $gift_variation_id = isset($gift_data['variation_id']) ? intval($gift_data['variation_id']) : 0;
                $gift_attributes_json = isset($gift_data['attributes']) ? $gift_data['attributes'] : '';

                if (!$gift_product_id) {
                    continue;
                }

                self::debug_log('MMD Gift: Adding gift - Product: ' . $gift_product_id . ', Variation: ' . $gift_variation_id);

                // Parse attributes from JSON if present
                $gift_attributes = array();
                if (!empty($gift_attributes_json)) {
                    $decoded = json_decode(urldecode($gift_attributes_json), true);
                    if (is_array($decoded)) {
                        // Attribute values are already decoded in JS, use them as-is
                        $gift_attributes = $decoded;
                        self::debug_log('MMD Gift: Parsed gift attributes: ' . print_r($gift_attributes, true));
                    }
                }

                $gift_cart_data = array(
                    'mmd_is_gift' => true,
                    'mmd_parent_key' => $cart_item_key,
                    'mmd_gift_type' => $gift_type,
                    'mmd_parent_product_id' => $product_id
                );

                if ($gift_variation_id) {
                    $variation_product = wc_get_product($gift_variation_id);
                    if (!$variation_product) {
                        self::debug_log('MMD Gift: ERROR - Variation product not found: ' . $gift_variation_id);
                        continue;
                    }

                    // CRITICAL: Merge variation attributes with selected attributes
                    // The variation may have "any" attributes (empty values), which need to be filled
                    // with the actual selected values from the frontend to pass WooCommerce validation
                    $variation_attributes = $variation_product->get_variation_attributes();

                    // Merge with selected attributes - selected attributes take precedence for empty variation attributes
                    foreach ($gift_attributes as $attr_key => $attr_value) {
                        // If the variation has an "any" value (empty) for this attribute, use the selected value
                        if (!isset($variation_attributes[$attr_key]) || $variation_attributes[$attr_key] === '') {
                            $variation_attributes[$attr_key] = $attr_value;
                        }
                    }

                    self::debug_log('MMD Gift: Selected attributes from frontend: ' . print_r($gift_attributes, true));
                    self::debug_log('MMD Gift: Variation product attributes: ' . print_r($variation_product->get_variation_attributes(), true));
                    self::debug_log('MMD Gift: Merged attributes for cart: ' . print_r($variation_attributes, true));

                    $result = WC()->cart->add_to_cart(
                        $variation_product->get_parent_id(),
                        $quantity,
                        $gift_variation_id,
                        $variation_attributes,
                        $gift_cart_data
                    );
                    self::debug_log('MMD Gift: Variation added to cart. Result: ' . ($result ? 'SUCCESS' : 'FAILED'));
                } else {
                    $result = WC()->cart->add_to_cart($gift_product_id, $quantity, 0, array(), $gift_cart_data);
                    self::debug_log('MMD Gift: Simple product added to cart. Result: ' . ($result ? 'SUCCESS' : 'FAILED'));
                }
            }
        } else {
            // Handle single gift selection
            $selected_gift = isset($_POST['mmd_selected_gift_product']) ? intval($_POST['mmd_selected_gift_product']) : 0;
            $selected_variation = isset($_POST['mmd_selected_gift_variation']) ? intval($_POST['mmd_selected_gift_variation']) : 0;
            $selected_attributes_json = isset($_POST['mmd_selected_gift_attributes']) ? $_POST['mmd_selected_gift_attributes'] : '';

            self::debug_log('MMD Gift: Single selection - product: ' . $selected_gift . ', variation: ' . $selected_variation);
            self::debug_log('MMD Gift: Attributes JSON: ' . $selected_attributes_json);

            if (!$selected_gift) {
                self::debug_log('MMD Gift: No gift product selected in single mode - POST mmd_selected_gift_product is missing or 0');
                self::debug_log('===== MMD Gift: handle_gift_selection END (no selection) =====');
                return;
            }

            // Parse attributes from JSON
            $selected_attributes = array();
            if (!empty($selected_attributes_json)) {
                self::debug_log('MMD Gift: Raw attributes JSON from POST: ' . $selected_attributes_json);

                // URL-decode the JSON string first (it was URL-encoded in JS to preserve special characters)
                $decoded_json = urldecode($selected_attributes_json);
                self::debug_log('MMD Gift: URL-decoded JSON string: ' . $decoded_json);

                $decoded = json_decode($decoded_json, true);
                if (is_array($decoded)) {
                    // Attribute values are already decoded in JS, use them as-is
                    $selected_attributes = $decoded;
                    self::debug_log('MMD Gift: Successfully parsed attributes: ' . print_r($selected_attributes, true));
                } else {
                    self::debug_log('MMD Gift: ERROR - Failed to JSON-decode attributes.');
                    self::debug_log('MMD Gift: - Raw JSON: ' . $selected_attributes_json);
                    self::debug_log('MMD Gift: - Decoded JSON: ' . $decoded_json);
                    self::debug_log('MMD Gift: - JSON decode error: ' . json_last_error_msg());
                }
            } else {
                self::debug_log('MMD Gift: WARNING - No attributes JSON in POST (mmd_selected_gift_attributes is empty or not set)');
            }

            self::debug_log('MMD Gift: Adding gift to cart - product: ' . $selected_gift . ', variation: ' . $selected_variation);

            $gift_cart_data = array(
                'mmd_is_gift' => true,
                'mmd_parent_key' => $cart_item_key,
                'mmd_gift_type' => $gift_type,
                'mmd_parent_product_id' => $product_id
            );

            if ($selected_variation) {
                $variation_product = wc_get_product($selected_variation);
                if (!$variation_product) {
                    self::debug_log('MMD Gift: ERROR - Variation product not found: ' . $selected_variation);
                    self::debug_log('===== MMD Gift: handle_gift_selection END (variation not found) =====');
                    return;
                }

                // CRITICAL: Merge variation attributes with selected attributes
                // The variation may have "any" attributes (empty values), which need to be filled
                // with the actual selected values from the frontend to pass WooCommerce validation
                $variation_attributes = $variation_product->get_variation_attributes();

                self::debug_log('MMD Gift: BEFORE MERGE - Selected attributes from frontend: ' . print_r($selected_attributes, true));
                self::debug_log('MMD Gift: BEFORE MERGE - Variation product attributes: ' . print_r($variation_attributes, true));
                self::debug_log('MMD Gift: BEFORE MERGE - Count of selected attributes: ' . count($selected_attributes));

                // Check if selected_attributes is empty
                if (empty($selected_attributes)) {
                    self::debug_log('MMD Gift: WARNING - Selected attributes is EMPTY! This will cause validation failure.');
                    self::debug_log('MMD Gift: Checking if attributes are in POST with different keys...');

                    // Check for attributes with 'gift_' prefix
                    foreach ($_POST as $key => $value) {
                        if (strpos($key, 'gift_attribute_') === 0 || strpos($key, 'gift_attr') === 0) {
                            self::debug_log('MMD Gift: Found POST key: ' . $key . ' = ' . $value);
                        }
                    }

                    // Try to reconstruct attributes from variation if possible
                    // This is a fallback - ideally we should have the selected attributes
                    $all_post_attrs = array();
                    foreach ($_POST as $key => $value) {
                        if (strpos($key, 'attribute_') === 0) {
                            $all_post_attrs[$key] = $value;
                        }
                    }
                    self::debug_log('MMD Gift: All attribute_* keys in POST: ' . print_r($all_post_attrs, true));
                }

                // Merge with selected attributes - selected attributes take precedence for empty variation attributes
                foreach ($selected_attributes as $attr_key => $attr_value) {
                    // If the variation has an "any" value (empty) for this attribute, use the selected value
                    if (!isset($variation_attributes[$attr_key]) || $variation_attributes[$attr_key] === '') {
                        $variation_attributes[$attr_key] = $attr_value;
                        self::debug_log('MMD Gift: Merged attribute ' . $attr_key . ' = ' . $attr_value);
                    } else {
                        self::debug_log('MMD Gift: Skipped merging ' . $attr_key . ' (variation already has value: ' . $variation_attributes[$attr_key] . ')');
                    }
                }

                self::debug_log('MMD Gift: AFTER MERGE - Adding variation to cart - Parent: ' . $variation_product->get_parent_id() . ', Variation: ' . $selected_variation);
                self::debug_log('MMD Gift: AFTER MERGE - Final merged attributes for cart: ' . print_r($variation_attributes, true));

                // Check if any required attributes are still empty
                $has_empty_attrs = false;
                foreach ($variation_attributes as $attr_key => $attr_value) {
                    if ($attr_value === '' || $attr_value === null) {
                        self::debug_log('MMD Gift: WARNING - Attribute ' . $attr_key . ' is EMPTY after merge!');
                        $has_empty_attrs = true;
                    }
                }

                if ($has_empty_attrs) {
                    self::debug_log('MMD Gift: ERROR - Cannot add variation to cart with empty required attributes.');
                    self::debug_log('MMD Gift: This will fail WooCommerce validation.');
                }

                $result = WC()->cart->add_to_cart(
                    $variation_product->get_parent_id(),
                    $quantity,
                    $selected_variation,
                    $variation_attributes,
                    $gift_cart_data
                );
                self::debug_log('MMD Gift: Variation added to cart. Result: ' . ($result ? 'SUCCESS (' . $result . ')' : 'FAILED'));

                if (!$result) {
                    self::debug_log('MMD Gift: ADD TO CART FAILED - Dumping all diagnostic info:');
                    self::debug_log('MMD Gift: - Parent product ID: ' . $variation_product->get_parent_id());
                    self::debug_log('MMD Gift: - Variation ID: ' . $selected_variation);
                    self::debug_log('MMD Gift: - Variation object type: ' . get_class($variation_product));
                    self::debug_log('MMD Gift: - Variation is_purchasable: ' . ($variation_product->is_purchasable() ? 'YES' : 'NO'));
                    self::debug_log('MMD Gift: - Variation is_in_stock: ' . ($variation_product->is_in_stock() ? 'YES' : 'NO'));
                    self::debug_log('MMD Gift: - Variation stock_status: ' . $variation_product->get_stock_status());
                }
            } else {
                // Check if $selected_gift is actually a variation ID (happens with attribute-filtered gifts)
                $gift_check_product = wc_get_product($selected_gift);
                if ($gift_check_product && $gift_check_product->is_type('variation')) {
                    self::debug_log('MMD Gift: Detected variation ID passed as product ID: ' . $selected_gift);
                    $variation_product = $gift_check_product;
                    $actual_variation_id = $selected_gift;
                    $actual_parent_id = $variation_product->get_parent_id();

                    $variation_attributes = $variation_product->get_variation_attributes();

                    // Merge with any selected attributes from POST
                    foreach ($selected_attributes as $attr_key => $attr_value) {
                        if (!isset($variation_attributes[$attr_key]) || $variation_attributes[$attr_key] === '') {
                            $variation_attributes[$attr_key] = $attr_value;
                        }
                    }

                    self::debug_log('MMD Gift: Adding variation via parent route - Parent: ' . $actual_parent_id . ', Variation: ' . $actual_variation_id);
                    $result = WC()->cart->add_to_cart(
                        $actual_parent_id,
                        $quantity,
                        $actual_variation_id,
                        $variation_attributes,
                        $gift_cart_data
                    );
                    self::debug_log('MMD Gift: Variation added via parent route. Result: ' . ($result ? 'SUCCESS (' . $result . ')' : 'FAILED'));
                } else {
                    self::debug_log('MMD Gift: Adding simple product to cart - Product: ' . $selected_gift);
                    $result = WC()->cart->add_to_cart($selected_gift, $quantity, 0, array(), $gift_cart_data);
                    self::debug_log('MMD Gift: Simple product added to cart. Result: ' . ($result ? 'SUCCESS (' . $result . ')' : 'FAILED'));
                }
            }
        }

        self::debug_log('===== MMD Gift: handle_gift_selection END (completed) =====');
    }

    /**
     * Apply gift pricing
     */
    public function apply_gift_pricing($cart) {
        // Shoppers blocked by an add-on (filter `mmd_promotions_blocked`) get no promotions.
        if (MMD_Modules::promotions_blocked('gifts')) {
            return;
        }

        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }

        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            if (isset($cart_item['mmd_is_gift']) && $cart_item['mmd_is_gift']) {
                $gift_type = isset($cart_item['mmd_gift_type']) ? $cart_item['mmd_gift_type'] : '';
                $original_price = $cart_item['data']->get_price();

                $parent_product_id = isset($cart_item['mmd_parent_product_id']) ? $cart_item['mmd_parent_product_id'] : 0;

                // If gift_type is empty, resolve it from gift settings (supports rules)
                if (empty($gift_type) && $parent_product_id) {
                    $gift_settings = $this->get_gift_settings($parent_product_id);
                    $gift_type = $gift_settings ? $gift_settings['gift_type'] : 'free';
                }

                // Default to free if still empty
                if (empty($gift_type)) {
                    $gift_type = 'free';
                }

                $gift_discount = 0;
                if ($parent_product_id && ($gift_type === 'discount' || $gift_type === 'fixed_discount')) {
                    $gift_settings = isset($gift_settings) ? $gift_settings : $this->get_gift_settings($parent_product_id);
                    $gift_discount = $gift_settings ? $gift_settings['gift_discount'] : get_post_meta($parent_product_id, '_mmd_gift_discount', true);
                }

                if ($gift_type === 'free') {
                    $cart_item['data']->set_price(0);
                } elseif ($gift_type === 'discount') {
                    $discounted_price = $original_price * (1 - ($gift_discount / 100));
                    $cart_item['data']->set_price($discounted_price);
                } elseif ($gift_type === 'fixed_discount') {
                    $discounted_price = max(0, $original_price - $gift_discount);
                    $cart_item['data']->set_price($discounted_price);
                }
            }
        }
    }

    /**
     * Display gift badge in cart
     */
    public function display_gift_in_cart($item_data, $cart_item) {
        if (isset($cart_item['mmd_is_gift']) && $cart_item['mmd_is_gift']) {
            $gift_type = $cart_item['mmd_gift_type'];

            $label = '';
            if ($gift_type === 'free') {
                $label = __( '🎁 Free gift', 'incentiv' );
            } else {
                $label = __( '🎁 Discounted product', 'incentiv' );
            }

            $item_data[] = array(
                'key' => '',
                'value' => '<span style="color: #7c3aed; font-weight: bold; background: #f4eeff; padding: 4px 8px; border-radius: 4px; display: inline-block;">' . $label . '</span>'
            );
        }

        return $item_data;
    }

    /**
     * Display gift badge in cart name
     */
    public function display_gift_badge_in_cart($name, $cart_item, $cart_item_key) {
        $is_product_page_gift = isset($cart_item['mmd_is_gift']) && $cart_item['mmd_is_gift'];
        $is_cart_based_gift = isset($cart_item['mmd_cart_based_gift']) && $cart_item['mmd_cart_based_gift'];

        if ($is_product_page_gift || $is_cart_based_gift) {
            // Get styling options
            $styling = array();
            if (class_exists('MMD_Gift_Rules')) {
                $gift_rules = MMD_Gift_Rules::get_instance();
                $styling = $gift_rules->get_styling_options();
            }

            $bg_color_1 = isset($styling['gift_box_bg_color_1']) ? $styling['gift_box_bg_color_1'] : '#7c3aed';
            $bg_color_2 = isset($styling['gift_box_bg_color_2']) ? $styling['gift_box_bg_color_2'] : '#a855f7';
            $text_color = isset($styling['gift_box_text_color']) ? $styling['gift_box_text_color'] : '#ffffff';
            $badge_text = isset($styling['gift_badge_text']) ? $styling['gift_badge_text'] : __( '🎁 Gift', 'incentiv' );

            // Custom badge for cart-based gifts showing discount type
            if ($is_cart_based_gift) {
                $gift_type = isset($cart_item['mmd_gift_type']) ? $cart_item['mmd_gift_type'] : 'free';
                if ($gift_type === 'free') {
                    $badge_text = __( '🎁 Free gift', 'incentiv' );
                } elseif ($gift_type === 'discount') {
                    $badge_text = ( __( '🎁 Discount', 'incentiv' ) . ' ' ) . $cart_item['mmd_gift_discount'] . '%';
                } elseif ($gift_type === 'fixed_discount') {
                    $badge_text = ( __( '🎁 Discount', 'incentiv' ) . ' ' ) . wc_price($cart_item['mmd_gift_discount']);
                }
            }

            $name .= ' <span class="mmd-gift-badge" style="background: linear-gradient(135deg, ' . esc_attr($bg_color_1) . ' 0%, ' . esc_attr($bg_color_2) . ' 100%); color: ' . esc_attr($text_color) . '; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: bold; margin-right: 5px;">' . $badge_text . '</span>';

            // Inline "Switch gift" button on the cart item row itself, so
            // the customer can change their gift selection without scrolling
            // back to the selector notice at the top of the page.
            if ($is_cart_based_gift) {
                $rule_id = isset($cart_item['mmd_gift_rule_id']) ? $cart_item['mmd_gift_rule_id'] : '';
                $name .= '<div class="mmd-cart-item-switch-gift" style="margin-top:4px;">'
                       . '<button type="button" class="mmd-switch-gift-btn button" data-rule-id="' . esc_attr($rule_id) . '" style="font-size:12px;padding:2px 10px;line-height:1.5;height:auto;background:#fff;color:#7c3aed;border:1px solid #7c3aed;border-radius:14px;cursor:pointer;">🔄 ' . esc_html__('Switch gift', 'incentiv') . '</button>'
                       . '</div>';
            }
        }

        return $name;
    }

    /**
     * Prevent gift removal
     */
    public function prevent_gift_removal($link, $cart_item_key) {
        $cart_item = WC()->cart->get_cart_item($cart_item_key);

        if (isset($cart_item['mmd_is_gift']) && $cart_item['mmd_is_gift']) {
            return ( '<span class="mmd-gift-locked" style="color: #999;" title="' . esc_attr__( 'This product is a gift and cannot be removed manually', 'incentiv' ) . '">🔒</span>' );
        }

        return $link;
    }

    /**
     * Lock gift quantity - Standard Cart
     */
    public function lock_gift_quantity($product_quantity, $cart_item_key, $cart_item) {
        if (isset($cart_item['mmd_is_gift']) && $cart_item['mmd_is_gift']) {
            return sprintf('%s <input type="hidden" name="cart[%s][qty]" value="%s" />', $cart_item['quantity'], $cart_item_key, $cart_item['quantity']);
        }
        return $product_quantity;
    }

    /**
     * Lock gift quantity - Checkout and Mini Cart (Arg order: HTML, Item, Key)
     */
    public function lock_gift_quantity_checkout($quantity_html, $cart_item, $cart_item_key) {
        if (isset($cart_item['mmd_is_gift']) && $cart_item['mmd_is_gift']) {
            // Just return the quantity number and hidden input, effectively removing buttons/inputs
            return sprintf('%s <input type="hidden" name="cart[%s][qty]" value="%s" />', $cart_item['quantity'], $cart_item_key, $cart_item['quantity']);
        }
        return $quantity_html;
    }

    /**
     * Add CSS class to gift items in cart
     */
    public function add_gift_cart_item_class($class, $cart_item, $cart_item_key) {
        if (isset($cart_item['mmd_is_gift']) && $cart_item['mmd_is_gift']) {
            $class .= ' mmd-gift-item-row';
        }
        return $class;
    }

    /**
     * Add CSS class to gift items in mini cart
     */
    public function add_gift_mini_cart_item_class($class, $cart_item, $cart_item_key) {
        if (isset($cart_item['mmd_is_gift']) && $cart_item['mmd_is_gift']) {
            $class .= ' mmd-gift-mini-cart-item';
        }
        return $class;
    }

    /**
     * Remove associated gifts
     */
    public function remove_associated_gifts($cart_item_key, $cart) {
        foreach ($cart->get_cart() as $key => $cart_item) {
            if (isset($cart_item['mmd_is_gift']) &&
                $cart_item['mmd_parent_key'] === $cart_item_key) {
                $cart->remove_cart_item($key);
            }
        }
    }

    /**
     * AJAX handler to add gift product to cart (chained action)
     * This is called AFTER the main product has been added to cart
     */
    public function ajax_add_gift_to_cart() {
        // Create custom debug file for detailed logging
        $debug_file = MMD_PLUGIN_DIR . 'debug-gift-ajax.log';
        $log = function($message) use ($debug_file) {
            $timestamp = gmdate('Y-m-d H:i:s');
            file_put_contents($debug_file, "[$timestamp] $message\n", FILE_APPEND);
            self::debug_log($message);
        };

        $log('===== MMD Gift AJAX: ajax_add_gift_to_cart START =====');

        try {
            // Check nonce
            if (!check_ajax_referer('mmd_gift_nonce', 'nonce', false)) {
                $log('MMD Gift AJAX: Nonce verification failed');
                wp_send_json_error(array('message' => 'Security check failed'));
                return;
            }

            // Get parameters
            $parent_product_id = isset($_POST['parent_product_id']) ? intval($_POST['parent_product_id']) : 0;
            $gift_product_id = isset($_POST['gift_product_id']) ? intval($_POST['gift_product_id']) : 0;
            $gift_variation_id = isset($_POST['gift_variation_id']) ? intval($_POST['gift_variation_id']) : 0;
            $gift_attributes_json = isset($_POST['gift_attributes']) ? wp_unslash($_POST['gift_attributes']) : '';

            // Fallback to the standard field name used elsewhere if the dedicated one is missing
            if (empty($gift_attributes_json) && isset($_POST['mmd_selected_gift_attributes'])) {
                $gift_attributes_json = wp_unslash($_POST['mmd_selected_gift_attributes']);
                $log('MMD Gift AJAX: gift_attributes missing, using mmd_selected_gift_attributes fallback');
            }

            $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;

            $log('MMD Gift AJAX: Parent product ID: ' . $parent_product_id);
            $log('MMD Gift AJAX: Gift product ID: ' . $gift_product_id);
            $log('MMD Gift AJAX: Gift variation ID: ' . $gift_variation_id);
            $log('MMD Gift AJAX: Quantity: ' . $quantity);
            $log('MMD Gift AJAX: Attributes JSON: ' . $gift_attributes_json);

            if (!$gift_product_id || !$parent_product_id) {
                $log('MMD Gift AJAX: Missing required parameters');
                wp_send_json_error(array('message' => 'Missing required parameters'));
                return;
            }

            // Find the parent product cart item key (the most recently added item for this product)
            $parent_cart_item_key = '';
            $cart = WC()->cart;
            if ($cart) {
                foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
                    // Check if this is the parent product (not a gift)
                    if ($cart_item['product_id'] == $parent_product_id && !isset($cart_item['mmd_is_gift'])) {
                        $parent_cart_item_key = $cart_item_key;
                        // Don't break - we want the last one (most recent)
                    }
                }
            }

            if (!$parent_cart_item_key) {
                $log('MMD Gift AJAX: Parent product not found in cart');
                // Continue anyway - the gift will still be added, just without parent association
                $parent_cart_item_key = 'unknown';
            }

            $log('MMD Gift AJAX: Found parent cart key: ' . $parent_cart_item_key);

            // Get gift settings - use get_gift_settings to support both product meta and gift rules
            $gift_settings = $this->get_gift_settings($parent_product_id);
            $gift_type = $gift_settings ? $gift_settings['gift_type'] : get_post_meta($parent_product_id, '_mmd_gift_type', true);
            if (empty($gift_type)) {
                $gift_type = 'free'; // Default to free if not set
            }

            // Parse attributes
            $gift_attributes = array();
            if (!empty($gift_attributes_json)) {
                $decoded = json_decode($gift_attributes_json, true);

                // Handle cases where the JSON payload was URL encoded before reaching PHP
                if (!is_array($decoded) && $gift_attributes_json !== urldecode($gift_attributes_json)) {
                    $log('MMD Gift AJAX: First JSON decode failed, attempting urldecode fallback');
                    $decoded = json_decode(urldecode($gift_attributes_json), true);
                }

                if (is_array($decoded)) {
                    $gift_attributes = $decoded;
                    $log('MMD Gift AJAX: Parsed attributes: ' . print_r($gift_attributes, true));
                    $log('MMD Gift AJAX: Raw JSON received: ' . $gift_attributes_json);
                } else {
                    $log('MMD Gift AJAX: Failed to parse attributes JSON');
                    $log('MMD Gift AJAX: JSON error: ' . json_last_error_msg());
                    $log('MMD Gift AJAX: Raw JSON: ' . $gift_attributes_json);
                }
            }

            // Prepare cart item data
            $gift_cart_data = array(
                'mmd_is_gift' => true,
                'mmd_parent_key' => $parent_cart_item_key,
                'mmd_gift_type' => $gift_type,
                'mmd_parent_product_id' => $parent_product_id
            );

            $cart_item_key = false;

            // Add to cart
            if ($gift_variation_id) {
                // Adding a variation product
                $variation_product = wc_get_product($gift_variation_id);
                if (!$variation_product || !($variation_product instanceof WC_Product_Variation)) {
                    $log('MMD Gift AJAX: Variation product not found or invalid type: ' . $gift_variation_id);
                    wp_send_json_error(array('message' => 'Variation product not found'));
                    return;
                }

                // Get variation attributes from the product (these keys might be encoded!)
                $original_variation_attributes = $variation_product->get_variation_attributes();

                $log('MMD Gift AJAX: Original variation attributes (from product): ' . print_r($original_variation_attributes, true));
                $log('MMD Gift AJAX: Gift attributes to merge (from user): ' . print_r($gift_attributes, true));

                // Create a mapping of decoded keys to original (potentially encoded) keys
                // We need to pass the KEYS exactly as they appear in $original_variation_attributes for WooCommerce to validate them
                $key_map = array();
                foreach ($original_variation_attributes as $orig_key => $orig_val) {
                    $decoded_key = urldecode($orig_key);
                    $key_map[$decoded_key] = $orig_key;
                    // Also handle the case where the key might be "attribute_" prefixed or not
                    if (strpos($decoded_key, 'attribute_') === 0) {
                        $short_key = substr($decoded_key, 10); // remove 'attribute_'
                        $key_map[$short_key] = $orig_key;
                    }
                }

                // Prepare final attributes array starting with defaults
                $final_variation_attributes = $original_variation_attributes;

                // Merge user attributes using the map to find correct keys
                foreach ($gift_attributes as $user_key => $user_val) {
                    $decoded_user_key = urldecode($user_key);
                    $decoded_user_val = urldecode($user_val);

                    $log('MMD Gift AJAX: Processing user attribute: ' . $user_key . ' => ' . $decoded_user_key . ' = ' . $decoded_user_val);

                    if (isset($key_map[$decoded_user_key])) {
                        $correct_key = $key_map[$decoded_user_key];
                        $final_variation_attributes[$correct_key] = $decoded_user_val;
                        $log('MMD Gift AJAX: Mapped to correct key: ' . $correct_key . ' = ' . $decoded_user_val);
                    } else {
                        // Fallback: if we can't map it, just use the key as provided (maybe it's a new custom attribute)
                        // But first try to add 'attribute_' prefix if missing
                        if (strpos($decoded_user_key, 'attribute_') === false) {
                            $final_variation_attributes['attribute_' . $decoded_user_key] = $decoded_user_val;
                            $log('MMD Gift AJAX: Unmapped key, adding with prefix: attribute_' . $decoded_user_key);
                        } else {
                            $final_variation_attributes[$decoded_user_key] = $decoded_user_val;
                            $log('MMD Gift AJAX: Unmapped key, using as is: ' . $decoded_user_key);
                        }
                    }
                }

                $log('MMD Gift AJAX: Final attributes for add_to_cart: ' . print_r($final_variation_attributes, true));

                // Validate variation using WooCommerce's validation
                try {
                    // Check if variation is valid and in stock
                    if (!$variation_product->is_purchasable()) {
                        $log('MMD Gift AJAX: Variation is not purchasable');
                        wp_send_json_error(array('message' => 'Variation is not available for purchase'));
                        return;
                    }

                    if (!$variation_product->is_in_stock()) {
                        $log('MMD Gift AJAX: Variation is out of stock');
                        wp_send_json_error(array('message' => 'Variation is out of stock'));
                        return;
                    }

                    // Log exactly what we're passing to add_to_cart
                    $log('MMD Gift AJAX: ===== CALLING WC()->cart->add_to_cart =====');
                    $log('MMD Gift AJAX: Parameter 1 (parent_id): ' . $variation_product->get_parent_id());
                    $log('MMD Gift AJAX: Parameter 2 (quantity): ' . $quantity);
                    $log('MMD Gift AJAX: Parameter 3 (variation_id): ' . $gift_variation_id);
                    $log('MMD Gift AJAX: Parameter 4 (variation_attributes): ' . print_r($final_variation_attributes, true));

                    // Add to cart with error handling
                    $cart_item_key = WC()->cart->add_to_cart(
                        $variation_product->get_parent_id(),
                        $quantity,
                        $gift_variation_id,
                        $final_variation_attributes,
                        $gift_cart_data
                    );

                    // Check for WooCommerce notices if add_to_cart failed
                    if (!$cart_item_key) {
                        $notices = wc_get_notices('error');
                        $log('MMD Gift AJAX: ❌ WooCommerce add_to_cart FAILED - returned false');
                        $log('MMD Gift AJAX: WooCommerce error notices: ' . print_r($notices, true));

                        // Clear notices to prevent them showing to user
                        wc_clear_notices();

                        $error_message = 'Failed to add variation to cart';
                        if (!empty($notices)) {
                            $error_message .= ': ' . $notices[0]['notice'];
                        }

                        $log('MMD Gift AJAX: Sending error response: ' . $error_message);
                        wp_send_json_error(array('message' => $error_message));
                        return;
                    } else {
                        $log('MMD Gift AJAX: ✅ add_to_cart succeeded! Cart key: ' . $cart_item_key);
                    }
                } catch (Exception $e) {
                    self::debug_log('MMD Gift AJAX: Exception during add to cart: ' . $e->getMessage());
                    wp_send_json_error(array('message' => 'Error adding variation to cart: ' . $e->getMessage()));
                    return;
                }
            } else {
                // Adding a simple product
                self::debug_log('MMD Gift AJAX: Adding simple product to cart');
                $cart_item_key = WC()->cart->add_to_cart($gift_product_id, $quantity, 0, array(), $gift_cart_data);

                if (!$cart_item_key) {
                    $notices = wc_get_notices('error');
                    self::debug_log('MMD Gift AJAX: WooCommerce add_to_cart failed for simple product');
                    wc_clear_notices();

                    $error_message = 'Failed to add simple product to cart';
                    if (!empty($notices)) {
                        $error_message .= ': ' . $notices[0]['notice'];
                    }

                    wp_send_json_error(array('message' => $error_message));
                    return;
                }
            }

            if ($cart_item_key) {
                self::debug_log('MMD Gift AJAX: Successfully added gift to cart. Cart key: ' . $cart_item_key);
                wp_send_json_success(array(
                    'message' => 'Gift added to cart',
                    'cart_item_key' => $cart_item_key
                ));
            }

            self::debug_log('===== MMD Gift AJAX: ajax_add_gift_to_cart END =====');
        } catch (Exception $e) {
            self::debug_log('MMD Gift AJAX: CRITICAL ERROR - ' . $e->getMessage());
            self::debug_log('MMD Gift AJAX: Stack trace - ' . $e->getTraceAsString());
            wp_send_json_error(array('message' => 'Critical error: ' . $e->getMessage()));
        }
    }

    /** @var array<string,array<string,bool>> Gifts shown by the picker, per page area. */
    private static $rendered_gifts = array();

    /**
     * Page area for de-duplication: cart and checkout pages share one picker
     * and one notice; the mini cart is separate.
     */
    private static function render_area() {
        $hook = current_filter();
        if ($hook === 'woocommerce_before_mini_cart') {
            return 'mini_cart';
        }
        if (function_exists('is_checkout') && is_checkout()) {
            return 'checkout';
        }
        return 'cart';
    }

    private static function mark_gift_rendered($rule_id) {
        self::$rendered_gifts[self::render_area()][(string) $rule_id] = true;
    }

    private static function gift_already_rendered($rule_id) {
        $area = self::render_area();
        return !empty(self::$rendered_gifts[$area][(string) $rule_id]);
    }

    /** Test helper: forget what was rendered in this request. */
    public static function reset_rendered_gifts() {
        self::$rendered_gifts = array();
    }

    /**
     * Display cart gift notice for cart-based gifts
     */
    public function display_cart_gift_notice() {
        if (!WC()->cart || WC()->cart->is_empty()) {
            return;
        }

        // Debug output for console
        $debug_data = array(
            'cart_items' => array(),
            'rules' => array(),
            'matching_info' => array(),
        );

        foreach (WC()->cart->get_cart() as $cart_item) {
            $debug_data['cart_items'][] = array(
                'product_id' => $cart_item['product_id'],
                'variation_id' => isset($cart_item['variation_id']) ? $cart_item['variation_id'] : 0,
                'is_gift' => isset($cart_item['mmd_is_gift']) ? $cart_item['mmd_is_gift'] : false,
                'is_cart_based_gift' => isset($cart_item['mmd_cart_based_gift']) ? $cart_item['mmd_cart_based_gift'] : false,
            );
        }

        if (class_exists('MMD_Gift_Rules')) {
            $gift_rules = MMD_Gift_Rules::get_instance();
            $all_rules = $gift_rules->get_rule_sets();
            foreach ($all_rules as $rule) {
                $debug_data['rules'][] = array(
                    'name' => $rule['name'],
                    'enabled' => isset($rule['enabled']) ? $rule['enabled'] : 'not set',
                    'gift_mode' => isset($rule['gift_mode']) ? $rule['gift_mode'] : 'not set',
                    'selection_method' => isset($rule['selection_method']) ? $rule['selection_method'] : 'not set',
                    'gift_source' => isset($rule['gift_source']) ? $rule['gift_source'] : 'not set',
                    'gift_categories' => isset($rule['gift_categories']) ? $rule['gift_categories'] : array(),
                    'gift_attribute_slug' => isset($rule['gift_attribute_slug']) ? $rule['gift_attribute_slug'] : '',
                    'gift_attribute_values' => isset($rule['gift_attribute_values']) ? $rule['gift_attribute_values'] : array(),
                );
            }
        }

        // Rule and cart details go to the browser console only in debug mode.
        $debug_console = self::is_debug_enabled();
        if ($debug_console) {
            echo '<script>console.log("MMD Cart Gift Debug:", ' . wp_json_encode($debug_data) . ');</script>';
        }

        $cart_based_gifts = $this->get_cart_based_gift_eligibility();

        if ($debug_console) {
            echo '<script>console.log("MMD Cart Gift Eligibility:", ' . wp_json_encode($cart_based_gifts) . ');</script>';
        }

        if (empty($cart_based_gifts)) {
            return;
        }

        foreach ($cart_based_gifts as $gift_info) {
            // The gift picker (display_cart_gift_selection) already showed this
            // gift on the cart or checkout page: don't repeat it as a bar.
            $rendered_key = isset($gift_info['rule_id']) ? (string) $gift_info['rule_id'] : '';
            if (self::gift_already_rendered($rendered_key)) {
                continue;
            }

            $notice_text = $gift_info['notice'];
            $gift_type_text = '';

            if ($gift_info['gift_type'] === 'free') {
                $gift_type_text = __( 'Free gift', 'incentiv' );
            } elseif ($gift_info['gift_type'] === 'discount') {
                $gift_type_text = ( __( 'A discount of', 'incentiv' ) . ' ' ) . $gift_info['gift_discount'] . '%';
            } elseif ($gift_info['gift_type'] === 'fixed_discount') {
                $gift_type_text = ( __( 'A discount of', 'incentiv' ) . ' ' ) . wc_price($gift_info['gift_discount']);
            }

            // Check if gift has already been claimed
            $gift_claimed = $this->is_cart_gift_claimed($gift_info);

            if ($gift_claimed) {
                $claimed_text = __( '✅ Your gift was added to the cart! (', 'incentiv' ) . $gift_type_text . ')';
                echo '<div class="woocommerce-info mmd-cart-gift-notice mmd-gift-claimed" style="background: linear-gradient(135deg, #7c3aed 0%, #a855f7 100%); color: white; border: none;">';
                echo '<span class="mmd-gift-icon">🎁</span> ' . esc_html($claimed_text);
                echo '</div>';
            } else {
                echo '<div class="woocommerce-info mmd-cart-gift-notice" style="background: linear-gradient(135deg, #7c3aed 0%, #a855f7 100%); color: white; border: none;">';
                echo '<span class="mmd-gift-icon">🎁</span> ' . esc_html($notice_text);
                echo ' <strong>(' . esc_html($gift_type_text) . ')</strong>';
                echo '</div>';
            }

            // Display custom cart/checkout message from gift rule
            $custom_message = '';
            if (isset($gift_info['rule'])) {
                $rule = $gift_info['rule'];
                if (is_cart() && !empty($rule['cart_message'])) {
                    $custom_message = $rule['cart_message'];
                } elseif (is_checkout() && !empty($rule['checkout_message'])) {
                    $custom_message = $rule['checkout_message'];
                }
            }

            if (!empty($custom_message)) {
                echo '<div class="woocommerce-info mmd-custom-gift-message" style="margin-bottom: 20px; background: #e8f5e9; border-left: 4px solid #7c3aed; padding: 12px; color: #333;">';
                echo '<div style="line-height: 1.6;">' . wp_kses_post($custom_message) . '</div>';
                echo '</div>';
            }
        }
    }

    /**
     * Display gift notice on product page for cart-based gifts
     */
    public function display_product_page_gift_notice() {
        global $product;

        if (!$product) {
            return;
        }

        $product_id = $product->get_id();

        // Check if this product matches any cart-based gift rule
        if (class_exists('MMD_Gift_Rules')) {
            $gift_rules = MMD_Gift_Rules::get_instance();
            $matching_rule = $gift_rules->get_matching_rule_for_product($product_id);

            if ($matching_rule && isset($matching_rule['gift_mode']) && $matching_rule['gift_mode'] === 'cart_based') {
                $notice_text = isset($matching_rule['cart_gift_notice']) ? $matching_rule['cart_gift_notice'] : __( 'You have a gift! Add a product from the participating categories to get a discount', 'incentiv' );
                $gift_type_text = '';

                if (isset($matching_rule['gift_type'])) {
                    if ($matching_rule['gift_type'] === 'free') {
                        $gift_type_text = __( 'Free gift', 'incentiv' );
                    } elseif ($matching_rule['gift_type'] === 'discount') {
                        $gift_type_text = ( __( 'A discount of', 'incentiv' ) . ' ' ) . $matching_rule['gift_discount'] . '%';
                    } elseif ($matching_rule['gift_type'] === 'fixed_discount') {
                        $gift_type_text = ( __( 'A discount of', 'incentiv' ) . ' ' ) . wc_price($matching_rule['gift_discount']);
                    }
                }

                echo '<div class="woocommerce-info mmd-product-gift-notice" style="background: linear-gradient(135deg, #7c3aed 0%, #a855f7 100%); color: white; border: none; margin-bottom: 20px; padding: 15px; border-radius: 8px;">';
                echo '<span class="mmd-gift-icon" style="font-size: 1.5em;">🎁</span> ' . esc_html($notice_text);
                if ($gift_type_text) {
                    echo ' <strong>(' . esc_html($gift_type_text) . ')</strong>';
                }
                echo '</div>';

                // Debug output (debug mode only)
                if (self::is_debug_enabled()) {
                    echo '<script>console.log("MMD Product Page Gift Notice: Product ' . esc_attr( $product_id ) . ' matches rule: ' . esc_js($matching_rule['name']) . '");</script>';
                }
            }
        }

        // Also check product-level settings
        $gift_mode = get_post_meta($product_id, '_mmd_gift_mode', true);
        $gift_enabled = get_post_meta($product_id, '_mmd_gift_enabled', true);

        if ($gift_enabled === '1' && $gift_mode === 'cart_based') {
            $cart_gift_notice = get_post_meta($product_id, '_mmd_cart_gift_notice', true);
            $notice_text = !empty($cart_gift_notice) ? $cart_gift_notice : __( 'You have a gift! Add a product from the participating categories to get a discount', 'incentiv' );
            $gift_type = get_post_meta($product_id, '_mmd_gift_type', true);
            $gift_discount = get_post_meta($product_id, '_mmd_gift_discount', true);

            $gift_type_text = '';
            if ($gift_type === 'free') {
                $gift_type_text = __( 'Free gift', 'incentiv' );
            } elseif ($gift_type === 'discount') {
                $gift_type_text = ( __( 'A discount of', 'incentiv' ) . ' ' ) . $gift_discount . '%';
            } elseif ($gift_type === 'fixed_discount') {
                $gift_type_text = ( __( 'A discount of', 'incentiv' ) . ' ' ) . wc_price($gift_discount);
            }

            echo '<div class="woocommerce-info mmd-product-gift-notice" style="background: linear-gradient(135deg, #7c3aed 0%, #a855f7 100%); color: white; border: none; margin-bottom: 20px; padding: 15px; border-radius: 8px;">';
            echo '<span class="mmd-gift-icon" style="font-size: 1.5em;">🎁</span> ' . esc_html($notice_text);
            if ($gift_type_text) {
                echo ' <strong>(' . esc_html($gift_type_text) . ')</strong>';
            }
            echo '</div>';
        }
    }

    /**
     * Display gift notice in mini cart
     */
    public function display_mini_cart_gift_notice() {
        if (!WC()->cart || WC()->cart->is_empty()) {
            return;
        }

        $cart_based_gifts = $this->get_cart_based_gift_eligibility();

        if (empty($cart_based_gifts)) {
            return;
        }

        foreach ($cart_based_gifts as $gift_info) {
            $notice_text = $gift_info['notice'];
            $gift_type_text = '';

            if ($gift_info['gift_type'] === 'free') {
                $gift_type_text = __( 'Free gift', 'incentiv' );
            } elseif ($gift_info['gift_type'] === 'discount') {
                $gift_type_text = ( __( 'A discount of', 'incentiv' ) . ' ' ) . $gift_info['gift_discount'] . '%';
            } elseif ($gift_info['gift_type'] === 'fixed_discount') {
                $gift_type_text = ( __( 'A discount of', 'incentiv' ) . ' ' ) . wc_price($gift_info['gift_discount']);
            }

            $gift_claimed = $this->is_cart_gift_claimed($gift_info);

            echo '<div class="mmd-mini-cart-gift-notice" style="background: linear-gradient(135deg, #7c3aed 0%, #a855f7 100%); color: white; padding: 10px; margin: 10px 0; border-radius: 5px; font-size: 13px;">';
            if ($gift_claimed) {
                echo '<span>✅ ' . esc_html__('Gift added!', 'incentiv') . '</span>';
            } else {
                echo '<span>🎁 ' . esc_html($notice_text) . '</span>';
            }
            echo '</div>';
        }
    }

    /**
     * Get cart-based gift eligibility for all items in cart
     *
     * @return array Array of gift eligibility info
     */
    private function get_cart_based_gift_eligibility() {
        $cart_gifts = array();
        $processed_rules = array();

        self::debug_log('=== MMD Cart Gift Debug: get_cart_based_gift_eligibility START ===');

        // Coupon-driven gifts: any gift rule whose required coupon is applied
        // is eligible regardless of cart contents. A coupon campaign with
        // both a discount and a gift should surface the gift as soon as the
        // coupon is applied — the customer shouldn't have to add an unrelated
        // product first to "unlock" the gift selector.
        $applied_coupons = array();
        if (WC()->cart) {
            $applied_coupons = array_map('strtolower', WC()->cart->get_applied_coupons());
        }
        if (!empty($applied_coupons) && class_exists('MMD_Gift_Rules')) {
            $gift_rules_inst = MMD_Gift_Rules::get_instance();
            foreach ($gift_rules_inst->get_rule_sets() as $rule) {
                if (empty($rule['id'])) continue;
                if (isset($rule['enabled']) && $rule['enabled'] === 'no') continue;
                if (empty($rule['coupon_required']) || $rule['coupon_required'] !== 'yes') continue;

                $required = isset($rule['required_coupon_codes']) ? (array) $rule['required_coupon_codes'] : array();
                $required = array_map('strtolower', array_map('trim', $required));
                if (empty($required) || !array_intersect($required, $applied_coupons)) {
                    continue;
                }

                // Honor the wizard's "where to show the gift picker" setting:
                // product_only campaigns must NOT render the cart selector.
                $display_location = isset($rule['gift_display_location']) ? $rule['gift_display_location'] : 'both';
                if ($display_location === 'product_only') {
                    continue;
                }

                // Schedule check
                $now = current_time('Y-m-d\TH:i');
                if (!empty($rule['schedule_start']) && $now < $rule['schedule_start']) continue;
                if (!empty($rule['schedule_end']) && $now > $rule['schedule_end']) continue;

                // User role check
                $allowed_roles = isset($rule['allowed_user_roles']) && is_array($rule['allowed_user_roles']) ? $rule['allowed_user_roles'] : array();
                if (!empty($allowed_roles)) {
                    if (!is_user_logged_in()) continue;
                    $user = wp_get_current_user();
                    if (empty(array_intersect($allowed_roles, $user->roles))) continue;
                }

                $processed_rules[] = $rule['id'];

                $gift_products = $this->get_gift_products_from_rule($rule);
                if (empty($gift_products)) continue;

                $cart_gifts[] = array(
                    'source'               => 'coupon_rule',
                    'rule_id'              => $rule['id'],
                    'rule'                 => $rule,
                    // Use 0 — there's no specific "trigger product"; the
                    // coupon itself is the trigger. Downstream code only uses
                    // this for the data-trigger-product attribute, where 0
                    // is fine (the JS never special-cases it).
                    'trigger_product_id'   => 0,
                    'notice'               => isset($rule['cart_gift_notice']) ? $rule['cart_gift_notice'] : __( 'You have a gift! Choose a product', 'incentiv' ),
                    'gift_type'            => isset($rule['gift_type']) ? $rule['gift_type'] : 'free',
                    'gift_discount'        => isset($rule['gift_discount']) ? $rule['gift_discount'] : 0,
                    'gift_products'        => $gift_products,
                    'gift_source'          => isset($rule['gift_source']) ? $rule['gift_source'] : 'specific',
                    'gift_categories'      => isset($rule['gift_categories']) ? $rule['gift_categories'] : array(),
                    'gift_attribute_slug'  => isset($rule['gift_attribute_slug']) ? $rule['gift_attribute_slug'] : '',
                    'gift_attribute_values'=> isset($rule['gift_attribute_values']) ? $rule['gift_attribute_values'] : array(),
                    'gift_default_attr_slug'  => isset($rule['gift_default_attr_slug']) ? $rule['gift_default_attr_slug'] : '',
                    'gift_default_attr_value' => isset($rule['gift_default_attr_value']) ? $rule['gift_default_attr_value'] : 0,
                    'gift_limit_type'      => isset($rule['gift_limit_type']) ? $rule['gift_limit_type'] : 'per_cart',
                );
            }
        }

        foreach (WC()->cart->get_cart() as $cart_item) {
            $product_id = $cart_item['product_id'];
            self::debug_log('MMD Cart Gift Debug: Checking product ID: ' . $product_id);

            // Skip items that are already gifts
            if (isset($cart_item['mmd_is_gift']) && $cart_item['mmd_is_gift']) {
                self::debug_log('MMD Cart Gift Debug: Skipping - is already a gift');
                continue;
            }

            // Skip items that are cart-based gifts
            if (isset($cart_item['mmd_cart_based_gift']) && $cart_item['mmd_cart_based_gift']) {
                self::debug_log('MMD Cart Gift Debug: Skipping - is cart-based gift');
                continue;
            }

            // Check global rules first
            if (class_exists('MMD_Gift_Rules')) {
                $gift_rules = MMD_Gift_Rules::get_instance();
                $all_rules = $gift_rules->get_rule_sets();
                self::debug_log('MMD Cart Gift Debug: Total rules found: ' . count($all_rules));
                foreach ($all_rules as $rule) {
                    self::debug_log('MMD Cart Gift Debug: Rule "' . $rule['name'] . '" - enabled: ' . ($rule['enabled'] ?? 'not set') . ', gift_mode: ' . ($rule['gift_mode'] ?? 'not set') . ', selection_method: ' . ($rule['selection_method'] ?? 'not set'));
                }

                $matching_rule = $gift_rules->get_matching_rule_for_product($product_id);
                self::debug_log('MMD Cart Gift Debug: Matching rule for product ' . $product_id . ': ' . ($matching_rule ? $matching_rule['name'] : 'NONE'));

                if ($matching_rule) {
                    self::debug_log('MMD Cart Gift Debug: Rule gift_mode: ' . ($matching_rule['gift_mode'] ?? 'NOT SET'));
                }

                if ($matching_rule && isset($matching_rule['gift_mode'])) {
                    $gift_mode = $matching_rule['gift_mode'];

                    // For product_page mode: show as cart fallback if the gift wasn't picked
                    // (i.e. the user bought the product but didn't select a gift on the product page)
                    $show_as_cart_fallback = false;
                    if ($gift_mode === 'product_page') {
                        // Check if any gift from this rule is already in the cart
                        $has_gift_in_cart = false;
                        foreach (WC()->cart->get_cart() as $check_item) {
                            if ((isset($check_item['mmd_is_gift']) && $check_item['mmd_is_gift']) ||
                                (isset($check_item['mmd_cart_based_gift']) && $check_item['mmd_cart_based_gift'])) {
                                $has_gift_in_cart = true;
                                break;
                            }
                        }
                        if (!$has_gift_in_cart) {
                            $show_as_cart_fallback = true;
                        }
                    }

                    if ($gift_mode === 'cart_based' || $show_as_cart_fallback) {
                    $rule_id = $matching_rule['id'];

                    // Skip if already processed this rule
                    if (in_array($rule_id, $processed_rules)) {
                        continue;
                    }

                    // Honor coupon_required: a rule that requires a coupon
                    // MUST NOT fire just because a cart item happens to
                    // match the rule's trigger criteria. Without this check
                    // an autocoffee-style "coupon → 10% off + gift" rule
                    // would surface the gift selector for every user who
                    // adds a coffee to the cart, regardless of whether
                    // they've applied the coupon.
                    if (!empty($matching_rule['coupon_required']) && $matching_rule['coupon_required'] === 'yes') {
                        $required_coupons = isset($matching_rule['required_coupon_codes']) ? $matching_rule['required_coupon_codes'] : array();
                        if (!is_array($required_coupons)) {
                            $required_coupons = array_map('trim', explode(',', $required_coupons));
                        }
                        $required_coupons = array_map('strtolower', array_map('trim', $required_coupons));
                        if (empty($required_coupons) || !array_intersect($required_coupons, $applied_coupons)) {
                            continue;
                        }
                    }

                    // Check shipping method condition
                    $shipping_condition = isset($matching_rule['shipping_method_condition']) ? $matching_rule['shipping_method_condition'] : '';
                    if (!empty($shipping_condition) && WC()->session) {
                        $chosen_methods = WC()->session->get('chosen_shipping_methods', array());
                        $shipping_match = false;
                        foreach ($chosen_methods as $chosen_method) {
                            if ($chosen_method === $shipping_condition || strpos($chosen_method, $shipping_condition) === 0) {
                                $shipping_match = true;
                                break;
                            }
                        }
                        if (!$shipping_match) {
                            continue;
                        }
                    }

                    // Check user role
                    $allowed_roles = isset($matching_rule['allowed_user_roles']) && is_array($matching_rule['allowed_user_roles']) ? $matching_rule['allowed_user_roles'] : array();
                    if (!empty($allowed_roles)) {
                        if (!is_user_logged_in()) {
                            continue;
                        }
                        $user = wp_get_current_user();
                        if (empty(array_intersect($allowed_roles, $user->roles))) {
                            continue;
                        }
                    }

                    // Check schedule
                    $now = current_time('Y-m-d\TH:i');
                    $sched_start = isset($matching_rule['schedule_start']) ? $matching_rule['schedule_start'] : '';
                    $sched_end = isset($matching_rule['schedule_end']) ? $matching_rule['schedule_end'] : '';
                    if (!empty($sched_start) && $now < $sched_start) {
                        continue;
                    }
                    if (!empty($sched_end) && $now > $sched_end) {
                        continue;
                    }

                    // Check cart total conditions
                    $cart_subtotal = WC()->cart->get_subtotal();
                    $rule_min_cart = isset($matching_rule['min_cart_total']) && $matching_rule['min_cart_total'] !== '' ? floatval($matching_rule['min_cart_total']) : 0;
                    $rule_max_cart = isset($matching_rule['max_cart_total']) && $matching_rule['max_cart_total'] !== '' ? floatval($matching_rule['max_cart_total']) : 0;
                    if ($rule_min_cart > 0 && $cart_subtotal < $rule_min_cart) {
                        continue;
                    }
                    if ($rule_max_cart > 0 && $cart_subtotal > $rule_max_cart) {
                        continue;
                    }

                    $processed_rules[] = $rule_id;

                    $gift_products = $this->get_gift_products_from_rule($matching_rule);

                    $cart_gifts[] = array(
                        'source' => 'rule',
                        'rule_id' => $rule_id,
                        'rule' => $matching_rule,
                        'trigger_product_id' => $product_id,
                        'notice' => isset($matching_rule['cart_gift_notice']) ? $matching_rule['cart_gift_notice'] : __( 'You have a gift! Add a product from the participating categories', 'incentiv' ),
                        'gift_type' => isset($matching_rule['gift_type']) ? $matching_rule['gift_type'] : 'free',
                        'gift_discount' => isset($matching_rule['gift_discount']) ? $matching_rule['gift_discount'] : 0,
                        'gift_products' => $gift_products,
                        'gift_source' => isset($matching_rule['gift_source']) ? $matching_rule['gift_source'] : 'specific',
                        'gift_categories' => isset($matching_rule['gift_categories']) ? $matching_rule['gift_categories'] : array(),
                        'gift_attribute_slug' => isset($matching_rule['gift_attribute_slug']) ? $matching_rule['gift_attribute_slug'] : '',
                        'gift_attribute_values' => isset($matching_rule['gift_attribute_values']) ? $matching_rule['gift_attribute_values'] : array(),
                        'gift_default_attr_slug'  => isset($matching_rule['gift_default_attr_slug']) ? $matching_rule['gift_default_attr_slug'] : '',
                        'gift_default_attr_value' => isset($matching_rule['gift_default_attr_value']) ? $matching_rule['gift_default_attr_value'] : 0,
                        'gift_limit_type' => isset($matching_rule['gift_limit_type']) ? $matching_rule['gift_limit_type'] : 'per_cart',
                    );
                    } // end if cart_based || show_as_cart_fallback
                } // end if matching_rule
            }

            // Check product-level settings
            $gift_mode = get_post_meta($product_id, '_mmd_gift_mode', true);
            $gift_enabled = get_post_meta($product_id, '_mmd_gift_enabled', true);

            if ($gift_enabled === '1' && $gift_mode === 'cart_based') {
                // Skip if a rule already covers this
                if (!empty($processed_rules)) {
                    continue;
                }

                $gift_products = $this->get_gift_products_from_product_meta($product_id);
                $cart_gift_notice = get_post_meta($product_id, '_mmd_cart_gift_notice', true);
                $gift_type = get_post_meta($product_id, '_mmd_gift_type', true);
                $gift_discount = get_post_meta($product_id, '_mmd_gift_discount', true);

                $cart_gifts[] = array(
                    'source' => 'product',
                    'trigger_product_id' => $product_id,
                    'notice' => !empty($cart_gift_notice) ? $cart_gift_notice : __( 'You have a gift! Add a product from the participating categories', 'incentiv' ),
                    'gift_type' => !empty($gift_type) ? $gift_type : 'free',
                    'gift_discount' => !empty($gift_discount) ? floatval($gift_discount) : 0,
                    'gift_products' => $gift_products,
                    'gift_source' => get_post_meta($product_id, '_mmd_gift_source', true) ?: 'specific',
                    'gift_categories' => get_post_meta($product_id, '_mmd_gift_categories', true) ?: array(),
                );
            }
        }

        return $cart_gifts;
    }

    /**
     * Check if a cart-based gift has already been claimed
     *
     * @param array $gift_info Gift eligibility info
     * @return bool Whether the gift has been claimed
     */
    private function is_cart_gift_claimed($gift_info) {
        $gift_products = $gift_info['gift_products'];

        foreach (WC()->cart->get_cart() as $cart_item) {
            $item_product_id = isset($cart_item['variation_id']) && $cart_item['variation_id'] > 0
                ? $cart_item['variation_id']
                : $cart_item['product_id'];

            // Check if this item is in the gift products list
            if (in_array($cart_item['product_id'], $gift_products) || in_array($item_product_id, $gift_products)) {
                // Check if it has the cart-based gift discount applied
                if (isset($cart_item['mmd_cart_based_gift']) && $cart_item['mmd_cart_based_gift']) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Display price for cart-based gift items
     *
     * @param string $price Price HTML
     * @param array $cart_item Cart item data
     * @param string $cart_item_key Cart item key
     * @return string Modified price HTML
     */
    public function display_cart_based_gift_price($price, $cart_item, $cart_item_key) {
        if (isset($cart_item['mmd_cart_based_gift']) && $cart_item['mmd_cart_based_gift']) {
            $product = $cart_item['data'];
            $gift_type = isset($cart_item['mmd_gift_type']) ? $cart_item['mmd_gift_type'] : 'free';

            if ($gift_type === 'free') {
                return '<span class="mmd-gift-price">' . wc_price(0) . '</span> <del>' . $price . '</del>';
            } else {
                // For discount types, show current price (already modified by apply_cart_based_gift_discounts)
                $current_price = $product->get_price();
                return '<span class="mmd-gift-price">' . wc_price($current_price) . '</span>';
            }
        }
        return $price;
    }

    /**
     * Display subtotal for cart-based gift items
     *
     * @param string $subtotal Subtotal HTML
     * @param array $cart_item Cart item data
     * @param string $cart_item_key Cart item key
     * @return string Modified subtotal HTML
     */
    public function display_cart_based_gift_subtotal($subtotal, $cart_item, $cart_item_key) {
        if (isset($cart_item['mmd_cart_based_gift']) && $cart_item['mmd_cart_based_gift']) {
            $product = $cart_item['data'];
            $gift_type = isset($cart_item['mmd_gift_type']) ? $cart_item['mmd_gift_type'] : 'free';
            $quantity = $cart_item['quantity'];

            if ($gift_type === 'free') {
                return '<span class="mmd-gift-subtotal">' . wc_price(0) . '</span> <del>' . $subtotal . '</del>';
            } else {
                // For discount types, calculate based on current price
                $current_price = $product->get_price();
                return '<span class="mmd-gift-subtotal">' . wc_price($current_price * $quantity) . '</span>';
            }
        }
        return $subtotal;
    }

    /**
     * Apply cart-based gift discounts
     *
     * @param WC_Cart $cart Cart object
     */
    public function apply_cart_based_gift_discounts($cart) {
        // Shoppers blocked by an add-on (filter `mmd_promotions_blocked`) get no promotions.
        if (MMD_Modules::promotions_blocked('gifts')) {
            return;
        }

        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }

        // Store debug info for output
        static $debug_output_done = false;
        $debug_info = array(
            'action_count' => did_action('woocommerce_before_calculate_totals'),
            'cart_items' => array(),
            'applied_gifts' => array(),
            'skipped_reasons' => array(),
        );

        $cart_based_gifts = $this->get_cart_based_gift_eligibility();

        $debug_info['cart_based_gifts_count'] = count($cart_based_gifts);

        if (empty($cart_based_gifts)) {
            $debug_info['result'] = 'No cart-based gifts eligible';
            $this->output_cart_gift_debug($debug_info, $debug_output_done);
            return;
        }

        // Add gift info to debug
        foreach ($cart_based_gifts as $gift_key => $gift_info) {
            $debug_info['gift_rule_' . $gift_key] = array(
                'source' => $gift_info['source'],
                'trigger_product_id' => $gift_info['trigger_product_id'],
                'gift_type' => $gift_info['gift_type'],
                'gift_source' => isset($gift_info['gift_source']) ? $gift_info['gift_source'] : 'not_set',
                'gift_categories' => isset($gift_info['gift_categories']) ? $gift_info['gift_categories'] : 'not_set',
                'gift_attribute_slug' => isset($gift_info['gift_attribute_slug']) ? $gift_info['gift_attribute_slug'] : '',
                'gift_attribute_values' => isset($gift_info['gift_attribute_values']) ? $gift_info['gift_attribute_values'] : array(),
                'gift_products_count' => count($gift_info['gift_products']),
                'gift_products' => $gift_info['gift_products'],
            );
        }

        // Track which gifts have been applied (one per rule/product)
        $applied_gifts = array();

        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            // Skip items that are already marked as product-page gifts
            if (isset($cart_item['mmd_is_gift']) && $cart_item['mmd_is_gift']) {
                $debug_info['skipped_reasons'][] = 'Item ' . $cart_item_key . ' - already a product-page gift';
                continue;
            }

            // Only zero / discount items the customer EXPLICITLY claimed via
            // the gift selector (ajax_add_cart_gift sets mmd_cart_based_gift=true).
            // Without this guard, any regular cart item whose parent happens
            // to be in the rule's gift_products list (e.g. another coffee in
            // the same category as the gift pool) gets auto-converted to a
            // free gift and the customer's purchase becomes ₪0.
            if (empty($cart_item['mmd_cart_based_gift'])) {
                continue;
            }

            $item_product_id = isset($cart_item['variation_id']) && $cart_item['variation_id'] > 0
                ? $cart_item['variation_id']
                : $cart_item['product_id'];

            $parent_product_id = $cart_item['product_id'];

            $product_name = '';
            if ($cart_item['data']) {
                $product_name = $cart_item['data']->get_name();
            }

            $debug_info['cart_items'][] = array(
                'cart_item_key' => $cart_item_key,
                'parent_product_id' => $parent_product_id,
                'item_product_id' => $item_product_id,
                'variation_id' => isset($cart_item['variation_id']) ? $cart_item['variation_id'] : 0,
                'product_name' => $product_name,
            );

            // Check each cart-based gift eligibility
            foreach ($cart_based_gifts as $gift_key => $gift_info) {
                $gift_limit_type = isset($gift_info['gift_limit_type']) ? $gift_info['gift_limit_type'] : 'per_cart';

                // Determine gift identifier based on limit type
                $base_identifier = $gift_info['source'] . '_' . ($gift_info['source'] === 'rule' ? $gift_info['rule_id'] : $gift_info['trigger_product_id']);

                if ($gift_limit_type === 'per_cart') {
                    // One gift per entire cart - use base identifier
                    $gift_identifier = $base_identifier;
                } elseif ($gift_limit_type === 'per_product') {
                    // One gift per trigger product - include trigger product in identifier
                    $gift_identifier = $base_identifier . '_trigger_' . $gift_info['trigger_product_id'];
                } else {
                    // Unlimited - unique identifier per cart item
                    $gift_identifier = $base_identifier . '_item_' . $cart_item_key;
                }

                if ($gift_limit_type !== 'unlimited' && isset($applied_gifts[$gift_identifier])) {
                    $debug_info['skipped_reasons'][] = 'Gift ' . $gift_identifier . ' already applied (limit: ' . $gift_limit_type . ')';
                    continue;
                }

                $gift_products = $gift_info['gift_products'];

                // Check if this cart item is in the gift products list
                $in_parent = in_array($parent_product_id, $gift_products);
                $in_item = in_array($item_product_id, $gift_products);

                $debug_info['matching_check_' . $cart_item_key . '_' . $gift_key] = array(
                    'parent_id' => $parent_product_id,
                    'item_id' => $item_product_id,
                    'parent_in_gifts' => $in_parent,
                    'item_in_gifts' => $in_item,
                    'gift_products' => $gift_products,
                );

                if ($in_parent || $in_item) {
                    // Don't apply if this is the triggering product itself
                    if ($parent_product_id == $gift_info['trigger_product_id']) {
                        $debug_info['skipped_reasons'][] = 'Item ' . $cart_item_key . ' is the trigger product';
                        continue;
                    }

                    // Mark this item as a cart-based gift
                    $cart->cart_contents[$cart_item_key]['mmd_cart_based_gift'] = true;
                    $cart->cart_contents[$cart_item_key]['mmd_gift_type'] = $gift_info['gift_type'];
                    $cart->cart_contents[$cart_item_key]['mmd_gift_discount'] = $gift_info['gift_discount'];

                    // Apply the discount
                    $product = $cart_item['data'];
                    $original_price = floatval($product->get_price());
                    $new_price = $original_price;

                    if ($gift_info['gift_type'] === 'free') {
                        $new_price = 0;
                        $product->set_price(0);
                    } elseif ($gift_info['gift_type'] === 'discount') {
                        $discount_percent = floatval($gift_info['gift_discount']);
                        $new_price = $original_price * (1 - ($discount_percent / 100));
                        $product->set_price(max(0, $new_price));
                    } elseif ($gift_info['gift_type'] === 'fixed_discount') {
                        $discount_amount = floatval($gift_info['gift_discount']);
                        $new_price = $original_price - $discount_amount;
                        $product->set_price(max(0, $new_price));
                    }

                    $debug_info['applied_gifts'][] = array(
                        'cart_item_key' => $cart_item_key,
                        'product_name' => $product_name,
                        'gift_type' => $gift_info['gift_type'],
                        'original_price' => $original_price,
                        'new_price' => $new_price,
                    );

                    // Mark this gift as applied
                    $applied_gifts[$gift_identifier] = true;

                    // Only apply to one item (the first qualifying one)
                    break;
                }
            }
        }

        $debug_info['result'] = count($debug_info['applied_gifts']) > 0 ? 'Applied ' . count($debug_info['applied_gifts']) . ' gifts' : 'No gifts applied';
        $this->output_cart_gift_debug($debug_info, $debug_output_done);
    }

    /**
     * Output debug info for cart gifts
     */
    private function output_cart_gift_debug(&$debug_info, &$debug_output_done) {
        // Storefront console output only in debug mode: it contains rule settings.
        if (!self::is_debug_enabled()) {
            $debug_output_done = true;
            return;
        }
        if (!$debug_output_done) {
            $debug_output_done = true;
            add_action('wp_footer', function() use ($debug_info) {
                // Output easy-to-read summary first. Every value goes through
                // wp_json_encode() so it is a safe JS literal.
                $log = function ( $label, $value ) {
                    echo 'console.log(' . wp_json_encode( (string) $label ) . ', ' . wp_json_encode( $value ) . ');';
                };
                echo '<script>';
                $log( '=== MMD CART GIFT DEBUG SUMMARY ===', '' );
                $log( 'Cart items count:', count( $debug_info['cart_items'] ) );
                $log( 'Cart-based gifts count:', $debug_info['cart_based_gifts_count'] ?? 0 );

                // Show each gift rule's details
                foreach ($debug_info as $key => $value) {
                    if (strpos($key, 'gift_rule_') === 0) {
                        $log( $key . ' gift_source:', $value['gift_source'] ?? 'not_set' );
                        $log( $key . ' gift_categories:', $value['gift_categories'] ?? array() );
                        $log( $key . ' gift_attribute_slug:', $value['gift_attribute_slug'] ?? '' );
                        $log( $key . ' gift_attribute_values:', $value['gift_attribute_values'] ?? array() );
                        $log( $key . ' gift_products_count:', $value['gift_products_count'] ?? 0 );
                        $log( $key . ' gift_products:', $value['gift_products'] ?? array() );
                    }
                }

                // Show cart items
                foreach ($debug_info['cart_items'] ?? [] as $idx => $item) {
                    $log( 'Cart item ' . $idx . ':', 'product_id=' . ($item['parent_product_id'] ?? 'unknown') . ', variation_id=' . ($item['variation_id'] ?? 0) . ', name=' . ($item['product_name'] ?? 'unknown') );
                }

                $log( 'Applied gifts:', count( $debug_info['applied_gifts'] ?? array() ) );
                $log( 'Result:', $debug_info['result'] ?? 'unknown' );
                $log( '=== FULL DEBUG DATA ===', '' );
                $log( 'MMD Cart Gift Apply Debug:', $debug_info );
                echo '</script>';
            }, 999);
        }
    }
    /**
     * Apply combined discount rules from gift rules
     * Called during woocommerce_before_calculate_totals
     */
    public function apply_combined_discount_rules($cart) {
        // Shoppers blocked by an add-on (filter `mmd_promotions_blocked`) get no promotions.
        if (MMD_Modules::promotions_blocked('gifts')) {
            return;
        }

        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }

        if (!class_exists('MMD_Gift_Rules')) {
            return;
        }

        $gift_rules = MMD_Gift_Rules::get_instance();
        $all_rules = $gift_rules->get_rule_sets();

        // Find rules with combined discounts enabled
        $combined_rules = array();
        foreach ($all_rules as $rule) {
            if (isset($rule['enabled']) && $rule['enabled'] === 'no') {
                continue;
            }
            if (!isset($rule['combined_discount_enabled']) || $rule['combined_discount_enabled'] !== 'yes') {
                continue;
            }
            if (empty($rule['combined_discount_rules'])) {
                continue;
            }

            // Check schedule
            $now = current_time('Y-m-d\TH:i');
            $sched_start = isset($rule['schedule_start']) ? $rule['schedule_start'] : '';
            $sched_end = isset($rule['schedule_end']) ? $rule['schedule_end'] : '';
            if (!empty($sched_start) && $now < $sched_start) continue;
            if (!empty($sched_end) && $now > $sched_end) continue;

            $combined_rules[] = $rule;
        }

        if (empty($combined_rules)) {
            return;
        }

        // Process each combined rule
        foreach ($combined_rules as $rule) {
            $matching_items = array();
            $total_quantity = 0;
            $discount_mode = isset($rule['combined_discount_mode']) ? $rule['combined_discount_mode'] : 'quantity';
            $apply_to = isset($rule['combined_discount_apply_to']) ? $rule['combined_discount_apply_to'] : 'cheapest';

            foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
                // Skip gift items
                if (isset($cart_item['mmd_is_gift']) && $cart_item['mmd_is_gift']) continue;
                if (isset($cart_item['mmd_cart_based_gift']) && $cart_item['mmd_cart_based_gift']) continue;

                $product_id = $cart_item['product_id'];

                if ($this->rule_matches($rule, $product_id, $gift_rules)) {
                    $matching_items[$cart_item_key] = $cart_item;
                    $total_quantity += $cart_item['quantity'];
                }
            }

            if (empty($matching_items)) {
                continue;
            }

            // Find the applicable discount tier
            $discount_rules = $rule['combined_discount_rules'];
            usort($discount_rules, function($a, $b) {
                return intval($b['quantity']) - intval($a['quantity']);
            });

            $applicable_discount = null;
            foreach ($discount_rules as $disc_rule) {
                if ($total_quantity >= intval($disc_rule['quantity'])) {
                    $applicable_discount = $disc_rule;
                    break;
                }
            }

            if (!$applicable_discount) {
                continue;
            }

            if ($discount_mode === 'global' || $apply_to === 'all') {
                // Global mode: apply discount to ALL matching items
                foreach ($matching_items as $cart_item_key => $cart_item) {
                    $product = $cart_item['data'];
                    $price = floatval($product->get_price());
                    $new_price = $this->calculate_combined_discount($price, $applicable_discount);
                    if (class_exists('MMD_Margin_Guards')) {
                        $new_price = MMD_Margin_Guards::enforce_margin_floor($new_price, $product, $rule);
                    }
                    $product->set_price(max(0, $new_price));
                }
            } else {
                // Quantity mode: apply to one item only
                // Flatten items with quantity > 1 for accurate cheapest/most expensive selection
                $flat_items = array();
                foreach ($matching_items as $cart_item_key => $cart_item) {
                    $price = floatval($cart_item['data']->get_price());
                    for ($i = 0; $i < $cart_item['quantity']; $i++) {
                        $flat_items[] = array(
                            'key' => $cart_item_key,
                            'price' => $price,
                            'item' => $cart_item,
                        );
                    }
                }

                // Sort by price
                if ($apply_to === 'cheapest') {
                    usort($flat_items, function($a, $b) { return $a['price'] <=> $b['price']; });
                } else {
                    usort($flat_items, function($a, $b) { return $b['price'] <=> $a['price']; });
                }

                // Apply discount to the target item (modify price for 1 unit)
                if (!empty($flat_items)) {
                    $target = $flat_items[0];
                    $product = $target['item']['data'];
                    $price = $target['price'];
                    $discount_amount = $this->calculate_combined_discount_amount($price, $applicable_discount);

                    // If the item has quantity > 1, we need to spread the discount using set_price
                    // Calculate average price: (original * qty - discount) / qty
                    $qty = $target['item']['quantity'];
                    if ($qty > 1) {
                        $total_original = $price * $qty;
                        $total_discounted = $total_original - $discount_amount;
                        $unit_price = $total_discounted / $qty;
                    } else {
                        $unit_price = max(0, $price - $discount_amount);
                    }
                    if (class_exists('MMD_Margin_Guards')) {
                        $unit_price = MMD_Margin_Guards::enforce_margin_floor($unit_price, $product, $rule);
                    }
                    $product->set_price(max(0, $unit_price));
                }
            }
        }
    }

    /**
     * Calculate discounted price for combined discount
     */
    private function calculate_combined_discount($price, $discount_rule) {
        if ($discount_rule['type'] === 'percentage') {
            return $price * (1 - (floatval($discount_rule['discount']) / 100));
        } else {
            return max(0, $price - floatval($discount_rule['discount']));
        }
    }

    /**
     * Calculate discount amount (how much to subtract)
     */
    private function calculate_combined_discount_amount($price, $discount_rule) {
        if ($discount_rule['type'] === 'percentage') {
            return $price * (floatval($discount_rule['discount']) / 100);
        } else {
            return min($price, floatval($discount_rule['discount']));
        }
    }

    /**
     * Check if a specific rule matches a product
     */
    private function rule_matches($rule, $product_id, $gift_rules = null) {
        if (!$gift_rules) {
            $gift_rules = MMD_Gift_Rules::get_instance();
        }
        $matching = $gift_rules->get_matching_rule_for_product($product_id);
        return $matching && $matching['id'] === $rule['id'];
    }

    /**
     * Display combined discount notice on product page
     */
    public function display_combined_discount_notice() {
        global $product;
        if (!$product) return;

        if (!class_exists('MMD_Gift_Rules')) return;

        $gift_rules = MMD_Gift_Rules::get_instance();
        $matching_rule = $gift_rules->get_matching_rule_for_product($product->get_id());

        if (!$matching_rule) return;
        if (!isset($matching_rule['combined_discount_enabled']) || $matching_rule['combined_discount_enabled'] !== 'yes') return;
        if (empty($matching_rule['combined_discount_rules'])) return;
        if (isset($matching_rule['combined_discount_notice_enabled']) && $matching_rule['combined_discount_notice_enabled'] === 'no') return;

        $notice_title = isset($matching_rule['combined_discount_notice_title']) ? $matching_rule['combined_discount_notice_title'] : __( '💰 Buy in quantity and save!', 'incentiv' );
        $notice_color = isset($matching_rule['combined_discount_notice_color']) ? $matching_rule['combined_discount_notice_color'] : '#4CAF50';
        $discount_mode = isset($matching_rule['combined_discount_mode']) ? $matching_rule['combined_discount_mode'] : 'quantity';
        $apply_to = isset($matching_rule['combined_discount_apply_to']) ? $matching_rule['combined_discount_apply_to'] : 'cheapest';

        $discount_rules = $matching_rule['combined_discount_rules'];
        usort($discount_rules, function($a, $b) {
            return intval($a['quantity']) - intval($b['quantity']);
        });

        $apply_text = '';
        if ($discount_mode === 'global' || $apply_to === 'all') {
            $apply_text = __( 'on all products', 'incentiv' );
        } elseif ($apply_to === 'cheapest') {
            $apply_text = __( 'on the cheapest one', 'incentiv' );
        } else {
            $apply_text = __( 'on the most expensive', 'incentiv' );
        }

        ?>
        <div class="mmd-combined-discount-notice" style="
            background: <?php echo esc_attr($notice_color); ?>;
            color: white;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
        ">
            <div style="font-weight: bold; font-size: 16px; margin-bottom: 10px;"><?php echo esc_html($notice_title); ?></div>
            <table style="width: 100%; border-collapse: collapse; background: rgba(255,255,255,0.15); border-radius: 6px; overflow: hidden;">
                <thead>
                    <tr style="background: rgba(255,255,255,0.2);">
                        <th style="padding: 8px 12px; text-align: start; font-size: 13px;"><?php esc_html_e( 'Quantity', 'incentiv' ); ?></th>
                        <th style="padding: 8px 12px; text-align: start; font-size: 13px;"><?php esc_html_e( 'Discount', 'incentiv' ); ?></th>
                        <th style="padding: 8px 12px; text-align: start; font-size: 13px;"><?php echo esc_html_x( 'Applies to', 'label', 'incentiv' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($discount_rules as $disc_rule):
                        $discount_text = '';
                        if ($disc_rule['type'] === 'percentage') {
                            $discount_text = $disc_rule['discount'] . '%';
                            if (floatval($disc_rule['discount']) >= 100) {
                                $discount_text = __( 'Free!', 'incentiv' );
                            }
                        } else {
                            $discount_text = wc_price($disc_rule['discount']);
                        }
                    ?>
                        <tr style="border-top: 1px solid rgba(255,255,255,0.2);">
                            <td style="padding: 8px 12px; font-size: 14px;">
                                <?php echo esc_html($disc_rule['quantity']); ?><?php esc_html_e( '+ items', 'incentiv' ); ?>
                            </td>
                            <td style="padding: 8px 12px; font-size: 14px; font-weight: bold;">
                                <?php echo wp_kses_post( $discount_text ); ?>
                            </td>
                            <td style="padding: 8px 12px; font-size: 13px;">
                                <?php echo esc_html($apply_text); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php
            // Also mention the gift if applicable
            $gift_type = isset($matching_rule['gift_type']) ? $matching_rule['gift_type'] : 'free';
            $gift_text = '';
            if ($gift_type === 'free') {
                $gift_text = __( 'Free gift', 'incentiv' );
            } elseif ($gift_type === 'discount') {
                $gift_text = ( __( 'A discount of', 'incentiv' ) . ' ' ) . $matching_rule['gift_discount'] . '%';
            } elseif ($gift_type === 'fixed_discount') {
                $gift_text = ( __( 'A discount of', 'incentiv' ) . ' ' ) . wc_price($matching_rule['gift_discount']);
            }
            if ($gift_text):
            ?>
                <div style="margin-top: 10px; font-size: 13px; opacity: 0.9;">
                    <?php esc_html_e( '🎁 Plus:', 'incentiv' ); ?> <?php echo esc_html($gift_text); ?> <?php esc_html_e( 'on a selected product!', 'incentiv' ); ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Display combined discount notice in cart/checkout
     */
    public function display_cart_combined_discount_notice() {
        if (!WC()->cart || WC()->cart->is_empty()) return;
        if (!class_exists('MMD_Gift_Rules')) return;

        $gift_rules = MMD_Gift_Rules::get_instance();
        $all_rules = $gift_rules->get_rule_sets();
        $displayed_rules = array();

        foreach (WC()->cart->get_cart() as $cart_item) {
            if (isset($cart_item['mmd_is_gift']) && $cart_item['mmd_is_gift']) continue;
            if (isset($cart_item['mmd_cart_based_gift']) && $cart_item['mmd_cart_based_gift']) continue;

            $product_id = $cart_item['product_id'];
            $matching_rule = $gift_rules->get_matching_rule_for_product($product_id);

            if (!$matching_rule) continue;
            if (in_array($matching_rule['id'], $displayed_rules)) continue;
            if (!isset($matching_rule['combined_discount_enabled']) || $matching_rule['combined_discount_enabled'] !== 'yes') continue;
            if (empty($matching_rule['combined_discount_rules'])) continue;
            if (isset($matching_rule['combined_discount_notice_enabled']) && $matching_rule['combined_discount_notice_enabled'] === 'no') continue;

            $displayed_rules[] = $matching_rule['id'];

            $notice_title = isset($matching_rule['combined_discount_notice_title']) ? $matching_rule['combined_discount_notice_title'] : __( '💰 Buy in quantity and save!', 'incentiv' );
            $notice_color = isset($matching_rule['combined_discount_notice_color']) ? $matching_rule['combined_discount_notice_color'] : '#4CAF50';

            $discount_rules = $matching_rule['combined_discount_rules'];
            usort($discount_rules, function($a, $b) {
                return intval($a['quantity']) - intval($b['quantity']);
            });

            // Count matching items in cart for this rule
            $current_qty = 0;
            foreach (WC()->cart->get_cart() as $ci) {
                if (isset($ci['mmd_is_gift']) && $ci['mmd_is_gift']) continue;
                if (isset($ci['mmd_cart_based_gift']) && $ci['mmd_cart_based_gift']) continue;
                if ($gift_rules->get_matching_rule_for_product($ci['product_id']) &&
                    $gift_rules->get_matching_rule_for_product($ci['product_id'])['id'] === $matching_rule['id']) {
                    $current_qty += $ci['quantity'];
                }
            }

            // Find next tier
            $current_tier = null;
            $next_tier = null;
            foreach ($discount_rules as $dr) {
                if ($current_qty >= intval($dr['quantity'])) {
                    $current_tier = $dr;
                } else {
                    if (!$next_tier) $next_tier = $dr;
                }
            }

            echo '<div class="woocommerce-info mmd-combined-discount-cart-notice" style="background: ' . esc_attr($notice_color) . '; color: white; border: none; padding: 12px 18px; border-radius: 8px; margin-bottom: 15px;">';
            echo '<strong>' . esc_html($notice_title) . '</strong>';

            if ($current_tier) {
                $disc_text = $current_tier['type'] === 'percentage' ? $current_tier['discount'] . '%' : wc_price($current_tier['discount']);
                echo ( '<div style="margin-top: 5px; font-size: 13px;">' . esc_html__( '✅ Active discount:', 'incentiv' ) . ' <strong>' ) . wp_kses_post( $disc_text ) . '</strong> (' . esc_html( $current_qty ) . ( ' ' . esc_html__( 'items in cart)', 'incentiv' ) . '</div>' );
            }

            if ($next_tier) {
                $need_more = intval($next_tier['quantity']) - $current_qty;
                $next_disc = $next_tier['type'] === 'percentage' ? $next_tier['discount'] . '%' : wc_price($next_tier['discount']);
                echo ( '<div style="margin-top: 5px; font-size: 13px;">' . esc_html__( '⬆️ Add', 'incentiv' ) . ' ' ) . esc_html( $need_more ) . ( ' ' . esc_html__( 'more items to get', 'incentiv' ) . ' <strong>' ) . esc_html( $next_disc ) . ( '</strong> ' . esc_html__( 'off!', 'incentiv' ) . '</div>' );
            }

            echo '</div>';
        }
    }

    /**
     * Shortcode for gift selection - allows embedding on any page
     * Usage: [mmd_gift_selection] or [mmd_gift_selection product_id="123"]
     */
    public function gift_selection_shortcode($atts) {
        $atts = shortcode_atts(array(
            'product_id' => '',
            'context' => 'auto', // 'product', 'cart', 'auto'
        ), $atts, 'mmd_gift_selection');

        ob_start();

        $context = $atts['context'];
        if ($context === 'auto') {
            if (is_cart() || is_checkout()) {
                $context = 'cart';
            } else {
                $context = 'product';
            }
        }

        if ($context === 'cart') {
            $this->display_cart_gift_selection();
        } else {
            // Product context
            if (!empty($atts['product_id'])) {
                global $product;
                $original_product = $product;
                $product = wc_get_product(intval($atts['product_id']));
                if ($product) {
                    $this->display_gift_selection();
                }
                $product = $original_product;
            } else {
                $this->display_gift_selection();
            }
        }

        return ob_get_clean();
    }

    /**
     * Inject gift selection on Elementor-rendered product pages
     * This handles cases where Elementor replaces the standard WooCommerce product template
     */
    public function inject_gift_selection_elementor() {
        if (!is_product()) {
            return;
        }

        // Check if the gift selection was already rendered by a hook or widget
        if (self::$gift_selection_rendered) {
            return;
        }

        $post_id = get_the_ID();
        if (!$post_id) {
            $queried = get_queried_object();
            if ($queried && isset($queried->ID)) {
                $post_id = $queried->ID;
            }
        }

        if (!$post_id || get_post_type($post_id) !== 'product') {
            return;
        }

        // Ensure product global is set
        global $product;
        if (!$product || !is_object($product)) {
            $product = wc_get_product($post_id);
        }

        if (!$product) {
            return;
        }

        $gift_settings = $this->get_gift_settings($product->get_id());
        if (!$gift_settings || empty($gift_settings['gift_products'])) {
            return;
        }

        // Only inject for product_page mode gifts
        $gift_mode = isset($gift_settings['gift_mode']) ? $gift_settings['gift_mode'] : 'product_page';
        if ($gift_mode !== 'product_page') {
            return;
        }

        // Render gift selection HTML
        ob_start();
        $this->display_gift_selection();
        $gift_html = ob_get_clean();

        if (empty($gift_html)) {
            return;
        }

        $gift_html_escaped = wp_json_encode($gift_html);

        ?>
        <script>
        jQuery(document).ready(function($) {
            // Skip if already injected
            if ($('.mmd-gift-selection-container').length) return;

            // Extended target list for all known Elementor product page structures
            var insertTargets = [
                // Elementor Pro product widgets
                {sel: '.elementor-widget-woocommerce-product-add-to-cart', method: 'before'},
                {sel: '.elementor-add-to-cart', method: 'before'},
                // Standard WC form
                {sel: 'form.cart', method: 'before'},
                // Elementor product content areas
                {sel: '.elementor-widget-woocommerce-product-content', method: 'append'},
                {sel: '.elementor-widget-woocommerce-product-short-description', method: 'after'},
                {sel: '.elementor-widget-woocommerce-product-price', method: 'after'},
                // Theme fallbacks
                {sel: '.product .summary', method: 'append'},
                {sel: '.product .entry-summary', method: 'append'},
                {sel: '.product-info', method: 'append'},
                // WoodMart theme
                {sel: '.product-image-summary .summary-inner', method: 'append'},
                // Astra/OceanWP
                {sel: '.ast-single-product-content', method: 'append'},
                // Generic product container
                {sel: '.product', method: 'append'}
            ];

            var html = <?php echo esc_html( $gift_html_escaped ); ?>;

            for (var i = 0; i < insertTargets.length; i++) {
                var $el = $(insertTargets[i].sel).first();
                if ($el.length) {
                    if (insertTargets[i].method === 'before') {
                        $el.before(html);
                    } else if (insertTargets[i].method === 'after') {
                        $el.after(html);
                    } else {
                        $el.append(html);
                    }

                    // Ensure the container has correct inline styles
                    var $container = $('.mmd-gift-selection-container');
                    $container.css({
                        'display': 'block',
                        'position': 'static',
                        'visibility': 'visible',
                        'opacity': '1'
                    });

                    // Ensure hidden fields are in the form
                    var $form = $('form.cart');
                    if ($form.length && !$form.find('#mmd_selected_gift_product').length) {
                        $form.append('<input type="hidden" name="mmd_selected_gift_product" id="mmd_selected_gift_product" value="">');
                        $form.append('<input type="hidden" name="mmd_selected_gift_variation" id="mmd_selected_gift_variation" value="">');
                        $form.append('<input type="hidden" name="mmd_selected_gift_attributes" id="mmd_selected_gift_attributes" value="">');
                    }

                    console.log('MMD: Gift selection injected via', insertTargets[i].sel, insertTargets[i].method);
                    return;
                }
            }

            console.log('MMD: Could not find suitable injection target for gift selection');
        });
        </script>
        <?php
    }

    /**
     * Display interactive gift selection on cart/checkout pages
     * This replaces the static notice with a full product selector
     */
    public function display_cart_gift_selection() {
        if (!WC()->cart) {
            return;
        }
        // We intentionally allow rendering on an empty cart: coupon-driven
        // gifts surface as soon as the coupon is applied, even before the
        // customer adds any other product.

        $cart_based_gifts = $this->get_cart_based_gift_eligibility();

        if (empty($cart_based_gifts)) {
            return;
        }

        // Get styling options
        $styling = array();
        if (class_exists('MMD_Gift_Rules')) {
            $gift_rules = MMD_Gift_Rules::get_instance();
            $styling = $gift_rules->get_styling_options();
        }

        // Check if modal display is enabled
        $show_as_modal = false;
        if (isset($cart_based_gifts[0]['rule']['cart_gift_display_mode'])) {
            $show_as_modal = $cart_based_gifts[0]['rule']['cart_gift_display_mode'] === 'modal';
        }

        foreach ($cart_based_gifts as $gift_info) {
            $gift_claimed = $this->is_cart_gift_claimed($gift_info);
            $rendered_key = isset($gift_info['rule_id']) ? (string) $gift_info['rule_id'] : '';

            $gift_type_text = '';
            if ($gift_info['gift_type'] === 'free') {
                $gift_type_text = __( 'Free gift', 'incentiv' );
            } elseif ($gift_info['gift_type'] === 'discount') {
                $gift_type_text = ( __( 'A discount of', 'incentiv' ) . ' ' ) . $gift_info['gift_discount'] . '%';
            } elseif ($gift_info['gift_type'] === 'fixed_discount') {
                $gift_type_text = ( __( 'A discount of', 'incentiv' ) . ' ' ) . wc_price($gift_info['gift_discount']);
            }

            if ($gift_claimed) {
                $rule_id_for_switch = isset($gift_info['rule_id']) ? $gift_info['rule_id'] : '';
                echo '<div class="woocommerce-info mmd-cart-gift-notice mmd-gift-claimed" style="background: linear-gradient(135deg, #7c3aed 0%, #a855f7 100%); color: white; border: none; padding: 15px; border-radius: 8px; margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap;">';
                echo ( '<span><span class="mmd-gift-icon" style="font-size: 1.3em;">🎁</span> ' . esc_html__( '✅ Your gift was added to the cart! (', 'incentiv' ) ) . esc_html($gift_type_text) . ')</span>';
                echo '<button type="button" class="mmd-switch-gift-btn" data-rule-id="' . esc_attr($rule_id_for_switch) . ( '" style="background: rgba(255,255,255,0.18); color: #fff; border: 1px solid rgba(255,255,255,0.5); padding: 6px 14px; border-radius: 16px; cursor: pointer; font-size: 13px; font-weight: 500;">' . esc_html_x( 'Switch gift', 'picker', 'incentiv' ) . '</button>' );
                echo '</div>';
                self::mark_gift_rendered($rendered_key);
                continue;
            }

            $gift_products = $gift_info['gift_products'];
            if (empty($gift_products)) continue;
            self::mark_gift_rendered($rendered_key);

            $notice_text = $gift_info['notice'];
            $gift_selection_type = isset($gift_info['rule']['gift_selection_type']) ? $gift_info['rule']['gift_selection_type'] : 'single';
            $trigger_product_id = $gift_info['trigger_product_id'];
            $rule_id = isset($gift_info['rule_id']) ? $gift_info['rule_id'] : '';

            // Show all qualifying products — the selection limit is enforced by JS, not by truncation

            // Modal mode: show trigger button + hidden modal
            if ($show_as_modal) {
                echo '<div class="woocommerce-info mmd-cart-gift-notice" style="background: linear-gradient(135deg, #7c3aed 0%, #a855f7 100%); color: white; border: none; padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: center;">';
                echo '<span style="font-size: 1.3em;">🎁</span> ' . esc_html($notice_text) . ' <strong>(' . esc_html($gift_type_text) . ')</strong><br>';
                echo ( '<button type="button" class="mmd-open-gift-modal-btn" style="margin-top: 10px; background: white; color: #7c3aed; border: none; padding: 10px 24px; border-radius: 24px; font-weight: bold; cursor: pointer; font-size: 14px;">' . esc_html__( 'Choose a gift', 'incentiv' ) . '</button>' );
                echo '</div>';
            }

            // Build the selector content (used both inline and in modal)
            $container_class = $show_as_modal ? 'mmd-cart-gift-modal-content' : '';
            $container_style = $show_as_modal ? 'display: none;' : 'display: block !important; position: static !important;';
            ?>
            <?php if ($show_as_modal): ?>
            <div class="mmd-cart-gift-modal-overlay" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; z-index: 99999; background: rgba(0,0,0,0.6); align-items: center; justify-content: center;">
                <div style="background: white; border-radius: 12px; max-width: 700px; width: 90%; max-height: 85vh; overflow-y: auto; position: relative;">
                    <button type="button" class="mmd-close-gift-modal" style="position: absolute; top: 10px; left: 10px; background: none; border: none; font-size: 24px; cursor: pointer; z-index: 1; color: #999;">&times;</button>
            <?php endif; ?>

            <div class="mmd-cart-gift-selection-container mmd-gift-selection-container <?php echo esc_attr($container_class); ?>"
                 data-trigger-product="<?php echo esc_attr($trigger_product_id); ?>"
                 data-rule-id="<?php echo esc_attr($rule_id); ?>"
                 data-gift-type="<?php echo esc_attr($gift_info['gift_type']); ?>"
                 data-gift-discount="<?php echo esc_attr($gift_info['gift_discount']); ?>"
                 data-selection-type="<?php echo esc_attr($gift_selection_type); ?>"
                 style="<?php echo esc_attr( $container_style ); ?>">
                <div class="mmd-gift-title">🎁 <?php echo esc_html($notice_text); ?></div>
                <p class="mmd-gift-subtitle">
                    <?php echo esc_html($gift_type_text); ?> -
                    <?php echo $gift_selection_type === 'multiple' ? esc_html__( 'Choose one or more:', 'incentiv' ) : esc_html__( 'Choose one product:', 'incentiv' ); ?>
                </p>

                <div class="mmd-gift-products-table">
                    <table class="mmd-gift-table">
                        <tbody>
                            <?php foreach ($gift_products as $gift_product_id):
                                $gift_product = wc_get_product($gift_product_id);
                                if (!$gift_product) continue;
                                if (!$gift_product->is_in_stock()) continue;

                                $is_variable = $gift_product->is_type('variable');
                                $is_variation = $gift_product->is_type('variation');

                                $display_name = $gift_product->get_name();
                                $actual_product_id = $gift_product_id;
                                $variation_id = 0;
                                if ($is_variation) {
                                    $variation_id = $gift_product_id;
                                    $actual_product_id = $gift_product->get_parent_id();
                                }

                                // For variable products, build per-attribute dropdowns
                                $attribute_selectors = array();
                                $variations_json = '';
                                $pre_selected_attrs = array(); // Attributes pre-set by the gift rule

                                // Get pre-selected attribute from gift rule (if any)
                                $rule_attr_slug = isset($gift_info['gift_attribute_slug']) ? $gift_info['gift_attribute_slug'] : '';
                                $rule_attr_values = isset($gift_info['gift_attribute_values']) ? $gift_info['gift_attribute_values'] : array();

                                if ($is_variable) {
                                    $available_variations = $gift_product->get_available_variations();
                                    $variation_attributes = $gift_product->get_variation_attributes();

                                    // Build the rule's attribute → [term_ids] filter map.
                                    // SAME shape the working product-page selector uses
                                    // (display_gift_selection / get_variation_data) so the
                                    // resolution logic is identical.
                                    $rule_filtered_attrs = array();
                                    if (!empty($rule_attr_slug) && !empty($rule_attr_values)) {
                                        $rule_filtered_attrs[$rule_attr_slug] = array_map('intval', (array) $rule_attr_values);
                                    }

                                    // Default for the customer's dropdown:
                                    // resolve the rule's gift_default_attr_value
                                    // (term ID) → slug. We pass slug → key map
                                    // into the selector renderer so it emits
                                    // `selected` on the matching <option>.
                                    $default_attr_slug  = isset($gift_info['gift_default_attr_slug']) ? $gift_info['gift_default_attr_slug'] : '';
                                    $default_attr_value = isset($gift_info['gift_default_attr_value']) ? intval($gift_info['gift_default_attr_value']) : 0;
                                    $default_per_attr   = array(); // attr_slug => option_value (slug)
                                    if (!empty($default_attr_slug) && $default_attr_value > 0) {
                                        $d_term = get_term($default_attr_value, $default_attr_slug);
                                        if ($d_term && !is_wp_error($d_term)) {
                                            $default_per_attr[$default_attr_slug] = $d_term->slug;
                                        }
                                    }

                                    // Per-attribute resolution — mirrors the product-page
                                    // path verbatim:
                                    //   - exactly one term-id in the rule → auto-fill
                                    //     (taxonomy-aware get_term($id, $taxonomy) ensures
                                    //     the right term is read for Hebrew taxonomies)
                                    //   - multiple term-ids → restrict the dropdown to the
                                    //     options whose term-id is in the set
                                    //   - no rule constraint → keep the parent's full
                                    //     option list
                                    foreach ($variation_attributes as $attr_name => $options) {
                                        $taxonomy = strpos($attr_name, 'pa_') === 0 ? $attr_name : '';

                                        // Build labeled option list for this attribute.
                                        $option_labels = array();
                                        foreach ($options as $opt_val) {
                                            $opt_label = $opt_val;
                                            if ($taxonomy) {
                                                $term = get_term_by('slug', $opt_val, $taxonomy);
                                                if ($term) $opt_label = $term->name;
                                            }
                                            $option_labels[] = array('value' => $opt_val, 'label' => $opt_label);
                                        }

                                        $auto_filled = false;
                                        if (isset($rule_filtered_attrs[$attr_name]) && !empty($rule_filtered_attrs[$attr_name])) {
                                            $rule_term_ids = $rule_filtered_attrs[$attr_name];

                                            if (count($rule_term_ids) === 1) {
                                                // Single term → auto-fill (hide dropdown).
                                                $term = get_term($rule_term_ids[0], $attr_name);
                                                if ($term && !is_wp_error($term)) {
                                                    $pre_selected_attrs[$attr_name] = $term->slug;
                                                    $auto_filled = true;
                                                }
                                            } else {
                                                // Multiple terms → keep only matching opts.
                                                $filtered_options = array();
                                                foreach ($option_labels as $opt) {
                                                    $opt_term = get_term_by('slug', $opt['value'], $attr_name);
                                                    if ($opt_term && in_array($opt_term->term_id, $rule_term_ids, true)) {
                                                        $filtered_options[] = $opt;
                                                    }
                                                }
                                                if (!empty($filtered_options)) {
                                                    $option_labels = $filtered_options;
                                                    if (count($filtered_options) === 1) {
                                                        // Only one option survived → auto-fill.
                                                        $pre_selected_attrs[$attr_name] = $filtered_options[0]['value'];
                                                        $auto_filled = true;
                                                    }
                                                }
                                            }
                                        }

                                        if (!$auto_filled) {
                                            $label = wc_attribute_label($attr_name);
                                            $default_value_for_attr = '';
                                            if (isset($default_per_attr[$attr_name])) {
                                                // Only use the default if it
                                                // actually exists among the
                                                // dropdown's surviving options.
                                                $candidate = $default_per_attr[$attr_name];
                                                foreach ($option_labels as $opt_check) {
                                                    if ($opt_check['value'] === $candidate) {
                                                        $default_value_for_attr = $candidate;
                                                        break;
                                                    }
                                                }
                                            }
                                            $attribute_selectors[] = array(
                                                'name'    => $attr_name,
                                                'label'   => $label,
                                                'options' => $option_labels,
                                                'default' => $default_value_for_attr,
                                            );
                                        }
                                    }

                                    // Filter the variations JSON to only those whose
                                    // attribute values are compatible with $pre_selected_attrs.
                                    // Keep variations with empty ("any") slots — the
                                    // pre-selected value fills them on the JS side.
                                    $allowed_slugs_per_attr = array();
                                    foreach ($pre_selected_attrs as $a_name => $a_val) {
                                        $allowed_slugs_per_attr[$a_name] = array($a_val);
                                    }
                                    // Multi-term restrictions also constrain the JSON.
                                    foreach ($rule_filtered_attrs as $a_name => $term_ids) {
                                        if (isset($pre_selected_attrs[$a_name])) continue;
                                        $slugs = array();
                                        foreach ($term_ids as $tid) {
                                            $term = get_term($tid, $a_name);
                                            if ($term && !is_wp_error($term)) $slugs[] = $term->slug;
                                        }
                                        if (!empty($slugs)) $allowed_slugs_per_attr[$a_name] = $slugs;
                                    }

                                    $filtered_variations = $available_variations;
                                    if (!empty($allowed_slugs_per_attr)) {
                                        $filtered_variations = array();
                                        foreach ($available_variations as $av) {
                                            $matches = true;
                                            foreach ($allowed_slugs_per_attr as $a_name => $allowed_slugs) {
                                                $val = '';
                                                if (!empty($av['attributes'])) {
                                                    foreach ($av['attributes'] as $av_k => $av_v) {
                                                        $bare = urldecode(preg_replace('/^attribute_/', '', $av_k));
                                                        if ($bare === $a_name) { $val = $av_v; break; }
                                                    }
                                                }
                                                // "any" (empty) is fine — pre-select fills it.
                                                if ($val !== '' && !in_array($val, $allowed_slugs, true)) {
                                                    $matches = false;
                                                    break;
                                                }
                                            }
                                            if ($matches) $filtered_variations[] = $av;
                                        }
                                    }

                                    // Build variations data for JS (decoded keys so the
                                    // JS matcher can compare against the dropdown's
                                    // data-attribute = "attribute_<bare-decoded-name>").
                                    $var_data = array();
                                    foreach ($filtered_variations as $av) {
                                        $vp = wc_get_product($av['variation_id']);
                                        if (!$vp || !$vp->is_in_stock()) continue;
                                        $normalized_attrs = array();
                                        if (!empty($av['attributes'])) {
                                            foreach ($av['attributes'] as $av_k => $av_v) {
                                                $bare = urldecode(preg_replace('/^attribute_/', '', $av_k));
                                                $normalized_attrs['attribute_' . $bare] = $av_v;
                                            }
                                        }
                                        $var_data[] = array(
                                            'id'         => $av['variation_id'],
                                            'attributes' => $normalized_attrs,
                                            'price'      => $vp->get_price(),
                                        );
                                    }
                                    $variations_json = wp_json_encode($var_data);
                                }
                            ?>
                                <tr class="mmd-gift-product-row mmd-cart-gift-row"
                                    data-product-id="<?php echo esc_attr($actual_product_id); ?>"
                                    data-variation-id="<?php echo esc_attr($variation_id); ?>"
                                    data-variable="<?php echo $is_variable ? '1' : '0'; ?>"
                                    <?php if ($variations_json): ?>data-variations="<?php echo esc_attr($variations_json); ?>"<?php endif; ?>>
                                    <td class="mmd-gift-image-cell">
                                        <?php echo wp_kses_post( $gift_product->get_image('thumbnail') ); ?>
                                    </td>
                                    <td class="mmd-gift-name-cell">
                                        <?php echo esc_html($display_name); ?>
                                        <?php if (!$is_variable && !$is_variation):
                                            $regular_price = $gift_product->get_regular_price();
                                            if ($regular_price):
                                        ?>
                                            <div style="font-size: 12px; color: rgba(0,0,0,0.5); margin-top: 2px;">
                                                <?php
                                                if ($gift_info['gift_type'] === 'free') {
                                                    echo '<del>' . wp_kses_post( wc_price($regular_price) ) . ( '</del> <strong style="color: #4CAF50;">' . esc_html__( 'Free!', 'incentiv' ) . '</strong>' );
                                                } elseif ($gift_info['gift_type'] === 'discount') {
                                                    $discounted = floatval($regular_price) * (1 - floatval($gift_info['gift_discount']) / 100);
                                                    echo '<del>' . wp_kses_post( wc_price($regular_price) ) . '</del> ' . wp_kses_post( wc_price($discounted) );
                                                } elseif ($gift_info['gift_type'] === 'fixed_discount') {
                                                    $discounted = max(0, floatval($regular_price) - floatval($gift_info['gift_discount']));
                                                    echo '<del>' . wp_kses_post( wc_price($regular_price) ) . '</del> ' . wp_kses_post( wc_price($discounted) );
                                                }
                                                ?>
                                            </div>
                                        <?php endif; endif; ?>
                                    </td>
                                    <td class="mmd-gift-variations-cell">
                                        <?php if ($is_variable && !empty($attribute_selectors)): ?>
                                            <div class="mmd-cart-gift-attributes" data-product-id="<?php echo esc_attr($gift_product_id); ?>">
                                                <?php foreach ($attribute_selectors as $attr):
                                                    $attr_default = isset($attr['default']) ? $attr['default'] : '';
                                                    ?>
                                                    <div class="mmd-cart-gift-attr-field" style="margin-bottom: 6px;">
                                                        <label style="display: block; font-size: 12px; color: #666; margin-bottom: 2px;"><?php echo esc_html($attr['label']); ?></label>
                                                        <select class="mmd-cart-gift-attr-select"
                                                                data-attribute="attribute_<?php echo esc_attr($attr['name']); ?>"
                                                                style="width: 100%; padding: 6px; border: 1px solid #ddd; border-radius: 4px; font-size: 13px;">
                                                            <option value=""<?php echo $attr_default === '' ? '' : ''; ?>><?php esc_html_e( 'Choose', 'incentiv' ); ?> <?php echo esc_html($attr['label']); ?>...</option>
                                                            <?php foreach ($attr['options'] as $opt): ?>
                                                                <option value="<?php echo esc_attr($opt['value']); ?>" <?php selected($opt['value'], $attr_default); ?>><?php echo esc_html($opt['label']); ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="mmd-gift-action-cell">
                                        <button type="button" class="mmd-gift-select-btn mmd-cart-gift-add-btn"
                                                data-product-id="<?php echo esc_attr($is_variation ? $actual_product_id : $gift_product_id); ?>"
                                                data-variation-id="<?php echo esc_attr($variation_id); ?>"
                                                data-trigger-product="<?php echo esc_attr($trigger_product_id); ?>"
                                                data-rule-id="<?php echo esc_attr($rule_id); ?>"
                                                data-gift-type="<?php echo esc_attr($gift_info['gift_type']); ?>"
                                                data-gift-discount="<?php echo esc_attr($gift_info['gift_discount']); ?>"
                                                data-pre-selected="<?php echo esc_attr(wp_json_encode($pre_selected_attrs)); ?>">
                                            <?php esc_html_e( 'Add to cart', 'incentiv' ); ?>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php if ($show_as_modal): ?>
                </div>
            </div>
            <?php endif; ?>
            <?php
        }
    }

    /**
     * AJAX handler for adding a gift from cart/checkout page
     */
    public function ajax_add_cart_gift() {
        if (!check_ajax_referer('mmd_gift_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => 'Security check failed'));
            return;
        }

        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
        $variation_id = isset($_POST['variation_id']) ? intval($_POST['variation_id']) : 0;
        $trigger_product_id = isset($_POST['trigger_product_id']) ? intval($_POST['trigger_product_id']) : 0;
        $gift_type = isset($_POST['gift_type']) ? sanitize_text_field($_POST['gift_type']) : 'free';
        $gift_discount = isset($_POST['gift_discount']) ? floatval($_POST['gift_discount']) : 0;
        $rule_id = isset($_POST['rule_id']) ? sanitize_text_field($_POST['rule_id']) : '';

        // Attributes the client picked (visible dropdowns + pre-selected from
        // the rule). Keys are full WC attribute keys, e.g. attribute_pa_weight.
        //
        // CRITICAL: do NOT run sanitize_text_field() on these values. That
        // helper strips every URL-encoded sequence ("%XX") from the string.
        // Hebrew taxonomy slugs are stored as URL-encoded ("100-%d7%92%d7%a8%d7%9d"),
        // so sanitize_text_field would gut "100-%d7%92%d7%a8%d7%9d" down to
        // "100-", which then fails WC's term-slug check and renders as
        // "X is a required field" / "Invalid value posted for X".
        // We accept the value as-is (forced to string + invalid-UTF8 check)
        // and let WC handle whatever sanitization it needs.
        $client_attrs = array();
        if (isset($_POST['attributes']) && is_array($_POST['attributes'])) {
            foreach ($_POST['attributes'] as $k => $v) {
                if (!is_scalar($k) || !is_scalar($v)) continue;
                $clean_key = wp_check_invalid_utf8((string) $k);
                $clean_val = wp_check_invalid_utf8((string) $v);
                if ($clean_key === '' || $clean_val === '') continue;
                $client_attrs[$clean_key] = $clean_val;
            }
        }

        if (!$product_id) {
            wp_send_json_error(array('message' => 'Missing product ID'));
            return;
        }

        // Prepare cart item data
        $gift_cart_data = array(
            'mmd_cart_based_gift' => true,
            'mmd_is_gift' => true,
            'mmd_gift_type' => $gift_type,
            'mmd_gift_discount' => $gift_discount,
            'mmd_parent_product_id' => $trigger_product_id,
            'mmd_gift_rule_id' => $rule_id,
        );

        $cart_item_key = false;

        if ($variation_id) {
            $variation_product = wc_get_product($variation_id);
            if (!$variation_product || !($variation_product instanceof WC_Product_Variation)) {
                wp_send_json_error(array('message' => __( 'The selected variation is not available', 'incentiv' )));
                return;
            }
            if (!$variation_product->is_purchasable() || !$variation_product->is_in_stock()) {
                wp_send_json_error(array('message' => __( 'The selected variation is not available', 'incentiv' )));
                return;
            }

            $dbg_file = MMD_PLUGIN_DIR . 'debug-cart-gift.log';
            $dbg = function ($label, $data = null) use ($dbg_file) {
                $line = '[' . gmdate('Y-m-d H:i:s') . '] ' . $label;
                if ($data !== null) $line .= ' ' . print_r($data, true);
                @file_put_contents($dbg_file, $line . "\n", FILE_APPEND);
            };
            $dbg('=== ajax_add_cart_gift START ===');
            $dbg('POST attributes', isset($_POST['attributes']) ? $_POST['attributes'] : '(none)');
            $dbg('client_attrs (sanitized)', $client_attrs);
            $dbg('variation_id', $variation_id);

            // SIMULATE A REAL PRODUCT-PAGE FORM SUBMISSION
            // ================================================================
            // The original gift system works because the customer literally
            // submits a <form action="?add-to-cart=X"> on the product page.
            // WC_Add_To_Cart_Handler_Variable reads $_REQUEST for each
            // variation attribute under the EXACT key 'attribute_' .
            // sanitize_title($attr->get_name()), builds the $variations
            // array, and calls WC()->cart->add_to_cart() with it.
            //
            // We replicate that flow exactly:
            //   1. Populate $_REQUEST with the parent ID, variation ID, and
            //      every attribute value under WC's canonical key.
            //   2. Build $variations the WC way — iterate the parent's
            //      variation attributes, fetch each value from $_REQUEST,
            //      sanitize_title() if it's a taxonomy value.
            //   3. Hand the $variations array to add_to_cart() exactly as
            //      WC's own handler does.
            // ================================================================
            $parent_p = wc_get_product($variation_product->get_parent_id());
            if (!$parent_p) {
                wp_send_json_error(array('message' => __( 'Parent product not found', 'incentiv' )));
                return;
            }

            // Stash the original $_REQUEST so we can restore after the call.
            $orig_request = $_REQUEST;
            $orig_post    = $_POST;

            $_REQUEST['add-to-cart']  = $variation_product->get_parent_id();
            $_REQUEST['variation_id'] = $variation_id;
            $_REQUEST['quantity']     = 1;
            $_POST['add-to-cart']     = $variation_product->get_parent_id();
            $_POST['variation_id']    = $variation_id;
            $_POST['quantity']        = 1;

            // For each variation attribute on the parent, find a value for
            // it from the client payload and put it in $_REQUEST under the
            // exact key WC's handler reads.
            $variation_stored = wc_get_product_variation_attributes($variation_id);
            $variations       = array();

            foreach ($parent_p->get_attributes() as $attribute) {
                if (!method_exists($attribute, 'get_variation') || !$attribute->get_variation()) {
                    continue;
                }
                $attr_name      = $attribute->get_name();
                $attribute_key  = sanitize_title($attr_name);
                $wc_canonical   = 'attribute_' . $attribute_key;

                // Resolve a value the way WC does:
                //   - from $_REQUEST under the canonical key (we populate it
                //     below from the client payload), then
                //   - from the variation's stored attrs (the "any" case
                //     where the variation has a specific value),
                //   - then first allowed option as a last-resort.
                $client_candidates = array_unique(array(
                    $wc_canonical,
                    'attribute_' . $attr_name,
                    'attribute_' . urldecode($attr_name),
                    'attribute_' . urlencode($attr_name),
                    $attr_name,
                    urldecode($attr_name),
                    urlencode($attr_name),
                ));

                $picked = '';
                foreach ($client_candidates as $cand) {
                    if (isset($client_attrs[$cand]) && $client_attrs[$cand] !== '' && $client_attrs[$cand] !== null) {
                        $picked = $client_attrs[$cand];
                        break;
                    }
                }

                // Variation's stored value
                if ($picked === '' && isset($variation_stored[$wc_canonical]) && $variation_stored[$wc_canonical] !== '') {
                    $picked = $variation_stored[$wc_canonical];
                }
                // First option fallback
                if ($picked === '') {
                    $opts = $attribute->get_options();
                    if (!empty($opts)) {
                        $first = reset($opts);
                        if ($attribute->is_taxonomy()) {
                            $term = get_term($first, $attr_name);
                            if ($term && !is_wp_error($term)) {
                                $picked = $term->slug;
                            }
                        } else {
                            $picked = (string) $first;
                        }
                    }
                }

                // Populate $_REQUEST under the WC-canonical key so any
                // validator that reads from the superglobal sees it.
                $_REQUEST[$wc_canonical] = $picked;
                $_POST[$wc_canonical]    = $picked;

                // Build $variations exactly as WC_Add_To_Cart_Handler_Variable does.
                if ($attribute->is_taxonomy() && is_string($picked)) {
                    $variations[$wc_canonical] = sanitize_title($picked);
                } else {
                    $variations[$wc_canonical] = (string) $picked;
                }
            }

            $dbg('variations (WC-style)', $variations);
            $dbg('_REQUEST after population', array_intersect_key($_REQUEST, array_flip(array_keys($variations) + array('add-to-cart', 'variation_id', 'quantity'))));

            $cart_item_key = WC()->cart->add_to_cart(
                $variation_product->get_parent_id(),
                1,
                $variation_id,
                $variations,
                $gift_cart_data
            );

            $dbg('add_to_cart returned', $cart_item_key);
            if (!$cart_item_key) {
                $dbg('wc notices', wc_get_notices());
            }

            // Restore superglobals.
            $_REQUEST = $orig_request;
            $_POST    = $orig_post;
        } else {
            $product = wc_get_product($product_id);
            if (!$product || !$product->is_in_stock()) {
                wp_send_json_error(array('message' => __( 'The product is not available', 'incentiv' )));
                return;
            }
            $cart_item_key = WC()->cart->add_to_cart($product_id, 1, 0, array(), $gift_cart_data);
        }

        if (!$cart_item_key) {
            $notices = wc_get_notices('error');
            wc_clear_notices();
            $error_message = _x( 'Error adding the gift to the cart', 'server', 'incentiv' );
            if (!empty($notices)) {
                $error_message .= ': ' . $notices[0]['notice'];
            }
            // Echo back the actual payload + the keys WC was looking for so
            // we can see why validation rejected this on the live site
            // (visible in the browser DevTools network response).
            $debug = array(
                'variation_id'           => $variation_id,
                'product_id'             => $product_id,
                'sent_attributes'        => isset($final_variation_attributes) ? $final_variation_attributes : (isset($variation_attributes) ? $variation_attributes : null),
                'client_attrs'           => $client_attrs,
            );
            if ($variation_id) {
                $vp = wc_get_product($variation_id);
                if ($vp) {
                    $parent_p = wc_get_product($vp->get_parent_id());
                    $expected_keys = array();
                    if ($parent_p) {
                        foreach ($parent_p->get_attributes() as $attr) {
                            if (method_exists($attr, 'get_variation') && $attr->get_variation()) {
                                $expected_keys[] = 'attribute_' . sanitize_title($attr->get_name());
                            }
                        }
                    }
                    $debug['wc_expected_keys'] = $expected_keys;
                    if (function_exists('wc_get_product_variation_attributes')) {
                        $debug['wc_variation_attrs_helper'] = wc_get_product_variation_attributes($variation_id);
                    }
                }
            }
            wp_send_json_error(array('message' => $error_message, 'debug' => $debug));
            return;
        }

        wp_send_json_success(array(
            'message' => __( 'The gift was added to the cart!', 'incentiv' ),
            'cart_item_key' => $cart_item_key,
        ));
    }

    /**
     * AJAX: remove the customer's currently-claimed cart-based gift for the
     * given rule so the selector re-appears and the customer can pick again.
     */
    public function ajax_switch_cart_gift() {
        if (!check_ajax_referer('mmd_gift_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => 'Security check failed'));
        }
        if (!WC()->cart) {
            wp_send_json_error(array('message' => 'Cart unavailable'));
        }

        $rule_id = isset($_POST['rule_id']) ? sanitize_text_field($_POST['rule_id']) : '';

        $removed = 0;
        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
            $is_gift = !empty($cart_item['mmd_cart_based_gift']) || !empty($cart_item['mmd_is_gift']);
            if (!$is_gift) continue;

            // If a rule_id is specified, only remove gifts belonging to that
            // rule. Empty rule_id means remove every cart-based gift (used
            // when the claimed notice doesn't know which rule it came from).
            if ($rule_id !== '') {
                $item_rule_id = isset($cart_item['mmd_gift_rule_id']) ? $cart_item['mmd_gift_rule_id'] : '';
                if ($item_rule_id !== $rule_id) continue;
            }

            WC()->cart->remove_cart_item($cart_item_key);
            $removed++;
        }

        if ($removed === 0) {
            wp_send_json_error(array('message' => __( 'No gift to remove', 'incentiv' )));
        }

        wp_send_json_success(array(
            'message' => __( 'Gift removed', 'incentiv' ),
            'removed' => $removed,
        ));
    }

    /**
     * Display gift promo notices on frontend (global notification banners)
     */
    public function display_gift_promo_notices() {
        if (is_admin()) {
            return;
        }

        $notices = $this->get_active_gift_promo_notices();

        if (empty($notices)) {
            return;
        }

        // Get styling
        $styling = array();
        if (class_exists('MMD_Gift_Rules')) {
            $gift_rules = MMD_Gift_Rules::get_instance();
            $styling = $gift_rules->get_styling_options();
        }

        $bg_color_1 = isset($styling['gift_box_bg_color_1']) ? $styling['gift_box_bg_color_1'] : '#7c3aed';
        $bg_color_2 = isset($styling['gift_box_bg_color_2']) ? $styling['gift_box_bg_color_2'] : '#a855f7';
        $text_color = isset($styling['gift_box_text_color']) ? $styling['gift_box_text_color'] : '#ffffff';

        ?>
        <div class="mmd-gift-promo-notices" style="position: fixed; bottom: 20px; left: 20px; right: 20px; z-index: 9999; display: flex; flex-direction: column; gap: 10px; align-items: center; pointer-events: none;">
            <?php foreach ($notices as $notice): ?>
                <div class="mmd-gift-promo-notice" style="
                    background: linear-gradient(135deg, <?php echo esc_attr($bg_color_1); ?> 0%, <?php echo esc_attr($bg_color_2); ?> 100%);
                    color: <?php echo esc_attr($text_color); ?>;
                    padding: 12px 24px;
                    border-radius: 8px;
                    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
                    font-size: 14px;
                    font-weight: 500;
                    text-align: center;
                    max-width: 600px;
                    width: 100%;
                    pointer-events: auto;
                    position: relative;
                    animation: mmdGiftPromoSlideUp 0.5s ease-out;
                ">
                    <button class="mmd-gift-promo-close" style="position: absolute; top: 4px; left: 8px; background: none; border: none; color: <?php echo esc_attr($text_color); ?>; font-size: 18px; cursor: pointer; opacity: 0.7; line-height: 1;" onclick="this.parentElement.style.display='none';">&times;</button>
                    <span style="font-size: 1.2em;">🎁</span>
                    <?php echo wp_kses_post($notice['text']); ?>
                    <?php if (!empty($notice['link'])): ?>
                        <a href="<?php echo esc_url($notice['link']); ?>" style="color: <?php echo esc_attr($text_color); ?>; text-decoration: underline; font-weight: bold; margin-right: 8px;">
                            <?php echo esc_html($notice['link_text']); ?>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <style>
            @keyframes mmdGiftPromoSlideUp {
                from { transform: translateY(20px); opacity: 0; }
                to { transform: translateY(0); opacity: 1; }
            }
            @media (max-width: 768px) {
                .mmd-gift-promo-notices { left: 10px; right: 10px; bottom: 10px; }
                .mmd-gift-promo-notice { font-size: 13px; padding: 10px 18px; }
            }
        </style>
        <?php
    }

    /**
     * Get active gift promo notices to display
     */
    private function get_active_gift_promo_notices() {
        $notices = array();

        // Check dismissal cookie
        if (isset($_COOKIE['mmd_gift_promo_dismissed']) && $_COOKIE['mmd_gift_promo_dismissed'] === '1') {
            return $notices;
        }

        // Get all active gift rules
        if (!class_exists('MMD_Gift_Rules')) {
            return $notices;
        }

        $gift_rules = MMD_Gift_Rules::get_instance();
        $all_rules = $gift_rules->get_rule_sets();

        foreach ($all_rules as $rule) {
            // Skip disabled rules
            if (isset($rule['enabled']) && $rule['enabled'] === 'no') {
                continue;
            }

            // Skip rules without promo notice enabled
            if (!isset($rule['promo_notice_enabled']) || $rule['promo_notice_enabled'] !== 'yes') {
                continue;
            }

            // Check schedule
            $now = current_time('Y-m-d\TH:i');
            $sched_start = isset($rule['schedule_start']) ? $rule['schedule_start'] : '';
            $sched_end = isset($rule['schedule_end']) ? $rule['schedule_end'] : '';
            if (!empty($sched_start) && $now < $sched_start) {
                continue;
            }
            if (!empty($sched_end) && $now > $sched_end) {
                continue;
            }

            // Check page visibility
            $promo_pages = isset($rule['promo_notice_pages']) ? $rule['promo_notice_pages'] : 'all';
            if ($promo_pages !== 'all') {
                $is_shop = function_exists('is_shop') && (is_shop() || is_product_category() || is_product_tag());
                $is_product_page = function_exists('is_product') && is_product();
                $is_cart_page = function_exists('is_cart') && is_cart();
                $is_checkout_page = function_exists('is_checkout') && is_checkout();

                if ($promo_pages === 'shop_only' && !$is_shop) continue;
                if ($promo_pages === 'product_only' && !$is_product_page) continue;
                if ($promo_pages === 'cart_checkout' && !$is_cart_page && !$is_checkout_page) continue;
                if ($promo_pages === 'shop_product' && !$is_shop && !$is_product_page) continue;
            }

            // Build notice text
            $gift_type_text = '';
            if (isset($rule['gift_type'])) {
                if ($rule['gift_type'] === 'free') {
                    $gift_type_text = __( 'Free gift', 'incentiv' );
                } elseif ($rule['gift_type'] === 'discount') {
                    $gift_type_text = ( __( 'A discount of', 'incentiv' ) . ' ' ) . $rule['gift_discount'] . '%';
                } elseif ($rule['gift_type'] === 'fixed_discount') {
                    $gift_type_text = ( __( 'A discount of', 'incentiv' ) . ' ' ) . wc_price($rule['gift_discount']);
                }
            }

            $promo_text = isset($rule['promo_notice_text']) ? $rule['promo_notice_text'] : '';
            if (empty($promo_text)) {
                $promo_text = $rule['name'] . ' - ' . $gift_type_text . '!';
            }

            // Replace placeholders
            $promo_text = str_replace('{gift_type}', $gift_type_text, $promo_text);
            $promo_text = str_replace('{rule_name}', $rule['name'], $promo_text);

            $link = '';
            $link_text = '';
            if (!empty($rule['promo_notice_link'])) {
                $link = $rule['promo_notice_link'];
                $link_text = isset($rule['promo_notice_link_text']) ? $rule['promo_notice_link_text'] : __( 'View products', 'incentiv' );
            }

            $notices[] = array(
                'text' => $promo_text,
                'gift_type' => $gift_type_text,
                'rule_name' => $rule['name'],
                'link' => $link,
                'link_text' => $link_text,
            );
        }

        // Also check product-level gifts that are active
        // Show a generic promo if there are any active product-level gifts
        $product_gifts_promo = get_option('mmd_product_gifts_promo_enabled', 'no');
        if ($product_gifts_promo === 'yes') {
            $promo_text = get_option('mmd_product_gifts_promo_text', __( 'Selected products come with a free gift!', 'incentiv' ));
            $promo_link = get_option('mmd_product_gifts_promo_link', '');
            if (!empty($promo_text)) {
                $notices[] = array(
                    'text' => $promo_text,
                    'gift_type' => __( 'Gift', 'incentiv' ),
                    'rule_name' => '',
                    'link' => $promo_link,
                    'link_text' => __( 'Buy now', 'incentiv' ),
                );
            }
        }

        return $notices;
    }
}

MMD_Product_Gifts_V2::get_instance();
