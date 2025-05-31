<?php
// Doğrudan erişimi engelle
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * WordPress varsayılanlarını yükler, bilinen bazı eklenti kalıntılarını ve geçicileri temizler,
 * ve varsayılan bir ana sayfa ayarlar.
 *
 * @param int $admin_user_id Yönetici kullanıcısının ID'si.
 * @return array|WP_Error Başarı mesajları veya WP_Error nesnesi.
 */
function wprc_install_defaults_and_cleanup_options( $admin_user_id ) {
    $messages = [];

    if ( ! $admin_user_id || ! get_user_by( 'ID', $admin_user_id ) ) {
        return new WP_Error( 'wprc_admin_user_not_found_for_defaults', __( 'WordPress varsayılanları yüklenirken geçerli bir yönetici kullanıcı ID\'si bulunamadı.', 'wp-reset-core' ) );
    }

    // Adım 1: WordPress varsayılanlarını yükle (bu, birçok temel wp_options girdisini sıfırlar/ayarlar)
    if ( ! function_exists( 'populate_options' ) || ! function_exists( 'wp_install_defaults' ) ) {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    }
    
    // populate_options() temel seçenekleri yükler (siteurl, home, admin_email vb.)
    // Bu, wp_options tablosu TRUNCATE EDİLMEDİĞİ için, mevcut değerlerin üzerine yazabilir veya
    // eksik olanları ekleyebilir.
    populate_options();
    $messages[] = __( 'Temel WordPress seçenekleri (populate_options ile) kontrol edildi/yüklendi.', 'wp-reset-core' );
    wprc_debug_log('populate_options completed.');

    try {
        wp_install_defaults( $admin_user_id ); // Varsayılan içerik ve diğer seçenekleri ekler/günceller
        $messages[] = __( 'WordPress varsayılan içeriği ve ek seçenekleri (wp_install_defaults ile) başarıyla yüklendi/sıfırlandı.', 'wp-reset-core' );
        wprc_debug_log('wp_install_defaults completed successfully.');
    } catch ( Exception $e ) {
        wprc_debug_log('EXCEPTION during wp_install_defaults: ' . $e->getMessage());
        return new WP_Error( 'wprc_wp_install_defaults_exception', sprintf( __( 'wp_install_defaults çalıştırılırken bir istisna oluştu: %s', 'wp-reset-core' ), $e->getMessage() ) );
    }
    
    // wp_install_defaults sonrası bazı temel ayarları teyit edelim.
    update_option( 'blogname', 'Yeni Bir WordPress Sitesi' );
    update_option( 'blogdescription', 'Sadece başka bir WordPress sitesi' );
    update_option( 'users_can_register', 0 );
    update_option( 'default_role', 'subscriber' );
    update_option( 'timezone_string', '' );
    update_option( 'date_format', 'j F Y' );
    update_option( 'time_format', 'H:i' );
    update_option( 'start_of_week', 1 );
    update_option( 'show_on_front', 'posts' ); // Varsayılan olarak son yazıları göster
    update_option( 'page_on_front', 0 );
    update_option( 'page_for_posts', 0 );

    // Sıfırlama sonrası ana sayfa ayarını kesin olarak son yazılar yap
    update_option('show_on_front', 'posts');
    update_option('page_on_front', 0);
    update_option('page_for_posts', 0);
    // Örnek sayfa varsa sil
    $sample_page = get_page_by_title('Örnek sayfa', OBJECT, 'page');
    if ($sample_page && isset($sample_page->ID)) {
        wp_delete_post($sample_page->ID, true);
    }
    // Permalink ve yönlendirme ayarlarını sıfırla
    flush_rewrite_rules(true);
    // Cache'i temizle
    wp_cache_flush();

    // Adım 2: Bilinen sorunlu eklenti seçeneklerini ve genel geçicileri temizle
    $cleanup_messages = wprc_cleanup_problematic_options_and_transients();
    if (is_array($cleanup_messages)) {
        $messages = array_merge($messages, $cleanup_messages);
    } elseif (is_wp_error($cleanup_messages)) {
        // Hata mesajını doğrudan ana diziye ekle, WP_Error nesnesi olarak değil
        $messages[] = $cleanup_messages->get_error_message();
         // Veya bir WP_Error nesnesi olarak eklemek için:
         // $messages[] = $cleanup_messages;
    }
    
    // Adım 3: Otomatik Ana Sayfa Ataması (wp_install_defaults "Örnek Sayfa"yı oluşturur)
    $sample_page = get_page_by_title('Örnek sayfa', OBJECT, 'page');
    if (!$sample_page) { $sample_page = get_page_by_title('Sample Page', OBJECT, 'page'); }

    if ($sample_page && isset($sample_page->ID)) {
        update_option('show_on_front', 'page');
        update_option('page_on_front', $sample_page->ID);
        $messages[] = sprintf(__('"%s" sayfası ana sayfa olarak atandı.', 'wp-reset-core'), $sample_page->post_title);
    } else {
        // Eğer örnek sayfa yoksa, son yazıları göstermeye devam et (yukarıda ayarlandı)
        $messages[] = __('Ana sayfa olarak atanacak "Örnek Sayfa" bulunamadı, son yazılar gösterilecek.', 'wp-reset-core');
    }

    wp_cache_flush();
    $messages[] = __( 'WordPress nesne önbelleği temizlendi.', 'wp-reset-core' );
    
    return $messages;
}

