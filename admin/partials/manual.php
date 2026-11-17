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
        <li><a href="#manual-getting-started"><?php esc_html_e( 'Jak zacząć pracę z wtyczką', 'openvote' ); ?></a></li>
        <li><a href="#manual-roles"><?php esc_html_e( 'Role użytkowników i uprawnienia', 'openvote' ); ?></a> — <a href="#manual-roles-legend"><?php esc_html_e( 'Legenda i tabela uprawnień', 'openvote' ); ?></a></li>
        <li><a href="#manual-groups"><?php esc_html_e( 'Grupy użytkowników', 'openvote' ); ?></a></li>
        <li><a href="#manual-coordinators"><?php esc_html_e( 'Koordynatorzy', 'openvote' ); ?></a></li>
        <li><a href="#manual-polls"><?php esc_html_e( 'Głosowania — tworzenie i zarządzanie', 'openvote' ); ?></a></li>
        <li><a href="#manual-vote"><?php esc_html_e( 'Jak odbywa się głosowanie (widok uczestnika)', 'openvote' ); ?></a></li>
        <li><a href="#manual-results"><?php esc_html_e( 'Wyniki głosowania', 'openvote' ); ?></a></li>
        <li><a href="#manual-invitations"><?php esc_html_e( 'Zaproszenia e-mail', 'openvote' ); ?></a></li>
        <li><a href="#manual-communication"><?php esc_html_e( 'Wysyłka — masowa wysyłka wiadomości', 'openvote' ); ?></a></li>
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

<!-- 2. Jak zacząć -->
<div class="openvote-manual__section" id="manual-getting-started">
<h2>2. <?php esc_html_e( 'Jak zacząć pracę z wtyczką', 'openvote' ); ?></h2>
<p><?php esc_html_e( 'Ten rozdział prowadzi Administratora WordPress od instalacji do stanu „system gotowy dla uczestników”. Wykonuj kroki po kolei: instalacja → konfiguracja wstępna → przygotowanie tego, co zobaczą uczestnicy → próba generalna → start. Przy każdym kroku znajdziesz: co zrobić (gdzie kliknąć), dlaczego to ważne (co się stanie, jeśli to pominiesz) i co wybrać.', 'openvote' ); ?></p>
<div class="openvote-manual__note">
    <?php esc_html_e( 'Większość ustawień znajduje się w menu OpenVote → Konfiguracja (w menu panelu widnieje skrót nazwy systemu, domyślnie „OpenVote”). Ekran Konfiguracji ma zakładki: Ogólne, Pola użytkownika, Role i dostęp, Wysyłka e-mail, Szablon zaproszenia, Auto-synchronizacja, Nieaktywni, Cron, Narzędzia. Po zmianach w zakładce kliknij „Zapisz konfigurację”.', 'openvote' ); ?>
</div>

<h3><?php esc_html_e( 'Etap A — instalacja', 'openvote' ); ?></h3>

<h4><?php esc_html_e( 'Krok 1. Sprawdź wymagania serwera', 'openvote' ); ?></h4>
<p><strong><?php esc_html_e( 'Co zrobić:', 'openvote' ); ?></strong> <?php esc_html_e( 'W panelu hostingu sprawdź (i w razie potrzeby zmień) wersję PHP dla domeny. Wymagane: PHP 8.1 lub nowszy, WordPress 6.4 lub nowszy, rozszerzenia PHP json, mbstring oraz mysqli lub pdo_mysql.', 'openvote' ); ?></p>
<p><strong><?php esc_html_e( 'Dlaczego:', 'openvote' ); ?></strong> <?php esc_html_e( 'Na starszym PHP wtyczka się nie uruchomi. Wersja PHP ma też znaczenie dla crona serwera (krok 8) — tam trzeba wskazać PHP 8.1 lub nowsze.', 'openvote' ); ?></p>
<p><strong><?php esc_html_e( 'Co wybrać:', 'openvote' ); ?></strong> <?php esc_html_e( 'Najnowszą wersję PHP oferowaną przez hosting (np. 8.4), o ile inne wtyczki na stronie z nią działają.', 'openvote' ); ?></p>

<h4><?php esc_html_e( 'Krok 2. Zainstaluj i włącz wtyczkę (oraz jak ją aktualizować)', 'openvote' ); ?></h4>
<p><strong><?php esc_html_e( 'Co zrobić:', 'openvote' ); ?></strong></p>
<ol>
    <li><?php esc_html_e( 'W panelu WordPress przejdź do Wtyczki → Dodaj nową → Wyślij wtyczkę na serwer.', 'openvote' ); ?></li>
    <li><?php esc_html_e( 'Wskaż plik openvote-install.zip i kliknij „Zainstaluj”, a potem „Włącz wtyczkę”.', 'openvote' ); ?></li>
    <li><?php esc_html_e( 'Jeśli pojawią się setki ostrzeżeń „chmod(): Operation not permitted” — to ograniczenie hostingu. Wtedy rozpakuj plik zip przez FTP do katalogu wp-content/plugins/ (powstanie katalog openvote) i włącz wtyczkę w menu Wtyczki.', 'openvote' ); ?></li>
</ol>
<p><strong><?php esc_html_e( 'Dlaczego:', 'openvote' ); ?></strong> <?php esc_html_e( 'Przy włączeniu wtyczka tworzy swoje tabele w bazie danych i grupę „Test” do próbnych głosowań. Bez włączenia menu wtyczki się nie pojawi.', 'openvote' ); ?></p>
<p><strong><?php esc_html_e( 'Aktualizacja:', 'openvote' ); ?></strong> <?php esc_html_e( 'Wgraj nowszy plik zip tą samą drogą (Wtyczki → Dodaj nową → Wyślij wtyczkę na serwer) i potwierdź zastąpienie bieżącej wersji wgraną. Baza danych zostanie zaktualizowana automatycznie przy pierwszym wejściu do panelu. Ustawienia, głosowania i grupy pozostają.', 'openvote' ); ?></p>
<div class="openvote-manual__warn">
    <?php esc_html_e( 'Nie usuwaj wtyczki przed aktualizacją. Usunięcie wtyczki w menu Wtyczki kasuje wszystkie jej tabele i ustawienia (głosowania, wyniki, grupy, ankiety) — tego nie da się cofnąć. Przy aktualizacji przez FTP po prostu nadpisz pliki w katalogu wtyczki.', 'openvote' ); ?>
</div>

<h3><?php esc_html_e( 'Etap B — konfiguracja wstępna', 'openvote' ); ?></h3>

<h4><?php esc_html_e( 'Krok 3. Nazwa, logo i czas (zakładka Ogólne)', 'openvote' ); ?></h4>
<p><strong><?php esc_html_e( 'Co zrobić:', 'openvote' ); ?></strong></p>
<ol>
    <li><?php esc_html_e( 'Konfiguracja → zakładka „Ogólne” → pole „Skrót nazwy”: wpisz krótką nazwę organizacji (do 12 znaków). Pojawi się w menu panelu; w e-mailach możesz ją wstawić polem {brand_short}.', 'openvote' ); ?></li>
    <li><?php esc_html_e( 'Pełna nazwa i logo pochodzą z WordPress: Ustawienia → Ogólne → „Tytuł witryny” oraz Wygląd → Dostosuj → Tożsamość witryny → „Ikona witryny”.', 'openvote' ); ?></li>
    <li><?php esc_html_e( 'Sprawdź strefę czasową w Ustawienia → Ogólne. Następnie w Konfiguracji → „Ogólne” → „Ustawienie czasu (strefa głosowań)” porównaj „Czas dla głosowań” z zegarkiem. Jeśli się różni, ustaw „Przesunięcie czasu”.', 'openvote' ); ?></li>
</ol>
<p><strong><?php esc_html_e( 'Dlaczego:', 'openvote' ); ?></strong> <?php esc_html_e( 'Uczestnicy rozpoznają nadawcę po nazwie. Zły czas oznacza, że głosowania kończą się o innej godzinie niż podano w zaproszeniu.', 'openvote' ); ?></p>
<p><strong><?php esc_html_e( 'Co wybrać:', 'openvote' ); ?></strong> <?php esc_html_e( 'Przesunięcie 0, jeśli „Czas dla głosowań” zgadza się z czasem lokalnym. Zmieniaj je tylko wtedy, gdy serwer pokazuje inną godzinę.', 'openvote' ); ?></p>

<h4><?php esc_html_e( 'Krok 4. Mapowanie pól użytkownika (zakładka Pola użytkownika)', 'openvote' ); ?></h4>
<p><strong><?php esc_html_e( 'Co zrobić:', 'openvote' ); ?></strong></p>
<ol>
    <li><?php esc_html_e( 'Konfiguracja → zakładka „Pola użytkownika”. Dla każdego pola logicznego (Imię, Nazwisko, Nickname, E-mail, Grupa, Numer telefonu, Dane własne) wybierz klucz w kolumnie „Klucz w bazie danych WordPress”.', 'openvote' ); ?></li>
    <li><?php esc_html_e( 'Imię, Nazwisko, Nickname i E-mail zostaw na domyślnych polach wbudowanych WordPress, chyba że Twoja wtyczka rejestracji zapisuje je inaczej.', 'openvote' ); ?></li>
    <li><?php esc_html_e( 'Pole „Grupa” (miasto/oddział): wskaż klucz, pod którym Twój formularz rejestracji zapisuje miasto — albo wybierz „Nie używaj miast”.', 'openvote' ); ?></li>
    <li><?php esc_html_e( 'W kolumnach „Wymagane do głosowania” i „Wymagane do ankiety” zaznacz, które pola uczestnik musi mieć wypełnione. Imię, Nazwisko, Nickname i E-mail są wymagane zawsze. Pole „Grupa” jest domyślnie wymagane do głosowania, gdy jest zmapowane — możesz je odznaczyć.', 'openvote' ); ?></li>
    <li><?php esc_html_e( 'Zapisz, a potem kliknij u góry ekranu Konfiguracji „Sprawdź konfigurację”. Jeśli pole Grupa jest źle ustawione, zobaczysz propozycję naprawy.', 'openvote' ); ?></li>
