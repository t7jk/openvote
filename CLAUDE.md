# EP-RWL — System e-głosowania (Wtyczka WordPress)

> Jesteś programistą implementującym wtyczkę WordPress o nazwie **EP-RWL**.  
> Ten plik jest Twoją pełną specyfikacją. Implementuj dokładnie to co tu opisano.  
> Język kodu: PHP 8.1+. Język interfejsu: polski.

---

## STACK TECHNICZNY

- **Platforma:** WordPress 6.4+
- **Backend:** PHP 8.1+, MySQL (tabele własne z prefiksem `wp_evoting_`)
- **Frontend publiczny:** własny szablon PHP + Vanilla JS (AJAX / REST API)
- **Panel admina:** WordPress Admin UI (WP_List_Table, Settings API, własne meta boxy)
- **API:** WordPress REST API (`/wp-json/evoting/v1/`)
- **E-mail:** `wp_mail()`
- **Hooks dynamiczne:** `user_register`, `profile_update`

---

## STRUKTURA PLIKÓW WTYCZKI

```
wp-content/plugins/evoting/
├── evoting.php                  # Główny plik wtyczki (nagłówek, aktywacja, deaktywacja)
├── includes/
│   ├── class-activator.php      # Tworzenie tabel przy aktywacji
│   ├── class-deactivator.php    # Deinstalacja — usuwanie tabel i opcji
│   ├── class-field-map.php      # Mapowanie pól profilu użytkownika
│   ├── class-eligibility.php    # Weryfikacja uprawnień do głosowania
│   ├── class-batch-processor.php # Przetwarzanie partiami po 100 rekordów
│   └── class-role-manager.php   # Zarządzanie rolami i limitami
├── admin/
│   ├── class-admin.php          # Główna klasa panelu admina
│   ├── class-polls-list.php     # Ekran: Lista głosowań (WP_List_Table)
│   ├── class-poll-form.php      # Ekran: Tworzenie/edycja głosowania
│   ├── class-results.php        # Ekran: Wyniki głosowania
│   ├── class-groups.php         # Ekran: Zarządzanie grupami
│   ├── class-roles-admin.php    # Ekran: Zarządzanie rolami i limitami
│   ├── class-config.php         # Ekran: Konfiguracja / mapowanie pól
│   └── views/                   # Szablony PHP ekranów admina
│       ├── polls-list.php
│       ├── poll-form.php
│       ├── results.php
│       ├── groups.php
│       ├── groups-members.php
│       ├── roles.php
│       └── config.php
├── public/
│   ├── class-frontend.php       # Rejestracja strony publicznej
│   ├── class-vote-form.php      # Renderowanie formularza głosowania
│   ├── class-results-view.php   # Renderowanie wyników
│   └── views/
│       ├── page.php             # Główny layout strony /glosowanie
│       ├── vote-form.php
│       ├── results.php
│       └── partials/
│           ├── countdown.php
│           ├── voters-list.php
│           └── progress-bar.php
├── api/
│   ├── class-rest-api.php       # Rejestracja endpointów REST
│   ├── class-vote-endpoint.php  # POST /polls/{id}/vote
│   ├── class-results-endpoint.php # GET /polls/{id}/results
│   └── class-groups-endpoint.php  # GET /groups, GET /groups/{id}/members
├── assets/
│   ├── css/
│   │   ├── admin.css
│   │   └── frontend.css
│   └── js/
│       ├── admin.js             # Panel admina (dynamiczne pytania, batch AJAX)
│       ├── vote.js              # Formularz głosowania (AJAX submit)
│       ├── countdown.js         # Licznik czasu
│       └── batch-progress.js   # Pasek postępu operacji masowych
└── languages/
    └── evoting-pl_PL.po
```

---

## BAZA DANYCH — 6 TABEL

