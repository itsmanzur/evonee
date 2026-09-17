<?php
if (!defined('ABSPATH')) {
    exit;
}

if (class_exists('\Elementor\Widget_Base')) {

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
        }

        protected function render() {
            $settings   = $this->get_settings_for_display();
            $card_style = !empty($settings['card_style']) ? $settings['card_style'] : 'classic';
            $cols       = !empty($settings['columns']) ? intval($settings['columns']) : 6;
            $badge      = !empty($settings['badge']) ? $settings['badge'] : '';
            $show_price = !empty($settings['show_price']) ? '1' : '0';

            echo Evonee_Quote_Modal::render_products_grid([
                'card_style' => $card_style,
                'cols'       => $cols,
                'badge'      => $badge,
                'show_price' => $show_price,
            ]);
        }
    }
}
