<?php
defined( 'ABSPATH' ) || exit;

/**
 * Generuje PDF z wynikami głosowania (te same dane co na ekranie).
 * Wymaga: Composer + tecnickcom/tcpdf (vendor/autoload.php).
 */
class Openvote_Results_Pdf {

	/**
	 * Czy biblioteka PDF jest dostępna (vendor zainstalowany).
	 */
	public static function is_available(): bool {
		$autoload = OPENVOTE_PLUGIN_DIR . 'vendor/autoload.php';
		if ( ! file_exists( $autoload ) ) {
			return false;
		}
		if ( ! class_exists( 'TCPDF', true ) ) {
			require_once $autoload;
		}
		return class_exists( 'TCPDF', true );
	}

	/**
	 * Generuje PDF i wysyła do przeglądarki (download).
	 *
	 * @param object $poll            Głosowanie.
	 * @param array  $results         Wynik Openvote_Vote::get_results.
	 * @param array  $voters          Wynik Openvote_Vote::get_voters_admin.
	 * @param array  $non_voters_list Wynik Openvote_Vote::get_non_voters.
	 */
	public static function output_download( object $poll, array $results, array $voters, array $non_voters_list = [] ): void {
		if ( ! self::is_available() ) {
			wp_die( esc_html__( 'Generowanie PDF wymaga zainstalowanych zależności Composer (composer install).', 'openvote' ) );
		}

		$brand_short    = openvote_get_brand_short_name();
		$brand_full     = openvote_get_brand_full_name();
		$total_eligible = (int) ( $results['total_eligible'] ?? 0 );
		$total_voters   = (int) ( $results['total_voters']   ?? 0 );
		$non_voters     = (int) ( $results['non_voters']     ?? 0 );
		$pct_voted      = $total_eligible > 0 ? round( $total_voters / $total_eligible * 100, 1 ) : 0;
		$pct_absent     = $total_eligible > 0 ? round( $non_voters   / $total_eligible * 100, 1 ) : 0;

		$pdf = new TCPDF( 'P', 'mm', 'A4', true, 'UTF-8', false );
		$pdf->SetCreator( $brand_short . ' E-Voting' );
		$pdf->SetTitle( $poll->title );
		$pdf->SetMargins( 15, 20, 15 );
		$pdf->SetAutoPageBreak( true, 20 );
		$pdf->setPrintHeader( false );
		$pdf->setPrintFooter( false );
		$pdf->AddPage();
		$pdf->SetFont( 'dejavusans', '', 10 );

		// ── Nagłówek: logo + nazwa systemu ─────────────────────────────────
		$logo_path = self::get_logo_path();
		$header_y  = $pdf->GetY();
		if ( $logo_path ) {
			$pdf->Image( $logo_path, 15, $header_y, 12, 12, '', '', '', false, 150 );
			$pdf->SetXY( 30, $header_y + 1 );
		}
		$pdf->SetFont( 'dejavusans', 'B', 13 );
		$pdf->Cell( 0, 6, $brand_short . '  —  ' . $brand_full, 0, 1 );
		$pdf->SetFont( 'dejavusans', '', 8 );
		$pdf->SetX( $logo_path ? 30 : 15 );
		$pdf->Cell( 0, 5, __( 'Raport wyników głosowania', 'openvote' ) . '   |   ' . gmdate( 'd.m.Y H:i' ), 0, 1 );
		$pdf->SetX( 15 );
		$pdf->Ln( 3 );
		$pdf->SetLineWidth( 0.5 );
		$pdf->SetDrawColor( 26, 26, 46 );
		$pdf->Line( 15, $pdf->GetY(), 195, $pdf->GetY() );
		$pdf->Ln( 5 );

		// ── Tytuł głosowania ───────────────────────────────────────────────
		$pdf->SetFont( 'dejavusans', 'B', 15 );
		$pdf->MultiCell( 0, 9, __( 'Wyniki:', 'openvote' ) . ' ' . $poll->title, 0, 'L', false, 1 );
		$pdf->SetFont( 'dejavusans', '', 9 );
		$pdf->SetTextColor( 100, 100, 100 );
		$pdf->Cell( 0, 6, __( 'Status:', 'openvote' ) . ' ' . $poll->status
			. '   |   ' . __( 'Okres:', 'openvote' ) . ' ' . $poll->date_start . ' — ' . $poll->date_end, 0, 1 );
		$pdf->SetTextColor( 0, 0, 0 );
		$pdf->Ln( 5 );

		// ── Frekwencja ─────────────────────────────────────────────────────
		self::section_header( $pdf, __( 'Frekwencja', 'openvote' ) );

		$w1 = 100; $w2 = 22; $w3 = 22;
		self::table_header_row( $pdf, [
			[ __( 'Kategoria', 'openvote' ),  $w1, 'L' ],
			[ __( 'Liczba',    'openvote' ),  $w2, 'C' ],
			[ __( 'Procent',   'openvote' ),  $w3, 'C' ],
		] );
		self::table_row( $pdf, [
			[ __( 'Uprawnionych do głosowania', 'openvote' ), $w1, 'L' ],
			[ (string) $total_eligible, $w2, 'C' ],
			[ '100%',                   $w3, 'C' ],
		] );
		self::table_row( $pdf, [
			[ __( 'Uczestniczyło w głosowaniu', 'openvote' ), $w1, 'L' ],
			[ (string) $total_voters,   $w2, 'C' ],
			[ $pct_voted . '%',         $w3, 'C' ],
		] );
		self::table_row( $pdf, [
			[ __( 'Nie uczestniczyło', 'openvote' ), $w1, 'L' ],
			[ (string) $non_voters,     $w2, 'C' ],
			[ $pct_absent . '%',        $w3, 'C' ],
		] );
		$pdf->Ln( 7 );

		// ── Wyniki pytań ───────────────────────────────────────────────────
		if ( ! empty( $results['questions'] ) ) {
			self::section_header( $pdf, __( 'Wyniki pytań', 'openvote' ) );

			$wa = 100; $wb = 22; $wc = 22;

			foreach ( $results['questions'] as $i => $q ) {
				$pdf->SetFont( 'dejavusans', 'B', 10 );
				$pdf->SetFillColor( 240, 240, 245 );
				$pdf->Cell( 0, 7, ( $i + 1 ) . '. ' . $q['question_text'], 'B', 1, 'L', true );
				$pdf->SetFillColor( 255, 255, 255 );

				self::table_header_row( $pdf, [
					[ __( 'Odpowiedź', 'openvote' ), $wa, 'L' ],
					[ __( 'Głosy',     'openvote' ), $wb, 'C' ],
					[ __( 'Procent',   'openvote' ), $wc, 'C' ],
				] );

				foreach ( $q['answers'] as $ai => $answer ) {
					$txt = $answer['text'];
					if ( ! empty( $answer['is_abstain'] ) ) {
						$txt .= ' (wstrzymanie)';
					}
					// Color-code: za=green, przeciw=red, abstain=orange
					if ( ! empty( $answer['is_abstain'] ) ) {
						$pdf->SetTextColor( 180, 95, 0 );
					} elseif ( $ai === 0 ) {
						$pdf->SetTextColor( 0, 120, 0 );
					} else {
						$pdf->SetTextColor( 180, 0, 0 );
					}
					self::table_row( $pdf, [
						[ $txt,                        $wa, 'L' ],
						[ (string) $answer['count'],   $wb, 'C' ],
						[ $answer['pct'] . '%',        $wc, 'C' ],
					] );
					$pdf->SetTextColor( 0, 0, 0 );
				}
				$pdf->Ln( 4 );
			}
		}

		// ── Lista głosujących ──────────────────────────────────────────────
		$total_v_count = count( $voters );
		self::section_header( $pdf, sprintf( __( 'Lista głosujących (%d)', 'openvote' ), $total_v_count ) );

		if ( ! empty( $voters ) ) {
			$pdf->SetFont( 'dejavusans', '', 8 );
			$pdf->SetTextColor( 100, 100, 100 );
			$pdf->Cell( 0, 5, __( 'Imię i nazwisko oraz zanonimizowany e-mail. Pozostałe dane utajnione.', 'openvote' ), 0, 1 );
			$pdf->SetTextColor( 0, 0, 0 );
			$pdf->Ln( 1 );

			$wv1 = 65; $wv2 = 75; $wv3 = 35;
			self::table_header_row( $pdf, [
				[ __( 'Imię i Nazwisko',         'openvote' ), $wv1, 'L' ],
				[ __( 'E-mail (zanonimizowany)',  'openvote' ), $wv2, 'L' ],
				[ __( 'Data głosowania',          'openvote' ), $wv3, 'C' ],
			] );
			foreach ( $voters as $v ) {
				self::table_row( $pdf, [
					[ $v['name']       ?? '',  $wv1, 'L' ],
					[ $v['email_anon'] ?? '',  $wv2, 'L' ],
					[ $v['voted_at']   ?? '',  $wv3, 'C' ],
				] );
			}
		} else {
			$pdf->SetFont( 'dejavusans', 'I', 9 );
			$pdf->Cell( 0, 6, __( 'Nikt nie głosował.', 'openvote' ), 0, 1 );
		}
		$pdf->Ln( 7 );

		// ── Lista niegłosujących ───────────────────────────────────────────
		$total_nv_count = count( $non_voters_list );
		self::section_header( $pdf, sprintf( __( 'Nie głosowali (%d)', 'openvote' ), $total_nv_count ) );

		if ( ! empty( $non_voters_list ) ) {
			$pdf->SetFont( 'dejavusans', '', 8 );
			$pdf->SetTextColor( 100, 100, 100 );
			$pdf->Cell( 0, 5, __( 'Uprawnieni użytkownicy, którzy nie oddali głosu. Pseudonimy zanonimizowane.', 'openvote' ), 0, 1 );
			$pdf->SetTextColor( 0, 0, 0 );
			$pdf->Ln( 1 );

			$pdf->SetFont( 'dejavusans', '', 9 );
			$cols   = 3;
			$col_w  = (int) floor( ( 195 - 15 ) / $cols );
			$items  = array_column( $non_voters_list, 'nicename' );
			$chunks = array_chunk( $items, $cols );
			foreach ( $chunks as $row ) {
				while ( count( $row ) < $cols ) {
					$row[] = '';
				}
				foreach ( $row as $cell ) {
					$pdf->Cell( $col_w, 6, $cell, 'B', 0, 'L' );
				}
				$pdf->Ln();
			}
		} else {
			$pdf->SetFont( 'dejavusans', 'I', 9 );
			$pdf->Cell( 0, 6, __( 'Wszyscy uprawnieni użytkownicy oddali głos.', 'openvote' ), 0, 1 );
		}

		// ── Stopka ────────────────────────────────────────────────────────
		$pdf->Ln( 8 );
		$pdf->SetLineWidth( 0.3 );
		$pdf->SetDrawColor( 180, 180, 180 );
		$pdf->Line( 15, $pdf->GetY(), 195, $pdf->GetY() );
		$pdf->Ln( 2 );
		$pdf->SetFont( 'dejavusans', '', 7 );
		$pdf->SetTextColor( 150, 150, 150 );
		$pdf->Cell( 0, 5, $brand_short . ' — ' . $brand_full . '   |   ' . __( 'Wygenerowano:', 'openvote' ) . ' ' . gmdate( 'd.m.Y H:i:s' ), 0, 1, 'C' );

		$filename = 'wyniki-' . $poll->id . '-' . preg_replace( '/[^a-zA-Z0-9]+/', '-', $poll->title ) . '.pdf';
		$pdf->Output( $filename, 'D' );
		exit;
	}