### `wp_evoting_polls`
```sql
CREATE TABLE wp_evoting_polls (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title         VARCHAR(512) NOT NULL,
  description   TEXT,
  status        ENUM('draft','open','closed') DEFAULT 'draft',
  join_mode     ENUM('open','closed') DEFAULT 'open',
  vote_mode     ENUM('public','anonymous') DEFAULT 'public',
  target_groups TEXT,
  notify_start  TINYINT(1) DEFAULT 0,
  notify_end    TINYINT(1) DEFAULT 0,
  date_start    DATE NOT NULL,
  date_end      DATE NOT NULL,
  created_by    BIGINT UNSIGNED NOT NULL,
  created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX (status), INDEX (date_start), INDEX (date_end)
);
```

### `wp_evoting_questions`
```sql
CREATE TABLE wp_evoting_questions (
  id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  poll_id    BIGINT UNSIGNED NOT NULL,
  body       VARCHAR(512) NOT NULL,
  sort_order TINYINT UNSIGNED DEFAULT 0,
  INDEX (poll_id)
);
```

### `wp_evoting_answers`
```sql
CREATE TABLE wp_evoting_answers (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  question_id BIGINT UNSIGNED NOT NULL,
  body        VARCHAR(512) NOT NULL,
  is_abstain  TINYINT(1) DEFAULT 0,
  sort_order  TINYINT UNSIGNED DEFAULT 0,
  INDEX (question_id)
);
```

### `wp_evoting_votes`
```sql
CREATE TABLE wp_evoting_votes (
  id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  poll_id      BIGINT UNSIGNED NOT NULL,
  question_id  BIGINT UNSIGNED NOT NULL,
  user_id      BIGINT UNSIGNED NOT NULL,
  answer_id    BIGINT UNSIGNED NOT NULL,
  is_anonymous TINYINT(1) DEFAULT 0,
  voted_at     DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_vote (poll_id, question_id, user_id),
  INDEX (poll_id), INDEX (user_id)
);
```

### `wp_evoting_groups`
```sql
CREATE TABLE wp_evoting_groups (
  id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name         VARCHAR(255) NOT NULL UNIQUE,
  type         ENUM('city','custom') DEFAULT 'city',
  description  TEXT,
  member_count INT UNSIGNED DEFAULT 0,
  created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX (name), INDEX (type)
);
```

### `wp_evoting_group_members`
```sql
CREATE TABLE wp_evoting_group_members (
  id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  group_id   BIGINT UNSIGNED NOT NULL,
  user_id    BIGINT UNSIGNED NOT NULL,
  source     ENUM('auto','manual') DEFAULT 'auto',
  added_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_member (group_id, user_id),
  INDEX (group_id), INDEX (user_id)
);
```

**Reguły integralności:**
- Usunięcie głosowania → kaskadowo usuwa questions → answers → votes (w PHP, nie FK)
- Usunięcie grupy → NIE usuwa użytkowników, tylko rekordy group_members
- Unikalność głosu: `(poll_id, question_id, user_id)` — klucz UNIQUE w bazie

---

## ROLE I LIMITY

Przechowywane w `wp_usermeta`:
- klucz `evoting_role` → `poll_admin` lub `poll_editor`
- klucz `evoting_groups` → JSON array ID grup (dla Redaktorów)

| Rola                    | Limit         |
|-------------------------|---------------|
| Administrator WordPress | min. 1, maks. 2 |
| Administrator Głosowań  | maks. 3        |
| Redaktor Głosowań       | maks. 3 na grupę |

**Zasady zarządzania rolami:**
- Nie można dodać, gdy limit osiągnięty → komunikat z nazwiskami zajmujących miejsca
- Nie można usunąć ostatniego Administratora WordPress
- Admin WP może usunąć każdego; Admin Głosowań może usunąć Redaktora i innego Admina Głosowań

---

## PRZETWARZANIE PARTIAMI (BATCH) — KLUCZOWY WYMÓG

> ⚠️ Baza ma ponad 10 000 użytkowników. KAŻDA operacja masowa na tabelach użytkowników i grup MUSI być wykonywana partiami po 100 rekordów przez AJAX z paskiem postępu.

### Klasa `EVOTING_Batch_Processor`

