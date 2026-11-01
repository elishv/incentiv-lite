<?php
/**
 * Cart Notes Functionality
 *
 * Allows customers to add notes to their cart/orders
 */

if (!defined('ABSPATH')) {
    exit;
}

class MMD_Cart_Notes {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Display cart note field in cart
        add_action('woocommerce_after_cart_table', array($this, 'display_cart_note_field'));

        // Save cart note to session
        add_action('wp_ajax_mmd_save_cart_note', array($this, 'ajax_save_cart_note'));
        add_action('wp_ajax_nopriv_mmd_save_cart_note', array($this, 'ajax_save_cart_note'));

        // Add cart note to order
        add_action('woocommerce_checkout_create_order', array($this, 'save_cart_note_to_order'), 10, 2);

        // Display cart note in order admin
        add_action('woocommerce_admin_order_data_after_billing_address', array($this, 'display_cart_note_in_admin'));

        // Display cart note in order emails
        add_action('woocommerce_email_order_meta', array($this, 'display_cart_note_in_email'), 10, 3);

        // Enqueue scripts
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    /**
     * Display cart note field in cart
     */
    public function display_cart_note_field() {
        $cart_note = WC()->session->get('mmd_cart_note', '');

        // Get styling options
        $styling = array();
        if (class_exists('MMD_Gift_Rules')) {
            $gift_rules = MMD_Gift_Rules::get_instance();
            $styling = $gift_rules->get_styling_options();
        }

        // Extract all styling options with defaults
        $bg_color = isset($styling['cart_note_bg_color']) ? $styling['cart_note_bg_color'] : '#f8f9fa';
        $text_color = isset($styling['cart_note_text_color']) ? $styling['cart_note_text_color'] : '#333333';
        $label_size = isset($styling['cart_note_label_size']) ? $styling['cart_note_label_size'] : '16';
        $input_size = isset($styling['cart_note_input_size']) ? $styling['cart_note_input_size'] : '14';
        $border_color = isset($styling['cart_note_border_color']) ? $styling['cart_note_border_color'] : '#dee2e6';
        $border_width = isset($styling['cart_note_border_width']) ? $styling['cart_note_border_width'] : '1';
        $border_radius = isset($styling['cart_note_border_radius']) ? $styling['cart_note_border_radius'] : '4';
        $padding = isset($styling['cart_note_padding']) ? $styling['cart_note_padding'] : '10';
        $placeholder_color = isset($styling['cart_note_placeholder_color']) ? $styling['cart_note_placeholder_color'] : '#999999';
        $focus_border = isset($styling['cart_note_focus_border']) ? $styling['cart_note_focus_border'] : '#7c3aed';

        ?>
        <style>
            .mmd-cart-note-container #mmd-cart-note::placeholder {
                color: <?php echo esc_attr($placeholder_color); ?>;
            }
            .mmd-cart-note-container #mmd-cart-note:focus {
                border-color: <?php echo esc_attr($focus_border); ?> !important;
                outline: none;
            }
        </style>
        <div class="mmd-cart-note-container" style="margin-top: 20px;">
            <h3 style="font-size: <?php echo esc_attr($label_size); ?>px; color: <?php echo esc_attr($text_color); ?>;"><?php esc_html_e( 'Order note', 'incentiv' ); ?></h3>
            <p class="description" style="font-size: <?php echo esc_attr($input_size); ?>px; color: <?php echo esc_attr($text_color); ?>;"><?php esc_html_e( 'Add a special note to your order (optional)', 'incentiv' ); ?></p>
            <textarea
                id="mmd-cart-note"
                name="mmd_cart_note"
                rows="3"
                placeholder="<?php esc_attr_e( 'For example: please add an extra straw, or: this is a gift, please wrap it nicely...', 'incentiv' ); ?>"
                style="width: 100%; padding: <?php echo esc_attr($padding); ?>px; border: <?php echo esc_attr($border_width); ?>px solid <?php echo esc_attr($border_color); ?>; border-radius: <?php echo esc_attr($border_radius); ?>px; background-color: <?php echo esc_attr($bg_color); ?>; color: <?php echo esc_attr($text_color); ?>; font-size: <?php echo esc_attr($input_size); ?>px;"
            ><?php echo esc_textarea($cart_note); ?></textarea>
        </div>
        <?php
    }

    /**
     * Enqueue scripts
     */
    public function enqueue_scripts() {
        if (!is_cart() && !is_checkout()) {
            return;
        }

        wp_enqueue_script('mmd-cart-notes', MMD_PLUGIN_URL . 'assets/cart-notes.js', array('jquery'), '1.0.0', true);
        wp_localize_script('mmd-cart-notes', 'mmdCartNotes', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mmd_cart_notes_nonce'),
        ));
    }

    /**
     * AJAX: Save cart note to session
     */
    public function ajax_save_cart_note() {
        check_ajax_referer('mmd_cart_notes_nonce', 'nonce');

        $cart_note = isset($_POST['cart_note']) ? sanitize_textarea_field($_POST['cart_note']) : '';

        WC()->session->set('mmd_cart_note', $cart_note);

        wp_send_json_success(array('message' => 'Cart note saved'));
    }

    /**
     * Save cart note to order
     */
    public function save_cart_note_to_order($order, $data) {
        $cart_note = WC()->session->get('mmd_cart_note', '');

        if (!empty($cart_note)) {
            $order->update_meta_data('_mmd_cart_note', $cart_note);
        }

        // Clear session
        WC()->session->set('mmd_cart_note', '');
    }

    /**
     * Display cart note in order admin
     */
    public function display_cart_note_in_admin($order) {
        $cart_note = $order->get_meta('_mmd_cart_note');

        if (!empty($cart_note)) {
            echo '<div class="mmd-order-cart-note" style="margin-top: 20px; padding: 15px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px;">';
            echo ( '<h3 style="margin-top: 0;">' . esc_html__( '📝 Customer note', 'incentiv' ) . '</h3>' );
            echo '<p style="margin: 0; white-space: pre-wrap;">' . esc_html($cart_note) . '</p>';
            echo '</div>';
        }
    }

    /**
     * Display cart note in order emails
     */
    public function display_cart_note_in_email($order, $sent_to_admin, $plain_text) {
        $cart_note = $order->get_meta('_mmd_cart_note');

        if (empty($cart_note)) {
            return;
        }

        if ($plain_text) {
            echo "\n" . '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━' . "\n";
            echo esc_html__( '📝 Customer note:', 'incentiv' ) . "\n";
            echo esc_html( wp_strip_all_tags( $cart_note ) ) . "\n";
            echo '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━' . "\n";
        } else {
            echo '<div style="margin: 20px 0; padding: 15px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px;">';
            echo ( '<h3 style="margin-top: 0;">' . esc_html__( '📝 Customer note', 'incentiv' ) . '</h3>' );
            echo '<p style="margin: 0; white-space: pre-wrap;">' . esc_html($cart_note) . '</p>';
            echo '</div>';
        }
    }
}

// Initialize
MMD_Cart_Notes::get_instance();
