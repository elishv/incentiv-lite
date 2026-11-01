<?php
/**
 * Elementor Gift Selection Widget
 * Renders the full interactive gift selection UI on any Elementor page
 * Works on product pages, cart, checkout, and custom Elementor pages
 */

if (!defined('ABSPATH')) {
    exit;
}

class MMD_Gift_Selection_Widget extends \Elementor\Widget_Base {

    public function get_name() {
        return 'mmd_gift_selection';
    }

    public function get_title() {
        return __('Interactive gift selection', 'incentiv');
    }

    public function get_icon() {
        return 'eicon-products';
    }

    public function get_categories() {
        return array('mmd-widgets', 'woocommerce-elements');
    }

    public function get_keywords() {
        return array('gift', 'selection', 'product', 'free', __( 'Gift', 'incentiv' ), __( 'Selection', 'incentiv' ), 'cart', 'checkout');
    }

    protected function register_controls() {
        // Content Section
        $this->start_controls_section(
            'content_section',
            array(
                'label' => __('Content', 'incentiv'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            )
        );

        $this->add_control(
            'display_context',
            array(
                'label' => __('Display context', 'incentiv'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => 'auto',
                'options' => array(
                    'auto' => __('Automatic (detect page)', 'incentiv'),
                    'product' => __('Product page', 'incentiv'),
                    'cart' => __('Cart / Checkout', 'incentiv'),
                ),
                'description' => __('Automatic detects the page type and shows the selection accordingly', 'incentiv'),
            )
        );

        $this->add_control(
            'show_title',
            array(
                'label' => __('Show title', 'incentiv'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'incentiv'),
                'label_off' => __('No', 'incentiv'),
                'return_value' => 'yes',
                'default' => 'yes',
            )
        );

        $this->add_control(
            'custom_title',
            array(
                'label' => __('Custom title', 'incentiv'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => '',
                'placeholder' => __('Leave empty for the default title', 'incentiv'),
                'condition' => array(
                    'show_title' => 'yes',
                ),
            )
        );

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        $context = $settings['display_context'];

        // Auto-detect context
        if ($context === 'auto') {
            if (is_product()) {
                $context = 'product';
            } elseif (function_exists('is_cart') && is_cart()) {
                $context = 'cart';
            } elseif (function_exists('is_checkout') && is_checkout()) {
                $context = 'cart';
            } else {
                // Custom Elementor page - check if we're on a product post
                $post_id = get_the_ID();
                if ($post_id && get_post_type($post_id) === 'product') {
                    $context = 'product';
                } else {
                    $context = 'cart';
                }
            }
        }

        if ($context === 'product') {
            $this->render_product_gift_selection($settings);
        } else {
            $this->render_cart_gift_selection($settings);
        }
    }

    /**
     * Render gift selection for product context
     */
    private function render_product_gift_selection($settings) {
        global $product;

        // Ensure $product is set - critical for Elementor pages
        if (!$product || !is_object($product)) {
            $post_id = get_the_ID();
            if ($post_id) {
                $product = wc_get_product($post_id);
            }
        }

        // Also try from the queried object (works when Elementor overrides the template)
        if (!$product || !is_object($product)) {
            $queried = get_queried_object();
            if ($queried && isset($queried->ID) && get_post_type($queried->ID) === 'product') {
                $product = wc_get_product($queried->ID);
            }
        }

        if (!$product) {
            if (\Elementor\Plugin::$instance->editor->is_edit_mode()) {
                echo ( '<div style="padding: 20px; background: #f0f0f0; text-align: center; border-radius: 8px;">' . esc_html__( 'The gift selection will be shown here on the product page', 'incentiv' ) . '</div>' );
            }
            return;
        }

        // Ensure scripts are loaded
        $this->ensure_gift_scripts_loaded();

        // Use the singleton instance directly
        $gifts_v2 = MMD_Product_Gifts_V2::get_instance();
        $gifts_v2->display_gift_selection();
    }

    /**
     * Render gift selection for cart/checkout context
     */
    private function render_cart_gift_selection($settings) {
        if (\Elementor\Plugin::$instance->editor->is_edit_mode()) {
            echo '<div style="padding: 20px; background: linear-gradient(135deg, #7c3aed 0%, #a855f7 100%); color: white; text-align: center; border-radius: 8px;">';
            echo ( '<div style="font-size: 18px; font-weight: bold; margin-bottom: 8px;">' . esc_html__( '🎁 Gift selection in the cart', 'incentiv' ) . '</div>' );
            echo ( '<div>' . esc_html__( 'The interactive gift selection will be shown here when the customer qualifies for a gift', 'incentiv' ) . '</div>' );
            echo '</div>';
            return;
        }

        if (!function_exists('WC') || !WC()->cart || WC()->cart->is_empty()) {
            return;
        }

        // Ensure scripts are loaded
        $this->ensure_gift_scripts_loaded();

        // Trigger the cart gift selection display
        $gifts_v2 = MMD_Product_Gifts_V2::get_instance();
        $gifts_v2->display_cart_gift_selection();
    }

    /**
     * Ensure gift selection scripts and styles are loaded
     */
    private function ensure_gift_scripts_loaded() {
        if (!wp_script_is('mmd-gift-selection', 'enqueued')) {
            $js_version = filemtime(MMD_PLUGIN_DIR . 'assets/gift-selection.js');
            $css_version = filemtime(MMD_PLUGIN_DIR . 'assets/gift-selection.css');

            wp_enqueue_style('mmd-gift-selection', MMD_PLUGIN_URL . 'assets/gift-selection.css', array(), $css_version);
            wp_enqueue_script('mmd-gift-selection', MMD_PLUGIN_URL . 'assets/gift-selection.js', array('jquery', 'wp-i18n'), $js_version, true);
            wp_set_script_translations('mmd-gift-selection', 'incentiv', MMD_PLUGIN_DIR . 'languages');

            wp_localize_script('mmd-gift-selection', 'mmdGiftData', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('mmd_gift_nonce'),
                'debug' => MMD_Helpers::debug_enabled(),
            ));
        }
    }

    protected function content_template() {
        ?>
        <div style="padding: 20px; background: linear-gradient(135deg, #7c3aed 0%, #a855f7 100%); color: white; border-radius: 8px;">
            <div style="font-size: 18px; font-weight: bold; margin-bottom: 8px;">
                <# if (settings.show_title === 'yes') { #>
                    {{{ settings.custom_title || <?php echo wp_json_encode( __( '🎁 Choose your gift!', 'incentiv' ) ); ?> }}}
                <# } #>
            </div>
            <div style="font-size: 14px; opacity: 0.9;"><?php esc_html_e( 'The interactive gift selection will be shown here', 'incentiv' ); ?></div>
        </div>
        <?php
    }
}
