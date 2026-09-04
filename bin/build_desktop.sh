#!/usr/bin/env bash
# WHY: Bundles the web-based ERP into native zero-dependency desktop executables.
# Provides offline capabilities and hardware integration (e.g. direct POS/printer access).
set -e
echo "🖥️ Bundling Desktop Apps for Windows, macOS, and Linux..."
oshim app:bundle --target=desktop
echo "✅ Desktop bundling complete!"
