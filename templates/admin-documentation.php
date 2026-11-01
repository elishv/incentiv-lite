<?php
/**
 * Plugin Documentation Page - English & Hebrew
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap mmd-admin-wrap">
    <?php include dirname(__FILE__) . '/admin-nav-tabs.php'; ?>

    <div style="max-width:1000px;margin:0 auto;">

    <!-- Getting-started hero -->
    <div class="mmd-docs-hero">
        <span class="mmd-docs-hero-icon"><span class="msr">rocket_launch</span></span>
        <div>
            <h2><?php esc_html_e( 'Let\'s get started', 'incentiv' ); ?></h2>
            <div class="mmd-docs-hero-sub"><?php echo wp_kses_post( __( '4 steps to your first quantity discount &middot; Get started in minutes', 'incentiv' ) ); ?></div>
        </div>
    </div>

    <?php $mmd_has_rules = class_exists('MMD_Discount_Rules') && count((array) MMD_Discount_Rules::get_all_rule_sets()) > 0; ?>
    <div class="mmd-steps-grid">
        <div class="mmd-step-card">
            <span class="mmd-step-num-circle done">✓</span>
            <div>
                <div class="mmd-step-title"><?php echo wp_kses_post( __( 'Install &amp; activate', 'incentiv' ) ); ?></div>
                <div class="mmd-step-desc"><?php esc_html_e( 'The plugin requires WooCommerce 4.0+. You\'ve activated it, so this step is done.', 'incentiv' ); ?></div>
            </div>
        </div>
        <div class="mmd-step-card">
            <span class="mmd-step-num-circle <?php echo $mmd_has_rules ? 'done' : 'current'; ?>"><?php echo $mmd_has_rules ? '✓' : '2'; ?></span>
            <div>
                <div class="mmd-step-title"><?php esc_html_e( 'Create your first rule', 'incentiv' ); ?></div>
                <div class="mmd-step-desc"><?php
                /* translators: %s: URL of the new-rule wizard. */
                echo wp_kses_post( sprintf( __( 'Use the <a href="%s">wizard</a> or import a ready-made template in one click.', 'incentiv' ), esc_url( admin_url( 'admin.php?page=mix-match-discounts&action=new' ) ) ) ); ?></div>
            </div>
        </div>
        <div class="mmd-step-card">
            <span class="mmd-step-num-circle">3</span>
            <div>
                <div class="mmd-step-title"><?php esc_html_e( 'Customise messages and design', 'incentiv' ); ?></div>
                <div class="mmd-step-desc"><?php
                /* translators: %s: URL of the Customer view screen. */
                echo wp_kses_post( sprintf( __( 'Set colours, texts and banners in <a href="%s">Customer view</a>.', 'incentiv' ), esc_url( admin_url( 'admin.php?page=mmd-storefront' ) ) ) ); ?></div>
            </div>
        </div>
        <div class="mmd-step-card">
            <span class="mmd-step-num-circle">4</span>
            <div>
                <div class="mmd-step-title"><?php esc_html_e( 'Track it on the dashboard', 'incentiv' ); ?></div>
                <div class="mmd-step-desc"><?php
                /* translators: %s: URL of the dashboard. */
                echo wp_kses_post( sprintf( __( 'Analyse performance, redemptions and conversion funnels on <a href="%s">the dashboard</a>.', 'incentiv' ), esc_url( admin_url( 'admin.php?page=mmd-dashboard' ) ) ) ); ?></div>
            </div>
        </div>
    </div>

    <!-- FAQ -->
    <div class="mmd-faq-card" style="margin-bottom:24px;">
        <h3><?php esc_html_e( 'Frequently asked questions', 'incentiv' ); ?></h3>
        <div class="mmd-faq-item open">
            <div class="mmd-faq-q"><span><?php esc_html_e( 'How do I set up "buy 3, get one free"?', 'incentiv' ); ?></span><span class="msr">expand_more</span></div>
            <div class="mmd-faq-a"><?php esc_html_e( 'Set a discount tier with a quantity of 3 and a 100% discount. The plugin applies the discount to the cheapest item in the cart.', 'incentiv' ); ?></div>
        </div>
        <div class="mmd-faq-item">
            <div class="mmd-faq-q"><span><?php esc_html_e( 'Can customers mix different products?', 'incentiv' ); ?></span><span class="msr">expand_more</span></div>
            <div class="mmd-faq-a"><?php echo wp_kses_post( __( 'Yes! That\'s exactly what Mix &amp; Match is: choose the "Categories" method and customers can mix any product from the included categories to reach the qualifying quantity.', 'incentiv' ) ); ?></div>
        </div>
        <div class="mmd-faq-item">
            <div class="mmd-faq-q"><span><?php esc_html_e( 'Does it support Hebrew and RTL?', 'incentiv' ); ?></span><span class="msr">expand_more</span></div>
            <div class="mmd-faq-a"><?php esc_html_e( 'Yes. The admin and storefront are built RTL-first and a Hebrew translation is included. On stores with VAT-inclusive prices, discounts stay exact on those prices.', 'incentiv' ); ?></div>
        </div>
        <div class="mmd-faq-item">
            <div class="mmd-faq-q"><span><?php esc_html_e( 'How do I track discount performance?', 'incentiv' ); ?></span><span class="msr">expand_more</span></div>
            <div class="mmd-faq-a"><?php esc_html_e( 'The new dashboard shows sales attributed to discounts, redeemed gifts, conversion rates and top rules, all from your real order data.', 'incentiv' ); ?></div>
        </div>
    </div>

    <?php
    // The translatable guide below is the main documentation. Hebrew-locale sites keep the
    // original Hebrew / English toggle with the static English guide; other locales show only
    // the (translated) guide, so English admins don't see the same text twice.
    $mmd_docs_toggle = ( 0 === strpos( determine_locale(), 'he' ) );
    if ( $mmd_docs_toggle ) : ?>
    <div class="mmd-docs-lang-toggle">
        <button type="button" class="button mmd-docs-lang-btn active" data-lang="he"><?php esc_html_e( 'Hebrew', 'incentiv' ); ?></button>
        <button type="button" class="button mmd-docs-lang-btn" data-lang="en">English</button>
    </div>
    <?php endif; ?>

    <!-- HEBREW DOCUMENTATION -->
    <div class="mmd-docs-content mmd-docs-he" id="mmd-docs-he" <?php echo $mmd_docs_toggle ? 'style="direction: rtl; text-align: right;"' : ''; ?>>

        <div class="mmd-docs-card">
            <h2><?php esc_html_e( 'User guide: Incentiv', 'incentiv' ); ?></h2>
            <p><?php esc_html_e( 'This plugin lets you create advanced quantity discount rules for your WooCommerce store. It supports many scenarios such as "buy 3 get a discount", BOGO, tiered discounts and more.', 'incentiv' ); ?></p>
        </div>

        <div class="mmd-docs-card">
            <h2><?php esc_html_e( '1. Quick start', 'incentiv' ); ?></h2>
            <ol>
                <li><?php echo wp_kses_post( __( 'Go to the <strong>"Quantity discounts"</strong> menu in the admin sidebar.', 'incentiv' ) ); ?></li>
                <li><?php echo wp_kses_post( __( 'Click <strong>"Add new rule"</strong> or use the <strong>creation wizard</strong> for guided setup.', 'incentiv' ) ); ?></li>
                <li><?php echo wp_kses_post( __( 'Alternatively, import one of the <strong>ready-made example rules</strong> with one click.', 'incentiv' ) ); ?></li>
                <li><?php esc_html_e( 'Set the rule name, choose products and set the discount tiers.', 'incentiv' ); ?></li>
                <li><?php esc_html_e( 'Save, and the discount starts working immediately!', 'incentiv' ); ?></li>
            </ol>
        </div>

        <div class="mmd-docs-card">
            <h2><?php esc_html_e( '2. Using the rule wizard', 'incentiv' ); ?></h2>
            <p><?php esc_html_e( 'The wizard guides you step by step through creating a discount rule:', 'incentiv' ); ?></p>
            <ol>
                <li><?php echo wp_kses_post( __( '<strong>Step 1 - Basic settings:</strong> name the rule, enable it, and choose which item the discount applies to (the cheapest or the most expensive).', 'incentiv' ) ); ?></li>
                <li><?php echo wp_kses_post( __( '<strong>Step 2 - Product selection:</strong> choose how to identify eligible products: all products, by category, by product ID, or by attribute.', 'incentiv' ) ); ?></li>
                <li><?php echo wp_kses_post( __( '<strong>Step 3 - Discount tiers:</strong> set the discount tiers: how many items must be bought and what percentage/amount to give.', 'incentiv' ) ); ?></li>
                <li><?php echo wp_kses_post( __( '<strong>Step 4 - Messages:</strong> set messages shown to customers on the product page and in the cart.', 'incentiv' ) ); ?></li>
                <li><?php echo wp_kses_post( __( '<strong>Step 5 - Conditions:</strong> add advanced conditions such as a minimum cart total, user role, scheduling and usage limits.', 'incentiv' ) ); ?></li>
            </ol>
            <p><?php echo wp_kses_post( __( 'To start the wizard, click <strong>"Create with wizard"</strong> on the rules list page.', 'incentiv' ) ); ?></p>
        </div>

        <div class="mmd-docs-card">
            <h2><?php esc_html_e( '3. Product selection methods', 'incentiv' ); ?></h2>
            <table class="mmd-docs-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Method', 'incentiv' ); ?></th>
                        <th><?php esc_html_e( 'Description', 'incentiv' ); ?></th>
                        <th><?php esc_html_e( 'When to use', 'incentiv' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong><?php esc_html_e( 'All products', 'incentiv' ); ?></strong></td>
                        <td><?php esc_html_e( 'The discount applies to all products in the store', 'incentiv' ); ?></td>
                        <td><?php esc_html_e( 'When you want a general store-wide promotion', 'incentiv' ); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php esc_html_e( 'By categories', 'incentiv' ); ?></strong></td>
                        <td><?php esc_html_e( 'The discount applies only to products from selected categories', 'incentiv' ); ?></td>
                        <td><?php esc_html_e( 'When you want a promotion on a specific group of products', 'incentiv' ); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php esc_html_e( 'By product IDs', 'incentiv' ); ?></strong></td>
                        <td><?php esc_html_e( 'The discount applies to specific selected products', 'incentiv' ); ?></td>
                        <td><?php esc_html_e( 'When you want a promotion on certain products only', 'incentiv' ); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php esc_html_e( 'By attribute', 'incentiv' ); ?></strong></td>
                        <td><?php esc_html_e( 'The discount applies to products with certain attribute values (such as colour, size)', 'incentiv' ); ?></td>
                        <td><?php esc_html_e( 'When you want a promotion on specific variations', 'incentiv' ); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="mmd-docs-card">
            <h2><?php esc_html_e( '4. Discount types', 'incentiv' ); ?></h2>
            <table class="mmd-docs-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Type', 'incentiv' ); ?></th>
                        <th><?php esc_html_e( 'Description', 'incentiv' ); ?></th>
                        <th><?php esc_html_e( 'Example', 'incentiv' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong><?php esc_html_e( 'Percentage (%)', 'incentiv' ); ?></strong></td>
                        <td><?php esc_html_e( 'A percentage off the price', 'incentiv' ); ?></td>
                        <td><?php esc_html_e( '30% off = an item at 100 ₪ becomes 70 ₪', 'incentiv' ); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php esc_html_e( 'Fixed amount', 'incentiv' ); ?></strong></td>
                        <td><?php esc_html_e( 'Fixed amount discount', 'incentiv' ); ?></td>
                        <td><?php esc_html_e( '20 ₪ off = an item at 100 ₪ becomes 80 ₪', 'incentiv' ); ?></td>
                    </tr>
                </tbody>
            </table>
            <p><?php echo wp_kses_post( __( '<strong>Tip:</strong> use 100% to give an item for free (like "buy 4 get 1 free").', 'incentiv' ) ); ?></p>
        </div>

        <div class="mmd-docs-card">
            <h2><?php esc_html_e( '5. Advanced settings', 'incentiv' ); ?></h2>
            <h3><?php esc_html_e( 'Coupon requirement', 'incentiv' ); ?></h3>
            <p><?php esc_html_e( 'You can make a discount apply only when the customer enters a specific coupon code. Useful for VIP or exclusive promotions.', 'incentiv' ); ?></p>

            <h3><?php esc_html_e( 'Cart total conditions', 'incentiv' ); ?></h3>
            <p><?php esc_html_e( 'Set a minimum and/or maximum cart total for the discount to be active.', 'incentiv' ); ?></p>

            <h3><?php esc_html_e( 'User role limits', 'incentiv' ); ?></h3>
            <p><?php esc_html_e( 'You can limit a discount to certain roles (for example, wholesale customers only).', 'incentiv' ); ?></p>

            <h3><?php esc_html_e( 'Scheduling', 'incentiv' ); ?></h3>
            <p><?php esc_html_e( 'Set start and end dates for the discount. Perfect for holiday or end-of-season sales.', 'incentiv' ); ?></p>

            <h3><?php esc_html_e( 'Usage limits', 'incentiv' ); ?></h3>
            <p><?php esc_html_e( 'Limit how many times the discount can be used, in total and/or per customer.', 'incentiv' ); ?></p>

            <h3><?php esc_html_e( 'Shipping method condition', 'incentiv' ); ?></h3>
            <p><?php esc_html_e( 'Activate a discount only when the customer chooses a specific shipping method.', 'incentiv' ); ?></p>
        </div>

        <div class="mmd-docs-card">
            <h2><?php esc_html_e( '6. Ready-made example rules', 'incentiv' ); ?></h2>
            <p><?php esc_html_e( 'The plugin includes several ready-to-use example rules:', 'incentiv' ); ?></p>
            <ul>
                <li><?php echo wp_kses_post( __( '<strong>Buy 3 get 30% off</strong> - a simple quantity discount', 'incentiv' ) ); ?></li>
                <li><?php echo wp_kses_post( __( '<strong>Tiered discounts</strong> - the discount grows with the quantity (10%, 20%, 30%, free)', 'incentiv' ) ); ?></li>
                <li><strong>BOGO 50%</strong> <?php esc_html_e( '- buy 1 get 1 at half price', 'incentiv' ); ?></li>
                <li><?php echo wp_kses_post( __( '<strong>Fixed discount</strong> - a fixed amount by quantity', 'incentiv' ) ); ?></li>
                <li><?php echo wp_kses_post( __( '<strong>VIP coupon discount</strong> - an exclusive discount with a coupon code', 'incentiv' ) ); ?></li>
                <li><?php echo wp_kses_post( __( '<strong>Buy 4 get 1 free</strong> - the fifth item is free', 'incentiv' ) ); ?></li>
            </ul>
            <p><?php echo wp_kses_post( __( 'To import, go to the <strong>"Discount rules"</strong> page and click <strong>"Import rule"</strong> next to the example you want.', 'incentiv' ) ); ?></p>
        </div>

        <div class="mmd-docs-card">
            <h2><?php esc_html_e( '7. FAQ', 'incentiv' ); ?></h2>

            <h3><?php esc_html_e( 'What happens when a product matches several rules?', 'incentiv' ); ?></h3>
            <p><?php esc_html_e( 'The first matching rule found applies. You can set "Override other discounts" so that a specific rule wins over the others.', 'incentiv' ); ?></p>

            <h3><?php esc_html_e( 'Does the discount apply to products that are already on sale?', 'incentiv' ); ?></h3>
            <p><?php esc_html_e( 'Yes, the discount is calculated on the product\'s current price (including the sale price if there is one).', 'incentiv' ); ?></p>

            <h3><?php esc_html_e( 'How do I know the discount is working?', 'incentiv' ); ?></h3>
            <p><?php esc_html_e( 'Add products to the cart and check. The discount message appears on the product page and in the cart. The discount appears as a negative "fee" line in the cart.', 'incentiv' ); ?></p>

            <h3><?php esc_html_e( 'Can I set different discounts for different categories?', 'incentiv' ); ?></h3>
            <p><?php esc_html_e( 'Yes! Create a separate rule for each category with different discount tiers.', 'incentiv' ); ?></p>
        </div>
    </div>

    <?php if ( $mmd_docs_toggle ) : ?>
    <!-- ENGLISH DOCUMENTATION -->
    <div class="mmd-docs-content mmd-docs-en" id="mmd-docs-en" style="display: none; direction: ltr; text-align: left;">

        <div class="mmd-docs-card">
            <h2>User Guide — Incentiv</h2>
            <p>This plugin allows you to create advanced quantity discount rules for your WooCommerce store. It supports various scenarios like "Buy 3 Get a Discount", BOGO, tiered discounts, and more.</p>
        </div>

        <div class="mmd-docs-card">
            <h2>1. Quick Start</h2>
            <ol>
                <li>Navigate to the <strong>"Quantity Discounts"</strong> menu in the WordPress admin sidebar.</li>
                <li>Click <strong>"Add New Rule"</strong> or use the <strong>Creation Wizard</strong> for guided setup.</li>
                <li>Alternatively, import one of the <strong>ready-to-use example rules</strong> with a single click.</li>
                <li>Set the rule name, select products, and define discount tiers.</li>
                <li>Save - the discount starts working immediately!</li>
            </ol>
        </div>

        <div class="mmd-docs-card">
            <h2>2. Using the Rule Creation Wizard</h2>
            <p>The wizard guides you step by step through creating a discount rule:</p>
            <ol>
                <li><strong>Step 1 - Basic Settings:</strong> Name your rule, enable it, and choose which item gets discounted (cheapest or most expensive).</li>
                <li><strong>Step 2 - Product Selection:</strong> Choose how to identify eligible products - all products, by category, by product ID, or by attribute.</li>
                <li><strong>Step 3 - Discount Rules:</strong> Set discount tiers - how many items to buy and what percentage/amount discount to give.</li>
                <li><strong>Step 4 - Notices:</strong> Configure messages shown to customers on the product page and in the cart.</li>
                <li><strong>Step 5 - Conditions:</strong> Add advanced conditions like minimum cart total, user role, scheduling, and usage limits.</li>
            </ol>
            <p>To launch the wizard, click <strong>"Create with Wizard"</strong> on the rules list page.</p>
        </div>

        <div class="mmd-docs-card">
            <h2>3. Product Selection Methods</h2>
            <table class="mmd-docs-table">
                <thead>
                    <tr>
                        <th>Method</th>
                        <th>Description</th>
                        <th>When to Use</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>All Products</strong></td>
                        <td>Discount applies to all products in the store</td>
                        <td>For store-wide promotions</td>
                    </tr>
                    <tr>
                        <td><strong>By Categories</strong></td>
                        <td>Discount applies only to products in selected categories</td>
                        <td>For category-specific promotions</td>
                    </tr>
                    <tr>
                        <td><strong>By Product IDs</strong></td>
                        <td>Discount applies to specifically selected products</td>
                        <td>For individual product promotions</td>
                    </tr>
                    <tr>
                        <td><strong>By Attribute</strong></td>
                        <td>Discount applies to products with specific attribute values (color, size, etc.)</td>
                        <td>For variation-specific promotions</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="mmd-docs-card">
            <h2>4. Discount Types</h2>
            <table class="mmd-docs-table">
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Description</th>
                        <th>Example</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>Percentage (%)</strong></td>
                        <td>Percentage off the price</td>
                        <td>30% off = $100 item becomes $70</td>
                    </tr>
                    <tr>
                        <td><strong>Fixed Amount</strong></td>
                        <td>Fixed amount off the price</td>
                        <td>$20 off = $100 item becomes $80</td>
                    </tr>
                </tbody>
            </table>
            <p><strong>Tip:</strong> Use 100% to give a free item (like "Buy 4 Get 1 Free").</p>
        </div>

        <div class="mmd-docs-card">
            <h2>5. Advanced Settings</h2>

            <h3>Coupon Requirement</h3>
            <p>You can require customers to enter a specific coupon code to activate the discount. Useful for VIP or exclusive promotions.</p>

            <h3>Cart Total Conditions</h3>
            <p>Set minimum and/or maximum cart totals for the discount to be active.</p>

            <h3>User Role Restrictions</h3>
            <p>Limit discounts to specific user roles (e.g., wholesale customers only).</p>

            <h3>Scheduling</h3>
            <p>Set start and end dates for the discount. Perfect for holiday sales or end-of-season promotions.</p>

            <h3>Usage Limits</h3>
            <p>Limit how many times the discount can be used - in total and/or per customer.</p>

            <h3>Shipping Method Condition</h3>
            <p>Activate the discount only when the customer selects a specific shipping method.</p>
        </div>

        <div class="mmd-docs-card">
            <h2>6. Ready-to-Use Example Rules</h2>
            <p>The plugin includes several pre-configured example rules:</p>
            <ul>
                <li><strong>Buy 3 Get 30% Off</strong> - Simple quantity discount</li>
                <li><strong>Tiered Volume Discount</strong> - Increasing discount with quantity (10%, 20%, 30%, free)</li>
                <li><strong>BOGO 50%</strong> - Buy one get one at half price</li>
                <li><strong>Fixed Amount Discount</strong> - Fixed amount per quantity tier</li>
                <li><strong>VIP Coupon Discount</strong> - Exclusive discount with coupon code</li>
                <li><strong>Buy 4 Get 1 Free</strong> - The fifth item is free</li>
            </ul>
            <p>To import, go to the <strong>"Manage Discount Rules"</strong> page and click <strong>"Import Rule"</strong> next to the desired example.</p>
        </div>

        <div class="mmd-docs-card">
            <h2>7. FAQ</h2>

            <h3>What happens when a product matches multiple rules?</h3>
            <p>The first matching rule found will apply. You can set "Override other discounts" to make a specific rule take priority.</p>

            <h3>Does the discount apply to already-on-sale products?</h3>
            <p>Yes, the discount is calculated on the current product price (including sale price if set).</p>

            <h3>How do I verify the discount is working?</h3>
            <p>Add products to the cart and check. The discount notice will appear on the product page and in the cart. The discount appears as a negative "fee" line in the cart.</p>

            <h3>Can I set different discounts for different categories?</h3>
            <p>Yes! Create a separate rule for each category with different discount tiers.</p>
        </div>
    </div>
    <?php endif; ?>

    </div><!-- /max-width wrapper -->
</div>

<script>
jQuery(document).ready(function($) {
    $('.mmd-docs-lang-btn').on('click', function() {
        var lang = $(this).data('lang');
        $('.mmd-docs-lang-btn').removeClass('active');
        $(this).addClass('active');
        $('.mmd-docs-content').hide();
        $('#mmd-docs-' + lang).show();
    });
});
</script>