</ol>
<p><strong><?php esc_html_e( 'Dlaczego najpierw:', 'openvote' ); ?></strong> <?php esc_html_e( 'Od mapowania zależy, kto w ogóle może głosować. Uczestnik, któremu brakuje wymaganego pola, zobaczy komunikat „Twój profil jest niekompletny” i nie odda głosu. Pole Grupa decyduje o grupach-miastach (krok 10) i o grupie Wszyscy (grupa systemowa).', 'openvote' ); ?></p>
<p><strong><?php esc_html_e( 'Dlaczego pole Grupa jest domyślnie wymagane:', 'openvote' ); ?></strong> <?php esc_html_e( 'Wypełnione pole Grupa (miasto, sejmik, dzielnica, partia…) odróżnia członków stowarzyszenia od kont technicznych obsługi strony (administratorzy, konta serwisowe, testowe). Konta bez Grupy nie głosują i nie należą do grupy Wszyscy (grupa systemowa).', 'openvote' ); ?></p>
<p><strong><?php esc_html_e( 'Co wybrać:', 'openvote' ); ?></strong></p>
<ul>
    <li><?php esc_html_e( 'Organizacja z oddziałami i formularz rejestracji pyta o miasto → zmapuj pole Grupa na ten klucz.', 'openvote' ); ?></li>
    <li><?php esc_html_e( 'Jedna wspólna grupa, bez podziału na miasta → „Nie używaj miast” (wszyscy z rolą, poza administratorami, trafiają do grupy systemowej Wszyscy).', 'openvote' ); ?></li>
    <li><?php esc_html_e( 'Telefon i Dane własne → zostaw „nie określone”, chyba że naprawdę ich potrzebujesz. Nigdy nie zaznaczaj jako wymaganego pola z kluczem „nie określone” — nikt nie będzie mógł głosować.', 'openvote' ); ?></li>
</ul>
<p><?php esc_html_e( 'Szczegóły: rozdział „Mapowanie pól użytkownika”.', 'openvote' ); ?> <a href="#manual-fieldmap"><?php esc_html_e( 'Przejdź do rozdziału', 'openvote' ); ?></a></p>

<h4><?php esc_html_e( 'Krok 5. Role i dostęp', 'openvote' ); ?></h4>
<p><strong><?php esc_html_e( 'Co zrobić:', 'openvote' ); ?></strong> <?php esc_html_e( 'Konfiguracja → zakładka „Role i dostęp”. Przejrzyj tabelę „Mapowanie roli” (które role widzą które ekrany), ustaw „Zakres dostępu koordynatora”, zdecyduj o opcji „Pozwól głosować bez logowania” i zostaw zaznaczone „Utwórz grupę do testowania : Test”.', 'openvote' ); ?></p>
<p><strong><?php esc_html_e( 'Dlaczego:', 'openvote' ); ?></strong> <?php esc_html_e( 'Te ustawienia decydują, co widzą koordynatorzy i czy uczestnik musi znać hasło do konta. Grupa Test jest potrzebna do próby generalnej (krok 13).', 'openvote' ); ?></p>
<p><strong><?php esc_html_e( 'Co wybrać:', 'openvote' ); ?></strong></p>
<ul>
    <li><?php esc_html_e( 'Mapowanie roli — zostaw domyślne, jeśli nie masz szczególnych potrzeb.', 'openvote' ); ?></li>
    <li><?php esc_html_e( 'Zakres dostępu koordynatora — domyślnie „Do wszystkich grup (Miast)”. Wybierz „Do tylko własnych grup (Miast)”, gdy masz wielu koordynatorów lokalnych, którzy mają widzieć tylko swoje oddziały. Wtedy grupę systemową Wszyscy może wybrać Administrator albo koordynator, któremu ją przypiszesz (ekran Role), a głosowanie bez grup docelowych może zapisać tylko Administrator.', 'openvote' ); ?></li>
    <li><?php esc_html_e( 'Głosowanie bez logowania — włącz, jeśli wielu uczestników nie pamięta haseł. Uczestnik nadal musi mieć konto: podaje jego adres e-mail i wpisuje 4-cyfrowy kod wysłany na ten adres. Opis: rozdział „Jak odbywa się głosowanie”.', 'openvote' ); ?> <a href="#manual-vote"><?php esc_html_e( 'Przejdź do rozdziału', 'openvote' ); ?></a></li>
</ul>

<h4><?php esc_html_e( 'Krok 6. Wysyłka e-mail i e-mail testowy (zakładka Wysyłka e-mail)', 'openvote' ); ?></h4>
<p><strong><?php esc_html_e( 'Co zrobić:', 'openvote' ); ?></strong></p>
<ol>
    <li><?php esc_html_e( 'Konfiguracja → zakładka „Wysyłka e-mail” → pole „E-mail nadawcy”: wpisz adres w domenie Twojej strony (np. noreply@twojadomena.pl).', 'openvote' ); ?></li>
    <li><?php esc_html_e( 'W polu „Metoda wysyłki” wybierz sposób wysyłki i uzupełnij jego dane (host i hasło SMTP albo klucz API dostawcy).', 'openvote' ); ?></li>
    <li><?php esc_html_e( 'W tabeli „Warunki wysyłki e-maili” ustaw limity zgodne z planem u dostawcy (na 15 minut, na godzinę, na dobę; 0 = brak limitu).', 'openvote' ); ?></li>
    <li><?php esc_html_e( 'Kliknij „Zapisz konfigurację”, następnie w polu „Wyślij test e-mail” wpisz swój adres i wyślij wiadomość testową. Sprawdź skrzynkę odbiorczą i folder SPAM.', 'openvote' ); ?></li>
</ol>
<p><strong><?php esc_html_e( 'Dlaczego:', 'openvote' ); ?></strong> <?php esc_html_e( 'Zaproszenia do głosowań, kody logowania bez hasła i masowe wiadomości idą tą drogą. Bez działającej wysyłki uczestnicy nie dowiedzą się o głosowaniu, a przekroczenie limitu dostawcy może zablokować konto pocztowe.', 'openvote' ); ?></p>
<p><strong><?php esc_html_e( 'Co wybrać:', 'openvote' ); ?></strong></p>
<table class="openvote-manual__table">
    <thead><tr>
        <th><?php esc_html_e( 'Metoda', 'openvote' ); ?></th>
        <th><?php esc_html_e( 'Kiedy wybrać', 'openvote' ); ?></th>
    </tr></thead>
    <tbody>
    <tr>
        <td><strong><?php esc_html_e( 'WordPress (PHP-mail)', 'openvote' ); ?></strong></td>
        <td><?php esc_html_e( 'Tylko na start i dla małych list — metoda jest niedostępna, gdy w systemie jest ponad 250 adresów e-mail. Wiadomości częściej trafiają do SPAM.', 'openvote' ); ?></td>
    </tr>
    <tr>
        <td><strong><?php esc_html_e( 'SMTP zewnętrzny', 'openvote' ); ?></strong></td>
        <td><?php esc_html_e( 'Gdy masz skrzynkę pocztową u hostingu lub w firmie. Zalecane szyfrowanie TLS / STARTTLS na porcie 587. Ustaw limity zgodne z limitami tej skrzynki.', 'openvote' ); ?></td>
    </tr>
    <tr>
        <td><strong><?php esc_html_e( 'BREVO (free)', 'openvote' ); ?></strong></td>
        <td><?php esc_html_e( 'Darmowa usługa do 300 e-maili na dobę. Przy większej liście wysyłka rozłoży się na kolejne dni (wznowienie wymaga crona — krok 8).', 'openvote' ); ?></td>
    </tr>
    <tr>
        <td><strong><?php esc_html_e( 'BREVO (płatne), SendGrid API, Freshmail API, GetResponse API', 'openvote' ); ?></strong></td>
        <td><?php esc_html_e( 'Dla dużych list i najlepszej dostarczalności. Limity czasowe wtyczki nie są dla nich egzekwowane — obowiązują limity Twojego planu. GetResponse wymaga dodatku do e-maili transakcyjnych i pola „From Field ID”.', 'openvote' ); ?></td>
    </tr>
    </tbody>
</table>
<p><?php esc_html_e( 'Zajrzyj też do zakładki „Nieaktywni”: osoby uznane tam za nieaktywne nie dostają żadnych powiadomień e-mail (zaproszeń do głosowań ani wiadomości masowych), ale nadal mogą głosować. Po oddaniu głosu od razu wracają do aktywnych.', 'openvote' ); ?></p>

<h4><?php esc_html_e( 'Krok 7. Treść zaproszenia (zakładka Szablon zaproszenia)', 'openvote' ); ?></h4>
<p><strong><?php esc_html_e( 'Co zrobić:', 'openvote' ); ?></strong> <?php esc_html_e( 'Konfiguracja → zakładka „Szablon zaproszenia”. Ustaw „Temat”, „Od (nazwa nadawcy)” oraz „Treść e-maila (czysty tekst)”. Możesz używać pól wbudowanych z tabeli nad formularzem, np. {poll_title}, {vote_url}, {date_end}, {questions}, {brand_short}. Kliknij „Zobacz podgląd”, a na koniec „Zapisz konfigurację”.', 'openvote' ); ?></p>
<p><strong><?php esc_html_e( 'Dlaczego:', 'openvote' ); ?></strong> <?php esc_html_e( 'To pierwsza wiadomość, którą zobaczy uczestnik. Nazwa nadawcy i jasna treść zmniejszają ryzyko, że zaproszenie zostanie zignorowane lub uznane za SPAM.', 'openvote' ); ?></p>
<p><strong><?php esc_html_e( 'Co wybrać:', 'openvote' ); ?></strong> <?php esc_html_e( 'Zacznij od treści domyślnej i zmień tylko ton oraz podpis. Zawsze zostaw w treści {vote_url} (link do strony głosowania) i {date_end} (termin). Jeśli treść się zepsuje, użyj przycisku „Przywróć domyślną”.', 'openvote' ); ?></p>

