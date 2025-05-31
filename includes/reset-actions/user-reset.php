<?php
// Doğrudan erişimi engelle
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Mevcut yönetici dışındaki tüm kullanıcıları siler.
 *
 * @param int $admin_user_id_to_keep Korunacak yönetici kullanıcısının ID'si.
 * @return array|WP_Error Başarı mesajları veya WP_Error nesnesi.
 */
function wprc_delete_other_users( $admin_user_id_to_keep ) {
    global $wpdb;
    $deleted_users_logins = [];
    $messages = [];

    if ( ! function_exists( 'wp_delete_user' ) ) {
        require_once ABSPATH . 'wp-admin/includes/user.php';
    }

    $all_users = get_users( [ 'fields' => [ 'ID', 'user_login' ] ] );

    if ( empty( $all_users ) || count( $all_users ) <= 1 ) { // Sadece admin varsa veya hiç kullanıcı yoksa
        $messages[] = __( 'Mevcut yönetici dışında silinecek başka kullanıcı bulunamadı.', 'wp-reset-core' );
        return $messages;
    }

    foreach ( $all_users as $user ) {
        if ( (int) $user->ID === (int) $admin_user_id_to_keep ) {
            continue;
        }
        if ( wp_delete_user( $user->ID ) ) {
            $deleted_users_logins[] = $user->user_login;
        } else {
            // Kullanıcı silinemezse hata döndür, ama diğer işlemleri durdurma.
            $messages[] = new WP_Error( 'wprc_user_delete_failed', sprintf( __( '%s kullanıcısı silinirken bir hata oluştu.', 'wp-reset-core' ), $user->user_login ) );
        }
    }

    if ( ! empty( $deleted_users_logins ) ) {
        $messages[] = __( 'Aşağıdaki kullanıcılar silindi (mevcut yönetici hariç): ', 'wp-reset-core' ) . implode( ', ', array_map('esc_html', $deleted_users_logins) );
    } elseif (count($all_users) > 1) { // Silmeye çalışıldı ama $deleted_users_logins boşsa (hata oluşmuş olabilir)
        $messages[] = __( 'Bazı kullanıcılar silinirken sorun oluşmuş olabilir.', 'wp-reset-core' );
    } else {
         $messages[] = __( 'Mevcut yönetici dışında silinecek başka kullanıcı bulunamadı.', 'wp-reset-core' );
    }
    return $messages;
}