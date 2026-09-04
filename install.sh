#!/usr/bin/env bash
# ⚡ OSHIM Sovereign Framework 1-Line Universal Installer
# Usage: curl -fsSL https://oshim.dev/install.sh | bash

set -e

echo "👑 Installing OSHIM Sovereign Universal Framework..."

INSTALL_DIR="${HOME}/.oshim"
BIN_DIR="${HOME}/.local/bin"

if ! command -v php >/dev/null 2>&1; then
    echo "❌ Error: PHP 8.3 or higher is required to run OSHIM."
    exit 1
fi

PHP_VERSION=$(php -r 'echo PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION;')
echo "✔ Detected PHP version: ${PHP_VERSION}"

mkdir -p "${INSTALL_DIR}"
mkdir -p "${BIN_DIR}"

CURRENT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# If running locally from repository
if [ -f "${CURRENT_DIR}/bin/oshim" ]; then
    echo "✔ Linking local OSHIM installation..."
    ln -sf "${CURRENT_DIR}/bin/oshim" "${BIN_DIR}/oshim"
    chmod +x "${BIN_DIR}/oshim"
else
    echo "✔ Downloading latest OSHIM sovereign release..."
    git clone --depth 1 https://github.com/oshim-framework/oshim-framework.git "${INSTALL_DIR}"
    ln -sf "${INSTALL_DIR}/bin/oshim" "${BIN_DIR}/oshim"
    chmod +x "${BIN_DIR}/oshim"
fi

echo ""
echo "🎉 OSHIM Sovereign Framework installed successfully!"
echo "   Binary linked to: ${BIN_DIR}/oshim"
echo ""
echo "🚀 Quickstart commands:"
echo "   $ oshim create my-app --template=saas"
echo "   $ cd my-app && oshim serve"
echo ""