<h4><?php esc_html_e( 'Krok 8. Cron serwera — konieczny', 'openvote' ); ?></h4>
<p><strong><?php esc_html_e( 'Co zrobić:', 'openvote' ); ?></strong></p>
<ol>
    <li><?php esc_html_e( 'Konfiguracja → zakładka „Cron” → skopiuj gotowe polecenie przyciskiem „Kopiuj”.', 'openvote' ); ?></li>
    <li><?php esc_html_e( 'W panelu hostingu (cPanel) otwórz „Zadania Cron” (Cron Jobs), w „Ustawieniach zwykłych” wybierz „Co 15 minut”, wklej polecenie w pole „Polecenie” i zapisz zadanie.', 'openvote' ); ?></li>
    <li><?php esc_html_e( 'Polecenie zaczyna się od /usr/local/bin/php84. Nie zamieniaj go na samo „php” ani „php8” — na wielu serwerach to stare PHP 7.4 lub 8.0, na którym wtyczka nie działa. Jeśli hosting nie ma php84, wskaż inną binarkę PHP 8.1 lub nowszą (ścieżki podpowie plik cron/cron-log.php w katalogu wtyczki, dostępny przez FTP).', 'openvote' ); ?></li>
    <li><?php esc_html_e( 'Po około 15 minutach odśwież zakładkę „Cron”: stan powinien zmienić się na zielony „Działa”. Przycisk „Uruchom teraz” pozwala sprawdzić działanie od ręki.', 'openvote' ); ?></li>
</ol>
<p><strong><?php esc_html_e( 'Dlaczego (co robi cron):', 'openvote' ); ?></strong></p>
<ul>
    <li><?php esc_html_e( 'Automatycznie wysyła zaproszenia po starcie głosowania (najpóźniej do 15 minut).', 'openvote' ); ?></li>
    <li><?php esc_html_e( 'Wznawia wysyłki wstrzymane przez limity oraz dokańcza wysyłki porzucone po zamknięciu karty przeglądarki.', 'openvote' ); ?></li>
    <li><?php esc_html_e( 'Zamyka głosowania i ankiety po terminie oraz nalicza opuszczone głosowania osobom, które nie zagłosowały (to podstawa statusu „nieaktywny”).', 'openvote' ); ?></li>
    <li><?php esc_html_e( 'Synchronizuje grupy systemowe i wykonuje zaległe zadania harmonogramu (np. synchronizację grup-miast).', 'openvote' ); ?></li>
</ul>
<p><?php esc_html_e( 'Bez crona trzeba ręcznie wysyłać zaproszenia z ekranu Zaproszenia i pilnować kart przeglądarki do końca wysyłki, a głosowania nie zmienią same statusu na „Zakończone”.', 'openvote' ); ?></p>
<p><strong><?php esc_html_e( 'Jak sprawdzać, że działa:', 'openvote' ); ?></strong> <?php esc_html_e( 'zielony znacznik „Działa” w zakładce Cron oznacza sygnał w ciągu ostatnich 20 minut. Jeśli cron milczy dłużej niż 20 minut, Administrator zobaczy w panelu ostrzeżenie z linkiem do Konfiguracji → Cron.', 'openvote' ); ?></p>
<p><?php esc_html_e( 'Więcej: podrozdział „Automatyczne zaproszenia i cron serwera”.', 'openvote' ); ?> <a href="#manual-cron"><?php esc_html_e( 'Przejdź do podrozdziału', 'openvote' ); ?></a></p>

<h3><?php esc_html_e( 'Etap C — przygotowanie tego, co zobaczą uczestnicy', 'openvote' ); ?></h3>

<h4><?php esc_html_e( 'Krok 9. Konta, rejestracja i logowanie uczestników', 'openvote' ); ?></h4>
<p><strong><?php esc_html_e( 'Co zrobić:', 'openvote' ); ?></strong></p>
<ol>
    <li><?php esc_html_e( 'Zdecyduj, jak uczestnicy założą konta: samodzielnie (w WordPress: Ustawienia → Ogólne → Członkostwo „Każdy może się zarejestrować”, domyślna rola „Subskrybent”) albo przez wtyczkę rejestracji z własnymi polami (np. User Registration & Membership), jeśli potrzebujesz pola miasto lub telefon.', 'openvote' ); ?></li>
    <li><?php esc_html_e( 'Upewnij się, że formularz rejestracji lub profilu zbiera wszystkie pola oznaczone w kroku 4 jako „Wymagane do głosowania” i zapisuje je pod tymi samymi kluczami.', 'openvote' ); ?></li>
    <li><?php esc_html_e( 'Przekaż uczestnikom, że muszą założyć konto tym adresem e-mail, na który mają dostawać zaproszenia.', 'openvote' ); ?></li>
</ol>
<p><strong><?php esc_html_e( 'Dlaczego:', 'openvote' ); ?></strong> <?php esc_html_e( 'Każdy głos jest przypisany do konta — tak wtyczka sprawdza uprawnienia i blokuje podwójne głosowanie. Wtyczka nie tworzy kont: import CSV tylko przypisuje do grup osoby, które już mają konto. Kto nie ma konta lub ma niekompletny profil, nie zagłosuje.', 'openvote' ); ?></p>
<p><strong><?php esc_html_e( 'Co wybrać:', 'openvote' ); ?></strong> <?php esc_html_e( 'Jeśli wystarczą imię, nazwisko i e-mail — wbudowana rejestracja WordPress. Jeśli dzielisz uczestników na miasta — wtyczka rejestracji z polem miasta. Dla osób, które nie pamiętają haseł — dodatkowo głosowanie bez logowania (krok 5).', 'openvote' ); ?></p>

<h4><?php esc_html_e( 'Krok 10. Grupy uczestników', 'openvote' ); ?></h4>
<p><strong><?php esc_html_e( 'Co zrobić:', 'openvote' ); ?></strong> <?php esc_html_e( 'Przejdź do OpenVote → Grupy. Ekran ma zakładki: Lista grup, Dodaj do grup, Import CSV, Dodaj grupę, Synchronizacja, Dziennik zmian. Przygotuj grupy, do których będziesz kierować głosowania:', 'openvote' ); ?></p>
<ul>
    <li><strong><?php esc_html_e( 'Grupy-miasta', 'openvote' ); ?></strong> — <?php esc_html_e( 'jeśli w kroku 4 zmapowano pole Grupa: uruchom synchronizację grup-miast (zakładka „Synchronizacja”), a w Konfiguracji → „Auto-synchronizacja” wybierz harmonogram.', 'openvote' ); ?></li>
    <li><strong><?php esc_html_e( 'Grupy ręczne', 'openvote' ); ?></strong> — <?php esc_html_e( 'np. zarząd lub komisja: zakładka „Dodaj grupę”, potem „Dodaj do grup” (wyszukiwanie po e-mailu).', 'openvote' ); ?></li>
    <li><strong><?php esc_html_e( 'Import CSV', 'openvote' ); ?></strong> — <?php esc_html_e( 'lista adresów e-mail przypisywana do wybranej grupy. Nie tworzy kont; adresy bez konta trafią do listy odrzuconych.', 'openvote' ); ?></li>
    <li><strong><?php esc_html_e( 'Grupa systemowa Wszyscy', 'openvote' ); ?></strong> — <?php esc_html_e( 'tworzona i utrzymywana automatycznie: członkowie z wypełnionym polem Grupa, bez administratorów. Nic nie trzeba robić.', 'openvote' ); ?> <a href="#manual-system-groups"><?php esc_html_e( 'Zobacz: Grupa systemowa Wszyscy', 'openvote' ); ?></a></li>
</ul>
<p><strong><?php esc_html_e( 'Dlaczego:', 'openvote' ); ?></strong> <?php esc_html_e( 'Głosować może tylko członek grupy docelowej głosowania. Bez grup nie da się wystartować głosowania.', 'openvote' ); ?></p>
<p><strong><?php esc_html_e( 'Co wybrać:', 'openvote' ); ?></strong> <?php esc_html_e( 'Głosowanie całej organizacji → Wszyscy (grupa systemowa). Głosowanie oddziałów → grupy-miasta. Wąskie gremium → grupa ręczna lub import CSV. Nie zostawiaj pustych grup docelowych: głosowanie bez grup jest dostępne dla każdego konta (także kont technicznych) i przy ograniczeniu koordynatorów może je zapisać tylko Administrator. Harmonogram auto-synchronizacji: raz w tygodniu lub raz w miesiącu; „codziennie” nie jest zalecane przy dużej liczbie użytkowników.', 'openvote' ); ?></p>
<p><?php esc_html_e( 'Szczegóły: rozdział „Grupy użytkowników”.', 'openvote' ); ?> <a href="#manual-groups"><?php esc_html_e( 'Przejdź do rozdziału', 'openvote' ); ?></a></p>

<h4><?php esc_html_e( 'Krok 11. Koordynatorzy (opcjonalnie)', 'openvote' ); ?></h4>
<p><strong><?php esc_html_e( 'Co zrobić:', 'openvote' ); ?></strong> <?php esc_html_e( 'OpenVote → Koordynatorzy: w lewej kolumnie wybierz użytkownika, w prawej grupę (lub kilka) i kliknij „Dodaj”.', 'openvote' ); ?></p>
<p><strong><?php esc_html_e( 'Dlaczego:', 'openvote' ); ?></strong> <?php esc_html_e( 'Koordynator może tworzyć i prowadzić głosowania oraz ankiety bez dostępu do Konfiguracji. Jeśli wszystko prowadzisz sam, ten krok możesz pominąć.', 'openvote' ); ?></p>
<p><strong><?php esc_html_e( 'Co wybrać:', 'openvote' ); ?></strong> <?php esc_html_e( 'Przypisuj koordynatorów do grup, za które odpowiadają. Czy mają widzieć tylko swoje grupy — decyduje ustawienie z kroku 5.', 'openvote' ); ?> <a href="#manual-roles-legend"><?php esc_html_e( 'Zobacz: tabela uprawnień', 'openvote' ); ?></a></p>

<h4><?php esc_html_e( 'Krok 12. Strony dla uczestników: głosowania, ankiety, zgłoszenia', 'openvote' ); ?></h4>
<p><strong><?php esc_html_e( 'Co zrobić:', 'openvote' ); ?></strong></p>
<ol>
    <li><?php esc_html_e( 'Konfiguracja → zakładka „Ogólne” → sekcja „URL strony głosowania”: ustaw „Slug strony” (domyślnie „glosuj”) i kliknij „Utwórz stronę głosowania”. Powstanie strona z blokiem zakładek Trwające / Zakończone głosowania.', 'openvote' ); ?></li>
    <li><?php esc_html_e( 'Jeśli korzystasz z ankiet: sekcja „URL strony ankiet” → „Utwórz stronę ankiet” (strona z blokiem „Ankiety (Open Vote)”).', 'openvote' ); ?></li>
    <li><?php esc_html_e( 'Jeśli chcesz publicznie pokazywać zatwierdzone zgłoszenia z ankiet: sekcja „URL strony zgłoszeń” → „Utwórz stronę zgłoszeń”. W przeciwnym razie pomiń.', 'openvote' ); ?></li>
    <li><?php esc_html_e( 'Otwórz adres strony głosowania. Jeśli widzisz „Not Found”, wejdź w Ustawienia → Bezpośrednie odnośniki, wybierz strukturę inną niż „Zwykły” i kliknij „Zapisz zmiany”.', 'openvote' ); ?></li>
    <li><?php esc_html_e( 'Dodaj link do strony głosowania w menu witryny (Wygląd → Menu lub edytor witryny), aby uczestnicy łatwo ją znaleźli.', 'openvote' ); ?></li>
