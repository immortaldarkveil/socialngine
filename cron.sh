#!/bin/bash

# =============================================================================
# Socialngine Cron Job Runner
# =============================================================================
#
# Usage (local development):
#   bash cron.sh
#
# Usage (production - add to crontab):
#   * * * * * /path/to/cron.sh >> /path/to/cron.log 2>&1
#
# Each endpoint uses file-based locking to prevent overlapping executions.
# =============================================================================

# Configuration - update these for your environment
BASE_URL="${SOCIALNGINE_URL:-http://socialngine.test}"
LOG_FILE="${SOCIALNGINE_CRON_LOG:-/dev/null}"

echo "[$(date '+%Y-%m-%d %H:%M:%S')] Cron started" | tee -a "$LOG_FILE"

# 1. Process pending orders — sends orders to API providers
echo -n "[order] " | tee -a "$LOG_FILE"
curl -s "${BASE_URL}/cron/order" | tee -a "$LOG_FILE"
echo | tee -a "$LOG_FILE"

# 2. Check multi-status — batch status check for efficiency
echo -n "[multi_status] " | tee -a "$LOG_FILE"
curl -s "${BASE_URL}/cron/multiple_status" | tee -a "$LOG_FILE"
echo | tee -a "$LOG_FILE"

# 3. Process drip feed orders
echo -n "[dripfeed] " | tee -a "$LOG_FILE"
curl -s "${BASE_URL}/cron/dripfeed" | tee -a "$LOG_FILE"
echo | tee -a "$LOG_FILE"

# 4. Process subscription orders
echo -n "[subscriptions] " | tee -a "$LOG_FILE"
curl -s "${BASE_URL}/cron/subscriptions" | tee -a "$LOG_FILE"
echo | tee -a "$LOG_FILE"

echo "[$(date '+%Y-%m-%d %H:%M:%S')] Cron completed" | tee -a "$LOG_FILE"
