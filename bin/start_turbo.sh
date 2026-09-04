#!/usr/bin/env bash
# WHY: Launches the OSHIM Turbo-Rocket Reactor for 500k+ RPS using io_uring SQPOLL.
# This ensures zero downtime and massive concurrency during result publication and admission surges.
set -e
echo "🚀 Starting OSHIM Turbo-Rocket 500k+ RPS Multi-Core Reactor..."
oshim turbo:serve
