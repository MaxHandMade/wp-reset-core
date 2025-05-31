<?php
// Doğrudan erişimi engelle
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Ana "Tam Sıfırlama" işlemini gerçekleştirir.
 */
function wprc_perform_full_reset() {
    if ( ! current_user_can( 'manage_options' ) ) { return new WP_Error( 'wprc_permission_denied', __( 'Bu işlemi yapma yetkiniz yok.', 'wp-reset-core' ) ); }
    
    $messages = [];
    $admin_user_id = get_current_user_id();

    if ( ! $admin_user_id ) {
        return new WP_Error( 'wprc_no_user', __( 'Geçerli yönetici kullanıcı ID\'si alınamadı. İşlem durduruldu.', 'wp-reset-core' ) );
    }
    $messages[] = sprintf( __( 'İşlemi yapan yönetici ID: %d.', 'wp-reset-core' ), (int)$admin_user_id );

    global $wp_filesystem;
    if ( empty( $wp_filesystem ) ) {
        require_once ABSPATH . '/wp-admin/includes/file.php';
        if ( ! WP_Filesystem() ) {
            return new WP_Error( 'wprc_filesystem_init_failed_main', __( 'WordPress Filesystem API başlatılamadı. FTP bilgileri gerekebilir.', 'wp-reset-core' ) );
        }
    }
    if ( ! $wp_filesystem ) {
        return new WP_Error( 'wprc_filesystem_error_main', __( 'WordPress Filesystem API kullanılamıyor.', 'wp-reset-core' ) );
    }

    // === ADIM 0: WORDPRESS DIŞI TABLOLARI SİL ===
    wprc_debug_log("Starting Step 0: Delete Non-Core Tables");
    if (function_exists('wprc_delete_non_core_tables')) {
        $result = wprc_delete_non_core_tables();
        if (is_wp_error($result)) { $messages[] = $result->get_error_message(); }
        elseif (is_array($result)) { $messages = array_merge($messages, $result); }
    }

    // === ADIM 1: KAPSAMLI VERİTABANI SIFIRLAMA ===
    // Bu, WordPress dışı tabloları SİLER VE TÜM çekirdek içerik/meta tablolarını TRUNCATE eder.
    wprc_debug_log("Starting Step 1: Comprehensive Database Reset");
    if (function_exists('wprc_reset_database_tables')) {
        $result = wprc_reset_database_tables(); 
        if (is_wp_error($result)) { $messages[] = $result->get_error_message(); } 
        elseif (is_array($result)) { $messages = array_merge($messages, $result); }
    } else { $messages[] = new WP_Error('wprc_func_missing_db_reset', 'wprc_reset_database_tables fonksiyonu bulunamadı.'); }

    // === ADIM 2: DİĞER KULLANICILARI SIFIRLA ===
    wprc_debug_log("Starting Step 2: User Reset");
    if (function_exists('wprc_delete_other_users')) {
        $result = wprc_delete_other_users($admin_user_id);
        if (is_wp_error($result)) { $messages[] = $result->get_error_message(); } 
        elseif (is_array($result)) { $messages = array_merge($messages, $result); }
    }

    // === ADIM 3: TEMAYI VARSAYILANA ÇEVİR (Diğer temaları silmeden ÖNCE) ===
    wprc_debug_log("Starting Step 3: Ensure Default Theme Active");
    $active_default_theme_slug = null;
    if (function_exists('wprc_ensure_default_theme_active')) {
        $result = wprc_ensure_default_theme_active();
        if (is_wp_error($result)) {
            $messages[] = $result->get_error_message();
            $messages[] = __("Kritik: Varsayılan tema aktif edilemedi. Sıfırlama işlemi bu noktada DURDURULMAYACAK, diğer adımlar devam edecek.", 'wp-reset-core');
            wprc_debug_log('FAILED to switch theme, devam ediliyor.');
        } elseif (is_array($result) && isset($result['messages'])) {
            $messages = array_merge($messages, $result['messages']);
            $active_default_theme_slug = $result['active_theme_slug'];
        }
        if (empty($active_default_theme_slug)) {
            $messages[] = __("Kritik: Aktif edilecek varsayılan tema belirlenemedi. Sıfırlama işlemi bu noktada DURDURULMAYACAK, diğer adımlar devam edecek.", 'wp-reset-core');
            wprc_debug_log('No default theme slug found, devam ediliyor.');
        }
    } else { $messages[] = new WP_Error('wprc_func_missing_theme_ensure', 'wprc_ensure_default_theme_active fonksiyonu bulunamadı.'); }

    // === ADIM 4: EKLENTİLERİ SİL ===
    wprc_debug_log("Starting Step 4: Deactivate and Delete Other Plugins");
    if (function_exists('wprc_deactivate_and_delete_other_plugins')) {
        $result = wprc_deactivate_and_delete_other_plugins();
        if (is_wp_error($result)) { $messages[] = $result->get_error_message(); } 
        elseif (is_array($result)) { $messages = array_merge($messages, $result); }
    }

    // === ADIM 5: DİĞER TEMALARI SİL (Varsayılan tema aktif edildikten ve eklentiler silindikten SONRA) ===
    wprc_debug_log("Starting Step 5: Delete Non-Default Themes");
    if (function_exists('wprc_delete_non_default_themes') && $active_default_theme_slug) {
        $result = wprc_delete_non_default_themes($active_default_theme_slug);
        if (is_wp_error($result)) { $messages[] = $result->get_error_message(); } 
        elseif (is_array($result)) { $messages = array_merge($messages, $result); }
    }

    // === ADIM 6: UPLOADS VE wp-content DOSYA SİSTEMİ TEMİZLİĞİ ===
    wprc_debug_log("Starting Step 6: Uploads and wp-content Cleanup");
    $delete_uploads_for_full_reset = true; 
    if ($delete_uploads_for_full_reset && function_exists('wprc_empty_uploads_folder')) {
        $result = wprc_empty_uploads_folder();
        if (is_wp_error($result)) { $messages[] = $result->get_error_message(); } 
        elseif (is_array($result)) { $messages = array_merge($messages, $result); }
    }
    // wprc_post_reset_health_cleanup (main-reset-controller.php içinde tanımlı)
    $health_check_msgs = wprc_post_reset_health_cleanup(); 
    if (is_array($health_check_msgs)) { $messages = array_merge($messages, $health_check_msgs); }
    
    // === ADIM 7: WP_OPTIONS SIFIRLA, WORDPRESS VARSAYILANLARINI YÜKLE VE GENEL TEMİZLİK ===
    wprc_debug_log("Starting Step 7: Reset Options and Install WordPress Defaults");
    if (function_exists('wprc_install_defaults_and_cleanup_options')) { // İsim wordpress-defaults.php'deki ile eşleşmeli
        $result = wprc_install_defaults_and_cleanup_options($admin_user_id);
        if (is_wp_error($result)) { $messages[] = $result->get_error_message(); } 
        elseif (is_array($result)) { $messages = array_merge($messages, $result); }
    } else { $messages[] = new WP_Error('wprc_func_missing_install_defaults', 'wprc_install_defaults_and_cleanup_options fonksiyonu bulunamadı.'); }
    
    // === ADIM 8: .HTACCESS SIFIRLA ===
    wprc_debug_log("Starting Step 8: Reset .htaccess");
    if (function_exists('wprc_reset_htaccess')) {
        $result = wprc_reset_htaccess();
        if (is_wp_error($result)) { $messages[] = $result->get_error_message(); } 
        elseif (is_array($result)) { $messages = array_merge($messages, $result); }
    }
    // .htaccess_old geri taşıma mantığı kaldırıldı, wprc_reset_htaccess zaten dosyayı oluşturur/boşaltır.
    
    // Adım 9: Son bir rewrite flush (kalıcı bağlantıları ve rolleri yeniden oluşturmak için)
    wprc_debug_log("Starting Step 9: Final Rewrite Flush");
    flush_rewrite_rules(true);
    // Kullanıcı rolleri wp_install_defaults tarafından ayarlanır, ekstra bir şeye gerek olmamalı.

    $messages[] = __("Tam Sıfırlama işlemi başarıyla tamamlandı. WordPress ilk kurulum haline döndürüldü.", 'wp-reset-core');
    return $messages;
}