	/**
	 * Sekcja – pogrubiony nagłówek z podkreśleniem.
	 */
	private static function section_header( TCPDF $pdf, string $title ): void {
		$pdf->SetFont( 'dejavusans', 'B', 12 );
		$pdf->SetFillColor( 26, 26, 46 );
		$pdf->SetTextColor( 255, 255, 255 );
		$pdf->Cell( 0, 8, $title, 0, 1, 'L', true );
		$pdf->SetFillColor( 255, 255, 255 );
		$pdf->SetTextColor( 0, 0, 0 );
		$pdf->Ln( 2 );
	}

	/**
	 * Wiersz nagłówkowy tabeli.
	 *
	 * @param array<int, array{0:string,1:int,2:string}> $cols
	 */
	private static function table_header_row( TCPDF $pdf, array $cols ): void {
		$pdf->SetFont( 'dejavusans', 'B', 9 );
		$pdf->SetFillColor( 230, 235, 245 );
		foreach ( $cols as [ $text, $w, $align ] ) {
			$pdf->Cell( $w, 6, $text, 1, 0, $align, true );
		}
		$pdf->Ln();
		$pdf->SetFillColor( 255, 255, 255 );
		$pdf->SetFont( 'dejavusans', '', 9 );
	}

	/**
	 * Wiersz danych tabeli.
	 *
	 * @param array<int, array{0:string,1:int,2:string}> $cols
	 */
	private static function table_row( TCPDF $pdf, array $cols ): void {
		foreach ( $cols as [ $text, $w, $align ] ) {
			$pdf->Cell( $w, 6, $text, 1, 0, $align );
		}
		$pdf->Ln();
	}

	/**
	 * Zwraca lokalną ścieżkę do ikony witryny WordPress (Site Icon).
	 * Ikona przechowywana jest jako załącznik WordPress — pobieramy jej ścieżkę systemową.
	 */
	private static function get_logo_path(): string {
		$site_icon_id = (int) get_option( 'site_icon', 0 );
		if ( ! $site_icon_id ) {
			return '';
		}
		$path = get_attached_file( $site_icon_id );
		return ( $path && file_exists( $path ) ) ? $path : '';
	}

	private static function s( string $text ): string {
		return $text;
	}
}
