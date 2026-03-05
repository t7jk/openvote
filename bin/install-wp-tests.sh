#!/usr/bin/env bash
# install-wp-tests.sh
#
# Downloads WordPress core and the WP test suite into /tmp, then creates the
# test database.  Mirrors the canonical script shipped with wordpress-develop.
#
# Usage:
#   bash bin/install-wp-tests.sh <db-name> <db-user> <db-pass> [db-host] [wp-version]
#
# Example:
#   bash bin/install-wp-tests.sh openvote_test root password localhost latest

set -e

DB_NAME="${1:-openvote_test}"
DB_USER="${2:-root}"
DB_PASS="${3:-}"
DB_HOST="${4:-localhost}"
WP_VERSION="${5:-latest}"

WP_TESTS_DIR="${WP_TESTS_DIR:-/tmp/wordpress-tests-lib}"
WP_CORE_DIR="${WP_CORE_DIR:-/tmp/wordpress}"

download() {
    if command -v curl >/dev/null 2>&1; then
        curl -s "$1" >"$2"
    elif command -v wget >/dev/null 2>&1; then
        wget -nv -O "$2" "$1"
    else
        echo "Error: curl or wget is required." >&2
        exit 1
    fi
}

# ── Resolve WP version ──────────────────────────────────────────────────────

if [ "$WP_VERSION" = "latest" ]; then
    local_version_file="$WP_CORE_DIR/wp-includes/version.php"
    if [ -f "$local_version_file" ]; then
        WP_VERSION=$(grep "wp_version = " "$local_version_file" | sed "s/.*wp_version = '\(.*\)'.*/\1/")
    fi
    if [ -z "$WP_VERSION" ] || [ "$WP_VERSION" = "latest" ]; then
        download "https://api.wordpress.org/core/version-check/1.7/" /tmp/wp-latest.json
        WP_VERSION=$(grep -o '"version":"[^"]*"' /tmp/wp-latest.json | head -1 | tr -d '"version:')
        WP_VERSION=${WP_VERSION#version\"}
        WP_VERSION=$(echo "$WP_VERSION" | grep -oP '[\d.]+' | head -1)
    fi
fi

WP_TESTS_TAG="tags/$WP_VERSION"
if [[ $WP_VERSION =~ ^([0-9]+)\.([0-9]+)$ ]]; then
    LATEST_MINOR=$(
        download "https://api.wordpress.org/core/version-check/1.7/" /dev/stdout 2>/dev/null \
        | grep -o '"version":"[0-9]*\.[0-9]*\.[0-9]*"' \
        | head -1 \
        | grep -o '[0-9]*\.[0-9]*\.[0-9]*'
    )
    if [[ "${LATEST_MINOR%.*}" == "$WP_VERSION" ]]; then
        WP_VERSION="$LATEST_MINOR"
        WP_TESTS_TAG="tags/$WP_VERSION"
    else
        WP_TESTS_TAG="branches/$WP_VERSION"
    fi
fi

# ── Download WP core ────────────────────────────────────────────────────────

if [ ! -d "$WP_CORE_DIR/wp-includes" ]; then
    mkdir -p "$WP_CORE_DIR"

    if [ "$WP_VERSION" = "nightly" ] || [ "$WP_VERSION" = "trunk" ]; then
        download "https://wordpress.org/nightly-builds/wordpress-latest.zip" /tmp/wp.zip
    else
        download "https://wordpress.org/wordpress-$WP_VERSION.zip" /tmp/wp.zip
    fi

    unzip -q /tmp/wp.zip -d /tmp/
    mv /tmp/wordpress/* "$WP_CORE_DIR"
    rmdir /tmp/wordpress
fi

# ── Download test suite ─────────────────────────────────────────────────────

if [ ! -d "$WP_TESTS_DIR/includes" ]; then
    mkdir -p "$WP_TESTS_DIR"
    svn export --quiet "https://develop.svn.wordpress.org/${WP_TESTS_TAG}/tests/phpunit/includes/" "$WP_TESTS_DIR/includes"
    svn export --quiet "https://develop.svn.wordpress.org/${WP_TESTS_TAG}/tests/phpunit/data/"     "$WP_TESTS_DIR/data"
fi

# ── Write wp-tests-config.php ───────────────────────────────────────────────

PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
CONFIG_FILE="$PLUGIN_DIR/wp-tests-config.php"

if [ ! -f "$CONFIG_FILE" ]; then
    cp "$PLUGIN_DIR/wp-tests-config-sample.php" "$CONFIG_FILE"
    sed -i "s/'openvote_test'/'$DB_NAME'/"   "$CONFIG_FILE"
    sed -i "s/'root'/'$DB_USER'/"            "$CONFIG_FILE"
    sed -i "s/'password'/'$DB_PASS'/"        "$CONFIG_FILE"
    sed -i "s/'localhost'/'$DB_HOST'/"       "$CONFIG_FILE"
    echo "Created wp-tests-config.php with provided credentials."
fi

# ── Create test database ────────────────────────────────────────────────────

EXTRA=""
if [ -n "$DB_PASS" ]; then
    EXTRA="-p$DB_PASS"
fi

mysqladmin create "$DB_NAME" --user="$DB_USER" "$EXTRA" --host="$DB_HOST" 2>/dev/null || true

# ── Install WP ───────────────────────────────────────────────────────────────

if [ ! -f "$WP_CORE_DIR/wp-config.php" ]; then
    php -d disable_functions="" "$WP_CORE_DIR/wp-admin/install.php" \
        --url="http://localhost/" \
        --title="Test" \
        --admin_user="admin" \
        --admin_password="admin" \
        --admin_email="admin@example.org" \
        --skip-email 2>/dev/null || true
fi

# ── Write WP test suite bootstrap config ────────────────────────────────────

if [ ! -f "$WP_TESTS_DIR/wp-tests-config.php" ]; then
    cp "$CONFIG_FILE" "$WP_TESTS_DIR/wp-tests-config.php"
fi

echo "WP $WP_VERSION test environment ready."
echo "  WP core:    $WP_CORE_DIR"
echo "  Test suite: $WP_TESTS_DIR"
echo "  Test DB:    $DB_NAME @ $DB_HOST"
