<?php
// Doğrudan erişimi engelle
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * WordPress çekirdek tabloları dışındaki tüm tabloları siler.
 *
 * @return array|WP_Error Silinen tabloların listesi veya WP_Error nesnesi.
 */
function wprc_delete_non_core_tables() {
    global $wpdb;
    $messages = [];
    $core_tables = [
        'commentmeta', 'comments', 'links', 'options', 'postmeta', 'posts',
        'term_relationships', 'term_taxonomy', 'termmeta', 'terms',
        'usermeta', 'users'
    ];
    $all_tables_raw = $wpdb->get_results("SHOW TABLES LIKE '{$wpdb->prefix}%'", ARRAY_N);
    $all_tables = array_map(function($row) { return $row[0]; }, $all_tables_raw);

    $wpdb->query('SET FOREIGN_KEY_CHECKS=0;');
    foreach ($all_tables as $table_name) {
        $table_name_without_prefix = str_replace($wpdb->prefix, '', $table_name);
        if (!in_array($table_name_without_prefix, $core_tables)) {
            $wpdb->query("DROP TABLE IF EXISTS `{$table_name}`");
            $messages[] = "$table_name tablosu silindi.";
        }
    }
    $wpdb->query('SET FOREIGN_KEY_CHECKS=1;');
    return $messages;
}

/**
 * WordPress çekirdek içerik tablolarını boşaltır (TRUNCATE).
 * users, usermeta ve options tablolarına dokunmaz.
 *
 * @return array|WP_Error Boşaltılan tabloların listesi veya WP_Error nesnesi.
 */
function wprc_truncate_core_content_tables() {
    global $wpdb;
    $truncated_tables = [];
    $messages = []; // Mesajları toplamak için

    $content_tables_to_truncate = [
        'commentmeta', 'comments', 'links', 'postmeta', 'posts',
        'term_relationships', 'term_taxonomy', 'termmeta', 'terms'
    ];
    $all_truncated_successfully = true;

    foreach ( $content_tables_to_truncate as $table_short_name ) {
        $table_name = $wpdb->prefix . $table_short_name;
        $result = $wpdb->query("TRUNCATE TABLE `{$table_name}`");
        if ($result === false) {
            // TRUNCATE başarısızsa DELETE FROM ile sil
            $wpdb->query("DELETE FROM `{$table_name}` WHERE 1=1");
            $messages[] = "$table_name tablosu DELETE FROM ile temizlendi.";
        } else {
            $truncated_tables[] = $table_name;
            wprc_debug_log('Core content table TRUNCATED: ' . $table_name);
        }
    }
    
    if ( ! empty( $truncated_tables ) ) {
        $messages[] = __( 'Çekirdek içerik tabloları başarıyla boşaltıldı (TRUNCATE): ', 'wp-reset-core' ) . implode( ', ', array_map('esc_html', $truncated_tables) );
    } elseif ($all_truncated_successfully) { // Hiç tablo truncate edilmedi ama hata da yoktu (belki liste boştu?)
        $messages[] = __( 'Boşaltılacak çekirdek içerik tablosu bulunamadı veya zaten boştu.', 'wp-reset-core' );
    }
    // Eğer bazıları truncate edilemediyse, $messages zaten WP_Error nesnelerini içeriyor olacak.

    return $messages;
}

function wprc_reset_database_tables() {
    global $wpdb;
    $messages = [];
    // SADECE içerik tabloları!
    $content_tables = [
        'commentmeta', 'comments', 'links', 'postmeta', 'posts',
        'term_relationships', 'term_taxonomy', 'termmeta', 'terms'
    ];
    foreach ($content_tables as $table_short_name) {
        $table_name = $wpdb->prefix . $table_short_name;
        $result = $wpdb->query("TRUNCATE TABLE `{$table_name}`");
        if ($result === false) {
            $wpdb->query("DELETE FROM `{$table_name}` WHERE 1=1");
            $messages[] = "$table_name tablosu DELETE FROM ile temizlendi.";
        } else {
            $messages[] = "$table_name tablosu TRUNCATE ile temizlendi.";
        }
    }
    return $messages;
}
?>