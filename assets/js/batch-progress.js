/**
 * EP-RWL — Pasek postępu operacji masowych (batch jobs).
 *
 * Używa: window.openvoteBatch.nonce i window.openvoteBatch.apiRoot
 * Opcjonalnie: window.openvoteBatch.emailDelay (ms między partiami e-mail)
 */

/**
 * Uruchom wizualizację zadania masowego.
 *
 * @param {string}   jobId            ID zadania zwrócony przez REST API.
 * @param {Function} onProgress       Callback(processed, total, pct, job). job has optional .logs array.
 * @param {Function} onComplete      Callback(jobData).
 * @param {Function} onError          Callback(error).
 * @param {number}   [delayMs]        Opóźnienie między partiami w ms (nadpisuje openvoteBatch.emailDelay).
 * @param {Function} [onLimitExceeded] Callback(job) gdy status === 'limit_exceeded'. job ma limit_message, wait_seconds, limit_type.
 */
async function openvoteRunBatchJob( jobId, onProgress, onComplete, onError, delayMs, onLimitExceeded ) {
	const apiRoot    = window.openvoteBatch?.apiRoot    || '/wp-json/openvote/v1';
	const nonce      = window.openvoteBatch?.nonce      || '';
	const emailDelay = delayMs ?? ( window.openvoteBatch?.emailDelay ?? 300 );
	const progressPollMs = 30000; // odpytywanie GET progress co 30 s (BitNinja / limit żądań)

	const headers = {
		'Content-Type':  'application/json',
		'X-WP-Nonce':    nonce,
	};

	let progressIntervalId = null;

	const stopProgressPoll = () => {
		if ( progressIntervalId !== null ) {
			clearInterval( progressIntervalId );
			progressIntervalId = null;
		}
	};

	const fetchProgress = async () => {
		try {
			const url = `${apiRoot}/jobs/${encodeURIComponent(jobId)}/progress?t=${Date.now()}`;
			const res = await fetch( url, { headers, cache: 'no-store' } );
			if ( ! res.ok ) return null;
			return await res.json();
		} catch ( e ) {
			return null;
		}
	};

	const poll = async () => {
		try {
			// 1. Pobierz aktualny stan
			const job = await fetchProgress();
			if ( ! job ) {
				throw new Error( 'Błąd odczytu postępu' );
			}

			onProgress( job.processed, job.total, job.pct, job );

			if ( 'done' === job.status || 'cancelled' === job.status ) {
				stopProgressPoll();
				onComplete( job );
				return;
			}

			if ( 'limit_exceeded' === job.status ) {
				stopProgressPoll();
				if ( typeof onLimitExceeded === 'function' ) {
					onLimitExceeded( job );
				} else if ( typeof onError === 'function' ) {
					onError( new Error( job.limit_message || 'Limit wysyłki przekroczony.' ) );
				}
				return;
			}

			// 2. Przetwórz następną partię (z ponowieniem przy 503 — rate limit WAF/BitNinja)
			const nextUrl = `${apiRoot}/jobs/${encodeURIComponent(jobId)}/next`;
			let nextRes = null;
			let lastErr = null;
			for ( let attempt = 0; attempt < 3; attempt++ ) {
				nextRes = await fetch( nextUrl, { method: 'POST', headers } );
				if ( nextRes.ok ) break;
				if ( nextRes.status === 503 && attempt < 2 ) {
					const waitMs = ( attempt + 1 ) * 5000;
					await new Promise( r => setTimeout( r, waitMs ) );
					lastErr = new Error( 'HTTP 503 po 3 próbach. WAF (np. BitNinja) lub serwer limituje żądania — odczekaj kilka minut i uruchom synchronizację ponownie.' );
					continue;
				}
				const err = await nextRes.json().catch( () => ({}) );
				throw new Error( err.message || `HTTP ${nextRes.status}` );
			}
			if ( ! nextRes || ! nextRes.ok ) {
				throw lastErr || new Error( 'HTTP 503. Serwer lub WAF może limitować żądań — odczekaj chwilę i spróbuj ponownie.' );
			}

			// 3. Odczekaj emailDelay ms, powtórz (throttle)
			setTimeout( poll, emailDelay );

		} catch ( err ) {
			stopProgressPoll();
			if ( typeof onError === 'function' ) {
				onError( err );
			} else {
				console.error( '[openvote batch]', err );
			}
		}
	};

	// Odpytywanie GET progress co 30 s — log i pasek postępu na bieżąco podczas długiego przetwarzania
	progressIntervalId = setInterval( async () => {
		const job = await fetchProgress();
		if ( job ) {
			onProgress( job.processed, job.total, job.pct, job );
			if ( 'done' === job.status || 'cancelled' === job.status || 'limit_exceeded' === job.status ) {
				stopProgressPoll();
			}
		}
	}, progressPollMs );

	poll();
}

