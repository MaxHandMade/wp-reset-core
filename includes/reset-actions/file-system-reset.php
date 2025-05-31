<?php
// Doğrudan erişimi engelle
if ( ! defined( 'ABSPATH' ) ) { exit; }

// wprc_ensure_default_theme_active, wprc_deactivate_and_delete_other_plugins,
// wprc_delete_non_default_themes, wprc_empty_uploads_folder fonksiyonları.

function wprc_ensure_default_theme_active() {
    $messages = [];
    if ( ! function_exists( 'themes_api' ) ) { require_once ABSPATH . 'wp-admin/includes/theme.php'; }
    if ( ! class_exists( 'Theme_Upgrader' ) ) { require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php'; }
    if ( ! function_exists( 'wp_clean_themes_cache' ) ) { require_once ABSPATH . 'wp-admin/includes/theme.php'; }

    $core_theme_slugs_ordered = ['twentytwentyfive', 'twentytwentyfour', 'twentytwentythree', 'twentytwentytwo', 'twentytwentyone', 'twentytwenty'];
    $latest_known_default_theme_to_download = 'twentytwentyfive';
    $fallback_default_theme_slug = 'twentytwentyfour';

    $installed_themes = wp_get_themes(['errors' => null]);
    $target_theme_to_activate = null;

    foreach ($core_theme_slugs_ordered as $core_slug) {
        if (isset($installed_themes[$core_slug]) && $installed_themes[$core_slug]->exists()) {
            $target_theme_to_activate = $core_slug;
            $messages[] = sprintf(__('Bulunan en güncel yüklü varsayılan tema: %s.', 'wp-reset-core'), esc_html($installed_themes[$core_slug]->Name));
            wprc_debug_log('Found installed default theme: ' . $core_slug);
            break;
        }
    }

    if (!$target_theme_to_activate) {
        $theme_to_download = $latest_known_default_theme_to_download;
        $messages[] = sprintf(__('Sistemde uygun bir varsayılan tema bulunamadı. "%s" teması WordPress.org\'dan indirilip kurulacak.', 'wp-reset-core'), $theme_to_download);
        wprc_debug_log('No default theme found. Attempting to download: ' . $theme_to_download);

        $skin = new WP_Ajax_Upgrader_Skin();
        $upgrader = new Theme_Upgrader($skin);
        $api = themes_api('theme_information', array('slug' => $theme_to_download, 'fields' => array('download_link' => true)));

        if (is_wp_error($api) || empty($api->download_link)) {
            $error_msg = is_wp_error($api) ? $api->get_error_message() : __('İndirme bağlantısı alınamadı.', 'wp-reset-core');
            if ($theme_to_download === $latest_known_default_theme_to_download && $latest_known_default_theme_to_download !== $fallback_default_theme_slug) {
                 $messages[] = sprintf(__('%1$s indirilemedi (%2$s). Fallback tema olan "%3$s" denenecek.', 'wp-reset-core'), $theme_to_download, $error_msg, $fallback_default_theme_slug);
                 wprc_debug_log('Download failed for ' . $theme_to_download . '. Trying fallback: ' . $fallback_default_theme_slug);
                 $theme_to_download = $fallback_default_theme_slug;
                 $api = themes_api('theme_information', array('slug' => $theme_to_download, 'fields' => array('download_link' => true)));
                 if (is_wp_error($api) || empty($api->download_link)) {
                     return new WP_Error('wprc_theme_download_api_failed_fallback', sprintf(__('%1$s (fallback) teması için de API\'den bilgi alınamadı: %2$s', 'wp-reset-core'), $theme_to_download, (is_wp_error($api) ? $api->get_error_message() : __('İndirme bağlantısı alınamadı.', 'wp-reset-core'))));
                 }
            } else {
                 return new WP_Error('wprc_theme_download_api_failed', sprintf(__('%1$s teması için WordPress.org API\'sinden bilgi alınamadı: %2$s', 'wp-reset-core'), $theme_to_download, $error_msg));
            }
        }
        $result = $upgrader->install($api->download_link);
        if (is_wp_error($result)) { return new WP_Error('wprc_theme_install_failed', sprintf(__('%1$s teması kurulurken bir hata oluştu: %2$s', 'wp-reset-core'), $theme_to_download, $result->get_error_message())); }
        if ($result === null || $result === false) {
            $error_details = isset($skin) && method_exists($skin, 'get_error_messages') && !empty($skin->get_error_messages()) ? ' Detaylar: ' . implode('; ', $skin->get_error_messages()) : '';
            return new WP_Error('wprc_theme_install_failed_silent', sprintf(__('%1$s teması kurulurken bir sorun oluştu.%2$s', 'wp-reset-core'), $theme_to_download, $error_details));
        }
        $messages[] = sprintf(__('%s teması başarıyla kuruldu.', 'wp-reset-core'), $theme_to_download);
        $target_theme_to_activate = $theme_to_download;
        wp_clean_themes_cache(true);
        $installed_themes = wp_get_themes(['errors' => null]); // Update after install
    }

    if (!$target_theme_to_activate || !isset($installed_themes[$target_theme_to_activate]) || !$installed_themes[$target_theme_to_activate]->exists()) {
        wprc_debug_log('CRITICAL: No target theme to activate. Aborting further theme operations.');
        return new WP_Error('wprc_critical_no_target_theme_after_check', __('Kritik Hata: Aktif edilecek bir varsayılan tema bulunamadı veya kurulamadı. Tema işlemleri durduruldu.', 'wp-reset-core'));
    }
    
    $current_stylesheet_option = get_option('stylesheet');
    if ($current_stylesheet_option !== $target_theme_to_activate) {
        wprc_debug_log('Switching theme from ' . $current_stylesheet_option . ' to ' . $target_theme_to_activate);
        switch_theme($target_theme_to_activate);
        $new_stylesheet_option = get_option('stylesheet');
        if ($new_stylesheet_option === $target_theme_to_activate) {
            $messages[] = sprintf(__('Aktif tema "%1$s" olarak değiştirildi.', 'wp-reset-core'), esc_html($installed_themes[$target_theme_to_activate]->Name));
        } else {
            $messages[] = sprintf(__('Uyarı: Aktif tema "%1$s" olarak değiştirilemedi. Mevcut aktif: "%2$s".', 'wp-reset-core'), esc_html($installed_themes[$target_theme_to_activate]->Name), esc_html($new_stylesheet_option));
            wprc_debug_log('FAILED to switch theme. Current active: ' . $new_stylesheet_option);
            return new WP_Error('wprc_theme_switch_failed', __('Hedef varsayılan temaya geçiş yapılamadı.', 'wp-reset-core'));
        }
    } else {
        $messages[] = sprintf(__('Aktif tema zaten "%s" idi.', 'wp-reset-core'), esc_html($installed_themes[$target_theme_to_activate]->Name));
    }
    return ['messages' => $messages, 'active_theme_slug' => $target_theme_to_activate];
}

function wprc_deactivate_and_delete_other_plugins() {
    $messages = [];
    if ( ! function_exists( 'get_plugins' ) || ! function_exists( 'deactivate_plugins' ) || ! function_exists( 'delete_plugins' ) ) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    $all_plugins = get_plugins();
    $this_plugin_basename = plugin_basename( WPRC_PLUGIN_FILE );
    $plugins_to_deactivate_paths = []; $plugins_to_delete_paths = [];
    $deactivated_plugins_names_log = []; $deleted_plugins_names_log = [];
    $original_plugin_names = [];

    foreach ( $all_plugins as $plugin_path => $plugin_data ) {
        $original_plugin_names[$plugin_path] = $plugin_data['Name'];
        if ( $plugin_path === $this_plugin_basename ) { continue; }
        if ( is_plugin_active( $plugin_path ) ) { $plugins_to_deactivate_paths[] = $plugin_path; }
        $plugins_to_delete_paths[] = $plugin_path;
    }
    if ( ! empty( $plugins_to_deactivate_paths ) ) {
        deactivate_plugins( $plugins_to_deactivate_paths, true ); // true: silent
        foreach($plugins_to_deactivate_paths as $path) { if(isset($original_plugin_names[$path])) { $deactivated_plugins_names_log[] = $original_plugin_names[$path]; } }
        if ( ! empty( $deactivated_plugins_names_log ) ) { $messages[] = __( 'Devre dışı bırakılan eklentiler: ', 'wp-reset-core' ) . implode( ', ', array_map('esc_html', $deactivated_plugins_names_log) ); }
    }
    if ( ! empty( $plugins_to_delete_paths ) ) {
        $delete_result = delete_plugins( $plugins_to_delete_paths );
        if ( is_wp_error( $delete_result ) ) { return new WP_Error('wprc_plugin_delete_failed', sprintf( __( 'Bazı eklentiler silinirken bir hata oluştu: %s.', 'wp-reset-core' ), $delete_result->get_error_message() ) );
        } else if ($delete_result === false && !empty($plugins_to_delete_paths)) { // false dönerse ve silinecek eklenti varsa, bu bir hatadır.
             return new WP_Error('wprc_plugin_delete_failed_generic', __('Eklentiler silinirken bir sorun oluştu (detay yok). Dosya izinlerini kontrol edin.', 'wp-reset-core'));
        } else { // Başarılı veya $delete_result true ise (boş dizi için true dönebilir)
            $actually_deleted_count = 0;
            foreach($plugins_to_delete_paths as $path) {
                if (!file_exists(WP_PLUGIN_DIR . '/' . $path) && isset($original_plugin_names[$path])) { // Dosya gerçekten silinmişse
                    $deleted_plugins_names_log[] = $original_plugin_names[$path];
                    $actually_deleted_count++;
                }
            }
            if ( ! empty( $deleted_plugins_names_log ) ) { $messages[] = __( 'Silinen eklentiler (bu eklenti hariç): ', 'wp-reset-core' ) . implode( ', ', array_map('esc_html', $deleted_plugins_names_log) ); }
            elseif (empty($deactivated_plugins_names_log) && empty($deleted_plugins_names_log)) { // Eğer hiç devre dışı bırakılan veya silinen yoksa (ve mesaj yoksa)
                 $messages[] = __( 'Bu eklenti dışında aktif veya pasif başka eklenti bulunamadı.', 'wp-reset-core' );
            }
        }
    } elseif (empty($deactivated_plugins_names_log)) { // Ne devre dışı bırakılan ne de silinecek varsa
        $messages[] = __( 'Bu eklenti dışında aktif veya pasif başka eklenti bulunamadı.', 'wp-reset-core' );
    }
    return $messages;
}

function wprc_delete_non_default_themes( $active_default_theme_slug ) {
    $messages = [];
    $core_theme_slugs_to_keep = ['twentytwentyfive', 'twentytwentyfour', 'twentytwentythree', 'twentytwentytwo', 'twentytwentyone', 'twentytwenty'];
    $deleted_themes_names = [];
    wp_clean_themes_cache(true);
    $all_themes = wp_get_themes(['errors' => null]);

    foreach ($all_themes as $theme_slug => $theme_object) {
        if ($theme_slug === $active_default_theme_slug || in_array($theme_slug, $core_theme_slugs_to_keep)) { continue; }
        wprc_debug_log('Attempting to delete theme (post-plugin-delete): ' . $theme_slug);
        $delete_result = delete_theme($theme_slug);
        if (is_wp_error($delete_result)) {
            $messages[] = sprintf(__('"%1$s" teması silinirken hata: %2$s', 'wp-reset-core'), esc_html($theme_object->Name), $delete_result->get_error_message());
            wprc_debug_log('FAILED to delete theme: ' . $theme_slug . ' - Error: ' . $delete_result->get_error_message());
        } else {
            $deleted_themes_names[] = $theme_object->Name;
            wprc_debug_log('Successfully deleted theme: ' . $theme_slug);
        }
    }
    if (!empty($deleted_themes_names)) { $messages[] = __('Silinen diğer temalar: ', 'wp-reset-core') . implode(', ', array_map('esc_html', $deleted_themes_names));
    } else {
        $non_core_themes_left = false; wp_clean_themes_cache(true); $final_check_themes = wp_get_themes(['errors' => null]);
        foreach($final_check_themes as $slug => $data) { if ($slug !== $active_default_theme_slug && !in_array($slug, $core_theme_slugs_to_keep)) { $non_core_themes_left = true; break; } }
        if (!$non_core_themes_left && empty(array_filter($messages, function($m){ return is_string($m) && strpos($m, __('Silinen diğer temalar', 'wp-reset-core')) !== false; }))) {
             $messages[] = __('Korunacak varsayılan temalar dışında silinecek başka tema bulunamadı.', 'wp-reset-core');
        } elseif ($non_core_themes_left && empty($deleted_themes_names) && empty(array_filter($messages, function($m){ return is_string($m) && strpos($m, __('teması silinirken hata', 'wp-reset-core')) !== false; }))) {
             // Özel tema var ama silinemedi ve hata da yoksa (bu pek olası değil)
             $messages[] = __('Bazı özel temalar silinememiş olabilir. Lütfen manuel kontrol edin.', 'wp-reset-core');
        }
    }
    return $messages;
}

function wprc_empty_uploads_folder() {
    global $wp_filesystem;
    // WP_Filesystem zaten main-reset-controller'da başlatılıyor, burada tekrar başlatmaya gerek yok.
    // Ancak fonksiyon bağımsız çalışacaksa diye kontrol edilebilir.
    if ( empty( $wp_filesystem ) ) {
        require_once ABSPATH . '/wp-admin/includes/file.php';
        if (!WP_Filesystem()) {
            return [new WP_Error('wprc_fs_init_uploads', __('Uploads klasörü temizlenemedi: WP Filesystem başlatılamadı.', 'wp-reset-core'))];
        }
    }

    $messages = []; $upload_dir_info = wp_upload_dir(); $uploads_path = $upload_dir_info['basedir'];
    if ( ! $wp_filesystem->is_dir( $uploads_path ) ) { $messages[] = __( 'Uploads klasörü bulunamadı.', 'wp-reset-core' ); return $messages; }
    
    // Önce klasörün içeriğini alalım
    $items_in_uploads = $wp_filesystem->dirlist( $uploads_path, false, true ); // include_hidden = false, recursive = true

    if ( empty( $items_in_uploads ) ) { $messages[] = __( 'Uploads klasörü zaten boş.', 'wp-reset-core' ); return $messages; }
    
    $all_deleted_successfully = true;
    // Uploads klasörünün kendisini silmeden içini boşalt
    foreach ( $items_in_uploads as $item_name => $item_details ) {
        $item_path = trailingslashit( $uploads_path ) . $item_name;
        if ( !$wp_filesystem->delete( $item_path, true ) ) { // true: recursive
            $all_deleted_successfully = false;
            wprc_debug_log('Failed to delete from uploads: ' . $item_path);
        }
    }

    if ($all_deleted_successfully) {
        $messages[] = __( 'Uploads klasörünün içeriği başarıyla temizlendi.', 'wp-reset-core' );
    } else {
        $messages[] = __( 'Uploads klasöründeki bazı öğeler silinirken sorun oluştu. Lütfen manuel kontrol edin.', 'wp-reset-core' );
    }
    return $messages;
}
?>