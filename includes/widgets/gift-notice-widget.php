<?php
/**
 * Elementor Gift Notice Widget
 */

if (!defined('ABSPATH')) {
    exit;
}

class MMD_Gift_Notice_Widget extends \Elementor\Widget_Base {

    /**
     * Get widget name
     */
    public function get_name() {
        return 'mmd_gift_notice';
    }

    /**
     * Get widget title
     */
    public function get_title() {
        return __('Product gift notice', 'incentiv');
    }

    /**
     * Get widget icon
     */
    public function get_icon() {
        return 'eicon-gift-card';
    }

    /**
     * Get widget categories
     */
    public function get_categories() {
        return array('mmd-widgets', 'woocommerce-elements');
    }

    /**
     * Get widget keywords
     */
    public function get_keywords() {
        return array('gift', 'product', 'discount', 'free', __( 'Gift', 'incentiv' ), __( 'Discount', 'incentiv' ));
    }

    /**
     * Register widget controls
     */
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
            'title',
            array(
                'label' => __('Title', 'incentiv'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('🎁 Special gift!', 'incentiv'),
                'placeholder' => __('Enter a title...', 'incentiv'),
            )
        );

        $this->add_control(
            'show_products',
            array(
                'label' => __('Show product names', 'incentiv'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'incentiv'),
                'label_off' => __('No', 'incentiv'),
                'return_value' => 'yes',
                'default' => 'yes',
            )
        );

        $this->add_control(
            'show_images',
            array(
                'label' => __('Show product images', 'incentiv'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'incentiv'),
                'label_off' => __('No', 'incentiv'),
                'return_value' => 'yes',
                'default' => 'no',
                'condition' => array(
                    'show_products' => 'yes',
                ),
            )
        );

