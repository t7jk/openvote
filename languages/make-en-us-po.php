<?php
/**
 * Generates evoting-en_US.po from evoting.pot using PL→EN translations.
 * Run from plugin root: php languages/make-en-us-po.php
 * Then: msgfmt -o languages/evoting-en_US.mo languages/evoting-en_US.po
 */

$pot_path = __DIR__ . '/evoting.pot';
$po_path  = __DIR__ . '/evoting-en_US.po';

$translations = [
    // Menu & main
    'Głosowania' => 'Polls',
    'Ankiety' => 'Surveys',
    'Grupy użytkowników' => 'User groups',
    'Grupy' => 'Groups',
    'Koordynatorzy' => 'Coordinators',
    'Konfiguracja' => 'Settings',
    'Podręcznik użytkownika' => 'User manual',
    '📖 Podręcznik' => '📖 Manual',
    'Przepisy prawne' => 'Legal provisions',
    '⚖️ Przepisy' => '⚖️ Legal',
    'O tym' => 'About',
    'Ustawienia' => 'Settings',
    'Dodaj nowe' => 'Add new',
    'Edytuj' => 'Edit',
    'Usuń' => 'Delete',
    'Wyniki' => 'Results',
    'Zaproszenia' => 'Invitations',
    'Podgląd' => 'Preview',
    'Duplikuj' => 'Duplicate',
    'Zakończ' => 'End',
    'Wszystkie' => 'All',
    'Tytuł' => 'Title',
    'Status' => 'Status',
    'Opis' => 'Description',
    'Akcja' => 'Action',
    'Szkic' => 'Draft',
    'Rozpoczęte' => 'Open',
    'Zakończone' => 'Closed',
    'Otwarta' => 'Open',
    'Zamknięta' => 'Closed',
    'Rozpoczęcie' => 'Start',
    'Zakończenie' => 'End',
    'E-mail' => 'Email',
    'Adres URL' => 'URL',
    'Zgłoszenia' => 'Submissions',
    'Głosowanie' => 'Voting',
    'głosowanie' => 'poll',
    'głosowania' => 'polls',
    'ankieta' => 'survey',
    'ankiety' => 'surveys',
    // Messages
    'Link wygasł lub jest nieprawidłowy.' => 'Link has expired or is invalid.',
    'Głosowanie nie istnieje.' => 'Poll does not exist.',
    'Brak uprawnień.' => 'Insufficient permissions.',
    'Głosowanie zostało uruchomione.' => 'Poll has been started.',
    'Głosowanie zostało usunięte.' => 'Poll has been deleted.',
    'Głosowanie zostało utworzone.' => 'Poll has been created.',
    'Głosowanie zostało zakończone.' => 'Poll has ended.',
    'Zmiany zostały zapisane.' => 'Changes have been saved.',
    'Utworzono kopię głosowania. Znajdziesz ją na liście jako szkic.' => 'Poll copy created. You will find it on the list as a draft.',
    'Edytuj skopiowane głosowanie' => 'Edit copied poll',
    'Szukaj głosowania' => 'Search poll',
    'Nie można edytować głosowania, które zostało rozpoczęte lub zakończone.' => 'Cannot edit a poll that has been started or ended.',
    'Nie można edytować głosowania, które zostało rozpoczęte lub zakończone. Tylko szkice są edytowalne.' => 'Cannot edit a poll that has been started or ended. Only drafts are editable.',
    'Nie udało się skopiować głosowania. Upewnij się, że głosowanie ma pytania.' => 'Failed to copy poll. Make sure the poll has questions.',
    'Błąd zapisu głosowania.' => 'Error saving poll.',
    'Tytuł jest wymagany.' => 'Title is required.',
    'Tytuł może zawierać maksymalnie 512 znaków.' => 'Title may contain at most 512 characters.',
    'Wybierz poprawny czas trwania głosowania.' => 'Select a valid poll duration.',
    'Każde pytanie może zawierać maksymalnie 512 znaków.' => 'Each question may contain at most 512 characters.',
    'Każde pytanie musi mieć co najmniej 3 odpowiedzi (w tym obowiązkową abstencję).' => 'Each question must have at least 3 answers (including mandatory abstention).',
    'Maksymalnie 12 odpowiedzi per pytanie.' => 'Maximum 12 answers per question.',
    'Dodaj przynajmniej jedno pytanie.' => 'Add at least one question.',
    'Maksymalnie 24 pytania.' => 'Maximum 24 questions.',
    'Nie wybrano użytkownika lub grupy.' => 'No user or group selected.',
    'Wystąpił błąd.' => 'An error occurred.',
    'Nieprawidłowy token zabezpieczający.' => 'Invalid security token.',
    'Zaznacz pole potwierdzenia przed usunięciem.' => 'Check the confirmation box before deleting.',
    'Zaznacz pole potwierdzenia przed wyczyszczeniem.' => 'Check the confirmation box before resetting.',
    'Baza danych i ustawienia zostały przywrócone do stanu fabrycznego.' => 'Database and settings have been reset to defaults.',
    'Błąd zapisu ankiety.' => 'Error saving survey.',
    'Tytuł ankiety jest wymagany.' => 'Survey title is required.',
    'Opis może zawierać maksymalnie 5000 znaków.' => 'Description may contain at most 5000 characters.',
    'Wybierz poprawny czas trwania ankiety.' => 'Select a valid survey duration.',
    'Etykieta pola może zawierać maksymalnie 512 znaków.' => 'Field label may contain at most 512 characters.',
    'Dodaj przynajmniej jedno pole ankiety.' => 'Add at least one survey field.',
    'Ankieta musi mieć co najmniej jedno pole.' => 'Survey must have at least one field.',
    'Wypełnij etykiety wszystkich pól.' => 'Fill in all field labels.',
    'Data zakończenia musi być późniejsza niż data rozpoczęcia.' => 'End date must be after start date.',
    'Nazwa grupy jest wymagana.' => 'Group name is required.',
    'Grupa została dodana.' => 'Group has been added.',
    'Błąd zapisu — nazwa grupy może być już zajęta.' => 'Save error — group name may already be in use.',
    'Nie wybrano grupy do usunięcia.' => 'No group selected for deletion.',
    'Wybrana grupa nie istnieje.' => 'Selected group does not exist.',
    'Grupa została usunięta.' => 'Group has been removed.',
    'Wybierz grupę i użytkownika.' => 'Select group and user.',
    'Członek dodany.' => 'Member added.',
    'Członek usunięty.' => 'Member removed.',
    'Wybierz użytkownika z listy lub wpisz ID użytkownika.' => 'Select a user from the list or enter user ID.',
    'Wybierz co najmniej jedną grupę.' => 'Select at least one group.',
    'Profil użytkownika zaktualizowany: wpisano miejsce zamieszkania.' => 'User profile updated: place of residence added.',
    'Nazwa grupy' => 'Group name',
    'Członkowie' => 'Members',
    'Brak grup.' => 'No groups.',
    'Usunąć tę grupę? Zostaną usunięci wszyscy jej członkowie (przypisania).' => 'Delete this group? All its members (assignments) will be removed.',
    'Usuń grupę' => 'Delete group',
    'ID użytkownika' => 'User ID',
    'Dodaj ręcznie' => 'Add manually',
    'Użytkownik' => 'User',
    'Źródło' => 'Source',
    'Dodano' => 'Added',
    'Brak członków.' => 'No members.',
    'Usunąć z grupy?' => 'Remove from group?',
    'Poprzednie' => 'Previous',
    'Następne' => 'Next',
    'Dodaj użytkownika do grup' => 'Add user to groups',
    'Ręczne przypisanie użytkownika do jednej lub wielu grup (niezależnie od automatycznego przyporządkowania). Przydatne przy testach.' => 'Manually assign a user to one or more groups. Useful for testing.',
    'Dodaj grupę' => 'Add group',
    'Synchronizacja grup-miast' => 'City groups sync',
    'Synchronizuj wszystkie grupy-miasta' => 'Sync all city groups',
    'Odkrywa unikalne wartości pola "miasto" w bazie użytkowników, tworzy brakujące grupy i przypisuje do nich użytkowników automatycznie (partiami po 100).' => 'Finds unique "city" values in the user database, creates missing groups and assigns users automatically (in batches of 100).',
    'Przewiń listę, wybierz jedną osobę.' => 'Scroll the list and select one person.',
    'Przepisy prawne obowiązujące w głosowaniach' => 'Legal provisions for voting',
    'Brak głosowań.' => 'No polls.',
    'Data rozpoczęcia' => 'Start date',
    'Data zakończenia' => 'End date',
    'Pytania' => 'Questions',
    'Akcje' => 'Actions',
    'Wszystkie głosowania' => 'All polls',
    'Edytuj głosowanie' => 'Edit poll',
    'Wyniki głosowania' => 'Poll results',
    'Pobierz wyniki (PDF)' => 'Download results (PDF)',
    'Frekwencja' => 'Turnout',
    'Lista głosujących (%d)' => 'List of voters (%d)',
    'Nie głosowali (%d)' => 'Did not vote (%d)',
    'Załaduj więcej (pokazano %1$d–%2$d z %3$d)' => 'Load more (showing %1$d–%2$d of %3$d)',
    'Pokaż od początku' => 'Show from start',
    'Anonimowy' => 'Anonymous',
    'Zaproszenia e-mail' => 'Email invitations',
    'Wyślij zaproszenia' => 'Send invitations',
    'Wyślij ponownie' => 'Send again',
    'Wysyłanie…' => 'Sending…',
    'Wysyłka zakończona!' => 'Sending complete!',
    'Wyniki dostępne po zakończeniu głosowania.' => 'Results available after the poll has ended.',
    'Zaproszenia można wysyłać tylko do otwartych lub zakończonych głosowań.' => 'Invitations can only be sent for open or ended polls.',
    'Wystąpił błąd podczas uruchamiania wysyłki zaproszeń.' => 'An error occurred while starting the invitation send.',
    'Ankieta nie istnieje.' => 'Survey does not exist.',
    'Ankieta nie jest aktualnie aktywna.' => 'Survey is not currently active.',
    'Błąd zapisu odpowiedzi.' => 'Error saving response.',
    'Twoja odpowiedź została zapisana jako Gotowa.' => 'Your response has been saved as Ready.',
    'Twoja odpowiedź została zapisana jako Szkic.' => 'Your response has been saved as Draft.',
    'Brak danych do zapisania.' => 'No data to save.',
    'Nieprawidłowy adres e-mail.' => 'Invalid email address.',
    'Grupa nie istnieje.' => 'Group does not exist.',
    'Synchronizacja automatyczna działa tylko dla grup typu "city".' => 'Automatic sync only works for groups of type "city".',
    'Synchronizacja uruchomiona.' => 'Sync started.',
    'Synchronizacja wszystkich grup-miast uruchomiona.' => 'Sync of all city groups started.',
    'Zadanie wygasło lub nie istnieje.' => 'Task has expired or does not exist.',
    '— brak (pole dowolne)' => '— none (optional)',
    'Krótki tekst do 100 znaków' => 'Short text up to 100 characters',
    'Długi tekst do 2000 znaków' => 'Long text up to 2000 characters',
    'Numer do 30 cyfr' => 'Number up to 30 digits',
    'Etykieta / tytuł pola' => 'Field label / title',
    'Pole profilu (na stronie /zgłoszenia/ dane wrażliwe są ukrywane)' => 'Profile field (sensitive data is hidden on /submissions/ page)',
    'Limit znaków:' => 'Character limit:',
    'Usuń pole' => 'Remove field',
    'Tytuł wtyczki' => 'Plugin title',
    'System e-głosowania' => 'E-voting system',
    'Autor' => 'Author',
    'Wersja' => 'Version',
    'Licencja' => 'License',
    'Wersja Darmowa (Free Version)' => 'Free Version',
    'Zakończyć głosowanie? Przyjmowanie głosów zostanie zatrzymane, data zakończenia ustawiona na dziś. Operacja nieodwracalna.' => 'End the poll? Voting will be stopped and end date set to today. This cannot be undone.',
    'Czy na pewno chcesz usunąć to głosowanie?' => 'Are you sure you want to delete this poll?',
    'Zakończyć ankietę? Operacja nieodwracalna.' => 'End the survey? This cannot be undone.',
    'Usunąć tę ankietę wraz z odpowiedziami?' => 'Delete this survey and all responses?',
    'Wtyczka do przeprowadzania elektronicznych głosowań i ankiet w organizacji. Umożliwia tworzenie głosowań z pytaniami i odpowiedziami, zarządzanie grupami uczestników, wysyłanie zaproszeń e-mail oraz przeglądanie wyników z zachowaniem anonimowości.' => 'Plugin for running electronic polls and surveys in your organization. Create polls with questions and answers, manage participant groups, send email invitations, and view results with anonymity preserved.',
    'Musisz być zalogowany, aby wziąć udział w głosowaniu.' => 'You must be logged in to vote.',
    'Konto użytkownika nie istnieje.' => 'User account does not exist.',
    'Twój profil jest niekompletny. Brakuje: %s.' => 'Your profile is incomplete. Missing: %s.',
    'Nie możesz głosować w tym głosowaniu. %s' => 'You cannot vote in this poll. %s',
    'Oddaj głos' => 'Cast vote',
    'Wstrzymuję się' => 'Abstain',
    'Głosuj jawnie - wyniki będą zawierać %1$s (%2$s) %3$s.' => 'Vote publicly — results will show %1$s (%2$s) %3$s.',
    'Głosuj anonimowo - w wynikach pojawisz się jako "Anonimowy".' => 'Vote anonymously — you will appear as "Anonymous" in the results.',
    'Trwające głosowania' => 'Active polls',
    'Zakończone głosowania' => 'Ended polls',
    'Brak głosowań w tym momencie.' => 'No polls at this time.',
    'Powrót do głosowań' => 'Back to polls',
    'Aby wziąć udział w %1$s, uzupełnij swój profil. Brakujące pola: %2$s' => 'To participate in %1$s, complete your profile. Missing fields: %2$s',
    'Proszę o wprowadzenie brakujących danych. Dane zostaną dodane do Twojego profilu użytkownika na tej stronie i będą dostępne do późniejszego wykorzystywania w następnych ankietach lub głosowaniach.' => 'Please enter the missing data. It will be added to your user profile and used in future surveys or polls.',
    'Uzupełnij profil przed wypełnieniem ankiety. Brakujące pola: %s' => 'Complete your profile before filling the survey. Missing fields: %s',
    '(brak imienia i nazwiska)' => '(no name)',
    'Za' => 'Yes',
    'Przeciw' => 'No',
    'Wstrzymało się' => 'Abstained',
    'Nie biorący udziału' => 'Did not participate',
    'Nie głosowali' => 'Did not vote',
    'Uprawnieni użytkownicy, którzy nie oddali głosu. Pseudonimy zanonimizowane.' => 'Eligible users who did not vote. Nicknames anonymized.',
    'Widoczne: imię i nazwisko oraz zanonimizowany adres e-mail. Pozostałe dane są utajnione.' => 'Shown: name and anonymized email. Other data is hidden.',
    'Wyświetlanie partiami po 100.' => 'Displayed in batches of 100.',
    'Twój głos został zapisany. Dziękujemy!' => 'Your vote has been recorded. Thank you!',
    'Nowe głosowanie: %s' => 'New poll: %s',
    "Zostało otwarte nowe głosowanie: %s\n\nZaloguj się, aby oddać swój głos." => "A new poll has been opened: %s\n\nLog in to cast your vote.",
    'Maksymalnie %d pól ankiety.' => 'Maximum %d survey fields.',
];

