/**
 * EP-RWL — Pasek postępu operacji masowych (batch jobs).
 *
 * Używa: window.openvoteBatch.nonce i window.openvoteBatch.apiRoot
 * Opcjonalnie: window.openvoteBatch.emailDelay (ms między partiami e-mail)
 */

/**
 * Uruchom wizualizację zadania masowego.
 *
 * @param {string}   jobId       ID zadania zwrócony przez REST API.
 * @param {Function} onProgress  Callback(processed, total, pct).
 * @param {Function} onComplete  Callback(jobData).
 * @param {Function} onError     Callback(error).
 * @param {number}   [delayMs]   Opóźnienie między partiami w ms (nadpisuje evotingBatch.emailDelay).
 */
async function openvoteRunBatchJob( jobId, onProgress, onComplete, onError, delayMs ) {
	const apiRoot    = window.openvoteBatch?.apiRoot    || '/wp-json/openvote/v1';
	const nonce      = window.openvoteBatch?.nonce      || '';
	const emailDelay = delayMs ?? ( window.openvoteBatch?.emailDelay ?? 300 );

	const headers = {
		'Content-Type':  'application/json',
		'X-WP-Nonce':    nonce,
	};

	const poll = async () => {
		try {
			// 1. Pobierz aktualny stan
			const progressRes = await fetch( `${apiRoot}/jobs/${encodeURIComponent(jobId)}/progress`, { headers } );
			if ( ! progressRes.ok ) {
				const err = await progressRes.json().catch( () => ({}) );
				throw new Error( err.message || `HTTP ${progressRes.status}` );
			}
			const job = await progressRes.json();

			onProgress( job.processed, job.total, job.pct );

			if ( 'done' === job.status ) {
				onComplete( job );
				return;
			}

			// 2. Przetwórz następną partię
			const nextRes = await fetch( `${apiRoot}/jobs/${encodeURIComponent(jobId)}/next`, {
				method:  'POST',
				headers,
			} );
			if ( ! nextRes.ok ) {
				const err = await nextRes.json().catch( () => ({}) );
				throw new Error( err.message || `HTTP ${nextRes.status}` );
			}

			// 3. Odczekaj emailDelay ms, powtórz (throttle)
			setTimeout( poll, emailDelay );

		} catch ( err ) {
			if ( typeof onError === 'function' ) {
				onError( err );
			} else {
				console.error( '[evoting batch]', err );
			}
		}
	};

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
		localStorage.setItem( 'evoting_job_' + pollId, jobId );
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
		return localStorage.getItem( 'evoting_job_' + pollId );
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
		localStorage.removeItem( 'evoting_job_' + pollId );
	} catch ( e ) { /* ignoruj */ }
}

/**
 * Pomocnicza funkcja: renderuje pasek postępu w podanym kontenerze.
 *
 * @param {HTMLElement} container  Element DOM do renderowania.
 * @param {number}      processed  Przetworzone rekordy.
 * @param {number}      total      Łączna liczba rekordów.
 * @param {number}      pct        Procent ukończenia.
 */
function openvoteRenderProgress( container, processed, total, pct ) {
	if ( ! container ) return;

	container.innerHTML = `
		<div class="evoting-progress-wrap">
			<div class="evoting-progress-bar-outer">
				<div class="evoting-progress-bar-inner" style="width:${pct}%"></div>
			</div>
			<p class="evoting-progress-label">${processed} / ${total} (${pct}%)</p>
		</div>
	`;
}

/**
 * Podłącz obsługę przycisku "Synchronizuj" w panelu grup.
 */
document.addEventListener( 'DOMContentLoaded', function () {
	// Przycisk sync pojedynczej grupy.
	document.querySelectorAll( '.evoting-sync-group-btn' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			const groupId   = btn.dataset.groupId;
			const container = document.getElementById( 'evoting-sync-progress-' + groupId );
			const apiRoot   = window.openvoteBatch?.apiRoot || '/wp-json/openvote/v1';
			const nonce     = window.openvoteBatch?.nonce   || '';

			btn.disabled = true;
			if ( container ) {
				container.innerHTML = '<p class="evoting-progress-label">Uruchamianie synchronizacji…</p>';
			}

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
						( processed, total, pct ) => openvoteRenderProgress( container, processed, total, pct ),
						( job ) => {
							if ( container ) {
								container.innerHTML = `<p class="evoting-progress-done">✓ Synchronizacja zakończona. Przetworzone: ${job.processed}</p>`;
							}
							btn.disabled = false;
							// Odśwież licznik po 2s.
							setTimeout( () => location.reload(), 2000 );
						},
						( err ) => {
							if ( container ) {
								const p = document.createElement( 'p' );
								p.className = 'evoting-progress-error';
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
						p.className = 'evoting-progress-error';
						p.textContent = 'Błąd: ' + err.message;
						container.innerHTML = '';
						container.appendChild( p );
					}
					btn.disabled = false;
				} );
		} );
	} );

	// Przycisk sync-all.
	const syncAllBtn = document.getElementById( 'evoting-sync-all-btn' );
	if ( syncAllBtn ) {
		syncAllBtn.addEventListener( 'click', function () {
			const container = document.getElementById( 'evoting-sync-all-progress' );
			const apiRoot   = window.openvoteBatch?.apiRoot || '/wp-json/openvote/v1';
			const nonce     = window.openvoteBatch?.nonce   || '';

			syncAllBtn.disabled = true;
			if ( container ) {
				container.innerHTML = '<p class="evoting-progress-label">Uruchamianie synchronizacji…</p>';
			}

			fetch( `${apiRoot}/groups/sync-all`, {
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
						( processed, total, pct ) => openvoteRenderProgress( container, processed, total, pct ),
						( job ) => {
							if ( container ) {
								container.innerHTML = `<p class="evoting-progress-done">✓ Synchronizacja zakończona. Przetworzone grupy: ${job.processed}</p>`;
							}
							syncAllBtn.disabled = false;
							setTimeout( () => location.reload(), 2000 );
						},
						( err ) => {
							if ( container ) {
								const p = document.createElement( 'p' );
								p.className = 'evoting-progress-error';
								p.textContent = 'Błąd: ' + err.message;
								container.innerHTML = '';
								container.appendChild( p );
							}
							syncAllBtn.disabled = false;
						}
					);
				} )
				.catch( err => {
					if ( container ) {
						const p = document.createElement( 'p' );
						p.className = 'evoting-progress-error';
						p.textContent = 'Błąd: ' + err.message;
						container.innerHTML = '';
						container.appendChild( p );
					}
					syncAllBtn.disabled = false;
				} );
		} );
	}
} );
