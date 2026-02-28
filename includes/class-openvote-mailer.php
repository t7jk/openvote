<?php
defined( 'ABSPATH' ) || exit;

/**
 * Obsługuje konfigurację SMTP dla wp_mail() oraz akcję AJAX testu.
 */
class Openvote_Mailer {

    /**
     * Rejestracja hooków – wywoływana z Openvote (class-openvote.php).
     */
    public static function register_hooks(): void {
        add_action( 'phpmailer_init', [ self::class, 'configure_smtp' ] );
        add_action( 'wp_ajax_openvote_test_smtp',      [ self::class, 'ajax_test_smtp' ] );
        add_action( 'wp_ajax_openvote_test_sendgrid',  [ self::class, 'ajax_test_sendgrid' ] );
    }

    /**
     * Konfiguruje PHPMailer pod SMTP gdy metoda == 'smtp'.
     *
     * @param PHPMailer\PHPMailer\PHPMailer $phpmailer
     */
    public static function configure_smtp( $phpmailer ): void {
        if ( 'smtp' !== openvote_get_mail_method() ) {
            return;
        }

        $cfg = openvote_get_smtp_config();

        if ( $cfg['host'] === '' ) {
            return;
        }

        $phpmailer->isSMTP();
        $phpmailer->Host     = $cfg['host'];
        $phpmailer->Port     = $cfg['port'];
        $phpmailer->SMTPAuth = ( $cfg['username'] !== '' );

        if ( $phpmailer->SMTPAuth ) {
            $phpmailer->Username = $cfg['username'];
            $phpmailer->Password = $cfg['password'];
        }

        switch ( $cfg['encryption'] ) {
            case 'ssl':
                $phpmailer->SMTPSecure = 'ssl';
                break;
            case 'tls':
                $phpmailer->SMTPSecure = 'tls';
                break;
            default:
                $phpmailer->SMTPSecure = '';
                $phpmailer->SMTPAutoTLS = false;
        }

        $from_email = openvote_get_from_email();
        $from_name  = openvote_get_brand_short_name();
        $phpmailer->setFrom( $from_email, $from_name, false );
    }

