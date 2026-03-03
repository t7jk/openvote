#!/usr/bin/env bash
#
# Tworzy archiwum ZIP do instalacji wtyczki Open Vote w WordPress.
# Wyklucza: pliki *.md, *.sh, oraz pliki/katalogi zaczynające się od '.' (dotfiles).
# Uruchom z katalogu nad openvote (np. Code): ./openvote/make-install-zip.sh
# Lub z katalogu openvote: ./make-install-zip.sh
#

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_DIR="$(basename "$SCRIPT_DIR")"
PARENT_DIR="$(dirname "$SCRIPT_DIR")"
OUTPUT_ZIP="$SCRIPT_DIR/openvote-install.zip"

cd "$PARENT_DIR"

# Usuń stary zip jeśli istnieje
rm -f "$OUTPUT_ZIP"

# Lista plików: wykluczamy *.md, *.sh, ścieżki zawierające element zaczynający się od '.' (np. .git, .cursor, .gitignore),
# node_modules, local, pliki csv/bak/zip
find "$PLUGIN_DIR" -type f \
  ! -path '*/node_modules/*' \
  ! -path '*/.git/*' \
  ! -path '*/.claude/*' \
  ! -path '*/.cursor/*' \
  ! -path '*/local' \
  ! -path '*/local/*' \
  ! -path '*/.*' \
  ! -path '*/.*/*' \
  ! -name '*.md' \
  ! -name '*.sh' \
  ! -name '*.csv' \
  ! -name '*.csv.bak' \
  ! -name '*.bak' \
  ! -name '*.zip' \
  -print | zip -r "$OUTPUT_ZIP" -@

echo "Utworzono: $OUTPUT_ZIP"
echo "Zawartość: katalog $PLUGIN_DIR (bez *.md, *.sh, plików '.'). Gotowy do Wtyczki → Dodaj nową → Wgraj wtyczkę."