</ol>
<p><strong><?php esc_html_e( 'Dlaczego:', 'openvote' ); ?></strong> <?php esc_html_e( 'Uczestnicy głosują i wypełniają ankiety na tych stronach, a link {vote_url} w zaproszeniu prowadzi właśnie na stronę głosowania. Bez niej zaproszenie prowadzi donikąd.', 'openvote' ); ?></p>
<p><strong><?php esc_html_e( 'Co wybrać:', 'openvote' ); ?></strong> <?php esc_html_e( 'Krótkie slugi bez polskich znaków (np. „glosuj”, „ankieta”). Strona zgłoszeń jest dostępna dla każdego, kto ją otworzy — jeśli zgłoszenia nie są przeznaczone dla wszystkich, ustaw stronę jako prywatną lub chronioną hasłem.', 'openvote' ); ?></p>

<h3><?php esc_html_e( 'Etap D — próba generalna i start', 'openvote' ); ?></h3>

<h4><?php esc_html_e( 'Krok 13. Głosowanie testowe w grupie Test', 'openvote' ); ?></h4>
<p><strong><?php esc_html_e( 'Co zrobić:', 'openvote' ); ?></strong></p>
<ol>
    <li><?php esc_html_e( 'OpenVote → Grupy → zakładka „Dodaj do grup”: dodaj do grupy „Test” siebie i jedną–dwie zaufane osoby (najlepiej ze zwykłym kontem Subskrybenta).', 'openvote' ); ?></li>
    <li><?php esc_html_e( 'OpenVote → Głosowania → „Dodaj nowe”: wpisz tytuł, opis i jedno pytanie, jako grupę docelową wybierz „Test”, czas trwania 1h, zostaw zaznaczone automatyczne wysłanie zaproszeń i kliknij „Wystartuj głosowanie”.', 'openvote' ); ?></li>
    <li><?php esc_html_e( 'W ciągu 15 minut zaproszenie powinno przyjść (cron). Sprawdź temat, nadawcę, treść i czy link otwiera stronę głosowania. Postęp zobaczysz na ekranie Zaproszenia (link przy głosowaniu na liście).', 'openvote' ); ?></li>
    <li><?php esc_html_e( 'Zagłosuj z konta testowego. Jeśli włączono głosowanie bez logowania — sprawdź też logowanie kodem z e-maila w oknie prywatnym przeglądarki.', 'openvote' ); ?></li>
    <li><?php esc_html_e( 'Po godzinie sprawdź, czy głosowanie samo zmieniło status na „Zakończone” (to potwierdza działanie crona), a następnie obejrzyj wyniki i pobierz raport PDF.', 'openvote' ); ?></li>
</ol>
<p><strong><?php esc_html_e( 'Dlaczego:', 'openvote' ); ?></strong> <?php esc_html_e( 'Próba generalna w małej grupie wyłapuje błędy wysyłki, mapowania pól, strony i crona, zanim zobaczą je wszyscy uczestnicy. Błędu w prawdziwym głosowaniu nie da się już cofnąć (np. trybu jawne/anonimowe nie zmienisz po starcie).', 'openvote' ); ?></p>
<p><?php esc_html_e( 'Opis formularza głosowania i wyników: rozdziały „Głosowania — tworzenie i zarządzanie” oraz „Wyniki głosowania”.', 'openvote' ); ?> <a href="#manual-polls"><?php esc_html_e( 'Głosowania', 'openvote' ); ?></a> · <a href="#manual-results"><?php esc_html_e( 'Wyniki', 'openvote' ); ?></a></p>

<h4><?php esc_html_e( 'Krok 14. Lista kontrolna „System gotowy dla klientów”', 'openvote' ); ?></h4>
<p><?php esc_html_e( 'Zanim uruchomisz pierwsze prawdziwe głosowanie lub ankietę, upewnij się, że:', 'openvote' ); ?></p>
<ul>
    <li><?php esc_html_e( 'Serwer ma PHP 8.1 lub nowsze, wtyczka jest włączona w najnowszej wersji.', 'openvote' ); ?></li>
    <li><?php esc_html_e( 'Skrót nazwy, tytuł witryny i ikona witryny są ustawione; „Czas dla głosowań” zgadza się z czasem lokalnym.', 'openvote' ); ?></li>
    <li><?php esc_html_e( 'Mapowanie pól jest zapisane, „Sprawdź konfigurację” nie pokazuje błędów, a żadne wymagane pole nie ma klucza „nie określone”.', 'openvote' ); ?></li>
    <li><?php esc_html_e( 'Uczestnicy mają konta (lub wiedzą, jak je założyć) i uzupełnione wymagane pola profilu.', 'openvote' ); ?></li>
    <li><?php esc_html_e( 'Grupy docelowe istnieją i mają właściwych członków; koordynatorzy są przypisani do swoich grup.', 'openvote' ); ?></li>
    <li><?php esc_html_e( 'E-mail testowy dotarł do skrzynki odbiorczej (nie do SPAM), limity wysyłki odpowiadają planowi dostawcy, progi w zakładce „Nieaktywni” są świadomie ustawione.', 'openvote' ); ?></li>
    <li><?php esc_html_e( 'Szablon zaproszenia ma właściwy temat, nazwę nadawcy i link {vote_url}.', 'openvote' ); ?></li>
    <li><?php esc_html_e( 'Zakładka Cron pokazuje zielone „Działa”.', 'openvote' ); ?></li>
    <li><?php esc_html_e( 'Strona głosowania (i w razie potrzeby strona ankiet) otwiera się bez błędu i jest podlinkowana w menu witryny.', 'openvote' ); ?></li>
    <li><?php esc_html_e( 'Głosowanie testowe w grupie Test przeszło całą drogę: zaproszenie, głos, automatyczne zakończenie, wyniki i PDF.', 'openvote' ); ?></li>
</ul>
<div class="openvote-manual__tip">
    <?php esc_html_e( 'Dalsza codzienna praca — tworzenie głosowań, zaproszenia, masowa wysyłka, wyniki i ankiety — jest opisana w kolejnych rozdziałach tego podręcznika.', 'openvote' ); ?>
    <a href="#manual-polls"><?php esc_html_e( 'Głosowania', 'openvote' ); ?></a> ·
    <a href="#manual-invitations"><?php esc_html_e( 'Zaproszenia e-mail', 'openvote' ); ?></a> ·
    <a href="#manual-communication"><?php esc_html_e( 'Wysyłka', 'openvote' ); ?></a> ·
    <a href="#manual-surveys"><?php esc_html_e( 'Ankiety', 'openvote' ); ?></a>
</div>
</div>



<!-- 3. Role -->
<div class="openvote-manual__section" id="manual-roles">
<h2>3. <?php esc_html_e( 'Role użytkowników i uprawnienia', 'openvote' ); ?></h2>
<p><?php esc_html_e( 'Wtyczka rozróżnia role WordPress i własne role wtyczki.', 'openvote' ); ?></p>

<h3 id="manual-roles-legend"><?php esc_html_e( 'Legenda: nazewnictwo i uprawnienia', 'openvote' ); ?></h3>
<p><?php esc_html_e( 'W dokumentach i na ekranie rola wtyczki zapisana w systemie jako poll_admin nazywana jest „Koordynator”.', 'openvote' ); ?></p>

<table class="openvote-manual__table">
    <caption class="screen-reader-text"><?php esc_html_e( 'Oznaczenia ról', 'openvote' ); ?></caption>
    <thead><tr>
        <th><?php esc_html_e( 'Oznaczenie', 'openvote' ); ?></th>
        <th><?php esc_html_e( 'Znaczenie', 'openvote' ); ?></th>
    </tr></thead>
    <tbody>
    <tr>
        <td><strong><?php esc_html_e( 'Administrator', 'openvote' ); ?></strong></td>
        <td><?php esc_html_e( 'Rola WordPress: administrator. Pełny dostęp do wszystkiego, w tym Konfiguracja i nadawanie roli Koordynatora.', 'openvote' ); ?></td>
    </tr>
    <tr>
        <td><strong><?php esc_html_e( 'Koordynator', 'openvote' ); ?></strong></td>
        <td><?php esc_html_e( 'Rola wtyczki (poll_admin). Nadawana ręcznie przez Administratora lub innego Koordynatora. Dostęp do listy głosowań, ankiet, grup i koordynatorów; bez dostępu do Konfiguracji. Może dodawać i usuwać koordynatorów oraz grupy.', 'openvote' ); ?></td>
    </tr>
    <tr>
        <td><strong><?php esc_html_e( 'Author / Editor', 'openvote' ); ?></strong></td>
        <td><?php esc_html_e( 'Role WordPress. Nie mają automatycznie roli Koordynatora; aby mogli zarządzać głosowaniami, należy nadać im rolę Koordynatora.', 'openvote' ); ?></td>
    </tr>
    <tr>
        <td><strong><?php esc_html_e( 'Subskrybent', 'openvote' ); ?></strong></td>
        <td><?php esc_html_e( 'Domyślna rola nowego użytkownika. Nie ma uprawnień Koordynatora; może tylko oddawać głosy, jeśli jest uprawniony.', 'openvote' ); ?></td>
    </tr>
    </tbody>
</table>

