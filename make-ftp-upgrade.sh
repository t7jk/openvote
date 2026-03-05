#!/usr/bin/env bash
#
# Przygotowuje katalog ftp-upgrade/ z plikami produkcyjnymi gotowymi do
# ręcznego wgrania przez FTP (np. FileZilla).
#
# Użycie w FileZilla:
#   Źródło:  <ten katalog>/ftp-upgrade/
#   Cel:     wp-content/plugins/openvote/   (na serwerze)
#   Opcja:   "Pomiń pliki identyczne (taka sama data i rozmiar)"
#
# Skrypt robi to samo co make-install-zip.sh, ale zamiast ZIP-a
# zostawia katalog gotowy do synchronizacji FTP.
#
# Wymagania: bash, rsync, composer
# Uruchomienie: ./make-ftp-upgrade.sh
#

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
OUTPUT_DIR="$SCRIPT_DIR/ftp-upgrade"
TMP_DIR="$(mktemp -d)"

cleanup() { rm -rf "$TMP_DIR"; }
trap cleanup EXIT

# ── 1. Kopiuj pliki produkcyjne do katalogu tymczasowego ────────────────────

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
  "$SCRIPT_DIR/" "$TMP_DIR/"

# ── 2. Zainstaluj produkcyjne zależności (tylko tecnickcom/tcpdf) ─────────────

cp "$SCRIPT_DIR/composer.lock" "$TMP_DIR/composer.lock"

composer install \
  --no-dev \
  --optimize-autoloader \
  --no-interaction \
  --quiet \
  --working-dir="$TMP_DIR"

rm "$TMP_DIR/composer.json" "$TMP_DIR/composer.lock"

# ── 3. Podmień ftp-upgrade/ na nową wersję ───────────────────────────────────
#
# Używamy rsync z --delete żeby usunąć pliki, które zniknęły ze źródła
# (np. stare klasy, usunięte assets). FileZilla nie usuwa plików na serwerze,
# więc to tylko lokalna synchronizacja katalogu tymczasowego.

mkdir -p "$OUTPUT_DIR"
rsync -a --delete "$TMP_DIR/" "$OUTPUT_DIR/"

echo "Gotowe: $OUTPUT_DIR"
echo ""
echo "Jak wgrać przez FileZilla:"
echo "  Źródło : $OUTPUT_DIR/"
echo "  Cel    : wp-content/plugins/openvote/"
echo "  Opcja  : Transfer → Pomiń pliki identyczne (taka sama data i rozmiar)"
