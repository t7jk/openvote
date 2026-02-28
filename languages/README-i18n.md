# Tłumaczenia (i18n)

## Zasada

- **Język WordPress = polski (pl_PL)** → wtyczka wyświetla napisy **po polsku** (stringi z kodu).
- **Język WordPress = inny (np. en_US, de_DE)** → wtyczka ładuje **evoting-en_US.mo** i wyświetla napisy **po angielsku**.

Logika w `includes/class-evoting-i18n.php`: po załadowaniu domeny `evoting` dla bieżącego locale, jeśli locale nie jest polski, doładowywany jest plik `languages/evoting-en_US.mo`.

## Pliki

| Plik | Opis |
|------|------|
| `evoting.pot` | Szablon (źródło stringów) — generowany np. przez `wp i18n make-pot`. |
| `evoting-en_US.po` | Tłumaczenia PL → EN (msgid po polsku, msgstr po angielsku). |
| `evoting-en_US.mo` | Skompilowany plik dla gettext (generowany przez `msgfmt`). |

## Regeneracja angielskiego .po / .mo

1. Uzupełnij lub popraw tłumaczenia w `languages/evoting-en_US.po` (msgstr).
2. Skompiluj:  
   `msgfmt -o languages/evoting-en_US.mo languages/evoting-en_US.po`
3. Opcjonalnie: po zmianie stringów w kodzie wygeneruj ponownie .po z .pot:  
   `php languages/make-en-us-po.php`  
   (skrypt uzupełnia msgstr z wbudowanego słownika PL→EN; brakujące wpisy trzeba dodać ręcznie).

## Dodanie języka (np. niemiecki)

1. Skopiuj `evoting.pot` do `evoting-de_DE.po`.
2. Ustaw w nagłówku: `Language: de_DE`, `Plural-Forms: nplurals=2; plural=(n != 1);`
3. Uzupełnij msgstr po niemiecku.
4. Skompiluj: `msgfmt -o languages/evoting-de_DE.mo languages/evoting-de_DE.po`.
5. W `class-evoting-i18n.php` można dodać ładowanie `evoting-de_DE.mo` gdy locale to `de_DE` (obecnie tylko en_US jest ładowany dla „nie‑polskiego”).