<table class="openvote-manual__table">
    <caption><?php esc_html_e( 'Tabela uprawnień', 'openvote' ); ?></caption>
    <thead><tr>
        <th><?php esc_html_e( 'Uprawnienie', 'openvote' ); ?></th>
        <th><?php esc_html_e( 'Administrator', 'openvote' ); ?></th>
        <th><?php esc_html_e( 'Koordynator', 'openvote' ); ?></th>
        <th><?php esc_html_e( 'Pozostali (bez Koordynatora)', 'openvote' ); ?></th>
    </tr></thead>
    <tbody>
    <tr>
        <td><?php esc_html_e( 'Lista głosowań, Ankiety, Grupy, Koordynatorzy', 'openvote' ); ?></td>
        <td><?php esc_html_e( 'Tak', 'openvote' ); ?></td>
        <td><?php esc_html_e( 'Tak', 'openvote' ); ?></td>
        <td><?php esc_html_e( 'Nie', 'openvote' ); ?></td>
    </tr>
    <tr>
        <td><?php esc_html_e( 'Konfiguracja (mapowanie pól, e-mail itd.)', 'openvote' ); ?></td>
        <td><?php esc_html_e( 'Tak', 'openvote' ); ?></td>
        <td><?php esc_html_e( 'Nie', 'openvote' ); ?></td>
        <td><?php esc_html_e( 'Nie', 'openvote' ); ?></td>
    </tr>
    <tr>
        <td><?php esc_html_e( 'Nadawanie i usuwanie roli Koordynatora', 'openvote' ); ?></td>
        <td><?php esc_html_e( 'Tak', 'openvote' ); ?></td>
        <td><?php esc_html_e( 'Tak', 'openvote' ); ?></td>
        <td><?php esc_html_e( 'Nie', 'openvote' ); ?></td>
    </tr>
    <tr>
        <td><?php esc_html_e( 'Dodawanie i usuwanie grup', 'openvote' ); ?></td>
        <td><?php esc_html_e( 'Tak', 'openvote' ); ?></td>
        <td><?php esc_html_e( 'Tak', 'openvote' ); ?></td>
        <td><?php esc_html_e( 'Nie', 'openvote' ); ?></td>
    </tr>
    <tr>
        <td><?php esc_html_e( 'Start / Zakończ głosowanie, Zaproszenia', 'openvote' ); ?></td>
        <td><?php esc_html_e( 'Tak', 'openvote' ); ?></td>
        <td><?php esc_html_e( 'Tak', 'openvote' ); ?></td>
        <td><?php esc_html_e( 'Nie', 'openvote' ); ?></td>
    </tr>
    </tbody>
</table>

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
    <li><?php esc_html_e( 'Przejdź do menu OpenVote → Koordynatorzy.', 'openvote' ); ?></li>
    <li><?php esc_html_e( 'W lewej kolumnie znajdź użytkownika (wpisz imię lub nazwisko).', 'openvote' ); ?></li>
    <li><?php esc_html_e( 'W prawej kolumnie wybierz grupę (lub kilka grup).', 'openvote' ); ?></li>
    <li><?php esc_html_e( 'Kliknij "Dodaj". Użytkownik stanie się Koordynatorem wybranych grup.', 'openvote' ); ?></li>
</ol>
</div>


<!-- 4. Grupy -->
<div class="openvote-manual__section" id="manual-groups">
<h2>4. <?php esc_html_e( 'Grupy użytkowników', 'openvote' ); ?></h2>
<p><?php esc_html_e( 'Grupy służą do definiowania kto ma prawo głosować w danym głosowaniu. Użytkownik musi należeć do co najmniej jednej grupy docelowej głosowania, aby móc oddać głos.', 'openvote' ); ?></p>

<h3><?php esc_html_e( 'Tworzenie grupy', 'openvote' ); ?></h3>
<ol>
    <li><?php esc_html_e( 'Przejdź do menu OpenVote → Grupy.', 'openvote' ); ?></li>
    <li><?php esc_html_e( 'Kliknij "Dodaj grupę", wpisz nazwę (np. nazwę miasta lub oddziału).', 'openvote' ); ?></li>
    <li><?php esc_html_e( 'Zapisz. Grupa pojawi się na liście.', 'openvote' ); ?></li>
</ol>

<h3><?php esc_html_e( 'Dodawanie użytkowników do grupy', 'openvote' ); ?></h3>
<p><?php esc_html_e( 'Są cztery sposoby (ekran Grupy ma zakładki: Lista grup, Dodaj do grup, Import CSV, Dodaj grupę, Synchronizacja, Dziennik zmian):', 'openvote' ); ?></p>
<ul>
    <li><strong><?php esc_html_e( 'Ręcznie', 'openvote' ); ?></strong> — <?php esc_html_e( 'na ekranie Grupy, w zakładce „Dodaj do grup”, wyszukaj użytkownika po adresie e-mail, wybierz grupę (lub kilka) i kliknij „Dodaj do grup”.', 'openvote' ); ?></li>
    <li><strong><?php esc_html_e( 'Automatycznie (przycisk "Auto")', 'openvote' ); ?></strong> — <?php esc_html_e( 'system przeszukuje wszystkich użytkowników z wypełnionym polem "Miasto" i przypisuje ich do grupy o tej samej nazwie. Grupy, które nie istnieją, zostaną automatycznie utworzone.', 'openvote' ); ?></li>
    <li><strong><?php esc_html_e( 'Z pliku CSV', 'openvote' ); ?></strong> — <?php esc_html_e( 'zakładka „Import CSV” (opis poniżej).', 'openvote' ); ?></li>
    <li><strong><?php esc_html_e( 'Przez rejestrację', 'openvote' ); ?></strong> — <?php esc_html_e( 'gdy nowy użytkownik się rejestruje lub uzupełnia profil, wtyczka automatycznie sprawdza czy pasuje do aktywnych głosowań z trybem dołączania "Otwarte" i dodaje go do odpowiedniej grupy.', 'openvote' ); ?></li>
</ul>

<h3><?php esc_html_e( 'Import z pliku CSV', 'openvote' ); ?></h3>
<p><?php esc_html_e( 'Grupy → zakładka „Import CSV”: wybierz grupę, wskaż plik .csv lub .txt (maks. 2 MB) z adresami e-mail w kolumnie „email” (albo same adresy, jeden w wierszu; separator: przecinek, średnik lub tabulator; kodowanie Windows-1250 jest obsługiwane). Pozostałe kolumny pliku są ignorowane.', 'openvote' ); ?></p>
<p><strong><?php esc_html_e( 'Import nie tworzy nowych kont — tylko przypisuje do grupy osoby, które już mają konto.', 'openvote' ); ?></strong></p>
<p><?php esc_html_e( 'Dlaczego? Głosowania odbywają się na rzeczywistych kontach użytkowników tej strony: każdy głos jest powiązany z kontem, co pozwala sprawdzić uprawnienia i zapobiec podwójnemu głosowaniu. Plik CSV służy więc wyłącznie do przypisania istniejących kont do wybranej grupy — konto jest rozpoznawane po adresie e-mail.', 'openvote' ); ?></p>
<p><?php esc_html_e( 'Po imporcie zobaczysz raport: ile adresów dodano, ile już było w grupie, ile nie ma konta i ile jest niepoprawnych (z listą odrzuconych adresów).', 'openvote' ); ?></p>
<p><strong><?php esc_html_e( 'Skąd biorą się odrzucenia?', 'openvote' ); ?></strong> <?php esc_html_e( 'Adres z pliku, dla którego nie istnieje jeszcze konto, zostaje odrzucony. To nie jest błąd pliku ani systemu — ta osoba musi najpierw sama założyć konto (zarejestrować się na stronie) dokładnie tym samym adresem e-mail, który jest w pliku. Na liście odrzuconych może się też znaleźć adres z literówką — wtedy konto zwykle istnieje, ale pod innym adresem. Adresy w ogóle niepoprawne (np. bez „@”) są liczone osobno jako „niepoprawne”.', 'openvote' ); ?></p>
<p><?php esc_html_e( 'Co zrobić z odrzuconymi adresami:', 'openvote' ); ?></p>
<ol>
    <li><?php esc_html_e( 'Rozwiń listę „Odrzucone adresy” w raporcie i skopiuj ją.', 'openvote' ); ?></li>
    <li><?php esc_html_e( 'Poproś te osoby o założenie konta na stronie tym samym adresem e-mail.', 'openvote' ); ?></li>
    <li><?php esc_html_e( 'Gdy się zarejestrują, zaimportuj ten sam plik ponownie. Osoby już przypisane zostaną pominięte (raport pokaże je jako „już w grupie”), a nowe konta zostaną dodane do grupy.', 'openvote' ); ?></li>
</ol>

<h3 id="manual-system-groups"><?php esc_html_e( 'Grupa systemowa Wszyscy', 'openvote' ); ?></h3>
<p><?php esc_html_e( 'Oprócz grup tworzonych ręcznie i grup-miast system sam tworzy i utrzymuje jedną grupę systemową: Wszyscy. Na liście grup ma etykietę „Systemowa”. To grupa wszystkich członków stowarzyszenia — wygodny cel głosowań i wiadomości do całej organizacji.', 'openvote' ); ?></p>
<p><strong><?php esc_html_e( 'Kto należy do grupy Wszyscy?', 'openvote' ); ?></strong></p>
<ul>
    <li><?php esc_html_e( 'Osoba z wypełnionym polem „Grupa” (miasto, sejmik, dzielnica, partia — pole wybrane w Konfiguracji → Pola użytkownika).', 'openvote' ); ?></li>
    <li><?php esc_html_e( 'Która nie jest administratorem WordPress — administratorzy to obsługa strony, nie członkowie.', 'openvote' ); ?></li>
    <li><?php esc_html_e( 'I ma jakąkolwiek rolę na tej stronie (konto zablokowane przez odebranie roli wypada z grupy).', 'openvote' ); ?></li>
</ul>
<p><?php esc_html_e( 'Konta techniczne bez miasta i administratorzy są więc wyłączeni. W trybie „Nie używaj miast” warunek pola Grupa odpada — do grupy należą wszyscy z rolą poza administratorami.', 'openvote' ); ?></p>
<p><strong><?php esc_html_e( 'Dlaczego pole grupujące, a nie rola?', 'openvote' ); ?></strong> <?php esc_html_e( 'Członek stowarzyszenia może mieć w WordPress dowolną rolę — subskrybent, autor, edytor. Rola mówi, co ktoś może robić na stronie, a nie czy jest członkiem. O członkostwie świadczy wypełnione pole Grupa, dlatego to ono jest kryterium.', 'openvote' ); ?></p>
<p><strong><?php esc_html_e( 'Jak grupa jest aktualizowana?', 'openvote' ); ?></strong></p>
<ul>
    <li><?php esc_html_e( 'Na bieżąco — gdy ktoś się zarejestruje, uzupełni lub wyczyści pole Grupa, zmieni mu się rola (np. nadanie lub odebranie administratora) albo konto zostanie usunięte, członkostwo zmienia się od razu.', 'openvote' ); ?></li>
    <li><?php esc_html_e( 'Okresowo — przy każdym uruchomieniu crona serwera (co ok. 15 minut) system porównuje grupę z profilami i poprawia ewentualne różnice (np. gdy inna wtyczka zmieniła dane bezpośrednio w bazie). Po zmianie mapowania pola Grupa w Konfiguracji grupa jest uzgadniana od nowa.', 'openvote' ); ?></li>
    <li><?php esc_html_e( 'Ręcznie — na ekranie Grupy administrator może uruchomić synchronizację przyciskiem „Synchronizuj teraz”.', 'openvote' ); ?></li>
