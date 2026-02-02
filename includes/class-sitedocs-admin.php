<?php
/**
 * Admin class for SiteDocs
 *
 * @package SiteDocs
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * SiteDocs Admin Class
 *
 * Handles admin menu, pages, and asset enqueuing
 */
class SiteDocs_Admin {

    /**
     * Single instance
     *
     * @var SiteDocs_Admin|null
     */
    private static $instance = null;

    /**
     * Get single instance
     *
     * @return SiteDocs_Admin
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
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'admin_notices', array( $this, 'security_notices' ) );
    }

    /**
     * Display security-related admin notices
     */
    public function security_notices() {
        // Only show to admins on the SiteDocs page
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $screen = get_current_screen();
        if ( ! $screen || 'toplevel_page_sitedocs' !== $screen->id ) {
            return;
        }

        // Check if sodium is available
        if ( ! SiteDocs_Encryption::is_available() ) {
            ?>
            <div class="notice notice-error">
                <p><strong><?php esc_html_e( 'SiteDocs Error:', 'sitedocs' ); ?></strong>
                <?php esc_html_e( 'The sodium encryption library is not available on your server. Credential storage will not work. Please contact your hosting provider to enable the sodium PHP extension (included in PHP 7.2+).', 'sitedocs' ); ?></p>
            </div>
            <?php
        }
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __( 'SiteDocs', 'sitedocs' ),
            __( 'SiteDocs', 'sitedocs' ),
            'manage_options',
            'sitedocs',
            array( $this, 'render_main_page' ),
            'dashicons-media-document',
            80
        );
    }

    /**
     * Enqueue admin assets
     *
     * @param string $hook Current admin page hook
     */
    public function enqueue_assets( $hook ) {
        // Only load on our plugin pages
        if ( 'toplevel_page_sitedocs' !== $hook ) {
            return;
        }

        // Toast UI Editor CSS
        wp_enqueue_style(
            'toastui-editor',
            SITEDOCS_PLUGIN_URL . 'assets/vendor/toastui-editor/toastui-editor.min.css',
            array(),
            SITEDOCS_VERSION
        );

        // Toast UI Editor Dark Theme CSS
        wp_enqueue_style(
            'toastui-editor-dark',
            SITEDOCS_PLUGIN_URL . 'assets/vendor/toastui-editor/toastui-editor-dark.css',
            array( 'toastui-editor' ),
            SITEDOCS_VERSION
        );

        // Prism.js theme for syntax highlighting
        wp_enqueue_style(
            'prismjs',
            SITEDOCS_PLUGIN_URL . 'assets/vendor/prismjs/prism-tomorrow.min.css',
            array(),
            SITEDOCS_VERSION
        );

        // Toast UI Editor plugin for code syntax highlighting
        wp_enqueue_style(
            'toastui-editor-plugin-code-syntax-highlight',
            SITEDOCS_PLUGIN_URL . 'assets/vendor/toastui-editor/toastui-editor-plugin-code-syntax-highlight.min.css',
            array(),
            SITEDOCS_VERSION
        );

        // Plugin styles
        wp_enqueue_style(
            'sitedocs-admin',
            SITEDOCS_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            SITEDOCS_VERSION
        );

        // Toast UI Editor JS
        wp_enqueue_script(
            'toastui-editor',
            SITEDOCS_PLUGIN_URL . 'assets/vendor/toastui-editor/toastui-editor-all.min.js',
            array(),
            SITEDOCS_VERSION,
            true
        );

        // Toast UI Editor plugin for code syntax highlighting (includes Prism.js with all languages)
        wp_enqueue_script(
            'toastui-editor-plugin-code-syntax-highlight',
            SITEDOCS_PLUGIN_URL . 'assets/vendor/toastui-editor/toastui-editor-plugin-code-syntax-highlight-all.min.js',
            array( 'toastui-editor' ),
            SITEDOCS_VERSION,
            true
        );

        // Plugin JavaScript
        wp_enqueue_script(
            'sitedocs-admin',
            SITEDOCS_PLUGIN_URL . 'assets/js/admin.js',
            array( 'jquery', 'toastui-editor', 'toastui-editor-plugin-code-syntax-highlight' ),
            SITEDOCS_VERSION,
            true
        );

        // Localize script
        wp_localize_script(
            'sitedocs-admin',
            'sitedocsAdmin',
            array(
                'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
                'nonce'            => wp_create_nonce( 'sitedocs_nonce' ),
                'content'          => SiteDocs_Notes::get_content(),
                'lastSaved'        => SiteDocs_Notes::get_last_saved_formatted(),
                'canViewCredentials' => SiteDocs_Credentials::current_user_can_view(),
                'credentialTypes'  => SiteDocs_Credentials::get_types(),
                'strings'          => array(
                    'saving'           => __( 'Saving...', 'sitedocs' ),
                    'saved'            => __( 'Saved', 'sitedocs' ),
                    'saveError'        => __( 'Error saving', 'sitedocs' ),
                    'lastSaved'        => __( 'Last saved:', 'sitedocs' ),
                    'never'            => __( 'Never', 'sitedocs' ),
                    'confirmDelete'    => __( 'Are you sure you want to delete this credential?', 'sitedocs' ),
                    'copied'           => __( 'Copied!', 'sitedocs' ),
                    'copyFailed'       => __( 'Copy failed', 'sitedocs' ),
                    'verifyPassword'   => __( 'Please enter your WordPress password to continue.', 'sitedocs' ),
                    'passwordIncorrect' => __( 'Password incorrect. Please try again.', 'sitedocs' ),
                    'loading'          => __( 'Loading...', 'sitedocs' ),
                    'noCredentials'    => __( 'No credentials stored yet.', 'sitedocs' ),
                    'addCredential'    => __( 'Add Credential', 'sitedocs' ),
                    'editCredential'   => __( 'Edit Credential', 'sitedocs' ),
                ),
                'settings'         => get_option( 'sitedocs_settings', array() ),
            )
        );
    }

    /**
     * Render main admin page
     */
    public function render_main_page() {
        $can_view_credentials = SiteDocs_Credentials::current_user_can_view();
        $is_admin = current_user_can( 'manage_options' );
        ?>
        <div class="wrap sitedocs-wrap">
            <h1 class="sitedocs-title">
                <span class="dashicons dashicons-media-document"></span>
                <?php esc_html_e( 'SiteDocs', 'sitedocs' ); ?>
            </h1>

            <div class="sitedocs-tabs">
                <button type="button" class="sitedocs-tab active" data-tab="notes">
                    <span class="dashicons dashicons-edit"></span>
                    <?php esc_html_e( 'Notes', 'sitedocs' ); ?>
                </button>
                <?php if ( $can_view_credentials ) : ?>
                <button type="button" class="sitedocs-tab" data-tab="credentials">
                    <span class="dashicons dashicons-lock"></span>
                    <?php esc_html_e( 'Credentials', 'sitedocs' ); ?>
                </button>
                <button type="button" class="sitedocs-tab" data-tab="activity">
                    <span class="dashicons dashicons-list-view"></span>
                    <?php esc_html_e( 'Activity Log', 'sitedocs' ); ?>
                </button>
                <?php endif; ?>
                <?php if ( $is_admin ) : ?>
                <button type="button" class="sitedocs-tab" data-tab="settings">
                    <span class="dashicons dashicons-admin-generic"></span>
                    <?php esc_html_e( 'Settings', 'sitedocs' ); ?>
                </button>
                <?php endif; ?>

                <div class="sitedocs-tab-actions">
                    <button type="button" id="sitedocs-theme-toggle" class="sitedocs-theme-toggle" title="<?php esc_attr_e( 'Toggle dark/light mode', 'sitedocs' ); ?>">
                        <span class="dashicons dashicons-lightbulb"></span>
                    </button>
                </div>
            </div>

            <!-- Notes Tab -->
            <div class="sitedocs-tab-content active" id="tab-notes">
                <div class="sitedocs-editor-header">
                    <div class="sitedocs-save-status">
                        <span class="sitedocs-last-saved">
                            <?php esc_html_e( 'Last saved:', 'sitedocs' ); ?>
                            <span id="sitedocs-last-saved-time"><?php echo esc_html( SiteDocs_Notes::get_last_saved_formatted() ); ?></span>
                        </span>
                        <span id="sitedocs-save-indicator" class="sitedocs-save-indicator"></span>
                    </div>
                    <button type="button" id="sitedocs-save-btn" class="button button-primary">
                        <span class="dashicons dashicons-saved"></span>
                        <?php esc_html_e( 'Save', 'sitedocs' ); ?>
                    </button>
                </div>
                <div id="sitedocs-editor"></div>
            </div>

            <?php if ( $can_view_credentials ) : ?>
            <!-- Credentials Tab -->
            <div class="sitedocs-tab-content" id="tab-credentials">
                <div class="sitedocs-credentials-header">
                    <h2><?php esc_html_e( 'Secure Credentials', 'sitedocs' ); ?></h2>
                    <button type="button" id="sitedocs-add-credential" class="button button-primary">
                        <span class="dashicons dashicons-plus-alt"></span>
                        <?php esc_html_e( 'Add Credential', 'sitedocs' ); ?>
                    </button>
                </div>
                <div id="sitedocs-credentials-list" class="sitedocs-credentials-list">
                    <!-- Credentials loaded via JS -->
                </div>
            </div>

            <!-- Activity Log Tab -->
            <div class="sitedocs-tab-content" id="tab-activity">
                <div class="sitedocs-activity-header">
                    <h2><?php esc_html_e( 'Activity Log', 'sitedocs' ); ?></h2>
                    <div class="sitedocs-activity-filters">
                        <select id="sitedocs-activity-filter-action">
                            <option value=""><?php esc_html_e( 'All Actions', 'sitedocs' ); ?></option>
                            <?php foreach ( SiteDocs_Audit_Log::get_action_types() as $type => $label ) : ?>
                            <option value="<?php echo esc_attr( $type ); ?>"><?php echo esc_html( $label ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div id="sitedocs-activity-log" class="sitedocs-activity-log">
                    <!-- Activity log loaded via JS -->
                </div>
                <div id="sitedocs-activity-pagination" class="sitedocs-pagination"></div>
            </div>
            <?php endif; ?>

            <?php if ( $is_admin ) : ?>
            <!-- Settings Tab -->
            <div class="sitedocs-tab-content" id="tab-settings">
                <?php $this->render_settings_tab(); ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Credential Modal -->
        <div id="sitedocs-credential-modal" class="sitedocs-modal">
            <div class="sitedocs-modal-content">
                <div class="sitedocs-modal-header">
                    <h3 id="sitedocs-modal-title"><?php esc_html_e( 'Add Credential', 'sitedocs' ); ?></h3>
                    <button type="button" class="sitedocs-modal-close">&times;</button>
                </div>
                <form id="sitedocs-credential-form">
                    <input type="hidden" id="credential-id" name="id" value="">

                    <div class="sitedocs-form-row">
                        <label for="credential-label"><?php esc_html_e( 'Label', 'sitedocs' ); ?> <span class="required">*</span></label>
                        <input type="text" id="credential-label" name="label" required>
                    </div>

                    <div class="sitedocs-form-row">
                        <label for="credential-type"><?php esc_html_e( 'Type', 'sitedocs' ); ?> <span class="required">*</span></label>
                        <select id="credential-type" name="type" required>
                            <?php foreach ( SiteDocs_Credentials::get_types() as $type => $label ) : ?>
                            <option value="<?php echo esc_attr( $type ); ?>"><?php echo esc_html( $label ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Username/Password fields -->
                    <div class="sitedocs-form-row credential-field" data-type="username_password">
                        <label for="credential-username"><?php esc_html_e( 'Username', 'sitedocs' ); ?></label>
                        <input type="text" id="credential-username" name="username" autocomplete="off">
                    </div>
                    <div class="sitedocs-form-row credential-field" data-type="username_password">
                        <label for="credential-password"><?php esc_html_e( 'Password', 'sitedocs' ); ?></label>
                        <div class="sitedocs-password-field">
                            <input type="password" id="credential-password" name="password" autocomplete="off">
                            <button type="button" class="sitedocs-toggle-visibility" data-target="credential-password">
                                <span class="dashicons dashicons-visibility"></span>
                            </button>
                        </div>
                    </div>

                    <!-- API Key field -->
                    <div class="sitedocs-form-row credential-field" data-type="api_key" style="display:none;">
                        <label for="credential-api-key"><?php esc_html_e( 'API Key', 'sitedocs' ); ?></label>
                        <div class="sitedocs-password-field">
                            <input type="password" id="credential-api-key" name="api_key" autocomplete="off">
                            <button type="button" class="sitedocs-toggle-visibility" data-target="credential-api-key">
                                <span class="dashicons dashicons-visibility"></span>
                            </button>
                        </div>
                    </div>

                    <!-- SSH Key field -->
                    <div class="sitedocs-form-row credential-field" data-type="ssh_key" style="display:none;">
                        <label for="credential-ssh-key"><?php esc_html_e( 'SSH Key / Certificate', 'sitedocs' ); ?></label>
                        <div class="sitedocs-password-field">
                            <textarea id="credential-ssh-key" name="ssh_key" rows="6" autocomplete="off" class="sitedocs-secure-textarea"></textarea>
                            <button type="button" class="sitedocs-toggle-visibility" data-target="credential-ssh-key">
                                <span class="dashicons dashicons-visibility"></span>
                            </button>
                        </div>
                    </div>

                    <!-- Secure Note field -->
                    <div class="sitedocs-form-row credential-field" data-type="secure_note" style="display:none;">
                        <label for="credential-secure-note"><?php esc_html_e( 'Secure Note', 'sitedocs' ); ?></label>
                        <div class="sitedocs-password-field">
                            <textarea id="credential-secure-note" name="secure_note" rows="6" autocomplete="off" class="sitedocs-secure-textarea"></textarea>
                            <button type="button" class="sitedocs-toggle-visibility" data-target="credential-secure-note">
                                <span class="dashicons dashicons-visibility"></span>
                            </button>
                        </div>
                    </div>

                    <div class="sitedocs-form-row">
                        <label for="credential-url"><?php esc_html_e( 'URL (optional)', 'sitedocs' ); ?></label>
                        <input type="url" id="credential-url" name="url">
                    </div>

                    <div class="sitedocs-form-row">
                        <label for="credential-notes"><?php esc_html_e( 'Notes (optional, not encrypted)', 'sitedocs' ); ?></label>
                        <textarea id="credential-notes" name="notes" rows="3"></textarea>
                    </div>

                    <div class="sitedocs-modal-footer">
                        <button type="button" class="button sitedocs-modal-cancel"><?php esc_html_e( 'Cancel', 'sitedocs' ); ?></button>
                        <button type="submit" class="button button-primary"><?php esc_html_e( 'Save', 'sitedocs' ); ?></button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Password Verification Modal -->
        <div id="sitedocs-password-modal" class="sitedocs-modal">
            <div class="sitedocs-modal-content sitedocs-modal-small">
                <div class="sitedocs-modal-header">
                    <h3><?php esc_html_e( 'Verify Your Identity', 'sitedocs' ); ?></h3>
                    <button type="button" class="sitedocs-modal-close">&times;</button>
                </div>
                <form id="sitedocs-password-form">
                    <p><?php esc_html_e( 'Please enter your WordPress password to access credentials.', 'sitedocs' ); ?></p>
                    <div class="sitedocs-form-row">
                        <label for="verify-password"><?php esc_html_e( 'Password', 'sitedocs' ); ?></label>
                        <input type="password" id="verify-password" name="password" required autocomplete="current-password">
                    </div>
                    <div id="password-error" class="sitedocs-error" style="display:none;"></div>
                    <div class="sitedocs-modal-footer">
                        <button type="button" class="button sitedocs-modal-cancel"><?php esc_html_e( 'Cancel', 'sitedocs' ); ?></button>
                        <button type="submit" class="button button-primary"><?php esc_html_e( 'Verify', 'sitedocs' ); ?></button>
                    </div>
                </form>
            </div>
        </div>
        <?php
    }

    /**
     * Render settings tab content
     */
    private function render_settings_tab() {
        $settings = get_option( 'sitedocs_settings', array() );
        ?>
        <div class="sitedocs-settings">
            <h2><?php esc_html_e( 'Settings', 'sitedocs' ); ?></h2>

            <form id="sitedocs-settings-form">
                <h3><?php esc_html_e( 'Security Settings', 'sitedocs' ); ?></h3>

                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Password Verification', 'sitedocs' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="require_password_verification" value="1"
                                    <?php checked( ! empty( $settings['require_password_verification'] ) ); ?>>
                                <?php esc_html_e( 'Require password re-entry to reveal credentials', 'sitedocs' ); ?>
                            </label>
                            <p class="description">
                                <?php esc_html_e( 'When enabled, users must enter their WordPress password before viewing or copying credential values.', 'sitedocs' ); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Audit Log Retention', 'sitedocs' ); ?></th>
                        <td>
                            <input type="number" name="audit_log_retention_days" min="0" max="365"
                                value="<?php echo esc_attr( isset( $settings['audit_log_retention_days'] ) ? $settings['audit_log_retention_days'] : 90 ); ?>">
                            <?php esc_html_e( 'days', 'sitedocs' ); ?>
                            <p class="description">
                                <?php esc_html_e( 'Automatically delete audit logs older than this many days. Set to 0 to keep logs forever.', 'sitedocs' ); ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <h3><?php esc_html_e( 'User Access', 'sitedocs' ); ?></h3>

                <p class="description">
                    <?php esc_html_e( 'Grant or revoke access to the Credentials section for specific users. Only users with this permission can view, add, edit, or delete credentials.', 'sitedocs' ); ?>
                </p>

                <div id="sitedocs-user-access">
                    <?php $this->render_user_access_list(); ?>
                </div>

                <p class="submit">
                    <button type="submit" class="button button-primary"><?php esc_html_e( 'Save Settings', 'sitedocs' ); ?></button>
                    <span id="sitedocs-settings-status" class="sitedocs-save-indicator"></span>
                </p>
            </form>
        </div>
        <?php
    }

    /**
     * Render user access list
     */
    private function render_user_access_list() {
        // Get all users who can potentially have this capability
        $users = get_users( array(
            'role__in' => array( 'administrator', 'editor', 'author' ),
            'orderby'  => 'display_name',
            'order'    => 'ASC',
        ) );
        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'User', 'sitedocs' ); ?></th>
                    <th><?php esc_html_e( 'Role', 'sitedocs' ); ?></th>
                    <th><?php esc_html_e( 'Credentials Access', 'sitedocs' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $users as $user ) : ?>
                <tr>
                    <td>
                        <?php echo get_avatar( $user->ID, 32 ); ?>
                        <strong><?php echo esc_html( $user->display_name ); ?></strong>
                        <br>
                        <span class="description"><?php echo esc_html( $user->user_email ); ?></span>
                    </td>
                    <td>
                        <?php
                        $roles = array_map( 'ucfirst', $user->roles );
                        echo esc_html( implode( ', ', $roles ) );
                        ?>
                    </td>
                    <td>
                        <?php if ( in_array( 'administrator', $user->roles, true ) ) : ?>
                            <span class="sitedocs-access-badge access-granted">
                                <?php esc_html_e( 'Always (Admin)', 'sitedocs' ); ?>
                            </span>
                        <?php else : ?>
                            <label class="sitedocs-toggle">
                                <input type="checkbox" name="user_access[]" value="<?php echo esc_attr( $user->ID ); ?>"
                                    <?php checked( $user->has_cap( SITEDOCS_CREDENTIALS_CAP ) ); ?>>
                                <span class="sitedocs-toggle-slider"></span>
                            </label>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }
}
