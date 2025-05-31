<?php
/**
 * Plugin Name:       WP Reset Core
 * Plugin URI:        https://maxhandmade.com/
 * Description:       WordPress sitelerini test amaçlı olarak ilk kurulum haline sıfırlar.
 * Version:           0.5.0 // Önemli güncellemeler için versiyon artırıldı
 * Author:            MHM Proje (proje@mhmproje.com)
 * Author URI:        https://maxhandmade.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-reset-core
 * Domain Path:       /languages
 */

// Doğrudan erişimi engelle
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Eklenti sabitleri
define( 'WPRC_VERSION', '0.5.0' );
define( 'WPRC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPRC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WPRC_INCLUDES_DIR', WPRC_PLUGIN_DIR . 'includes/' );
define( 'WPRC_PLUGIN_FILE', __FILE__ );
define( 'WPRC_CUSTOM_POST_TYPES_KEY', 'custom_post_types');

/**
 * Geliştirme sırasında debug loglaması için yardımcı fonksiyon.
 * Sadece WP_DEBUG ve WP_DEBUG_LOG true ise loglama yapar.
 */
if ( ! function_exists( 'wprc_debug_log' ) ) {
    function wprc_debug_log( $message ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG === true && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG === true ) {
            if ( is_array( $message ) || is_object( $message ) ) {
                error_log( '[WP Reset Core Debug] ' . print_r( $message, true ) );
            } else {
                error_log( '[WP Reset Core Debug] ' . $message );
            }
        }
    }
}

// Gerekli modülleri yükle
require_once WPRC_INCLUDES_DIR . 'admin/admin-menu.php';
require_once WPRC_INCLUDES_DIR . 'admin/admin-assets.php';
require_once WPRC_INCLUDES_DIR . 'admin/admin-page-display.php';

require_once WPRC_INCLUDES_DIR . 'reset-actions/database-reset.php';
require_once WPRC_INCLUDES_DIR . 'reset-actions/user-reset.php';
require_once WPRC_INCLUDES_DIR . 'reset-actions/file-system-reset.php';
require_once WPRC_INCLUDES_DIR . 'reset-actions/wordpress-defaults.php';
require_once WPRC_INCLUDES_DIR . 'reset-actions/main-reset-controller.php';
?>