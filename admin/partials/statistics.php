<?php
defined( 'ABSPATH' ) || exit;

// Zapis opcji i przeliczanie nieaktywnych są obsługiwane w Openvote_Admin::handle_statistics_form_early() (admin_init, priorytet 1), żeby redirect działał przed outputem motywu.

$missed_votes   = openvote_get_stat_missed_votes();
$months_inactive = openvote_get_stat_months_inactive();

global $wpdb;
$groups_table = $wpdb->prefix . 'openvote_groups';
$gm_table     = $wpdb->prefix . 'openvote_group_members';
$polls_table  = $wpdb->prefix . 'openvote_polls';
$surveys_table  = $wpdb->prefix . 'openvote_surveys';
$responses_table = $wpdb->prefix . 'openvote_survey_responses';
$now            = current_time( 'mysql' );

$groups_count   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$groups_table}" );
$members_count  = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT user_id) FROM {$gm_table}" );
$closed_polls_count = (int) $wpdb->get_var( $wpdb->prepare(
	"SELECT COUNT(*) FROM {$polls_table} WHERE status = 'closed' AND date_end <= %s",
	$now
) );
$surveys_launched  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$surveys_table} WHERE status IN ('open','closed')" );
$surveys_filled    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$responses_table} WHERE response_status = 'ready'" );
$surveys_views     = (int) get_option( 'openvote_survey_views', 0 );

$member_ids = $wpdb->get_col( "SELECT DISTINCT user_id FROM {$gm_table}" );
$active_count   = 0;
$inactive_count = 0;
foreach ( (array) $member_ids as $uid ) {
	if ( openvote_is_user_inactive( (int) $uid ) ) {
		++$inactive_count;
	} else {
		++$active_count;
	}
}