$pot = file_get_contents( $pot_path );
if ( $pot === false ) {
    fwrite( STDERR, "Cannot read $pot_path\n" );
    exit( 1 );
}

$en_header = '"Project-Id-Version: EP-RWL E-Voting\n"
"Report-Msgid-Bugs-To: \n"
"POT-Creation-Date: ' . date( 'Y-m-d H:iO' ) . '\n"
"PO-Revision-Date: ' . date( 'Y-m-d H:iO' ) . '\n"
"Last-Translator: \n"
"Language-Team: English\n"
"Language: en_US\n"
"MIME-Version: 1.0\n"
"Content-Type: text/plain; charset=UTF-8\n"
"Content-Transfer-Encoding: 8bit\n"
"Plural-Forms: nplurals=1; plural=0;\n"
"X-Generator: make-en-us-po.php\n"
';

// Replace the header (first msgstr block).
$pot = preg_replace(
    '/^msgstr ""\n""[^"]*"Plural-Forms:[^"]*";\n"/m',
    'msgstr ""' . "\n" . $en_header . '"',
    $pot,
    1
);

// For each "msgid \"...\"\nmsgstr \"\"" replace msgstr with translation if we have it.
$lines = explode( "\n", $pot );
$out = [];
$i = 0;
$n = count( $lines );
while ( $i < $n ) {
    $line = $lines[ $i ];
    $out[] = $line;
    // Match line that is msgid "something" (possibly multiline)
    if ( preg_match( '/^msgid "(.*)"\s*$/', $line, $m ) ) {
        $msgid = str_replace( '\\n', "\n", $m[1] );
        $j = $i + 1;
        while ( $j < $n && preg_match( '/^"(.*)"\s*$/', $lines[ $j ] ) ) {
            $msgid .= str_replace( '\\n', "\n", substr( $lines[ $j ], 1, -1 ) );
            $out[] = $lines[ $j ];
            $j++;
        }
        $i = $j - 1;
        // Next non-empty line should be msgstr or msgid_plural
        if ( $i + 1 < $n ) {
            $next = $lines[ $i + 1 ];
            if ( preg_match( '/^msgstr "(.*)"\s*$/', $next ) && trim( $next ) !== 'msgstr ""' ) {
                $i++;
                $out[] = $next;
                $i++;
                continue;
            }
            if ( preg_match( '/^msgstr ""\s*$/', $next ) && $msgid !== '' && isset( $translations[ $msgid ] ) ) {
                $i++;
                $trans = $translations[ $msgid ];
                $trans = addcslashes( $trans, '"\\' );
                $trans = str_replace( "\n", '\\n",' . "\n" . '"', $trans );
                $out[] = 'msgstr "' . $trans . '"';
                $i++;
                continue;
            }
            if ( preg_match( '/^msgstr ""\s*$/', $next ) ) {
                $i++;
                $out[] = $next;
                $i++;
                continue;
            }
        }
    }
    $i++;
}

$po_content = implode( "\n", $out );

// Plural forms: leave msgstr[0]/msgstr[1] as in .pot; fix manually in .po if needed.
file_put_contents( $po_path, $po_content );
echo "Written $po_path\n";
echo "Run: msgfmt -o " . __DIR__ . "/evoting-en_US.mo $po_path\n";
