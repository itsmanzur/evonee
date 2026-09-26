<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('\Elementor\Widget_Base')) {
    return;
}

class Evonee_Elementor_Quote_Button_Widget extends \Elementor\Widget_Base {

        public function get_name() {
            return 'evonee_quote_button';
        }

        public function get_title() {
            return __('Evonee Quote Button', 'evonee');
        }

        public function get_icon() {
            return 'eicon-button';
        }

        public function get_categories() {
            return ['general'];
        }

        protected function register_controls() {
            $this->start_controls_section(
                'content_section',
                [
                    'label' => __('Quote Button Settings', 'evonee'),
                    'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
                ]
            );

            $this->add_control(
                'product_name',
                [
                    'label'       => __('Product Name', 'evonee'),
                    'type'        => \Elementor\Controls_Manager::TEXT,
                    'default'     => __('Silicone Wristband', 'evonee'),
                    'placeholder' => __('Enter product name', 'evonee'),
                ]
            );

            $this->add_control(
                'button_text',
                [
                    'label'       => __('Button Label', 'evonee'),
                    'type'        => \Elementor\Controls_Manager::TEXT,
                    'default'     => __('Get Quote', 'evonee'),
                    'placeholder' => __('e.g. Get Instant Quote', 'evonee'),
                ]
            );

            $this->add_control(
                'product_description',
                [
                    'label'       => __('Product Description', 'evonee'),
                    'type'        => \Elementor\Controls_Manager::TEXTAREA,
                    'default'     => '',
                    'placeholder' => __('Optional product specs or description', 'evonee'),
                ]
            );

            $this->end_controls_section();
        }

