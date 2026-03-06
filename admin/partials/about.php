<?php
defined( 'ABSPATH' ) || exit;
?>
<div class="wrap">
    <h1><?php esc_html_e( 'O OpenVote', 'openvote' ); ?></h1>
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
                        <?php esc_html_e( 'Wtyczka do przeprowadzania elektronicznych głosowań i ankiet w organizacji. Głosowania wielopytaniowe z grupami docelowymi i rolą Koordynatora. Zaproszenia e-mail oraz masowa wysyłka wiadomości (Komunikacja) — WordPress, SMTP, SendGrid, Brevo, Freshmail, GetResponse. Ankiety ze stroną zgłoszeń. Wyniki jawne lub anonimowe, eksport do PDF.', 'openvote' ); ?>
                    </td>
                </tr>
                <tr style="border-bottom:1px solid #f0f0f1;">
                    <td style="padding:12px 0;color:#666;font-size:13px;"><?php esc_html_e( 'Autor', 'openvote' ); ?></td>
                    <td style="padding:12px 0;font-size:14px;">Tomasz Kalinowski</td>
                </tr>
                <tr style="border-bottom:1px solid #f0f0f1;">
                    <td style="padding:12px 0;color:#666;font-size:13px;"><?php esc_html_e( 'X (Twitter)', 'openvote' ); ?></td>
                    <td style="padding:12px 0;font-size:14px;">
                        <a href="https://x.com/tomas3man" target="_blank" rel="noopener noreferrer">@tomas3man</a>
                    </td>
                </tr>
                <tr style="border-bottom:1px solid #f0f0f1;">
                    <td style="padding:12px 0;color:#666;font-size:13px;"><?php esc_html_e( 'GitHub', 'openvote' ); ?></td>
                    <td style="padding:12px 0;font-size:14px;">
                        <a href="https://github.com/t7jk/openvote" target="_blank" rel="noopener noreferrer">https://github.com/t7jk/openvote</a>
                    </td>
                </tr>
                <tr style="border-bottom:1px solid #f0f0f1;">
                    <td style="padding:12px 0;color:#666;font-size:13px;"><?php esc_html_e( 'Darowizna', 'openvote' ); ?></td>
                    <td style="padding:12px 0;font-size:14px;">
                        <a href="https://ko-fi.com/tomas3man" target="_blank" rel="noopener noreferrer">https://ko-fi.com/tomas3man</a>
                    </td>
                </tr>
                <tr style="border-bottom:1px solid #f0f0f1;">
                    <td style="padding:12px 0;color:#666;font-size:13px;"><?php esc_html_e( 'Wersja', 'openvote' ); ?></td>
                    <td style="padding:12px 0;font-size:14px;">
                        <strong><?php echo esc_html( OPENVOTE_VERSION ); ?></strong>
                    </td>
                </tr>
                <tr style="border-bottom:1px solid #f0f0f1;">
                    <td style="padding:12px 0;color:#666;font-size:13px;"><?php esc_html_e( 'Status', 'openvote' ); ?></td>
                    <td style="padding:12px 0;">
                        <span style="display:inline-block;background:#0073aa;color:#fff;font-size:11px;font-weight:700;letter-spacing:.5px;padding:3px 10px;border-radius:20px;text-transform:uppercase;">
                            <?php esc_html_e( 'BETA — może zawierać błędy', 'openvote' ); ?>
                        </span>
                    </td>
                </tr>
                <tr>
                    <td style="padding:12px 0;color:#666;font-size:13px;"><?php esc_html_e( 'Licencja', 'openvote' ); ?></td>
                    <td style="padding:12px 0;font-size:14px;">
                        <a href="https://www.gnu.org/licenses/gpl-2.0.html" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'GPL v2 lub nowsza', 'openvote' ); ?></a>
                    </td>
                </tr>
                <tr>
                    <td colspan="2" style="padding:14px 0 0;border-top:1px solid #f0f0f1;vertical-align:top;">
                        <p style="margin:0;padding:12px 14px;background:#fff8e5;border-left:4px solid #dba617;border-radius:0 4px 4px 0;font-size:13px;line-height:1.6;color:#1d2327;">
                            <strong><?php esc_html_e( 'Uwaga', 'openvote' ); ?>:</strong>
                            <?php esc_html_e( 'Wersja beta nie jest zalecana do zastosowań produkcyjnych. Zalecamy wstrzymanie się z wdrożeniem do premiery wersji 1.1.x.', 'openvote' ); ?>
                        </p>
                    </td>
                </tr>
            </tbody>
        </table>

    </div>
</div>
