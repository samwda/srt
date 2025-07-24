<?php
/**
 * Plugin Name: Sam Reading Time
 * Plugin URI:  https://samwda.ir
 * Description: A lightweight WordPress plugin to display the estimated reading time of posts and pages using the [sam_reading_time] shortcode.
 * Version:     1.0
 * Author:      Seyyed Ahmadreza Mahjoob
 * Author URI:  https://samwda.ir
 * License:     GPLv2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: Sam-reading-time
 * Domain Path: /languages
 */

// Prevent direct access to the file
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Main class for the Sam Reading Time Plugin.
 * Manages all plugin functionalities including the shortcode and settings.
 */
class Sam_Reading_Time_Plugin {

    /**
     * Constructor.
     * Registers necessary WordPress hooks.
     */
    public function __construct() {
        // Register the shortcode
        add_shortcode( 'sam_reading_time', array( $this, 'display_reading_time_shortcode' ) );

        // Add admin menu and settings page
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'initialize_settings' ) );

        // Load plugin text domain for internationalization
        add_action( 'plugins_loaded', array( $this, 'load_plugin_textdomain' ) );

        // Add custom CSS to the frontend
        add_action( 'wp_head', array( $this, 'add_custom_css_to_frontend' ) );
    }

    /**
     * Loads the plugin's text domain for translation.
     */
    public function load_plugin_textdomain() {
        load_plugin_textdomain( 'Sam-reading-time', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
    }

    /**
     * Counts the number of words in a given text content.
     * This function is optimized for better support of Unicode languages (like Persian).
     *
     * @param string $content The text content to count words from.
     * @return int The number of words.
     */
    private function count_words( $content ) {
        // 1. Remove other shortcodes from the content to prevent them from being counted as words.
        $content = strip_shortcodes( $content );

        // 2. Remove all HTML tags from the content.
        $content = wp_strip_all_tags( $content );

        // 3. Replace special characters (like newlines, tabs) and multiple spaces with a single space.
        // This step is essential for preparing the text for accurate word separation.
        $content = preg_replace( '/\s+/u', ' ', $content ); // Use /u for Unicode support
        $content = trim( $content ); // Remove leading and trailing spaces.

        // 4. Split words based on whitespace and count them.
        // Use preg_split with PREG_SPLIT_NO_EMPTY flag to remove empty strings after splitting
        // and 'u' flag for Unicode support in Persian texts.
        $words = preg_split('/\s+/u', $content, -1, PREG_SPLIT_NO_EMPTY);

        // Count the number of elements in the words array.
        return count($words);
    }

    /**
     * Callback function for the [sam_reading_time] shortcode.
     * Calculates and displays the estimated reading time based on global settings.
     *
     * @param array $atts Attributes passed to the shortcode (only 'type' is considered for content source).
     * @return string Formatted reading time HTML.
     */
    public function display_reading_time_shortcode( $atts ) {
        // Get global settings directly. Shortcode attributes are ignored for most settings.
        $words_per_minute        = get_option( 'sam_reading_time_words_per_minute', 200 );
        /* translators: %1$s: The number of minutes. */
        $singular_format         = get_option( 'sam_reading_time_singular_format', esc_html__( '%1$s minute read', 'Sam-reading-time' ) );
        /* translators: %1$s: The number of minutes. */
        $plural_format           = get_option( 'sam_reading_time_plural_format', esc_html__( '%1$s minutes read', 'Sam-reading-time' ) );
        $less_than_a_minute_format = get_option( 'sam_reading_time_less_than_a_minute_format', esc_html__( 'Less than a minute read', 'Sam-reading-time' ) );
        $prefix_text             = get_option( 'sam_reading_time_prefix_text', '' );
        $suffix_text             = get_option( 'sam_reading_time_suffix_text', '' );
        $wrapper_tag             = get_option( 'sam_reading_time_wrapper_tag', 'span' );
        $hide_if_less_than_a_minute = get_option( 'sam_reading_time_hide_if_less_than_a_minute', false );
        $enable_debug_output     = get_option( 'sam_reading_time_enable_debug_output', false );

        // The 'type' attribute for 'excerpt' is still supported for flexibility,
        // but it's not advertised in the usage instructions.
        $content_type            = isset( $atts['type'] ) && in_array( $atts['type'], array( 'content', 'excerpt' ) ) ? $atts['type'] : 'content';

        global $post;
        $post_id = null;
        $content_to_count = '';

        // Attempt to get post ID from standard WordPress function.
        // This is the most reliable way when inside the loop or for singular posts.
        if ( is_singular() || ( function_exists('get_the_ID') && get_the_ID() ) ) {
            $post_id = get_the_ID();
        } elseif ( is_a( $post, 'WP_Post' ) ) { // Fallback to global $post object
            $post_id = $post->ID;
        }

        // If post ID is still not found, return a debug message or empty string.
        if ( ! $post_id ) {
            if ( $enable_debug_output ) {
                $debug_message = esc_html__( 'Sam Reading Time Debug: Post ID not found. Shortcode might be used in an unsupported context (e.g., outside the main loop, non-singular page).', 'Sam-reading-time' );
                return '<span style="color: red; direction:ltr; text-align:left; display:block; padding: 5px; border: 1px dashed red;">' . $debug_message . '</span>';
            }
            return '';
        }

        // Get content based on type
        if ( 'excerpt' === $content_type ) {
            $content_to_count = get_the_excerpt( $post_id );
        } else {
            // Using get_post_field directly for content is generally safe.
            // apply_filters('the_content', ...) could be used if you need other plugins' content filters to run,
            // but for word count, raw content is often preferred to avoid counting shortcode output etc.
            $content_to_count = get_post_field( 'post_content', $post_id );
        }

        // If no content to count, return empty string.
        if ( empty( $content_to_count ) ) {
            if ( $enable_debug_output ) {
                /* translators: %1$s: The Post ID. */
                return '<span style="color: orange; direction:ltr; text-align:left; display:block; padding: 5px; border: 1px dashed orange;">' . sprintf( esc_html__( 'Sam Reading Time Debug: No content found for Post ID %1$s.', 'Sam-reading-time' ), absint( $post_id ) ) . '</span>';
            }
            return '';
        }

        // Count words in the cleaned content.
        $word_count = $this->count_words( $content_to_count );

        // If word count is 0 (after cleaning), display nothing.
        if ( $word_count === 0 ) {
            if ( $enable_debug_output ) {
                /* translators: %1$s: The Post ID. */
                return '<span style="color: orange; direction:ltr; text-align:left; display:block; padding: 5px; border: 1px dashed orange;">' . sprintf( esc_html__( 'Sam Reading Time Debug: Word count is 0 for Post ID %1$s.', 'Sam-reading-time' ), absint( $post_id ) ) . '</span>';
            }
            return '';
        }

        // Calculate raw reading time (can be decimal).
        $valid_words_per_minute = max( 1, (int) $words_per_minute ); // Ensure WPM is at least 1.
        $raw_reading_time = $word_count / $valid_words_per_minute;

        $formatted_reading_time = '';

        // Logic for "Less than a minute" format.
        // If raw reading time is less than 1 minute, use the "less than a minute" format.
        if ( $raw_reading_time < 1 ) {
            if ( $hide_if_less_than_a_minute ) {
                return ''; // Hide output if setting is enabled
            }
            $formatted_reading_time = $less_than_a_minute_format;
        } else {
            // For posts that are 1 minute or more, always round up to the nearest whole minute.
            $display_time_value = ceil( $raw_reading_time );

            // Apply singular or plural format based on the rounded minute value.
            if ( $display_time_value === 1 ) {
                $formatted_reading_time = sprintf( $singular_format, $display_time_value );
            } else {
                $formatted_reading_time = sprintf( $plural_format, $display_time_value );
            }
        }

        // Add prefix and suffix.
        $final_output = $prefix_text . $formatted_reading_time . $suffix_text;

        // Add debug output if enabled
        if ( $enable_debug_output ) {
            /* translators: %1$s: The word count. %2$s: The raw reading time. */
            $final_output .= ' <span style="font-size:0.8em; opacity:0.7; direction:ltr; text-align:left; background-color: #f0f0f0; padding: 2px 5px; border-radius: 3px;">(' . sprintf( esc_html__( 'Words: %1$s, Raw Time: %2$s', 'Sam-reading-time' ), absint( $word_count ), number_format($raw_reading_time, 2) ) . ')</span>';
        }

        // Prepare CSS classes.
        $classes = array( 'reading-time' ); // Default class
        $class_attr = 'class="' . esc_attr( implode( ' ', $classes ) ) . '"';

        // Return the formatted reading time wrapped in the chosen HTML tag.
        return '<' . esc_attr( $wrapper_tag ) . ' ' . $class_attr . '>' . $final_output . '</' . esc_attr( $wrapper_tag ) . '>';
    }

    /**
     * Adds the plugin's settings page to the WordPress admin menu.
     * It's added as a submenu under the 'Posts' menu.
     */
    public function add_admin_menu() {
        add_submenu_page(
            'edit.php', // Parent slug for Posts menu
            esc_html__( 'Sam Reading Time Settings', 'Sam-reading-time' ), // Page title
            esc_html__( 'Sam Reading Time', 'Sam-reading-time' ),       // Menu title
            'manage_options',                                                // Capability required to access
            'sam-reading-time',                                              // Menu slug
            array( $this, 'options_page_html' )                              // Callback function to display page HTML
        );
    }

    /**
     * Initializes and registers plugin settings.
     */
    public function initialize_settings() {
        // Register a settings section.
        add_settings_section(
            'sam_reading_time_plugin_section',
            esc_html__( 'General Settings', 'Sam-reading-time' ),
            array( $this, 'reading_time_settings_section_callback' ),
            'sam-reading-time'
        );

        // Register field for Words Per Minute (WPM).
        add_settings_field(
            'sam_reading_time_words_per_minute',
            esc_html__( 'Words Per Minute (WPM)', 'Sam-reading-time' ),
            array( $this, 'words_per_minute_callback' ),
            'sam-reading-time',
            'sam_reading_time_plugin_section'
        );
        register_setting(
            'sam_reading_time',
            'sam_reading_time_words_per_minute',
            array(
                'type'              => 'integer',
                'sanitize_callback' => array( $this, 'sanitize_words_per_minute' ),
                'default'           => 200,
                'show_in_rest'      => false, // Not exposed via REST API
            )
        );

        // Register field for Singular Format.
        add_settings_field(
            'sam_reading_time_singular_format',
            esc_html__( 'Singular Format (e.g., 1 minute)', 'Sam-reading-time' ),
            array( $this, 'singular_format_callback' ),
            'sam-reading-time',
            'sam_reading_time_plugin_section'
        );
        register_setting(
            'sam_reading_time',
            'sam_reading_time_singular_format',
            array(
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                /* translators: %1$s: The number of minutes. */
                'default'           => esc_html__( '%1$s minute read', 'Sam-reading-time' ),
                'show_in_rest'      => false,
            )
        );

        // Register field for Plural Format.
        add_settings_field(
            'sam_reading_time_plural_format',
            esc_html__( 'Plural Format (e.g., 2 minutes)', 'Sam-reading-time' ),
            array( $this, 'plural_format_callback' ),
            'sam-reading-time',
            'sam_reading_time_plugin_section'
        );
        register_setting(
            'sam_reading_time',
            'sam_reading_time_plural_format',
            array(
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                /* translators: %1$s: The number of minutes. */
                'default'           => esc_html__( '%1$s minutes read', 'Sam-reading-time' ),
                'show_in_rest'      => false,
            )
        );

        // Register field for "Less than a minute" format.
        add_settings_field(
            'sam_reading_time_less_than_a_minute_format',
            esc_html__( 'Less Than A Minute Format', 'Sam-reading-time' ),
            array( $this, 'less_than_a_minute_format_callback' ),
            'sam-reading-time',
            'sam_reading_time_plugin_section'
        );
        register_setting(
            'sam_reading_time',
            'sam_reading_time_less_than_a_minute_format',
            array(
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default'           => esc_html__( 'Less than a minute read', 'Sam-reading-time' ),
                'show_in_rest'      => false,
            )
        );

        // Register field for Hide if Less Than A Minute.
        add_settings_field(
            'sam_reading_time_hide_if_less_than_a_minute',
            esc_html__( 'Hide if Less Than A Minute', 'Sam-reading-time' ),
            array( $this, 'hide_if_less_than_a_minute_callback' ),
            'sam-reading-time',
            'sam_reading_time_plugin_section'
        );
        register_setting(
            'sam_reading_time',
            'sam_reading_time_hide_if_less_than_a_minute',
            array(
                'type'              => 'boolean',
                'sanitize_callback' => 'rest_sanitize_boolean', // Use WordPress's boolean sanitizer
                'default'           => false,
                'show_in_rest'      => false,
            )
        );

        // Register field for Prefix Text.
        add_settings_field(
            'sam_reading_time_prefix_text',
            esc_html__( 'Prefix Text', 'Sam-reading-time' ),
            array( $this, 'prefix_text_callback' ),
            'sam-reading-time',
            'sam_reading_time_plugin_section'
        );
        register_setting(
            'sam_reading_time',
            'sam_reading_time_prefix_text',
            array(
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default'           => '',
                'show_in_rest'      => false,
            )
        );

        // Register field for Suffix Text.
        add_settings_field(
            'sam_reading_time_suffix_text',
            esc_html__( 'Suffix Text', 'Sam-reading-time' ),
            array( $this, 'suffix_text_callback' ),
            'sam-reading-time',
            'sam_reading_time_plugin_section'
        );
        register_setting(
            'sam_reading_time',
            'sam_reading_time_suffix_text',
            array(
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default'           => '',
                'show_in_rest'      => false,
            )
        );

        // Register field for Wrapper HTML Tag.
        add_settings_field(
            'sam_reading_time_wrapper_tag',
            esc_html__( 'Wrapper HTML Tag', 'Sam-reading-time' ),
            array( $this, 'wrapper_tag_callback' ),
            'sam-reading-time',
            'sam_reading_time_plugin_section'
        );
        register_setting(
            'sam_reading_time',
            'sam_reading_time_wrapper_tag',
            array(
                'type'              => 'string',
                'sanitize_callback' => array( $this, 'sanitize_wrapper_tag' ),
                'default'           => 'span',
                'show_in_rest'      => false,
            )
        );

        // Register field for Custom CSS Styles.
        add_settings_field(
            'sam_reading_time_custom_styles',
            esc_html__( 'Custom CSS Styles', 'Sam-reading-time' ),
            array( $this, 'custom_styles_callback' ),
            'sam-reading-time',
            'sam_reading_time_plugin_section'
        );
        register_setting(
            'sam_reading_time',
            'sam_reading_time_custom_styles',
            array(
                'type'              => 'string',
                // Using wp_strip_all_tags for CSS is a basic protection.
                // For full CSS sanitization, a more robust solution might be needed for complex inputs,
                // but for general user input, this prevents script injection.
                'sanitize_callback' => 'wp_strip_all_tags',
                'default'           => '',
                'show_in_rest'      => false,
            )
        );

        // Register field for Enable Debug Output.
        add_settings_field(
            'sam_reading_time_enable_debug_output',
            esc_html__( 'Enable Debug Output', 'Sam-reading-time' ),
            array( $this, 'enable_debug_output_callback' ),
            'sam-reading-time',
            'sam_reading_time_plugin_section'
        );
        register_setting(
            'sam_reading_time',
            'sam_reading_time_enable_debug_output',
            array(
                'type'              => 'boolean',
                'sanitize_callback' => 'rest_sanitize_boolean',
                'default'           => false,
                'show_in_rest'      => false,
            )
        );
    }

    /**
     * Callback for the settings section description.
     */
    public function reading_time_settings_section_callback() {
        echo '<p>' . esc_html__( 'Configure the general display settings for the Sam Reading Time plugin here.', 'Sam-reading-time' ) . '</p>';
    }

    /**
     * Callback for the Words Per Minute (WPM) settings field.
     */
    public function words_per_minute_callback() {
        $wpm = get_option( 'sam_reading_time_words_per_minute', 200 );
        // Using absint for output to ensure it's a positive integer, though sanitize_words_per_minute handles input.
        echo '<input type="number" name="sam_reading_time_words_per_minute" value="' . absint( $wpm ) . '" min="1" class="regular-text" />';
        echo '<p class="description">' . esc_html__( 'Average number of words a person reads per minute.', 'Sam-reading-time' ) . '</p>';
    }

    /**
     * Sanitization callback for Words Per Minute (WPM).
     *
     * @param int $input The input value.
     * @return int The sanitized value.
     */
    public function sanitize_words_per_minute( $input ) {
        $input = intval( $input );
        return ( $input > 0 ) ? $input : 200; // Ensure the value is positive.
    }

    /**
     * Callback for the Singular Format settings field.
     */
    public function singular_format_callback() {
        $format = get_option( 'sam_reading_time_singular_format', esc_html__( '%1$s minute read', 'Sam-reading-time' ) );
        echo '<input type="text" name="sam_reading_time_singular_format" value="' . esc_attr( $format ) . '" class="regular-text" />';
        // Using literal text for example to avoid placeholder issues with sprintf.
        echo '<p class="description">' . esc_html__( 'Use %s for the reading time. Example: "1 minute read"', 'Sam-reading-time' ) . '</p>';
    }

    /**
     * Callback for the Plural Format settings field.
     */
    public function plural_format_callback() {
        $format = get_option( 'sam_reading_time_plural_format', esc_html__( '%1$s minutes read', 'Sam-reading-time' ) );
        echo '<input type="text" name="sam_reading_time_plural_format" value="' . esc_attr( $format ) . '" class="regular-text" />';
        // Using literal text for example to avoid placeholder issues with sprintf.
        echo '<p class="description">' . esc_html__( 'Use %s for the reading time. Example: "2 minutes read"', 'Sam-reading-time' ) . '</p>';
    }

    /**
     * Callback for the "Less Than A Minute" Format settings field.
     */
    public function less_than_a_minute_format_callback() {
        $format = get_option( 'sam_reading_time_less_than_a_minute_format', esc_html__( 'Less than a minute read', 'Sam-reading-time' ) );
        echo '<input type="text" name="sam_reading_time_less_than_a_minute_format" value="' . esc_attr( $format ) . '" class="regular-text" />';
        echo '<p class="description">' . esc_html__( 'Text to display for articles that take less than one minute to read.', 'Sam-reading-time' ) . '</p>';
    }

    /**
     * Callback for Hide if Less Than A Minute settings field.
     */
    public function hide_if_less_than_a_minute_callback() {
        $hide = get_option( 'sam_reading_time_hide_if_less_than_a_minute', false );
        echo '<input type="checkbox" name="sam_reading_time_hide_if_less_than_a_minute" value="1" ' . checked( 1, $hide, false ) . ' />';
        echo '<p class="description">' . esc_html__( 'Check this box to hide the reading time output if it is less than one minute.', 'Sam-reading-time' ) . '</p>';
    }

    /**
     * Callback for the Prefix Text settings field.
     */
    public function prefix_text_callback() {
        $prefix = get_option( 'sam_reading_time_prefix_text', '' );
        echo '<input type="text" name="sam_reading_time_prefix_text" value="' . esc_attr( $prefix ) . '" class="regular-text" />';
        echo '<p class="description">' . esc_html__( 'Text to display before the reading time. Example: "Estimated reading time: "', 'Sam-reading-time' ) . '</p>';
    }

    /**
     * Callback for the Suffix Text settings field.
     */
    public function suffix_text_callback() {
        $suffix = get_option( 'sam_reading_time_suffix_text', '' );
        echo '<input type="text" name="sam_reading_time_suffix_text" value="' . esc_attr( $suffix ) . '" class="regular-text" />';
        echo '<p class="description">' . esc_html__( 'Text to display after the reading time. Example: " (approx.)"', 'Sam-reading-time' ) . '</p>';
    }

    /**
     * Callback for the Wrapper HTML Tag settings field.
     */
    public function wrapper_tag_callback() {
        $tag = get_option( 'sam_reading_time_wrapper_tag', 'span' );
        ?>
        <select name="sam_reading_time_wrapper_tag">
            <option value="span" <?php selected( $tag, 'span' ); ?>>span</option>
            <option value="div" <?php selected( $tag, 'div' ); ?>>div</option>
            <option value="p" <?php selected( $tag, 'p' ); ?>>p</option>
            <option value="strong" <?php selected( $tag, 'strong' ); ?>>strong</option>
            <option value="em" <?php selected( $tag, 'em' ); ?>>em</option>
        </select>
        <p class="description"><?php esc_html_e( 'Choose the HTML tag to wrap the reading time output. This affects its display behavior.', 'Sam-reading-time' ) . '<br>' . esc_html__( 'For inline display, use "span". For block display, use "div" or "p".', 'Sam-reading-time' ); ?></p>
        <?php
    }

    /**
     * Sanitization callback for Wrapper HTML Tag.
     *
     * @param string $input The input value.
     * @return string The sanitized value.
     */
    public function sanitize_wrapper_tag( $input ) {
        $allowed_tags = array( 'span', 'div', 'p', 'strong', 'em' );
        // Use sanitize_key for stricter validation if only specific, fixed strings are allowed.
        // For HTML tags, array check is sufficient.
        return in_array( $input, $allowed_tags, true ) ? sanitize_key( $input ) : 'span';
    }

    /**
     * Callback for the Custom CSS Styles textarea field.
     */
    public function custom_styles_callback() {
        $styles = get_option( 'sam_reading_time_custom_styles', '' );
        // Using esc_textarea to properly escape the value for HTML textarea.
        echo '<textarea name="sam_reading_time_custom_styles" rows="10" class="large-text code">' . esc_textarea( $styles ) . '</textarea>';
        echo '<p class="description">' . esc_html__( 'Enter your custom CSS styles here. These styles will be applied to the reading time output. Use the class ".reading-time" for default styling.', 'Sam-reading-time' ) . '</p>';
        echo '<p class="description"><strong>' . esc_html__( 'Example:', 'Sam-reading-time' ) . '</strong></p>';
        echo '<pre><code>.reading-time {
    color: #28a745; /* Green color */
    font-size: 1.2em;
    font-weight: 600;
    font-family: "Georgia", serif;
    padding: 5px 10px;
    border: 1px solid #28a745;
    border-radius: 8px;
    background-color: #e6ffe6;
    display: inline-block;
}</code></pre>';
    }

    /**
     * Callback for the Enable Debug Output checkbox.
     */
    public function enable_debug_output_callback() {
        $enable_debug = get_option( 'sam_reading_time_enable_debug_output', false );
        echo '<input type="checkbox" name="sam_reading_time_enable_debug_output" value="1" ' . checked( 1, $enable_debug, false ) . ' />';
        echo '<p class="description">' . esc_html__( 'Check this box to display word count and raw reading time next to the output for debugging purposes.', 'Sam-reading-time' ) . '</p>';
    }

    /**
     * Adds custom CSS from settings to the frontend.
     */
    public function add_custom_css_to_frontend() {
        $custom_styles = get_option( 'sam_reading_time_custom_styles', '' );
        if ( ! empty( $custom_styles ) ) {
            // wp_strip_all_tags is used here as a basic safety measure to remove any HTML tags.
            // esc_html is then used to escape any remaining characters that could break out of the style tag.
            echo '<style type="text/css" id="sam-reading-time-custom-styles">' . esc_html( wp_strip_all_tags( $custom_styles ) ) . '</style>';
        }
    }

    /**
     * Displays the HTML for the plugin's settings page.
     */
    public function options_page_html() {
        // Check user capabilities.
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        ?>
        <div class="wrap">
            <style>
                /* General Styling */
                .sam-settings-container {
                    background-color: #fcfcfc;
                    padding: 40px;
                    border-radius: 15px;
                    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
                    max-width: 900px;
                    margin: 40px auto;
                    font-family: 'Inter', sans-serif;
                    color: #333;
                    line-height: 1.7;
                    border: 1px solid #e0e0e0;
                    direction: ltr; /* Ensure LTR for admin page */
                    text-align: left; /* Ensure left alignment for admin page */
                }

                h1 {
                    color: #007bff;
                    font-size: 2.8em;
                    margin-bottom: 30px;
                    text-align: center;
                    font-weight: 700;
                    border-bottom: 3px solid #e9f5ff;
                    padding-bottom: 15px;
                    text-transform: uppercase;
                    letter-spacing: 1px;
                }

                h2 {
                    color: #34495e;
                    font-size: 2em;
                    margin-top: 45px;
                    margin-bottom: 25px;
                    border-bottom: 2px solid #f0f4f7;
                    padding-bottom: 10px;
                    font-weight: 600;
                }

                p {
                    margin-bottom: 18px;
                }

                /* Form Styling */
                form {
                    background-color: #ffffff;
                    padding: 35px;
                    border-radius: 12px;
                    box-shadow: 0 3px 10px rgba(0, 0, 0, 0.04);
                    border: 1px solid #f0f0f0;
                }

                .form-table th {
                    padding-top: 20px;
                    padding-bottom: 20px;
                    font-weight: 600;
                    vertical-align: middle;
                    width: 35%;
                    color: #555;
                    text-align: left; /* Ensure left alignment */
                }

                .form-table td {
                    padding-top: 20px;
                    padding-bottom: 20px;
                    text-align: left; /* Ensure left alignment */
                }

                .regular-text, input[type="number"], select, textarea.code {
                    width: 100%;
                    max-width: 450px;
                    padding: 12px 15px;
                    border: 1px solid #dcdcdc;
                    border-radius: 8px;
                    box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.05);
                    transition: border-color 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
                    font-size: 1em;
                    direction: ltr; /* Ensure LTR for input fields */
                    text-align: left; /* Ensure left alignment for input fields */
                }

                .regular-text:focus, input[type="number"]:focus, select:focus, textarea.code:focus {
                    border-color: #007bff;
                    box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.25);
                    outline: none;
                }

                input[type="checkbox"] {
                    width: auto;
                    margin-right: 8px;
                    transform: scale(1.2);
                    vertical-align: middle;
                }

                .description {
                    font-size: 0.9em;
                    color: #7a7a7a;
                    margin-top: 8px;
                    line-height: 1.5;
                }

                textarea.code {
                    font-family: 'Consolas', 'Monaco', monospace;
                    background-color: #f8f9fa;
                    border: 1px solid #e0e7ed;
                }

                /* Submit Button */
                .submit {
                    padding-top: 30px;
                    text-align: center;
                }

                .submit .button-primary {
                    background: linear-gradient(to right, #007bff, #0056b3);
                    border: none;
                    box-shadow: 0 5px 15px rgba(0, 123, 255, 0.25);
                    text-shadow: none;
                    border-radius: 8px;
                    padding: 14px 30px;
                    font-size: 1.2em;
                    font-weight: 600;
                    height: auto;
                    cursor: pointer;
                    transition: all 0.3s ease;
                    color: #fff;
                }

                .submit .button-primary:hover,
                .submit .button-primary:focus {
                    background: linear-gradient(to right, #0056b3, #004085);
                    box-shadow: 0 8px 20px rgba(0, 123, 255, 0.35);
                    transform: translateY(-2px);
                }

                /* Usage Instructions */
                .usage-instructions {
                    background-color: #e6f7ff;
                    border: 1px solid #b3e0ff;
                    border-radius: 12px;
                    padding: 30px;
                    margin-top: 50px;
                    color: #0056b3;
                    box-shadow: 0 3px 10px rgba(0, 0, 0, 0.03);
                }

                .usage-instructions h3 {
                    color: #004085;
                    font-size: 1.6em;
                    margin-top: 0;
                    margin-bottom: 20px;
                    font-weight: 600;
                }

                .usage-instructions code {
                    background-color: #cceeff;
                    padding: 4px 8px;
                    border-radius: 5px;
                    font-family: 'Consolas', 'Monaco', monospace;
                    color: #333;
                    font-size: 0.95em;
                    border: 1px solid #aaddff;
                    direction: ltr;
                    text-align: left;
                }

                .usage-instructions ul {
                    list-style-type: '🚀 '; /* Custom bullet point */
                    margin-left: 25px;
                    padding-left: 0;
                }

                .usage-instructions ul li {
                    margin-bottom: 10px;
                }

                .usage-instructions strong {
                    color: #004085;
                }

                pre {
                    background-color: #cceeff;
                    padding: 15px;
                    border-radius: 8px;
                    border: 1px solid #aaddff;
                    overflow-x: auto;
                    font-size: 0.9em;
                    direction: ltr;
                    text-align: left;
                }
            </style>

            <div class="sam-settings-container">
                <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
                <form action="options.php" method="post">
                    <?php
                    // Output security fields for the registered setting.
                    settings_fields( 'sam_reading_time' );
                    // Output settings sections and fields.
                    do_settings_sections( 'sam-reading-time' );
                    // Output save changes button.
                    submit_button( esc_html__( 'Save Changes', 'Sam-reading-time' ) );
                    ?>
                </form>

                <div class="usage-instructions">
                    <h2><?php esc_html_e( 'How to Use the Sam Reading Time Plugin', 'Sam-reading-time' ); ?></h2>
                    <p><?php esc_html_e( 'This plugin allows you to display the estimated reading time of your posts and pages using a simple shortcode. All display formats and calculation settings are managed from this page.', 'Sam-reading-time' ); ?></p>

                    <h3><?php esc_html_e( 'Basic Usage', 'Sam-reading-time' ); ?></h3>
                    <p><?php esc_html_e( 'Simply add the following shortcode anywhere in your post or page content:', 'Sam-reading-time' ); ?></p>
                    <p><code>[sam_reading_time]</code></p>
                    <p><?php esc_html_e( 'This will display the reading time based on the global settings configured above.', 'Sam-reading-time' ); ?></p>

                    <h3><?php esc_html_e( 'Custom Styling', 'Sam-reading-time' ); ?></h3>
                    <p><?php esc_html_e( 'The output of the shortcode is wrapped in an HTML tag with the default class ', 'Sam-reading-time' ); ?><code>.reading-time</code>.
                    <?php esc_html_e( 'You can use the "Custom CSS Styles" field for advanced styling.', 'Sam-reading-time' ); ?></p>
                    <p><?php esc_html_e( 'Example CSS for the ', 'Sam-reading-time' ); ?><code>.reading-time</code> <?php esc_html_e( 'class:', 'Sam-reading-time' ); ?></p>
                    <pre><code>.reading-time {
    font-weight: bold;
    color: #007bff;
    font-size: 0.95em;
    margin-right: 10px; /* Adjust for RTL if needed in your theme */
    padding: 5px 10px;
    background-color: #f0f8ff;
    border-radius: 5px;
    display: inline-block;
}</code></pre>
                </div>
            </div>
        </div>
        <?php
    }
}

// Create an instance of the plugin class to activate functionalities.
new Sam_Reading_Time_Plugin();

?>
