# Open Vote (Otwarte Głosowanie)

Wtyczka WordPress do przeprowadzania elektronicznych głosowań wśród zarejestrowanych użytkowników: głosowania wielopytaniowe, grupy docelowe, zaproszenia e-mail, masowa wysyłka wiadomości (Komunikacja), ankiety i wyniki (jawne lub anonimowe).

**Autor:** Tomasz Kalinowski  
**Licencja:** GPL v2 lub późniejsza  
**Wymagania:** WordPress 6.4+, PHP 8.1+

---

## Do czego służy wtyczka

- **Głosowania** — tworzenie głosowań z wieloma pytaniami (1–24), określonym czasem trwania i grupami docelowymi.
- **Grupy** — przypisywanie użytkowników do grup (np. według miasta); grupy docelowe decydują, kto może głosować.
- **Koordynatorzy** — rola wtyczki: zarządzanie głosowaniami i grupami bez dostępu do pełnej Konfiguracji.
- **Zaproszenia e-mail** — automatyczna wysyłka zaproszeń po starcie głosowania (WordPress, SMTP, SendGrid, Brevo, Freshmail, GetResponse).
- **Komunikacja** — masowa wysyłka wiadomości e-mail do wybranych grup (ogłoszenia, powiadomienia), z kodami {Nadawca}, {Skrót nazwy}, {moja_grupa}, {grupa_docelowa}.
- **Ankiety** — formularze z pytaniami, zbieranie odpowiedzi, publiczna strona zgłoszeń z opcją ukrywania danych wrażliwych.
- **Wyniki** — zbiorcze wyniki i listy głosujących/nieobecnych, eksport do PDF; tryb jawny lub anonimowy.

---

## Instalacja

1. Pobierz [najnowszy plik ZIP](https://github.com/t7jk/openvote/releases) lub sklonuj repozytorium do `wp-content/plugins/`.
2. W katalogu wtyczki: `composer install` (TCPDF i zależności), `npm run build` w `blocks/openvote-poll/` (blok Gutenberg).
3. W panelu WordPress: Wtyczki → Aktywuj „Open Vote”.
4. Po aktywacji: Open Vote → Konfiguracja — ustaw slug strony głosowania, e-mail nadawcy i metodę wysyłki.

---

## Podręcznik (skrót)

### 1. Przepływ pracy

1. Administrator tworzy grupy i przypisuje użytkowników (ręcznie, „Auto” według pola Miasto, lub przez rejestrację).
2. Administrator lub Koordynator tworzy głosowanie (tytuł, grupy docelowe, pytania i odpowiedzi), potem klika „Wystartuj głosowanie”.
3. System wysyła zaproszenia e-mail; uprawnieni użytkownicy wchodzą na stronę głosowania i oddają głos.
4. Po zakończeniu wyniki są dostępne w zakładce „Zakończone głosowania” oraz w panelu (Wyniki, PDF).

### 2. Role

- **Administrator WordPress** — pełny dostęp, w tym Konfiguracja.
- **Koordynator (rola wtyczki)** — nadawana w Open Vote → Koordynatorzy; zarządza głosowaniami i grupami, bez Konfiguracji.
- **Subskrybent** — może tylko głosować, jeśli jest w grupie docelowej i ma kompletny profil.

### 3. Grupy

- Open Vote → Grupy: dodawanie grup, przypisywanie użytkowników (ręcznie lub „Synchronizuj wszystkie grupy-miasta”).
- Pole „Miasto” (lub inne z Mapowania pól) służy do auto-przypisywania do grup o tej samej nazwie.

### 4. Głosowania

- Tytuł, opis, czas trwania, tryb dołączania (Otwarte/Zamknięte), tryb głosowania (Jawne/Anonimowe), grupy docelowe, pytania i odpowiedzi.
- Statusy: Szkic → Rozpoczęte (po „Wystartuj”) → Zakończone.

### 5. Zaproszenia i limity

- Zaproszenia: przy każdym głosowaniu link „Zaproszenia” — kolejka wysyłki, przycisk „Wyślij zaproszenia” / „Wyślij ponownie”.
- W Konfiguracji (Warunki wysyłki e-maili) ustawia się limity na 15 min, godzinę i dobę dla Brevo (free), WordPress, SMTP. Do limitu wliczane są tylko e-maile faktycznie dostarczone.

### 6. Komunikacja (masowa wysyłka)

- Open Vote → Komunikacja: lista wysyłek (Szkic / Wysłano), „Nowa wysyłka” — tytuł, treść, grupy docelowe.
- Kody w treści: `{Nadawca}`, `{Skrót nazwy}`, `{moja_grupa}`, `{grupa_docelowa}`.
- Wysłanej wiadomości nie można edytować; dostępny jest Podgląd (wyszarzony), Duplikuj, Kasuj.

### 7. Konfiguracja (Administrator)

- Slug strony głosowania, strona ankiet, strona zgłoszeń.
- E-mail nadawcy, metoda wysyłki (WordPress, SMTP, SendGrid, Brevo, Freshmail, GetResponse), warunki wysyłki (limity).
- Mapowanie pól użytkownika (Imię, Nazwisko, Nickname, E-mail, Grupa/Miasto, Telefon itd.) — wymagane do głosowania i do wyświetlania na stronie zgłoszeń.

### 8. Ankiety i zgłoszenia

- Ankiety: Open Vote → Ankiety; tworzenie formularzy, zbieranie odpowiedzi, statusy (oczekuje / nie spam / spam).
- Strona zgłoszeń — publiczny widok zatwierdzonych zgłoszeń; pola oznaczone jako „wrażliwe” są ukrywane.

---

## Komendy deweloperskie

```bash
npm run build        # kompilacja bloku Gutenberg (openvote-poll)
npm run start        # tryb watch
composer install     # zależności PHP (TCPDF)
./make-install-zip.sh # budowa pliku ZIP wtyczki
```

---

## Kod źródłowy

[https://github.com/t7jk/openvote](https://github.com/t7jk/openvote)

Pełny podręcznik użytkownika jest dostępny w panelu WordPress: **Open Vote → Podręcznik**.
