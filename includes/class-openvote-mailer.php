<?php
defined( 'ABSPATH' ) || exit;

/**
 * Obsługuje konfigurację SMTP dla wp_mail() oraz akcję AJAX testu.
 */
class Openvote_Mailer {

    /** Treść zaproszenia (HTML) przed wysyłką — przywracana przez filtr jeśli inna wtyczka usunie <style>. */
    public static $intended_invitation_body = '';

    /**
     * Rejestracja hooków – wywoływana z Openvote (class-openvote.php).
     */
    public static function register_hooks(): void {
        add_action( 'phpmailer_init', [ self::class, 'configure_smtp' ], 5 );
        add_action( 'phpmailer_init', [ self::class, 'ensure_html_content_type' ], 20 );
        add_filter( 'wp_mail', [ self::class, 'restore_invitation_body_if_stripped' ], 9999, 1 );
        add_action( 'wp_ajax_openvote_send_test_invitation_email', [ self::class, 'ajax_send_test_invitation_email' ] );
    }

    /**
     * Jeśli inna wtyczka/filtr usunęła tagi HTML z treści zaproszenia (np. wp_kses_post), przywróć oryginalną treść.
     * Wykrywanie: zamierzona treść to HTML (zaczyna się od '<'), a aktualna zawiera surowy CSS (body { font).
     *
     * @param array<string, mixed> $args Argumenty wp_mail (to, subject, message, headers, attachments).
     * @return array<string, mixed>
     */
    public static function restore_invitation_body_if_stripped( array $args ): array {
        if ( self::$intended_invitation_body === '' || ! isset( $args['message'] ) || ! is_string( $args['message'] ) ) {
            return $args;
        }
        $intended = trim( self::$intended_invitation_body );
        if ( ! str_starts_with( $intended, '<' ) ) {
            return $args;
        }
        $current = trim( $args['message'] );
        if ( str_starts_with( $current, '<' ) ) {
            return $args;
        }
        if ( str_contains( $current, 'body { font' ) ) {
            $args['message'] = self::$intended_invitation_body;
        }
        return $args;
    }

