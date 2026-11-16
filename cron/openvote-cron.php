<?php
/**
 * OpenVote — punkt wejścia crona serwera (tylko wiersz poleceń).
 *
 * Użycie (cPanel → Zadania Cron, co 15 minut):
 *   php -q /ścieżka/do/wp-content/plugins/openvote/cron/openvote-cron.php >/dev/null 2>&1
 *
 * Plik nie wykona się z przeglądarki (sprawdzenie SAPI + .htaccess w tym katalogu).
 */

// Tylko uruchomienie z wiersza poleceń i brak śladów żądania WWW.
// Dopuszczamy też php-cgi uruchomione z crona (na części hostingów /usr/local/bin/php to binarka CGI) —
// żądanie WWW przez CGI/FastCGI zawsze ma REQUEST_METHOD i REMOTE_ADDR, więc i tak zostanie odrzucone.
if (
	! in_array( PHP_SAPI, [ 'cli', 'cgi', 'cgi-fcgi' ], true )
	|| isset( $_SERVER['REQUEST_METHOD'] )
	|| isset( $_SERVER['HTTP_HOST'] )
	|| isset( $_SERVER['REMOTE_ADDR'] )
) {
	if ( ! headers_sent() ) {
		http_response_code( 403 );
	}
	exit;
}

define( 'OPENVOTE_CRON_CLI', true );

/**
 * Log w katalogu cron/ (zablokowany dla przeglądarki przez .htaccess): data, wersja i ścieżka PHP, wynik.
 * Pozwala sprawdzić przez FTP, który PHP uruchamia cron na hostingu. Plik przycinany do ~200 ostatnich wpisów.
 */
function openvote_cron_log( string $msg ): void {
	// .php z nagłówkiem exit: nawet gdy serwer ignoruje .htaccess, przeglądarka nie zobaczy treści.
	$file = __DIR__ . '/cron-log.php';
	$head = "<?php exit; ?>\n";
	$line = gmdate( 'Y-m-d H:i:s' ) . ' UTC | PHP ' . PHP_VERSION . ' (' . PHP_BINARY . ') | ' . $msg . "\n";
	if ( is_file( $file ) && filesize( $file ) > 40000 ) {
		$lines = @file( $file );
		if ( is_array( $lines ) ) {
			@file_put_contents( $file, $head . implode( '', array_slice( $lines, -200 ) ), LOCK_EX );
		}
	}
	if ( ! is_file( $file ) ) {
		@file_put_contents( $file, $head, LOCK_EX );
	}
	@file_put_contents( $file, $line, FILE_APPEND | LOCK_EX );
}

if ( PHP_VERSION_ID < 80100 ) {
	// Podpowiedź: znalezione na serwerze binarki PHP 8.x (cPanel EA4, CloudLinux alt-php, inne typowe miejsca).
	$openvote_found = [];
	$openvote_globs = [
		'/opt/cpanel/ea-php8*/root/usr/bin/php',
		'/opt/alt/php8*/usr/bin/php',
		'/usr/local/bin/php8*',
		'/usr/bin/php8*',
		'/usr/local/php8*/bin/php',
		'/opt/php8*/bin/php',
	];
	foreach ( $openvote_globs as $openvote_g ) {
		foreach ( (array) @glob( $openvote_g ) as $openvote_f ) {
			if ( $openvote_f && @is_file( $openvote_f ) && @is_executable( $openvote_f ) ) {
				$openvote_found[] = $openvote_f;
			}
		}
	}
	openvote_cron_log(
		'BŁĄD: wymagane PHP 8.1+, zmień ścieżkę PHP w poleceniu crona. Znalezione PHP 8: '
		. ( $openvote_found ? implode( ', ', array_unique( $openvote_found ) ) : 'brak w typowych miejscach' )
	);
	fwrite( STDERR, 'OpenVote cron: wymagane PHP 8.1+, uruchomiono ' . PHP_VERSION . "\n" );
	exit( 1 );
}

// Domyślne wartości $_SERVER dla wp-config.php budujących adresy z HTTP_HOST (po sprawdzeniu powyżej).
$_SERVER['HTTP_HOST']      = $_SERVER['HTTP_HOST'] ?? 'localhost';
$_SERVER['SERVER_NAME']    = $_SERVER['SERVER_NAME'] ?? 'localhost';
$_SERVER['REQUEST_URI']    = $_SERVER['REQUEST_URI'] ?? '/';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['SERVER_PROTOCOL'] = $_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1';
$_SERVER['REMOTE_ADDR']    = '127.0.0.1';

$openvote_wp_load = '';
$openvote_dir     = __DIR__;
for ( $i = 0; $i < 8; $i++ ) {
	$openvote_dir = dirname( $openvote_dir );
	if ( is_file( $openvote_dir . '/wp-load.php' ) ) {
		$openvote_wp_load = $openvote_dir . '/wp-load.php';
		break;
	}
}
if ( $openvote_wp_load === '' && is_file( dirname( __DIR__, 4 ) . '/wp-load.php' ) ) {
	$openvote_wp_load = dirname( __DIR__, 4 ) . '/wp-load.php';
}
if ( $openvote_wp_load === '' ) {
	openvote_cron_log( 'BŁĄD: nie znaleziono wp-load.php' );
	fwrite( STDERR, "OpenVote cron: nie znaleziono wp-load.php\n" );
	exit( 1 );
}

require_once $openvote_wp_load;

if ( ! class_exists( 'Openvote_Heartbeat' ) ) {
	openvote_cron_log( 'BŁĄD: wtyczka OpenVote nie jest aktywna' );
	fwrite( STDERR, "OpenVote cron: wtyczka OpenVote nie jest aktywna\n" );
	exit( 1 );
}

$openvote_res = Openvote_Heartbeat::run( 'cron' );
$openvote_out = ( $openvote_res['ran'] ? 'OK' : 'POMINIĘTO' ) . ' - ' . $openvote_res['summary'];
openvote_cron_log( $openvote_out );
echo 'OpenVote cron: ' . $openvote_out . "\n";
exit( 0 );