```php
class EVOTING_Batch_Processor {

    public static function start_job(string $type, array $params): string {
        $job_id = uniqid('evoting_job_', true);
        set_transient($job_id, [
            'type'      => $type,
            'params'    => $params,
            'offset'    => 0,
            'total'     => 0,
            'processed' => 0,
            'status'    => 'running',
            'results'   => [],
        ], HOUR_IN_SECONDS);
        return $job_id;
    }

    public static function process_batch(string $job_id): array {
        $job = get_transient($job_id);
        // pobierz 100 rekordów od $job['offset']
        // przetwórz, zaktualizuj offset i wyniki
        // jeśli offset >= total → status = 'done'
        set_transient($job_id, $job, HOUR_IN_SECONDS);
        return $job;
    }
}
```

### Frontend pasek postępu (JS)

```javascript
async function runBatchJob(jobId, onProgress, onComplete) {
  const poll = async () => {
    const res = await fetch(`/wp-json/evoting/v1/jobs/${jobId}/progress`, {
      headers: { 'X-WP-Nonce': evotingData.nonce }
    });
    const job = await res.json();
    onProgress(job.processed, job.total, job.results);
    if (job.status === 'running') {
      await fetch(`/wp-json/evoting/v1/jobs/${jobId}/next`, {
        method: 'POST', headers: { 'X-WP-Nonce': evotingData.nonce }
      });
      setTimeout(poll, 500);
    } else {
      onComplete(job.results);
    }
  };
  poll();
}
```

### Operacje wymagające przetwarzania partiami:
1. Synchronizacja użytkowników z grupami
2. Import sugestii miast z bazy użytkowników
3. Wysyłka e-mail o starcie głosowania
4. Wysyłka e-mail przypomnienia (24h przed końcem)
5. Budowanie snapshot listy uprawnionych (tryb zamknięty)

---

## WERYFIKACJA UPRAWNIEŃ — `EVOTING_Eligibility::can_vote()`

Metoda `can_vote(int $user_id, int $poll_id): array` zwraca `['eligible' => bool, 'reason' => string]`.

Sprawdzenia w kolejności:
1. Głosowanie istnieje i ma status `open`
2. Dzisiejsza data mieści się między `date_start` a `date_end`
3. Użytkownik jest zalogowany
4. Profil kompletny: Imię, Nazwisko, Nickname, E-mail, Miasto (wg Field Map)
5. Użytkownik należy do grupy docelowej (lub `target_groups = null`)
6. Użytkownik jeszcze nie głosował
7. Jeśli `join_mode = closed` → użytkownik na liście snapshot

---

## DYNAMICZNE DOŁĄCZANIE — `EVOTING_Dynamic_Join`

```php
add_action('user_register', ['EVOTING_Dynamic_Join', 'on_user_register']);
add_action('profile_update', ['EVOTING_Dynamic_Join', 'on_profile_update'], 10, 2);
```

Logika `check_and_enroll(int $user_id)`:
1. Pobierz aktywne głosowania z `join_mode = 'open'`
2. Dla każdego sprawdź `EVOTING_Eligibility::can_vote($user_id, $poll_id)`
3. Jeśli eligible → dodaj do `wp_evoting_group_members` jeśli jeszcze nie ma

---

## REST API — ENDPOINTY

Prefiks: `/wp-json/evoting/v1/`

| Endpoint | Metoda | Auth | Opis |
|---|---|---|---|
| `/polls` | GET | Redaktor+ | Lista głosowań dla danej roli |
| `/polls/{id}` | GET | Publiczny | Dane + status użytkownika |
| `/polls/{id}/vote` | POST | Zalogowany | Oddanie głosu — pełna walidacja |
| `/polls/{id}/results` | GET | Publiczny | Wyniki — tylko po zamknięciu |
| `/groups` | GET | Admin/Redaktor | Lista grup |
| `/groups/{id}/members` | GET | Admin/Redaktor | Członkowie (partiami po 100) |
| `/groups/{id}/sync` | POST | Admin | Start synchronizacji → zwraca `job_id` |
| `/jobs/{job_id}/progress` | GET | Admin | Status operacji masowej |
| `/jobs/{job_id}/next` | POST | Admin | Przetworz następną partię |