/**
 * Zapisz job_id w localStorage — umożliwia wznowienie po zamknięciu karty.
 *
 * @param {string} pollId  ID głosowania (klucz lokalny).
 * @param {string} jobId   ID zadania.
 */
function openvoteSaveJobId( pollId, jobId ) {
	try {
		localStorage.setItem( 'openvote_job_' + pollId, jobId );
	} catch ( e ) { /* ignoruj — prywatny tryb przeglądarki */ }
}

/**
 * Odczytaj zapisany job_id dla danego głosowania.
 *
 * @param {string} pollId
 * @return {string|null}
 */
function openvoteGetSavedJobId( pollId ) {
	try {
		return localStorage.getItem( 'openvote_job_' + pollId );
	} catch ( e ) {
		return null;
	}
}

/**
 * Usuń zapisany job_id po zakończeniu wysyłki.
 *
 * @param {string} pollId
 */
function openvoteClearJobId( pollId ) {
	try {
		localStorage.removeItem( 'openvote_job_' + pollId );
	} catch ( e ) { /* ignoruj */ }
}

/**
 * Pomocnicza funkcja: renderuje pasek postępu w podanym kontenerze.
 *
 * @param {HTMLElement} container  Element DOM do renderowania.
 * @param {number}      processed  Przetworzone rekordy.
 * @param {number}      total      Łączna liczba rekordów.
 * @param {number}      pct        Procent ukończenia.
 * @param {Object}      [job]      Opcjonalnie: obiekt job; jeśli job.users_synced — pokazuje też liczbę zsynchronizowanych użytkowników.
 */
function openvoteRenderProgress( container, processed, total, pct, job ) {
	if ( ! container ) return;

	let barPct = pct;
	if ( job && typeof job.users_synced === 'number' && typeof job.total_users === 'number' && job.total_users > 0 ) {
		barPct = Math.round( ( job.users_synced / job.total_users ) * 100 );
		if ( job.users_synced > 0 && job.users_synced < job.total_users && barPct < 1 ) {
			barPct = 1;
		}
	}

	let label = processed + ' / ' + total + ' (' + pct + '%)';
	if ( job && typeof job.users_synced === 'number' ) {
		label = 'Miasta: ' + label + ' · Zsynchronizowano użytkowników: ' + job.users_synced.toLocaleString( 'pl-PL' );
		if ( typeof job.total_users === 'number' && job.total_users > 0 ) {
			label += ' / ' + job.total_users.toLocaleString( 'pl-PL' ) + ' (' + barPct + '%)';
		}
		if ( typeof job.estimated_minutes_remaining === 'number' && job.estimated_minutes_remaining > 0 ) {
			label += ' · Szac. czas do zakończenia: ok. ' + job.estimated_minutes_remaining + ' min';
		}
	}

	container.innerHTML = `
		<div class="openvote-progress-wrap">
			<div class="openvote-progress-bar-outer">
				<div class="openvote-progress-bar-inner" style="width:${barPct}%"></div>
			</div>
			<p class="openvote-progress-label">${label}</p>
		</div>
	`;
}

/**
 * Tworzy panel logów synchronizacji pod kontenerem postępu.
 * Zwraca obiekt z metodą update(job), która dopisuje nowe linie z job.logs i przewija na dół.
 *
 * @param {HTMLElement} progressContainer  Kontener postępu (np. #openvote-sync-all-progress).
 * @return {{ update: function(Object): void }}
 */