    /**
     * Wyślij e-maile przez SendGrid Web API v3.
     *
     * Akceptuje do 1000 odbiorców w jednym żądaniu HTTP (batch personalizations).
     *
     * @param array<array{email: string, name: string}> $recipients Tablica odbiorców.
     * @param string $subject Temat wiadomości.
     * @param string $body_text Treść w formacie plain-text.
     * @param string $api_key  Klucz API (pusta = z opcji).
     * @return array{ sent: int, failed: int, error: string }
     */
    public static function send_via_sendgrid(
        array $recipients,
        string $subject,
        string $body_text,
        string $api_key = ''
    ): array {
        if ( $api_key === '' ) {
            $api_key = openvote_get_sendgrid_api_key();
        }
        if ( $api_key === '' ) {
            return [ 'sent' => 0, 'failed' => count( $recipients ), 'error' => __( 'Brak klucza API SendGrid.', 'openvote' ) ];
        }

        $from_email = openvote_get_from_email();
        $from_name  = openvote_get_brand_short_name();

        $personalizations = [];
        foreach ( $recipients as $r ) {
            $email = sanitize_email( $r['email'] ?? '' );
            if ( ! is_email( $email ) ) {
                continue;
            }
            $entry = [ 'to' => [ [ 'email' => $email ] ] ];
            if ( ! empty( $r['name'] ) ) {
                $entry['to'][0]['name'] = sanitize_text_field( $r['name'] );
            }
            $personalizations[] = $entry;
        }

        if ( empty( $personalizations ) ) {
            return [ 'sent' => 0, 'failed' => count( $recipients ), 'error' => __( 'Brak prawidłowych adresów e-mail.', 'openvote' ) ];
        }

        $payload = [
            'personalizations' => $personalizations,
            'from'             => [ 'email' => $from_email, 'name' => $from_name ],
            'subject'          => $subject,
            'content'          => [
                [ 'type' => 'text/plain', 'value' => $body_text ],
            ],
        ];

        $response = wp_remote_post(
            'https://api.sendgrid.com/v3/mail/send',
            [
                'timeout' => 30,
                'headers' => [
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type'  => 'application/json',
                ],
                'body'    => wp_json_encode( $payload ),
            ]
        );

        if ( is_wp_error( $response ) ) {
            return [ 'sent' => 0, 'failed' => count( $personalizations ), 'error' => $response->get_error_message() ];
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( $code >= 200 && $code < 300 ) {
            return [ 'sent' => count( $personalizations ), 'failed' => 0, 'error' => '' ];
        }

        $body  = wp_remote_retrieve_body( $response );
        $data  = json_decode( $body, true );
        $error = $data['errors'][0]['message'] ?? "HTTP {$code}";
        return [ 'sent' => 0, 'failed' => count( $personalizations ), 'error' => $error ];
    }

    /**
     * AJAX: wyślij testowy e-mail przez SendGrid (bez zapisywania).
     */
    public static function ajax_test_sendgrid(): void {
        check_ajax_referer( 'openvote_test_sendgrid', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Brak uprawnień.', 'openvote' ) );
        }

        $api_key = sanitize_text_field( wp_unslash( $_POST['api_key'] ?? '' ) );
        if ( $api_key === '' ) {
            $api_key = openvote_get_sendgrid_api_key();
        }
        if ( $api_key === '' ) {
            wp_send_json_error( __( 'Podaj klucz API SendGrid.', 'openvote' ) );
        }

        $to      = wp_get_current_user()->user_email;
        $subject = __( 'Test SendGrid — E-Voting', 'openvote' );
        $message = __( 'To jest testowy e-mail weryfikujący konfigurację SendGrid w E-Voting.', 'openvote' );

        $result = self::send_via_sendgrid(
            [ [ 'email' => $to, 'name' => wp_get_current_user()->display_name ] ],
            $subject,
            $message,
            $api_key
        );

        if ( $result['sent'] > 0 ) {
            wp_send_json_success( sprintf(
                /* translators: %s: email address */
                __( 'E-mail wysłany pomyślnie na: %s', 'openvote' ),
                $to
            ) );
        } else {
            wp_send_json_error( __( 'Wysyłka nie powiodła się.', 'openvote' ) . ' ' . $result['error'] );
        }
    }

    /**
     * AJAX: wyślij testowy e-mail z podaną konfiguracją (bez zapisywania).
     */
    public static function ajax_test_smtp(): void {
        check_ajax_referer( 'openvote_test_smtp', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Brak uprawnień.', 'openvote' ) );
        }

        $host = sanitize_text_field( wp_unslash( $_POST['host'] ?? '' ) );
        $port = (int) ( $_POST['port'] ?? 587 );
        $enc  = in_array( $_POST['enc'] ?? '', [ 'tls', 'ssl', 'none' ], true )
              ? sanitize_key( $_POST['enc'] )
              : 'tls';
        $user = sanitize_text_field( wp_unslash( $_POST['user'] ?? '' ) );
        $pass = sanitize_text_field( wp_unslash( $_POST['pass'] ?? '' ) );
        $from = sanitize_email( wp_unslash( $_POST['from'] ?? '' ) );

        if ( $from === '' || ! is_email( $from ) ) {
            $domain = wp_parse_url( home_url(), PHP_URL_HOST );
            $from   = 'noreply@' . ( $domain ?: 'example.com' );
        }

        $to      = wp_get_current_user()->user_email;
        $subject = __( 'Test SMTP — Open Vote', 'openvote' );
        $message = __( 'To jest testowy e-mail weryfikujący konfigurację SMTP w Open Vote.', 'openvote' );

        // Tymczasowy hook konfigurujący PHPMailer z parametrami z formularza.
        $configurator = static function ( $phpmailer ) use ( $host, $port, $enc, $user, $pass, $from ): void {
            if ( $host === '' ) {
                return;
            }
            $phpmailer->isSMTP();
            $phpmailer->Host       = $host;
            $phpmailer->Port       = $port;
            $phpmailer->SMTPAuth   = ( $user !== '' );
            $phpmailer->Username   = $user;
            $phpmailer->Password   = $pass;
            switch ( $enc ) {
                case 'ssl':
                    $phpmailer->SMTPSecure  = 'ssl';
                    break;
                case 'tls':
                    $phpmailer->SMTPSecure  = 'tls';
                    break;
                default:
                    $phpmailer->SMTPSecure  = '';
                    $phpmailer->SMTPAutoTLS = false;
            }
            $phpmailer->setFrom( $from, openvote_get_brand_short_name(), false );
        };

        // Tymczasowo zamień hook (przed wysyłką)
        remove_action( 'phpmailer_init', [ Openvote_Mailer::class, 'configure_smtp' ] );
        add_action( 'phpmailer_init', $configurator );

        $result = wp_mail( $to, $subject, $message, [
            'Content-Type: text/plain; charset=UTF-8',
            'From: ' . openvote_get_brand_short_name() . ' <' . $from . '>',
        ] );

        remove_action( 'phpmailer_init', $configurator );
        add_action( 'phpmailer_init', [ Openvote_Mailer::class, 'configure_smtp' ] );

        if ( $result ) {
            wp_send_json_success( sprintf(
                /* translators: %s: email address */
                __( 'E-mail wysłany pomyślnie na: %s', 'openvote' ),
                $to
            ) );
        } else {
            global $phpmailer;
            $error = '';
            if ( isset( $phpmailer ) && property_exists( $phpmailer, 'ErrorInfo' ) ) {
                $error = $phpmailer->ErrorInfo;
            }
            wp_send_json_error( __( 'Wysyłka nie powiodła się.', 'openvote' ) . ( $error ? ' ' . $error : '' ) );
        }
    }
}
