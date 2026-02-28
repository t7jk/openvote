#!/usr/bin/env bash
#
# Tworzy archiwum ZIP do instalacji wtyczki Open Vote w WordPress.
# Wyklucza pliki deweloperskie i tym samym ogranicza problemy z chmod na serwerze.
# Uruchom z katalogu nad openvote (np. Code): ./openvote/make-install-zip.sh
# Lub z katalogu openvote: ./make-install-zip.sh
#

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_DIR="$(basename "$SCRIPT_DIR")"
PARENT_DIR="$(dirname "$SCRIPT_DIR")"
OUTPUT_ZIP="$SCRIPT_DIR/openvote.zip"

cd "$PARENT_DIR"

# Usuń stary zip jeśli istnieje
rm -f "$OUTPUT_ZIP"

zip -r "$OUTPUT_ZIP" "$PLUGIN_DIR" \
  -x "${PLUGIN_DIR}/node_modules/*" \
  -x "${PLUGIN_DIR}/.git/*" \
  -x "${PLUGIN_DIR}/.claude/*" \
  -x "${PLUGIN_DIR}/.cursor/*" \
  -x "${PLUGIN_DIR}/user_base.csv" \
  -x "${PLUGIN_DIR}/*.csv" \
  -x "${PLUGIN_DIR}/*.csv.bak" \
  -x "${PLUGIN_DIR}/*.bak" \
  -x "${PLUGIN_DIR}/local" \
  -x "${PLUGIN_DIR}/local/*" \
  -x "${PLUGIN_DIR}/sync-openvote.sh" \
  -x "${PLUGIN_DIR}/fix-wordpress-permissions.sh" \
  -x "${PLUGIN_DIR}/AGENTS.md" \
  -x "${PLUGIN_DIR}/CLAUDE.md" \
  -x "${PLUGIN_DIR}/wordpress-org-checklist.md" \
  -x "${PLUGIN_DIR}/.gitignore" \
  -x "${PLUGIN_DIR}/openvote.zip" \
  -x "${PLUGIN_DIR}/*.zip" \
  -x "${PLUGIN_DIR}/.DS_Store" \
  -x "${PLUGIN_DIR}/.phpunit.result.cache" \
  -x "*.DS_Store"

echo "Utworzono: $OUTPUT_ZIP"
echo "Zawartość: katalog $PLUGIN_DIR (gotowy do Wtyczki → Dodaj nową → Wgraj wtyczkę)."
