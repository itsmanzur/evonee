=== Evonee - Get Quote System ===
Contributors: evoneeteam
Tags: quote, b2b, woocommerce quote, price estimator, lead capture
Requires at least: 5.8
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 2.5.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Professional AJAX Quote Modal, Price Estimator, PDF Sheet Generator & CRM Lead Management for WordPress and WooCommerce.

== Short Description ==
Professional AJAX Quote Modal, Price Estimator, PDF Sheet Generator & CRM Lead Management for WordPress and WooCommerce.

== Description ==

**Evonee Get Quote System (v2.0.0 Enterprise)** is a complete B2B quotation pipeline and custom promotional product lead capture solution. Designed for manufacturing companies, printing shops, promo product vendors, and custom merchandise businesses.

= 🚀 8 Core Modules & 35+ Features =

* **📦 Module 1 — Advanced CRM (Admin):** Unlimited pagination, team internal notes, follow-up date reminders with due badges, activity log timeline, sortable table headers, date range filters, and WP Dashboard Widget.
* **✉️ Module 2 — Email Branding & History:** Visual Email Template Builder (logo, colors, custom footer), detailed outgoing Email Dispatch Log, 1-click Quick Reply Templates, and tokenized quote acceptance links.
* **💰 Module 3 — Quoting & PDF Generation:** Quoted Price Entry in admin drawer, 1-Click Printable/Save-as-PDF Official Quote Sheet with custom brand logo support, live unit price estimator, and customer acceptance buttons (Accept/Decline).
* **📊 Module 4 — Interactive Analytics:** Chart.js Monthly Submissions Trend Bar Chart, Status Distribution Doughnut Chart, Conversion Funnel (Submitted → Quoted → Approved → Completed), and CSV Data Export.
* **🛒 Module 5 — WooCommerce B2B Integration:** Auto-detects WooCommerce shop items, loop auto-buttons, and optional B2B "Quote Only" Mode (hides traditional Add to Cart buttons).
* **🔗 Module 6 — Third-Party Integrations:** Webhook support for Zapier, Make & HubSpot, plus formatted instant Slack Channel Lead Notifications.
* **🛡️ Module 7 — Security Shield & UX:** Invisible Google reCAPTCHA v3, multi-file artwork upload (up to 3 files), LocalStorage form draft auto-resume, GDPR consent checkbox, honeypot bot protection, and IP rate-limiting.
* **🎨 Module 8 — Modern UI/UX:** Multi-step gradient progress bar, live social proof counter ("⚡ X quotes requested today"), floating WhatsApp quick chat button, and responsive product grids.

== Installation ==

1. Upload the `evonee` folder to the `/wp-content/plugins/` directory (or upload the `.zip` file via **Plugins → Add New → Upload Plugin**).
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Navigate to **Evonee Quotes → Settings** to set your sales email address, brand logo, and module toggles.
4. Add the shortcode `[evonee_products]` to any page or Elementor Shortcode Widget to display the popular products grid.

== Shortcodes Reference ==

* `[evonee_products]` — Displays the popular products grid with interactive Get Quote popup triggers.
  * Attributes: `cols="6"`, `cols_tablet="3"`, `cols_mobile="2"`, `gap="18px"`, `limit="12"`, `show_title="no"`
* `[evonee_quote_button product="Silicone Wristband" text="Get Free Quote"]` — Renders a standalone quote trigger button for any specific product.

== Elementor & Custom Theme Triggers ==

You can convert any custom button or link into a quote modal trigger by adding the class `eq-trigger` and setting data attributes:

```html
<button type="button" class="eq-trigger my-btn" 
        data-product="Silicone Wristband" 
        data-image="https://example.com/wristband.png"
        data-description="Custom silicone wristbands for events">
    Get Quote &rarr;
</button>
```

== Frequently Asked Questions ==

= Does it work with Elementor? =
Yes! Use the `[evonee_products]` shortcode inside Elementor's Shortcode widget or add the `eq-trigger` class to any Elementor button.

= How does Customer Quote Acceptance work? =
When an admin sends a reply email from the Submissions detail drawer, the plugin automatically appends secure "Accept Quote" and "Decline Quote" action buttons with a 30-day expiring token. Clicking a link updates the quote status directly in your CRM to `APPROVED` or `REJECTED`.

= Can I send lead data to Zapier or Slack? =
Yes. In **Evonee Quotes → Settings (Section 6)**, enable Webhooks or Slack notifications and input your target Webhook URL.

== Screenshots ==

1. Admin CRM Submissions Table with Filters, Search, and Pagination.
2. Submission Detail Drawer with Internal Notes, Timeline, and Quoted Price Entry.
3. 1-Click Printable PDF Official Quote Sheet.
4. Interactive Analytics & Business Reports Dashboard with Chart.js Graphs.
5. Pop-up Quote Modal with Multi-step Progress Bar, Price Estimator, and Dropzone Upload.

== Changelog ==

= 2.0.0 =
* Initial Enterprise Release with 8 Core Modules and 35+ Features.
