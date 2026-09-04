#!/usr/bin/env bash
# WHY: Compiles the ERP into native mobile applications for iOS and Android.
# Enhances user engagement through push notifications and native biometric authentication.
set -e
echo "📱 Compiling Native Mobile Apps for iOS and Android..."
oshim mobile:build
echo "✅ Mobile compilation complete!"
