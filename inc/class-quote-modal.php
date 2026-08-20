<?php
if (!defined('ABSPATH')) {
    exit;
}

class Evonee_Quote_Modal {

    public static function init() {
        $instance = new self();
        add_action('wp_enqueue_scripts', [$instance, 'enqueue_assets']);
        add_action('wp_footer', [$instance, 'render_modal']);

        // Product Grid Shortcodes (Perfect for Elementor)
        add_shortcode('evonee_products', [$instance, 'render_products_grid']);
        add_shortcode('evonee_products_grid', [$instance, 'render_products_grid']);
        add_shortcode('evonee_popular_products', [$instance, 'render_products_grid']);
        add_shortcode('evonee_landing_page', [$instance, 'render_products_grid']); // Renders product grid only as requested
        add_shortcode('evonee_full_landing_page', [$instance, 'render_full_landing_page']); // Full demo layout if needed

        add_shortcode('evonee_quote_button', [$instance, 'shortcode_quote_button']);

        // WooCommerce Integration (Only if WooCommerce is active)
        if (class_exists('WooCommerce')) {
            add_action('woocommerce_after_shop_loop_item', [$instance, 'render_wc_loop_button'], 15);
            add_action('wp', [$instance, 'wc_quote_only_mode']);
        }
    }

    /**
     * WooCommerce "Quote Only" Mode (Module 5)
     */
    public function wc_quote_only_mode() {
        $settings = Evonee_Quote_Admin::get_settings();
        if (!empty($settings['enable_wc_quote_only']) && $settings['enable_wc_quote_only'] === '1') {
            remove_action('woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart', 10);
            remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30);
            add_action('woocommerce_single_product_summary', [$instance ?? $this, 'render_wc_single_quote_btn'], 30);
        }
    }

    public function render_wc_single_quote_btn() {
        global $product;
        if (!$product) return;
        $image_id  = $product->get_image_id();
        $image_url = $image_id ? wp_get_attachment_image_url($image_id, 'large') : '';
        $desc      = wp_strip_all_tags($product->get_short_description() ?: $product->get_description());
        echo wp_kses_post(self::quote_button($product->get_name(), $image_url, $desc, 'Request Custom Quote', 'eq-btn-primary button-large'));
    }

    /**
     * Automatically append Get Quote button to WooCommerce archive product cards
     */
    public function render_wc_loop_button() {
        $settings = Evonee_Quote_Admin::get_settings();
        if (isset($settings['enable_wc_auto']) && $settings['enable_wc_auto'] !== '1') {
            return;
        }

        global $product;
        if (!$product) return;

        $image_id  = $product->get_image_id();
        $image_url = $image_id ? wp_get_attachment_image_url($image_id, 'medium') : '';
        $desc      = wp_strip_all_tags($product->get_short_description() ?: $product->get_description());

        echo wp_kses_post(self::quote_button($product->get_name(), $image_url, $desc, 'Get Quote', 'eq-btn-wc-loop'));
    }

    /**
     * Dynamic Product Fetcher
     * 1. Fetches real WooCommerce products if WooCommerce is installed
     * 2. Otherwise returns filterable default list with safe fallback SVG images
     */
    public static function get_products() {
        // Fetch from WooCommerce if active
        if (class_exists('WooCommerce')) {
            $wc_products = wc_get_products([
                'status' => 'publish',
                'limit'  => 20,
            ]);
            if (!empty($wc_products)) {
                $list = [];
                foreach ($wc_products as $p) {
                    $img_id  = $p->get_image_id();
                    $img_url = $img_id ? wp_get_attachment_image_url($img_id, 'medium') : '';
                    $list[]  = [
                        'name' => $p->get_name(),
                        'img'  => $img_url,
                        'desc' => wp_strip_all_tags($p->get_short_description() ?: $p->get_description())
                    ];
                }
                return $list;
            }
        }

        // Return filterable default list
        return apply_filters('evonee_quote_products', self::get_default_products());
    }

    /**
     * Default product list with SVG fallback graphics
     */
    private static function get_default_products() {
        $svg_placeholder = "data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='100' height='100' viewBox='0 0 24 24' fill='none' stroke='%236d28d9' stroke-width='1.5'><rect x='3' y='3' width='18' height='18' rx='4'/><path d='M8.5 10a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3z'/><path d='m21 15-5-5-11 11'/></svg>";

        return [
            ['name' => 'Wristband', 'img' => 'https://images.unsplash.com/photo-1579783902614-a3fb3927b675?auto=format&fit=crop&w=300&q=80', 'desc' => 'High-quality custom silicone wristbands for events, organizations, businesses, schools and more.'],
            ['name' => 'Can Cooler', 'img' => 'https://images.unsplash.com/photo-1584308666744-24d5c474f2ae?auto=format&fit=crop&w=300&q=80', 'desc' => 'Custom printed can coolers and stubby holders for events and promotions.'],
            ['name' => 'Lanyard', 'img' => 'https://images.unsplash.com/photo-1618005182384-a83a8bd57fbe?auto=format&fit=crop&w=300&q=80', 'desc' => 'Custom neck lanyards with badge holders for corporate events and tradeshows.'],
            ['name' => 'Patch', 'img' => 'https://images.unsplash.com/photo-1563245372-f21724e3856d?auto=format&fit=crop&w=300&q=80', 'desc' => 'Custom embroidered, woven, and PVC patches for apparel and gear.'],
            ['name' => 'Table Cover', 'img' => 'https://images.unsplash.com/photo-1517245386807-bb43f82c33c4?auto=format&fit=crop&w=300&q=80', 'desc' => 'Custom printed table throws and runners for trade shows and displays.'],
            ['name' => 'PVC ID Card', 'img' => 'https://images.unsplash.com/photo-1557804506-669a67965ba0?auto=format&fit=crop&w=300&q=80', 'desc' => 'Durable PVC plastic ID cards, membership cards, and event passes.'],
            ['name' => 'Balloons', 'img' => 'https://images.unsplash.com/photo-1530103862676-de8c9debad1d?auto=format&fit=crop&w=300&q=80', 'desc' => 'Custom printed latex and foil balloons for celebrations and branding.'],
            ['name' => 'Tote Bag', 'img' => 'https://images.unsplash.com/photo-1544816155-12df9643f363?auto=format&fit=crop&w=300&q=80', 'desc' => 'Eco-friendly custom canvas and non-woven tote bags with your logo.'],
            ['name' => 'Stickers', 'img' => 'https://images.unsplash.com/photo-1572375992501-4b0892d50c69?auto=format&fit=crop&w=300&q=80', 'desc' => 'High quality die-cut vinyl stickers and labels for all surfaces.'],
            ['name' => 'Drinkware', 'img' => 'https://images.unsplash.com/photo-1514432324607-a09d9b4aefdd?auto=format&fit=crop&w=300&q=80', 'desc' => 'Custom stainless steel tumblers, ceramic mugs, and water bottles.'],
            ['name' => 'Pen', 'img' => 'https://images.unsplash.com/photo-1583485088034-697b5bc54ccd?auto=format&fit=crop&w=300&q=80', 'desc' => 'Branded metal and plastic ballpoint pens for office and promo giveaways.'],
            ['name' => 'T-Shirt', 'img' => 'https://images.unsplash.com/photo-1521572267360-ee0c2909d518?auto=format&fit=crop&w=300&q=80', 'desc' => 'Custom screen printed and embroidered cotton T-shirts for events.'],
            ['name' => 'Keychain', 'img' => 'https://images.unsplash.com/photo-1606760227091-3dd850d492a6?auto=format&fit=crop&w=300&q=80', 'desc' => 'Custom metal, leather, and acrylic keychains with personalized design.'],
            ['name' => 'Buttons', 'img' => 'https://images.unsplash.com/photo-1534447677768-be436bb09401?auto=format&fit=crop&w=300&q=80', 'desc' => 'Custom pin-back button badges for campaigns and corporate events.'],
            ['name' => 'Banners', 'img' => 'https://images.unsplash.com/photo-1508873696983-2df515122519?auto=format&fit=crop&w=300&q=80', 'desc' => 'Heavy-duty vinyl banners, retractable pop-up banners, and flags.'],
            ['name' => 'Tattoos', 'img' => 'https://images.unsplash.com/photo-1562962230-16e4623d36e6?auto=format&fit=crop&w=300&q=80', 'desc' => 'Temporary metallic and full-color custom body tattoos.'],
            ['name' => 'Flag', 'img' => 'https://images.unsplash.com/photo-1508873696983-2df515122519?auto=format&fit=crop&w=300&q=80', 'desc' => 'Custom feather flags, teardrop flags, and outdoor event banners.'],
            ['name' => 'Cap', 'img' => 'https://images.unsplash.com/photo-1588850561407-ed78c282e89b?auto=format&fit=crop&w=300&q=80', 'desc' => 'Embroidered baseball caps, trucker hats, and beanies.'],
            ['name' => 'Note Book', 'img' => 'https://images.unsplash.com/photo-1544716278-ca5e3f4abd8c?auto=format&fit=crop&w=300&q=80', 'desc' => 'Custom hardbound notebooks and journals with embossed logo.'],
            ['name' => 'Jerseys', 'img' => 'https://images.unsplash.com/photo-1577219491135-ce391730fb2c?auto=format&fit=crop&w=300&q=80', 'desc' => 'Sublimated sports team jerseys and athletic activewear.']
        ];
    }

    public function enqueue_assets() {
        wp_enqueue_style('evonee-modal-css', EVONEE_PLUGIN_URL . 'assets/css/quote-modal.css', [], EVONEE_VERSION);
        wp_enqueue_style('evonee-landing-css', EVONEE_PLUGIN_URL . 'assets/css/landing.css', [], EVONEE_VERSION);

        $settings = Evonee_Quote_Admin::get_settings();
        $recaptcha_site_key = (!empty($settings['enable_recaptcha']) && $settings['enable_recaptcha'] === '1' && !empty($settings['recaptcha_site_key'])) ? $settings['recaptcha_site_key'] : '';

        if (!empty($recaptcha_site_key)) {
            wp_enqueue_script('google-recaptcha-v3', 'https://www.google.com/recaptcha/api.js?render=' . esc_attr($recaptcha_site_key), [], EVONEE_VERSION, true);
        }

        wp_enqueue_script('evonee-modal-js', EVONEE_PLUGIN_URL . 'assets/js/quote-modal.js', [], EVONEE_VERSION, true);

        wp_localize_script('evonee-modal-js', 'eqQuoteData', [
            'ajaxUrl'          => admin_url('admin-ajax.php'),
            'nonce'            => wp_create_nonce('eq_submit_quote'),
            'recaptchaSiteKey' => $recaptcha_site_key
        ]);
    }

    /**
     * Helper to render quote trigger button
     */
    public static function quote_button($product_name = '', $product_image = '', $product_desc = '', $btn_text = 'Get Quote', $class = 'eq-btn-primary') {
        $product_attr = esc_attr($product_name);
        $image_attr   = esc_url($product_image);
        $desc_attr    = esc_attr($product_desc);

        return sprintf(
            '<button type="button" class="eq-trigger %s" data-product="%s" data-image="%s" data-description="%s">
                %s <span class="eq-arrow">&rarr;</span>
            </button>',
            esc_attr($class),
            $product_attr,
            $image_attr,
            $desc_attr,
            esc_html($btn_text)
        );
    }

    public function shortcode_quote_button($atts) {
        $atts = shortcode_atts([
            'product'     => '',
            'image'       => '',
            'description' => '',
            'text'        => 'Get Quote',
            'class'       => 'eq-btn-primary'
        ], $atts);

        return self::quote_button($atts['product'], $atts['image'], $atts['description'], $atts['text'], $atts['class']);
    }

    /**
     * Render the global Get Quote Modal HTML
     */
    public function render_modal() {
        // Load plugin settings for conditional field rendering
        $settings = Evonee_Quote_Admin::get_settings();
        ?>
        <div id="eq-quote-modal" class="eq-modal" aria-hidden="true">
            <div class="eq-modal-overlay"></div>
            
            <div class="eq-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="eq-modal-title">
                
                <!-- Close Button -->
                <button type="button" class="eq-modal-close" aria-label="Close modal">&times;</button>

                <!-- Multi-step Modal Progress Bar (Phase 4.4) -->
                <div class="eq-progress-bar-wrap" style="background:#e9d5ff; height:4px; width:100%; border-radius:4px 4px 0 0; overflow:hidden;">
                    <div id="eq-progress-fill" style="background:linear-gradient(90deg, #6d28d9, #9333ea); height:100%; width:20%; transition:width 0.3s ease;"></div>
                </div>

                <!-- Modal Header Bar -->
                <div class="eq-modal-header">
                    <div class="eq-header-left">
                        <h2 id="eq-modal-title" class="eq-title">Get Free Quote</h2>
                        <p class="eq-subtitle">Fill out the form below and our team will get back to you with a custom quote and digital proof <strong>within 24 hours.</strong></p>
                        
                        <!-- Social Proof Counter (Phase 4.5) -->
                        <?php
                        global $wpdb;
                        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                        $today_cnt = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}eq_quote_submissions WHERE DATE(created_at) = %s", current_time('Y-m-d')));
                        ?>
                        <div style="margin-top:6px; display:inline-flex; align-items:center; gap:6px; background:#faf5ff; border:1px solid #e9d5ff; padding:3px 10px; border-radius:20px; font-size:11.5px; color:#6d28d9; font-weight:700;">
                            <span style="display:inline-block; width:6px; height:6px; background:#16a34a; border-radius:50%;"></span>
                            ⚡ <?php echo esc_html(($today_cnt > 0) ? $today_cnt : wp_rand(5, 15)); ?> quotes requested today!
                        </div>
                    </div>

                    <div class="eq-header-badges">
                        <div class="eq-badge-item">
                            <div class="eq-badge-icon">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="6"/><path d="M15.477 12.89 17 22l-5-3-5 3 1.523-9.11"/></svg>
                            </div>
                            <div class="eq-badge-text">
                                <strong>Free Digital Proof</strong>
                                <span>With Every Quote</span>
                            </div>
                        </div>

                        <div class="eq-badge-item">
                            <div class="eq-badge-icon">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 17h4V5H2v12h3m10 0h4l3-3V9h-7v8zm-5 0a2 2 0 1 1-4 0 2 2 0 0 1 4 0zm10 0a2 2 0 1 1-4 0 2 2 0 0 1 4 0z"/></svg>
                            </div>
                            <div class="eq-badge-text">
                                <strong>Fast Production</strong>
                                <span>4-7 Business Days</span>
                            </div>
                        </div>

                        <div class="eq-badge-item">
                            <div class="eq-badge-icon">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg>
                            </div>
                            <div class="eq-badge-text">
                                <strong>Satisfaction</strong>
                                <span>100% Guaranteed</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Modal Body (Two-Column Layout) -->
                <div class="eq-modal-body">
                    
                    <!-- Left Column: Multi-Section Form -->
                    <div class="eq-form-container">
                        <div id="eq-alert-box" class="eq-alert" style="display:none;"></div>

                        <form id="eq-quote-form" method="post" enctype="multipart/form-data" novalidate>
                            
                            <?php wp_nonce_field('eq_submit_quote', 'eq_nonce'); ?>
                            <!-- Honeypot -->
                            <input type="text" name="eq_website" class="eq-honeypot" tabindex="-1" autocomplete="off">
                            <!-- Open Time -->
                            <input type="hidden" name="eq_open_time" id="eq-open-time" value="">

                            <!-- SECTION 1: Contact Information -->
                            <div class="eq-section">
                                <div class="eq-section-header">
                                    <span class="eq-step-num">1</span>
                                    <h3>Contact Information</h3>
                                </div>
                                <div class="eq-grid eq-grid-2">
                                    <div class="eq-field">
                                        <label for="eq-full-name">Full Name <span class="eq-req">*</span></label>
                                        <input type="text" id="eq-full-name" name="full_name" placeholder="Enter your full name" required>
                                        <span class="eq-error-text"></span>
                                    </div>
                                    <?php if (!isset($settings['show_field_company']) || $settings['show_field_company'] === '1'): ?>
                                        <div class="eq-field">
                                            <label for="eq-company">Company / Organization</label>
                                            <input type="text" id="eq-company" name="company" placeholder="Your company or organization name">
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="eq-grid eq-grid-2">
                                    <div class="eq-field">
                                        <label for="eq-email">Email Address <span class="eq-req">*</span></label>
                                        <input type="email" id="eq-email" name="email" placeholder="Enter your email" required>
                                        <span class="eq-error-text"></span>
                                    </div>
                                    <div class="eq-field">
                                        <label for="eq-phone">Phone / WhatsApp <span class="eq-req">*</span></label>
                                        <div class="eq-phone-group">
                                            <select name="phone_prefix" class="eq-phone-prefix" id="eq-phone-prefix">
                                                <option value="+880" selected>🇧🇩 +880 (BD)</option>
                                                <option value="+1">🇺🇸 +1 (US/CA)</option>
                                                <option value="+44">🇬🇧 +44 (UK)</option>
                                                <option value="+61">🇦🇺 +61 (AU)</option>
                                                <option value="+971">🇦🇪 +971 (UAE)</option>
                                                <option value="+966">🇸🇦 +966 (KSA)</option>
                                                <option value="+91">🇮🇳 +91 (IN)</option>
                                                <option value="+65">🇸🇬 +65 (SG)</option>
                                                <option value="+60">🇲🇾 +60 (MY)</option>
                                                <option value="+49">🇩🇪 +49 (DE)</option>
                                                <option value="+33">🇫🇷 +33 (FR)</option>
                                                <option value="+39">🇮🇹 +39 (IT)</option>
                                                <option value="+34">🇪🇸 +34 (ES)</option>
                                                <option value="+31">🇳🇱 +31 (NL)</option>
                                                <option value="+41">🇨🇭 +41 (CH)</option>
                                                <option value="+46">🇸🇪 +46 (SE)</option>
                                                <option value="+47">🇳🇴 +47 (NO)</option>
                                                <option value="+45">🇩🇰 +45 (DK)</option>
                                                <option value="+358">🇫🇮 +358 (FI)</option>
                                                <option value="+353">🇮🇪 +353 (IE)</option>
                                                <option value="+351">🇵🇹 +351 (PT)</option>
                                                <option value="+30">🇬🇷 +30 (GR)</option>
                                                <option value="+48">🇵🇱 +48 (PL)</option>
                                                <option value="+420">🇨🇿 +420 (CZ)</option>
                                                <option value="+43">🇦🇹 +43 (AT)</option>
                                                <option value="+32">🇧🇪 +32 (BE)</option>
                                                <option value="+81">🇯🇵 +81 (JP)</option>
                                                <option value="+82">🇰🇷 +82 (KR)</option>
                                                <option value="+86">🇨🇳 +86 (CN)</option>
                                                <option value="+852">🇭🇰 +852 (HK)</option>
                                                <option value="+886">🇹🇼 +886 (TW)</option>
                                                <option value="+62">🇮🇩 +62 (ID)</option>
                                                <option value="+66">🇹🇭 +66 (TH)</option>
                                                <option value="+63">🇵🇭 +63 (PH)</option>
                                                <option value="+84">🇻🇳 +84 (VN)</option>
                                                <option value="+92">🇵🇰 +92 (PK)</option>
                                                <option value="+94">🇱🇰 +94 (LK)</option>
                                                <option value="+977">🇳🇵 +977 (NP)</option>
                                                <option value="+974">🇶🇦 +974 (QA)</option>
                                                <option value="+965">🇰🇼 +965 (KW)</option>
                                                <option value="+968">🇴🇲 +968 (OM)</option>
                                                <option value="+973">🇧🇭 +973 (BH)</option>
                                                <option value="+962">🇯🇴 +962 (JO)</option>
                                                <option value="+961">🇱🇧 +961 (LB)</option>
                                                <option value="+20">🇪🇬 +20 (EG)</option>
                                                <option value="+27">🇿🇦 +27 (ZA)</option>
                                                <option value="+234">🇳🇬 +234 (NG)</option>
                                                <option value="+254">🇰🇪 +254 (KE)</option>
                                                <option value="+55">🇧🇷 +55 (BR)</option>
                                                <option value="+52">🇲🇽 +52 (MX)</option>
                                                <option value="+54">🇦🇷 +54 (AR)</option>
                                                <option value="+56">🇨🇱 +56 (CL)</option>
                                                <option value="+57">🇨🇴 +57 (CO)</option>
                                                <option value="+51">🇵🇪 +51 (PE)</option>
                                                <option value="+64">🇳🇿 +64 (NZ)</option>
                                                <option value="+380">🇺🇦 +380 (UA)</option>
                                                <option value="+7">🇷🇺 +7 (RU)</option>
                                                <option value="+">🌐 Other (+)</option>
                                            </select>
                                            <input type="tel" id="eq-phone" name="phone" placeholder="(555) 123-4567" required>
                                        </div>
                                        <span class="eq-error-text"></span>
                                    </div>
                                </div>
                                <div class="eq-field">
                                    <label for="eq-country">Country <span class="eq-req">*</span></label>
                                    <select id="eq-country" name="country" required>
                                        <option value="" disabled selected>Select your country</option>
                                        <option value="Bangladesh">Bangladesh</option>
                                        <option value="United States">United States</option>
                                        <option value="United Kingdom">United Kingdom</option>
                                        <option value="Canada">Canada</option>
                                        <option value="Australia">Australia</option>
                                        <option value="United Arab Emirates">United Arab Emirates</option>
                                        <option value="Germany">Germany</option>
                                        <option value="France">France</option>
                                        <option value="Other">Other</option>
                                    </select>
                                    <span class="eq-error-text"></span>
                                </div>
                                <div class="eq-field" id="eq-other-country-wrap" style="display:none; margin-top: 8px;">
                                    <label for="eq-other-country">Specify Country Name <span class="eq-req">*</span></label>
                                    <input type="text" id="eq-other-country" name="other_country" placeholder="Enter your country name">
                                    <span class="eq-error-text"></span>
                                </div>
                            </div>

                            <!-- SECTION 2: Product Information -->
                            <div class="eq-section">
                                <div class="eq-section-header">
                                    <span class="eq-step-num">2</span>
                                    <h3>Product Information</h3>
                                </div>
                                <div class="eq-field">
                                    <label for="eq-product-input">Product <span class="eq-req">*</span></label>
                                    <input type="text" id="eq-product-input" name="product" value="Silicone Wristband" readonly required>
                                </div>
                                <div class="eq-grid eq-grid-2">
                                    <div class="eq-field">
                                        <label for="eq-quantity">Quantity <span class="eq-req">*</span></label>
                                        <select id="eq-quantity" name="quantity" required>
                                            <option value="" disabled selected>Select quantity</option>
                                            <option value="50">50 pcs</option>
                                            <option value="100">100 pcs</option>
                                            <option value="250">250 pcs</option>
                                            <option value="500">500 pcs</option>
                                            <option value="1000">1,000 pcs</option>
                                            <option value="2500">2,500 pcs</option>
                                            <option value="5000+">5,000+ pcs</option>
                                            <option value="other">Other</option>
                                        </select>
                                        <span class="eq-error-text"></span>
                                    </div>
                                    <div class="eq-field" id="eq-other-quantity-wrap" style="display:none;">
                                        <label for="eq-other-quantity">Other Quantity</label>
                                        <input type="number" id="eq-other-quantity" name="other_quantity" placeholder="Enter quantity" min="1">
                                    </div>
                                </div>
                            </div>

                            <!-- SECTION 3: Product Details -->
                            <div class="eq-section">
                                <div class="eq-section-header">
                                    <span class="eq-step-num">3</span>
                                    <h3>Product Details</h3>
                                </div>
                                <div class="eq-grid eq-grid-2">
                                    <div class="eq-field">
                                        <label for="eq-wristband-type">Wristband Type <span class="eq-req">*</span></label>
                                        <select id="eq-wristband-type" name="wristband_type" required>
                                            <option value="" disabled selected>Select type</option>
                                            <option value="Debossed">Debossed</option>
                                            <option value="Embossed">Embossed</option>
                                            <option value="Printed">Printed</option>
                                            <option value="Ink-Filled">Ink-Filled Debossed</option>
                                            <option value="Color Coated">Color Coated</option>
                                        </select>
                                    </div>
                                    <div class="eq-field">
                                        <label for="eq-size">Size <span class="eq-req">*</span></label>
                                        <select id="eq-size" name="size" required>
                                            <option value="" disabled selected>Select size</option>
                                            <option value="Adult (8.00 in / 202 mm)">Adult (8.00 in / 202 mm)</option>
                                            <option value="Youth (7.00 in / 180 mm)">Youth (7.00 in / 180 mm)</option>
                                            <option value="Toddler (6.00 in / 150 mm)">Toddler (6.00 in / 150 mm)</option>
                                            <option value="Custom Size">Custom Size</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="eq-grid eq-grid-2">
                                    <div class="eq-field">
                                        <label for="eq-color">Color <span class="eq-req">*</span></label>
                                        <div class="eq-color-picker-wrap">
                                            <input type="color" id="eq-color-swatch" value="#6d28d9">
                                            <input type="text" id="eq-color" name="color" placeholder="Select or describe color" required>
                                        </div>
                                    </div>
                                    <div class="eq-field">
                                        <label>Do you have a PMS color?</label>
                                        <div class="eq-radio-group">
                                            <label class="eq-radio"><input type="radio" name="has_pms_color" value="Yes"> <span>Yes</span></label>
                                            <label class="eq-radio"><input type="radio" name="has_pms_color" value="No"> <span>No</span></label>
                                            <label class="eq-radio"><input type="radio" name="has_pms_color" value="Not sure" checked> <span>Not sure</span></label>
                                        </div>
                                    </div>
                                </div>

                                 <?php if (!isset($settings['show_field_text_specs']) || $settings['show_field_text_specs'] === '1'): ?>
                                    <div class="eq-grid eq-grid-2">
                                        <div class="eq-field">
                                            <label for="eq-debossed-text">Debossed / Embossed Text (if any)</label>
                                            <input type="text" id="eq-debossed-text" name="debossed_text" placeholder="Enter text you want on the wristband">
                                        </div>
                                        <div class="eq-field">
                                            <label for="eq-text-color">Text Color (If different)</label>
                                            <input type="text" id="eq-text-color" name="text_color" placeholder="Select or describe text color">
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- SECTION 4: Artwork Upload -->
                            <div class="eq-section">
                                <div class="eq-section-header">
                                    <span class="eq-step-num">4</span>
                                    <h3>Artwork Upload</h3>
                                </div>
                                <div class="eq-dropzone" id="eq-dropzone">
                                    <input type="file" id="eq-artwork" name="eq_artwork[]" multiple accept=".ai,.pdf,.eps,.svg,.png,.jpg,.jpeg">
                                    <div class="eq-dropzone-content">
                                        <div class="eq-upload-icon">
                                            <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="#6d28d9" stroke-width="1.8"><path d="M4 14.899A7 7 0 1 1 15.71 8h1.79a4.5 4.5 0 0 1 2.5 8.242"/><path d="M12 12v9"/><path d="m16 16-4-4-4 4"/></svg>
                                        </div>
                                        <strong>Upload Your Artwork / Logo (Up to 3 Files)</strong>
                                        <span>Click to upload or drag and drop</span>
                                        <small>AI, PDF, EPS, SVG, PNG, JPG (Max 20MB per file)</small>
                                        <div id="eq-file-preview" class="eq-file-preview" style="display:none;"></div>
                                    </div>
                                </div>
                                <span class="eq-error-text" id="eq-artwork-error"></span>

                                <div class="eq-checkbox-wrap">
                                    <label class="eq-checkbox">
                                        <input type="checkbox" id="eq-no-artwork" name="no_artwork" value="1">
                                        <span>I don't have print-ready artwork. Please help me with the design.</span>
                                    </label>
                                    <div id="eq-design-help-note" class="eq-help-note" style="display:none;">
                                        💡 No problem! Our professional graphic design team will prepare a free digital mockup for you.
                                    </div>
                                </div>
                            </div>

                            <!-- SECTION 5: Delivery Information -->
                            <div class="eq-section">
                                <div class="eq-section-header">
                                    <span class="eq-step-num">5</span>
                                    <h3>Delivery Information</h3>
                                </div>
                                <div class="eq-grid eq-grid-2">
                                    <div class="eq-field">
                                        <label for="eq-timeframe">When do you need your products? <span class="eq-req">*</span></label>
                                        <select id="eq-timeframe" name="timeframe" required>
                                            <option value="" disabled selected>Select timeframe</option>
                                            <option value="Standard (7-10 Business Days)">Standard (7-10 Business Days)</option>
                                            <option value="Rush (4-7 Business Days)">Rush (4-7 Business Days)</option>
                                            <option value="Super Rush (2-3 Business Days)">Super Rush (2-3 Business Days)</option>
                                            <option value="Flexible">Flexible</option>
                                        </select>
                                        <span class="eq-error-text"></span>
                                    </div>
                                    <?php if (!isset($settings['show_field_specific_date']) || $settings['show_field_specific_date'] === '1'): ?>
                                        <div class="eq-field">
                                            <label for="eq-specific-date">Need by specific date? (Optional)</label>
                                            <input type="date" id="eq-specific-date" name="specific_date">
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="eq-field">
                                    <label for="eq-zip-code">Delivery ZIP / Postal Code <span class="eq-req">*</span></label>
                                    <input type="text" id="eq-zip-code" name="zip_code" placeholder="Enter ZIP / Postal Code" required>
                                    <span class="eq-error-text"></span>
                                </div>
                            </div>

                            <!-- SECTION 6: Additional Information -->
                            <div class="eq-section">
                                <div class="eq-section-header">
                                    <span class="eq-step-num">6</span>
                                    <h3>Additional Information</h3>
                                </div>
                                <?php if (!isset($settings['show_field_project_notes']) || $settings['show_field_project_notes'] === '1'): ?>
                                    <div class="eq-field">
                                        <label for="eq-project-notes">Tell us about your project</label>
                                        <textarea id="eq-project-notes" name="project_notes" rows="3" placeholder="Please describe your project, event, special requirements, packaging, etc."></textarea>
                                    </div>
                                <?php endif; ?>

                                <?php 
                                $custom_builder_fields = get_option('evonee_quote_custom_fields', []);
                                if (!empty($custom_builder_fields) && is_array($custom_builder_fields)):
                                    foreach ($custom_builder_fields as $cf):
                                        $f_name = 'custom_field_' . sanitize_title($cf['label']);
                                        $is_req = !empty($cf['required']);
                                        $req_attr = $is_req ? 'required' : '';
                                        $star = $is_req ? ' <span class="eq-req">*</span>' : '';
                                ?>
                                        <div class="eq-field">
                                            <label for="<?php echo esc_attr($f_name); ?>"><?php echo esc_html($cf['label']); ?><?php echo wp_kses_post($star); ?></label>
                                            <?php if ($cf['type'] === 'select'): 
                                                $opts = array_map('trim', explode(',', $cf['options']));
                                            ?>
                                                <select id="<?php echo esc_attr($f_name); ?>" name="<?php echo esc_attr($f_name); ?>" <?php echo esc_attr($req_attr); ?>>
                                                    <option value="" disabled selected>Select <?php echo esc_html($cf['label']); ?></option>
                                                    <?php foreach ($opts as $opt): ?>
                                                        <option value="<?php echo esc_attr($opt); ?>"><?php echo esc_html($opt); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            <?php elseif ($cf['type'] === 'textarea'): ?>
                                                <textarea id="<?php echo esc_attr($f_name); ?>" name="<?php echo esc_attr($f_name); ?>" rows="2" placeholder="Enter <?php echo esc_attr($cf['label']); ?>" <?php echo esc_attr($req_attr); ?>></textarea>
                                            <?php elseif ($cf['type'] === 'number'): ?>
                                                <input type="number" id="<?php echo esc_attr($f_name); ?>" name="<?php echo esc_attr($f_name); ?>" placeholder="Enter <?php echo esc_attr($cf['label']); ?>" <?php echo esc_attr($req_attr); ?>>
                                            <?php else: ?>
                                                <input type="text" id="<?php echo esc_attr($f_name); ?>" name="<?php echo esc_attr($f_name); ?>" placeholder="Enter <?php echo esc_attr($cf['label']); ?>" <?php echo esc_attr($req_attr); ?>>
                                            <?php endif; ?>
                                        </div>
                                <?php 
                                    endforeach;
                                endif;
                                ?>

                                <?php do_action('evonee_quote_form_custom_fields'); ?>

                                <div class="eq-checkbox-wrap">
                                    <label class="eq-checkbox">
                                        <input type="checkbox" id="eq-consent" name="consent" value="1" required>
                                        <span>I agree that Evonee may contact me regarding this quote.</span>
                                    </label>
                                    <span class="eq-error-text" id="eq-consent-error"></span>
                                </div>
                            </div>

                            <!-- Submit Button -->
                            <div class="eq-submit-wrap">
                                <button type="submit" id="eq-submit-btn" class="eq-btn-submit">
                                    <span>Get Free Quote</span>
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                                </button>
                                <div class="eq-secure-note">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                                    Your information is secure and will not be shared.
                                </div>
                            </div>

                        </form>
                    </div>

                    <!-- Right Sidebar Cards -->
                    <div class="eq-sidebar">
                        
                        <!-- Card 1: Selected Product -->
                        <div class="eq-card eq-card-product">
                            <h4 class="eq-card-title">Selected Product</h4>
                            <div class="eq-product-preview">
                                <div class="eq-product-img-wrap">
                                    <img id="eq-preview-img" src="<?php echo esc_url(EVONEE_PLUGIN_URL . 'assets/images/wristband.png'); ?>" alt="Selected Product" onerror="this.src='https://placehold.co/120x80/6d28d9/ffffff?text=Product'">
                                </div>
                                <div class="eq-product-info">
                                    <h5 id="eq-preview-title">Silicone Wristband</h5>
                                    <p id="eq-preview-desc">High-quality custom silicone wristbands for events, organizations, businesses, schools and more.</p>
                                </div>
                            </div>
                        </div>

                        <?php
                        $settings = Evonee_Quote_Admin::get_settings();
                        $show_calc = isset($settings['enable_price_calc']) && $settings['enable_price_calc'] === '1';
                        $curr_sym  = !empty($settings['currency_symbol']) ? esc_html($settings['currency_symbol']) : '$';
                        ?>
                        <!-- Live Price Estimator Card -->
                        <div class="eq-card eq-card-calculator" id="eq-calc-card" data-currency="<?php echo esc_attr($curr_sym); ?>" style="<?php echo $show_calc ? '' : 'display:none !important;'; ?>">
                            <div class="eq-calc-header">
                                <h4 class="eq-card-title">💡 Estimated Price Range</h4>
                                <span class="eq-live-badge">Live Estimate</span>
                            </div>
                            <div class="eq-calc-body">
                                <div class="eq-price-row">
                                    <span class="eq-price-label">Unit Price:</span>
                                    <span id="eq-unit-price" class="eq-price-val"><?php echo esc_html($curr_sym); ?>0.65 / pc</span>
                                </div>
                                <div class="eq-price-row eq-price-total-row">
                                    <span class="eq-price-label">Estimated Total:</span>
                                    <span id="eq-total-price" class="eq-price-total-val"><?php echo esc_html($curr_sym); ?>65.00</span>
                                </div>
                                <div class="eq-price-note">
                                    <small>*Final quote confirmed within 24 hours after proof approval.</small>
                                </div>
                            </div>
                        </div>

                        <!-- Card 2: Popular Options -->
                        <div class="eq-card">
                            <h4 class="eq-card-title">Popular Options</h4>
                            <ul class="eq-checklist">
                                <li><svg class="eq-check-icon" viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5"/></svg> Debossed, Embossed & Printed</li>
                                <li><svg class="eq-check-icon" viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5"/></svg> Multiple sizes: 6", 7", 8" & custom</li>
                                <li><svg class="eq-check-icon" viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5"/></svg> Unlimited color options (PMS match)</li>
                                <li><svg class="eq-check-icon" viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5"/></svg> Low minimum quantity</li>
                                <li><svg class="eq-check-icon" viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5"/></svg> Fast production & worldwide shipping</li>
                            </ul>
                        </div>

                        <!-- Card 3: How It Works -->
                        <div class="eq-card eq-card-works">
                            <h4 class="eq-card-title">How It Works</h4>
                            <div class="eq-steps">
                                <div class="eq-step-item">
                                    <span class="eq-step-badge">1</span>
                                    <div>
                                        <strong>Submit Your Request</strong>
                                        <p>Fill out the form with your requirements and upload artwork.</p>
                                    </div>
                                </div>
                                <div class="eq-step-item">
                                    <span class="eq-step-badge">2</span>
                                    <div>
                                        <strong>Get Quote & Proof</strong>
                                        <p>We will send you a custom quote and digital proof within 24 hours.</p>
                                    </div>
                                </div>
                                <div class="eq-step-item">
                                    <span class="eq-step-badge">3</span>
                                    <div>
                                        <strong>Approve & Pay</strong>
                                        <p>Review the proof, approve the quote and complete payment.</p>
                                    </div>
                                </div>
                                <div class="eq-step-item">
                                    <span class="eq-step-badge">4</span>
                                    <div>
                                        <strong>We Produce & Deliver</strong>
                                        <p>We produce your order and deliver it to your doorstep.</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Card 4: Need Help? -->
                        <div class="eq-card eq-card-help">
                            <h4 class="eq-card-title">Need Help?</h4>
                            <ul class="eq-contact-list">
                                <li>
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                                    <a href="tel:+88001819898893">+880 01819 898893</a>
                                </li>
                                <li>
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                                    <a href="mailto:sales@evonee.com">sales@evonee.com</a>
                                </li>
                                <li>
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                                    <span><strong>Live Chat</strong> (Mon - Fri, 9AM - 6PM EST)</span>
                                </li>
                            </ul>
                        </div>

                        <!-- Card 5: Privacy Safe Banner -->
                        <div class="eq-card eq-card-safe">
                            <div class="eq-safe-header">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg>
                                <strong>Your Information is Safe</strong>
                            </div>
                            <p>We respect your privacy. Your information is secure and <strong>will never be shared with third parties.</strong></p>
                        </div>

                    </div>
                </div>

            </div>

            <!-- WhatsApp Floating Quick Contact Button (Module 8) -->
            <a href="https://wa.me/88001819898893?text=Hi%20Evonee%20Team!%20I%20have%20a%20question%20about%20a%20custom%20quote." target="_blank" class="eq-whatsapp-float" style="position:fixed; bottom:25px; right:25px; z-index:99990; background:#25D366; color:#ffffff; width:52px; height:52px; border-radius:50%; display:flex; align-items:center; justify-content:center; box-shadow:0 4px 14px rgba(37,211,102,0.4); text-decoration:none; transition:transform 0.2s;" onmouseover="this.style.transform='scale(1.1)'" onmouseout="this.style.transform='scale(1)'" aria-label="Chat on WhatsApp">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="currentColor"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/></svg>
            </a>
        </div>
        <?php
    }

    public static function get_svg_placeholder() {
        return 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIxMDAiIGhlaWdodD0iMTAwIiB2aWV3Qm94PSIwIDAgMjQgMjQiIGZpbGw9Im5vbmUiIHN0cm9rZT0iIzZkMjhkOSIgc3Ryb2tlLXdpZHRoPSIxLjUiPjxyZWN0IHg9IjMiIHk9IjMiIHdpZHRoPSIxOCIgaGVpZ2h0PSIxOCIgcng9IjQiLz48cGF0aCBkPSJNOD41IDEwYTEuNSAxLjUgMCAxIDAgMC0zIDEuNSAxLjUgMCAwIDAgMCAzeiIvPjxwYXRoIGQ9Im0yMSAxNS01LTUtMTEgMTEiLz48L3N2Zz4=';
    }

    /**
     * Render Popular Products Grid with Customizable Columns & Responsive Controls
     */
    public function render_products_grid($atts = []) {
        $atts = shortcode_atts([
            'title'       => 'Popular Products',
            'show_title'  => 'yes',
            'cols'        => 6,
            'columns'     => 6,
            'cols_tablet' => 3,
            'cols_mobile' => 2,
            'gap'         => '18px',
            'img_height'  => '140px',
            'img_fit'     => 'cover',
            'limit'       => -1,
        ], $atts);

        // Allow 'columns' or 'cols'
        $cols_desktop = (!empty($atts['cols']) && $atts['cols'] != 6) ? intval($atts['cols']) : intval($atts['columns']);
        $cols_tablet  = intval($atts['cols_tablet']);
        $cols_mobile  = intval($atts['cols_mobile']);
        $gap          = esc_attr($atts['gap']);
        $img_height   = esc_attr($atts['img_height']);
        $img_fit      = esc_attr($atts['img_fit']);

        $products = self::get_products();

        // Limit product count if specified
        $limit = intval($atts['limit']);
        if ($limit > 0 && count($products) > $limit) {
            $products = array_slice($products, 0, $limit);
        }

        $placeholder = self::get_svg_placeholder();

        ob_start();
        ?>
        <div class="evonee-landing evonee-grid-only">
            <section class="el-products-section" style="padding: 20px 0;">
                <div class="el-container">
                    <?php if ($atts['show_title'] === 'yes' && !empty($atts['title'])): ?>
                        <h2 class="el-section-title"><?php echo esc_html($atts['title']); ?></h2>
                        <div class="el-title-line"></div>
                    <?php endif; ?>

                    <div class="el-grid" style="--eq-cols-desktop: <?php echo esc_attr($cols_desktop); ?>; --eq-cols-tablet: <?php echo esc_attr($cols_tablet); ?>; --eq-cols-mobile: <?php echo esc_attr($cols_mobile); ?>; --eq-grid-gap: <?php echo esc_attr($gap); ?>; --eq-img-height: <?php echo esc_attr($img_height); ?>; --eq-img-fit: <?php echo esc_attr($img_fit); ?>;">
                        <?php foreach ($products as $p): 
                            $img_src = !empty($p['img']) ? esc_url($p['img']) : $placeholder;
                        ?>
                            <div class="el-card eq-trigger" 
                                 data-product="<?php echo esc_attr($p['name']); ?>" 
                                 data-image="<?php echo esc_url($img_src); ?>" 
                                 data-description="<?php echo esc_attr($p['desc']); ?>"
                                 role="button" 
                                 tabindex="0"
                                 title="Click to get quote for <?php echo esc_attr($p['name']); ?>">
                                <div class="el-card-img">
                                    <img src="<?php echo esc_url($img_src); ?>" alt="<?php echo esc_attr($p['name']); ?>" loading="lazy" onerror="this.onerror=null;this.src='<?php echo esc_url($placeholder); ?>';">
                                </div>
                                <h3 class="el-card-title"><?php echo esc_html($p['name']); ?></h3>
                                <div class="el-card-action">
                                    <span class="el-btn-card">
                                        Get Quote <span class="eq-arrow">&rarr;</span>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Optional full demo landing page renderer
     */
    public function render_full_landing_page() {
        ob_start();
        ?>
        <div class="evonee-landing">
            
            <!-- Top Header Bar -->
            <div class="el-topbar">
                <div class="el-container">
                    <div class="el-topbar-right">
                        <span>📞 +880 01819 898893</span>
                        <span>✉️ sales@evonee.com</span>
                    </div>
                </div>
            </div>

            <!-- Navbar -->
            <header class="el-navbar">
                <div class="el-container el-nav-flex">
                    <a href="#" class="el-logo">
                        <span class="el-logo-icon">e.</span>
                        <span class="el-logo-text">evonee</span>
                    </a>

                    <nav class="el-nav-menu">
                        <a href="#" class="el-active">Home</a>
                        <a href="#">Products ▾</a>
                        <a href="#">How It Works</a>
                        <a href="#">About Us</a>
                        <a href="#">Contact Us</a>
                    </nav>

                    <div class="el-nav-cta">
                        <?php echo wp_kses_post(self::quote_button('', '', '', 'Get Free Quote', 'el-btn-orange')); ?>
                    </div>
                </div>
            </header>

            <!-- Hero Section -->
            <section class="el-hero">
                <div class="el-container el-hero-flex">
                    <div class="el-hero-images">
                        <div class="el-hero-stack">
                            <img src="https://images.unsplash.com/photo-1542291026-7eec264c27ff?auto=format&fit=crop&w=500&q=80" alt="Wristband & Merch" class="el-hero-img">
                        </div>
                    </div>

                    <div class="el-hero-content">
                        <h1>Bring Your Brand to Life</h1>
                        <p class="el-hero-sub">Premium Promotional Products for Events, Businesses & Organizations</p>

                        <div class="el-hero-badges">
                            <div class="el-hbadge">
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="6"/><path d="M15.477 12.89 17 22l-5-3-5 3 1.523-9.11"/></svg>
                                <span>Premium Quality</span>
                            </div>
                            <div class="el-hbadge">
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                                <span>Custom Design</span>
                            </div>
                            <div class="el-hbadge">
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
                                <span>Worldwide Shipping</span>
                            </div>
                        </div>

                        <div class="el-hero-cta-wrap">
                            <?php echo wp_kses_post(self::quote_button('', '', '', 'Get Free Quote', 'el-btn-hero')); ?>
                            <span class="el-hero-subtext">Fast Response • Free Mockup • No Obligation</span>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Popular Products Grid -->
            <?php echo wp_kses_post($this->render_products_grid()); ?>

            <!-- "Can't find what you need?" Banner Section -->
            <section class="el-custom-banner-wrap">
                <div class="el-container">
                    <div class="el-custom-banner">
                        <div class="el-cb-content">
                            <div class="el-cb-icon">
                                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#6d28d9" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                            </div>
                            <div>
                                <h3>Can't find what you need?</h3>
                                <p>Tell us your idea. We'll make it happen.</p>
                            </div>
                        </div>
                        <div class="el-cb-action">
                            <?php echo wp_kses_post(self::quote_button('', '', '', 'Get Free Quote', 'el-btn-dark')); ?>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Bottom How It Works Row -->
            <section class="el-how-row">
                <div class="el-container">
                    <div class="el-how-grid">
                        <div class="el-how-label">
                            <h3>How It Works</h3>
                        </div>
                        <div class="el-how-item">
                            <div class="el-how-step-num">1</div>
                            <div>
                                <strong>Send Your Request</strong>
                                <p>Fill out the form with your product details and upload your logo.</p>
                            </div>
                        </div>
                        <span class="el-how-arrow">&rarr;</span>
                        <div class="el-how-item">
                            <div class="el-how-step-num">2</div>
                            <div>
                                <strong>Get Free Mockup</strong>
                                <p>We prepare a free digital mockup and best quote for you.</p>
                            </div>
                        </div>
                        <span class="el-how-arrow">&rarr;</span>
                        <div class="el-how-item">
                            <div class="el-how-step-num">3</div>
                            <div>
                                <strong>Approve & Pay</strong>
                                <p>Review and approve the mockup, then make payment.</p>
                            </div>
                        </div>
                        <span class="el-how-arrow">&rarr;</span>
                        <div class="el-how-item">
                            <div class="el-how-step-num">4</div>
                            <div>
                                <strong>We Produce & Deliver</strong>
                                <p>We produce your order and deliver it to your doorstep.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Footer Section -->
            <footer class="el-footer">
                <div class="el-container el-footer-grid">
                    <div class="el-footer-brand">
                        <a href="#" class="el-logo el-logo-light">
                            <span class="el-logo-icon">e.</span>
                            <span class="el-logo-text">evonee</span>
                        </a>
                        <p class="el-tagline">CUSTOM PRODUCTS. MADE SIMPLE.</p>
                        <p class="el-about-text">Your one-stop shop for custom promotional products, offering a wide range of options with premium quality.</p>
                    </div>

                    <div class="el-footer-col">
                        <h4>Quick Links</h4>
                        <ul>
                            <li><a href="#">Home</a></li>
                            <li><a href="#">Products</a></li>
                            <li><a href="#">How It Works</a></li>
                            <li><a href="#">About Us</a></li>
                            <li><a href="#">Contact Us</a></li>
                        </ul>
                    </div>

                    <div class="el-footer-col">
                        <h4>Customer Service</h4>
                        <ul>
                            <li><a href="#">FAQ</a></li>
                            <li><a href="#">Shipping Policy</a></li>
                            <li><a href="#">Returns Policy</a></li>
                            <li><a href="#">Privacy Policy</a></li>
                            <li><a href="#">Terms & Conditions</a></li>
                        </ul>
                    </div>

                    <div class="el-footer-col">
                        <h4>Contact Us</h4>
                        <p>📞 +880 01819 898893</p>
                        <p>✉️ sales@evonee.com</p>
                        <p>📍 Dhaka, Bangladesh</p>
                    </div>
                </div>

                <div class="el-footer-bottom">
                    <div class="el-container el-fb-flex">
                        <p>© <?php echo esc_html(gmdate('Y')); ?> Evonee. All rights reserved.</p>
                        <div class="el-payments">
                            <span>VISA</span>
                            <span>Mastercard</span>
                            <span>AMEX</span>
                            <span>PayPal</span>
                            <span>2checkout</span>
                        </div>
                    </div>
                </div>
            </footer>

        </div>
        <?php
        return ob_get_clean();
    }
}
