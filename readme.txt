=== Incentiv – Advanced Promotions for WooCommerce ===
Contributors: elis85
Donate link: https://a-in.co.il/plugins/incentiv/
Tags: discounts, quantity discounts, gifts, promotions
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 2.1.0
WC requires at least: 4.0
WC tested up to: 11.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A promotions engine for WooCommerce: quantity and mix & match discounts, automatic gifts and BOGO, margin guards and a visual flow builder.

== Description ==

Incentiv (formerly Mix & Match Quantity Discounts) is a promotions engine for WooCommerce. Build quantity and mix & match discounts and automatic gifts, keep them profitable with margin guards, then see each promotion as a visual customer journey in the flow builder.

**Main features**

* **Quantity discounts**: discount tiers per product, category or mix & match combination.
* **Automatic gifts (BOGO)**: a gift picker for the customer, stock awareness (out-of-stock gifts are not offered) and a cart fallback (if no gift was picked on the product page, it is offered in the cart).
* **Rule wizard and editor**: a step indicator, quick-fill presets, a live preview and ready-made example rules.
* **Coupon requirement**: make a rule apply only with a coupon code you choose.
* **Visual flow builder**: each promotion as a customer journey.
* **Margin guards and limits**: maximum discount, budget caps and a margin floor.
* **Dashboard**: sales from discounts, gifts redeemed, discount type breakdown, funnel and top rules.
* **Storefront**: Elementor widgets and shortcodes, discounts on the cheapest or most expensive item, and custom cart messages.
* **Built for the Israeli market**: RTL-first admin and storefront (mini cart included), a Hebrew translation in the package, and discounts that stay exact on VAT-inclusive prices.
* Rule diagnostics and debug mode.

Everything listed above is free and complete. There are no locked features, licence keys or trial periods in this plugin.

**Incentiv Pro**