</ul>
<p><strong><?php esc_html_e( 'Czego nie można zrobić z grupą systemową?', 'openvote' ); ?></strong></p>
<p><?php esc_html_e( 'Członkostwo jest automatyczne, dlatego grupy systemowej Wszyscy nie można usunąć, nie można do niej ręcznie dodawać ani z niej usuwać osób, nie można też importować do niej pliku CSV. Aby ktoś znalazł się w grupie, uzupełnij mu pole Grupa w profilu. Nie można też utworzyć własnej grupy o nazwie „Wszyscy” ani o nazwie zaczynającej się od „system.”.', 'openvote' ); ?></p>
<p><strong><?php esc_html_e( 'Kto może kierować głosowania i wiadomości do grupy Wszyscy?', 'openvote' ); ?></strong></p>
<ul>
    <li><?php esc_html_e( 'Gdy w Konfiguracji włączone jest ograniczenie „Do tylko własnych grup” — administrator WordPress oraz koordynator, któremu grupę Wszyscy przypisano na ekranie Role (wtedy jest to „jego” grupa: widzi ją, wybiera i może do niej wysyłać zaproszenia i wiadomości). Pozostali koordynatorzy jej nie widzą.', 'openvote' ); ?></li>
    <li><?php esc_html_e( 'Gdy to ograniczenie jest wyłączone — każdy koordynator może wybrać każdą grupę, także systemową.', 'openvote' ); ?></li>
</ul>
<p class="description"><?php esc_html_e( 'Uwaga: prawo głosu w grupie Wszyscy jest sprawdzane na bieżąco, także w trakcie trwającego głosowania — osoba, która uzupełni pole Grupa, zyskuje prawo głosu; osoba, której je wyczyszczono lub nadano rolę administratora, je traci (już oddane głosy pozostają). Grupa systemowa nie jest wliczana do statystyk członków i nieaktywnych.', 'openvote' ); ?></p>

<h3><?php esc_html_e( 'Synchronizacja wszystkich grup-miast', 'openvote' ); ?></h3>
<p><?php esc_html_e( 'Na ekranie Grupy w zakładce „Synchronizacja” dostępne są dwa przyciski:', 'openvote' ); ?></p>
<ul>
    <li><strong><?php esc_html_e( 'Synchronizuj wszystkie grupy-miasta', 'openvote' ); ?></strong> — <?php esc_html_e( 'odkrywa unikalne wartości pola „miasto” w bazie użytkowników, tworzy brakujące grupy i przypisuje do nich użytkowników. Można wznawiać od ostatniego stanu (checkpoint) — przydatne przy bardzo dużej liczbie użytkowników.', 'openvote' ); ?></li>
    <li><strong><?php esc_html_e( 'Synchronizuj od początku wszystkie grupy-miasta (Nie zalecane)', 'openvote' ); ?></strong> — <?php esc_html_e( 'robi to samo, ale resetuje zapisany stan i zaczyna synchronizację od zera. Użyj tylko wtedy, gdy np. zmieniła się struktura bazy użytkowników, lista miast lub chcesz wymusić pełne przeliczenie. Na co dzień korzystaj z pierwszego przycisku.', 'openvote' ); ?></li>
</ul>
<p><?php esc_html_e( 'Proces może trwać bardzo długo przy dużej liczbie użytkowników. W logu widać postęp (partie miast, liczbę przetworzonych użytkowników).', 'openvote' ); ?></p>

<div class="openvote-manual__tip">
    <?php esc_html_e( 'Jeśli użytkownik ma "(brak)" jako miasto i zostanie ręcznie przypisany do dokładnie jednej grupy, wtyczka automatycznie zaktualizuje jego pole "Miasto" nazwą tej grupy.', 'openvote' ); ?>
</div>
</div>


<!-- 5. Koordynatorzy -->
<div class="openvote-manual__section" id="manual-coordinators">
<h2>5. <?php esc_html_e( 'Koordynatorzy', 'openvote' ); ?></h2>
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


<!-- 6. Głosowania -->
<div class="openvote-manual__section" id="manual-polls">
<h2>6. <?php esc_html_e( 'Głosowania — tworzenie i zarządzanie', 'openvote' ); ?></h2>

<h3><?php esc_html_e( 'Tworzenie nowego głosowania', 'openvote' ); ?></h3>
<ol>
    <li><?php esc_html_e( 'Przejdź do OpenVote → Głosowania → Dodaj nowe.', 'openvote' ); ?></li>
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
        <td><?php esc_html_e( 'Wybierz z listy: 1h, 12h, 1 dzień, 7 dni, 14 dni, 21 dni, 28 dni. Data zakończenia jest obliczana automatycznie.', 'openvote' ); ?></td>
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


<!-- 7. Widok uczestnika -->
<div class="openvote-manual__section" id="manual-vote">
<h2>7. <?php esc_html_e( 'Jak odbywa się głosowanie (widok uczestnika)', 'openvote' ); ?></h2>
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

<h3><?php esc_html_e( 'Głosowanie bez logowania (kod z e-maila)', 'openvote' ); ?></h3>
<p><?php esc_html_e( 'Jeśli administrator włączy tę opcję (Konfiguracja → zakładka „Role i dostęp”, „Pozwól głosować bez logowania”), osoba niezalogowana może zagłosować po potwierdzeniu adresu e-mail:', 'openvote' ); ?></p>
<ol>
    <li><?php esc_html_e( 'Uczestnik wpisuje adres e-mail powiązany z jego kontem na stronie głosowania.', 'openvote' ); ?></li>
    <li><?php esc_html_e( 'Na ten adres wysyłany jest 4-cyfrowy kod. Kod jest ważny 15 minut, a ponowne wysłanie jest możliwe po 120 sekundach.', 'openvote' ); ?></li>
    <li><?php esc_html_e( 'Po wpisaniu poprawnego kodu uczestnik głosuje jako właściciel tego konta (z tymi samymi uprawnieniami i ograniczeniami grup co zalogowany).', 'openvote' ); ?></li>
</ol>
<p><?php esc_html_e( 'Zabezpieczenia: maksymalnie 5 prób na adres IP w ciągu 24 godzin (także dla adresu, którego nie znaleziono). Anonimowość głosu jest nadal egzekwowana po stronie serwera — kod potwierdza tylko tożsamość, nie ujawnia wyboru.', 'openvote' ); ?></p>

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


<!-- 8. Wyniki -->
<div class="openvote-manual__section" id="manual-results">
<h2>8. <?php esc_html_e( 'Wyniki głosowania', 'openvote' ); ?></h2>

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
<p><?php esc_html_e( 'W panelu admina (OpenVote → kliknij głosowanie → Wyniki) dostępne są:', 'openvote' ); ?></p>
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


<!-- 9. Zaproszenia -->
<div class="openvote-manual__section" id="manual-invitations">
<h2>9. <?php esc_html_e( 'Zaproszenia e-mail', 'openvote' ); ?></h2>
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

<h3><?php esc_html_e( 'Limity wysyłki i automatyczne wznowienie', 'openvote' ); ?></h3>
<p><?php esc_html_e( 'W Konfiguracji w tabeli „Warunki wysyłki e-maili” można ustawić dla wybranej metody (Brevo darmowy, WordPress, SMTP) maksymalną liczbę e-maili: na 15 minut, na godzinę oraz na dobę. Wartość 0 oznacza brak limitu. Dla metod abonamentowych (SendGrid, Brevo płatny, Freshmail, GetResponse) limity czasowe nie są egzekwowane.', 'openvote' ); ?></p>
<p><?php esc_html_e( 'Gdy podczas wysyłki zaproszeń zostanie przekroczony ustawiony limit, wysyłka zatrzymuje się. Na ekranie Zaproszenia pojawia się komunikat z informacją, za ile minut można wznowić (dla limitów na 15 min i na godzinę). Przy przekroczeniu limitu dziennego dostępny jest przycisk „Zaplanuj automatyczne wznowienie” — po kliknięciu wysyłka zostanie automatycznie wznowiona o północy w strefie czasu głosowań (Konfiguracja → strefa czasowa).', 'openvote' ); ?></p>
<div class="openvote-manual__tip">
    <?php esc_html_e( 'Limity pomagają uniknąć zablokowania przez dostawcę poczty (np. Brevo free, WordPress) przy dużej liczbie odbiorców. Ustaw wartości zgodne z limitami Twojego planu. Do limitu wliczane są wyłącznie e-maile faktycznie dostarczone (zaakceptowane przez SMTP lub dostawcę); odrzucone próby wysyłki nie zwiększają licznika.', 'openvote' ); ?>
</div>

<h3 id="manual-cron"><?php esc_html_e( 'Automatyczne zaproszenia i cron serwera', 'openvote' ); ?></h3>
<p><?php esc_html_e( 'W formularzu głosowania pole „Automatycznie wyślij zaproszenia po rozpoczęciu głosowania” działa tylko wtedy, gdy na serwerze skonfigurowano cron. Cron to zadanie hostingu, które co 15 minut uruchamia plik PHP wtyczki z wiersza poleceń — dzięki temu wtyczka pracuje także wtedy, gdy nikt nie jest zalogowany.', 'openvote' ); ?></p>
<p><strong><?php esc_html_e( 'Co robi cron:', 'openvote' ); ?></strong></p>
<ul>
    <li><?php esc_html_e( 'Wysyła zaproszenia e-mail na trwające głosowania z zaznaczoną opcją automatycznej wysyłki. Dotyczy wyłącznie głosowań rozpoczętych po włączeniu tej funkcji i tylko jeśli dla głosowania nie wysłano jeszcze zaproszeń (ani automatycznie, ani ręcznie) — nikt nie dostanie zaproszenia dwa razy, a głosowania sprzed aktualizacji wtyczki nie są wysyłane automatycznie. Duże listy są wysyłane partiami w kolejnych wywołaniach, z poszanowaniem limitów wysyłki.', 'openvote' ); ?></li>
    <li><?php esc_html_e( 'Wznawia wysyłkę zaproszeń i wiadomości wstrzymaną przez limity (15 minut, godzina, doba), gdy limit znów na to pozwala — bez konieczności logowania.', 'openvote' ); ?></li>
    <li><?php esc_html_e( 'Wykonuje zaległe zadania harmonogramu wtyczki (np. automatyczną synchronizację grup).', 'openvote' ); ?></li>