function openvoteCreateSyncLogPanel( progressContainer ) {
	if ( ! progressContainer ) return { update: function () {} };
	let lastLogIndex = 0;
	const wrap = document.createElement( 'div' );
	wrap.className = 'openvote-sync-log-panel';
	wrap.style.marginTop = '12px';
	wrap.innerHTML = `
		<pre class="openvote-sync-log-content" style="max-height:280px;overflow:auto;background:#1e1e1e;color:#d4d4d4;padding:10px;font-size:12px;border:1px solid #333;"></pre>
		<button type="button" class="button openvote-sync-log-copy" style="margin-top:6px;">Kopiuj log</button>
		<button type="button" class="button openvote-sync-log-close" style="margin-top:6px;margin-left:6px;">Zamknij</button>
	`;
	progressContainer.after( wrap );
	const pre = wrap.querySelector( '.openvote-sync-log-content' );
	const copyBtn = wrap.querySelector( '.openvote-sync-log-copy' );
	const closeBtn = wrap.querySelector( '.openvote-sync-log-close' );
	copyBtn.addEventListener( 'click', function () {
		const text = pre.textContent || '';
		if ( ! text ) return;
		navigator.clipboard.writeText( text ).then( function () {
			copyBtn.textContent = 'Skopiowano';
			setTimeout( function () { copyBtn.textContent = 'Kopiuj log'; }, 2000 );
		} );
	} );
	closeBtn.addEventListener( 'click', function () {
		wrap.style.display = 'none';
	} );
	return {
		update: function ( job ) {
			if ( ! job || ! Array.isArray( job.logs ) ) return;
			if ( job.logs.length <= lastLogIndex ) return;
			const fragment = document.createDocumentFragment();
			for ( let i = lastLogIndex; i < job.logs.length; i++ ) {
				fragment.appendChild( document.createTextNode( job.logs[ i ] + '\n' ) );
			}
			pre.appendChild( fragment );
			lastLogIndex = job.logs.length;
			pre.scrollTop = pre.scrollHeight;
		},
	};
}

