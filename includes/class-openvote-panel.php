<?php
/**
 * Panel koordynatora: tryb "kiosk" nakładany na istniejące ekrany wp-admin (admin.php?page=openvote).
 *
 * Wejście: /{slug panelu} z Konfiguracji, domyślnie /openvote (wykrywane po ścieżce na `init`, bez rewrite rules — upgrade FTP nie robi flush).
 * Tryb panelu = ciasteczko `openvote_panel` + page=openvote. Każde inne wejście w wp-admin kasuje
 * ciasteczko, więc przejście do zwykłego kokpitu kończy tryb. Ciasteczko (a nie parametr URL) jest po to,
 * żeby tryb przetrwał redirecty istniejących handlerów (admin_url('admin.php?page=openvote...')).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Openvote_Panel {

	private const COOKIE = 'openvote_panel';

	/**
	 * Zakładki panelu w kolejności wyświetlania: slug strony wp-admin => aktywna w panelu?
	 * Nieaktywna = widoczna, ale wyszarzona i nieklikalna. Włączenie kolejnej = zmiana na true.
	 */
	private const TABS = [
		'openvote'               => true,
		'openvote-surveys'       => true,
		'openvote-communication' => true,
	];

	private static function tab_label( string $slug ): string {
		$labels = [
			'openvote'               => __( 'Głosowania', 'openvote' ),
			'openvote-surveys'       => __( 'Ankiety', 'openvote' ),
			'openvote-communication' => __( 'Wysyłka', 'openvote' ),
		];
		return $labels[ $slug ] ?? $slug;
	}

	/** Slug bieżącej strony admina, jeśli należy do panelu; inaczej ''. */
	private static function current_panel_page(): string {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		return ! empty( self::TABS[ $page ] ) ? $page : '';
	}

	public static function register_hooks(): void {
		add_action( 'init', [ __CLASS__, 'maybe_handle_entry' ], 1 );
		add_action( 'admin_init', [ __CLASS__, 'maybe_end_panel' ], 1 );
		add_filter( 'admin_body_class', [ __CLASS__, 'body_class' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue' ] );
		add_action( 'in_admin_header', [ __CLASS__, 'render_header' ], 1 );
	}

	private static function has_access(): bool {
		// Klasa admina jest ładowana w define_admin_hooks(); upewniamy się, że jest dostępna na `init`.
		if ( ! class_exists( 'Openvote_Admin' ) ) {
			require_once OPENVOTE_PLUGIN_DIR . 'admin/class-openvote-admin.php';
		}
		return Openvote_Admin::user_can_access_coordinators();
	}

	private static function entry_path(): string {
		return untrailingslashit( (string) wp_parse_url( openvote_get_panel_url(), PHP_URL_PATH ) );
	}

	/** Aktywny tylko na ekranach głosowań, z ciasteczkiem i uprawnieniami. */
	public static function is_active(): bool {
		return is_admin()
			&& ! empty( $_COOKIE[ self::COOKIE ] )
			&& '' !== self::current_panel_page()
			&& self::has_access();
	}

	public static function maybe_handle_entry(): void {
		if ( 'GET' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || is_admin() ) {
			return;
		}
		$path = untrailingslashit( (string) wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH ) );
		// Druga forma: permalinki typu PATH_INFO (/index.php/openvote) — np. dev bez mod_rewrite.
		$home_path = untrailingslashit( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ) );
		if ( $path !== self::entry_path() && $path !== $home_path . '/index.php/' . openvote_get_panel_slug() ) {
			return;
		}
		// Po zalogowaniu wracamy pod ten sam wariant adresu, którym weszliśmy.
		$entry_url = $path === self::entry_path() ? openvote_get_panel_url() : home_url( '/index.php/' . openvote_get_panel_slug() );
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( wp_login_url( $entry_url ) );
			exit;
		}
		if ( ! self::has_access() ) {
			wp_die( esc_html__( 'Brak dostępu do panelu koordynatora.', 'openvote' ), esc_html__( 'Brak dostępu', 'openvote' ), [ 'response' => 403 ] );
		}
		self::set_cookie( 1 );
		self::render_shell();
		exit;
	}

	/**
	 * Strona-ramka pod adresem panelu: pasek adresu stale pokazuje /openvote, a ekrany wp-admin
	 * ładują się w pełnoekranowym iframe (ten sam origin — WP wysyła X-Frame-Options: SAMEORIGIN).
	 * Konsekwencja: odświeżenie strony wraca do listy głosowań; linki „na zewnątrz” (wyloguj) mają target=_top.
	 */
	private static function render_shell(): void {
		nocache_headers();
		header( 'Content-Type: text/html; charset=' . get_option( 'blog_charset' ) );
		$title = openvote_get_brand_short_name() . ' — ' . __( 'panel koordynatora', 'openvote' );
		$src   = admin_url( 'admin.php?page=openvote' );
		?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php echo esc_attr( get_option( 'blog_charset' ) ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?php echo esc_html( $title ); ?></title>
<style>html,body{margin:0;height:100%;overflow:hidden}iframe{display:block;border:0;width:100%;height:100%}</style>
</head>
<body>
<iframe src="<?php echo esc_url( $src ); ?>" title="<?php echo esc_attr( $title ); ?>"></iframe>
</body>
</html>
		<?php
	}

	private static function set_cookie( int $on ): void {
		// Ścieżka admina: ciasteczko ma iść tylko do wp-admin; sesyjne (expires 0) lub kasowane.
		setcookie( self::COOKIE, $on ? '1' : '', [
			'expires'  => $on ? 0 : time() - YEAR_IN_SECONDS,
			'path'     => defined( 'ADMIN_COOKIE_PATH' ) ? ADMIN_COOKIE_PATH : '/',
			'domain'   => COOKIE_DOMAIN,
			'secure'   => is_ssl(),
			'httponly' => true,
			'samesite' => 'Lax',
		] );
		if ( ! $on ) {
			unset( $_COOKIE[ self::COOKIE ] );
		}
	}

	public static function maybe_end_panel(): void {
		if ( empty( $_COOKIE[ self::COOKIE ] ) || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}
		$script = basename( (string) ( $_SERVER['SCRIPT_NAME'] ?? '' ) );
		if ( 'admin-post.php' === $script || 'async-upload.php' === $script ) {
			return;
		}
		if ( '' === self::current_panel_page() ) {
			self::set_cookie( 0 );
		}
	}

	public static function body_class( $classes ) {
		return self::is_active() ? $classes . ' openvote-panel-mode' : $classes;
	}

	public static function enqueue(): void {
		if ( self::is_active() ) {
			wp_enqueue_style( 'openvote-panel', OPENVOTE_PLUGIN_URL . 'admin/css/openvote-panel.css', [], OPENVOTE_VERSION );
		}
	}

	public static function render_header(): void {
		if ( ! self::is_active() ) {
			return;
		}
		// Notice'y rdzenia/innych wtyczek (i duplikat nagłówka marki OpenVote) są w panelu zbędne.
		// Komunikaty OpenVote wypisują same partiale ekranów — nie przez admin_notices.
		foreach ( [ 'admin_notices', 'all_admin_notices', 'network_admin_notices', 'user_admin_notices' ] as $hook ) {
			remove_all_actions( $hook );
		}
		$logo = openvote_get_logo_url();
		?>
		<div class="openvote-panel-header">
			<span class="openvote-panel-brand">
				<?php if ( '' !== $logo ) : ?>
					<img src="<?php echo esc_url( $logo ); ?>" alt="" class="openvote-panel-logo">
				<?php endif; ?>
				<?php echo esc_html( openvote_get_brand_short_name() . ' — ' . __( 'panel koordynatora', 'openvote' ) ); ?>
			</span>
			<a class="openvote-panel-logout" target="_top" href="<?php echo esc_url( wp_logout_url( openvote_get_panel_url() ) ); ?>"><?php esc_html_e( 'Wyloguj', 'openvote' ); ?></a>
		</div>
		<div class="openvote-tabs" role="navigation">
			<?php $current = self::current_panel_page(); ?>
			<?php foreach ( self::TABS as $slug => $enabled ) : ?>
				<?php if ( ! $enabled ) : ?>
					<span class="openvote-tab openvote-tab--disabled" aria-disabled="true"><?php echo esc_html( self::tab_label( $slug ) ); ?></span>
				<?php else : ?>
					<a class="openvote-tab<?php echo $slug === $current ? ' openvote-tab--active' : ''; ?>"<?php echo $slug === $current ? ' aria-current="page"' : ''; ?> href="<?php echo esc_url( admin_url( 'admin.php?page=' . $slug ) ); ?>"><?php echo esc_html( self::tab_label( $slug ) ); ?></a>
				<?php endif; ?>
			<?php endforeach; ?>
		</div>
		<script>
		/* W ramce panelu: linki poza ekrany panelu (np. Grupy, Konfiguracja, strona publiczna) otwieramy
		   w całym oknie, żeby nie zagnieżdżać pełnego kokpitu w ramce. Linki z target (np. _blank) bez zmian. */
		( function () {
			if ( window.top === window.self ) { return; }
			var panelPages = <?php echo wp_json_encode( array_keys( array_filter( self::TABS ) ) ); ?>;
			document.addEventListener( 'click', function ( e ) {
				var a = e.target.closest ? e.target.closest( 'a[href]' ) : null;
				if ( ! a || a.target || a.getAttribute( 'href' ).charAt( 0 ) === '#' || a.protocol === 'javascript:' ) { return; }
				var page = new URL( a.href, location.href ).searchParams.get( 'page' );
				var inPanel = a.pathname.indexOf( '/wp-admin/' ) !== -1 && panelPages.indexOf( page ) !== -1;
				if ( ! inPanel ) { a.target = '_top'; }
			}, true );
		}() );
		</script>
		<?php
	}
}