</ul>
<p><strong><?php esc_html_e( 'Jak skonfigurować (cPanel):', 'openvote' ); ?></strong></p>
<ol>
    <li><?php esc_html_e( 'Wejdź w Konfiguracja → zakładka „Cron” i skopiuj gotową komendę (przycisk „Kopiuj”).', 'openvote' ); ?></li>
    <li><?php esc_html_e( 'W panelu hostingu (cPanel) otwórz „Zadania Cron” (Cron Jobs), w „Ustawieniach zwykłych” wybierz „Co 15 minut”.', 'openvote' ); ?></li>
    <li><?php esc_html_e( 'W polu „Polecenie” wklej komendę z zakładki Cron (zaczyna się od „/usr/local/bin/php84 -q”). Nie używaj samego /usr/local/bin/php ani php8 — na wielu serwerach to stare PHP 7.4 / 8.0, a wtyczka wymaga PHP 8.1 lub nowszego. Nie są potrzebne ani wget, ani curl, ani żaden klucz. Każde uruchomienie dopisuje do pliku cron/cron-log.php w katalogu wtyczki datę, wersję PHP i wynik — po pierwszym uruchomieniu sprawdź ten plik przez FTP. Przy zbyt starym PHP cron tylko zapisze błąd i listę nowszych wersji PHP znalezionych na serwerze.', 'openvote' ); ?></li>
    <li><?php esc_html_e( 'Po kilkunastu minutach zakładka „Cron” pokaże zielony znacznik „Działa”. Przycisk „Uruchom teraz” pozwala sprawdzić działanie od ręki.', 'openvote' ); ?></li>
</ol>
<div class="openvote-manual__tip">
    <?php esc_html_e( 'Katalog „cron” wtyczki jest zablokowany dla przeglądarek plikiem .htaccess, a skrypt uruchamia się wyłącznie z wiersza poleceń. Bez crona zaproszenia nadal można wysłać ręcznie z ekranu Zaproszenia.', 'openvote' ); ?>
</div>
</div>


<!-- 10. Komunikacja -->
<div class="openvote-manual__section" id="manual-communication">
<h2>10. <?php esc_html_e( 'Wysyłka — masowa wysyłka wiadomości', 'openvote' ); ?></h2>
<p><?php esc_html_e( 'Moduł Wysyłka (menu OpenVote → Wysyłka) umożliwia tworzenie i wysyłkę masowych wiadomości e-mail do wybranych grup użytkowników — niezależnie od głosowań. Przydatne do ogłoszeń, powiadomień lub newslettera.', 'openvote' ); ?></p>

<h3><?php esc_html_e( 'Tworzenie i wysyłka', 'openvote' ); ?></h3>
<ol>
    <li><?php esc_html_e( 'Wpisz tytuł wysyłki i treść wiadomości w sekcji "Nowa wysyłka" (lub edytuj istniejącą w pozycji "Edytuj" na liście).', 'openvote' ); ?></li>
    <li><?php esc_html_e( 'Wybierz co najmniej jedną grupę docelową (Ctrl lub Cmd + klik dla wielu). Tylko członkowie tych grup otrzymają wiadomość.', 'openvote' ); ?></li>
    <li><?php esc_html_e( 'Kliknij "Zapisz", a następnie "Wyślij" przy danej wiadomości — otworzy się ekran wysyłki z podglądem i przyciskiem "Wyślij wiadomości e-mailem".', 'openvote' ); ?></li>
    <li><?php esc_html_e( 'Wysyłka odbywa się partiami (jak zaproszenia). Postęp i ewentualne błędy są widoczne na ekranie.', 'openvote' ); ?></li>
</ol>

<h3><?php esc_html_e( 'Kody w treści wiadomości', 'openvote' ); ?></h3>
<p><?php esc_html_e( 'W treści i temacie możesz użyć kodów zastępowanych przy wysyłce:', 'openvote' ); ?></p>
<ul>
    <li><code>{Nadawca}</code> — <?php esc_html_e( 'imię i nazwisko autora wiadomości', 'openvote' ); ?></li>
    <li><code>{Skrót nazwy}</code> — <?php esc_html_e( 'skrót organizacji z Konfiguracji', 'openvote' ); ?></li>
    <li><code>{moja_grupa}</code> — <?php esc_html_e( 'nazwa grupy odbiorcy (z wybranych grup docelowych)', 'openvote' ); ?></li>
    <li><code>{grupa_docelowa}</code> — <?php esc_html_e( 'lista wszystkich grup docelowych wysyłki', 'openvote' ); ?></li>
</ul>

<h3><?php esc_html_e( 'Statusy i akcje na liście', 'openvote' ); ?></h3>
<ul>
    <li><strong><?php esc_html_e( 'Szkic', 'openvote' ); ?></strong> — <?php esc_html_e( 'wiadomość nie została jeszcze wysłana. Dostępne: Edytuj, Duplikuj, Usuń, Wyślij.', 'openvote' ); ?></li>
    <li><strong><?php esc_html_e( 'Wysłano', 'openvote' ); ?></strong> — <?php esc_html_e( 'wiadomość została wysłana. Dostępne: Podgląd (wyszarzony widok bez edycji), Duplikuj, Usuń. Link "Wyślij" nie jest wyświetlany.', 'openvote' ); ?></li>
</ul>
<p><?php esc_html_e( 'Wysłanej wiadomości nie można już edytować; można ją zduplikować i edytować kopię.', 'openvote' ); ?></p>

<h3><?php esc_html_e( 'Limity i statystyki', 'openvote' ); ?></h3>
<p><?php esc_html_e( 'Te same limity co dla zaproszeń (na 15 min, godzinę, dobę) dotyczą wysyłki z modułu Wysyłka. Do limitu i statystyk "Liczba wysłanych e-maili" wliczane są tylko e-maile faktycznie dostarczone do serwera SMTP lub dostawcy; odrzucone adresy (np. nieaktywne) nie zwiększają licznika.', 'openvote' ); ?></p>
</div>


<!-- 11. Konfiguracja -->
<div class="openvote-manual__section" id="manual-config">
<h2>11. <?php esc_html_e( 'Konfiguracja wtyczki', 'openvote' ); ?></h2>
<p><?php esc_html_e( 'Dostępna w menu OpenVote → Konfiguracja (tylko Administrator WordPress). Ekran ma zakładki: Ogólne, Pola użytkownika, Role i dostęp, Wysyłka e-mail, Szablon zaproszenia, Auto-synchronizacja, Nieaktywni, Cron, Narzędzia.', 'openvote' ); ?></p>

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
        <td><?php esc_html_e( 'WordPress (wp_mail), SMTP zewnętrzny, SendGrid API, Brevo (darmowy i płatny), Freshmail API, GetResponse API. Wybór dotyczy zarówno zaproszeń do głosowań, jak i wysyłki z modułu Wysyłka.', 'openvote' ); ?></td>
    </tr>
    <tr>
        <td><strong><?php esc_html_e( 'Warunki wysyłki e-maili', 'openvote' ); ?></strong></td>
        <td><?php esc_html_e( 'Dla Brevo darmowy, WordPress i SMTP: maks. liczba e-maili na 15 min, na godzinę i na dobę. Po przekroczeniu limitu wysyłka zatrzymuje się; przy limitcie dziennym można zaplanować automatyczne wznowienie o północy. Wartość 0 = brak limitu.', 'openvote' ); ?></td>
    </tr>
    <tr>
        <td><strong><?php esc_html_e( 'Odinstaluj', 'openvote' ); ?></strong></td>
        <td><?php esc_html_e( 'Na dole strony Konfiguracji. Usuwa wszystkie tabele i opcje wtyczki z bazy danych. Nieodwracalne — używać ostrożnie.', 'openvote' ); ?></td>
    </tr>
    </tbody>
</table>
</div>


<!-- 12. Mapowanie pól -->
<div class="openvote-manual__section" id="manual-fieldmap">
<h2>12. <?php esc_html_e( 'Mapowanie pól użytkownika', 'openvote' ); ?></h2>
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
    <tr><td><?php esc_html_e( 'Nickname', 'openvote' ); ?></td><td><code>user_nicename</code></td><td><?php esc_html_e( 'Domyślnie: wbudowane pole „Przyjazna dla URL wersja nazwy” (user_nicename) z wp_users. Pseudonim w anonimowych wynikach. Zawsze wymagane.', 'openvote' ); ?></td></tr>
    <tr><td><?php esc_html_e( 'E-mail', 'openvote' ); ?></td><td><code>user_email</code></td><td><?php esc_html_e( 'Adres e-mail (wbudowane WP). Zawsze wymagane.', 'openvote' ); ?></td></tr>
    <tr><td><?php esc_html_e( 'Grupa', 'openvote' ); ?></td><td><code>user_registration_miejsce_spotkania</code></td><td><?php esc_html_e( 'Pole używane do przypisywania do grup i do grupy systemowej Wszyscy (członkowie = wypełnione pole). Zmień na klucz używany przez Twoją wtyczkę rejestracji. Domyślnie wymagane do głosowania. Można wyłączyć (wszyscy z rolą, poza administratorami, w grupie Wszyscy).', 'openvote' ); ?></td></tr>
    <tr><td><?php esc_html_e( 'Numer telefonu', 'openvote' ); ?></td><td><code>user_gsm</code></td><td><?php esc_html_e( 'Opcjonalne. Ustaw na klucz z Twojej wtyczki lub zostaw "nie określone".', 'openvote' ); ?></td></tr>
    <tr><td><?php esc_html_e( 'Dane własne', 'openvote' ); ?></td><td><code>user_id</code></td><td><?php esc_html_e( 'Opcjonalne. Dowolne pole z profilu (np. numer dowodu). Można zaznaczyć jako wymagane do głosowania.', 'openvote' ); ?></td></tr>
    </tbody>
