#!/bin/bash
#
# Build distribution ZIP for UCP for WooCommerce
# Excludes development files based on .distignore
#

set -e

PLUGIN_SLUG="ucp-for-woocommerce"
# Main plugin file name
PLUGIN_MAIN_FILE="harmonytics-ucp-connector-woocommerce.php"
# Read version from the plugin header in the main file
VERSION=$(grep -oP "Version:\s*\K[0-9.]+" "$PLUGIN_MAIN_FILE" 2>/dev/null || echo "1.0.0")
ZIP_NAME="${PLUGIN_SLUG}-${VERSION}.zip"

# Get script directory (plugin root)
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

# Build from parent directory
cd ..

echo "Building ${ZIP_NAME}..."

# Remove old zip if exists
rm -f "$ZIP_NAME"

# Create zip excluding unwanted files
zip -r "$ZIP_NAME" "$PLUGIN_SLUG" \
    -x "${PLUGIN_SLUG}/.git/*" \
    -x "${PLUGIN_SLUG}/.gitignore" \
    -x "${PLUGIN_SLUG}/.github/*" \
    -x "${PLUGIN_SLUG}/tests/*" \
    -x "${PLUGIN_SLUG}/bin/*" \
    -x "${PLUGIN_SLUG}/vendor/*" \
    -x "${PLUGIN_SLUG}/node_modules/*" \
    -x "${PLUGIN_SLUG}/phpunit.xml" \
    -x "${PLUGIN_SLUG}/phpunit.xml.dist" \
    -x "${PLUGIN_SLUG}/composer.json" \
    -x "${PLUGIN_SLUG}/composer.lock" \
    -x "${PLUGIN_SLUG}/docker-compose*.yml" \
    -x "${PLUGIN_SLUG}/CONTRIBUTING.md" \
    -x "${PLUGIN_SLUG}/.distignore" \
    -x "${PLUGIN_SLUG}/build-zip.sh" \
    -x "${PLUGIN_SLUG}/.idea/*" \
    -x "${PLUGIN_SLUG}/.vscode/*" \
    -x "${PLUGIN_SLUG}/*.code-workspace" \
    -x "${PLUGIN_SLUG}/.DS_Store" \
    -x "${PLUGIN_SLUG}/Thumbs.db" \
    -x "${PLUGIN_SLUG}/*.log" \
    -x "${PLUGIN_SLUG}/*.cache" \
    -x "${PLUGIN_SLUG}/languages/.gitkeep"

echo "Created: $(pwd)/$ZIP_NAME"
echo "Size: $(du -h "$ZIP_NAME" | cut -f1)"

# List contents for verification
echo ""
echo "Contents:"
unzip -l "$ZIP_NAME" | tail -n +4 | head -n -2
