#!/usr/bin/env bash
# 👑 OSHIM Sovereign Framework Global Native Installer
# Installs `oshim` as a native system command (like node, python, go)

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
FRAMEWORK_DIR="$(dirname "$SCRIPT_DIR")"
OSHIM_BIN="$SCRIPT_DIR/oshim"

chmod +x "$OSHIM_BIN"

TARGET_DIR="/usr/local/bin"
if [ ! -w "$TARGET_DIR" ]; then
    TARGET_DIR="$HOME/.local/bin"
    mkdir -p "$TARGET_DIR"
fi

TARGET_BIN="$TARGET_DIR/oshim"

ln -sf "$OSHIM_BIN" "$TARGET_BIN"

echo "=================================================="
echo "👑 OSHIM Sovereign Native CLI Installed Successfully!"
echo "Binary linked to: $TARGET_BIN"
echo "Framework root:   $FRAMEWORK_DIR"
echo "=================================================="
echo "You can now open ANY blank folder anywhere and type:"
echo "  $ oshim make:crud Product"
echo "  $ oshim serve"
echo "  $ oshim turbo:serve"
echo "Zero engine folders, zero vendor folders needed in your app!"
echo "=================================================="
