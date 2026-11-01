<?php
/**
 * Product Add-on Gifts/Discounts
 * Allows adding free gifts or discounted products based on product purchase
 */

if (!defined('ABSPATH')) {
    exit;
}

class MMD_Product_Gifts {

    public function __construct() {
        // Add meta box to product pages
        add_action('add_meta_boxes', array($this, 'add_gift_meta_box'));

        // Save meta box data
        add_action('save_post_product', array($this, 'save_gift_meta_box'), 10, 2);

        // Enqueue admin scripts
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));

        // Frontend hooks
        add_action('woocommerce_add_to_cart', array($this, 'add_gift_to_cart'), 10, 6);
        add_action('woocommerce_before_calculate_totals', array($this, 'apply_gift_pricing'), 20);

        // Display gift notice
        add_action('woocommerce_before_add_to_cart_button', array($this, 'display_gift_notice'));

        // Cart item data
        add_filter('woocommerce_get_item_data', array($this, 'display_gift_in_cart'), 10, 2);

        // Prevent gift removal
        add_filter('woocommerce_cart_item_remove_link', array($this, 'prevent_gift_removal'), 10, 2);

        // Remove gifts when parent removed
        add_action('woocommerce_cart_item_removed', array($this, 'remove_associated_gifts'), 10, 2);
    }

    /**
     * Add meta box to product pages
     */
    public function add_gift_meta_box() {
        add_meta_box(
            'mmd_product_gifts',
            __( '🎁 Gifts/discounts for this product', 'incentiv' ),
            array($this, 'render_gift_meta_box'),
            'product',
            'normal',
            'high'
        );
    }

    /**
     * Render gift meta box
     */
    public function render_gift_meta_box($post) {
        wp_nonce_field('mmd_gift_meta_box', 'mmd_gift_meta_box_nonce');

        $enabled = get_post_meta($post->ID, '_mmd_gift_enabled', true);
        $gift_products = get_post_meta($post->ID, '_mmd_gift_products', true);
        $gift_type = get_post_meta($post->ID, '_mmd_gift_type', true);
        $gift_discount = get_post_meta($post->ID, '_mmd_gift_discount', true);
        $gift_variations = get_post_meta($post->ID, '_mmd_gift_variations', true);
        $limit_variations = get_post_meta($post->ID, '_mmd_gift_limit_variations', true);
        $allowed_variations = get_post_meta($post->ID, '_mmd_gift_allowed_variations', true);

        $gift_products = !empty($gift_products) ? $gift_products : array();
        $gift_type = !empty($gift_type) ? $gift_type : 'free';
        $gift_discount = !empty($gift_discount) ? $gift_discount : 0;
        $gift_variations = !empty($gift_variations) ? $gift_variations : array();
        $allowed_variations = !empty($allowed_variations) ? $allowed_variations : array();

        ?>
        <div class="mmd-gift-settings">
            <style>
                .mmd-gift-settings { padding: 15px; }
                .mmd-gift-field { margin-bottom: 20px; }
                .mmd-gift-field label { display: block; font-weight: bold; margin-bottom: 8px; }
                .mmd-gift-field input[type="text"],
                .mmd-gift-field select { width: 100%; max-width: 500px; }
                .mmd-gift-products-list { border: 1px solid #ddd; padding: 10px; max-height: 200px; overflow-y: auto; background: #f9f9f9; }
                .mmd-gift-product-item { padding: 8px; margin: 5px 0; background: white; border: 1px solid #ddd; border-radius: 3px; }
                .mmd-switch { position: relative; display: inline-block; width: 50px; height: 24px; }
                .mmd-switch input { opacity: 0; width: 0; height: 0; }
                .mmd-switch-slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .4s; border-radius: 24px; }
                .mmd-switch-slider:before { position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px; background-color: white; transition: .4s; border-radius: 50%; }
                .mmd-switch input:checked + .mmd-switch-slider { background-color: #2196F3; }
                .mmd-switch input:checked + .mmd-switch-slider:before { transform: translateX(26px); }
                .mmd-variation-selector { margin-top: 15px; padding: 15px; background: #f0f0f1; border-radius: 5px; }
                .mmd-variation-item { margin: 8px 0; }
            </style>

            <div class="mmd-gift-field">
                <label>
                    <label class="mmd-switch">
                        <input type="checkbox" name="mmd_gift_enabled" value="1" <?php checked($enabled, '1'); ?>>
                        <span class="mmd-switch-slider"></span>
                    </label>
                    <?php echo esc_html_x( 'Enable gifts/discounts for this product', 'classic', 'incentiv' ); ?>
                </label>
                <p class="description"><?php esc_html_e( 'When a customer buys this product, the products selected below are added automatically', 'incentiv' ); ?></p>
            </div>

            <div class="mmd-gift-field">
                <label for="mmd_gift_type"><?php esc_html_e( 'Benefit type', 'incentiv' ); ?></label>
                <select name="mmd_gift_type" id="mmd_gift_type">
                    <option value="free" <?php selected($gift_type, 'free'); ?>><?php esc_html_e( 'Free product (gift)', 'incentiv' ); ?></option>
                    <option value="discount" <?php selected($gift_type, 'discount'); ?>><?php esc_html_e( 'Percentage discount', 'incentiv' ); ?></option>
                    <option value="fixed_discount" <?php selected($gift_type, 'fixed_discount'); ?>><?php esc_html_e( 'Fixed amount discount', 'incentiv' ); ?></option>
                </select>
            </div>

            <div class="mmd-gift-field" id="mmd_discount_field" style="display: <?php echo ($gift_type !== 'free') ? 'block' : 'none'; ?>;">
                <label for="mmd_gift_discount"><?php echo esc_html_x( 'Discount rate', 'classic', 'incentiv' ); ?></label>
                <input type="number" name="mmd_gift_discount" id="mmd_gift_discount" value="<?php echo esc_attr($gift_discount); ?>" min="0" step="0.01">
                <p class="description"><?php esc_html_e( 'Discount percentage (for example: 50 for 50%) or a fixed amount in shekels', 'incentiv' ); ?></p>
            </div>

            <div class="mmd-gift-field">
                <label for="mmd_gift_products"><?php esc_html_e( 'Choose products for the benefit', 'incentiv' ); ?></label>
                <select name="mmd_gift_products[]" id="mmd_gift_products" class="mmd-product-search" multiple style="width: 100%; max-width: 500px;">
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
                <p class="description"><?php esc_html_e( 'Type to search for products. You can choose several products.', 'incentiv' ); ?></p>
            </div>

            <div class="mmd-gift-field">
                <label>
                    <input type="checkbox" name="mmd_gift_limit_variations" value="1" <?php checked($limit_variations, '1'); ?> id="mmd_limit_variations">
                    <?php esc_html_e( 'Limit to specific variations of the gift products', 'incentiv' ); ?>
                </label>
            </div>

            <div class="mmd-variation-selector" id="mmd_variations_container" style="display: <?php echo ($limit_variations == '1') ? 'block' : 'none'; ?>;">
                <h4><?php esc_html_e( 'Choose allowed variations', 'incentiv' ); ?></h4>
                <div id="mmd_variations_list">
                    <p class="description"><?php esc_html_e( 'Select a variable product above to show its variations', 'incentiv' ); ?></p>
                </div>
            </div>

            <script>
            jQuery(document).ready(function($) {
                // Toggle discount field
                $('#mmd_gift_type').on('change', function() {
                    if ($(this).val() === 'free') {
                        $('#mmd_discount_field').hide();
                    } else {
                        $('#mmd_discount_field').show();
                    }
                });

                // Toggle variations selector
                $('#mmd_limit_variations').on('change', function() {
                    if ($(this).is(':checked')) {
                        $('#mmd_variations_container').show();
                        loadVariations();
                    } else {
                        $('#mmd_variations_container').hide();
                    }
                });

                // Load variations when products change
                $('#mmd_gift_products').on('change', function() {
                    if ($('#mmd_limit_variations').is(':checked')) {
                        loadVariations();
                    }
                });

                function loadVariations() {
                    var productIds = $('#mmd_gift_products').val();
                    if (!productIds || productIds.length === 0) {
                        $('#mmd_variations_list').html(( '<p class="description">' + <?php echo wp_json_encode( __( 'Select a variable product above to show its variations', 'incentiv' ) ); ?> + '<\/p>' ));
                        return;
                    }

                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'mmd_get_product_variations',
                            product_ids: productIds,
                            nonce: '<?php echo esc_attr( wp_create_nonce('mmd_variations_nonce') ); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                $('#mmd_variations_list').html(response.data.html);
                            }
                        }
                    });
                }

                // Initialize if variations are already limited
                <?php if ($limit_variations == '1'): ?>
                loadVariations();
                <?php endif; ?>
            });
            </script>
        </div>
        <?php
    }

    /**
     * Save meta box data
     */
    public function save_gift_meta_box($post_id, $post) {
        // Security checks
        if (!isset($_POST['mmd_gift_meta_box_nonce']) || !wp_verify_nonce($_POST['mmd_gift_meta_box_nonce'], 'mmd_gift_meta_box')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Save enabled status
        $enabled = isset($_POST['mmd_gift_enabled']) ? '1' : '0';
        update_post_meta($post_id, '_mmd_gift_enabled', $enabled);

        // Save gift type
        $gift_type = isset($_POST['mmd_gift_type']) ? sanitize_text_field($_POST['mmd_gift_type']) : 'free';
        update_post_meta($post_id, '_mmd_gift_type', $gift_type);

        // Save discount amount
        $gift_discount = isset($_POST['mmd_gift_discount']) ? floatval($_POST['mmd_gift_discount']) : 0;
        update_post_meta($post_id, '_mmd_gift_discount', $gift_discount);

        // Save gift products
        $gift_products = isset($_POST['mmd_gift_products']) && is_array($_POST['mmd_gift_products'])
            ? array_map('intval', $_POST['mmd_gift_products'])
            : array();
        update_post_meta($post_id, '_mmd_gift_products', $gift_products);

        // Save variation limitation
        $limit_variations = isset($_POST['mmd_gift_limit_variations']) ? '1' : '0';
        update_post_meta($post_id, '_mmd_gift_limit_variations', $limit_variations);

        // Save allowed variations
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
                    placeholder: " . wp_json_encode( __( 'Search products...', 'incentiv' ) ) . "
                });
            });
        ");
    }

    /**
     * Add gift to cart when product is purchased
     */
    public function add_gift_to_cart($cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data) {
        $enabled = get_post_meta($product_id, '_mmd_gift_enabled', true);

        if ($enabled !== '1') {
            return;
        }

        $gift_products = get_post_meta($product_id, '_mmd_gift_products', true);
        $gift_type = get_post_meta($product_id, '_mmd_gift_type', true);
        $limit_variations = get_post_meta($product_id, '_mmd_gift_limit_variations', true);
        $allowed_variations = get_post_meta($product_id, '_mmd_gift_allowed_variations', true);

        if (empty($gift_products)) {
            return;
        }

        foreach ($gift_products as $gift_product_id) {
            $gift_product = wc_get_product($gift_product_id);

            if (!$gift_product) {
                continue;
            }

            // Handle variable products
            if ($gift_product->is_type('variable')) {
                if ($limit_variations == '1' && !empty($allowed_variations)) {
                    // Add only allowed variations
                    foreach ($allowed_variations as $variation_id) {
                        if (!$this->is_gift_in_cart($variation_id, $cart_item_key)) {
                            $this->add_single_gift_to_cart($variation_id, $quantity, $cart_item_key, $gift_type);
                        }
                    }
                } else {
                    // Add default variation or let user choose
                    $available_variations = $gift_product->get_available_variations();
                    if (!empty($available_variations)) {
                        $first_variation_id = $available_variations[0]['variation_id'];
                        if (!$this->is_gift_in_cart($first_variation_id, $cart_item_key)) {
                            $this->add_single_gift_to_cart($first_variation_id, $quantity, $cart_item_key, $gift_type);
                        }
                    }
                }
            } else {
                // Simple product
                if (!$this->is_gift_in_cart($gift_product_id, $cart_item_key)) {
                    $this->add_single_gift_to_cart($gift_product_id, $quantity, $cart_item_key, $gift_type);
                }
            }
        }
    }

    /**
     * Check if gift is already in cart
     */
    private function is_gift_in_cart($product_id, $parent_cart_key) {
        foreach (WC()->cart->get_cart() as $cart_item) {
            if (isset($cart_item['mmd_is_gift']) &&
                $cart_item['mmd_parent_key'] === $parent_cart_key &&
                ($cart_item['product_id'] === $product_id || $cart_item['variation_id'] === $product_id)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Add single gift to cart
     */
    private function add_single_gift_to_cart($product_id, $quantity, $parent_cart_key, $gift_type) {
        $product = wc_get_product($product_id);

        if (!$product) {
            return;
        }

        $cart_item_data = array(
            'mmd_is_gift' => true,
            'mmd_parent_key' => $parent_cart_key,
            'mmd_gift_type' => $gift_type
        );

        if ($product->is_type('variation')) {
            $variation = wc_get_product($product_id);
            WC()->cart->add_to_cart(
                $variation->get_parent_id(),
                $quantity,
                $product_id,
                $variation->get_variation_attributes(),
                $cart_item_data
            );
        } else {
            WC()->cart->add_to_cart($product_id, $quantity, 0, array(), $cart_item_data);
        }
    }

    /**
     * Apply gift pricing
     */
    public function apply_gift_pricing($cart) {
        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }

        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            if (isset($cart_item['mmd_is_gift']) && $cart_item['mmd_is_gift']) {
                $gift_type = $cart_item['mmd_gift_type'];
                $original_price = $cart_item['data']->get_price();

                // Get parent product to fetch discount settings
                $parent_key = $cart_item['mmd_parent_key'];
                $parent_item = $cart->get_cart_item($parent_key);

                if ($parent_item) {
                    $parent_product_id = $parent_item['product_id'];
                    $gift_discount = get_post_meta($parent_product_id, '_mmd_gift_discount', true);

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
    }

    /**
     * Display gift notice on product page
     */
    public function display_gift_notice() {
        global $product;

        if (!$product) {
            return;
        }

        $enabled = get_post_meta($product->get_id(), '_mmd_gift_enabled', true);

        if ($enabled !== '1') {
            return;
        }

        $gift_products = get_post_meta($product->get_id(), '_mmd_gift_products', true);
        $gift_type = get_post_meta($product->get_id(), '_mmd_gift_type', true);
        $gift_discount = get_post_meta($product->get_id(), '_mmd_gift_discount', true);

        if (empty($gift_products)) {
            return;
        }

        $gift_text = '';
        if ($gift_type === 'free') {
            $gift_text = __( 'Free gift', 'incentiv' );
        } elseif ($gift_type === 'discount') {
            $gift_text = ( __( 'A discount of', 'incentiv' ) . ' ' ) . $gift_discount . '%';
        } elseif ($gift_type === 'fixed_discount') {
            $gift_text = ( __( 'A discount of', 'incentiv' ) . ' ' ) . wc_price($gift_discount);
        }

        $product_names = array();
        foreach ($gift_products as $gift_product_id) {
            $gift_product = wc_get_product($gift_product_id);
            if ($gift_product) {
                $product_names[] = $gift_product->get_name();
            }
        }

        if (!empty($product_names)) {
            echo '<div class="mmd-gift-notice" style="background: linear-gradient(135deg, #7c3aed 0%, #a855f7 100%); color: white; padding: 15px; margin: 15px 0; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">';
            echo ( '<div style="font-size: 18px; font-weight: bold; margin-bottom: 8px;">' . esc_html__( '🎁 A special gift!', 'incentiv' ) . '</div>' );
            echo ( '<div style="font-size: 14px;">' . esc_html__( 'Buy this product and get', 'incentiv' ) . ' ' ) . esc_html($gift_text) . ( ' ' . esc_html__( 'on:', 'incentiv' ) . ' <strong>' ) . esc_html(implode(', ', $product_names)) . '</strong></div>';
            echo '</div>';
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
            } elseif ($gift_type === 'discount') {
                $label = __( '🎁 Discounted product', 'incentiv' );
            } elseif ($gift_type === 'fixed_discount') {
                $label = __( '🎁 Discounted product', 'incentiv' );
            }

            $item_data[] = array(
                'key' => '',
                'value' => '<span style="color: #7c3aed; font-weight: bold;">' . $label . '</span>'
            );
        }

        return $item_data;
    }

    /**
     * Prevent manual removal of gift items
     */
    public function prevent_gift_removal($link, $cart_item_key) {
        $cart_item = WC()->cart->get_cart_item($cart_item_key);

        if (isset($cart_item['mmd_is_gift']) && $cart_item['mmd_is_gift']) {
            return ( '<span class="mmd-gift-locked" title="' . esc_attr__( 'This product is a gift and cannot be removed manually', 'incentiv' ) . '">🔒</span>' );
        }

        return $link;
    }

    /**
     * Remove associated gifts when parent product is removed
     */
    public function remove_associated_gifts($cart_item_key, $cart) {
        foreach ($cart->get_cart() as $key => $cart_item) {
            if (isset($cart_item['mmd_is_gift']) &&
                $cart_item['mmd_parent_key'] === $cart_item_key) {
                $cart->remove_cart_item($key);
            }
        }
    }
}

// Initialize if in admin or frontend
if (is_admin()) {
    new MMD_Product_Gifts();
} else {
    add_action('init', function() {
        new MMD_Product_Gifts();
    });
}
