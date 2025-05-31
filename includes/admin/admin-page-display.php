<?php
// Doğrudan erişimi engelle
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Yönetici sayfası HTML içeriğini oluşturur.
 */
function wprc_admin_page_html() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( __( 'Bu sayfayı görüntüleme yetkiniz bulunmamaktadır.', 'wp-reset-core' ) );
    }
    $reset_message = ''; $message_type = '';
    $partial_cleanup_message = ''; $partial_cleanup_type = '';
    $db_cleanup_message = ''; $db_cleanup_type = '';
    $core_garbage_message = ''; $core_garbage_type = '';

    // Veritabanı Temizliği
    if ( isset( $_POST['wprc_db_cleanup_nonce_field'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wprc_db_cleanup_nonce_field'] ) ), 'wprc_db_cleanup_action' ) ) {
        if (function_exists('wprc_delete_non_core_tables')) {
            $result = wprc_delete_non_core_tables();
            if ( is_wp_error( $result ) ) { $db_cleanup_message = $result->get_error_message(); $db_cleanup_type = 'error';
            } elseif ( is_array( $result ) && !empty($result) ) {
                $message_html = '<ul>'; foreach ($result as $item) { $message_html .= '<li>' . esc_html( $item ) . '</li>'; } $message_html .= '</ul>';
                $db_cleanup_message = __( 'Sadece WordPress dışı tablolar silindi:', 'wp-reset-core' ) . $message_html; $db_cleanup_type = 'success';
            } else { $db_cleanup_message = __( 'Çekirdek dışı silinecek tablo bulunamadı.', 'wp-reset-core' ); $db_cleanup_type = 'info'; }
        } else { $db_cleanup_message = __( 'Veritabanı temizleme fonksiyonu (wprc_delete_non_core_tables) bulunamadı.', 'wp-reset-core' ); $db_cleanup_type = 'error'; }
    }

    // Tam Sıfırlama
    if ( isset( $_POST['wprc_reset_nonce_field'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wprc_reset_nonce_field'] ) ), 'wprc_reset_action' ) ) {
        $confirm_text = isset( $_POST['confirm_reset_text'] ) ? sanitize_text_field( wp_unslash( $_POST['confirm_reset_text'] ) ) : '';
        $understand_consequences = isset( $_POST['understand_consequences'] );
        if ( $confirm_text === 'EVET SİL' && $understand_consequences ) {
            $result = wprc_perform_full_reset();
            if ( is_wp_error( $result ) ) { $reset_message = $result->get_error_message(); $message_type = 'error';
            } elseif ( is_array( $result ) && !empty($result) ) {
                $message_html = '<ul>'; foreach ($result as $item) { if (is_wp_error($item)) { $message_html .= '<li style="color: red;">' . esc_html( $item->get_error_message() ) . '</li>'; } else { $message_html .= '<li>' . esc_html( $item ) . '</li>'; } } $message_html .= '</ul>';
                $reset_message = __( 'WordPress sıfırlama işlemleri gerçekleştirildi:', 'wp-reset-core' ) . $message_html; $message_type = 'success'; foreach ($result as $item) { if (is_wp_error($item)) { $message_type = 'warning'; break; } }
            } else { $reset_message = __( 'Sıfırlama işlemi raporlanacak bir mesaj üretmedi veya bir sorun oluştu.', 'wp-reset-core' ); $message_type = 'info'; }
        } else { $reset_message = __( 'Lütfen onay metnini doğru girin ("EVET SİL") ve sonuçları anladığınızı onaylayın.', 'wp-reset-core' ); $message_type = 'error'; }
    }

    // Seçimli Temizlik
    if ( isset( $_POST['wprc_partial_cleanup_nonce_field'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wprc_partial_cleanup_nonce_field'] ) ), 'wprc_partial_cleanup_action' ) ) {
        $selected_content_types = isset($_POST['wprc_partial_content_types']) ? (array) $_POST['wprc_partial_content_types'] : array();
        $selected_plugins = isset($_POST['wprc_partial_plugins']) ? (array) $_POST['wprc_partial_plugins'] : array();
        $selected_themes = isset($_POST['wprc_partial_themes']) ? (array) $_POST['wprc_partial_themes'] : array();
        $delete_uploads = isset($_POST['wprc_partial_delete_uploads']) && $_POST['wprc_partial_delete_uploads'] == '1';
        $plugin_db_cleanup = isset($_POST['wprc_partial_plugin_db_cleanup']) && $_POST['wprc_partial_plugin_db_cleanup'] == '1';
        if (function_exists('wprc_perform_partial_cleanup')) {
            $result = wprc_perform_partial_cleanup($selected_content_types, $selected_plugins, $selected_themes, $delete_uploads, $plugin_db_cleanup);
            if ( is_wp_error( $result ) ) { $partial_cleanup_message = $result->get_error_message(); $partial_cleanup_type = 'error';
            } elseif ( is_array( $result ) && !empty($result) ) {
                $message_html = '<ul>'; foreach ($result as $item) { if (is_wp_error($item)) { $message_html .= '<li style="color: red;">' . esc_html( $item->get_error_message() ) . '</li>'; } else { $message_html .= '<li>' . esc_html( $item ) . '</li>'; } } $message_html .= '</ul>';
                $partial_cleanup_message = __( 'Seçimli temizlik işlemleri gerçekleştirildi:', 'wp-reset-core' ) . $message_html; $partial_cleanup_type = 'success'; foreach ($result as $item) { if (is_wp_error($item)) { $partial_cleanup_type = 'warning'; break; } }
            } else { $partial_cleanup_message = __( 'Temizlik işlemi raporlanacak bir mesaj üretmedi.', 'wp-reset-core' ); $partial_cleanup_type = 'info'; }
        } else { $partial_cleanup_message = __( 'Seçimli temizlik fonksiyonu bulunamadı.', 'wp-reset-core'); $partial_cleanup_type = 'error';}
    }

    // Çekirdek Tablo Kalıntıları Temizleme
    if ( isset( $_POST['wprc_core_garbage_cleanup_nonce_field'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wprc_core_garbage_cleanup_nonce_field'] ) ), 'wprc_core_garbage_cleanup_action' ) ) {
        if (function_exists('wprc_cleanup_core_table_garbage')) {
            $result = wprc_cleanup_core_table_garbage();
            if ( is_wp_error( $result ) ) { $core_garbage_message = $result->get_error_message(); $core_garbage_type = 'error';
            } elseif ( is_array( $result ) && !empty($result) ) {
                $message_html = '<ul>'; foreach ($result as $item) { $message_html .= '<li>' . esc_html( $item ) . '</li>'; } $message_html .= '</ul>';
                $core_garbage_message = __( 'Çekirdek tablo kalıntıları temizlendi:', 'wp-reset-core' ) . $message_html; $core_garbage_type = 'success';
            } else { $core_garbage_message = __( 'Silinecek çekirdek tablo kalıntısı bulunamadı.', 'wp-reset-core' ); $core_garbage_type = 'info'; }
        } else { $core_garbage_message = __( 'Çekirdek tablo temizleme fonksiyonu bulunamadı.', 'wp-reset-core' ); $core_garbage_type = 'error'; }
    }
    ?>
    <!-- FontAwesome CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <div class="wrap wprc-wrap">
        <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
        <?php if ( ! empty( $reset_message ) ) : ?><div class="notice notice-<?php echo esc_attr( $message_type ); ?> is-dismissible"><p><?php echo wp_kses_post( $reset_message ); ?></p></div><?php endif; ?>
        <?php if ( ! empty( $partial_cleanup_message ) ) : ?><div class="notice notice-<?php echo esc_attr( $partial_cleanup_type ); ?> is-dismissible"><p><?php echo wp_kses_post( $partial_cleanup_message ); ?></p></div><?php endif; ?>
        <?php if ( ! empty( $db_cleanup_message ) ) : ?><div class="notice notice-<?php echo esc_attr( $db_cleanup_type ); ?> is-dismissible"><p><?php echo wp_kses_post( $db_cleanup_message ); ?></p></div><?php endif; ?>
        <?php if ( ! empty( $core_garbage_message ) ) : ?><div class="notice notice-<?php echo esc_attr( $core_garbage_type ); ?> is-dismissible"><p><?php echo wp_kses_post( $core_garbage_message ); ?></p></div><?php endif; ?>

        <div class="wprc-section wprc-warning-section">
            <h2><span class="dashicons dashicons-warning"></span> <?php _e( 'DİKKAT: Bu İşlem Geri Alınamaz!', 'wp-reset-core' ); ?></h2>
            <p><?php _e( 'Bu eklentiyi kullanarak WordPress sitenizi ilk kurulum haline getireceksiniz. Bu işlem:', 'wp-reset-core' ); ?></p>
            <ul>
                <li><?php _e( 'Tüm yazıları, sayfaları, yorumları, özel yazı tiplerini, kategorileri ve etiketleri silecektir.', 'wp-reset-core' ); ?></li>
                <li><?php _e( 'Tüm eklentileri (bu eklenti hariç) ve temaları (WordPress varsayılan temaları hariç) silecektir.', 'wp-reset-core' ); ?></li>
                <li><?php _e( 'Yüklemeler klasörünüzdeki (<code>wp-content/uploads</code>) tüm dosyaları silecektir.', 'wp-reset-core' ); ?></li>
                <li><?php _e( 'Veritabanındaki birçok eklenti ve tema ayarını varsayılan değerlerine döndürecektir.', 'wp-reset-core' ); ?></li>
                <li><?php _e( '<code>.htaccess</code> dosyanız WordPress varsayılanlarına döndürülecektir.', 'wp-reset-core' ); ?></li>
            </ul>
            <p style="color: #d63638; font-weight: bold;"><?php _e('UYARI: Özellikle ücretli (premium) temalarınız ve eklentileriniz de dahil olmak üzere, tüm temalar ve eklentiler silinecektir. Ücretli tema ve eklentilerinizi tekrar yüklemek için orijinal dosyalarına ve lisans anahtarlarına ihtiyacınız olacaktır. Lütfen bu dosyaları ve lisans bilgilerinizi yedeklediğinizden emin olun!', 'wp-reset-core'); ?></p>
            <p style="color: #d63638; font-weight: bold;"><?php _e('UYARI: Bu eklenti, WooCommerce gibi karmaşık eklentilerin tüm izlerini temizlemeyi hedefler. Ancak, sıfırlama sonrası bu tür eklentileri tekrar yüklediğinizde, eklentinin bazı özel ayarları veya veritabanı tabloları için manuel müdahale gerekebilir. En iyi sonuç için, sıfırlama sonrası bu eklentileri "temiz bir kurulum" gibi kurmanız önerilir.', 'wp-reset-core'); ?></p>
            <p><strong><?php _e( 'BU İŞLEMİ YAPMADAN ÖNCE SİTENİZİN TAM BİR YEDEĞİNİ (DOSYALAR + VERİTABANI) ALDIĞINIZDAN KESİNLİKLE EMİN OLUN.', 'wp-reset-core' ); ?></strong></p>
            <p><strong><?php _e( 'Bu eklenti, canlı sitelerde değil, yalnızca test ve geliştirme ortamlarında kullanılmak üzere tasarlanmıştır.', 'wp-reset-core' ); ?></strong></p>
        </div>

        <!-- Sekmeli Arayüz Başlangıcı -->
        <div class="wprc-tabs-wrapper" id="wprc-tabs-wrapper">
            <button class="wprc-tab active" data-tab="tab-reset"><i class="fa-solid fa-rotate"></i> <?php _e('Tam Sıfırlama', 'wp-reset-core'); ?></button>
            <button class="wprc-tab" data-tab="tab-partial"><i class="fa-solid fa-broom"></i> <?php _e('Seçimli Temizlik', 'wp-reset-core'); ?></button>
            <button class="wprc-tab" data-tab="tab-db"><i class="fa-solid fa-database"></i> <?php _e('Veritabanı Araçları', 'wp-reset-core'); ?></button>
            <button class="wprc-tab" data-tab="tab-about"><i class="fa-solid fa-circle-info"></i> <?php _e('Hakkımızda', 'wp-reset-core'); ?></button>
            <button class="wprc-tab" data-tab="tab-soon"><i class="fa-solid fa-lightbulb"></i> <?php _e('Yakında Gelecek', 'wp-reset-core'); ?></button>
        </div>
        <div class="wprc-tab-content active" id="tab-reset">
            <!-- TAM SIFIRLAMA FORMU -->
            <form method="POST" action="" id="wprc-reset-form">
                <?php wp_nonce_field( 'wprc_reset_action', 'wprc_reset_nonce_field' ); ?>
                <div class="wprc-section wprc-confirmation-section">
                    <h3><i class="fa-solid fa-rotate"></i> <?php _e( 'Tam WordPress Sıfırlama', 'wp-reset-core' ); ?></h3>
                    <p><?php _e('Bu işlem tüm WordPress sitenizi ilk kurulum haline getirir. Tüm içerik, eklenti, tema ve dosyalar silinir.', 'wp-reset-core'); ?></p>
                    <p><?php printf( esc_html__( 'Devam etmek ve tüm verilerinizi silmek için lütfen aşağıdaki metin kutusuna büyük harflerle %s yazın.', 'wp-reset-core' ), '<strong>EVET SİL</strong>' ); ?></p>
                    <p><input type="text" name="confirm_reset_text" id="confirm_reset_text" class="regular-text" placeholder="<?php esc_attr_e( 'EVET SİL', 'wp-reset-core' ); ?>" autocomplete="off"></p>
                    <p><label for="understand_consequences"><input type="checkbox" name="understand_consequences" id="understand_consequences" value="1"> <?php _e( 'Yukarıdaki uyarıları okudum, bu işlemin geri alınamaz olduğunu ve tüm verilerimin silineceğini anladım. Tüm sonuçları kabul ediyorum ve tam yedek aldım.', 'wp-reset-core' ); ?></label></p>
                </div>
                <p class="submit"><button type="submit" name="submit_reset" id="submit_reset_button" class="button button-primary wprc-button-danger" disabled><span class="dashicons dashicons-trash"></span> <?php _e( 'WordPress\'i Şimdi Sıfırla!', 'wp-reset-core' ); ?></button></p>
            </form>
        </div>
        <div class="wprc-tab-content" id="tab-partial">
            <!-- SEÇİMLİ TEMİZLİK FORMU -->
            <form method="POST" action="" id="wprc-partial-cleanup-form">
                <?php wp_nonce_field( 'wprc_partial_cleanup_action', 'wprc_partial_cleanup_nonce_field' ); ?>
                <div class="wprc-section wprc-confirmation-section">
                    <h3><i class="fa-solid fa-broom"></i> <?php _e('Seçimli Temizlik Araçları', 'wp-reset-core'); ?></h3>
                    <p><?php _e('Aşağıdaki araçları kullanarak sitenizde belirli temizlik işlemleri yapabilirsiniz. Bu işlemler geri alınamaz, lütfen dikkatli olun ve yedek alın.', 'wp-reset-core'); ?></p>
                    
                    <hr><h4><?php _e('İçerik Temizliği', 'wp-reset-core'); ?></h4>
                    <p><label for="wprc-pc-content"><?php _e('Silinecek İçerik Türleri:', 'wp-reset-core'); ?></label><br>
                        <select id="wprc-pc-content" name="wprc_partial_content_types[]" multiple size="5" style="min-width:250px;">
                            <option value="post"><?php _e('Yazılar (Posts)', 'wp-reset-core'); ?></option>
                            <option value="page"><?php _e('Sayfalar (Pages)', 'wp-reset-core'); ?></option>
                            <option value="attachment"><?php _e('Medya Dosyaları (Attachments)', 'wp-reset-core'); ?></option>
                            <option value="<?php echo esc_attr(WPRC_CUSTOM_POST_TYPES_KEY); ?>"><?php _e('Tüm Özel Yazı Tipleri (Custom Post Types)', 'wp-reset-core'); ?></option>
                            <option value="comments"><?php _e('Yorumlar (Comments)', 'wp-reset-core'); ?></option>
                        </select><br><small><?php _e('Birden fazla seçim için Ctrl (veya Mac için Cmd) tuşunu kullanın.', 'wp-reset-core'); ?></small>
                    </p>

                    <hr><h4><?php _e('Eklenti Temizliği', 'wp-reset-core'); ?></h4>
                    <p><label for="wprc-pc-plugins"><?php _e('Silinecek Eklentiler:', 'wp-reset-core'); ?></label><br>
                        <select id="wprc-pc-plugins" name="wprc_partial_plugins[]" multiple size="6" style="min-width:250px;">
                            <?php $all_plugins_list = get_plugins(); $this_plugin_file_basename = plugin_basename( WPRC_PLUGIN_FILE );
                            foreach ( $all_plugins_list as $plugin_file_path => $plugin_data_item ) { if ( $plugin_file_path === $this_plugin_file_basename ) continue; echo '<option value="' . esc_attr( $plugin_file_path ) . '">' . esc_html( $plugin_data_item['Name'] ) . '</option>'; } ?>
                        </select>
                    </p>
                    <p><label><input type="checkbox" name="wprc_partial_plugin_db_cleanup" value="1"> <?php _e('Seçilen eklentiler silindikten sonra, onlara ait olabilecek veritabanı tablolarını ve seçeneklerini de silmeye çalış (Bu işlem eklenti adını kullanarak arama yapar ve yanlışlıkla veri silebilir, dikkatli kullanın!).', 'wp-reset-core'); ?></label></p>


                    <hr><h4><?php _e('Tema Temizliği', 'wp-reset-core'); ?></h4>
                    <p><label for="wprc-pc-themes"><?php _e('Silinecek Temalar:', 'wp-reset-core'); ?></label><br>
                        <select id="wprc-pc-themes" name="wprc_partial_themes[]" multiple size="6" style="min-width:250px;">
                            <?php $all_themes_list = wp_get_themes(['errors' => null]); $active_theme_stylesheet_val = get_option('stylesheet');
                            foreach ( $all_themes_list as $theme_item_slug => $theme_obj_item ) {
                                $label = esc_html( $theme_obj_item->Name );
                                if ( $theme_item_slug === $active_theme_stylesheet_val ) { $label .= ' (' . __( 'Aktif Tema - Seçerseniz siteniz bozulabilir!', 'wp-reset-core' ) . ')'; }
                                echo '<option value="' . esc_attr( $theme_item_slug ) . '">' . $label . '</option>';
                            } ?>
                        </select>
                    </p>

                    <hr><h4><?php _e('Uploads Klasörü Temizliği', 'wp-reset-core'); ?></h4>
                    <p><label><input type="checkbox" name="wprc_partial_delete_uploads" value="1"> <?php _e('Evet, <code>wp-content/uploads</code> klasöründeki tüm dosyalar ve alt klasörler silinsin.', 'wp-reset-core'); ?></label></p>
                    
                    <p class="submit" style="margin-top:20px;">
                        <button type="submit" name="submit_partial_cleanup" id="submit_partial_cleanup_button" class="button button-secondary wprc-button-danger">
                            <span class="dashicons dashicons-admin-tools"></span> <?php _e( 'Seçilenleri Temizle', 'wp-reset-core' ); ?>
                        </button>
                    </p>
                </div>
            </form>
        </div>
        <div class="wprc-tab-content" id="tab-db">
            <!-- VERİTABANI ARAÇLARI -->
            <div class="wprc-section wprc-confirmation-section">
                <h3><i class="fa-solid fa-database"></i> <?php _e('Veritabanı Bakım Araçları', 'wp-reset-core'); ?></h3>
                <p><?php _e('Aşağıdaki araçlar, veritabanınızda spesifik temizlik işlemleri yapar.', 'wp-reset-core'); ?></p>
                
                <form method="POST" action="" id="wprc-db-cleanup-form" style="padding: 10px 0; border-top: 1px solid #eee;">
                    <?php wp_nonce_field( 'wprc_db_cleanup_action', 'wprc_db_cleanup_nonce_field' ); ?>
                    <h4><?php _e('WordPress Dışı Tabloları Sil', 'wp-reset-core'); ?></h4>
                    <p><?php _e('Bu işlem, WordPress çekirdek tabloları dışındaki tüm veritabanı tablolarını siler. Genellikle eklentiler tarafından oluşturulan tabloları temizler.', 'wp-reset-core'); ?></p>
                    <p class="submit"><button type="submit" name="submit_db_cleanup" id="submit_db_cleanup_button" class="button button-secondary"><span class="dashicons dashicons-database"></span> <?php _e( 'WordPress Dışı Tabloları Sil', 'wp-reset-core' ); ?></button></p>
                </form>

                <form method="POST" action="" id="wprc-core-garbage-cleanup-form" style="padding: 10px 0; border-top: 1px solid #eee; margin-top: 20px;">
                    <?php wp_nonce_field( 'wprc_core_garbage_cleanup_action', 'wprc_core_garbage_cleanup_nonce_field' ); ?>
                    <h4><?php _e('Çekirdek Tablo Kalıntılarını Temizle (Options, Postmeta)', 'wp-reset-core'); ?></h4>
                    <p><?php _e('Bu işlem, <code>wp_options</code> ve <code>wp_postmeta</code> tablolarında bilinen bazı eklenti ön eklerine sahip kalıntıları siler.', 'wp-reset-core'); ?></p>
                    <p class="submit"><button type="submit" name="submit_core_garbage_cleanup" id="submit_core_garbage_cleanup_button" class="button button-secondary"><span class="dashicons dashicons-trash"></span> <?php _e( 'Çekirdek Tablo Kalıntılarını Temizle', 'wp-reset-core' ); ?></button></p>
                </form>
            </div>
        </div>
        <div class="wprc-tab-content" id="tab-about">
            <!-- Hakkımızda Sekmesi -->
            <div class="wprc-section">
                <h3><i class="fa-solid fa-circle-info"></i> <?php _e('Hakkımızda', 'wp-reset-core'); ?></h3>
                <p><strong>WP Reset Core</strong> eklentisi, <a href="https://maxhandmade.com" target="_blank">maxhandmade.com</a> tarafından geliştirilmiştir.</p>
                <ul>
                    <li><i class="fa-solid fa-globe"></i> <a href="https://maxhandmade.com" target="_blank">maxhandmade.com</a></li>
                    <li><i class="fa-solid fa-envelope"></i> <a href="mailto:proje@mhmproje.com">proje@mhmproje.com</a></li>
                    <li><i class="fa-brands fa-whatsapp"></i> <a href="https://wa.me/905385564158" target="_blank">+90 538 556 41 58</a></li>
                    <li><i class="fa-brands fa-youtube"></i> <a href="https://www.youtube.com/@ramazanboz" target="_blank">YouTube Kanalımız</a></li>
                </ul>
                <p><strong><?php _e('Sorularınız ve işbirliği talepleriniz için bizimle iletişime geçebilirsiniz.', 'wp-reset-core'); ?></strong></p>
            </div>
        </div>
        <div class="wprc-tab-content" id="tab-soon">
            <!-- Yakında Gelecek Sekmesi -->
            <div class="wprc-section">
                <h3><i class="fa-solid fa-lightbulb"></i> <?php _e('Yakında Gelecek', 'wp-reset-core'); ?></h3>
                <ul>
                    <li>🔄 Daha fazla yedekleme ve geri alma özelliği</li>
                    <li>📊 Gelişmiş istatistik ve raporlama</li>
                    <li>🛡️ Güvenlik ve loglama geliştirmeleri</li>
                    <li>🌐 Çoklu dil desteği ve daha fazlası...</li>
                </ul>
                <p><?php _e('Geliştirme yol haritası ve yeni özellikler için bizi takip edin!', 'wp-reset-core'); ?></p>
            </div>
        </div>
        <!-- Sekmeli Arayüz Sonu -->

        <!-- Footer Alanı -->
        <div class="wprc-footer">
            <img src="<?php echo esc_url( plugins_url( '../../css/logo.png', __FILE__ ) ); ?>" alt="Logo" class="wprc-footer-logo" />
            <div class="wprc-footer-icons">
                <div class="wprc-footer-iconbox">
                    <a href="https://maxhandmade.com" target="_blank" class="wprc-footer-icon" title="Web Sitesi"><i class="fa-solid fa-globe"></i></a>
                    <div class="wprc-footer-label">maxhandmade.com</div>
                </div>
                <div class="wprc-footer-iconbox">
                    <a href="mailto:proje@mhmproje.com" class="wprc-footer-icon" title="E-posta"><i class="fa-solid fa-envelope"></i></a>
                    <div class="wprc-footer-label">proje@mhmproje.com</div>
                </div>
                <div class="wprc-footer-iconbox">
                    <a href="https://wa.me/905385564158" target="_blank" class="wprc-footer-icon" title="WhatsApp"><i class="fa-brands fa-whatsapp"></i></a>
                    <div class="wprc-footer-label">+90 538 556 41 58</div>
                </div>
                <div class="wprc-footer-iconbox">
                    <a href="https://www.youtube.com/@ramazanboz" target="_blank" class="wprc-footer-icon" title="YouTube"><i class="fa-brands fa-youtube"></i></a>
                    <div class="wprc-footer-label">YouTube</div>
                </div>
                <div class="wprc-footer-iconbox">
                    <a href="https://www.instagram.com/max.handmade/" target="_blank" class="wprc-footer-icon" title="Instagram"><i class="fa-brands fa-instagram"></i></a>
                    <div class="wprc-footer-label">Instagram</div>
                </div>
            </div>
        </div>

        <script type="text/javascript">
        // Sekme geçişleri için vanilla JS
        document.addEventListener('DOMContentLoaded', function() {
            const tabs = document.querySelectorAll('.wprc-tab');
            const tabContents = document.querySelectorAll('.wprc-tab-content');
            tabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    tabs.forEach(t => t.classList.remove('active'));
                    tabContents.forEach(tc => tc.classList.remove('active'));
                    this.classList.add('active');
                    const target = this.getAttribute('data-tab');
                    document.getElementById(target).classList.add('active');
                });
            });

            // TAM SIFIRLAMA FORMU: Buton aktifleşme kontrolü
            const resetInput = document.getElementById('confirm_reset_text');
            const resetCheckbox = document.getElementById('understand_consequences');
            const resetButton = document.getElementById('submit_reset_button');
            function checkResetForm() {
                if (resetInput && resetCheckbox && resetButton) {
                    if (resetInput.value.trim() === 'EVET SİL' && resetCheckbox.checked) {
                        resetButton.disabled = false;
                    } else {
                        resetButton.disabled = true;
                    }
                }
            }
            if (resetInput && resetCheckbox && resetButton) {
                resetInput.addEventListener('input', checkResetForm);
                resetCheckbox.addEventListener('change', checkResetForm);
                checkResetForm(); // ilk yüklemede kontrol
            }
        });
        </script>
    </div>
    <?php
}