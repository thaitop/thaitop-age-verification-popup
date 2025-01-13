<?php
if (!defined('ABSPATH')) {
    exit;
}

if (class_exists('WC_Settings_Page', false)) {
    class WC_Settings_Age_Verification extends WC_Settings_Page {

        private $color_templates = array(
            'steam' => array(
                'name' => 'Steam Theme',
                'colors' => array(
                    'background_start' => '#2a475e',
                    'background_end' => '#1b2838',
                    'text' => '#ffffff',
                    'text_secondary' => '#acb2b8',
                    'accent' => '#66c0f4',
                    'button_start' => '#47bfff',
                    'button_end' => '#1a44c2'
                )
            ),
            'dark' => array(
                'name' => 'Dark Theme',
                'colors' => array(
                    'background_start' => '#2d2d2d',
                    'background_end' => '#1a1a1a',
                    'text' => '#ffffff',
                    'text_secondary' => '#cccccc',
                    'accent' => '#ff4444',
                    'button_start' => '#ff4444',
                    'button_end' => '#cc0000'
                )
            ),
            'light' => array(
                'name' => 'Light Theme',
                'colors' => array(
                    'background_start' => '#ffffff',
                    'background_end' => '#f5f5f5',
                    'text' => '#333333',
                    'text_secondary' => '#666666',
                    'accent' => '#0073aa',
                    'button_start' => '#0073aa',
                    'button_end' => '#005177'
                )
            ),
            'nature' => array(
                'name' => 'Nature Theme',
                'colors' => array(
                    'background_start' => '#2d5a27',
                    'background_end' => '#1a3a18',
                    'text' => '#ffffff',
                    'text_secondary' => '#b8e6b3',
                    'accent' => '#8bc34a',
                    'button_start' => '#8bc34a',
                    'button_end' => '#689f38'
                )
            ),
            'custom' => array(
                'name' => 'Custom Colors',
                'colors' => array(
                    'background_start' => '',
                    'background_end' => '',
                    'text' => '',
                    'text_secondary' => '',
                    'accent' => '',
                    'button_start' => '',
                    'button_end' => ''
                )
            )
        );

        public function __construct() {
            $this->id = 'age_verification';
            $this->label = esc_html__('Age Verification', 'thaitop-age-verification-popup');

            parent::__construct();

            add_action('woocommerce_admin_field_color_template', array($this, 'output_color_template_field'));
            add_action('woocommerce_update_option_color_template', array($this, 'update_color_template_field'));
        }

        public function output_color_template_field($value) {
            $current_template = get_option('wc_age_verification_color_template', 'steam');
            ?>
            <tr valign="top">
                <th scope="row" class="titledesc">
                    <label for="<?php echo esc_attr($value['id']); ?>"><?php echo esc_html($value['title']); ?></label>
                </th>
                <td class="forminp forminp-<?php echo esc_attr(sanitize_title($value['type'])); ?>">
                    <?php wp_nonce_field('wc_age_verification_color_template_update', 'wc_age_verification_nonce'); ?>
                    <select name="<?php echo esc_attr($value['id']); ?>" id="<?php echo esc_attr($value['id']); ?>" class="wc-enhanced-select" style="width: 400px;">
                        <?php foreach ($this->color_templates as $key => $template) : ?>
                            <option value="<?php echo esc_attr($key); ?>" <?php selected($current_template, $key); ?>><?php echo esc_html($template['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description"><?php echo wp_kses_post($value['desc']); ?></p>
                    <script type="text/javascript">
                        jQuery(document).ready(function($) {
                            var templates = <?php echo wp_json_encode($this->color_templates); ?>;
                            var colorFields = {
                                'background_start': 'wc_age_verification_background_start',
                                'background_end': 'wc_age_verification_background_end',
                                'text': 'wc_age_verification_text_color',
                                'text_secondary': 'wc_age_verification_secondary_text_color',
                                'accent': 'wc_age_verification_accent_color',
                                'button_start': 'wc_age_verification_button_gradient_start',
                                'button_end': 'wc_age_verification_button_gradient_end'
                            };

                            $('#<?php echo esc_attr($value['id']); ?>').on('change', function() {
                                var template = templates[$(this).val()];
                                if (template && template.colors) {
                                    Object.keys(colorFields).forEach(function(key) {
                                        if (template.colors[key]) {
                                            $('#' + colorFields[key]).val(template.colors[key]).trigger('change');
                                        }
                                    });
                                }
                            });
                        });
                    </script>
                </td>
            </tr>
            <?php
        }

        public function update_color_template_field($value) {
            // Sanitize and verify nonce
            $nonce_key = 'wc_age_verification_nonce';
            $nonce = isset($_POST[$nonce_key]) ? sanitize_key(wp_unslash($_POST[$nonce_key])) : '';
            if (empty($nonce) || !wp_verify_nonce($nonce, 'wc_age_verification_color_template_update')) {
                return;
            }

            // Sanitize the option ID
            $option_id = isset($value['id']) ? sanitize_key($value['id']) : '';
            if (empty($option_id)) {
                return;
            }

            // Sanitize the $_POST key before checking
            $sanitized_post_key = sanitize_key($option_id);
            
            // Get and sanitize the posted value
            if (!isset($_POST[$sanitized_post_key])) {
                return;
            }
            
            $raw_value = sanitize_text_field(wp_unslash($_POST[$sanitized_post_key]));
            if (empty($raw_value)) {
                return;
            }

            // Additional validation for color template value
            $valid_templates = array_keys($this->color_templates);
            
            if (in_array($raw_value, $valid_templates, true)) {
                update_option($sanitized_post_key, $raw_value);
            }
        }

        public function get_settings() {
            $settings = array(
                array(
                    'title' => esc_html__('Age Verification Popup Style', 'thaitop-age-verification-popup'),
                    'type'  => 'title',
                    'desc'  => esc_html__('Customize the appearance of the age verification popup.', 'thaitop-age-verification-popup'),
                    'id'    => 'wc_age_verification_style_options'
                ),

                array(
                    'title'    => esc_html__('Show Buddhist Year', 'thaitop-age-verification-popup'),
                    'desc'     => esc_html__('Show Buddhist year (BE) next to Gregorian year (CE)', 'thaitop-age-verification-popup'),
                    'id'       => 'wc_age_verification_show_buddhist_year',
                    'type'     => 'checkbox',
                    'default'  => 'yes'
                ),

                array(
                    'title'    => esc_html__('Color Template', 'thaitop-age-verification-popup'),
                    'desc'     => esc_html__('Choose a predefined color template or select "Custom Colors" to customize each color.', 'thaitop-age-verification-popup'),
                    'id'       => 'wc_age_verification_color_template',
                    'type'     => 'color_template',
                    'default'  => 'steam'
                ),

                array(
                    'title'    => esc_html__('Background Gradient Start', 'thaitop-age-verification-popup'),
                    'desc'     => esc_html__('Select the start color for the popup background gradient.', 'thaitop-age-verification-popup'),
                    'id'       => 'wc_age_verification_background_start',
                    'type'     => 'color',
                    'default'  => '#2a475e',
                    'css'      => 'width:6em;'
                ),

                array(
                    'title'    => esc_html__('Background Gradient End', 'thaitop-age-verification-popup'),
                    'desc'     => esc_html__('Select the end color for the popup background gradient.', 'thaitop-age-verification-popup'),
                    'id'       => 'wc_age_verification_background_end',
                    'type'     => 'color',
                    'default'  => '#1b2838',
                    'css'      => 'width:6em;'
                ),

                array(
                    'title'    => esc_html__('Primary Text Color', 'thaitop-age-verification-popup'),
                    'desc'     => esc_html__('Select the color for primary text (headings).', 'thaitop-age-verification-popup'),
                    'id'       => 'wc_age_verification_text_color',
                    'type'     => 'color',
                    'default'  => '#ffffff',
                    'css'      => 'width:6em;'
                ),

                array(
                    'title'    => esc_html__('Secondary Text Color', 'thaitop-age-verification-popup'),
                    'desc'     => esc_html__('Select the color for secondary text (paragraphs).', 'thaitop-age-verification-popup'),
                    'id'       => 'wc_age_verification_secondary_text_color',
                    'type'     => 'color',
                    'default'  => '#acb2b8',
                    'css'      => 'width:6em;'
                ),

                array(
                    'title'    => esc_html__('Accent Color', 'thaitop-age-verification-popup'),
                    'desc'     => esc_html__('Select the accent color for borders and highlights.', 'thaitop-age-verification-popup'),
                    'id'       => 'wc_age_verification_accent_color',
                    'type'     => 'color',
                    'default'  => '#66c0f4',
                    'css'      => 'width:6em;'
                ),

                array(
                    'title'    => esc_html__('Button Gradient Start', 'thaitop-age-verification-popup'),
                    'desc'     => esc_html__('Select the start color for the button gradient.', 'thaitop-age-verification-popup'),
                    'id'       => 'wc_age_verification_button_gradient_start',
                    'type'     => 'color',
                    'default'  => '#47bfff',
                    'css'      => 'width:6em;'
                ),

                array(
                    'title'    => esc_html__('Button Gradient End', 'thaitop-age-verification-popup'),
                    'desc'     => esc_html__('Select the end color for the button gradient.', 'thaitop-age-verification-popup'),
                    'id'       => 'wc_age_verification_button_gradient_end',
                    'type'     => 'color',
                    'default'  => '#1a44c2',
                    'css'      => 'width:6em;'
                ),

                array(
                    'type' => 'sectionend',
                    'id'   => 'wc_age_verification_style_options'
                ),
            );

            return apply_filters('woocommerce_get_settings_' . $this->id, $settings);
        }
    }

    return new WC_Settings_Age_Verification();
} 