document.addEventListener( 'DOMContentLoaded', function () {
	// Przycisk sync pojedynczej grupy.
	document.querySelectorAll( '.openvote-sync-group-btn' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			const groupId   = btn.dataset.groupId;
			const container = document.getElementById( 'openvote-sync-progress-' + groupId );
			const apiRoot   = window.openvoteBatch?.apiRoot || '/wp-json/openvote/v1';
			const nonce     = window.openvoteBatch?.nonce   || '';

			btn.disabled = true;
			if ( container ) {
				container.innerHTML = '<p class="openvote-progress-label">Uruchamianie synchronizacji…</p>';
			}
			const logPanel = openvoteCreateSyncLogPanel( container );

			fetch( `${apiRoot}/groups/${groupId}/sync`, {
				method:  'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce':   nonce,
				},
			} )
				.then( r => r.json() )
				.then( data => {
					if ( ! data.job_id ) {
						throw new Error( data.message || 'Błąd uruchamiania zadania.' );
					}

					openvoteRunBatchJob(
						data.job_id,
						( processed, total, pct, job ) => {
							openvoteRenderProgress( container, processed, total, pct, job );
							if ( logPanel ) logPanel.update( job );
						},
						( job ) => {
							if ( container ) {
								container.innerHTML = `<p class="openvote-progress-done">✓ Synchronizacja zakończona. Przetworzone: ${job.processed}</p>`;
							}
							if ( logPanel ) logPanel.update( job );
							btn.disabled = false;
						},
						( err ) => {
							if ( container ) {
								const p = document.createElement( 'p' );
								p.className = 'openvote-progress-error';
								p.textContent = 'Błąd: ' + err.message;
								container.innerHTML = '';
								container.appendChild( p );
							}
							btn.disabled = false;
						}
					);
				} )
				.catch( err => {
					if ( container ) {
						const p = document.createElement( 'p' );
						p.className = 'openvote-progress-error';
						p.textContent = 'Błąd: ' + err.message;
						container.innerHTML = '';
						container.appendChild( p );
					}
					btn.disabled = false;
				} );
		} );
	} );

	// Przyciski sync-all i sync-all od początku (reset).
	const syncAllBtn     = document.getElementById( 'openvote-sync-all-btn' );
	const syncAllResetBtn = document.getElementById( 'openvote-sync-all-reset-btn' );
	const syncAllStopBtn = document.getElementById( 'openvote-sync-all-stop-btn' );

	function runSyncAllGroups( resetFromStart ) {
		const container = document.getElementById( 'openvote-sync-all-progress' );
		const apiRoot   = window.openvoteBatch?.apiRoot || '/wp-json/openvote/v1';
		const nonce     = window.openvoteBatch?.nonce   || '';

		if ( syncAllBtn ) syncAllBtn.disabled = true;
		if ( syncAllResetBtn ) syncAllResetBtn.disabled = true;
		if ( syncAllStopBtn ) syncAllStopBtn.style.display = 'inline-block';
		if ( container ) {
			container.innerHTML = '<p class="openvote-progress-label">Uruchamianie synchronizacji…</p>';
		}
		const logPanel = openvoteCreateSyncLogPanel( container );

		function doSyncAll( retries, resetFromStart ) {
			retries = retries || 0;
			return fetch( `${apiRoot}/groups/sync-all`, {
				method:  'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce':   nonce,
				},
				body: resetFromStart ? JSON.stringify( { reset: true } ) : undefined,
			} ).then( function ( r ) {
				if ( r.status === 503 && retries < 1 && container ) {
					container.innerHTML = '<p class="openvote-progress-label">Serwer zajęty (503). Ponawiam za 2 s…</p>';
					return new Promise( function ( resolve ) { setTimeout( resolve, 2000 ); } ).then( function () {
						return doSyncAll( retries + 1, resetFromStart );
					} );
				}
				return r.json().then( function ( data ) {
					if ( ! data.job_id ) {
						throw new Error( data.message || ( r.status === 503 ? 'Serwer niedostępny (503). Spróbuj ponownie za chwilę.' : 'Błąd uruchamiania zadania.' ) );
					}
					return data;
				} );
			} );
		}
		doSyncAll( 0, resetFromStart )
			.then( function ( data ) {
				if ( syncAllStopBtn ) {
					syncAllStopBtn.onclick = function () {
						syncAllStopBtn.disabled = true;
						fetch( `${apiRoot}/jobs/${encodeURIComponent( data.job_id )}/stop`, {
							method:  'POST',
							headers: { 'X-WP-Nonce': nonce },
						} ).finally( function () { syncAllStopBtn.disabled = false; } );
					};
				}

				openvoteRunBatchJob(
					data.job_id,
					function ( processed, total, pct, job ) {
						openvoteRenderProgress( container, processed, total, pct, job );
						if ( logPanel ) logPanel.update( job );
					},
					function ( job ) {
						if ( container ) {
							if ( job.status === 'cancelled' ) {
								container.innerHTML = '<p class="openvote-progress-label">Zatrzymano przez użytkownika. Przetworzone: ' + job.processed + '</p>';
							} else {
								container.innerHTML = `<p class="openvote-progress-done">✓ Synchronizacja zakończona. Przetworzone grupy: ${job.processed}</p>`;
							}
						}
						if ( logPanel ) logPanel.update( job );
						if ( syncAllBtn ) syncAllBtn.disabled = false;
						if ( syncAllResetBtn ) syncAllResetBtn.disabled = false;
						if ( syncAllStopBtn ) syncAllStopBtn.style.display = 'none';
					},
					function ( err ) {
						if ( container ) {
							var p = document.createElement( 'p' );
							p.className = 'openvote-progress-error';
							p.textContent = 'Błąd: ' + err.message;
							container.innerHTML = '';
							container.appendChild( p );
						}
						if ( syncAllBtn ) syncAllBtn.disabled = false;
						if ( syncAllResetBtn ) syncAllResetBtn.disabled = false;
						if ( syncAllStopBtn ) syncAllStopBtn.style.display = 'none';
					},
					10000
				);
			} )
			.catch( function ( err ) {
				if ( container ) {
					var p = document.createElement( 'p' );
					p.className = 'openvote-progress-error';
					p.textContent = 'Błąd: ' + err.message;
					container.innerHTML = '';
					container.appendChild( p );
				}
				if ( syncAllBtn ) syncAllBtn.disabled = false;
				if ( syncAllResetBtn ) syncAllResetBtn.disabled = false;
				if ( syncAllStopBtn ) syncAllStopBtn.style.display = 'none';
			} );
	}

	if ( syncAllBtn ) {
		syncAllBtn.addEventListener( 'click', function () { runSyncAllGroups( false ); } );
	}
	if ( syncAllResetBtn ) {
		syncAllResetBtn.addEventListener( 'click', function () { runSyncAllGroups( true ); } );
	}
} );
