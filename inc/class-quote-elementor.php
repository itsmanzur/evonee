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
}
