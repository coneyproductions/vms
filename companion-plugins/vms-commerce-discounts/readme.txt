=== VMS Commerce Discounts ===
Contributors: venue-management-system
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 0.2.13
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Rule-based discounts and optional checkout tips for WooCommerce products, TEC/Event Tickets, and VMS entitlements.

== Description ==
VMS Commerce Discounts applies configurable global and per-event rules with support for regular WooCommerce products, TEC/Event Tickets, VMS entitlements, optional checkout tips/gratuity as Woo fee lines, Square-native discount bridging, and richer Woo audit ledgering.

Key features:
- Multiple rules per event
- Optional global rules
- Regular WooCommerce product targeting
- Ticket / add-on / entitlement / combined targeting
- Square-native discount bridge for WooCommerce Square 5.2.0
- Optional checkout tip/gratuity prompt using WooCommerce fee lines
- Rich order audit meta (`_vms_discounts_applied`, `_vms_discounts_ledger`)

== Installation ==
1. Upload `vms-commerce-discounts.zip` in WordPress Plugins.
2. Activate plugin.
3. Configure global rules at WooCommerce > VMS Discounts.
4. Configure tips/gratuity in the Tips section when needed.
5. Configure per-event rules in the event editor meta box.

== Changelog ==
= 0.2.13 =
* Preserves explicitly recorded zero-dollar and comped Woo cart-line subtotals as zero even when the catalog product price is positive.
* Falls back to the live product price only when Woo has not recorded line subtotal or line total data.
* Keeps genuinely paid and discounted-positive quantities eligible for existing rules and allocations.

= 0.2.12 =
* Prevents activation fatals when WooCommerce Square is unavailable by loading the Square bridge only after its request parent class exists.
* Keeps non-Square discount features available and shows administrators that Square-specific discount synchronization is unavailable.
* Preserves the existing Square bridge classes and callback registrations when WooCommerce Square is available.

= 0.2.11 =
* Hotfix: stops Woo Cart/Checkout Blocks from rendering VMS discount display HTML as literal text.
* Keeps the 0.2.8 calculation fallback and the 0.2.9 classic cart line-through presentation.
* Changes block cart display filters to return safe text-only price formats with the required `<price/>` placeholder.
* Adds plain-text Store API display fields so block filters no longer have to inject `wc_price()` HTML.

= 0.2.9 =
- Restored visible cart-line discount presentation for VMS-adjusted cart items. Classic cart templates now show original price struck through, discounted price, and the applied discount label.
- Added Woo Cart/Checkout Block support by exposing VMS discount display data through the Store API and registering block checkout display filters when available.
- Added lightweight frontend CSS/JS for discount display only on cart and checkout screens.

= 0.2.8 =
- Fixed a ticket-count discount regression where Woo cart lines with a temporary recorded subtotal of 0.00 could be treated as non-paid before Woo recalculated totals, preventing 4-ticket and similar paid-ticket discounts from applying.
- Discount qualification now uses the restored VMS original price when present, then a positive recorded Woo line subtotal, then the current live product price as a fallback for paid lines that have not had totals written yet.
- Preserves zero-dollar/free-line protection when the live product price is also zero.

= 0.2.7 =
- Added a standalone Tips / Gratuity module inside the existing discounts plugin for quick Express Bar checkout use without changing the discount rule engine.
- Tips are optional positive WooCommerce fee lines with settings for scope, label, prompt copy, preset amounts, custom amount support, max amount, and taxable/non-taxable behavior.
- Added regular-product-only scope as the recommended default for Express Bar today, plus all-order and best-effort Express Bar-only scope options.
- Recorded tip order/fee metadata for future reporting while keeping tips separate from ticket revenue, discounts, add-on qualification, inventory, and vendor bonus calculations.

= 0.2.6 =
- Added order-wide global discount evaluation so regular WooCommerce product orders can qualify for and receive VMS discounts, not just event-ticket carts.
- Added product-generic targeting options: Selected products only, All products in the order, and Any paid product count.
- Preserved existing event ticket/add-on rules while preventing product-generic global rules from double-applying inside event contexts.
- Updated order note/admin ledger language so non-event discounts show as Order-wide rather than Event 0.

= 0.2.5 =
- Improved rule-builder UX: adding or duplicating a rule now collapses the other rule cards, scrolls to the newly created rule, highlights it, and announces that the new rule is ready to edit.
- Added an Edit/Collapse control to each rule card so long existing discount forms do not hide newly added rules.
- Improved product picker UX for product-count rules: search results now support selecting multiple products at once, adding selected products in bulk, and preserving the current search results after each addition.

= 0.2.4 =
- Fixed the regression introduced during `BUG-27` work: positive-priced ticket lines once again recalculate discounts from the restored original unit price, while recorded Woo cart subtotals are only used to identify true zero-dollar lines.

= 0.2.3 =
- Corrected the percent-discount basis for tax-inclusive ticket pricing so a displayed `$20.00` ticket discounted by `10%` now reduces by `$2.00` instead of discounting Woo's tax-exclusive subtotal.

= 0.2.2 =
- Fixed `BUG-27` so comped/free ticket lines no longer qualify for or absorb discounts when the live Woo cart subtotal for that line is `0.00`.
- Discount qualification now trusts the recorded Woo cart line subtotal ahead of catalog product price, while still preserving VMS's own pre-discount original-price metadata on recalculation.

= 0.2.1 =
- Moved the Square-native bridge from the early `process_payment` filter to WooCommerce Square's later `get_order` filter so Checkout Block requests keep their populated payment nonce before the VMS bridge pre-creates the Square order.

= 0.2.0 =
- Added a Square-native discount bridge that pre-creates Square orders with explicit VMS discount entries before payment.
- Added a richer Woo order discount ledger, gross/discount/net admin summary, and Square sync status tracking.
- Added an explicit Square discount mode setting with native mode as the default and compatibility reduced-price mode as the fallback.

= 0.1.0 =
- Initial rule-engine release.
