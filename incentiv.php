<?php
/**
 * Plugin Name: Incentiv – Advanced Promotions for WooCommerce
 * Plugin URI: https://a-in.co.il/plugins/incentiv/
 * Description: Incentiv is a promotions engine for WooCommerce: quantity and mix & match discounts, automatic gifts and BOGO, margin guards and a visual flow builder. Every reason to buy more.
 * Version: 2.2.0
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
define('INCV_VERSION', '2.2.0');
define('INCV_PLUGIN_FILE', __FILE__);
define('INCV_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('INCV_PLUGIN_URL', plugin_dir_url(__FILE__));
define('INCV_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Declare HPOS compatibility. Orders are only read through wc_get_orders().
add_action( 'before_woocommerce_init', function() {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
} );

// One-time copy of data stored under the previous prefix (see the class).
require_once INCV_PLUGIN_DIR . 'includes/class-incv-migration.php';
INCV_Migration::init();

if (!defined('INCV_DEBUG')) {
    define('INCV_DEBUG', false);
}

/**
 * Main Plugin Class
 */
class INCV_Plugin {
    
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
        echo '<div class="error"><p><strong>' . esc_html__('Incentiv', 'incentiv') . '</strong> ' . esc_html__('Requires the WooCommerce plugin to be installed and active.', 'incentiv') . '</p></div>';
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        // Load text domain
        load_plugin_textdomain('incentiv', false, dirname(INCV_PLUGIN_BASENAME) . '/languages'); // phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound -- Loads the bundled translations shipped in /languages until WordPress.org language packs exist.
        
        // Include required files
        $this->includes();
        
        // Initialize components
        if (is_admin()) {
            new INCV_Admin();
        }
        new INCV_Frontend();
        INCV_Rule_Banner::instance();
        INCV_Analytics::instance();

        // Add-on modules (Incentiv Pro and others) register here.
        INCV_Modules::instance()->boot();

        // Add settings link
        add_filter('plugin_action_links_' . INCV_PLUGIN_BASENAME, array($this, 'add_settings_link'));
    }
    
    /**
     * Include required files
     */
    private function includes() {
        require_once INCV_PLUGIN_DIR . 'includes/class-incv-helpers.php';
        require_once INCV_PLUGIN_DIR . 'includes/class-incv-modules.php';
        require_once INCV_PLUGIN_DIR . 'includes/class-incv-margin-guards.php';
        require_once INCV_PLUGIN_DIR . 'includes/class-incv-discount-allocator.php';
        require_once INCV_PLUGIN_DIR . 'includes/class-incv-discount-rules.php';
        require_once INCV_PLUGIN_DIR . 'includes/class-incv-gift-rules.php';
        require_once INCV_PLUGIN_DIR . 'includes/class-incv-cart-notes.php';
        require_once INCV_PLUGIN_DIR . 'includes/class-incv-frontend.php';
        require_once INCV_PLUGIN_DIR . 'includes/class-incv-product-gifts-v2.php';
        require_once INCV_PLUGIN_DIR . 'includes/class-incv-ajax.php';
        require_once INCV_PLUGIN_DIR . 'includes/class-incv-rule-banner.php';
        require_once INCV_PLUGIN_DIR . 'includes/class-incv-analytics.php';

        if (is_admin()) {
            require_once INCV_PLUGIN_DIR . 'includes/class-incv-flow-builder.php';
        }

        if (is_admin()) {
            require_once INCV_PLUGIN_DIR . 'includes/class-incv-admin.php';
            require_once INCV_PLUGIN_DIR . 'includes/class-incv-example-rules.php';
            require_once INCV_PLUGIN_DIR . 'includes/class-incv-rule-diagnostic.php';
        }

        // Load Elementor widget if Elementor is active
        if (did_action('elementor/loaded')) {
            require_once INCV_PLUGIN_DIR . 'includes/class-incv-elementor-widget.php';
        }

    }
    
    /**
     * Add settings link to plugins page
     */
    public function add_settings_link($links) {
        $settings_link = '<a href="' . admin_url('admin.php?page=incv-discount-rules') . '">' . __('Settings', 'incentiv') . '</a>';
        array_unshift($links, $settings_link);

        // Plugin-row link to the paid add-on, only while it is not active.
        if (!defined('INCENTIV_PRO_VERSION')) {
            $links[] = '<a href="' . esc_url('https://a-in.co.il/plugins/incentiv/#pro') . '" target="_blank" rel="noopener">' . esc_html__('Upgrade to Pro', 'incentiv') . '</a>';
        }
        return $links;
    }
}

// Initialize the plugin
function INCV() {
    return INCV_Plugin::instance();
}

// Start the plugin
add_action('plugins_loaded', 'INCV');

/**
 * Activation Hook
 */
register_activation_hook(__FILE__, 'incv_activate_plugin');

function incv_activate_plugin() {
    // Keep the settings of stores that ran an earlier Incentiv build.
    INCV_Migration::run();

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

    add_option('incv_settings', $default_options);
}

/**
 * Deactivation Hook
 */
register_deactivation_hook(__FILE__, 'incv_deactivate_plugin');

function incv_deactivate_plugin() {
    // Clear any transients or temporary data
    delete_transient('incv_discount_cache');
}
