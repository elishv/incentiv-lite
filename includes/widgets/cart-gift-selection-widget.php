<?php
/**
 * Elementor Cart Gift Selection Widget
 * Renders interactive gift selection on cart/checkout and custom Elementor pages
 */

if (!defined('ABSPATH')) {
    exit;
}

class MMD_Cart_Gift_Selection_Widget extends \Elementor\Widget_Base {

    public function get_name() {
        return 'mmd_cart_gift_selection';
    }

    public function get_title() {
        return __('Cart gift selection', 'incentiv');
    }

    public function get_icon() {
        return 'eicon-cart';
    }

    public function get_categories() {
        return array('mmd-widgets', 'woocommerce-elements');
    }

    public function get_keywords() {
        return array('gift', 'cart', 'checkout', 'selection', __( 'Gift', 'incentiv' ), __( 'Cart', 'incentiv' ));
    }

    protected function register_controls() {
        $this->start_controls_section(
            'content_section',
            array(
                'label' => __('Settings', 'incentiv'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            )
        );

        $this->add_control(
            'info_note',
            array(
                'type' => \Elementor\Controls_Manager::RAW_HTML,
                'raw' => '<div style="padding: 10px; background: #e8f5e9; border-radius: 4px; font-size: 13px;">' .
                         ( esc_html__( 'This widget automatically shows the interactive gift selection when the customer qualifies for a gift under the rules you set.', 'incentiv' ) . '<br><br>' ) .
                         __( 'You can place this widget on any page: cart, checkout or a custom page.', 'incentiv' ) .
                         '</div>',
                'content_classes' => 'elementor-panel-alert',
            )
        );

        $this->end_controls_section();
    }

    protected function render() {
        if (\Elementor\Plugin::$instance->editor->is_edit_mode()) {
            echo '<div style="padding: 20px; background: linear-gradient(135deg, #7c3aed 0%, #a855f7 100%); color: white; text-align: center; border-radius: 8px;">';
            echo ( '<div style="font-size: 18px; font-weight: bold; margin-bottom: 8px;">' . esc_html__( '🎁 Gift selection in the cart', 'incentiv' ) . '</div>' );
            echo ( '<div style="font-size: 14px; opacity: 0.9;">' . esc_html__( 'When the customer qualifies for a gift, an interactive product selector is shown here', 'incentiv' ) . '</div>' );
            echo '<div style="margin-top: 15px; background: white; border-radius: 6px; padding: 15px; color: #333;">';
            echo '<table style="width: 100%; text-align: start;"><tr>';
            echo '<td style="padding: 8px;">📷</td>';
            echo ( '<td style="padding: 8px;">' . esc_html__( 'Sample product', 'incentiv' ) . '</td>' );
            echo ( '<td style="padding: 8px;"><button style="background: #7c3aed; color: white; border: none; padding: 6px 14px; border-radius: 4px;">' . esc_html__( 'Add to cart', 'incentiv' ) . '</button></td>' );
            echo '</tr></table>';
            echo '</div>';
            echo '</div>';
            return;
        }

        // Trigger the actual cart gift selection
        do_action('mmd_render_cart_gift_selection');
    }

    protected function content_template() {
        ?>
        <div style="padding: 20px; background: linear-gradient(135deg, #7c3aed 0%, #a855f7 100%); color: white; text-align: center; border-radius: 8px;">
            <div style="font-size: 18px; font-weight: bold; margin-bottom: 8px;"><?php esc_html_e( '🎁 Gift selection in the cart', 'incentiv' ); ?></div>
            <div style="font-size: 14px; opacity: 0.9;"><?php esc_html_e( 'When the customer qualifies for a gift, an interactive product selector is shown here', 'incentiv' ); ?></div>
        </div>
        <?php
    }
}
