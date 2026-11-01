<?php
/**
 * Elementor Widget for Product Gifts
 */

if (!defined('ABSPATH')) {
    exit;
}

class MMD_Elementor_Widget {

    public function __construct() {
        add_action('elementor/widgets/register', array($this, 'register_widgets'));
        add_action('elementor/elements/categories_registered', array($this, 'add_elementor_category'));
    }

    /**
     * Add custom Elementor category
     */
    public function add_elementor_category($elements_manager) {
        $elements_manager->add_category(
            'mmd-widgets',
            array(
                'title' => __('Incentiv', 'incentiv'),
                'icon' => 'fa fa-gift',
            )
        );
    }

    /**
     * Register widgets
     */
    public function register_widgets($widgets_manager) {
        require_once MMD_PLUGIN_DIR . 'includes/widgets/gift-notice-widget.php';
        $widgets_manager->register(new \MMD_Gift_Notice_Widget());

        require_once MMD_PLUGIN_DIR . 'includes/widgets/gift-selection-widget.php';
        $widgets_manager->register(new \MMD_Gift_Selection_Widget());

        require_once MMD_PLUGIN_DIR . 'includes/widgets/cart-gift-selection-widget.php';
        $widgets_manager->register(new \MMD_Cart_Gift_Selection_Widget());
    }
}

new MMD_Elementor_Widget();
