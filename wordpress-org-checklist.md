# Publikacja na WordPress.org — checklist

Przed wysłaniem wtyczki do katalogu [wordpress.org/plugins](https://wordpress.org/plugins/developers/) uzupełnij poniższe.

## 1. Nagłówek wtyczki (`openvote.php`)

* **Plugin URI** i **Author URI** — po zatwierdzeniu wtyczki WordPress.org przypisze adres typu `https://wordpress.org/plugins/TWOJ-SLUG/`. Zaktualizuj oba URI na ten adres (slug podasz przy pierwszym submit).

## 2. Plik `readme.txt`

* **Contributors** — wpisz swój login z WordPress.org (obecnie: `ep-rwl`). Może być kilka osób oddzielonych przecinkami.
* **Stable tag** — musi być równy wersji w nagłówku `Version` w `openvote.php` (np. `1.0.0`). Przy każdej nowej wersji zaktualizuj oba.
* **Tested up to** — ustaw na aktualną wersję WordPress (np. 6.7). Przed wydaniem sprawdź wtyczkę na tej wersji.

## 3. Katalog WordPress.org (SVN)

Po zaakceptowaniu wtyczki:

1. Skopiuj zawartość wtyczki do **trunk** (główny kod).
2. Katalogi **vendor**, **node_modules** i pliki deweloperskie (.git, tests niepotrzebne do działania) — możesz wykluczyć w `.svnignore` lub nie dodawać do repozytorium SVN, jeśli nie chcesz ich w repo. **Uwaga:** Jeśli PDF ma działać „out of the box”, `vendor/` (TCPDF) musi być w paczce; w przeciwnym razie w readme musi być jasno napisane, że do PDF trzeba uruchomić `composer install`.
3. Dla wersji 1.0.0 utwórz tag **1.0.0** w SVN (skopiuj trunk do `tags/1.0.0`). W readme **Stable tag** musi być `1.0.0`.
4. Screenshots — dodaj do **assets** w SVN: `screenshot-1.png`, `screenshot-2.png`, … (opis w sekcji Screenshots w readme). Rozmiar zalecany 1280×720 px.

## 4. Zawartość paczki

* **Dołącz:** `readme.txt`, `LICENSE`, `index.php` (w katalogu głównym i podkatalogach), cały kod PHP/JS/CSS, `languages/*.po`, `languages/*.mo`, ewentualnie `vendor/` jeśli PDF ma działać bez Composer.
* **Nie dołączaj:** `.git`, `.gitignore`, `node_modules`, plików tylko deweloperskich (np. `make-en-us-po.php` można zostawić lub usunąć — nie jest wymagany do działania).

## 5. Wymagania katalogu

* Licencja GPL v2 lub nowsza — **LICENSE** w repozytorium, w nagłówku i w readme.
* Brak złośliwego kodu, zgodność z [Plugin Guidelines](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/).
* Wszystkie stringi w UI przez funkcje tłumaczeń (domain `openvote`) — spełnione.

## 6. Po publikacji

* Zaktualizuj **Plugin URI** i **Author URI** w `openvote.php` na faktyczny adres wtyczki w WordPress.org.
* Przy kolejnych wersjach: podnieś **Version** w `openvote.php`, zaktualizuj **Stable tag** i **Changelog** w `readme.txt`, utwórz nowy tag w SVN (np. `tags/1.0.1`).