Incentiv Pro is a separate add-on, sold on [a-in.co.il](https://a-in.co.il/plugins/incentiv/), for stores that want more: special events with access control, per-rule attribution with CSV export and an email digest, audience targeting (new or returning customers, order count, lifetime value, country, language, UTM), promotion protection (block list), smart coupons with campaigns and auto-apply links, and the smart rule builder. Incentiv works fully without it.

**Use cases**

* Coffee shops: buy 3 kg, get a discount on the cheapest one.
* Electronics: discounts on large quantities.
* Fashion: buy 5 items, get one free.
* Any product where you want to encourage buying in quantity.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`, or install it from the Plugins screen.
2. Activate it. WooCommerce must be active.
3. Open **Incentiv** in the admin menu.
4. Create your first discount rule, or import a ready-made example rule.

== Frequently Asked Questions ==

= Does the plugin require WooCommerce? =

Yes, WooCommerce 4.0 or later.

= Can customers mix different products? =

Yes. That's what Mix & Match is for: customers can combine different products and still get the discount.

= Is Hebrew supported? =

Yes. The plugin was built for the Israeli market: the admin and storefront are RTL-first and a Hebrew (he_IL) translation is included. On stores that enter prices including VAT, discounts stay exact on the VAT-inclusive prices (using the store's WooCommerce tax settings).

= Is the admin available in English? =

Yes. All admin screens, storefront messages, emails and scripts use English source text and are translatable (text domain `incentiv`). A Hebrew (he_IL) translation ships with the plugin, so Hebrew sites look the same as before. The admin follows the site's text direction (RTL or LTR).

= How do I set up "buy 3, get one free"? =

Create a discount rule with a quantity of 3 and a 100% discount, applied to the cheapest (or most expensive) item.

== External services ==

Incentiv does not contact any external service.

== Third-party libraries ==

* Select2 4.0.13 (MIT), `assets/vendor/select2/`. Source: https://github.com/select2/select2
* jQuery UI 1.13.2 base theme CSS (MIT), `assets/vendor/jquery-ui/`. Source: https://github.com/jquery/jquery-ui
* Fonts: Assistant and Sora (SIL Open Font License 1.1), Material Symbols Rounded (Apache License 2.0), `assets/vendor/fonts/`.

== Screenshots ==

1. Discount rules: statuses, tiers and a gallery of ready-made templates.
2. Rule editor with a step indicator, discount tiers with quick-fill presets and a live preview.
3. Gift rules with customer choice and a live preview of the gift widget.
4. Flow builder: each promotion as a visual customer journey.
5. What the customer sees: tier table, gift picker and the discounted cart.

== Changelog ==

= 2.1.0 =
* Fixed: the product-page gift picker no longer offers out-of-stock gifts (the cart picker already left them out).
* Fixed: the Elementor Gift notice widget no longer lists out-of-stock gifts.
* Fixed: the cart page showed the gift offer twice (gift picker and gift bar) and showed messages from rules that had no products in the cart. Each message now shows once, and only for rules the cart can use (filter `mmd_show_rule_notice_without_items` restores the old behaviour). Gift debug data is printed to the browser console only in debug mode.
* Incentiv is now the complete free core. Special events, per-rule attribution with CSV export and the email digest, audience targeting, promotion protection, smart coupons and the smart rule builder moved to the separate Incentiv Pro add-on.
* Your data is kept: settings and rule fields that belong to Pro are left untouched, and a rule with audience conditions waits (does not apply) until Pro is active again, so a targeted offer never turns into an offer for everyone.
* Developers: a module registry and new hooks (`mmd_register_modules`, `mmd_admin_tabs`, `mmd_rule_editor_cards`, `mmd_dashboard_sections`, `mmd_promotions_blocked`, `mmd_rule_set_matches_audience`, `mmd_sanitize_rule_set`, `mmd_flow_builder_flows`).

= 2.0.1 =
* Fixed: the cart no longer looks double-discounted. A discount added as a cart line (the default) is now shown only in that line, with no extra strike-through on the product row and no reduced Subtotal. Discounts allocated onto products are shown only in the product prices, with no extra "already included" row above the total. Totals were always correct.
* Privacy: the optional geolocation fallback for location-restricted special events is now off by default and must be enabled per event (special events are part of Incentiv Pro since 2.1.0).
* The jQuery UI datepicker CSS is bundled locally instead of being loaded from code.jquery.com.
* i18n: every hard-coded Hebrew string in the admin, storefront, emails and JavaScript is now English source text wrapped in gettext. JavaScript uses `wp.i18n` with `wp_set_script_translations()`. Hebrew included: the translation ships as `languages/incentiv-he_IL.po/.mo` plus JSON files for the scripts, and the `.pot` is included.
* The admin layout follows the site's text direction: left-to-right on LTR sites, right-to-left on Hebrew and other RTL sites.
* Security: late escaping for HTML built in PHP (badges, site-wide notices, debug panels, banners) and for exception messages.
* Fix: the daily analytics cleanup no longer runs a DELETE on the special-events analytics table before that table exists.
* English plugin description and readme; Requires at least 6.0, Requires PHP 7.4, `Requires Plugins: woocommerce`.
* The GitHub sync module is optional (it is not part of the WordPress.org package).
* Declares WooCommerce HPOS compatibility (orders are only read through `wc_get_orders()`).
* The default rule created on a fresh install now has a translatable English name ("Default quantity discounts").
* Readme: shorter description, Donate link, FAQ on the English admin. New English screenshots.

= 2.0.0 =
* Rebrand: Incentiv, the advanced promotions engine for WooCommerce.
* New admin design: analytics dashboard, rule step indicator and live preview.
* Gifts (v2): gift rules with customer choice, stock awareness and fallbacks, including Elementor widgets.
* Smart coupons and coupon management.
* Special events: scheduling, access control, checkout and analytics.
* Visual flow builder and conversational rule builder.
* Segments and targeting, margin guards and limits, discount allocation.
* Reports and attribution: per-rule metrics, lift, CSV export and an email digest.
* Rule Diagnostic and Storefront view in the admin.
* Elementor widgets: event banner, gift selection and events.
* Shortcodes: mmd_gift_selection, mmd_rule_banner.

= 1.0.0 =
* Initial release: quantity discounts, flexible product selection, custom messages, debug mode.

== Upgrade Notice ==

= 2.1.0 =
Special events, advanced analytics, audience targeting, promotion protection, smart coupons and the smart rule builder are now part of Incentiv Pro. Their data is kept. Sites on the paid build keep every module.

= 2.0.0 =
Incentiv 2.0: new branding, analytics dashboard, gifts v2, special events, flow builder and attribution reports. Back up before upgrading.