$last_10_polls = Openvote_Poll::get_all( [
	'status'  => 'closed',
	'orderby' => 'date_end',
	'order'   => 'DESC',
	'limit'   => 10,
	'offset'  => 0,
] );

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
// Przy braku danych: szare słupki o losowej wysokości (placeholder).
if ( ! $chart_has_data ) {
	$chart_placeholder_heights = array_map( function () use ( $chart_scale ) {
		return mt_rand( (int) ( $chart_scale * 0.1 ), (int) ( $chart_scale * 0.9 ) );
	}, range( 0, 10 ) );
}
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

	<?php if ( isset( $_GET['saved'] ) ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Ustawienia zapisane.', 'openvote' ); ?></p>
		</div>
	<?php endif; ?>
	<?php if ( isset( $_GET['recalculated'] ) ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Statystyki nieaktywnych przeliczone.', 'openvote' ); ?></p>
		</div>
	<?php endif; ?>

	<div class="openvote-stat-section">
		<h2 class="openvote-stat-section__title"><?php esc_html_e( 'Informacje', 'openvote' ); ?></h2>
		<div class="openvote-stat-cards">
			<div class="openvote-stat-card">
				<span class="openvote-stat-card__heading"><?php esc_html_e( 'Grupy i członkowie', 'openvote' ); ?></span>
				<div class="openvote-stat-card__row">
					<span class="openvote-stat-card__label"><?php esc_html_e( 'Ilość grup', 'openvote' ); ?></span>
					<span class="openvote-stat-card__value"><?php echo esc_html( (string) $groups_count ); ?></span>
				</div>
				<div class="openvote-stat-card__row">
					<span class="openvote-stat-card__label"><?php esc_html_e( 'Ilość członków', 'openvote' ); ?></span>
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
			<div class="openvote-stat-card">
				<span class="openvote-stat-card__heading"><?php esc_html_e( 'Głosowania i ankiety', 'openvote' ); ?></span>
				<div class="openvote-stat-card__row">
					<span class="openvote-stat-card__label"><?php esc_html_e( 'Liczba wykonanych głosowań', 'openvote' ); ?></span>
					<span class="openvote-stat-card__value"><?php echo esc_html( (string) $closed_polls_count ); ?></span>
				</div>
				<div class="openvote-stat-card__row">
					<span class="openvote-stat-card__label"><?php esc_html_e( 'Ilość uruchomionych ankiet', 'openvote' ); ?></span>
					<span class="openvote-stat-card__value"><?php echo esc_html( (string) $surveys_launched ); ?></span>
				</div>
				<div class="openvote-stat-card__row">
					<span class="openvote-stat-card__label"><?php esc_html_e( 'Ilość wypełnionych ankiet', 'openvote' ); ?></span>
					<span class="openvote-stat-card__value"><?php echo esc_html( (string) $surveys_filled ); ?></span>
				</div>
				<div class="openvote-stat-card__row">
					<span class="openvote-stat-card__label"><?php esc_html_e( 'Ilość wyświetleń wszystkich ankiet', 'openvote' ); ?></span>
					<span class="openvote-stat-card__value"><?php echo esc_html( (string) $surveys_views ); ?></span>
				</div>
			</div>
		</div>
	</div>

	<div class="openvote-stat-table-wrap">
		<h2 class="openvote-stat-section__title"><?php esc_html_e( 'Ostatnie 10 głosowań (zakończone)', 'openvote' ); ?></h2>
		<table class="widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Tytuł', 'openvote' ); ?></th>
					<th><?php esc_html_e( 'Ilość uprawnionych do głosowania', 'openvote' ); ?></th>
					<th><?php esc_html_e( 'Ilość głosujących', 'openvote' ); ?></th>
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
		</table>
	</div>

	<div class="openvote-stat-chart-section openvote-chart-box" style="padding:20px 24px;background:#fff;border:1px solid #c3c4c7;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,.06);max-width:800px;">
		<h2 class="title" style="margin-top:0;margin-bottom:16px;"><?php esc_html_e( 'Frekwencja (ostatnie 10 głosowań)', 'openvote' ); ?></h2>
		<div class="openvote-chart-wrap" style="max-width:720px;min-height:240px;">
			<div class="openvote-chart-y-label" style="width:40px;display:inline-block;vertical-align:bottom;font-size:11px;text-align:right;"><?php echo esc_html( (string) $chart_max ); ?>%</div>
			<div class="openvote-chart-bars" style="display:inline-block;width:calc(100% - 50px);vertical-align:bottom;min-height:220px;">
				<?php for ( $i = 0; $i < 11; $i++ ) : ?>
					<?php
					$label  = $i < 10 ? (string) ( $i + 1 ) : __( 'Średnia', 'openvote' );
					if ( $chart_has_data ) {
						$pct    = $i < 10 ? ( isset( $chart_data[ $i ] ) ? $chart_data[ $i ]['pct'] : 0 ) : $avg_pct;
						$height = $chart_max > 0 ? ( $pct / $chart_max ) * $chart_scale : 0;
						$bar_color = '#2271b1';
						$bar_title = $label . ': ' . $pct . '%';
					} else {
						$height = $chart_placeholder_heights[ $i ];
						$bar_color = '#a7aaad';
						$bar_title = $label;
					}
					?>
					<div class="openvote-chart-bar-cell" style="display:inline-block;width:8%;min-width:40px;vertical-align:bottom;text-align:center;">
						<div class="openvote-chart-bar" style="height:<?php echo esc_attr( (string) round( $height ) ); ?>px;max-height:<?php echo (int) $chart_scale; ?>px;min-height:0;background:<?php echo esc_attr( $bar_color ); ?>;margin:0 auto 4px;width:24px;" title="<?php echo esc_attr( $bar_title ); ?>"></div>
						<span style="font-size:11px;"><?php echo esc_html( $label ); ?></span>
					</div>
				<?php endfor; ?>
			</div>
		</div>
		<p class="description" style="margin-top:12px;margin-bottom:0;"><?php esc_html_e( 'Oś X: numer głosowania (1 = ostatnie, 11 = średnia). Oś Y: frekwencja w %.', 'openvote' ); ?></p>
	</div>

	<div class="openvote-stat-threshold-section">
		<h2 class="title"><?php esc_html_e( 'Progi nieaktywności', 'openvote' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Jeśli użytkownik jest uznany za nieaktywnego, nie będzie otrzymywać powiadomień o nowych głosowaniach.', 'openvote' ); ?>
		</p>
		<p class="description">
			<?php esc_html_e( 'Ustawienie wartości maksymalnej (24) powoduje wyłączenie oznaczania użytkowników jako nieaktywnych.', 'openvote' ); ?>
		</p>
		<p class="description">
			<?php esc_html_e( 'Wystarczy aby jeden z wyżej ustawionych warunków został spełniony.', 'openvote' ); ?>
		</p>

		<form method="post" action="">
		<?php wp_nonce_field( 'openvote_save_statistics', 'openvote_statistics_nonce' ); ?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="openvote_stat_missed_votes"><?php esc_html_e( 'Ilość opuszczonych głosowań', 'openvote' ); ?></label></th>
				<td>
					<input type="range" name="openvote_stat_missed_votes" id="openvote_stat_missed_votes" min="1" max="24" value="<?php echo esc_attr( (string) $missed_votes ); ?>" class="openvote-stat-range">
					<span class="openvote-stat-range-value" data-for="openvote_stat_missed_votes"><?php echo esc_html( (string) $missed_votes ); ?></span>
					<p class="description"><?php esc_html_e( 'To ustawienie określa, ile głosowań musi opuścić (nie brać w ogóle udziału) członek, aby został uznany za nieaktywnego.', 'openvote' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="openvote_stat_months_inactive"><?php esc_html_e( 'Ilość miesięcy', 'openvote' ); ?></label></th>
				<td>
					<input type="range" name="openvote_stat_months_inactive" id="openvote_stat_months_inactive" min="1" max="24" value="<?php echo esc_attr( (string) $months_inactive ); ?>" class="openvote-stat-range">
					<span class="openvote-stat-range-value" data-for="openvote_stat_months_inactive"><?php echo esc_html( (string) $months_inactive ); ?></span>
					<p class="description"><?php esc_html_e( 'Ilość miesięcy, ile musi minąć od ostatniego głosowania, aby został uznany za nieaktywnego.', 'openvote' ); ?></p>
				</td>
			</tr>
		</table>
		<p class="submit">
			<?php submit_button( __( 'Zapisz', 'openvote' ), 'primary', 'submit', false ); ?>
		</p>
		</form>

		<?php if ( current_user_can( 'manage_options' ) ) : ?>
		<style>.openvote-progress-wrap{display:flex;align-items:center;gap:12px;margin:4px 0}.openvote-progress-bar-outer{width:280px;max-width:100%;height:12px;background:#ddd;border-radius:6px;overflow:hidden}.openvote-progress-bar-inner{height:100%;background:#2271b1;transition:width .3s}.openvote-progress-label{margin:0;font-size:12px;color:#555}.openvote-progress-done{color:#0a730a;font-weight:600}.openvote-progress-error{color:#d63638;font-weight:600}</style>
		<div style="margin-top:16px;">
			<button type="button" id="openvote-recalc-inactive-btn" class="button"><?php esc_html_e( 'Przelicz statystyki nieaktywnych', 'openvote' ); ?></button>
			<p class="description" style="margin-top:6px;"><?php esc_html_e( 'Przelicza liczbę opuszczonych głosowań dla wszystkich członków. Przetwarzanie partiami z paskiem postępu (ograniczenie obciążenia serwera).', 'openvote' ); ?></p>
			<div id="openvote-recalc-progress" style="display:none;margin-top:10px;max-width:560px;"></div>
		</div>
		<?php endif; ?>
	</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
	document.querySelectorAll('.openvote-stat-range').forEach(function(el) {
		var val = document.querySelector('.openvote-stat-range-value[data-for="' + el.id + '"]');
		if (val) {
			el.addEventListener('input', function() { val.textContent = this.value; });
		}
	});

	var recalcBtn = document.getElementById('openvote-recalc-inactive-btn');
	var recalcProgress = document.getElementById('openvote-recalc-progress');
	if (recalcBtn && recalcProgress && typeof openvoteRunBatchJob === 'function' && typeof openvoteRenderProgress === 'function') {
		recalcBtn.addEventListener('click', function() {
			var apiRoot = window.openvoteBatch && window.openvoteBatch.apiRoot ? window.openvoteBatch.apiRoot : '/wp-json/openvote/v1';
			var nonce = window.openvoteBatch && window.openvoteBatch.nonce ? window.openvoteBatch.nonce : '';
			recalcBtn.disabled = true;
			recalcProgress.style.display = '';
			recalcProgress.innerHTML = '<p class="openvote-progress-label"><?php echo esc_js( __( 'Uruchamianie…', 'openvote' ) ); ?></p>';

			fetch( apiRoot + '/statistics/recalc-inactive', {
				method: 'POST',
				headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce }
			} ).then(function(r) { return r.json(); }).then(function(data) {
				if (!data.job_id) {
					throw new Error(data.message || '<?php echo esc_js( __( 'Błąd uruchamiania.', 'openvote' ) ); ?>');
				}
				openvoteRunBatchJob(
					data.job_id,
					function(processed, total, pct) {
						openvoteRenderProgress(recalcProgress, processed, total, pct, null);
					},
					function() {
						recalcProgress.innerHTML = '<p class="openvote-progress-done">✓ <?php echo esc_js( __( 'Statystyki nieaktywnych przeliczone.', 'openvote' ) ); ?></p>';
						recalcBtn.disabled = false;
						setTimeout(function() { window.location.reload(); }, 1500);
					},
					function(err) {
						recalcProgress.innerHTML = '<p class="openvote-progress-error">' + (err && err.message ? err.message : '<?php echo esc_js( __( 'Błąd.', 'openvote' ) ); ?>') + '</p>';
						recalcBtn.disabled = false;
					},
					2000
				);
			}).catch(function(err) {
				recalcProgress.innerHTML = '<p class="openvote-progress-error">' + (err && err.message ? err.message : '<?php echo esc_js( __( 'Błąd.', 'openvote' ) ); ?>') + '</p>';
				recalcBtn.disabled = false;
			});
		});
	}
});
</script>