### POST `/polls/{id}/vote` — walidacja serwera (zawsze, niezależnie od frontendu):
1. Nonce WordPress
2. Użytkownik zalogowany
3. `EVOTING_Eligibility::can_vote()` — wszystkie 7 sprawdzeń
4. Każde `answer_id` należy do `question_id` danego głosowania
5. Odpowiedź na każde pytanie jest obecna
6. Jeśli `vote_mode = anonymous` → wymuszaj `is_anonymous = true`

---

## OBLICZANIE WYNIKÓW — `EVOTING_Results::get(int $poll_id)`

> Wyniki obliczane dynamicznie, bez cachowania.

```php
// 1. Policz uprawnionych: użytkownicy w grupach docelowych z kompletnym profilem
// 2. Policz unikalnych głosujących: COUNT(DISTINCT user_id) WHERE poll_id
// 3. Nieuczestniczący = uprawnieni - głosujący
// 4. Dla każdego pytania:
//    - policz głosy per answer_id
//    - do is_abstain += liczba nieuczestniczących
//    - oblicz % względem sumy uprawnionych
// 5. Lista głosujących:
//    - vote_mode = anonymous → tylko count, ZERO danych osobowych
//    - vote_mode = public → zanonimizowane dane
```

### Funkcje anonimizacji:
```php
// "Jan Kowalski" → "Jan...ski"
function evoting_anonymize_nick(string $nick): string {
    if (mb_strlen($nick) <= 6) return str_repeat('.', mb_strlen($nick));
    return mb_substr($nick, 0, 3) . '...' . mb_substr($nick, -3);
}

// "jan@gmail.com" → "jan.........@g....com"
function evoting_anonymize_email(string $email): string {
    [$local, $domain] = explode('@', $email);
    $parts = explode('.', $domain);
    return mb_substr($local, 0, 3) . '.........' .
           '@' . mb_substr($parts[0], 0, 1) . '....' . '.' . end($parts);
}
```

---

## WIDOK WYNIKÓW PUBLICZNYCH

**Tryb jawny (`vote_mode = public`):**
- Zalogowany widzi swój wpis jako pierwszy, bez anonimizacji, z etykietą "(Ty)"
- Pozostali: zanonimizowane nicki (Jan..ski)
- Gość: tylko zanonimizowane nicki

**Tryb anonimowy (`vote_mode = anonymous`):**
- BRAK listy głosujących
- Komunikat: "Głosowanie odbyło się w trybie anonimowym. Wyświetlane są wyłącznie zbiorcze wyniki."
- Dotyczy wszystkich — włącznie z adminem

---

## PANEL ADMINA — MENU

```
E-głosowania
├── Lista głosowań        (page=evoting)
├── Dodaj nowe            (page=evoting-new)
├── Grupy                 (page=evoting-groups)
├── Role                  (page=evoting-roles)
└── Konfiguracja          (page=evoting-config)
```

Dostęp per rola:
- Admin WP: wszystkie ekrany + Role + Konfiguracja
- Admin Głosowań: Lista, Dodaj nowe, Grupy (bez konfiguracji)
- Redaktor: Lista (tylko swoje głosowania), Dodaj nowe (tylko swoje grupy)

---

## FORMULARZ TWORZENIA GŁOSOWANIA — POLA

| Pole | Typ | Uwagi |
|---|---|---|
| Tytuł | input text | wymagane, maks. 512 znaków |
| Opis | textarea | opcjonalne |
| Status | select | Szkic / Otwarte / Zamknięte |
| Data rozpoczęcia | date | wymagane |
| Data zakończenia | date | wymagane |
| Tryb dołączania | radio | Otwarte / Zamknięte |
| Tryb głosowania | radio | Jawne / 🔒 Anonimowe + ostrzeżenie o nieodwracalności |
| Grupy docelowe | multiselect | z listy wp_evoting_groups |
| Powiadomienie start | checkbox | e-mail przy zmianie na Otwarte |
| Powiadomienie koniec | checkbox | e-mail 24h przed datą końca |
| Pytania | dynamiczne | JS, 1–24 pytań, 3–12 odpowiedzi |

