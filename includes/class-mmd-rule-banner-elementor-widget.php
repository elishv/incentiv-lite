<?php
/**
 * Elementor widget: MMD Rule Banner
 * Displays the banner of the first discount rule matching the current product.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!did_action('elementor/loaded')) {
    return;
}

class MMD_Rule_Banner_Elementor_Widget extends \Elementor\Widget_Base {

    public function get_name() {
        return 'mmd_rule_banner';
    }

    public function get_title() {
        return __('MMD Rule Banner', 'incentiv');
    }

    public function get_icon() {
        return 'eicon-image';
    }

    public function get_categories() {
        return array('woocommerce-elements', 'general');
    }

    public function get_keywords() {
        return array('mmd', 'discount', 'rule', 'banner', 'sale', 'promotion');
    }

    protected function register_controls() {
        // Content section
        $this->start_controls_section('content_section', array(
            'label' => __('Banner', 'incentiv'),
            'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
        ));

        // Build options for rule selection
        $rule_options = array('' => __('-- First matching rule --', 'incentiv'));
        if (class_exists('MMD_Discount_Rules')) {
            $rule_sets = MMD_Discount_Rules::get_all_rule_sets();
            foreach ($rule_sets as $rule_set) {
                $has_banner = !empty($rule_set['banner_image_id']) || !empty($rule_set['banner_image_url']);
                if (!$has_banner) continue;
                $name = isset($rule_set['name']) ? $rule_set['name'] : (isset($rule_set['id']) ? $rule_set['id'] : '');
                $rule_options[isset($rule_set['id']) ? $rule_set['id'] : ''] = $name;
            }
        }

        $this->add_control('rule_id', array(
            'label' => __('Rule', 'incentiv'),
            'type' => \Elementor\Controls_Manager::SELECT,
            'options' => $rule_options,
            'default' => '',
            'description' => __('Leave blank to show the first rule that matches the current product.', 'incentiv'),
        ));

        $this->add_control('product_id', array(
            'label' => __('Product ID (optional)', 'incentiv'),
            'type' => \Elementor\Controls_Manager::NUMBER,
            'default' => '',
            'description' => __('Override the current product. Leave blank to use the product on the page.', 'incentiv'),
        ));

        $this->add_control('placeholder_notice', array(
            'label' => __('Editor placeholder', 'incentiv'),
            'type' => \Elementor\Controls_Manager::HEADING,
            'separator' => 'before',
        ));

        $this->add_control('show_placeholder_in_editor', array(
            'label' => __('Show placeholder in Elementor editor', 'incentiv'),
            'type' => \Elementor\Controls_Manager::SWITCHER,
            'default' => 'yes',
            'description' => __('Useful when previewing the widget on a page that has no qualifying product.', 'incentiv'),
        ));

        $this->end_controls_section();

        // Style section
        $this->start_controls_section('style_section', array(
            'label' => __('Style', 'incentiv'),
            'tab' => \Elementor\Controls_Manager::TAB_STYLE,
        ));

        $this->add_responsive_control('alignment', array(
            'label' => __('Alignment', 'incentiv'),
            'type' => \Elementor\Controls_Manager::CHOOSE,
            'options' => array(
                'left' => array('title' => __('Left', 'incentiv'), 'icon' => 'eicon-text-align-left'),
                'center' => array('title' => __('Center', 'incentiv'), 'icon' => 'eicon-text-align-center'),
                'right' => array('title' => __('Right', 'incentiv'), 'icon' => 'eicon-text-align-right'),
            ),
            'default' => 'center',
            'selectors' => array(
                '{{WRAPPER}} .mmd-rule-banner' => 'text-align: {{VALUE}};',
            ),
        ));

        $this->add_responsive_control('max_width', array(
            'label' => __('Max Width', 'incentiv'),
            'type' => \Elementor\Controls_Manager::SLIDER,
            'size_units' => array('px', '%'),
            'range' => array(
                'px' => array('min' => 100, 'max' => 1500, 'step' => 10),
                '%' => array('min' => 10, 'max' => 100, 'step' => 1),
            ),
            'default' => array('unit' => '%', 'size' => 100),
            'selectors' => array(
                '{{WRAPPER}} .mmd-rule-banner-image' => 'max-width: {{SIZE}}{{UNIT}};',
            ),
        ));

        $this->add_responsive_control('border_radius', array(
            'label' => __('Border Radius', 'incentiv'),
            'type' => \Elementor\Controls_Manager::DIMENSIONS,
            'size_units' => array('px', '%'),
            'selectors' => array(
                '{{WRAPPER}} .mmd-rule-banner-image' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ),
        ));

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        $product_id = !empty($settings['product_id']) ? intval($settings['product_id']) : 0;
        $rule_id = isset($settings['rule_id']) ? $settings['rule_id'] : '';

        if (class_exists('MMD_Rule_Banner')) {
            $html = MMD_Rule_Banner::instance()->get_banner_html_for_product($product_id, $rule_id);
            if ($html) {
                echo wp_kses_post( $html );
                return;
            }
        }

        // Editor placeholder
        if (\Elementor\Plugin::$instance->editor->is_edit_mode() && !empty($settings['show_placeholder_in_editor']) && $settings['show_placeholder_in_editor'] === 'yes') {
            echo '<div class="mmd-rule-banner-placeholder" style="background:#f0f0f1;border:1px dashed #c3c4c7;padding:30px;text-align:center;color:#646970;">'
                . esc_html__('MMD Rule Banner — no matching rule has a banner for this context. Configure a banner image on a rule, or assign this widget to a product page.', 'incentiv')
                . '</div>';
        }
    }
}
