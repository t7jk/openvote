#!/usr/bin/env bash
#
# Tworzy archiwum ZIP do instalacji wtyczki OpenVote w WordPress.
#
# Strategia:
#   1. Kopiuje pliki produkcyjne do katalogu tymczasowego (rsync z wykluczeniami).
#   2. Uruchamia `composer install --no-dev` w tym katalogu — generuje czysty
#      autoloader bez pakietów deweloperskich (PHPUnit, Brain Monkey, Mockery itp.).
#   3. Pakuje katalog tymczasowy do ZIP-a.
#
# Wymagania: bash, rsync, composer, zip
# Uruchomienie: ./make-install-zip.sh  (z dowolnego miejsca)
#

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_NAME="openvote"
OUTPUT_ZIP="$SCRIPT_DIR/openvote-install.zip"
TMP_DIR="$(mktemp -d)"
STAGE="$TMP_DIR/$PLUGIN_NAME"

cleanup() { rm -rf "$TMP_DIR"; }
trap cleanup EXIT

mkdir -p "$STAGE"

# ── 1. Kopiuj pliki produkcyjne ──────────────────────────────────────────────
#
# Wykluczamy:
#   - katalogi deweloperskie / narzędziowe: .git, .claude, .cursor, node_modules,
#     local, bin, tests, blocks/*/src
#   - pliki konfiguracyjne tylko dla dev/build: composer.lock (używany tylko przez
#     krok 2 poniżej), package*.json, phpunit.xml, wp-tests-config*.php
#   - pliki AI / IDE: CLAUDE.md, .phpunit.result.cache, *.cache
#   - dotfiles: .gitignore, .editorconfig itp.
#   - pliki tymczasowe / budowania: *.md (README), *.sh (skrypty), *.zip,
#     *.csv, *.bak, *.log
#   - cały vendor/ — zainstalujemy produkcyjną wersję w kroku 2

rsync -a \
  --exclude='.git/' \
  --exclude='.claude/' \
  --exclude='.cursor/' \
  --exclude='.github/' \
  --exclude='node_modules/' \
  --exclude='local/' \
  --exclude='bin/' \
  --exclude='tests/' \
  --exclude='ftp-upgrade/' \
  --exclude='blocks/openvote-poll/src/' \
  --exclude='vendor/' \
  --exclude='package.json' \
  --exclude='package-lock.json' \
  --exclude='phpunit.xml' \
  --exclude='wp-tests-config-sample.php' \
  --exclude='.*' \
  --exclude='*.md' \
  --exclude='*.sh' \
  --exclude='*.zip' \
  --exclude='*.csv' \
  --exclude='*.bak' \
  --exclude='*.log' \
  --exclude='*.cache' \
  "$SCRIPT_DIR/" "$STAGE/"

# ── 2. Zainstaluj produkcyjne zależności (tylko tecnickcom/tcpdf) ─────────────
#
# composer.json został skopiowany; lock file kopiujemy teraz żeby install był
# deterministyczny, a po instalacji go usuwamy (nie trafia do ZIP-a).

cp "$SCRIPT_DIR/composer.lock" "$STAGE/composer.lock"

composer install \
  --no-dev \
  --optimize-autoloader \
  --no-interaction \
  --quiet \
  --working-dir="$STAGE"

# composer.json i composer.lock nie są potrzebne na serwerze produkcyjnym
rm "$STAGE/composer.json" "$STAGE/composer.lock"

# ── 3. Pakuj ─────────────────────────────────────────────────────────────────

rm -f "$OUTPUT_ZIP"

cd "$TMP_DIR"
zip -r "$OUTPUT_ZIP" "$PLUGIN_NAME" -x "*.DS_Store" -x "__MACOSX/*"

echo "Utworzono: $OUTPUT_ZIP"
echo "Zawartość: katalog $PLUGIN_NAME (tylko pliki produkcyjne, vendor bez dev-deps)."
echo "Gotowy do: Wtyczki → Dodaj nową → Wgraj wtyczkę."
echo "W razie błędów chmod() na serwerze — patrz INSTALL.txt w paczce."