Ostatnia odpowiedź każdego pytania to zawsze "Wstrzymuję się" (`is_abstain=1`), zablokowana, nieusuwalna.

---

## POWIADOMIENIA E-MAIL

```php
// Uruchamiane gdy status zmienia się na 'open':
EVOTING_Notifications::send_start_emails(int $poll_id); // zwraca job_id

// Cron codziennie, wysyła 24h przed date_end:
add_action('evoting_check_reminders', ['EVOTING_Notifications', 'check_and_send_reminders']);
```

Rejestracja crona przy aktywacji:
```php
if (!wp_next_scheduled('evoting_check_reminders')) {
    wp_schedule_event(time(), 'daily', 'evoting_check_reminders');
}
```

---

## WYMAGANIA JAKOŚCI KODU

- `sanitize_text_field()`, `absint()`, `wp_kses_post()` — na każdym wejściu
- `esc_html()`, `esc_attr()`, `esc_url()` — na każdym wyjściu
- Nonce na każdym formularzu i żądaniu AJAX
- `$wpdb->prepare()` na każdym zapytaniu SQL
- `current_user_can()` na każdym ekranie admina
- Prefix `evoting_` lub `EVOTING_` na wszystkich funkcjach, klasach, hookach
- Wszystkie stringi przez `__()` / `_e()` z domeną `evoting`

---

## KOLEJNOŚĆ IMPLEMENTACJI

```
Faza 1 — Fundament
  evoting.php + class-activator.php (6 tabel przez dbDelta)
  class-field-map.php + ekran konfiguracji
  class-role-manager.php + ekran ról

Faza 2 — Grupy
  class-batch-processor.php (silnik partiowania)
  class-groups.php + REST /groups + /jobs

Faza 3 — Głosowania admin
  class-polls-list.php (WP_List_Table)
  class-poll-form.php + admin.js (dynamiczne pytania)
  class-eligibility.php (7 sprawdzeń)

Faza 4 — Frontend
  class-frontend.php (rejestracja strony /glosowanie)
  vote-form.php + vote.js + countdown.js

Faza 5 — Wyniki i e-mail
  class-results.php + GET /polls/{id}/results
  class-notifications.php + cron

Faza 6 — Dynamiczne dołączanie
  class-dynamic-join.php (user_register + profile_update)

Faza 7 — Finalizacja
  Ekran deinstalacji, CSS, testy
```

---

## SCENARIUSZE TESTOWE

| # | Scenariusz | Oczekiwany wynik |
|---|---|---|
| 1 | Gość otwiera aktywne głosowanie | Treść pytań tylko do odczytu + zachęta do logowania |
| 2 | Użytkownik bez pola „miasto" | Komunikat o brakującym polu |
| 3 | Użytkownik z Gdańska, głosowanie dla Warszawy | Komunikat o złej grupie |
| 4 | Uprawniony użytkownik oddaje głos | Formularz disabled, potwierdzenie bez przeładowania |
| 5 | Ten sam użytkownik odświeża stronę | Widzi potwierdzenie (nie formularz) |
| 6 | Głosowanie anonimowe — admin patrzy na wyniki | Tylko liczby, zero danych osobowych |
| 7 | Sync 10 000 użytkowników | Pasek postępu, partia po 100, brak błędów MySQL |
| 8 | Próba dodania 3. Admina WP (limit=2) | Komunikat z nazwiskami zajmujących miejsca |
| 9 | Usunięcie jedynego Admina WP | Zablokowane |
| 10 | Nowy user rejestruje się, join_mode=open | Automatycznie dodany do głosowania |
| 11 | Nowy user rejestruje się, join_mode=closed | NIE dodany do głosowania |
| 12 | Zalogowany user patrzy na wyniki (tryb jawny) | Swój wpis pierwszy, bez anonimizacji + "(Ty)" |