</table>

<h3><?php esc_html_e( 'Kolumna "Wymagane do głosowania"', 'openvote' ); ?></h3>
<p><?php esc_html_e( 'Checkbox przy każdym polu oznacza, że użytkownik MUSI mieć wypełnione to pole, aby móc oddać głos. Pola "zawsze" (Imię, Nazwisko, Nickname, E-mail) nie mogą zostać odznaczone.', 'openvote' ); ?></p>

<h3><?php esc_html_e( 'Dane wrażliwe na stronie zgłoszeń', 'openvote' ); ?></h3>
<p><?php esc_html_e( 'Na publicznej stronie zgłoszeń (/zgloszenia lub inny skonfigurowany slug) wtyczka nie wyświetla wartości pól uznanych za wrażliwe. Są to pola z listy mapowania: E-mail, Grupa, Numer telefonu, Dane własne. Aby dana odpowiedź w ankiecie była na stronie zgłoszeń ukryta (pokazywane jest „—"), przy tworzeniu lub edycji ankiety zaznacz checkbox „Informacja wrażliwa - nie pokazuj odpowiedzi na stronie publicznie" przy tym pytaniu.', 'openvote' ); ?></p>

<div class="openvote-manual__tip">
    <?php esc_html_e( 'Przykład: jeśli zaznaczysz pole opcjonalne (np. Telefon) jako wymagane do głosowania, ale klucz pozostanie "nie określone", żaden użytkownik nie będzie mógł głosować (pole zawsze puste). Najpierw ustaw poprawny klucz meta, dopiero potem zaznacz jako wymagane.', 'openvote' ); ?>
</div>
</div>


<!-- 13. Ankiety i strona zgłoszeń -->
<div class="openvote-manual__section" id="manual-surveys">
<h2>13. <?php esc_html_e( 'Ankiety i strona zgłoszeń', 'openvote' ); ?></h2>
<p><?php esc_html_e( 'Wtyczka umożliwia tworzenie ankiet (formularzy z pytaniami), zbieranie odpowiedzi użytkowników oraz publiczne wyświetlanie zatwierdzonych zgłoszeń na dedykowanej stronie.', 'openvote' ); ?></p>

<h3><?php esc_html_e( 'Gdzie znajdziesz ankiety', 'openvote' ); ?></h3>
<ul>
    <li><?php esc_html_e( 'Lista ankiet: OpenVote → Ankiety (lub z menu listy głosowań — opcja "Ankiety").', 'openvote' ); ?></li>
    <li><?php esc_html_e( 'Na górze listy ankiet wyświetlane jest okienko z adresami strony ankiet i strony zgłoszeń (linki do wypełniania ankiet i przeglądania zgłoszeń). Okienko można zamknąć krzyżykiem; stan zamknięcia jest zapamiętany do końca sesji przeglądarki.', 'openvote' ); ?></li>
    <li><?php esc_html_e( 'Tworzenie i edycja: tytuł, opis, czas trwania, pola ankiety (etykieta, typ pola, opcjonalnie checkbox informacja wrażliwa).', 'openvote' ); ?></li>
    <li><?php esc_html_e( 'Dla każdej ankiety: lista zgłoszeń ze statusem (oczekuje / nie spam / spam), przycisk "To nie spam" do zatwierdzania zgłoszeń.', 'openvote' ); ?></li>
</ul>

<h3><?php esc_html_e( 'Informacja wrażliwa przy polach ankiety', 'openvote' ); ?></h3>
<p><?php esc_html_e( 'Przy każdym polu ankiety (pytaniu) znajduje się checkbox „Informacja wrażliwa - nie pokazuj odpowiedzi na stronie publicznie”. Domyślnie jest odznaczony.', 'openvote' ); ?></p>
<ul>
    <li><?php esc_html_e( 'Gdy checkbox jest odznaczony, odpowiedź użytkownika na to pytanie jest na stronie zgłoszeń (/zgloszenia) wyświetlana w całości.', 'openvote' ); ?></li>
    <li><?php esc_html_e( 'Gdy checkbox jest zaznaczony, wartość tej odpowiedzi na stronie zgłoszeń zostanie zastąpiona znakiem „—", aby nie ujawniać danych publicznie.', 'openvote' ); ?></li>
</ul>
<p><?php esc_html_e( 'W nagłówku każdej karty zgłoszenia na stronie publicznej wyświetlane są wyłącznie imię i nazwisko; pozostałe dane z profilu (e-mail, telefon, data) nie są tam pokazywane.', 'openvote' ); ?></p>

<h3><?php esc_html_e( 'Strona zgłoszeń (/zgloszenia)', 'openvote' ); ?></h3>
<p><?php esc_html_e( 'Adres strony (slug) ustawiasz w Konfiguracji w sekcji „URL strony zgłoszeń". Strona powinna zawierać blok Gutenberg „Zgłoszenia (Open Vote)" (openvote/survey-responses). Możesz utworzyć ją ręcznie w edytorze lub użyć przycisków w Konfiguracji: „Utwórz stronę zgłoszeń" lub „Zaktualizuj stronę zgłoszeń (dodaj blok)".', 'openvote' ); ?></p>
<p><?php esc_html_e( 'Na stronie wyświetlane są tylko zgłoszenia ze statusem „Nie spam". Dla każdego zgłoszenia widać: tytuł ankiety, imię i nazwisko oraz listę pytań z odpowiedziami. Odpowiedzi oznakowane w ankiecie jako informacja wrażliwa są ukrywane („—").', 'openvote' ); ?></p>
<p><?php esc_html_e( 'Dostęp do listy zgłoszeń ma każdy, kto może wyświetlić stronę. Aby ograniczyć dostęp (np. tylko dla zalogowanych lub wybranej roli), umieść blok na stronie chronionej hasłem lub ustaw widoczność strony na „Prywatna".', 'openvote' ); ?></p>

<div class="openvote-manual__note">
    <strong><?php esc_html_e( 'Strona ankiet', 'openvote' ); ?></strong><br>
    <?php esc_html_e( 'Osobna opcja w Konfiguracji to „URL strony ankiet" — strona z blokiem „Ankiety (Open Vote)", na której użytkownicy wypełniają ankiety. Slug możesz ustawić np. na "ankieta".', 'openvote' ); ?>
</div>
</div>


<!-- 14. FAQ -->
<div class="openvote-manual__section" id="manual-faq">
<h2>14. <?php esc_html_e( 'Najczęstsze pytania (FAQ)', 'openvote' ); ?></h2>

<h4><?php esc_html_e( 'Użytkownik widzi "Twój profil jest niekompletny" — co robić?', 'openvote' ); ?></h4>
<p><?php esc_html_e( 'Sprawdź, które pole jest brakujące (komunikat podaje nazwę pola). Poproś użytkownika o uzupełnienie profilu lub sprawdź w Konfiguracji → Mapowanie pól, czy klucz jest poprawny.', 'openvote' ); ?></p>

<h4><?php esc_html_e( 'Głosowanie nie pojawia się w zakładce "Trwające" pomimo że jest aktywne.', 'openvote' ); ?></h4>
<p><?php esc_html_e( 'Sprawdź: (1) Czy użytkownik należy do grupy docelowej głosowania? (2) Czy profil użytkownika jest kompletny? (3) Czy data zakończenia nie minęła?', 'openvote' ); ?></p>

<h4><?php esc_html_e( 'Zaproszenia e-mail nie dochodzą.', 'openvote' ); ?></h4>
<p><?php esc_html_e( 'Sprawdź: (1) Ekran Zaproszenia — czy status to "wysłano" czy "błąd"? (2) Konfiguracja → ustawienia e-mail — czy skonfigurowany jest SMTP lub SendGrid? (3) Sprawdź folder SPAM u odbiorcy.', 'openvote' ); ?></p>

<h4><?php esc_html_e( 'Wysyłka zaproszeń zatrzymała się z komunikatem o limicie.', 'openvote' ); ?></h4>
<p><?php esc_html_e( 'Wtyczka respektuje limity ustawione w Konfiguracji (Warunki wysyłki e-maili). Poczekaj do czasu podanego w komunikacie (np. „Wznowienie za X min”) lub przy limitcie dziennym kliknij „Zaplanuj automatyczne wznowienie” — wysyłka zostanie wznowiona o północy. Możesz też zwiększyć limity w Konfiguracji, jeśli dostawca poczty na to pozwala.', 'openvote' ); ?></p>

<h4><?php esc_html_e( 'Strona głosowania pokazuje błąd lub przekierowuje w pętli.', 'openvote' ); ?></h4>
<p><?php esc_html_e( 'Przejdź do Konfiguracji i kliknij "Zaktualizuj stronę głosowania" jeśli pojawia się ostrzeżenie. Następnie odśwież permalinki w Ustawienia → Bezpośrednie odnośniki.', 'openvote' ); ?></p>

<h4><?php esc_html_e( 'Jak zmienić nazwę witryny w nagłówku i PDF?', 'openvote' ); ?></h4>
<p><?php esc_html_e( 'Wtyczka używa Tytułu witryny z WordPress (Ustawienia → Ogólne → Tytuł witryny). Zmień tam, a zaktualizuje się wszędzie automatycznie.', 'openvote' ); ?></p>

<h4><?php esc_html_e( 'Jak zmienić logo wyświetlane w panelu admina?', 'openvote' ); ?></h4>
<p><?php esc_html_e( 'Wtyczka używa ikony witryny WordPress (Wygląd → Dostosuj → Tożsamość witryny → Ikona witryny). Ustaw ikonę tam.', 'openvote' ); ?></p>

<h4><?php esc_html_e( 'Co to jest tryb "Nie używaj miast"?', 'openvote' ); ?></h4>
<p><?php esc_html_e( 'Gdy wybierzesz tę opcję w mapowaniu pola Miasto, wszystkich użytkowników z rolą (poza administratorami) traktuje jako należących do jednej grupy systemowej „Wszyscy” (zob. rozdział 4, „Grupa systemowa Wszyscy”). Przydatne gdy nie chcesz grupować uczestników według lokalizacji.', 'openvote' ); ?></p>
</div>

<p style="margin-top:3em;color:#999;font-size:12px;">
    <?php printf(
        esc_html__( 'Wersja dokumentacji: %s', 'openvote' ),
        esc_html( OPENVOTE_VERSION )
    ); ?>
</p>

</div>
