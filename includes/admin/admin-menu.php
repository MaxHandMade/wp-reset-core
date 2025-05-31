<?php
// Doğrudan erişimi engelle
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Yönetici menüsünü ekler.
 */
function wprc_add_admin_menu() {
    add_menu_page(
        __( 'WP Reset Core', 'wp-reset-core' ),
        __( 'WP Reset Core', 'wp-reset-core' ),
        'manage_options',
        'wp_reset_core_page',
        'wprc_admin_page_html', // Bu fonksiyon admin-page-display.php içinde tanımlı
        'dashicons-shield-alt',
        85
    );
}
add_action( 'admin_menu', 'wprc_add_admin_menu' );