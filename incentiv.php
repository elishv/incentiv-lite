<?php
/**
 * Plugin Name: Incentiv – Advanced Promotions for WooCommerce
 * Plugin URI: https://a-in.co.il/plugins/incentiv/
 * Description: Incentiv is a promotions engine for WooCommerce: quantity and mix & match discounts, automatic gifts and BOGO, margin guards and a visual flow builder. Every reason to buy more.
 * Version: 2.1.0
 * Author: Eli Shvarzman
 * Author URI: https://a-in.co.il/
 * Text Domain: incentiv
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * WC requires at least: 4.0
 * WC tested up to: 11.2
 * Requires Plugins: woocommerce
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('MMD_VERSION', '2.1.0');
define('MMD_PLUGIN_FILE', __FILE__);
define('MMD_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MMD_PLUGIN_URL', plugin_dir_url(__FILE__));
define('MMD_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Declare HPOS compatibility. Orders are only read through wc_get_orders().
add_action( 'before_woocommerce_init', function() {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
} );

if (!defined('MMD_DEBUG')) {
    define('MMD_DEBUG', false);
}

/**
 * Main Plugin Class
 */
class Mix_Match_Discounts {
    
    private static $instance = null;
    
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function __construct() {
        // Check if WooCommerce is active
        if (!$this->is_woocommerce_active()) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return;
        }

        // Initialize plugin immediately
        $this->init();
    }
    
    /**
     * Check if WooCommerce is active
     */
    private function is_woocommerce_active() {
        return class_exists('WooCommerce');
    }
    
    /**
     * WooCommerce missing notice
     */
    public function woocommerce_missing_notice() {
        echo '<div class="error"><p><strong>' . esc_html__('Mix & Match Quantity Discounts', 'incentiv') . '</strong> ' . esc_html__('Requires the WooCommerce plugin to be installed and active.', 'incentiv') . '</p></div>';
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        // Load text domain
        load_plugin_textdomain('incentiv', false, dirname(MMD_PLUGIN_BASENAME) . '/languages');
        
        // Include required files
        $this->includes();
        
        // Initialize components
        if (is_admin()) {
            new MMD_Admin();
        }
        new MMD_Frontend();
        MMD_Rule_Banner::instance();
        MMD_Analytics::instance();

        // Add-on modules (Incentiv Pro and others) register here.
        MMD_Modules::instance()->boot();

        // Add settings link
        add_filter('plugin_action_links_' . MMD_PLUGIN_BASENAME, array($this, 'add_settings_link'));
    }
    
    /**
     * Include required files
     */
    private function includes() {
        require_once MMD_PLUGIN_DIR . 'includes/class-mmd-helpers.php';
        require_once MMD_PLUGIN_DIR . 'includes/class-mmd-modules.php';
        require_once MMD_PLUGIN_DIR . 'includes/class-mmd-margin-guards.php';
        require_once MMD_PLUGIN_DIR . 'includes/class-mmd-discount-allocator.php';
        require_once MMD_PLUGIN_DIR . 'includes/class-mmd-discount-rules.php';
        require_once MMD_PLUGIN_DIR . 'includes/class-mmd-gift-rules.php';
        require_once MMD_PLUGIN_DIR . 'includes/class-mmd-cart-notes.php';
        require_once MMD_PLUGIN_DIR . 'includes/class-mmd-frontend.php';
        require_once MMD_PLUGIN_DIR . 'includes/class-mmd-product-gifts-v2.php';
        require_once MMD_PLUGIN_DIR . 'includes/class-mmd-ajax.php';
        require_once MMD_PLUGIN_DIR . 'includes/class-mmd-rule-banner.php';
        require_once MMD_PLUGIN_DIR . 'includes/class-mmd-analytics.php';

        if (is_admin()) {
            require_once MMD_PLUGIN_DIR . 'includes/class-mmd-flow-builder.php';
        }

        if (is_admin()) {
            require_once MMD_PLUGIN_DIR . 'includes/class-mmd-admin.php';
            require_once MMD_PLUGIN_DIR . 'includes/class-mmd-example-rules.php';
            require_once MMD_PLUGIN_DIR . 'includes/class-mmd-rule-diagnostic.php';
        }

        // Load Elementor widget if Elementor is active
        if (did_action('elementor/loaded')) {
            require_once MMD_PLUGIN_DIR . 'includes/class-mmd-elementor-widget.php';
        }

    }
    
    /**
     * Add settings link to plugins page
     */
    public function add_settings_link($links) {
        $settings_link = '<a href="' . admin_url('admin.php?page=mix-match-discounts') . '">' . __('Settings', 'incentiv') . '</a>';
        array_unshift($links, $settings_link);

        // Plugin-row link to the paid add-on, only while it is not active.
        if (!defined('INCENTIV_PRO_VERSION')) {
            $links[] = '<a href="' . esc_url('https://a-in.co.il/plugins/incentiv/#pro') . '" target="_blank" rel="noopener">' . esc_html__('Upgrade to Pro', 'incentiv') . '</a>';
        }
        return $links;
    }
}

// Initialize the plugin
function MMD() {
    return Mix_Match_Discounts::instance();
}

// Start the plugin
add_action('plugins_loaded', 'MMD');

/**
 * Activation Hook
 */
register_activation_hook(__FILE__, 'mmd_activate_plugin');

function mmd_activate_plugin() {
    // Flush rewrite rules for Special Events URLs
    flush_rewrite_rules();

    // Set default options
    $default_options = array(
        'enabled' => 'yes',
        'selection_method' => 'category',
        'included_categories' => array(),
        'excluded_categories' => array(),
        'included_products' => array(),
        'excluded_products' => array(),
        'attribute_slug' => '',
        'attribute_values' => array(),
        'attribute_excluded_values' => array(),
        'discount_rules' => array(
            array('quantity' => 3, 'discount' => 30, 'type' => 'percentage'),
            array('quantity' => 4, 'discount' => 50, 'type' => 'percentage'),
            array('quantity' => 5, 'discount' => 100, 'type' => 'percentage'),
        ),
        'apply_to' => 'cheapest',
        'notice_enabled' => 'yes',
        'notice_title' => __( '☕ Buy more and save!', 'incentiv' ),
        'notice_content' => '',
        'notice_color' => '#a16a3f',
        'discount_label' => __( '☕ Quantity discount', 'incentiv' ),
        'show_in_mini_cart' => 'yes',
        'debug_mode' => 'no',
    );

    add_option('mmd_settings', $default_options);
}

/**
 * Deactivation Hook
 */
register_deactivation_hook(__FILE__, 'mmd_deactivate_plugin');

function mmd_deactivate_plugin() {
    // Clear any transients or temporary data
    delete_transient('mmd_discount_cache');
}