function wprc_reset_htaccess() {
    $messages = []; global $wp_rewrite;
    if ( ! isset( $wp_rewrite ) || ! is_object( $wp_rewrite) ) { require_once ABSPATH . WPINC . '/rewrite.php'; $wp_rewrite = new WP_Rewrite(); }
    update_option( 'permalink_structure', '' ); 
    
    global $wp_filesystem; if ( empty( $wp_filesystem ) ) { require_once ABSPATH . '/wp-admin/includes/file.php'; WP_Filesystem(); }
    if ($wp_filesystem) {
        $htaccess_file = get_home_path() . '.htaccess';
        if ($wp_filesystem->exists($htaccess_file) && $wp_filesystem->is_writable($htaccess_file)) { 
            $wp_filesystem->put_contents($htaccess_file, ''); 
            $messages[] = __('.htaccess dosyası bulundu ve içeriği temizlendi.', 'wp-reset-core'); 
        }
    } else { $messages[] = __('.htaccess dosyasına WP Filesystem ile erişilemedi, manuel kontrol gerekebilir.', 'wp-reset-core'); }
    
    $wp_rewrite->set_permalink_structure(''); 
    flush_rewrite_rules( true ); 
    $messages[] = __( '.htaccess dosyası varsayılan ayarlara döndürülmeye çalışıldı.', 'wp-reset-core' );
    return $messages;
}

/**
 * Bilinen sorunlu eklenti "kurulum/versiyon" seçeneklerini ve genel geçici verileri temizler.
 */
function wprc_cleanup_problematic_options_and_transients() {
    $messages = [];
    global $wpdb;
    $deleted_options_count = 0;

    $options_to_delete_exact = [
        'woocommerce_version', 'woocommerce_db_version', 'wc_db_version',
        'action_scheduler_migration_status', 'as_has_run_reset_migrations',
        'elementor_version', // Elementor'un da versiyon bilgisi olabilir
        // İhtiyaç duyulursa diğer eklentilerin kritik "kurulu" flag'leri eklenebilir
    ];

    $options_to_delete_like = [
        '_transient_%',         // Tüm WordPress geçici verileri
        '_site_transient_%',    // Tüm WordPress site geneli geçici verileri
        'action_scheduler_lock_%', // Action Scheduler kilitleri
        // 'schema-ActionScheduler_%', // Bu, AS'nin çalışması için gerekebilir, wp_install_defaults bunu ellemez.
                                 // Eğer AS tabloları siliniyorsa, bu da silinmeli.
                                 // Şimdilik sadece kilitleri ve genel geçicileri hedefleyelim.
    ];

    foreach ( $options_to_delete_exact as $option_name ) {
        if ( get_option( $option_name ) !== false ) {
            if ( delete_option( $option_name ) ) {
                $deleted_options_count++;
                wprc_debug_log('Problematic exact option deleted: ' . $option_name);
            }
        }
    }

    foreach ($options_to_delete_like as $option_pattern) {
        $sql = $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $option_pattern );
        $options_found = $wpdb->get_col( $sql );
        if (!empty($options_found)) {
            foreach($options_found as $option_to_delete) {
                if ( delete_option( $option_to_delete ) ) {
                    $deleted_options_count++;
                    wprc_debug_log('Problematic LIKE match option/transient deleted: ' . $option_to_delete);
                }
            }
        }
    }
    // Action Scheduler şema bilgilerini de temizleyelim (AS tabloları silindiği için)
    if (delete_option('schema-ActionScheduler_StoreSchema')) { $deleted_options_count++; wprc_debug_log('Deleted option: schema-ActionScheduler_StoreSchema');}
    if (delete_option('schema-ActionScheduler_LoggerSchema')) { $deleted_options_count++; wprc_debug_log('Deleted option: schema-ActionScheduler_LoggerSchema');}


    if ($deleted_options_count > 0) {
        $messages[] = sprintf(__('Yaklaşık %d adet eklenti kalıntısı/geçici veri temizlendi.', 'wp-reset-core'), $deleted_options_count);
    } else {
        $messages[] = __('Temizlenecek bilinen eklenti kalıntısı veya geçici veri bulunamadı.', 'wp-reset-core');
    }
    return $messages;
}
?>