<?php
defined( 'ABSPATH' ) || exit;
$brand = openvote_get_brand_short_name();
?>
<div class="wrap openvote-manual">

<h1><?php printf( esc_html__( 'Podręcznik użytkownika — %s', 'openvote' ), esc_html( $brand ) ); ?></h1>

<style>
.openvote-manual { max-width: 860px; }
.openvote-manual h2 { font-size: 1.4em; margin-top: 2em; border-bottom: 2px solid #c3c4c7; padding-bottom: 6px; }
.openvote-manual h3 { font-size: 1.1em; margin-top: 1.4em; color: #1d2327; }
.openvote-manual h4 { font-size: 1em; margin-top: 1.2em; color: #3c434a; }
.openvote-manual p, .openvote-manual li { color: #3c434a; line-height: 1.7; }
.openvote-manual ul, .openvote-manual ol { padding-left: 22px; }
.openvote-manual ul li { list-style: disc; }
.openvote-manual ol li { list-style: decimal; }
.openvote-manual .openvote-manual__toc { background: #f6f7f7; border: 1px solid #c3c4c7; border-radius: 4px; padding: 16px 20px; margin-bottom: 2em; }
.openvote-manual .openvote-manual__toc ol { margin: 8px 0 0; }
.openvote-manual .openvote-manual__toc a { text-decoration: none; }
.openvote-manual .openvote-manual__toc a:hover { text-decoration: underline; }
.openvote-manual .openvote-manual__note { background: #fff8e1; border-left: 4px solid #ffb900; padding: 10px 14px; margin: 12px 0; border-radius: 0 4px 4px 0; }
.openvote-manual .openvote-manual__tip  { background: #edfaef; border-left: 4px solid #00a32a; padding: 10px 14px; margin: 12px 0; border-radius: 0 4px 4px 0; }
.openvote-manual .openvote-manual__warn { background: #fcf0f1; border-left: 4px solid #d63638; padding: 10px 14px; margin: 12px 0; border-radius: 0 4px 4px 0; }
.openvote-manual table.openvote-manual__table { border-collapse: collapse; width: 100%; margin: 12px 0; }
.openvote-manual table.openvote-manual__table th,
.openvote-manual table.openvote-manual__table td { border: 1px solid #c3c4c7; padding: 8px 12px; text-align: left; }
.openvote-manual table.openvote-manual__table th { background: #f6f7f7; font-weight: 600; }
.openvote-manual .openvote-manual__section { margin-bottom: 2.5em; }
</style>

<!-- Spis treści -->
<div class="openvote-manual__toc">
    <strong><?php esc_html_e( 'Spis treści', 'openvote' ); ?></strong>
    <ol>
        <li><a href="#manual-intro"><?php esc_html_e( 'Czym jest wtyczka i jak działa', 'openvote' ); ?></a></li>
        <li><a href="#manual-roles"><?php esc_html_e( 'Role użytkowników i uprawnienia', 'openvote' ); ?></a></li>
        <li><a href="#manual-groups"><?php esc_html_e( 'Grupy użytkowników', 'openvote' ); ?></a></li>
        <li><a href="#manual-coordinators"><?php esc_html_e( 'Koordynatorzy', 'openvote' ); ?></a></li>
        <li><a href="#manual-polls"><?php esc_html_e( 'Głosowania — tworzenie i zarządzanie', 'openvote' ); ?></a></li>
        <li><a href="#manual-vote"><?php esc_html_e( 'Jak odbywa się głosowanie (widok uczestnika)', 'openvote' ); ?></a></li>
        <li><a href="#manual-results"><?php esc_html_e( 'Wyniki głosowania', 'openvote' ); ?></a></li>
        <li><a href="#manual-invitations"><?php esc_html_e( 'Zaproszenia e-mail', 'openvote' ); ?></a></li>
        <li><a href="#manual-config"><?php esc_html_e( 'Konfiguracja wtyczki', 'openvote' ); ?></a></li>
        <li><a href="#manual-fieldmap"><?php esc_html_e( 'Mapowanie pól użytkownika', 'openvote' ); ?></a></li>
        <li><a href="#manual-surveys"><?php esc_html_e( 'Ankiety i strona zgłoszeń', 'openvote' ); ?></a></li>
        <li><a href="#manual-faq"><?php esc_html_e( 'Najczęstsze pytania (FAQ)', 'openvote' ); ?></a></li>
    </ol>
</div>


<!-- 1. Intro -->
<div class="openvote-manual__section" id="manual-intro">
<h2>1. <?php esc_html_e( 'Czym jest wtyczka i jak działa', 'openvote' ); ?></h2>
<p><?php printf(
    esc_html__( '%s to wtyczka WordPress służąca do przeprowadzania elektronicznych głosowań wśród zarejestrowanych użytkowników witryny. Umożliwia tworzenie głosowań wielopytaniowych, definiowanie grup docelowych, wysyłkę zaproszeń e-mail oraz prezentację wyników — w formie zbiorczej lub jawnej.', 'openvote' ),
    '<strong>' . esc_html( $brand ) . '</strong>'
); ?></p>

<h3><?php esc_html_e( 'Główne elementy systemu', 'openvote' ); ?></h3>
<ul>
    <li><strong><?php esc_html_e( 'Głosowania', 'openvote' ); ?></strong> — <?php esc_html_e( 'zestaw pytań z określonym czasem trwania i grupą docelową.', 'openvote' ); ?></li>
    <li><strong><?php esc_html_e( 'Pytania i odpowiedzi', 'openvote' ); ?></strong> — <?php esc_html_e( 'każde głosowanie zawiera 1–24 pytania, każde pytanie ma 3–12 odpowiedzi. Ostatnia odpowiedź to zawsze "Wstrzymuję się".', 'openvote' ); ?></li>
    <li><strong><?php esc_html_e( 'Grupy', 'openvote' ); ?></strong> — <?php esc_html_e( 'zbiory użytkowników (np. według miasta). Głosowanie może być skierowane do jednej lub wielu grup.', 'openvote' ); ?></li>
    <li><strong><?php esc_html_e( 'Koordynatorzy', 'openvote' ); ?></strong> — <?php esc_html_e( 'użytkownicy z uprawnieniami do zarządzania głosowaniami przypisanymi do konkretnych grup.', 'openvote' ); ?></li>
    <li><strong><?php esc_html_e( 'Strona głosowania', 'openvote' ); ?></strong> — <?php esc_html_e( 'publiczna strona WordPress z blokiem głosowania, dostępna pod adresem skonfigurowanym w Konfiguracji.', 'openvote' ); ?></li>
</ul>

<h3><?php esc_html_e( 'Przepływ pracy', 'openvote' ); ?></h3>
<ol>
    <li><?php esc_html_e( 'Administrator tworzy grupy i przypisuje do nich użytkowników.', 'openvote' ); ?></li>
    <li><?php esc_html_e( 'Administrator (lub Koordynator) tworzy głosowanie, wybiera grupy docelowe i pyta.', 'openvote' ); ?></li>
    <li><?php esc_html_e( 'Kliknięcie "Wystartuj głosowanie" zmienia status na Rozpoczęte i wysyła zaproszenia e-mail.', 'openvote' ); ?></li>
    <li><?php esc_html_e( 'Uprawnieni użytkownicy logują się, wchodzą na stronę głosowania i oddają głos.', 'openvote' ); ?></li>
    <li><?php esc_html_e( 'Po zakończeniu głosowania wyniki są dostępne w zakładce "Zakończone głosowania".', 'openvote' ); ?></li>
</ol>
</div>


<!-- 2. Role -->
<div class="openvote-manual__section" id="manual-roles">
<h2>2. <?php esc_html_e( 'Role użytkowników i uprawnienia', 'openvote' ); ?></h2>
<p><?php esc_html_e( 'Wtyczka rozróżnia role WordPress i własne role wtyczki.', 'openvote' ); ?></p>

<table class="openvote-manual__table">
    <thead><tr>
        <th><?php esc_html_e( 'Rola', 'openvote' ); ?></th>
        <th><?php esc_html_e( 'Uprawnienia', 'openvote' ); ?></th>
    </tr></thead>
    <tbody>
    <tr>
        <td><strong><?php esc_html_e( 'Administrator WordPress', 'openvote' ); ?></strong></td>
        <td><?php esc_html_e( 'Pełny dostęp: wszystkie ekrany, Konfiguracja, Role, zarządzanie grupami i koordynatorami, pobieranie PDF.', 'openvote' ); ?></td>
    </tr>
    <tr>
        <td><strong><?php esc_html_e( 'Editor / Author WordPress', 'openvote' ); ?></strong></td>
        <td><?php esc_html_e( 'Dostęp do menu E-głosowań (lista głosowań, grupy, koordynatorzy). Może tworzyć głosowania.', 'openvote' ); ?></td>
    </tr>
    <tr>
        <td><strong><?php esc_html_e( 'Koordynator (rola wtyczki)', 'openvote' ); ?></strong></td>
        <td><?php esc_html_e( 'Widzi i zarządza głosowaniami przypisanymi do swoich grup. Może tworzyć głosowania dla swoich grup.', 'openvote' ); ?></td>
    </tr>
    <tr>
        <td><strong><?php esc_html_e( 'Zwykły użytkownik (Subscriber)', 'openvote' ); ?></strong></td>
        <td><?php esc_html_e( 'Może oddawać głosy. Menu w panelu jest widoczne, ale nieaktywne (kursywa).', 'openvote' ); ?></td>
    </tr>
    </tbody>
</table>

<div class="openvote-manual__note">
    <strong><?php esc_html_e( 'Limity ról', 'openvote' ); ?></strong><br>
    <?php esc_html_e( 'Administratorów WordPress może być co najmniej 1, maksymalnie 2. Nie można usunąć ostatniego Administratora WordPress. Koordynatorów może być wielu i można ich przypisywać do wielu grup jednocześnie.', 'openvote' ); ?>
</div>

<h3><?php esc_html_e( 'Jak nadać rolę Koordynatora', 'openvote' ); ?></h3>
<ol>
    <li><?php esc_html_e( 'Przejdź do menu Open Vote → Koordynatorzy.', 'openvote' ); ?></li>
    <li><?php esc_html_e( 'W lewej kolumnie znajdź użytkownika (wpisz imię lub nazwisko).', 'openvote' ); ?></li>
    <li><?php esc_html_e( 'W prawej kolumnie wybierz grupę (lub kilka grup).', 'openvote' ); ?></li>
    <li><?php esc_html_e( 'Kliknij "Dodaj". Użytkownik stanie się Koordynatorem wybranych grup.', 'openvote' ); ?></li>
</ol>
</div>


<!-- 3. Grupy -->
<div class="openvote-manual__section" id="manual-groups">
<h2>3. <?php esc_html_e( 'Grupy użytkowników', 'openvote' ); ?></h2>
<p><?php esc_html_e( 'Grupy służą do definiowania kto ma prawo głosować w danym głosowaniu. Użytkownik musi należeć do co najmniej jednej grupy docelowej głosowania, aby móc oddać głos.', 'openvote' ); ?></p>

<h3><?php esc_html_e( 'Tworzenie grupy', 'openvote' ); ?></h3>
<ol>
    <li><?php esc_html_e( 'Przejdź do menu Open Vote → Grupy.', 'openvote' ); ?></li>
    <li><?php esc_html_e( 'Kliknij "Dodaj grupę", wpisz nazwę (np. nazwę miasta lub oddziału).', 'openvote' ); ?></li>
    <li><?php esc_html_e( 'Zapisz. Grupa pojawi się na liście.', 'openvote' ); ?></li>
</ol>

<h3><?php esc_html_e( 'Dodawanie użytkowników do grupy', 'openvote' ); ?></h3>
<p><?php esc_html_e( 'Są trzy sposoby:', 'openvote' ); ?></p>
<ul>
    <li><strong><?php esc_html_e( 'Ręcznie', 'openvote' ); ?></strong> — <?php esc_html_e( 'na ekranie Grupy wyszukaj użytkownika, wybierz grupę (lub kilka) i kliknij "Dodaj".', 'openvote' ); ?></li>
    <li><strong><?php esc_html_e( 'Automatycznie (przycisk "Auto")', 'openvote' ); ?></strong> — <?php esc_html_e( 'system przeszukuje wszystkich użytkowników z wypełnionym polem "Miasto" i przypisuje ich do grupy o tej samej nazwie. Grupy, które nie istnieją, zostaną automatycznie utworzone.', 'openvote' ); ?></li>
    <li><strong><?php esc_html_e( 'Przez rejestrację', 'openvote' ); ?></strong> — <?php esc_html_e( 'gdy nowy użytkownik się rejestruje lub uzupełnia profil, wtyczka automatycznie sprawdza czy pasuje do aktywnych głosowań z trybem dołączania "Otwarte" i dodaje go do odpowiedniej grupy.', 'openvote' ); ?></li>
</ul>

<div class="openvote-manual__tip">
    <?php esc_html_e( 'Jeśli użytkownik ma "(brak)" jako miasto i zostanie ręcznie przypisany do dokładnie jednej grupy, wtyczka automatycznie zaktualizuje jego pole "Miasto" nazwą tej grupy.', 'openvote' ); ?>
</div>
</div>


<!-- 4. Koordynatorzy -->
<div class="openvote-manual__section" id="manual-coordinators">
<h2>4. <?php esc_html_e( 'Koordynatorzy', 'openvote' ); ?></h2>
<p><?php esc_html_e( 'Koordynator to użytkownik przypisany do jednej lub więcej grup. Ma dostęp do panelu admina i może zarządzać głosowaniami dla swoich grup.', 'openvote' ); ?></p>

<h3><?php esc_html_e( 'Zarządzanie koordynatorami', 'openvote' ); ?></h3>
<ul>
    <li><strong><?php esc_html_e( 'Dodanie', 'openvote' ); ?></strong> — <?php esc_html_e( 'wybierz użytkownika z lewej listy, grupę (grupy) z prawej listy, kliknij "Dodaj".', 'openvote' ); ?></li>
    <li><strong><?php esc_html_e( 'Odłączenie od wszystkich grup', 'openvote' ); ?></strong> — <?php esc_html_e( 'kliknij "Odłącz wszystko" przy danym koordynatorze na liście.', 'openvote' ); ?></li>
    <li><strong><?php esc_html_e( 'Odłączenie od konkretnej grupy', 'openvote' ); ?></strong> — <?php esc_html_e( 'kliknij nazwę grupy wyświetloną przy koordynatorze (jest podkreślona jako link).', 'openvote' ); ?></li>
</ul>

<div class="openvote-manual__note">
    <?php esc_html_e( 'Jeden użytkownik może być koordynatorem wielu grup jednocześnie — nie znika z lewej listy po pierwszym przypisaniu.', 'openvote' ); ?>
</div>
</div>


<!-- 5. Głosowania -->
<div class="openvote-manual__section" id="manual-polls">
<h2>5. <?php esc_html_e( 'Głosowania — tworzenie i zarządzanie', 'openvote' ); ?></h2>

<h3><?php esc_html_e( 'Tworzenie nowego głosowania', 'openvote' ); ?></h3>
<ol>
    <li><?php esc_html_e( 'Przejdź do Open Vote → Głosowania → Dodaj nowe.', 'openvote' ); ?></li>
    <li><?php esc_html_e( 'Wypełnij formularz (opis pól poniżej).', 'openvote' ); ?></li>
    <li><?php esc_html_e( 'Kliknij "Zapisz jako szkic" lub "Wystartuj głosowanie".', 'openvote' ); ?></li>
</ol>

<h3><?php esc_html_e( 'Opis pól formularza głosowania', 'openvote' ); ?></h3>
<table class="openvote-manual__table">
    <thead><tr>
        <th><?php esc_html_e( 'Pole', 'openvote' ); ?></th>
        <th><?php esc_html_e( 'Opis', 'openvote' ); ?></th>
    </tr></thead>
    <tbody>
    <tr>
        <td><strong><?php esc_html_e( 'Tytuł', 'openvote' ); ?></strong></td>
        <td><?php esc_html_e( 'Nazwa głosowania widoczna dla uczestników. Wymagane, maks. 512 znaków.', 'openvote' ); ?></td>
    </tr>
    <tr>
        <td><strong><?php esc_html_e( 'Opis', 'openvote' ); ?></strong></td>
        <td><?php esc_html_e( 'Dodatkowe informacje o celu głosowania. Widoczny na karcie głosowania.', 'openvote' ); ?></td>
    </tr>
    <tr>
        <td><strong><?php esc_html_e( 'Data i godzina rozpoczęcia', 'openvote' ); ?></strong></td>
        <td><?php esc_html_e( 'Automatycznie ustawiana na moment kliknięcia "Wystartuj głosowanie". Nie wymaga ręcznego ustawiania.', 'openvote' ); ?></td>
    </tr>
    <tr>
        <td><strong><?php esc_html_e( 'Czas trwania', 'openvote' ); ?></strong></td>
        <td><?php esc_html_e( 'Wybierz z listy: 1 godz., 6 godz., 1 dzień, 2 dni itd. Data zakończenia jest obliczana automatycznie.', 'openvote' ); ?></td>
    </tr>
    <tr>
        <td><strong><?php esc_html_e( 'Tryb dołączania', 'openvote' ); ?></strong></td>
        <td><?php esc_html_e( '"Otwarte" — nowi użytkownicy, którzy zarejestrują się w trakcie głosowania, mogą automatycznie dołączyć. "Zamknięte" — tylko osoby z listy snapshot w momencie startu.', 'openvote' ); ?></td>
    </tr>
    <tr>
        <td><strong><?php esc_html_e( 'Tryb głosowania', 'openvote' ); ?></strong></td>
        <td><?php esc_html_e( '"Jawne" — w wynikach widać (częściowo zanonimizowane) dane głosujących. "Anonimowe" — w wynikach widać tylko liczby, zero danych osobowych. Uwaga: nie można zmienić po starcie.', 'openvote' ); ?></td>
    </tr>
    <tr>
        <td><strong><?php esc_html_e( 'Grupy docelowe', 'openvote' ); ?></strong></td>
        <td><?php esc_html_e( 'Wybierz jedną lub więcej grup, których członkowie będą uprawnieni do głosowania. Wymagane.', 'openvote' ); ?></td>
    </tr>
    <tr>
        <td><strong><?php esc_html_e( 'Powiadomienie e-mail', 'openvote' ); ?></strong></td>
        <td><?php esc_html_e( 'Zaznaczone domyślnie. Po starcie głosowania system wysyła zaproszenia do wszystkich uprawnionych uczestników.', 'openvote' ); ?></td>
    </tr>
    <tr>
        <td><strong><?php esc_html_e( 'Pytania i odpowiedzi', 'openvote' ); ?></strong></td>
        <td><?php esc_html_e( 'Dodawaj pytania przyciskiem "+ Dodaj pytanie". Każde pytanie ma min. 2 odpowiedzi (plus automatyczna "Wstrzymuję się" na końcu, której nie można usunąć).', 'openvote' ); ?></td>
    </tr>
    </tbody>
</table>

<h3><?php esc_html_e( 'Statusy głosowania', 'openvote' ); ?></h3>
<table class="openvote-manual__table">
    <thead><tr>
        <th><?php esc_html_e( 'Status', 'openvote' ); ?></th>
        <th><?php esc_html_e( 'Znaczenie', 'openvote' ); ?></th>
    </tr></thead>
    <tbody>
    <tr>
        <td><strong><?php esc_html_e( 'Szkic', 'openvote' ); ?></strong></td>
        <td><?php esc_html_e( 'Głosowanie zapisane, ale jeszcze nie rozpoczęte. Nie widoczne dla uczestników.', 'openvote' ); ?></td>
    </tr>
    <tr>
        <td><strong><?php esc_html_e( 'Rozpoczęte', 'openvote' ); ?></strong></td>
        <td><?php esc_html_e( 'Aktywne. Uczestnicy mogą oddawać głosy. Zaproszenia e-mail zostały wysłane.', 'openvote' ); ?></td>
    </tr>
    <tr>
        <td><strong><?php esc_html_e( 'Zakończone', 'openvote' ); ?></strong></td>
        <td><?php esc_html_e( 'Czas minął lub admin ręcznie zakończył. Widoczne w zakładce "Zakończone głosowania" z pełnymi wynikami.', 'openvote' ); ?></td>
    </tr>
    </tbody>
</table>

<div class="openvote-manual__warn">
    <strong><?php esc_html_e( 'Walidacja przed startem', 'openvote' ); ?></strong><br>
    <?php esc_html_e( 'Przycisk "Wystartuj głosowanie" jest nieaktywny jeśli: brak tytułu, brak opisu, brak grupy docelowej, brak pytań, puste odpowiedzi lub czas trwania przekracza 1 miesiąc.', 'openvote' ); ?>
</div>
</div>


<!-- 6. Widok uczestnika -->
<div class="openvote-manual__section" id="manual-vote">
<h2>6. <?php esc_html_e( 'Jak odbywa się głosowanie (widok uczestnika)', 'openvote' ); ?></h2>
<p><?php esc_html_e( 'Uczestnik wchodzi na stronę głosowania (domyślnie /glosowanie lub skonfigurowany adres). Strona ma dwie zakładki:', 'openvote' ); ?></p>
<ul>
    <li><strong><?php esc_html_e( 'Trwające głosowania', 'openvote' ); ?></strong> — <?php esc_html_e( 'aktywne głosowania, w których użytkownik może wziąć udział.', 'openvote' ); ?></li>
    <li><strong><?php esc_html_e( 'Zakończone głosowania', 'openvote' ); ?></strong> — <?php esc_html_e( 'lista zakończonych głosowań z wynikami, w których użytkownik był uprawniony.', 'openvote' ); ?></li>
</ul>

<h3><?php esc_html_e( 'Proces oddania głosu', 'openvote' ); ?></h3>
<ol>
    <li><?php esc_html_e( 'Zaloguj się do WordPress.', 'openvote' ); ?></li>
    <li><?php esc_html_e( 'Wejdź na stronę głosowania i wybierz zakładkę "Trwające głosowania".', 'openvote' ); ?></li>
    <li><?php esc_html_e( 'Odpowiedz na wszystkie pytania (wybierz jedną odpowiedź na pytanie).', 'openvote' ); ?></li>
    <li><?php esc_html_e( 'Wybierz sposób oddania głosu: "Jawnie" (Twoje dane pojawią się w wynikach, częściowo zanonimizowane) lub "Anonimowo" (widoczna tylko Twoja nazwa użytkownika).', 'openvote' ); ?></li>
    <li><?php esc_html_e( 'Kliknij "Oddaj głos". Przycisk jest nieaktywny dopóki nie wypełnisz wszystkich pól.', 'openvote' ); ?></li>
    <li><?php esc_html_e( 'Po oddaniu głosu formularz zostaje zablokowany i pojawia się potwierdzenie.', 'openvote' ); ?></li>
</ol>

<h3><?php esc_html_e( 'Warunki uprawnienia do głosowania', 'openvote' ); ?></h3>
<p><?php esc_html_e( 'Użytkownik może głosować tylko jeśli spełnia wszystkie poniższe warunki:', 'openvote' ); ?></p>
<ol>
    <li><?php esc_html_e( 'Głosowanie ma status "Rozpoczęte".', 'openvote' ); ?></li>
    <li><?php esc_html_e( 'Obecna data mieści się między datą startu a datą zakończenia.', 'openvote' ); ?></li>
    <li><?php esc_html_e( 'Użytkownik jest zalogowany.', 'openvote' ); ?></li>
    <li><?php esc_html_e( 'Profil jest kompletny — wypełnione wszystkie pola oznaczone jako "Wymagane" w Konfiguracji (Imię, Nazwisko, Nickname, E-mail — zawsze; plus dowolne inne zaznaczone przez admina).', 'openvote' ); ?></li>
    <li><?php esc_html_e( 'Użytkownik należy do grupy docelowej głosowania.', 'openvote' ); ?></li>
    <li><?php esc_html_e( 'Użytkownik jeszcze nie głosował.', 'openvote' ); ?></li>
    <li><?php esc_html_e( 'Jeśli tryb dołączania to "Zamknięte" — użytkownik musi być na liście snapshot z momentu startu.', 'openvote' ); ?></li>
</ol>
</div>


<!-- 7. Wyniki -->
<div class="openvote-manual__section" id="manual-results">
<h2>7. <?php esc_html_e( 'Wyniki głosowania', 'openvote' ); ?></h2>

<h3><?php esc_html_e( 'Widok uczestnika (zakładka Zakończone)', 'openvote' ); ?></h3>
<p><?php esc_html_e( 'Każde zakończone głosowanie wyświetla:', 'openvote' ); ?></p>
<ul>
    <li><?php esc_html_e( 'Tytuł i opis głosowania.', 'openvote' ); ?></li>
    <li><?php esc_html_e( 'Pasek frekwencji (ilu uprawnionych wzięło udział).', 'openvote' ); ?></li>
    <li><?php esc_html_e( 'Dla każdego pytania: odpowiedzi z procentami i paskami wyników.', 'openvote' ); ?></li>
    <li><?php esc_html_e( 'Zielony pasek = wynik wiodący, szary = pozostałe, żółty = wstrzymanie się.', 'openvote' ); ?></li>
    <li><?php esc_html_e( 'Dla administratorów: przycisk "Pobierz wyniki (PDF)".', 'openvote' ); ?></li>
</ul>

<h3><?php esc_html_e( 'Widok admina', 'openvote' ); ?></h3>
<p><?php esc_html_e( 'W panelu admina (Open Vote → kliknij głosowanie → Wyniki) dostępne są:', 'openvote' ); ?></p>
<ul>
    <li><?php esc_html_e( 'Pełne wyniki z listą głosujących (jawnie lub anonimowo, zależnie od trybu).', 'openvote' ); ?></li>
    <li><?php esc_html_e( 'Lista nieobecnych (uprawnionych, którzy nie zagłosowali).', 'openvote' ); ?></li>
    <li><?php esc_html_e( 'Pobieranie raportu PDF zawierającego wyniki i obie listy.', 'openvote' ); ?></li>
</ul>

<div class="openvote-manual__note">
    <strong><?php esc_html_e( 'Tryb anonimowy', 'openvote' ); ?></strong><br>
    <?php esc_html_e( 'Gdy głosowanie odbyło się w trybie anonimowym, lista głosujących jest ukryta dla wszystkich — łącznie z administratorem. Widoczne są wyłącznie zbiorcze liczby i procenty.', 'openvote' ); ?>
</div>

<h3><?php esc_html_e( 'Jak obliczane są procenty', 'openvote' ); ?></h3>
<p><?php esc_html_e( 'Procenty są obliczane względem liczby głosów faktycznie oddanych, a nie względem wszystkich uprawnionych. Osoby nieobecne nie są wliczane do "wstrzymujących się".', 'openvote' ); ?></p>
</div>


<!-- 8. Zaproszenia -->
<div class="openvote-manual__section" id="manual-invitations">
<h2>8. <?php esc_html_e( 'Zaproszenia e-mail', 'openvote' ); ?></h2>
<p><?php esc_html_e( 'Po uruchomieniu głosowania system automatycznie tworzy kolejkę wiadomości e-mail dla wszystkich uprawnionych uczestników.', 'openvote' ); ?></p>

<h3><?php esc_html_e( 'Ekran Zaproszenia', 'openvote' ); ?></h3>
<p><?php esc_html_e( 'Dostępny w liście głosowań pod linkiem "Zaproszenia" przy każdym głosowaniu. Pokazuje:', 'openvote' ); ?></p>
<ul>
    <li><?php esc_html_e( 'Ile e-maili zostało wysłanych, ile oczekuje, ile nie udało się wysłać.', 'openvote' ); ?></li>
    <li><?php esc_html_e( 'Pasek postępu wysyłki.', 'openvote' ); ?></li>
    <li><?php esc_html_e( 'Przycisk "Wyślij zaproszenia" / "Wyślij ponownie" (dla niezwysłanych).', 'openvote' ); ?></li>
    <li><?php esc_html_e( 'Gdy wszyscy zostali powiadomieni, przycisk zmienia się na szary "Wszyscy powiadomieni".', 'openvote' ); ?></li>
</ul>

<div class="openvote-manual__tip">
    <?php esc_html_e( '"Wyślij ponownie" wysyła TYLKO do osób, które jeszcze nie otrzymały zaproszenia (status "oczekuje" lub "błąd"). Nie tworzy duplikatów.', 'openvote' ); ?>
</div>

<h3><?php esc_html_e( 'Konfiguracja e-mail', 'openvote' ); ?></h3>
<p><?php esc_html_e( 'W Konfiguracji można ustawić:', 'openvote' ); ?></p>
<ul>
    <li><?php esc_html_e( 'Adres nadawcy (E-mail nadawcy).', 'openvote' ); ?></li>
    <li><?php esc_html_e( 'Metodę wysyłki: domyślna WordPress (wp_mail) lub zewnętrzny serwer SMTP.', 'openvote' ); ?></li>
    <li><?php esc_html_e( 'Dla SMTP: host, port, szyfrowanie, nazwa użytkownika i hasło.', 'openvote' ); ?></li>
    <li><?php esc_html_e( 'Opcjonalnie: wysyłka przez SendGrid API (klucz API w Konfiguracji).', 'openvote' ); ?></li>
</ul>
</div>


<!-- 9. Konfiguracja -->
<div class="openvote-manual__section" id="manual-config">
<h2>9. <?php esc_html_e( 'Konfiguracja wtyczki', 'openvote' ); ?></h2>
<p><?php esc_html_e( 'Dostępna w menu Open Vote → Konfiguracja (tylko Administrator WordPress).', 'openvote' ); ?></p>

<table class="openvote-manual__table">
    <thead><tr>
        <th><?php esc_html_e( 'Opcja', 'openvote' ); ?></th>
        <th><?php esc_html_e( 'Opis', 'openvote' ); ?></th>
    </tr></thead>
    <tbody>
    <tr>
        <td><strong><?php esc_html_e( 'Slug strony głosowania', 'openvote' ); ?></strong></td>
        <td><?php esc_html_e( 'Adres URL strony głosowania, np. "glosowanie". Po zmianie odśwież permalinki (Ustawienia → Bezpośrednie odnośniki → Zapisz).', 'openvote' ); ?></td>
    </tr>
    <tr>
        <td><strong><?php esc_html_e( 'Dodaj podstronę', 'openvote' ); ?></strong></td>
        <td><?php esc_html_e( 'Tworzy stronę WordPress o podanym slug z blokiem głosowania. Pojawia się tylko gdy strona jeszcze nie istnieje.', 'openvote' ); ?></td>
    </tr>
    <tr>
        <td><strong><?php esc_html_e( 'Zaktualizuj stronę głosowania', 'openvote' ); ?></strong></td>
        <td><?php esc_html_e( 'Pojawia się gdy strona istnieje, ale nie zawiera aktualnego bloku zakładek. Kliknij, aby zastąpić treść nowym blokiem.', 'openvote' ); ?></td>
    </tr>
    <tr>
        <td><strong><?php esc_html_e( 'URL strony ankiet', 'openvote' ); ?></strong></td>
        <td><?php esc_html_e( 'Slug strony z ankietami (np. "ankieta"). Strona powinna zawierać blok "Ankiety (Open Vote)". Przyciski "Utwórz stronę ankiet" i "Zaktualizuj stronę ankiet (dodaj blok)" tworzą lub uzupełniają stronę.', 'openvote' ); ?></td>
    </tr>
    <tr>
        <td><strong><?php esc_html_e( 'URL strony zgłoszeń', 'openvote' ); ?></strong></td>
        <td><?php esc_html_e( 'Slug strony ze zgłoszeniami ankiet oznaczonymi jako „Nie spam" (np. "zgloszenia"). Strona zawiera blok "Zgłoszenia (Open Vote)". Na stronie publicznej wyświetlane są tylko imię i nazwisko oraz odpowiedzi na pytania — wartości pól uznanych za wrażliwe (E-mail, Miasto, Telefon, PESEL itd.) są ukrywane. Przyciski "Utwórz stronę zgłoszeń" i "Zaktualizuj stronę zgłoszeń (dodaj blok)" ułatwiają konfigurację.', 'openvote' ); ?></td>
    </tr>
    <tr>
        <td><strong><?php esc_html_e( 'E-mail nadawcy', 'openvote' ); ?></strong></td>
        <td><?php esc_html_e( 'Adres, z którego wysyłane są zaproszenia. Domyślnie noreply@twojadomena.pl.', 'openvote' ); ?></td>
    </tr>
    <tr>
        <td><strong><?php esc_html_e( 'Metoda wysyłki e-mail', 'openvote' ); ?></strong></td>
        <td><?php esc_html_e( '"WordPress domyślny" używa wbudowanej funkcji wp_mail(). "Zewnętrzny SMTP" — wpisz dane serwera pocztowego. "SendGrid API" — wpisz klucz API z konta SendGrid.', 'openvote' ); ?></td>
    </tr>
    <tr>
        <td><strong><?php esc_html_e( 'Odinstaluj', 'openvote' ); ?></strong></td>
        <td><?php esc_html_e( 'Na dole strony Konfiguracji. Usuwa wszystkie tabele i opcje wtyczki z bazy danych. Nieodwracalne — używać ostrożnie.', 'openvote' ); ?></td>
    </tr>
    </tbody>
</table>
</div>


<!-- 10. Mapowanie pól -->
<div class="openvote-manual__section" id="manual-fieldmap">
<h2>10. <?php esc_html_e( 'Mapowanie pól użytkownika', 'openvote' ); ?></h2>
<p><?php esc_html_e( 'Sekcja w Konfiguracji służy do powiązania logicznych nazw pól wtyczki z kluczami meta, pod jakimi Twoja wtyczka rejestracji zapisuje dane użytkownika.', 'openvote' ); ?></p>

<h3><?php esc_html_e( 'Pola logiczne', 'openvote' ); ?></h3>
<table class="openvote-manual__table">
    <thead><tr>
        <th><?php esc_html_e( 'Pole logiczne', 'openvote' ); ?></th>
        <th><?php esc_html_e( 'Domyślny klucz', 'openvote' ); ?></th>
        <th><?php esc_html_e( 'Opis', 'openvote' ); ?></th>
    </tr></thead>
    <tbody>
    <tr><td><?php esc_html_e( 'Imię', 'openvote' ); ?></td><td><code>first_name</code></td><td><?php esc_html_e( 'Imię użytkownika (wbudowane WP). Zawsze wymagane.', 'openvote' ); ?></td></tr>
    <tr><td><?php esc_html_e( 'Nazwisko', 'openvote' ); ?></td><td><code>last_name</code></td><td><?php esc_html_e( 'Nazwisko użytkownika (wbudowane WP). Zawsze wymagane.', 'openvote' ); ?></td></tr>
    <tr><td><?php esc_html_e( 'Nickname', 'openvote' ); ?></td><td><code>nickname</code></td><td><?php esc_html_e( 'Pseudonim używany w anonimowych wynikach. Zawsze wymagane.', 'openvote' ); ?></td></tr>
    <tr><td><?php esc_html_e( 'E-mail', 'openvote' ); ?></td><td><code>user_email</code></td><td><?php esc_html_e( 'Adres e-mail (wbudowane WP). Zawsze wymagane.', 'openvote' ); ?></td></tr>
    <tr><td><?php esc_html_e( 'Miasto / miejsce spotkania', 'openvote' ); ?></td><td><code>user_registration_miejsce_spotkania</code></td><td><?php esc_html_e( 'Pole używane do przypisywania do grup. Zmień na klucz używany przez Twoją wtyczkę rejestracji. Można wyłączyć (wszyscy w grupie Wszyscy).', 'openvote' ); ?></td></tr>
    <tr><td><?php esc_html_e( 'Numer telefonu', 'openvote' ); ?></td><td><code>user_gsm</code></td><td><?php esc_html_e( 'Opcjonalne. Ustaw na klucz z Twojej wtyczki lub zostaw "nie określone".', 'openvote' ); ?></td></tr>
    <tr><td><?php esc_html_e( 'Numer PESEL', 'openvote' ); ?></td><td><code>user_pesel</code></td><td><?php esc_html_e( 'Opcjonalne. Można zaznaczyć jako wymagane do głosowania.', 'openvote' ); ?></td></tr>
    <tr><td><?php esc_html_e( 'Numer dowodu osobistego', 'openvote' ); ?></td><td><code>user_id</code></td><td><?php esc_html_e( 'Opcjonalne. Można zaznaczyć jako wymagane do głosowania.', 'openvote' ); ?></td></tr>
    <tr><td><?php esc_html_e( 'Ulica i numer domu', 'openvote' ); ?></td><td><code>user_address</code></td><td><?php esc_html_e( 'Opcjonalne.', 'openvote' ); ?></td></tr>
    <tr><td><?php esc_html_e( 'Kod pocztowy', 'openvote' ); ?></td><td><code>user_zip</code></td><td><?php esc_html_e( 'Opcjonalne.', 'openvote' ); ?></td></tr>
    <tr><td><?php esc_html_e( 'Miejscowość', 'openvote' ); ?></td><td><code>user_city</code></td><td><?php esc_html_e( 'Opcjonalne. Oddzielne od "Miasta / miejsca spotkania" używanego do grup.', 'openvote' ); ?></td></tr>
    </tbody>
</table>

<h3><?php esc_html_e( 'Kolumna "Wymagane do głosowania"', 'openvote' ); ?></h3>
<p><?php esc_html_e( 'Checkbox przy każdym polu oznacza, że użytkownik MUSI mieć wypełnione to pole, aby móc oddać głos. Pola "zawsze" (Imię, Nazwisko, Nickname, E-mail) nie mogą zostać odznaczone.', 'openvote' ); ?></p>

<h3><?php esc_html_e( 'Dane wrażliwe na stronie zgłoszeń', 'openvote' ); ?></h3>
<p><?php esc_html_e( 'Na publicznej stronie zgłoszeń (/zgloszenia lub inny skonfigurowany slug) wtyczka nie wyświetla wartości pól uznanych za wrażliwe. Są to pola z listy mapowania: E-mail, Nazwa miasta / miejsce spotkania, Numer telefonu, Numer PESEL, Numer dowodu osobistego, Ulica i numer domu, Kod pocztowy, Miejscowość. Aby dana odpowiedź w ankiecie była na stronie zgłoszeń ukryta (pokazywane jest „—"), przy tworzeniu lub edycji ankiety należy przypisać do tego pytania odpowiednie „Pole profilu" w sekcji pól ankiety.', 'openvote' ); ?></p>

<div class="openvote-manual__tip">
    <?php esc_html_e( 'Przykład: jeśli zaznaczysz PESEL jako wymagany, ale klucz pozostanie "nie określone", żaden użytkownik nie będzie mógł głosować (pole zawsze puste). Najpierw ustaw poprawny klucz meta, dopiero potem zaznacz jako wymagane.', 'openvote' ); ?>
</div>
</div>


<!-- 11. Ankiety i strona zgłoszeń -->
<div class="openvote-manual__section" id="manual-surveys">
<h2>11. <?php esc_html_e( 'Ankiety i strona zgłoszeń', 'openvote' ); ?></h2>
<p><?php esc_html_e( 'Wtyczka umożliwia tworzenie ankiet (formularzy z pytaniami), zbieranie odpowiedzi użytkowników oraz publiczne wyświetlanie zatwierdzonych zgłoszeń na dedykowanej stronie.', 'openvote' ); ?></p>

<h3><?php esc_html_e( 'Gdzie znajdziesz ankiety', 'openvote' ); ?></h3>
<ul>
    <li><?php esc_html_e( 'Lista ankiet: Open Vote → Ankiety (lub z menu listy głosowań — opcja "Ankiety").', 'openvote' ); ?></li>
    <li><?php esc_html_e( 'Tworzenie i edycja: tytuł, opis, czas trwania, pola ankiety (etykieta, typ pola, opcjonalnie powiązanie z polem profilu).', 'openvote' ); ?></li>
    <li><?php esc_html_e( 'Dla każdej ankiety: lista zgłoszeń ze statusem (oczekuje / nie spam / spam), przycisk "To nie spam" do zatwierdzania zgłoszeń.', 'openvote' ); ?></li>
</ul>

<h3><?php esc_html_e( 'Pole profilu przy pytaniach ankiety', 'openvote' ); ?></h3>
<p><?php esc_html_e( 'Przy każdym polu ankiety (pytaniu) można ustawić opcjonalne „Pole profilu": wybór z listy (Imię, Nazwisko, E-mail, Miasto, Numer telefonu, PESEL, Ulica, Kod pocztowy, Miejscowość itd.). To powiązanie służy stronie publicznej zgłoszeń:', 'openvote' ); ?></p>
<ul>
    <li><?php esc_html_e( 'Jeśli pole profilu jest puste (— brak), odpowiedź użytkownika na to pytanie jest na stronie /zgloszenia wyświetlana w całości.', 'openvote' ); ?></li>
    <li><?php esc_html_e( 'Jeśli wybierzesz pole uznane za wrażliwe (E-mail, Miasto, Telefon, PESEL, Dowód, Ulica, Kod pocztowy, Miejscowość), wartość tej odpowiedzi na stronie zgłoszeń zostanie zastąpiona znakiem „—", aby nie ujawniać danych osobowych.', 'openvote' ); ?></li>
</ul>
<p><?php esc_html_e( 'W nagłówku każdej karty zgłoszenia na stronie publicznej wyświetlane są wyłącznie imię i nazwisko; pozostałe dane z profilu (e-mail, telefon, data) nie są tam pokazywane.', 'openvote' ); ?></p>

<h3><?php esc_html_e( 'Strona zgłoszeń (/zgloszenia)', 'openvote' ); ?></h3>
<p><?php esc_html_e( 'Adres strony (slug) ustawiasz w Konfiguracji w sekcji „URL strony zgłoszeń". Strona powinna zawierać blok Gutenberg „Zgłoszenia (Open Vote)" (openvote/survey-responses). Możesz utworzyć ją ręcznie w edytorze lub użyć przycisków w Konfiguracji: „Utwórz stronę zgłoszeń" lub „Zaktualizuj stronę zgłoszeń (dodaj blok)".', 'openvote' ); ?></p>
<p><?php esc_html_e( 'Na stronie wyświetlane są tylko zgłoszenia ze statusem „Nie spam". Dla każdego zgłoszenia widać: tytuł ankiety, imię i nazwisko oraz listę pytań z odpowiedziami. Odpowiedzi powiązane z wrażliwymi polami profilu są ukrywane („—").', 'openvote' ); ?></p>
<p><?php esc_html_e( 'Dostęp do listy zgłoszeń ma każdy, kto może wyświetlić stronę. Aby ograniczyć dostęp (np. tylko dla zalogowanych lub wybranej roli), umieść blok na stronie chronionej hasłem lub ustaw widoczność strony na „Prywatna".', 'openvote' ); ?></p>

<div class="openvote-manual__note">
    <strong><?php esc_html_e( 'Strona ankiet', 'openvote' ); ?></strong><br>
    <?php esc_html_e( 'Osobna opcja w Konfiguracji to „URL strony ankiet" — strona z blokiem „Ankiety (Open Vote)", na której użytkownicy wypełniają ankiety. Slug możesz ustawić np. na "ankieta".', 'openvote' ); ?>
</div>
</div>


<!-- 12. FAQ -->
<div class="openvote-manual__section" id="manual-faq">
<h2>12. <?php esc_html_e( 'Najczęstsze pytania (FAQ)', 'openvote' ); ?></h2>

<h4><?php esc_html_e( 'Użytkownik widzi "Twój profil jest niekompletny" — co robić?', 'openvote' ); ?></h4>
<p><?php esc_html_e( 'Sprawdź, które pole jest brakujące (komunikat podaje nazwę pola). Poproś użytkownika o uzupełnienie profilu lub sprawdź w Konfiguracji → Mapowanie pól, czy klucz jest poprawny.', 'openvote' ); ?></p>

<h4><?php esc_html_e( 'Głosowanie nie pojawia się w zakładce "Trwające" pomimo że jest aktywne.', 'openvote' ); ?></h4>
<p><?php esc_html_e( 'Sprawdź: (1) Czy użytkownik należy do grupy docelowej głosowania? (2) Czy profil użytkownika jest kompletny? (3) Czy data zakończenia nie minęła?', 'openvote' ); ?></p>

<h4><?php esc_html_e( 'Zaproszenia e-mail nie dochodzą.', 'openvote' ); ?></h4>
<p><?php esc_html_e( 'Sprawdź: (1) Ekran Zaproszenia — czy status to "wysłano" czy "błąd"? (2) Konfiguracja → ustawienia e-mail — czy skonfigurowany jest SMTP lub SendGrid? (3) Sprawdź folder SPAM u odbiorcy.', 'openvote' ); ?></p>

<h4><?php esc_html_e( 'Strona głosowania pokazuje błąd lub przekierowuje w pętli.', 'openvote' ); ?></h4>
<p><?php esc_html_e( 'Przejdź do Konfiguracji i kliknij "Zaktualizuj stronę głosowania" jeśli pojawia się ostrzeżenie. Następnie odśwież permalinki w Ustawienia → Bezpośrednie odnośniki.', 'openvote' ); ?></p>

<h4><?php esc_html_e( 'Jak zmienić nazwę witryny w nagłówku i PDF?', 'openvote' ); ?></h4>
<p><?php esc_html_e( 'Wtyczka używa Tytułu witryny z WordPress (Ustawienia → Ogólne → Tytuł witryny). Zmień tam, a zaktualizuje się wszędzie automatycznie.', 'openvote' ); ?></p>

<h4><?php esc_html_e( 'Jak zmienić logo wyświetlane w panelu admina?', 'openvote' ); ?></h4>
<p><?php esc_html_e( 'Wtyczka używa ikony witryny WordPress (Wygląd → Dostosuj → Tożsamość witryny → Ikona witryny). Ustaw ikonę tam.', 'openvote' ); ?></p>

<h4><?php esc_html_e( 'Co to jest tryb "Nie używaj miast"?', 'openvote' ); ?></h4>
<p><?php esc_html_e( 'Gdy wybierzesz tę opcję w mapowaniu pola Miasto, wszystkich użytkowników traktuje jako należących do jednej grupy "Wszyscy". Przydatne gdy nie chcesz grupować uczestników według lokalizacji.', 'openvote' ); ?></p>
</div>

<p style="margin-top:3em;color:#999;font-size:12px;">
    <?php printf(
        esc_html__( 'Wersja dokumentacji: %s', 'openvote' ),
        esc_html( OPENVOTE_VERSION )
    ); ?>
</p>

</div>