// === YARDIMCI FONKSİYONLAR (Seçimli Temizlik, Veritabanı Araçları vb. için) ===
// (Bir önceki paylaştığınız main-reset-controller.php dosyasındaki
// wprc_delete_selected_content_types, wprc_perform_partial_cleanup, 
// wprc_cleanup_plugin_database, wprc_post_reset_health_cleanup,
// wprc_cleanup_core_table_garbage fonksiyonları buraya gelecek.)
// Bu fonksiyonların içerikleri bir önceki mesajınızdaki gibi kalabilir.
// Sadece wprc_perform_full_reset fonksiyonunu optimize ettim.

// ... (main-reset-controller.php dosyasının geri kalanı - önceki mesajınızdaki yardımcı fonksiyonlar) ...
// (( Bir önceki mesajınızdaki wprc_delete_selected_content_types ve diğer yardımcı fonksiyonları buraya kopyalıyorum ))

function wprc_delete_selected_content_types($selected_content_types = array()) {
    global $wpdb; $messages = array(); $deleted_counts = array();
    if (empty($selected_content_types)) return $messages;
    if (in_array('post', $selected_content_types)) { $deleted = $wpdb->query("DELETE FROM {$wpdb->posts} WHERE post_type = 'post'"); if($deleted !== false) $deleted_counts[__('Yazılar','wp-reset-core')] = $deleted; }
    if (in_array('page', $selected_content_types)) { $deleted = $wpdb->query("DELETE FROM {$wpdb->posts} WHERE post_type = 'page'"); if($deleted !== false) $deleted_counts[__('Sayfalar','wp-reset-core')] = $deleted; }
    if (in_array('attachment', $selected_content_types)) { $deleted = $wpdb->query("DELETE FROM {$wpdb->posts} WHERE post_type = 'attachment'"); if($deleted !== false) $deleted_counts[__('Medya','wp-reset-core')] = $deleted; }
    if (in_array('comments', $selected_content_types)) { $deleted = $wpdb->query("TRUNCATE TABLE {$wpdb->comments}"); if($deleted !== false) $deleted_counts[__('Yorumlar','wp-reset-core')] = $deleted; $wpdb->query("TRUNCATE TABLE {$wpdb->commentmeta}"); }
    if (in_array(WPRC_CUSTOM_POST_TYPES_KEY, $selected_content_types)) {
        $builtin = array('post','page','attachment','revision','nav_menu_item','custom_css','customize_changeset','oembed_cache','user_request','wp_block','wp_template','wp_template_part','wp_global_styles','wp_navigation');
        $post_types = get_post_types(array('_builtin' => false), 'names');
        foreach ($post_types as $cpt) { if (!in_array($cpt, $builtin)) { $deleted = $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->posts} WHERE post_type = %s", $cpt)); if($deleted !== false) $deleted_counts[sprintf(__('Özel Yazı Tipi: %s','wp-reset-core'), $cpt)] = $deleted; } }
    }
    foreach ($deleted_counts as $label => $count) { $messages[] = sprintf(__('%s silindi: %d', 'wp-reset-core'), $label, $count); }
    return $messages;
}

function wprc_perform_partial_cleanup($selected_content_types = array(), $selected_plugins = array(), $selected_themes = array(), $delete_uploads = false, $plugin_db_cleanup = false) {
    $messages = array();
    if (!empty($selected_content_types)) { $result = wprc_delete_selected_content_types($selected_content_types); if (is_wp_error($result)) { $messages[] = $result->get_error_message(); } elseif (is_array($result)) { $messages = array_merge($messages, $result); } }
    if (!empty($selected_plugins)) {
        if ( ! function_exists( 'deactivate_plugins' ) || ! function_exists( 'delete_plugins' ) ) { require_once ABSPATH . 'wp-admin/includes/plugin.php'; }
        deactivate_plugins($selected_plugins, true); $delete_result = delete_plugins($selected_plugins);
        if (is_wp_error($delete_result)) { $messages[] = new WP_Error('wprc_partial_plugin_delete_failed', __('Bazı eklentiler silinirken hata oluştu: ', 'wp-reset-core') . $delete_result->get_error_message());
        } else {
            $messages[] = sprintf(__('Seçilen eklentiler silindi: %s', 'wp-reset-core'), implode(', ', array_map('esc_html', $selected_plugins)));
            if ($plugin_db_cleanup && function_exists('wprc_cleanup_plugin_database')) {
                foreach ($selected_plugins as $plugin_path) { $slug = dirname($plugin_path); $cleanup_msgs = wprc_cleanup_plugin_database($slug); if (is_array($cleanup_msgs)) { $messages = array_merge($messages, $cleanup_msgs); } }
            }
        }
    }
    if (!empty($selected_themes)) {
        if ( ! function_exists( 'delete_theme' ) ) { require_once ABSPATH . 'wp-admin/includes/theme.php'; }
        foreach ($selected_themes as $theme_slug) {
            if (get_option('stylesheet') === $theme_slug) { $messages[] = new WP_Error('wprc_active_theme_delete_attempt', sprintf(__('Aktif tema ("%s") güvenlik nedeniyle silinemez. Lütfen önce başka bir temayı aktif edin.', 'wp-reset-core'), $theme_slug)); continue; }
            $delete_result = delete_theme($theme_slug);
            if (is_wp_error($delete_result)) { $messages[] = new WP_Error('wprc_partial_theme_delete_failed', sprintf(__('"%s" teması silinirken hata: %s', 'wp-reset-core'), esc_html($theme_slug), $delete_result->get_error_message()));
            } else { $messages[] = sprintf(__('"%s" teması silindi.', 'wp-reset-core'), esc_html($theme_slug)); }
        }
    }
    if ($delete_uploads && function_exists('wprc_empty_uploads_folder')) { $result = wprc_empty_uploads_folder(); if (is_wp_error($result)) { $messages[] = $result->get_error_message(); } elseif (is_array($result)) { $messages = array_merge($messages, $result); } }
    return $messages;
}

function wprc_cleanup_plugin_database($plugin_slug) {
    global $wpdb; $messages = array();
    $like_pattern_option = $wpdb->esc_like($plugin_slug) . '%';
    $like_pattern_option_alt = '%' . $wpdb->esc_like($plugin_slug) . '%';
    $deleted_options = $wpdb->query( $wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", $like_pattern_option, $like_pattern_option_alt) );
    if ($deleted_options !== false) $messages[] = sprintf(__('%d adet potansiyel seçenek silindi (%s ile ilgili).', 'wp-reset-core'), $deleted_options, $plugin_slug);
    
    $like_pattern_table = $wpdb->esc_like($wpdb->prefix . str_replace('-', '_', $plugin_slug)) . '%';
    $tables = $wpdb->get_col($wpdb->prepare("SHOW TABLES LIKE %s", $like_pattern_table));
    $deleted_table_count = 0;
    foreach ($tables as $table) { if ( $wpdb->query("DROP TABLE IF EXISTS `$table`") !== false ) { $messages[] = sprintf(__('%s tablosu silindi.', 'wp-reset-core'), $table); $deleted_table_count++; } }
    if ($deleted_table_count > 0) $messages[] = sprintf(__('%d adet potansiyel eklenti tablosu silindi (%s ile ilgili).', 'wp-reset-core'), $deleted_table_count, $plugin_slug);
    
    return $messages;
}

function wprc_post_reset_health_cleanup() {
    global $wp_filesystem; if ( empty( $wp_filesystem ) ) { require_once ABSPATH . '/wp-admin/includes/file.php'; WP_Filesystem(); }
    $messages = array(); $wp_content = WP_CONTENT_DIR; $default_dirs = array('plugins', 'themes', 'uploads', 'languages', 'upgrade', 'mu-plugins', 'cache', 'logs'); // logs klasörü de eklendi
    $dirs = @scandir($wp_content);
    if ($dirs && is_array($dirs)) { foreach ($dirs as $dir) { if ($dir === '.' || $dir === '..') continue; $full_path = $wp_content . DIRECTORY_SEPARATOR . $dir; if (is_dir($full_path) && !in_array($dir, $default_dirs)) { if ($wp_filesystem->delete($full_path, true)) $messages[] = sprintf(__('wp-content içindeki "%s" klasörü silindi.', 'wp-reset-core'), $dir); } } }
    $special_files = array('advanced-cache.php', 'object-cache.php', 'db.php', 'debug.log'); // debug.log da eklendi
    foreach ($special_files as $file) { $file_path = $wp_content . DIRECTORY_SEPARATOR . $file; if ($wp_filesystem->exists($file_path)) { if ($wp_filesystem->delete($file_path)) $messages[] = sprintf(__('%s dosyası silindi.', 'wp-reset-core'), $file); } }
    $cache_dir = $wp_content . DIRECTORY_SEPARATOR . 'cache'; if ($wp_filesystem->is_dir($cache_dir)) { if ($wp_filesystem->delete($cache_dir, true)) $messages[] = __('wp-content/cache klasörü silindi.', 'wp-reset-core'); }
    return $messages;
}

function wprc_cleanup_core_table_garbage() {
    global $wpdb; $messages = [];
    // Bu fonksiyon, `wprc_install_defaults_and_cleanup_options` içinde
    // `wprc_cleanup_problematic_options_and_transients` tarafından daha hedefli yapıldığı için
    // genel "Tam Sıfırlama"da ayrıca çağrılmasına gerek kalmayabilir.
    // "Seçimli Temizlik Aracı" olarak kalması daha mantıklı.
    $option_patterns = ['elementor\_%', 'litespeed\_%', 'wpvivid\_%', 'woocommerce\_%', 'revslider\_%', 'yith\_%', 'woof\_%'];
    $meta_patterns = ['elementor\_%', 'litespeed\_%', 'wpvivid\_%', 'woocommerce\_%', 'revslider\_%', 'yith\_%', 'woof\_%'];
    $deleted_options_total = 0; $deleted_meta_total = 0;
    foreach ($option_patterns as $pattern) { $deleted = $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $pattern)); if ($deleted !== false) $deleted_options_total += $deleted; }
    if ($deleted_options_total > 0) $messages[] = sprintf(__('%d adet bilinen eklenti ön ekine sahip seçenek (çekirdek tablolardan) silindi.', 'wp-reset-core'), $deleted_options_total);
    
    foreach ($meta_patterns as $pattern) { $deleted = $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s", $pattern)); if ($deleted !== false) $deleted_meta_total += $deleted; }
    if ($deleted_meta_total > 0) $messages[] = sprintf(__('%d adet bilinen eklenti ön ekine sahip yazı meta verisi (çekirdek tablolardan) silindi.', 'wp-reset-core'), $deleted_meta_total);
    
    if(empty($messages)) $messages[] = __('Silinecek bilinen çekirdek tablo kalıntısı bulunamadı.', 'wp-reset-core');
    return $messages;
}
?>