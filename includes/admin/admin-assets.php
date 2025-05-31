<?php
// Doğrudan erişimi engelle
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Yönetici sayfası için CSS dosyalarını yükler.
 */
function wprc_enqueue_admin_styles( $hook_suffix ) {
    // Sadece kendi eklenti sayfamızda CSS'i yükle
    if ( 'toplevel_page_wp_reset_core_page' !== $hook_suffix ) {
        return;
    }
    wp_enqueue_style(
        'wprc-admin-style',
        WPRC_PLUGIN_URL . 'css/admin-style.css',
        [],
        WPRC_VERSION
    );
}
add_action( 'admin_enqueue_scripts', 'wprc_enqueue_admin_styles' );