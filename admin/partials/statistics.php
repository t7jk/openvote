<?php
defined( 'ABSPATH' ) || exit;

// Przeliczanie nieaktywnych jest uruchamiane przez REST (POST /statistics/recalc-inactive) — zob. batch-progress.js.

global $wpdb;
$groups_table = $wpdb->prefix . 'openvote_groups';
$gm_table     = $wpdb->prefix . 'openvote_group_members';
$polls_table  = $wpdb->prefix . 'openvote_polls';
$surveys_table  = $wpdb->prefix . 'openvote_surveys';
$responses_table = $wpdb->prefix . 'openvote_survey_responses';
// Zegar jak przy głosowaniu (z przesunięciem z Konfiguracji) — spójnie z Openvote_Poll::is_time_expired().
$now            = openvote_current_time_for_voting( 'Y-m-d H:i:s' );

$groups_count   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$groups_table}" );
// Członkowie/nieaktywni: bez grup systemowych (Wszyscy obejmuje każdego z rolą — zawyżałaby liczniki).
$sys_ids        = class_exists( 'Openvote_System_Groups' ) ? array_map( 'intval', Openvote_System_Groups::get_system_group_ids() ) : [];
$gm_non_system  = $sys_ids ? ' WHERE group_id NOT IN (' . implode( ',', $sys_ids ) . ')' : '';
$members_count  = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT user_id) FROM {$gm_table}{$gm_non_system}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tylko liczby całkowite
// Zakończone = 'closed' albo 'open' z minionym terminem (jak Openvote_Poll::get_closed_polls_for_user()); bez szkiców.
$closed_polls_count = (int) $wpdb->get_var( $wpdb->prepare(
	"SELECT COUNT(*) FROM {$polls_table} WHERE ( status = 'closed' OR ( status = 'open' AND date_end < %s ) )",
	$now
) );
$surveys_launched  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$surveys_table} WHERE status IN ('open','closed')" );
$surveys_filled    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$responses_table} WHERE response_status = 'ready'" );
$surveys_views     = (int) get_option( 'openvote_survey_views', 0 );

