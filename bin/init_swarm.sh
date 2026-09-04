#!/usr/bin/env bash
# WHY: Initializes the Sovereign Swarm Cluster for high availability.
# Allows multiple servers to join the network and distribute load autonomously.
set -e
echo "🐝 Initializing OSHIM Sovereign Swarm Cluster as Leader..."
oshim swarm:init
