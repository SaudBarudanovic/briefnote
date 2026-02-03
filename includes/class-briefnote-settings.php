<?php
/**
 * Settings class for Briefnote
 *
 * @package Briefnote
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Briefnote Settings Class
 *
 * Handles plugin settings and configuration
 */
class Briefnote_Settings {

    /**
     * Single instance
     *
     * @var Briefnote_Settings|null
     */
    private static $instance = null;

    /**
     * Get single instance
     *
     * @return Briefnote_Settings
     */
    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        // Settings are handled via AJAX in the admin page
    }

    /**
     * Get a setting value
     *
     * @param string $key     Setting key
     * @param mixed  $default Default value
     * @return mixed
     */
    public static function get( $key, $default = null ) {
        $settings = get_option( 'briefnote_settings', array() );
        return isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
    }

    /**
     * Set a setting value
     *
     * @param string $key   Setting key
     * @param mixed  $value Setting value
     * @return bool
     */
    public static function set( $key, $value ) {
        $settings = get_option( 'briefnote_settings', array() );
        $settings[ $key ] = $value;
        return update_option( 'briefnote_settings', $settings );
    }

    /**
     * Get default settings
     *
     * @return array
     */
    public static function get_defaults() {
        return array(
            'require_password_verification' => false,
            'audit_log_retention_days'      => 90,
        );
    }
}