        $this->add_control(
            'placement',
            array(
                'label' => __('Display position', 'incentiv'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => 'custom',
                'options' => array(
                    'custom' => __('Custom position', 'incentiv'),
                    'before_add_to_cart' => __('Before the add to cart button', 'incentiv'),
                    'after_add_to_cart' => __('After the add to cart button', 'incentiv'),
                    'before_product_summary' => __('Before the product short description', 'incentiv'),
                ),
            )
        );

        $this->end_controls_section();

        // Style Section
        $this->start_controls_section(
            'style_section',
            array(
                'label' => __('Style', 'incentiv'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_control(
            'background_type',
            array(
                'label' => __('Background type', 'incentiv'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => 'gradient',
                'options' => array(
                    'solid' => __('Solid color', 'incentiv'),
                    'gradient' => __('Gradient', 'incentiv'),
                ),
            )
        );

        $this->add_control(
            'background_color',
            array(
                'label' => __('Background color', 'incentiv'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#7c3aed',
                'condition' => array(
                    'background_type' => 'solid',
                ),
            )
        );

        $this->add_control(
            'gradient_color_1',
            array(
                'label' => __('Gradient color 1', 'incentiv'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#7c3aed',
                'condition' => array(
                    'background_type' => 'gradient',
                ),
            )
        );

        $this->add_control(
            'gradient_color_2',
            array(
                'label' => __('Gradient color 2', 'incentiv'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#a855f7',
                'condition' => array(
                    'background_type' => 'gradient',
                ),
            )
        );

        $this->add_control(
            'text_color',
            array(
                'label' => __('Text color', 'incentiv'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#ffffff',
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            array(
                'name' => 'title_typography',
                'label' => __('Typography - title', 'incentiv'),
                'selector' => '{{WRAPPER}} .mmd-gift-title',
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            array(
                'name' => 'content_typography',
                'label' => __('Typography - content', 'incentiv'),
                'selector' => '{{WRAPPER}} .mmd-gift-content',
            )
        );

        $this->add_control(
            'border_radius',
            array(
                'label' => __('Border radius', 'incentiv'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => array('px'),
                'range' => array(
                    'px' => array(
                        'min' => 0,
                        'max' => 50,
                        'step' => 1,
                    ),
                ),
                'default' => array(
                    'unit' => 'px',
                    'size' => 8,
                ),
            )
        );

        $this->add_control(
            'padding',
            array(
                'label' => __('Padding', 'incentiv'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => array('px', 'em', '%'),
                'default' => array(
                    'top' => '15',
                    'right' => '15',
                    'bottom' => '15',
                    'left' => '15',
                    'unit' => 'px',
                ),
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Box_Shadow::get_type(),
            array(
                'name' => 'box_shadow',
                'label' => __('Shadow', 'incentiv'),
                'selector' => '{{WRAPPER}} .mmd-gift-notice',
            )
        );

        $this->end_controls_section();
    }

    /**
     * Render widget output on the frontend
     */
    protected function render() {
        global $product;

        // Get current product
        if (!$product || !is_object($product)) {
            $product = wc_get_product(get_the_ID());
        }

        if (!$product) {
            if (\Elementor\Plugin::$instance->editor->is_edit_mode()) {
                echo ( '<div style="padding: 20px; background: #f0f0f0; text-align: center;">' . esc_html__( 'A gift notice will be shown here on the product page', 'incentiv' ) . '</div>' );
            }
            return;
        }

        $settings = $this->get_settings_for_display();

        // Check if gift is enabled for this product
        $enabled = get_post_meta($product->get_id(), '_mmd_gift_enabled', true);

        if ($enabled !== '1') {
            if (\Elementor\Plugin::$instance->editor->is_edit_mode()) {
                echo ( '<div style="padding: 20px; background: #f0f0f0; text-align: center;">' . esc_html__( 'This product has no gift set up. Enable gifts on the product edit page.', 'incentiv' ) . '</div>' );
            }
            return;
        }

        // In-stock gifts only, the same as the product-page and cart pickers.
        $gift_products = \MMD_Product_Gifts_V2::get_notice_gift_products($product->get_id());
        $gift_type = get_post_meta($product->get_id(), '_mmd_gift_type', true);
        $gift_discount = get_post_meta($product->get_id(), '_mmd_gift_discount', true);

        if (empty($gift_products)) {
            return;
        }

        // Prepare styles
        $background_style = '';
        if ($settings['background_type'] === 'gradient') {
            $background_style = 'background: linear-gradient(135deg, ' . $settings['gradient_color_1'] . ' 0%, ' . $settings['gradient_color_2'] . ' 100%);';
        } else {
            $background_style = 'background-color: ' . $settings['background_color'] . ';';
        }

        $padding_style = sprintf(
            'padding: %s %s %s %s;',
            $settings['padding']['top'] . $settings['padding']['unit'],
            $settings['padding']['right'] . $settings['padding']['unit'],
            $settings['padding']['bottom'] . $settings['padding']['unit'],
            $settings['padding']['left'] . $settings['padding']['unit']
        );

        $border_radius = 'border-radius: ' . $settings['border_radius']['size'] . 'px;';
        $text_color = 'color: ' . $settings['text_color'] . ';';

        // Prepare gift text
        $gift_text = '';
        if ($gift_type === 'free') {
            $gift_text = __( 'Free gift', 'incentiv' );
        } elseif ($gift_type === 'discount') {
            $gift_text = ( __( 'A discount of', 'incentiv' ) . ' ' ) . $gift_discount . '%';
        } elseif ($gift_type === 'fixed_discount') {
            $gift_text = ( __( 'A discount of', 'incentiv' ) . ' ' ) . wc_price($gift_discount);
        }

        ?>
        <div class="mmd-gift-notice elementor-widget-mmd-gift-notice" style="<?php echo esc_attr($background_style . $padding_style . $border_radius . $text_color); ?>">
            <div class="mmd-gift-title" style="font-size: 18px; font-weight: bold; margin-bottom: 10px;">
                <?php echo esc_html($settings['title']); ?>
            </div>

            <?php if ($settings['show_products'] === 'yes'): ?>
                <div class="mmd-gift-content" style="font-size: 14px;">
                    <p><?php esc_html_e( 'Buy this product and get', 'incentiv' ); ?> <?php echo esc_html($gift_text); ?> <?php esc_html_e( 'on:', 'incentiv' ); ?></p>

                    <?php if ($settings['show_images'] === 'yes'): ?>
                        <div class="mmd-gift-products-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(100px, 1fr)); gap: 10px; margin-top: 10px;">
                            <?php foreach ($gift_products as $gift_product_id):
                                $gift_product = wc_get_product($gift_product_id);
                                if ($gift_product):
                            ?>
                                <div class="mmd-gift-product-item" style="text-align: center;">
                                    <?php echo wp_kses_post( $gift_product->get_image('thumbnail') ); ?>
                                    <div style="font-size: 12px; margin-top: 5px;"><?php echo esc_html($gift_product->get_name()); ?></div>
                                </div>
                            <?php
                                endif;
                            endforeach;
                            ?>
                        </div>
                    <?php else: ?>
                        <ul style="margin: 10px 0; padding-right: 20px;">
                            <?php foreach ($gift_products as $gift_product_id):
                                $gift_product = wc_get_product($gift_product_id);
                                if ($gift_product):
                            ?>
                                <li><strong><?php echo esc_html($gift_product->get_name()); ?></strong></li>
                            <?php
                                endif;
                            endforeach;
                            ?>
                        </ul>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="mmd-gift-content" style="font-size: 14px;">
                    <?php esc_html_e( 'Buy this product and get', 'incentiv' ); ?> <?php echo esc_html($gift_text); ?>!
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render widget output in the editor
     */
    protected function content_template() {
        ?>
        <#
        var backgroundStyle = '';
        if (settings.background_type === 'gradient') {
            backgroundStyle = 'background: linear-gradient(135deg, ' + settings.gradient_color_1 + ' 0%, ' + settings.gradient_color_2 + ' 100%);';
        } else {
            backgroundStyle = 'background-color: ' + settings.background_color + ';';
        }

        var paddingStyle = 'padding: ' + settings.padding.top + settings.padding.unit + ' ' +
                          settings.padding.right + settings.padding.unit + ' ' +
                          settings.padding.bottom + settings.padding.unit + ' ' +
                          settings.padding.left + settings.padding.unit + ';';

        var borderRadius = 'border-radius: ' + settings.border_radius.size + 'px;';
        var textColor = 'color: ' + settings.text_color + ';';
        #>

        <div class="mmd-gift-notice" style="{{{ backgroundStyle + paddingStyle + borderRadius + textColor }}}">
            <div class="mmd-gift-title" style="font-size: 18px; font-weight: bold; margin-bottom: 10px;">
                {{{ settings.title }}}
            </div>
            <div class="mmd-gift-content" style="font-size: 14px;">
                <# if (settings.show_products === 'yes') { #>
                    <p><?php esc_html_e( 'Buy this product and get a free gift on the selected products!', 'incentiv' ); ?></p>
                <# } else { #>
                    <?php esc_html_e( 'Buy this product and get a free gift!', 'incentiv' ); ?>
                <# } #>
            </div>
        </div>
        <?php
    }
}
