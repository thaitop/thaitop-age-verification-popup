<?php
/**
 * Plugin Name: ThaiTop Age Verification Popup
 * Plugin URI: 
 * Description: Add age verification popup for WooCommerce products with customizable minimum age requirements. Features include age calculation from current or custom date, Buddhist year display support, multiple color templates, and mobile-responsive design. Perfect for stores selling age-restricted products.
 * Version: 1.1.0
 * Author: ThaiTop
 * Author URI: https://thaitoptecs.com
 * Text Domain: thaitop-age-verification-popup
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.2
 * WC requires at least: 6.0
 * WC tested up to: 8.4
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * This plugin adds an age verification popup to WooCommerce products.
 * Key features:
 * - Individual product age verification
 * - Customizable minimum age requirements
 * - Current or custom date age calculation
 * - Buddhist year display support (optional)
 * - Multiple color templates (Steam, Dark, Light, Nature)
 * - Full color customization
 * - Mobile-responsive design
 * - Multi-language support
 * - WooCommerce HPOS compatible
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('ThaiTop_Age_Verification_Popup')) {

    class ThaiTop_Age_Verification_Popup {
        /**
         * @var WC_Logger|null Logger instance for WooCommerce logging
         */
        private $logger = null;

        public function __construct() {
            add_action('plugins_loaded', array($this, 'init'));
            
            // Add compatibility with High-Performance Order Storage (HPOS)
            add_action('before_woocommerce_init', array($this, 'declare_hpos_compatibility'));

            // Add settings link to plugins page
            add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'add_action_links'));
        }

        public function declare_hpos_compatibility() {
            if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
                \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
                \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
            }
        }

        public function init() {
            // Check if WooCommerce is active
            if (!class_exists('WooCommerce')) {
                add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
                return;
            }

            // Check WooCommerce version
            if (!$this->is_version_compatible()) {
                add_action('admin_notices', array($this, 'version_incompatible_notice'));
                return;
            }

            // Add settings
            add_filter('woocommerce_get_settings_pages', array($this, 'add_settings_page'));

            // Add product tab
            add_filter('woocommerce_product_data_tabs', array($this, 'add_age_verification_product_tab'));
            add_action('woocommerce_product_data_panels', array($this, 'add_age_verification_product_fields'));
            add_action('woocommerce_process_product_meta', array($this, 'save_age_verification_fields'));

            // Frontend scripts and styles
            add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
            
            // Add popup to single product page
            add_action('woocommerce_before_single_product', array($this, 'add_age_verification_popup'));
            
            // AJAX handlers
            add_action('wp_ajax_verify_age', array($this, 'verify_age'));
            add_action('wp_ajax_nopriv_verify_age', array($this, 'verify_age'));

            // Initialize logger if available
            if (function_exists('wc_get_logger')) {
                $this->logger = wc_get_logger();
            }
        }

        public function add_settings_page($settings) {
            $settings[] = include plugin_dir_path(__FILE__) . 'includes/class-wc-settings-age-verification.php';
            return $settings;
        }

        private function is_version_compatible() {
            if (defined('WC_VERSION') && WC_VERSION) {
                if (version_compare(WC_VERSION, '6.0', '<')) {
                    return false;
                }
            }
            return true;
        }

        public function woocommerce_missing_notice() {
            ?>
            <div class="error">
                <p><?php esc_html_e('WooCommerce Age Verification Popup requires WooCommerce to be installed and active.', 'thaitop-age-verification-popup'); ?></p>
            </div>
            <?php
        }

        public function version_incompatible_notice() {
            ?>
            <div class="error">
                <p><?php esc_html_e('WooCommerce Age Verification Popup requires WooCommerce 6.0 or higher.', 'thaitop-age-verification-popup'); ?></p>
            </div>
            <?php
        }

        public function add_age_verification_product_tab($tabs) {
            $tabs['age_verification'] = array(
                'label' => esc_html__('Age Verification', 'thaitop-age-verification-popup'),
                'target' => 'age_verification_product_data',
                'class' => array('show_if_simple', 'show_if_variable'),
            );
            return $tabs;
        }

        public function add_age_verification_product_fields() {
            global $woocommerce, $post;
            ?>
            <div id="age_verification_product_data" class="panel woocommerce_options_panel">
                <?php
                // Add nonce field
                wp_nonce_field('thaitop_age_verification_save', 'thaitop_age_verification_nonce');

                woocommerce_wp_checkbox(array(
                    'id' => '_require_age_verification',
                    'label' => esc_html__('Enable Age Verification', 'thaitop-age-verification-popup'),
                    'description' => esc_html__('Check this to enable age verification for this product.', 'thaitop-age-verification-popup')
                ));
                ?>

                <div class="age-verification-fields" style="display: none;">
                    <?php
                    // Minimum Age field
                    woocommerce_wp_text_input(array(
                        'id' => '_minimum_age',
                        'label' => esc_html__('Minimum Age Required', 'thaitop-age-verification-popup'),
                        'description' => esc_html__('Enter the minimum age required to purchase this product.', 'thaitop-age-verification-popup'),
                        'type' => 'number',
                        'custom_attributes' => array(
                            'min' => '0',
                            'step' => '1'
                        )
                    ));

                    // Maximum Age field (ย้ายมาอยู่ต่อจาก Minimum Age)
                    woocommerce_wp_text_input(array(
                        'id' => '_maximum_age',
                        'label' => esc_html__('Maximum Age Allowed', 'thaitop-age-verification-popup'),
                        'description' => esc_html__('Enter the maximum age allowed to purchase this product (leave empty for no limit).', 'thaitop-age-verification-popup'),
                        'type' => 'number',
                        'custom_attributes' => array(
                            'min' => '0',
                            'step' => '1'
                        )
                    ));

                    // Age Calculation Method field
                    woocommerce_wp_select(array(
                        'id' => '_age_calculation_type',
                        'label' => esc_html__('Age Calculation Method', 'thaitop-age-verification-popup'),
                        'description' => esc_html__('Choose how to calculate the age requirement.', 'thaitop-age-verification-popup'),
                        'options' => array(
                            'current' => esc_html__('Calculate from current date', 'thaitop-age-verification-popup'),
                            'custom' => esc_html__('Calculate from custom date', 'thaitop-age-verification-popup')
                        ),
                        'value' => get_post_meta($post->ID, '_age_calculation_type', true) ?: 'current'
                    ));

                    // Reference Date field
                    woocommerce_wp_text_input(array(
                        'id' => '_reference_date',
                        'label' => esc_html__('Reference Date', 'thaitop-age-verification-popup'),
                        'description' => esc_html__('Enter the date to calculate age from (YYYY-MM-DD format).', 'thaitop-age-verification-popup'),
                        'type' => 'date',
                        'custom_attributes' => array(
                            'pattern' => '[0-9]{4}-[0-9]{2}-[0-9]{2}'
                        ),
                        'wrapper_class' => 'reference-date-field'
                    ));
                    ?>
                </div>

                <script type="text/javascript">
                    jQuery(document).ready(function($) {
                        // Show/hide age verification fields based on checkbox
                        function toggleAgeVerificationFields() {
                            if ($('#_require_age_verification').is(':checked')) {
                                $('.age-verification-fields').show();
                            } else {
                                $('.age-verification-fields').hide();
                            }
                        }

                        // Show/hide reference date field based on select
                        function toggleReferenceDate() {
                            if ($('#_age_calculation_type').val() === 'custom') {
                                $('.reference-date-field').show();
                            } else {
                                $('.reference-date-field').hide();
                            }
                        }
                        
                        $('#_require_age_verification').change(toggleAgeVerificationFields);
                        $('#_age_calculation_type').change(toggleReferenceDate);
                        
                        // Run on page load
                        toggleAgeVerificationFields();
                        toggleReferenceDate();
                    });
                </script>
            </div>
            <?php
        }

        public function save_age_verification_fields($post_id) {
            // Verify nonce
            $nonce = isset($_POST['thaitop_age_verification_nonce']) ? sanitize_text_field(wp_unslash($_POST['thaitop_age_verification_nonce'])) : '';
            if (empty($nonce) || !wp_verify_nonce($nonce, 'thaitop_age_verification_save')) {
                return;
            }

            // Check user capabilities
            if (!current_user_can('edit_post', $post_id)) {
                return;
            }

            $require_age_verification = isset($_POST['_require_age_verification']) ? 'yes' : 'no';
            update_post_meta($post_id, '_require_age_verification', wp_unslash($require_age_verification));

            if (isset($_POST['_minimum_age'])) {
                update_post_meta($post_id, '_minimum_age', absint(wp_unslash($_POST['_minimum_age'])));
            }

            if (isset($_POST['_age_calculation_type'])) {
                $calculation_type = sanitize_text_field(wp_unslash($_POST['_age_calculation_type']));
                if (in_array($calculation_type, array('current', 'custom'))) {
                    update_post_meta($post_id, '_age_calculation_type', $calculation_type);
                }
            }

            if (isset($_POST['_reference_date'])) {
                // Validate date format
                $reference_date = sanitize_text_field(wp_unslash($_POST['_reference_date']));
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $reference_date)) {
                    update_post_meta($post_id, '_reference_date', $reference_date);
                }
            }

            if (isset($_POST['_maximum_age'])) {
                $maximum_age = sanitize_text_field(wp_unslash($_POST['_maximum_age']));
                if (is_numeric($maximum_age) || empty($maximum_age)) {
                    update_post_meta($post_id, '_maximum_age', $maximum_age);
                }
            }
        }

        public function enqueue_scripts() {
            if (is_product()) {
                wp_enqueue_style('dashicons');
                wp_enqueue_style('thaitop-age-verification-popup-style', plugins_url('assets/css/style.css', __FILE__));
                
                // Add custom CSS variables
                $custom_css = $this->get_custom_css();
                wp_add_inline_style('thaitop-age-verification-popup-style', $custom_css);
                
                wp_enqueue_script('thaitop-age-verification-popup-script', plugins_url('assets/js/script.js', __FILE__), array('jquery'), '1.0.0', true);
                wp_localize_script('thaitop-age-verification-popup-script', 'thaitopAgeVerification', array(
                    'ajaxurl' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('thaitop-age-verification')
                ));
            }
        }

        private function get_custom_css() {
            $background_start = get_option('wc_age_verification_background_start', '#2a475e');
            $background_end = get_option('wc_age_verification_background_end', '#1b2838');
            $text_color = get_option('wc_age_verification_text_color', '#ffffff');
            $secondary_text_color = get_option('wc_age_verification_secondary_text_color', '#acb2b8');
            $accent_color = get_option('wc_age_verification_accent_color', '#66c0f4');
            $button_gradient_start = get_option('wc_age_verification_button_gradient_start', '#47bfff');
            $button_gradient_end = get_option('wc_age_verification_button_gradient_end', '#1a44c2');

            return "
                :root {
                    --age-verification-bg-start: " . esc_attr($background_start) . ";
                    --age-verification-bg-end: " . esc_attr($background_end) . ";
                    --age-verification-text: " . esc_attr($text_color) . ";
                    --age-verification-text-secondary: " . esc_attr($secondary_text_color) . ";
                    --age-verification-accent: " . esc_attr($accent_color) . ";
                    --age-verification-button-start: " . esc_attr($button_gradient_start) . ";
                    --age-verification-button-end: " . esc_attr($button_gradient_end) . ";
                }
            ";
        }

        public function add_age_verification_popup() {
            global $product;
            
            if (!$product) return;
            
            $require_verification = get_post_meta($product->get_id(), '_require_age_verification', true);
            
            if ($require_verification === 'yes') {
                $minimum_age = get_post_meta($product->get_id(), '_minimum_age', true);
                $maximum_age = get_post_meta($product->get_id(), '_maximum_age', true);
                $calculation_type = get_post_meta($product->get_id(), '_age_calculation_type', true) ?: 'current';
                $reference_date = get_post_meta($product->get_id(), '_reference_date', true);
                ?>
                <div id="age-verification-popup" class="age-verification-popup" style="display: none;">
                    <div class="popup-content">
                        <h2><?php esc_html_e('Age Verification Required', 'thaitop-age-verification-popup'); ?></h2>
                        
                        <?php if (!empty($maximum_age)) : ?>
                            <p><?php 
                                printf(/* translators: %1$s: minimum age, %2$s: maximum age */
                                    esc_html__('You must be between %1$s and %2$s years old to view this product.', 'thaitop-age-verification-popup'),
                                    esc_html($minimum_age),
                                    esc_html($maximum_age)
                                ); 
                            ?></p>
                        <?php else : ?>
                            <p><?php 
                                printf(/* translators: %s: minimum age required */
                                    esc_html__('You must be %s years or older to view this product.', 'thaitop-age-verification-popup'),
                                    esc_html($minimum_age)
                                ); 
                            ?></p>
                        <?php endif; ?>

                        <?php if ($calculation_type === 'custom' && $reference_date) : ?>
                            <p class="reference-date-notice"><?php 
                                printf(/* translators: %s: reference date in format "j F Y" */
                                    wp_kses_post(__('Age will be calculated as of <strong>%s</strong>', 'thaitop-age-verification-popup')),
                                    esc_html(gmdate('j F Y', strtotime($reference_date)))
                                ); 
                            ?></p>
                        <?php else : ?>
                            <p class="reference-date-notice"><?php esc_html_e('Age will be calculated from current date', 'thaitop-age-verification-popup'); ?></p>
                        <?php endif; ?>
                        
                        <div class="birthday-selector">
                            <select id="birth-day" name="birth-day">
                                <option value=""><?php esc_html_e('Day', 'thaitop-age-verification-popup'); ?></option>
                                <?php for ($i = 1; $i <= 31; $i++) : ?>
                                    <option value="<?php echo esc_attr($i); ?>"><?php echo esc_html($i); ?></option>
                                <?php endfor; ?>
                            </select>
                            
                            <select id="birth-month" name="birth-month">
                                <option value=""><?php esc_html_e('Month', 'thaitop-age-verification-popup'); ?></option>
                                <?php 
                                $months = array(
                                    1 => 'January',
                                    2 => 'February',
                                    3 => 'March',
                                    4 => 'April',
                                    5 => 'May',
                                    6 => 'June',
                                    7 => 'July',
                                    8 => 'August',
                                    9 => 'September',
                                    10 => 'October',
                                    11 => 'November',
                                    12 => 'December'
                                );
                                foreach ($months as $num => $name) : ?>
                                    <option value="<?php echo esc_attr($num); ?>"><?php echo esc_html($name); ?></option>
                                <?php endforeach; ?>
                            </select>
                            
                            <select id="birth-year" name="birth-year">
                                <option value=""><?php esc_html_e('Year', 'thaitop-age-verification-popup'); ?></option>
                                <?php 
                                $show_buddhist_year = get_option('wc_age_verification_show_buddhist_year', 'yes');
                                for ($i = gmdate('Y'); $i >= gmdate('Y') - 100; $i--) : 
                                    $buddhist_year = $i + 543;
                                    $year_display = $show_buddhist_year === 'yes' ? 
                                        /* translators: 1: Gregorian year (CE), 2: Buddhist year (BE) */
                                        sprintf(esc_html__('%1$d (BE %2$d)', 'thaitop-age-verification-popup'), esc_html($i), esc_html($buddhist_year)) : 
                                        esc_html($i);
                                ?>
                                    <option value="<?php echo esc_attr($i); ?>"><?php echo wp_kses_post($year_display); ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        
                        <div class="button-group">
                            <button id="verify-age-btn" class="button"><?php esc_html_e('Verify Age', 'thaitop-age-verification-popup'); ?></button>
                            <button id="cancel-age-btn" type="button" onclick="history.back()"><?php esc_html_e('Cancel', 'thaitop-age-verification-popup'); ?></button>
                        </div>
                        <input type="hidden" id="minimum-age" value="<?php echo esc_attr($minimum_age); ?>">
                        <input type="hidden" id="calculation-type" value="<?php echo esc_attr($calculation_type); ?>">
                        <?php if ($calculation_type === 'custom' && $reference_date) : ?>
                            <input type="hidden" id="reference-date" value="<?php echo esc_attr($reference_date); ?>">
                        <?php endif; ?>
                        <input type="hidden" id="maximum-age" value="<?php echo esc_attr($maximum_age); ?>">
                    </div>
                </div>
                <?php
            }
        }

        public function verify_age() {
            check_ajax_referer('thaitop-age-verification', 'nonce');

            $birth_day = isset($_POST['birth_day']) ? intval(wp_unslash($_POST['birth_day'])) : 0;
            $birth_month = isset($_POST['birth_month']) ? intval(wp_unslash($_POST['birth_month'])) : 0;
            $birth_year = isset($_POST['birth_year']) ? intval(wp_unslash($_POST['birth_year'])) : 0;
            $minimum_age = isset($_POST['minimum_age']) ? intval(wp_unslash($_POST['minimum_age'])) : 0;
            $maximum_age = isset($_POST['maximum_age']) && !empty($_POST['maximum_age']) ? intval(wp_unslash($_POST['maximum_age'])) : 0;
            $calculation_type = isset($_POST['calculation_type']) ? sanitize_text_field(wp_unslash($_POST['calculation_type'])) : 'current';
            $reference_date = isset($_POST['reference_date']) ? sanitize_text_field(wp_unslash($_POST['reference_date'])) : '';

            if (!$birth_day || !$birth_month || !$birth_year || !$minimum_age) {
                if (isset($this->logger)) {
                    $this->logger->debug('Age verification failed: Missing required fields', array('source' => 'age-verification'));
                }
                wp_send_json_error(esc_html__('Please fill in all fields.', 'thaitop-age-verification-popup'));
            }

            try {
                // สร้าง DateTime objects สำหรับวันเกิดและวันที่เปรียบเทียบ
                $birth_date = new DateTime();
                $birth_date->setDate($birth_year, $birth_month, $birth_day);
                $birth_date->setTime(0, 0, 0);

                $compare_date = ($calculation_type === 'custom' && $reference_date) ? 
                    new DateTime($reference_date) : 
                    new DateTime('today');
                $compare_date->setTime(0, 0, 0);

                // ตรวจสอบว่าวันเกิดไม่เกินวันที่ปัจจุบัน
                if ($birth_date > $compare_date) {
                    if (isset($this->logger)) {
                        $this->logger->notice('Age verification failed: Birth date is in the future');
                    }
                    wp_send_json_error(esc_html__('Invalid birth date.', 'thaitop-age-verification-popup'));
                }

                // คำนวณอายุอย่างแม่นยำ
                $age = $compare_date->diff($birth_date);
                $years = $age->y;

                // บันทึก log การคำนวณอายุ
                if (isset($this->logger)) {
                    $this->logger->debug(sprintf(
                        'Age calculation: Birth date: %s, Compare date: %s, Age: %d years',
                        $birth_date->format('Y-m-d'),
                        $compare_date->format('Y-m-d'),
                        $years
                    ));
                }

                // ตรวจสอบอายุขั้นต่ำ
                if ($years < $minimum_age) {
                    if (isset($this->logger)) {
                        $this->logger->notice(sprintf(
                            'Age verification failed: User age %d is below minimum requirement of %d',
                            $years,
                            $minimum_age
                        ));
                    }
                    wp_send_json_error(esc_html__('Sorry, you must be older to view this product.', 'thaitop-age-verification-popup'));
                }

                // ตรวจสอบอายุสูงสุด (ถ้ามีการกำหนด)
                if (!empty($maximum_age) && $years > intval($maximum_age)) {
                    if (isset($this->logger)) {
                        $this->logger->notice(sprintf(
                            'Age verification failed: User age %d exceeds maximum limit of %d',
                            $years,
                            $maximum_age
                        ));
                    }
                    wp_send_json_error(esc_html__('Sorry, you exceed the maximum age limit for this product.', 'thaitop-age-verification-popup'));
                }

                // บันทึก log เมื่อผ่านการตรวจสอบ
                if (isset($this->logger)) {
                    $this->logger->info(sprintf(
                        'Age verification successful: User age %d is within allowed range (min: %d, max: %s)',
                        $years,
                        $minimum_age,
                        !empty($maximum_age) ? $maximum_age : 'no limit'
                    ));
                }

                wp_send_json_success();

            } catch (Exception $e) {
                if (isset($this->logger)) {
                    $this->logger->error('Age calculation error: ' . $e->getMessage());
                }
                wp_send_json_error(esc_html__('An error occurred while verifying your age.', 'thaitop-age-verification-popup'));
            }
        }

        public function add_action_links($links) {
            $settings_link = '<a href="' . admin_url('admin.php?page=wc-settings&tab=age_verification') . '">' . esc_html__('Settings', 'thaitop-age-verification-popup') . '</a>';
            array_unshift($links, $settings_link);
            return $links;
        }
    }

    new ThaiTop_Age_Verification_Popup();
}