        protected function render() {
            $settings = $this->get_settings_for_display();
            $product  = !empty($settings['product_name']) ? $settings['product_name'] : 'Silicone Wristband';
            $label    = !empty($settings['button_text']) ? $settings['button_text'] : 'Get Quote';
            $desc     = !empty($settings['product_description']) ? $settings['product_description'] : '';

            echo Evonee_Quote_Modal::quote_button($product, '', $desc, $label);
        }
    }

    class Evonee_Elementor_Product_Grid_Widget extends \Elementor\Widget_Base {

        public function get_name() {
            return 'evonee_product_grid';
        }

        public function get_title() {
            return __('Evonee Product Grid', 'evonee');
        }

        public function get_icon() {
            return 'eicon-gallery-grid';
        }

        public function get_categories() {
            return ['general'];
        }

        protected function register_controls() {
            $this->start_controls_section(
                'content_section',
                [
                    'label' => __('Product Grid Settings', 'evonee'),
                    'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
                ]
            );

            $this->add_control(
                'card_style',
                [
                    'label'   => __('Card Design Style', 'evonee'),
                    'type'    => \Elementor\Controls_Manager::SELECT,
                    'default' => 'classic',
                    'options' => [
                        'classic'    => __('1. Classic Soft Pill (Default)', 'evonee'),
                        'floating'   => __('2. Modern Floating (Shadow & Badge)', 'evonee'),
                        'compact'    => __('3. E-commerce Compact (Price Tag)', 'evonee'),
                        'gradient'   => __('4. Gradient Glass (Luxury Tech)', 'evonee'),
                        'horizontal' => __('5. Horizontal Split (List Layout)', 'evonee'),
                        'dark'       => __('6. Dark Sleek Edition', 'evonee'),
                    ],
                ]
            );

            $this->add_control(
                'columns',
                [
                    'label'   => __('Desktop Columns', 'evonee'),
                    'type'    => \Elementor\Controls_Manager::NUMBER,
                    'default' => 6,
                    'min'     => 1,
                    'max'     => 6,
                ]
            );

            $this->add_control(
                'badge',
                [
                    'label'       => __('Badge Tag Label', 'evonee'),
                    'type'        => \Elementor\Controls_Manager::TEXT,
                    'default'     => '',
                    'placeholder' => __('e.g. Popular, Best Seller', 'evonee'),
                ]
            );

            $this->add_control(
                'show_price',
                [
                    'label'        => __('Show Starting Price Tag', 'evonee'),
                    'type'         => \Elementor\Controls_Manager::SWITCHER,
                    'label_on'     => __('Show', 'evonee'),
                    'label_off'    => __('Hide', 'evonee'),
                    'return_value' => '1',
                    'default'      => '',
                ]
            );

            $this->end_controls_section();

            // Query & Sorting Section
            $this->start_controls_section(
                'query_section',
                [
                    'label' => __('Query & Sorting Options', 'evonee'),
                    'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
                ]
            );

            $this->add_control(
                'orderby',
                [
                    'label'   => __('Order By Parameter', 'evonee'),
                    'type'    => \Elementor\Controls_Manager::SELECT,
                    'default' => 'date',
                    'options' => [
                        'date'       => __('1. Date Created (Default)', 'evonee'),
                        'menu_order' => __('2. Menu Order (Custom Admin Order)', 'evonee'),
                        'title'      => __('3. Product Title (A-Z / Z-A)', 'evonee'),
                        'price'      => __('4. Product Price', 'evonee'),
                        'popularity' => __('5. Popularity (Top Sales)', 'evonee'),
                        'rand'       => __('6. Random Shuffle', 'evonee'),
                    ],
                ]
            );

            $this->add_control(
                'order',
                [
                    'label'   => __('Order Direction', 'evonee'),
                    'type'    => \Elementor\Controls_Manager::SELECT,
                    'default' => 'DESC',
                    'options' => [
                        'DESC' => __('Descending (High to Low / Newest First)', 'evonee'),
                        'ASC'  => __('Ascending (Low to High / Oldest First)', 'evonee'),
                    ],
                ]
            );

            $this->add_control(
                'category',
                [
                    'label'       => __('Category Slugs (Optional)', 'evonee'),
                    'type'        => \Elementor\Controls_Manager::TEXT,
                    'default'     => '',
                    'placeholder' => __('e.g. clothing, accessories', 'evonee'),
                ]
            );

            $this->add_control(
                'include_ids',
                [
                    'label'       => __('Specific Product IDs (Optional)', 'evonee'),
                    'type'        => \Elementor\Controls_Manager::TEXT,
                    'default'     => '',
                    'placeholder' => __('e.g. 102, 105, 88', 'evonee'),
                ]
            );

            $this->end_controls_section();

            // Style Tab Controls (Brand Color Customizer)
            $this->start_controls_section(
                'style_section',
                [
                    'label' => __('Brand Colors & Styling', 'evonee'),
                    'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
                ]
            );

            $this->add_control(
                'title_color',
                [
                    'label'     => __('Product Title Color', 'evonee'),
                    'type'      => \Elementor\Controls_Manager::COLOR,
                    'selectors' => [
                        '{{WRAPPER}} .el-card-title' => 'color: {{VALUE}} !important;',
                    ],
                ]
            );

            $this->add_control(
                'title_hover_color',
                [
                    'label'     => __('Product Title Hover Color', 'evonee'),
                    'type'      => \Elementor\Controls_Manager::COLOR,
                    'selectors' => [
                        '{{WRAPPER}} .el-card:hover .el-card-title' => 'color: {{VALUE}} !important;',
                    ],
                ]
            );

            $this->add_control(
                'accent_color',
                [
                    'label'     => __('Top Accent / Hover Border Color', 'evonee'),
                    'type'      => \Elementor\Controls_Manager::COLOR,
                    'selectors' => [
                        '{{WRAPPER}} .el-card::before' => 'background: {{VALUE}} !important;',
                        '{{WRAPPER}} .el-card:hover'  => 'border-color: {{VALUE}} !important;',
                    ],
                ]
            );

            $this->add_control(
                'btn_bg',
                [
                    'label'     => __('Button Normal Background', 'evonee'),
                    'type'      => \Elementor\Controls_Manager::COLOR,
                    'selectors' => [
                        '{{WRAPPER}} .el-btn-card' => 'background: {{VALUE}} !important;',
                    ],
                ]
            );

            $this->add_control(
                'btn_text',
                [
                    'label'     => __('Button Normal Text Color', 'evonee'),
                    'type'      => \Elementor\Controls_Manager::COLOR,
                    'selectors' => [
                        '{{WRAPPER}} .el-btn-card' => 'color: {{VALUE}} !important;',
                    ],
                ]
            );

            $this->add_control(
                'btn_hover_bg',
                [
                    'label'     => __('Button Hover Background', 'evonee'),
                    'type'      => \Elementor\Controls_Manager::COLOR,
                    'selectors' => [
                        '{{WRAPPER}} .el-card:hover .el-btn-card' => 'background: {{VALUE}} !important;',
                    ],
                ]
            );

            $this->add_control(
                'btn_hover_text',
                [
                    'label'     => __('Button Hover Text Color', 'evonee'),
                    'type'      => \Elementor\Controls_Manager::COLOR,
                    'selectors' => [
                        '{{WRAPPER}} .el-card:hover .el-btn-card' => 'color: {{VALUE}} !important;',
                    ],
                ]
            );

            $this->add_control(
                'badge_bg',
                [
                    'label'     => __('Badge Tag Background', 'evonee'),
                    'type'      => \Elementor\Controls_Manager::COLOR,
                    'selectors' => [
                        '{{WRAPPER}} .el-card-badge' => 'background: {{VALUE}} !important;',
                    ],
                ]
            );

            $this->add_control(
                'badge_text_color',
                [
                    'label'     => __('Badge Tag Text Color', 'evonee'),
                    'type'      => \Elementor\Controls_Manager::COLOR,
                    'selectors' => [
                        '{{WRAPPER}} .el-card-badge' => 'color: {{VALUE}} !important;',
                    ],
                ]
            );

            $this->end_controls_section();
        }

        protected function render() {
            $settings   = $this->get_settings_for_display();
            $card_style = !empty($settings['card_style']) ? $settings['card_style'] : 'classic';
            $cols       = !empty($settings['columns']) ? intval($settings['columns']) : 6;
            $badge      = !empty($settings['badge']) ? $settings['badge'] : '';
            $show_price = !empty($settings['show_price']) ? '1' : '0';
            $orderby    = !empty($settings['orderby']) ? $settings['orderby'] : '';
            $order      = !empty($settings['order']) ? $settings['order'] : '';
            $category   = !empty($settings['category']) ? $settings['category'] : '';
            $include    = !empty($settings['include_ids']) ? $settings['include_ids'] : '';

            echo Evonee_Quote_Modal::render_products_grid([
                'card_style' => $card_style,
                'cols'       => $cols,
                'badge'      => $badge,
                'show_price' => $show_price,
                'orderby'    => $orderby,
                'order'      => $order,
                'category'   => $category,
                'include'    => $include,
            ]);
        }
    }
