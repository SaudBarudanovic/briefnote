<?php
/**
 * Plugin Name: SiteDocs
 * Plugin URI: https://github.com/SaudBarudanovic/sitedocs
 * Description: A live-rendering Markdown editor and secure credentials storage for developer documentation in the WordPress admin.
 * Version: 1.0.0
 * Author: Saud Barudanovic
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: sitedocs
 * Requires at least: 5.2
 * Requires PHP: 7.4
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Plugin constants
define( 'SITEDOCS_VERSION', '1.0.0' );
define( 'SITEDOCS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SITEDOCS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SITEDOCS_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Custom capability constant
define( 'SITEDOCS_CREDENTIALS_CAP', 'view_sitedocs_credentials' );

/**
 * Main plugin class
 */
final class SiteDocs {

    /**
     * Single instance of the class
     *
     * @var SiteDocs|null
     */
    private static $instance = null;

    /**
     * Get single instance of the class
     *
     * @return SiteDocs
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
        $this->includes();
        $this->init_hooks();
    }

    /**
     * Include required files
     */
    private function includes() {
        // Core classes
        require_once SITEDOCS_PLUGIN_DIR . 'includes/class-sitedocs-encryption.php';
        require_once SITEDOCS_PLUGIN_DIR . 'includes/class-sitedocs-database.php';
        require_once SITEDOCS_PLUGIN_DIR . 'includes/class-sitedocs-notes.php';
        require_once SITEDOCS_PLUGIN_DIR . 'includes/class-sitedocs-credentials.php';
        require_once SITEDOCS_PLUGIN_DIR . 'includes/class-sitedocs-audit-log.php';
        require_once SITEDOCS_PLUGIN_DIR . 'includes/class-sitedocs-admin.php';
        require_once SITEDOCS_PLUGIN_DIR . 'includes/class-sitedocs-ajax.php';
        require_once SITEDOCS_PLUGIN_DIR . 'includes/class-sitedocs-settings.php';
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        register_activation_hook( __FILE__, array( $this, 'activate' ) );
        register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );

        add_action( 'init', array( $this, 'init' ), 0 );

        // Initialize admin components - must be early enough for admin_menu hook
        if ( is_admin() ) {
            // Initialize admin and AJAX handlers immediately
            SiteDocs_Admin::instance();
            SiteDocs_Ajax::instance();
            SiteDocs_Settings::instance();

            // Schedule audit log cleanup
            add_action( 'admin_init', array( $this, 'schedule_cleanup' ) );
        }
    }

    /**
     * Plugin activation
     */
    public function activate() {
        // Create database tables
        SiteDocs_Database::create_tables();

        // Add custom capability to administrator role
        $admin_role = get_role( 'administrator' );
        if ( $admin_role ) {
            $admin_role->add_cap( SITEDOCS_CREDENTIALS_CAP );
        }

        // Set default options
        if ( false === get_option( 'sitedocs_content' ) ) {
            add_option( 'sitedocs_content', '' );
        }
        if ( false === get_option( 'sitedocs_last_saved' ) ) {
            add_option( 'sitedocs_last_saved', '' );
        }
        if ( false === get_option( 'sitedocs_settings' ) ) {
            add_option( 'sitedocs_settings', array(
                'require_password_verification' => false,
                'audit_log_retention_days' => 90,
            ) );
        }

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Remove capability from all roles
        global $wp_roles;
        foreach ( $wp_roles->roles as $role_name => $role_info ) {
            $role = get_role( $role_name );
            if ( $role ) {
                $role->remove_cap( SITEDOCS_CREDENTIALS_CAP );
            }
        }

        flush_rewrite_rules();
    }

    /**
     * Initialize plugin
     */
    public function init() {
        // Text domain is automatically loaded by WordPress for plugins hosted on wordpress.org since WP 4.6.
    }

    /**
     * Schedule audit log cleanup and ensure tables exist
     */
    public function schedule_cleanup() {
        // Ensure database tables exist (in case activation hook didn't run)
        if ( ! SiteDocs_Database::tables_exist() ) {
            SiteDocs_Database::create_tables();
        }

        if ( ! wp_next_scheduled( 'sitedocs_cleanup_audit_logs' ) ) {
            wp_schedule_event( time(), 'daily', 'sitedocs_cleanup_audit_logs' );
        }
        add_action( 'sitedocs_cleanup_audit_logs', array( 'SiteDocs_Audit_Log', 'cleanup_old_logs' ) );
    }
}

/**
 * Returns the main instance of SiteDocs
 *
 * @return SiteDocs
 */
function sitedocs() {
    return SiteDocs::instance();
}

// Initialize the plugin
sitedocs();
