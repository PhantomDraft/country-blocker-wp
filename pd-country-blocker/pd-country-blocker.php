<?php
/**
 * Plugin Name: PD Country Blocker
 * Plugin URI: https://github.com/PhantomDraft/country-blocker-wp
 * Description: Blocks the entire site or individual pages based on countries.
 * Version:     1.1
 * Author:      PD
 * Author URI:  https://guides.phantom-draft.com/
 * License:     GPL2
 * Text Domain: pd-country-blocker
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PD_Country_Blocker {
    private static $instance = null;
    private $option_name = 'pd_country_blocker_options';

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'template_redirect', [ $this, 'check_access' ] );
    }

    /**
     * Add settings menu.
     */
    public function add_admin_menu() {
        // Register global PD menu if not already registered
        if ( ! defined( 'PD_GLOBAL_MENU_REGISTERED' ) ) {
            add_menu_page(
                'PD',                                // Title displayed in admin area
                'PD',                                // Menu title
                'manage_options',                    // Capability required
                'pd_main_menu',                      // Global menu slug
                'pd_global_menu_callback',           // Callback function for the global menu page
                'dashicons-shield',                  // Shield icon
                2                                    // Menu position
            );
            define( 'PD_GLOBAL_MENU_REGISTERED', true );
        }

        // Add PD Country Blocker as a submenu under the global PD menu
        add_submenu_page(
            'pd_main_menu',                        // Parent menu slug
            'PD Country Blocker',                  // Page title
            'PD Country Blocker',                  // Menu title
            'manage_options',                      // Capability required
            'pd_country_blocker',                  // Slug of the settings page
            [ $this, 'options_page_html' ]         // Callback function to display the page
        );
    }

    /**
     * Template for the settings page.
     */
    public function options_page_html() {
        ?>
        <div class="wrap">
            <h1>PD Country Blocker Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields( $this->option_name );
                do_settings_sections( $this->option_name );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Register settings in WordPress.
     */
    public function register_settings() {
        register_setting( $this->option_name, $this->option_name, [ $this, 'sanitize_settings' ] );

        add_settings_section( 'pd_blocker_main', 'Blocking Settings', null, $this->option_name );

        add_settings_field( 
            'blocked_countries', 
            'Blocked Countries', 
            [ $this, 'field_blocked_countries' ], 
            $this->option_name, 
            'pd_blocker_main' 
        );
        add_settings_field( 
            'blocked_items', 
            'Blocked IDs/Slugs', 
            [ $this, 'field_blocked_items' ], 
            $this->option_name, 
            'pd_blocker_main' 
        );
        add_settings_field( 
            'redirect_page', 
            'Redirect Page on Block', 
            [ $this, 'field_redirect_page' ], 
            $this->option_name, 
            'pd_blocker_main' 
        );
        add_settings_field( 
            'country_specific_rules', 
            'Country-Specific Rules', 
            [ $this, 'field_country_specific_rules' ], 
            $this->option_name, 
            'pd_blocker_main' 
        );
    }

    /**
     * Sanitize incoming settings.
     */
    public function sanitize_settings( $input ) {
        return [
            'blocked_countries' => isset( $input['blocked_countries'] ) ? array_map( 'sanitize_text_field', $input['blocked_countries'] ) : [],
            'blocked_items'     => isset( $input['blocked_items'] ) ? sanitize_text_field( $input['blocked_items'] ) : '',
            'redirect_page'     => isset( $input['redirect_page'] ) ? intval( $input['redirect_page'] ) : 0,
            'country_specific_rules' => isset( $input['country_specific_rules'] ) ? sanitize_textarea_field( $input['country_specific_rules'] ) : '',
        ];
    }

    /**
     * Get options with default values.
     */
    private function get_options() {
        return wp_parse_args( get_option( $this->option_name, [] ), [
            'blocked_countries' => [],
            'blocked_items'     => '',
            'redirect_page'     => 0
        ]);
    }

    /**
     * Field for selecting blocked countries.
     */
    public function field_blocked_countries() {
        $options = $this->get_options();
        $blocked_countries = $options['blocked_countries'];

        // Full list of countries with ISO codes
        $countries = [
            'AF' => 'Afghanistan', 'AL' => 'Albania', 'DZ' => 'Algeria', 'AD' => 'Andorra', 'AO' => 'Angola',
            'AG' => 'Antigua and Barbuda', 'AR' => 'Argentina', 'AM' => 'Armenia', 'AU' => 'Australia', 'AT' => 'Austria',
            'AZ' => 'Azerbaijan', 'BS' => 'Bahamas', 'BH' => 'Bahrain', 'BD' => 'Bangladesh', 'BB' => 'Barbados',
            'BY' => 'Belarus', 'BE' => 'Belgium', 'BZ' => 'Belize', 'BJ' => 'Benin', 'BT' => 'Bhutan',
            'BO' => 'Bolivia', 'BA' => 'Bosnia and Herzegovina', 'BW' => 'Botswana', 'BR' => 'Brazil', 'BN' => 'Brunei',
            'BG' => 'Bulgaria', 'BF' => 'Burkina Faso', 'BI' => 'Burundi', 'KH' => 'Cambodia', 'CM' => 'Cameroon',
            'CA' => 'Canada', 'CV' => 'Cape Verde', 'CF' => 'Central African Republic', 'TD' => 'Chad', 'CL' => 'Chile',
            'CN' => 'China', 'CO' => 'Colombia', 'KM' => 'Comoros', 'CD' => 'DR Congo', 'CG' => 'Republic of the Congo',
            'CR' => 'Costa Rica', 'HR' => 'Croatia', 'CU' => 'Cuba', 'CY' => 'Cyprus', 'CZ' => 'Czech Republic',
            'DK' => 'Denmark', 'DJ' => 'Djibouti', 'DM' => 'Dominica', 'DO' => 'Dominican Republic',
            'EC' => 'Ecuador', 'EG' => 'Egypt', 'SV' => 'El Salvador', 'GQ' => 'Equatorial Guinea',
            'ER' => 'Eritrea', 'EE' => 'Estonia', 'SZ' => 'Eswatini', 'ET' => 'Ethiopia', 'FJ' => 'Fiji',
            'FI' => 'Finland', 'FR' => 'France', 'GA' => 'Gabon', 'GM' => 'Gambia', 'GE' => 'Georgia',
            'DE' => 'Germany', 'GH' => 'Ghana', 'GR' => 'Greece', 'GD' => 'Grenada', 'GT' => 'Guatemala',
            'GN' => 'Guinea', 'GW' => 'Guinea-Bissau', 'GY' => 'Guyana', 'HT' => 'Haiti', 'HN' => 'Honduras',
            'HU' => 'Hungary', 'IS' => 'Iceland', 'IN' => 'India', 'ID' => 'Indonesia', 'IR' => 'Iran',
            'IQ' => 'Iraq', 'IE' => 'Ireland', 'IL' => 'Israel', 'IT' => 'Italy', 'JM' => 'Jamaica',
            'JP' => 'Japan', 'JO' => 'Jordan', 'KZ' => 'Kazakhstan', 'KE' => 'Kenya', 'KI' => 'Kiribati',
            'KW' => 'Kuwait', 'KG' => 'Kyrgyzstan', 'LA' => 'Laos', 'LV' => 'Latvia', 'LB' => 'Lebanon',
            'LS' => 'Lesotho', 'LR' => 'Liberia', 'LY' => 'Libya', 'LI' => 'Liechtenstein', 'LT' => 'Lithuania',
            'LU' => 'Luxembourg', 'MG' => 'Madagascar', 'MW' => 'Malawi', 'MY' => 'Malaysia', 'MV' => 'Maldives',
            'ML' => 'Mali', 'MT' => 'Malta', 'MH' => 'Marshall Islands', 'MR' => 'Mauritania', 'MU' => 'Mauritius',
            'MX' => 'Mexico', 'FM' => 'Micronesia', 'MD' => 'Moldova', 'MC' => 'Monaco', 'MN' => 'Mongolia',
            'ME' => 'Montenegro', 'MA' => 'Morocco', 'MZ' => 'Mozambique', 'MM' => 'Myanmar', 'NA' => 'Namibia',
            'NR' => 'Nauru', 'NP' => 'Nepal', 'NL' => 'Netherlands', 'NZ' => 'New Zealand', 'NI' => 'Nicaragua',
            'NE' => 'Niger', 'NG' => 'Nigeria', 'KP' => 'North Korea', 'NO' => 'Norway', 'OM' => 'Oman',
            'PK' => 'Pakistan', 'PW' => 'Palau', 'PA' => 'Panama', 'PG' => 'Papua New Guinea', 'PY' => 'Paraguay',
            'PE' => 'Peru', 'PH' => 'Philippines', 'PL' => 'Poland', 'PT' => 'Portugal', 'QA' => 'Qatar',
            'RO' => 'Romania', 'RU' => 'Russia', 'RW' => 'Rwanda', 'KN' => 'Saint Kitts and Nevis',
            'LC' => 'Saint Lucia', 'VC' => 'Saint Vincent and the Grenadines', 'WS' => 'Samoa', 'SM' => 'San Marino',
            'ST' => 'Sao Tome and Principe', 'SA' => 'Saudi Arabia', 'SN' => 'Senegal', 'RS' => 'Serbia',
            'SC' => 'Seychelles', 'SL' => 'Sierra Leone', 'SG' => 'Singapore', 'SK' => 'Slovakia', 'SI' => 'Slovenia',
            'SB' => 'Solomon Islands', 'SO' => 'Somalia', 'ZA' => 'South Africa', 'KR' => 'South Korea',
            'SS' => 'South Sudan', 'ES' => 'Spain', 'LK' => 'Sri Lanka', 'SD' => 'Sudan', 'SR' => 'Suriname',
            'SE' => 'Sweden', 'CH' => 'Switzerland', 'SY' => 'Syria', 'TJ' => 'Tajikistan', 'TZ' => 'Tanzania',
            'TH' => 'Thailand', 'TG' => 'Togo', 'TO' => 'Tonga', 'TT' => 'Trinidad and Tobago', 'TN' => 'Tunisia',
            'TR' => 'Turkey', 'TM' => 'Turkmenistan', 'TV' => 'Tuvalu', 'UG' => 'Uganda', 'UA' => 'Ukraine',
            'AE' => 'United Arab Emirates', 'GB' => 'United Kingdom', 'US' => 'United States', 'UY' => 'Uruguay', 'UZ' => 'Uzbekistan',
            'VU' => 'Vanuatu', 'VA' => 'Vatican City', 'VE' => 'Venezuela', 'VN' => 'Vietnam', 'YE' => 'Yemen',
            'ZM' => 'Zambia', 'ZW' => 'Zimbabwe'
        ];

        foreach ( $countries as $code => $name ) {
            echo "<label><input type='checkbox' name='{$this->option_name}[blocked_countries][]' value='$code' " . checked( in_array( $code, $blocked_countries ), true, false ) . " /> $name</label><br>";
        }
    }

    /**
     * Field for entering blocked IDs/Slugs.
     */
    public function field_blocked_items() {
        $options = $this->get_options();
        ?>
        <textarea name="<?php echo esc_attr( $this->option_name ); ?>[blocked_items]" rows="5"><?php echo esc_textarea( $options['blocked_items'] ); ?></textarea>
        <p class="description">Enter IDs or slugs to block (e.g., <code>12, about-us, my-category</code>).</p>
        <?php
    }

    /**
     * Field for selecting the redirect page when blocked.
     */
    public function field_redirect_page() {
        $options = $this->get_options();
        $redirect_page = isset( $options['redirect_page'] ) ? $options['redirect_page'] : '';

        $pages = get_pages();
        ?>
        <select name="<?php echo esc_attr( $this->option_name ); ?>[redirect_page]">
            <option value=""><?php _e( '(Leave empty to display a block message)', 'pd-country-blocker' ); ?></option>
            <?php foreach ( $pages as $page ) : ?>
                <option value="<?php echo $page->ID; ?>" <?php selected( $redirect_page, $page->ID ); ?>>
                    <?php echo esc_html( $page->post_title ); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="description">Select the page to which the user will be redirected when blocked.</p>
        <?php
    }

    /**
     * Field for entering country-specific rules.
     * Each line should be in the format: identifier|country
     * Example:
     * 42|US
     * about-us|GB
     */
    public function field_country_specific_rules() {
        $options = $this->get_options();
        $value = isset( $options['country_specific_rules'] ) ? $options['country_specific_rules'] : '';
        ?>
        <textarea name="<?php echo esc_attr( $this->option_name ); ?>[country_specific_rules]" rows="5" cols="50" style="font-family: monospace;"><?php echo esc_textarea( $value ); ?></textarea>
        <p class="description">
            Enter country-specific blocking rules. Each line should be in the format: <code>identifier|country</code>.<br>
            For example: <code>42|US</code> or <code>about-us|GB</code>.<br>
            (Country should be the ISO country code.)<br>
            You may refer to the country codes provided in the list below.
        </p>
        <?php
    }

    /**
     * Check access and redirect if necessary.
     */
    public function check_access() {
        if ( is_admin() || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
            return;
        }

        $options = $this->get_options();
        $blocked_countries = $options['blocked_countries'];
        $blocked_items = array_filter( array_map( 'trim', explode( ',', $options['blocked_items'] ) ) );
        $redirect_page = isset( $options['redirect_page'] ) ? intval( $options['redirect_page'] ) : 0;

        if ( empty( $blocked_countries ) ) {
            return;
        }

        $user_ip = $_SERVER['REMOTE_ADDR'];
        $user_country = $this->get_country_by_ip( $user_ip );

        // If country-specific rules are set, determine the current content (if it is a post or taxonomy)
        $current_id = null;
        $current_slug = null;
        if ( is_singular() ) {
            $post = get_post();
            if ( $post ) {
                $current_id = $post->ID;
                $current_slug = $post->post_name;
            }
        } elseif ( is_category() || is_tag() || is_tax() ) {
            $term = get_queried_object();
            if ( $term ) {
                $current_id = $term->term_id;
                $current_slug = $term->slug;
            }
        }
        $options = $this->get_options();
        if ( ! empty( $options['country_specific_rules'] ) && ( $current_id || $current_slug ) ) {
            $country_rules = $this->parse_country_specific_rules( $options['country_specific_rules'] );
            foreach ( $country_rules as $rule ) {
                $identifier = $rule['identifier'];
                $rule_country = $rule['country'];
                if ( is_numeric( $identifier ) && (int)$identifier === (int)$current_id ) {
                    if ( strtoupper( $user_country ) === $rule_country ) {
                        $this->do_redirect( $redirect_page, $user_ip, $user_country );
                    }
                } elseif ( ! is_numeric( $identifier ) && $identifier === $current_slug ) {
                    if ( strtoupper( $user_country ) === $rule_country ) {
                        $this->do_redirect( $redirect_page, $user_ip, $user_country );
                    }
                }
            }
        }

        if ( in_array( $user_country, $blocked_countries ) ) {
            $this->do_redirect( $redirect_page, $user_ip, $user_country );
        }

        if ( in_array( get_queried_object_id(), $blocked_items ) || in_array( get_query_var( 'name' ), $blocked_items ) ) {
            $this->do_redirect( $redirect_page, $user_ip, $user_country );
        }
    }

    /**
     * Redirect the user.
     */
    private function do_redirect( $redirect_page, $user_ip = '', $user_country = '' ) {
        if ( $redirect_page > 0 ) {
            $url = get_permalink( $redirect_page );
            if ( ! empty( $url ) ) {
                wp_safe_redirect( esc_url( $url ) );
                exit;
            }
        }
        // If no redirect page is set, display a detailed message
        $message  = 'Access to this content is restricted.<br><br>';
        if ( $user_ip || $user_country ) {
            $message .= 'Your IP: ' . esc_html( $user_ip ) . '<br>';
            $message .= 'Your country: ' . esc_html( $user_country ) . '<br><br>';
        }
        $message .= 'Sorry, access from your region is blocked.';
        wp_die( $message );
    }

    /**
     * Get country code based on IP.
     */
    private function get_country_by_ip( $ip ) {
        $response = wp_remote_get( "https://ipinfo.io/{$ip}/json" );
        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        return $data['country'] ?? 'UNKNOWN';
    }

    /**
     * Parse raw country-specific rules into an array.
     * Each rule is in the format: identifier|country
     */
    private function parse_country_specific_rules( $raw ) {
        $rules = [];
        $lines = preg_split( "/\r\n|\n|\r/", trim( $raw ) );
        foreach ( $lines as $line ) {
            $line = trim( $line );
            if ( empty( $line ) ) {
                continue;
            }
            $parts = explode( '|', $line );
            $identifier = isset( $parts[0] ) ? sanitize_text_field( trim( $parts[0] ) ) : '';
            $country = isset( $parts[1] ) ? sanitize_text_field( trim( $parts[1] ) ) : '';
            if ( ! empty( $identifier ) && ! empty( $country ) ) {
                $rules[] = [
                    'identifier' => $identifier,
                    'country'    => strtoupper( $country ), // приводим к верхнему регистру
                ];
            }
        }
        return $rules;
    }
}

if ( ! function_exists( 'pd_global_menu_callback' ) ) {
    function pd_global_menu_callback() {
        ?>
        <div class="wrap">
            <h1>PD Global Menu</h1>
            <p>Please visit our GitHub page:</p>
            <p><a href="https://github.com/PhantomDraft" target="_blank">https://github.com/PhantomDraft</a></p>
        </div>
        <?php
    }
}

PD_Country_Blocker::get_instance();