    /**
     * Gdy treść wiadomości to HTML, ustaw PHPMailer na IsHTML(true) i wyczyść AltBody,
     * żeby nie generować multipart/alternative z wersją plain (która mogłaby pokazywać surowy tekst).
     *
     * @param PHPMailer\PHPMailer\PHPMailer $phpmailer
     */
    public static function ensure_html_content_type( $phpmailer ): void {
        if ( ! isset( $phpmailer->Body ) || ! is_string( $phpmailer->Body ) ) {
            return;
        }
        if ( str_starts_with( trim( $phpmailer->Body ), '<' ) ) {
            $phpmailer->isHTML( true );
            $phpmailer->AltBody = '';
        }
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
     * @param string $body_text Treść wiadomości (plain lub HTML).
     * @param string $api_key  Klucz API (pusta = z opcji).
     * @param string $content_type 'text/plain' lub 'text/html'.
     * @return array{ sent: int, failed: int, error: string }
     */
    public static function send_via_sendgrid(
        array $recipients,
        string $subject,
        string $body_text,
        string $api_key = '',
        string $content_type = 'text/plain'
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

        if ( $content_type !== 'text/html' ) {
            $content_type = 'text/plain';
        }
        $payload = [
            'personalizations' => $personalizations,
            'from'             => [ 'email' => $from_email, 'name' => $from_name ],
            'subject'          => $subject,
            'content'          => [
                [ 'type' => $content_type, 'value' => $body_text ],
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
     * Wyślij e-maile przez Brevo Transactional API v3.
     *
     * @param array<array{email: string, name?: string}> $recipients
     * @param string $subject
     * @param string $body_text Treść (plain lub HTML).
     * @param string $api_key  Pusta = z opcji.
     * @param string $content_type 'text/plain' lub 'text/html'.
     * @return array{ sent: int, failed: int, error: string }
     */
    public static function send_via_brevo(
        array $recipients,
        string $subject,
        string $body_text,
        string $api_key = '',
        string $content_type = 'text/plain'
    ): array {
        if ( $api_key === '' ) {
            $api_key = openvote_get_brevo_api_key();
        }
        if ( $api_key === '' ) {
            return [ 'sent' => 0, 'failed' => count( $recipients ), 'error' => __( 'Brak klucza API Brevo.', 'openvote' ) ];
        }

        $from_email = openvote_get_from_email();
        $from_name  = openvote_get_brand_short_name();

        $to_list = [];
        foreach ( $recipients as $r ) {
            $email = sanitize_email( $r['email'] ?? '' );
            if ( ! is_email( $email ) ) {
                continue;
            }
            $entry = [ 'email' => $email ];
            if ( ! empty( $r['name'] ) ) {
                $entry['name'] = sanitize_text_field( $r['name'] );
            }
            $to_list[] = $entry;
        }

        if ( empty( $to_list ) ) {
            return [ 'sent' => 0, 'failed' => count( $recipients ), 'error' => __( 'Brak prawidłowych adresów e-mail.', 'openvote' ) ];
        }

        $payload = [
            'sender' => [ 'name' => $from_name, 'email' => $from_email ],
            'to'     => $to_list,
            'subject' => $subject,
        ];
        if ( $content_type === 'text/html' ) {
            $payload['htmlContent'] = $body_text;
        } else {
            $payload['textContent'] = $body_text;
        }

        $response = wp_remote_post(
            'https://api.brevo.com/v3/smtp/email',
            [
                'timeout' => 30,
                'headers' => [
                    'accept'       => 'application/json',
                    'api-key'      => $api_key,
                    'Content-Type' => 'application/json',
                ],
                'body' => wp_json_encode( $payload ),
            ]
        );

        if ( is_wp_error( $response ) ) {
            return [ 'sent' => 0, 'failed' => count( $to_list ), 'error' => $response->get_error_message() ];
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( $code >= 200 && $code < 300 ) {
            return [ 'sent' => count( $to_list ), 'failed' => 0, 'error' => '' ];
        }

        $body  = wp_remote_retrieve_body( $response );
        $data  = json_decode( $body, true );
        $error = isset( $data['message'] ) ? $data['message'] : "HTTP {$code}";
        return [ 'sent' => 0, 'failed' => count( $to_list ), 'error' => $error ];
    }

    /**
     * Zwraca etykietę bieżącej metody wysyłki (do logów).
     *
     * @return string
     */
    public static function get_method_label(): string {
        $method = openvote_get_mail_method();
        $labels = [
            'wordpress'   => 'WordPress (wp_mail)',
            'smtp'        => 'SMTP zewnętrzny',
            'sendgrid'    => 'SendGrid API',
            'brevo'       => 'Brevo API',
            'brevo_paid'  => 'Brevo API (płatny)',
            'freshmail'   => 'Freshmail API',
            'getresponse' => 'GetResponse API',
        ];
        return $labels[ $method ] ?? $method;
    }

    /**
     * Sprawdza połączenie z dostawcą e-mail (Brevo/SendGrid: jedno żądanie GET do API).
     * Dla pozostałych metod zwraca komunikat, że weryfikacja nastąpi przy pierwszej wysyłce.
     *
     * @return array{ ok: bool, message: string, method_label: string }
     */
    public static function test_connection(): array {
        $method = openvote_get_mail_method();
        $label  = self::get_method_label();
        $msg_ok_generic = __( 'Połączenie z dostawcą e-mail OK.', 'openvote' );
        $msg_not_tested = __( 'Połączenie zostanie zweryfikowane przy pierwszej wysyłce.', 'openvote' );

        if ( 'brevo' === $method || 'brevo_paid' === $method ) {
            $api_key = openvote_get_brevo_api_key();
            if ( $api_key === '' ) {
                return [ 'ok' => false, 'message' => __( 'Brak klucza API.', 'openvote' ), 'method_label' => $label ];
            }
            $response = wp_remote_get(
                'https://api.brevo.com/v3/account',
                [
                    'timeout' => 15,
                    'headers' => [ 'api-key' => $api_key, 'accept' => 'application/json' ],
                ]
            );
            if ( is_wp_error( $response ) ) {
                return [ 'ok' => false, 'message' => $response->get_error_message(), 'method_label' => $label ];
            }
            $code = wp_remote_retrieve_response_code( $response );
            if ( $code >= 200 && $code < 300 ) {
                return [ 'ok' => true, 'message' => $msg_ok_generic, 'method_label' => $label ];
            }
            $body  = wp_remote_retrieve_body( $response );
            $data  = json_decode( $body, true );
            $error = isset( $data['message'] ) ? $data['message'] : "HTTP {$code}";
            return [ 'ok' => false, 'message' => $error, 'method_label' => $label ];
        }

        if ( 'sendgrid' === $method ) {
            $api_key = openvote_get_sendgrid_api_key();
            if ( $api_key === '' ) {
                return [ 'ok' => false, 'message' => __( 'Brak klucza API.', 'openvote' ), 'method_label' => $label ];
            }
            $response = wp_remote_get(
                'https://api.sendgrid.com/v3/user/account',
                [
                    'timeout' => 15,
                    'headers' => [ 'Authorization' => 'Bearer ' . $api_key, 'Content-Type' => 'application/json' ],
                ]
            );
            if ( is_wp_error( $response ) ) {
                return [ 'ok' => false, 'message' => $response->get_error_message(), 'method_label' => $label ];
            }
            $code = wp_remote_retrieve_response_code( $response );
            if ( $code >= 200 && $code < 300 ) {
                return [ 'ok' => true, 'message' => $msg_ok_generic, 'method_label' => $label ];
            }
            $body  = wp_remote_retrieve_body( $response );
            $data  = json_decode( $body, true );
            $error = isset( $data['errors'][0]['message'] ) ? $data['errors'][0]['message'] : "HTTP {$code}";
            return [ 'ok' => false, 'message' => $error, 'method_label' => $label ];
        }

        return [ 'ok' => true, 'message' => $msg_not_tested, 'method_label' => $label ];
    }

    /**
     * Wyślij e-maile przez Freshmail REST API (jedno żądanie na odbiorcę).
     *
     * @param array<array{email: string, name?: string}> $recipients
     * @param string $subject
     * @param string $body_text
     * @param string $api_key   Pusta = z opcji.
     * @param string $api_secret Pusta = z opcji.
     * @param string $content_type 'text/plain' lub 'text/html'.
     * @return array{ sent: int, failed: int, error: string }
     */
    public static function send_via_freshmail(
        array $recipients,
        string $subject,
        string $body_text,
        string $api_key = '',
        string $api_secret = '',
        string $content_type = 'text/plain'
    ): array {
        if ( $api_key === '' ) {
            $api_key = openvote_get_freshmail_api_key();
        }
        if ( $api_secret === '' ) {
            $api_secret = openvote_get_freshmail_api_secret();
        }
        if ( $api_key === '' || $api_secret === '' ) {
            return [ 'sent' => 0, 'failed' => count( $recipients ), 'error' => __( 'Brak klucza API lub sekretu Freshmail.', 'openvote' ) ];
        }

        $from_email = openvote_get_from_email();
        $from_name  = openvote_get_brand_short_name();
        $path       = '/rest/mail';
        $sent       = 0;
        $last_error = '';

        foreach ( $recipients as $r ) {
            $email = sanitize_email( $r['email'] ?? '' );
            if ( ! is_email( $email ) ) {
                continue;
            }
            $payload = [
                'subscriber' => $email,
                'subject'    => $subject,
                'from'       => $from_email,
                'from_name'  => $from_name,
            ];
            if ( $content_type === 'text/html' ) {
                $payload['html'] = $body_text;
            } else {
                $payload['text'] = $body_text;
            }
            $json_body = wp_json_encode( $payload );
            $sign      = sha1( $api_key . $path . $json_body . $api_secret );

            $response = wp_remote_post(
                'https://api.freshmail.com' . $path,
                [
                    'timeout' => 30,
                    'headers' => [
                        'X-Rest-ApiKey'  => $api_key,
                        'X-Rest-ApiSign' => $sign,
                        'Content-Type'   => 'application/json',
                    ],
                    'body' => $json_body,
                ]
            );

            if ( is_wp_error( $response ) ) {
                $last_error = $response->get_error_message();
                continue;
            }
            $code = wp_remote_retrieve_response_code( $response );
            if ( $code >= 200 && $code < 300 ) {
                $sent++;
            } else {
                $body  = wp_remote_retrieve_body( $response );
                $data  = json_decode( $body, true );
                $last_error = isset( $data['errors'][0]['message'] ) ? $data['errors'][0]['message'] : "HTTP {$code}";
            }
        }

        $failed = count( $recipients ) - $sent;
        return [ 'sent' => $sent, 'failed' => $failed, 'error' => $failed > 0 ? $last_error : '' ];
    }

    /**
     * Wyślij e-maile przez GetResponse API v3 Transactional (jedno żądanie na odbiorcę).
     *
     * @param array<array{email: string, name?: string}> $recipients
     * @param string $subject
     * @param string $body_text
     * @param string $api_key        Pusta = z opcji.
     * @param string $from_field_id  Pusta = z opcji.
     * @param string $content_type   'text/plain' lub 'text/html'.
     * @return array{ sent: int, failed: int, error: string }
     */
    public static function send_via_getresponse(
        array $recipients,
        string $subject,
        string $body_text,
        string $api_key = '',
        string $from_field_id = '',
        string $content_type = 'text/plain'
    ): array {
        if ( $api_key === '' ) {
            $api_key = openvote_get_getresponse_api_key();
        }
        if ( $from_field_id === '' ) {
            $from_field_id = openvote_get_getresponse_from_field_id();
        }
        if ( $api_key === '' ) {
            return [ 'sent' => 0, 'failed' => count( $recipients ), 'error' => __( 'Brak klucza API GetResponse.', 'openvote' ) ];
        }
        if ( $from_field_id === '' ) {
            return [ 'sent' => 0, 'failed' => count( $recipients ), 'error' => __( 'Brak From Field ID GetResponse.', 'openvote' ) ];
        }

        $sent       = 0;
        $last_error = '';

        foreach ( $recipients as $r ) {
            $email = sanitize_email( $r['email'] ?? '' );
            if ( ! is_email( $email ) ) {
                continue;
            }
            $name = ! empty( $r['name'] ) ? sanitize_text_field( $r['name'] ) : '';
            $payload = [
                'fromField' => [ 'fromFieldId' => $from_field_id ],
                'subject'   => $subject,
                'content'   => $content_type === 'text/html' ? [ 'html' => $body_text ] : [ 'plain' => $body_text ],
                'recipients' => [
                    'to' => [ [ 'email' => $email, 'name' => $name ] ],
                ],
            ];

            $response = wp_remote_post(
                'https://api.getresponse.com/v3/transactional-emails',
                [
                    'timeout' => 30,
                    'headers' => [
                        'X-Auth-Token'  => 'api-key ' . $api_key,
                        'Content-Type'  => 'application/json',
                    ],
                    'body' => wp_json_encode( $payload ),
                ]
            );

            if ( is_wp_error( $response ) ) {
                $last_error = $response->get_error_message();
                continue;
            }
            $code = wp_remote_retrieve_response_code( $response );
            if ( $code >= 200 && $code < 300 ) {
                $sent++;
            } else {
                $body      = wp_remote_retrieve_body( $response );
                $data      = json_decode( $body, true );
                $last_error = isset( $data['message'] ) ? $data['message'] : "HTTP {$code}";
            }
        }

        $failed = count( $recipients ) - $sent;
        return [ 'sent' => $sent, 'failed' => $failed, 'error' => $failed > 0 ? $last_error : '' ];
    }

    /**
     * AJAX: wyślij testowy e-mail z treścią zaproszenia (HTML) wybraną metodą.
     */
    public static function ajax_send_test_invitation_email(): void {
        check_ajax_referer( 'openvote_send_test_invitation_email', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Brak uprawnień.', 'openvote' ) ] );
        }

        $to = sanitize_email( wp_unslash( $_POST['to'] ?? '' ) );
        if ( $to === '' || ! is_email( $to ) ) {
            wp_send_json_error( [ 'message' => __( 'Podaj prawidłowy adres e-mail odbiorcy.', 'openvote' ) ] );
        }

        $test_method = sanitize_key( wp_unslash( $_POST['test_method'] ?? 'wordpress' ) );
        $allowed     = [ 'wordpress', 'smtp', 'sendgrid', 'brevo', 'brevo_paid', 'freshmail', 'getresponse' ];
        if ( ! in_array( $test_method, $allowed, true ) ) {
            $test_method = 'wordpress';
        }

        // Atrapa głosowania do renderowania szablonu zaproszenia (HTML).
        $poll_dummy = (object) [
            'title'      => __( 'Test zaproszenia', 'openvote' ),
            'date_start' => gmdate( 'Y-m-d' ),
            'date_end'   => gmdate( 'Y-m-d', strtotime( '+7 days' ) ),
            'questions'  => [],
        ];

        $subject   = openvote_render_email_template( openvote_get_email_subject_template(), $poll_dummy );
        $from_name = openvote_render_email_template( openvote_get_email_from_template(), $poll_dummy );
        $message   = openvote_render_email_template( openvote_get_email_body_html_template(), $poll_dummy, 'html' );

        $recipient = [ [ 'email' => $to, 'name' => '' ] ];

        if ( $test_method === 'sendgrid' ) {
            $result = self::send_via_sendgrid( $recipient, $subject, $message, '', 'text/html' );
            if ( $result['sent'] > 0 ) {
                self::record_test_email_sent();
                wp_send_json_success( [ 'message' => sprintf( __( 'E-mail wysłany pomyślnie na: %s', 'openvote' ), $to ) ] );
            } else {
                wp_send_json_error( [ 'message' => __( 'Wysyłka nie powiodła się.', 'openvote' ) . ' ' . $result['error'] ] );
            }
            return;
        }

        if ( $test_method === 'brevo' || $test_method === 'brevo_paid' ) {
            $result = self::send_via_brevo( $recipient, $subject, $message, '', 'text/html' );
            if ( $result['sent'] > 0 ) {
                self::record_test_email_sent();
                wp_send_json_success( [ 'message' => sprintf( __( 'E-mail wysłany pomyślnie na: %s', 'openvote' ), $to ) ] );
            } else {
                wp_send_json_error( [ 'message' => __( 'Wysyłka nie powiodła się.', 'openvote' ) . ' ' . $result['error'] ] );
            }
            return;
        }

        if ( $test_method === 'freshmail' ) {
            $result = self::send_via_freshmail( $recipient, $subject, $message, '', '', 'text/html' );
            if ( $result['sent'] > 0 ) {
                self::record_test_email_sent();
                wp_send_json_success( [ 'message' => sprintf( __( 'E-mail wysłany pomyślnie na: %s', 'openvote' ), $to ) ] );
            } else {
                wp_send_json_error( [ 'message' => __( 'Wysyłka nie powiodła się.', 'openvote' ) . ' ' . $result['error'] ] );
            }
            return;
        }

        if ( $test_method === 'getresponse' ) {
            $result = self::send_via_getresponse( $recipient, $subject, $message, '', '', 'text/html' );
            if ( $result['sent'] > 0 ) {
                self::record_test_email_sent();
                wp_send_json_success( [ 'message' => sprintf( __( 'E-mail wysłany pomyślnie na: %s', 'openvote' ), $to ) ] );
            } else {
                wp_send_json_error( [ 'message' => __( 'Wysyłka nie powiodła się.', 'openvote' ) . ' ' . $result['error'] ] );
            }
            return;
        }

        if ( $test_method === 'smtp' ) {
            add_filter( 'wp_mail_from', fn() => openvote_get_from_email() );
            add_filter( 'wp_mail_from_name', fn() => $from_name );
            $current = openvote_get_mail_method();
            update_option( 'openvote_mail_method', 'smtp', false );
            remove_action( 'phpmailer_init', [ self::class, 'configure_smtp' ] );
            add_action( 'phpmailer_init', [ self::class, 'configure_smtp' ] );
        }

        $from_email = openvote_get_from_email();
        $headers    = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $from_name . ' <' . $from_email . '>',
        ];
        try {
            self::$intended_invitation_body = $message;
            $sent = wp_mail( $to, $subject, $message, $headers );
        } finally {
            self::$intended_invitation_body = '';
            if ( $test_method === 'smtp' && isset( $current ) ) {
                update_option( 'openvote_mail_method', $current, false );
            }
        }

        if ( $sent ) {
            self::record_test_email_sent();
            wp_send_json_success( [ 'message' => sprintf( __( 'E-mail wysłany pomyślnie na: %s', 'openvote' ), $to ) ] );
        } else {
            global $phpmailer;
            $error = '';
            if ( isset( $phpmailer ) && is_object( $phpmailer ) && property_exists( $phpmailer, 'ErrorInfo' ) ) {
                $error = $phpmailer->ErrorInfo;
            }
            wp_send_json_error( [ 'message' => __( 'Wysyłka nie powiodła się.', 'openvote' ) . ( $error ? ' ' . $error : '' ) ] );
        }
    }

    private static function record_test_email_sent(): void {
        if ( class_exists( 'Openvote_Email_Rate_Limits', false ) ) {
            Openvote_Email_Rate_Limits::increment( 1 );
        }
        openvote_increment_emails_sent( 1 );
    }
}