$member_ids = $wpdb->get_col( "SELECT DISTINCT user_id FROM {$gm_table}{$gm_non_system}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tylko liczby całkowite
$active_count   = 0;
$inactive_count = 0;
foreach ( (array) $member_ids as $uid ) {
	if ( openvote_is_user_inactive( (int) $uid ) ) {
		++$inactive_count;
	} else {
		++$active_count;
	}
}

// Ostatnie 10 zakończonych głosowań (ten sam zakres co $closed_polls_count — także wygasłe ze statusem 'open').
$last_10_polls = (array) $wpdb->get_results( $wpdb->prepare(
	"SELECT id, title FROM {$polls_table} WHERE ( status = 'closed' OR ( status = 'open' AND date_end < %s ) ) ORDER BY date_end DESC LIMIT 10",
	$now
) );

$chart_data = [];
$max_pct    = 0;
foreach ( $last_10_polls as $p ) {
	$poll_obj = Openvote_Poll::get( (int) $p->id );
	if ( ! $poll_obj ) {
		continue;
	}
	$counts   = Openvote_Vote::get_turnout_counts( (int) $p->id );
	$eligible = $counts['total_eligible'];
	$voters   = $counts['total_voters'];
	$pct     = $eligible > 0 ? round( ( $voters / $eligible ) * 100, 1 ) : 0;
	$chart_data[] = [
		'title'    => $p->title,
		'eligible' => $eligible,
		'voters'   => $voters,
		'pct'      => $pct,
	];
	if ( $pct > $max_pct ) {
		$max_pct = $pct;
	}
}
$avg_pct = ! empty( $chart_data ) ? round( array_sum( array_column( $chart_data, 'pct' ) ) / count( $chart_data ), 1 ) : 0;
if ( $avg_pct > $max_pct ) {
	$max_pct = $avg_pct;
}
$chart_max  = $max_pct > 0 ? $max_pct : 100;
$chart_scale = 200; // max bar height in px
$chart_has_data = ! empty( $chart_data );
?>
<style>
.openvote-stat-section { margin-top: 24px; }
.openvote-stat-section__title {
	margin: 0 0 16px;
	font-size: 1.25em;
	font-weight: 600;
	color: #1d2327;
	letter-spacing: -0.02em;
}
.openvote-stat-cards {
	display: grid;
	grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
	gap: 16px;
	max-width: 960px;
}
.openvote-stat-card {
	background: #fff;
	border: 1px solid #c3c4c7;
	border-radius: 8px;
	box-shadow: 0 1px 3px rgba(0,0,0,.06);
	padding: 18px 20px;
	transition: box-shadow .2s ease;
}
.openvote-stat-card:hover { box-shadow: 0 2px 8px rgba(0,0,0,.08); }
.openvote-stat-card__heading {
	display: block;
	font-size: 11px;
	font-weight: 600;
	text-transform: uppercase;
	letter-spacing: .04em;
	color: #646970;
	margin-bottom: 12px;
	padding-bottom: 10px;
	border-bottom: 1px solid #f0f0f1;
}
.openvote-stat-card__row {
	display: flex;
	justify-content: space-between;
	align-items: baseline;
	gap: 12px;
	padding: 6px 0;
	font-size: 13px;
}
.openvote-stat-card__row:not(:last-child) { border-bottom: 1px solid #f6f7f7; }
.openvote-stat-card__label { color: #50575e; flex-shrink: 0; }
.openvote-stat-card__value {
	font-weight: 600;
	color: #1d2327;
	font-variant-numeric: tabular-nums;
}
.openvote-stat-table-wrap {
	margin-top: 24px;
	background: #fff;
	border: 1px solid #c3c4c7;
	border-radius: 8px;
	box-shadow: 0 1px 3px rgba(0,0,0,.06);
	overflow: hidden;
	max-width: 960px;
}
.openvote-stat-table-wrap .openvote-stat-section__title { margin: 20px 20px 12px; }
.openvote-stat-table-wrap .widefat { border: none; margin: 0; }
.openvote-stat-table-wrap .widefat th,
.openvote-stat-table-wrap .widefat td { padding: 10px 20px; }
.openvote-stat-chart-section { margin-top: 24px; }
.openvote-stat-threshold-section {
	margin-top: 24px;
	background: #fff;
	border: 1px solid #c3c4c7;
	border-radius: 8px;
	box-shadow: 0 1px 3px rgba(0,0,0,.06);
	padding: 20px 24px;
	max-width: 960px;
}
.openvote-stat-threshold-section .title { margin-top: 0; }
</style>
<div class="wrap">
	<h1><?php esc_html_e( 'Statystyka', 'openvote' ); ?></h1>


	<div class="openvote-stat-section">
		<h2 class="openvote-stat-section__title"><?php esc_html_e( 'Informacje', 'openvote' ); ?></h2>
		<div class="openvote-stat-cards">
			<div class="openvote-stat-card">
				<span class="openvote-stat-card__heading"><?php esc_html_e( 'Grupy i członkowie', 'openvote' ); ?></span>
				<div class="openvote-stat-card__row">
					<span class="openvote-stat-card__label"><?php esc_html_e( 'Liczba grup', 'openvote' ); ?></span>
					<span class="openvote-stat-card__value"><?php echo esc_html( (string) $groups_count ); ?></span>
				</div>
				<div class="openvote-stat-card__row">
					<span class="openvote-stat-card__label"><?php esc_html_e( 'Liczba członków', 'openvote' ); ?></span>
					<span class="openvote-stat-card__value"><?php echo esc_html( (string) $members_count ); ?></span>
				</div>
			</div>
			<div class="openvote-stat-card">
				<span class="openvote-stat-card__heading"><?php esc_html_e( 'Aktywność', 'openvote' ); ?></span>
				<div class="openvote-stat-card__row">
					<span class="openvote-stat-card__label"><?php esc_html_e( 'Członkowie aktywni', 'openvote' ); ?></span>
					<span class="openvote-stat-card__value"><?php echo esc_html( (string) $active_count ); ?></span>
				</div>
				<div class="openvote-stat-card__row">
					<span class="openvote-stat-card__label"><?php esc_html_e( 'Członkowie nieaktywni', 'openvote' ); ?></span>
					<span class="openvote-stat-card__value"><?php echo esc_html( (string) $inactive_count ); ?></span>
				</div>
			</div>
			<div class="openvote-stat-card" style="grid-column:1/-1;box-shadow:none;border-style:dashed;">
				<span class="description"><?php
					$ov_missed = (int) openvote_get_stat_missed_votes();
					if ( $ov_missed <= 0 ) {
						esc_html_e( 'Próg opuszczonych głosowań ustawiono na ∞ — żaden członek nie jest uznawany za nieaktywnego. Próg zmienisz w Konfiguracji → Nieaktywni.', 'openvote' );
					} else {
						/* translators: %d: missed polls */
						printf( esc_html__( 'Członek nieaktywny = opuścił co najmniej %d głosowań. Próg zmienisz w Konfiguracji → Nieaktywni.', 'openvote' ), $ov_missed );
					}
				?></span>
			</div>
			<div class="openvote-stat-card">
				<span class="openvote-stat-card__heading"><?php esc_html_e( 'Głosowania i ankiety', 'openvote' ); ?></span>
				<div class="openvote-stat-card__row">
					<span class="openvote-stat-card__label"><?php esc_html_e( 'Liczba wykonanych głosowań', 'openvote' ); ?></span>
					<span class="openvote-stat-card__value"><?php echo esc_html( (string) $closed_polls_count ); ?></span>
				</div>
				<div class="openvote-stat-card__row">
					<span class="openvote-stat-card__label"><?php esc_html_e( 'Liczba uruchomionych ankiet', 'openvote' ); ?></span>
					<span class="openvote-stat-card__value"><?php echo esc_html( (string) $surveys_launched ); ?></span>
				</div>
				<div class="openvote-stat-card__row">
					<span class="openvote-stat-card__label"><?php esc_html_e( 'Liczba wypełnionych ankiet', 'openvote' ); ?></span>
					<span class="openvote-stat-card__value"><?php echo esc_html( (string) $surveys_filled ); ?></span>
				</div>
				<div class="openvote-stat-card__row">
					<span class="openvote-stat-card__label"><?php esc_html_e( 'Liczba wyświetleń wszystkich ankiet', 'openvote' ); ?></span>
					<span class="openvote-stat-card__value"><?php echo esc_html( (string) $surveys_views ); ?></span>
				</div>
			</div>
		</div>
	</div>

	<?php
	$email_rate_counts = class_exists( 'Openvote_Email_Rate_Limits', false ) ? Openvote_Email_Rate_Limits::get_counts() : [ 'count_15' => 0, 'count_hour' => 0, 'count_day' => 0 ];
	$email_month_history = openvote_get_emails_sent_history( 1 );
	$email_count_month   = reset( $email_month_history ) ?: 0;
	$ov_lim_15  = function_exists( 'openvote_get_email_limit_per_15min' ) ? (int) openvote_get_email_limit_per_15min() : 0;
	$ov_lim_h   = function_exists( 'openvote_get_email_limit_per_hour' ) ? (int) openvote_get_email_limit_per_hour() : 0;
	$ov_lim_day = function_exists( 'openvote_get_email_limit_per_day' ) ? (int) openvote_get_email_limit_per_day() : 0;
	$ov_fmt_lim = function ( $count, $limit ) {
		return $limit > 0 ? sprintf( '%d / %d', (int) $count, (int) $limit ) : (string) (int) $count;
	};
	?>
	<div class="openvote-stat-section" style="margin-top:24px;">
		<h2 class="openvote-stat-section__title"><?php esc_html_e( 'Liczba wysłanych e-maili', 'openvote' ); ?></h2>
		<div class="openvote-stat-cards">
			<div class="openvote-stat-card">
				<span class="openvote-stat-card__heading"><?php esc_html_e( 'W ciągu ostatnich 15 minut', 'openvote' ); ?></span>
				<div class="openvote-stat-card__row">
					<span class="openvote-stat-card__label"><?php esc_html_e( 'Liczba e-maili', 'openvote' ); ?></span>
					<span class="openvote-stat-card__value"><?php echo esc_html( $ov_fmt_lim( $email_rate_counts['count_15'], $ov_lim_15 ) ); ?></span>
				</div>
			</div>
			<div class="openvote-stat-card">
				<span class="openvote-stat-card__heading"><?php esc_html_e( 'W ciągu ostatniej godziny', 'openvote' ); ?></span>
				<div class="openvote-stat-card__row">
					<span class="openvote-stat-card__label"><?php esc_html_e( 'Liczba e-maili', 'openvote' ); ?></span>
					<span class="openvote-stat-card__value"><?php echo esc_html( $ov_fmt_lim( $email_rate_counts['count_hour'], $ov_lim_h ) ); ?></span>
				</div>
			</div>
			<div class="openvote-stat-card">
				<span class="openvote-stat-card__heading"><?php esc_html_e( 'W ciągu doby', 'openvote' ); ?></span>
				<div class="openvote-stat-card__row">
					<span class="openvote-stat-card__label"><?php esc_html_e( 'Liczba e-maili', 'openvote' ); ?></span>
					<span class="openvote-stat-card__value"><?php echo esc_html( $ov_fmt_lim( $email_rate_counts['count_day'], $ov_lim_day ) ); ?></span>
				</div>
			</div>
			<div class="openvote-stat-card">
				<span class="openvote-stat-card__heading"><?php esc_html_e( 'W ciągu miesiąca', 'openvote' ); ?></span>
				<div class="openvote-stat-card__row">
					<span class="openvote-stat-card__label"><?php esc_html_e( 'Liczba e-maili', 'openvote' ); ?></span>
					<span class="openvote-stat-card__value"><?php echo esc_html( (string) $email_count_month ); ?></span>
				</div>
			</div>
		</div>
		<p class="description"><?php esc_html_e( 'Liczby w formacie „wysłano / limit” — limity zależą od wybranej metody wysyłki (Konfiguracja → Wysyłka e-mail). Po osiągnięciu limitu wysyłka jest wstrzymywana i wznawiana automatycznie.', 'openvote' ); ?></p>
	</div>

	<div class="openvote-stat-table-wrap">
		<h2 class="openvote-stat-section__title"><?php esc_html_e( 'Ostatnie 10 głosowań (zakończone)', 'openvote' ); ?></h2>
		<div class="openvote-table-scroll"><table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Tytuł', 'openvote' ); ?></th>
					<th><?php esc_html_e( 'Liczba uprawnionych do głosowania', 'openvote' ); ?></th>
					<th><?php esc_html_e( 'Liczba głosujących', 'openvote' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $last_10_polls ) ) : ?>
					<tr><td colspan="3"><?php esc_html_e( 'Brak zakończonych głosowań.', 'openvote' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $last_10_polls as $p ) : ?>
						<?php
						$counts   = Openvote_Vote::get_turnout_counts( (int) $p->id );
						$eligible = $counts['total_eligible'];
						$voters   = $counts['total_voters'];
						?>
						<tr>
							<td><?php echo esc_html( isset( $p->title ) ? $p->title : '' ); ?></td>
							<td><?php echo esc_html( (string) $eligible ); ?></td>
							<td><?php echo esc_html( (string) $voters ); ?></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table></div>
	</div>

	<div class="openvote-stat-chart-section openvote-chart-box" style="padding:20px 24px;background:#fff;border:1px solid #c3c4c7;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,.06);max-width:800px;">
		<h2 class="title" style="margin-top:0;margin-bottom:16px;"><?php esc_html_e( 'Frekwencja (ostatnie 10 głosowań)', 'openvote' ); ?></h2>
		<?php if ( ! $chart_has_data ) : ?>
			<p class="description"><?php esc_html_e( 'Wykres pojawi się po zakończeniu pierwszego głosowania.', 'openvote' ); ?></p>
		<?php else : ?>
		<div class="openvote-chart-wrap" style="max-width:720px;">
			<div class="openvote-stats-chart" role="img" aria-label="<?php echo esc_attr__( 'Wykres frekwencji w ostatnich głosowaniach; wartości w tabeli poniżej.', 'openvote' ); ?>">
				<?php for ( $i = 0; $i < 11; $i++ ) :
					$label  = $i < 10 ? (string) ( $i + 1 ) : __( 'Średnia', 'openvote' );
					$pct    = $i < 10 ? ( isset( $chart_data[ $i ] ) ? $chart_data[ $i ]['pct'] : 0 ) : $avg_pct;
					$height = $chart_max > 0 ? ( $pct / $chart_max ) * 100 : 0;
					$bar_color = $i < 10 ? '#2271b1' : '#1d2327';
					?>
					<div class="openvote-stats-chart__col" title="<?php echo esc_attr( $label . ': ' . $pct . '%' ); ?>">
						<span><?php echo esc_html( $pct . '%' ); ?></span>
						<div class="openvote-stats-chart__bar" style="height:<?php echo esc_attr( (string) round( $height * 1.2 ) ); ?>px;background:<?php echo esc_attr( $bar_color ); ?>;"></div>
						<span><?php echo esc_html( $label ); ?></span>
					</div>
				<?php endfor; ?>
			</div>
			<details style="margin-top:12px;">
				<summary><?php esc_html_e( 'Pokaż dane w tabeli', 'openvote' ); ?></summary>
				<div class="openvote-table-scroll"><table class="widefat striped" style="max-width:720px;">
					<thead><tr><th>#</th><th><?php esc_html_e( 'Tytuł', 'openvote' ); ?></th><th><?php esc_html_e( 'Frekwencja', 'openvote' ); ?></th></tr></thead>
					<tbody>
					<?php foreach ( $chart_data as $ci => $cd ) : ?>
						<tr><td><?php echo (int) ( $ci + 1 ); ?></td><td><?php echo esc_html( (string) $cd['title'] ); ?></td><td><?php echo esc_html( $cd['pct'] . '%' ); ?></td></tr>
					<?php endforeach; ?>
					<tr><td></td><td><strong><?php esc_html_e( 'Średnia', 'openvote' ); ?></strong></td><td><?php echo esc_html( $avg_pct . '%' ); ?></td></tr>
					</tbody>
				</table></div>
			</details>
		</div>
		<?php endif; ?>
		<p class="description" style="margin-top:12px;margin-bottom:0;"><?php esc_html_e( 'Oś X: numer głosowania (1 = ostatnie, 11 = średnia). Oś Y: frekwencja w %.', 'openvote' ); ?></p>
	</div>

</div>
