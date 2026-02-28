<?php
defined( 'ABSPATH' ) || exit;
?>
<div class="wrap">
    <h1><?php esc_html_e( 'O tym', 'openvote' ); ?></h1>
    <hr class="wp-header-end">

    <div style="max-width:580px;background:#fff;border:1px solid #e2e4e7;border-radius:6px;padding:36px 40px;margin-top:20px;">

        <table style="width:100%;border-collapse:collapse;">
            <tbody>
                <tr style="border-bottom:1px solid #f0f0f1;">
                    <td style="padding:12px 0;color:#666;width:40%;font-size:13px;"><?php esc_html_e( 'Tytuł wtyczki', 'openvote' ); ?></td>
                    <td style="padding:12px 0;font-weight:600;font-size:14px;"><?php esc_html_e( 'Open Vote', 'openvote' ); ?> — <?php esc_html_e( 'System e-głosowania', 'openvote' ); ?></td>
                </tr>
                <tr style="border-bottom:1px solid #f0f0f1;">
                    <td style="padding:12px 0;color:#666;font-size:13px;"><?php esc_html_e( 'Opis', 'openvote' ); ?></td>
                    <td style="padding:12px 0;font-size:14px;line-height:1.6;">
                        <?php esc_html_e( 'Wtyczka do przeprowadzania elektronicznych głosowań i ankiet w organizacji. Umożliwia tworzenie głosowań z pytaniami i odpowiedziami, zarządzanie grupami uczestników, wysyłanie zaproszeń e-mail oraz przeglądanie wyników z zachowaniem anonimowości.', 'openvote' ); ?>
                    </td>
                </tr>
                <tr style="border-bottom:1px solid #f0f0f1;">
                    <td style="padding:12px 0;color:#666;font-size:13px;"><?php esc_html_e( 'Autor', 'openvote' ); ?></td>
                    <td style="padding:12px 0;font-size:14px;">Tomasz Kalinowski</td>
                </tr>
                <tr style="border-bottom:1px solid #f0f0f1;">
                    <td style="padding:12px 0;color:#666;font-size:13px;"><?php esc_html_e( 'E-mail', 'openvote' ); ?></td>
                    <td style="padding:12px 0;font-size:14px;">
                        <a href="mailto:tjkalinowski@gmail.com">tjkalinowski@gmail.com</a>
                    </td>
                </tr>
                <tr style="border-bottom:1px solid #f0f0f1;">
                    <td style="padding:12px 0;color:#666;font-size:13px;"><?php esc_html_e( 'Wersja', 'openvote' ); ?></td>
                    <td style="padding:12px 0;font-size:14px;">
                        <strong><?php echo esc_html( OPENVOTE_VERSION ); ?></strong>
                    </td>
                </tr>
                <tr>
                    <td style="padding:12px 0;color:#666;font-size:13px;"><?php esc_html_e( 'Licencja', 'openvote' ); ?></td>
                    <td style="padding:12px 0;">
                        <span style="display:inline-block;background:#0073aa;color:#fff;font-size:11px;font-weight:700;letter-spacing:.5px;padding:3px 10px;border-radius:20px;text-transform:uppercase;">
                            <?php esc_html_e( 'Wersja Darmowa (Free Version)', 'openvote' ); ?>
                        </span>
                    </td>
                </tr>
            </tbody>
        </table>

    </div>
</div>
