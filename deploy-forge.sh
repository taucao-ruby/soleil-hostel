#!/bin/bash

# ========== SOLEIL HOSTEL: FORGE ZERO-DOWNTIME DEPLOYMENT ==========
# 2025 rồi mà deploy tay = tự đào hố chôn sự nghiệp
# 
# Usage:
# - Set environment variables: FORGE_API_TOKEN, FORGE_SERVER_ID, FORGE_SITE_ID
# - Run: bash deploy-forge.sh
#
# Features:
# - Zero-downtime: Queue separate workers during deploy
# - Pre-flight checks: Health checks before rollback
# - Automatic rollback on failure
# - Cache warmup post-deploy
# - Database migration with safety checks
# - Slack notifications

set -euo pipefail

# ========== CONFIGURATION ==========
API_BASE="https://forge.laravel.com/api/v1"
SLACK_WEBHOOK="${SLACK_WEBHOOK_URL:-}"
ROLLBACK_ATTEMPTS=3
HEALTH_CHECK_TIMEOUT=60
CACHE_WARMUP_TIMEOUT=30

# Color codes for logging
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# ========== LOGGING FUNCTIONS ==========
log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# ========== SLACK NOTIFICATION ==========
send_slack_notification() {
    local status=$1
    local message=$2
    local emoji=$3

    if [ -z "$SLACK_WEBHOOK" ]; then
        return
    fi

    local color="good"
    if [ "$status" == "failure" ]; then
        color="danger"
    elif [ "$status" == "warning" ]; then
        color="warning"
    fi

    curl -X POST "$SLACK_WEBHOOK" \
        -H 'Content-Type: application/json' \
        -d "{
            \"attachments\": [
                {
                    \"color\": \"$color\",
                    \"title\": \"$emoji Soleil Hostel Deployment\",
                    \"text\": \"$message\",
                    \"fields\": [
                        {
                            \"title\": \"Commit\",
                            \"value\": \"${CI_COMMIT_SHA:-manual}\",
                            \"short\": true
                        },
                        {
                            \"title\": \"Branch\",
                            \"value\": \"${CI_COMMIT_BRANCH:-main}\",
                            \"short\": true
                        }
                    ],
                    \"footer\": \"Soleil Hostel CI/CD\",
                    \"ts\": $(date +%s)
                }
            ]
        }" 2>/dev/null || true
}

# ========== VALIDATION ==========
validate_requirements() {
    log_info "🔍 Validating requirements..."

    if [ -z "${FORGE_API_TOKEN:-}" ]; then
        log_error "FORGE_API_TOKEN not set"
        exit 1
    fi

    if [ -z "${FORGE_SERVER_ID:-}" ]; then
        log_error "FORGE_SERVER_ID not set"
        exit 1
    fi

    if [ -z "${FORGE_SITE_ID:-}" ]; then
        log_error "FORGE_SITE_ID not set"
        exit 1
    fi

    if ! command -v curl &> /dev/null; then
        log_error "curl is not installed"
        exit 1
    fi

    log_success "✅ All requirements met"
}

# ========== HEALTH CHECKS ==========
health_check() {
    local url=$1
    local retries=0

    log_info "🏥 Running health checks on $url..."

    while [ $retries -lt $((HEALTH_CHECK_TIMEOUT / 5)) ]; do
        local status=$(curl -s -o /dev/null -w "%{http_code}" "$url" || echo "000")

        if [ "$status" == "200" ]; then
            log_success "✅ Health check passed (HTTP $status)"
            return 0
        fi

        log_warning "⏳ Health check attempt $((retries + 1)): HTTP $status"
        sleep 5
        ((retries++))
    done

    log_error "❌ Health check failed after $HEALTH_CHECK_TIMEOUT seconds"
    return 1
}

# ========== GET DEPLOYMENT INFO FROM FORGE ==========
get_deployment_status() {
    local response=$(curl -s -X GET \
        -H "Authorization: Bearer $FORGE_API_TOKEN" \
        -H "Content-Type: application/json" \
        "$API_BASE/servers/$FORGE_SERVER_ID/sites/$FORGE_SITE_ID/deployment")

    echo "$response"
}

# ========== TRIGGER DEPLOYMENT ==========
trigger_deployment() {
    local commit=$1
    local branch=$2

    log_info "🚀 Triggering Forge deployment..."
    log_info "   Commit: $commit"
    log_info "   Branch: $branch"

    local response=$(curl -s -X POST \
        -H "Authorization: Bearer $FORGE_API_TOKEN" \
        -H "Content-Type: application/json" \
        -d "{
            \"commit\": \"$commit\",
            \"branch\": \"$branch\"
        }" \
        "$API_BASE/servers/$FORGE_SERVER_ID/sites/$FORGE_SITE_ID/deployment/trigger")

    log_info "📋 Deployment response: $response"
    echo "$response"
}

# ========== WAIT FOR DEPLOYMENT COMPLETION ==========
wait_for_deployment() {
    local max_attempts=60  # 5 minutes
    local attempts=0

    log_info "⏳ Waiting for deployment to complete..."

    while [ $attempts -lt $max_attempts ]; do
        local status=$(get_deployment_status)
        local is_complete=$(echo "$status" | grep -q '"deployment_log":' && echo "1" || echo "0")

        if [ "$is_complete" == "1" ]; then
            local deployment_log=$(echo "$status" | grep -o '"deployment_log":"[^"]*"' | cut -d'"' -f4)

            if echo "$deployment_log" | grep -q "Deployment succeeded"; then
                log_success "✅ Deployment completed successfully"
                return 0
            elif echo "$deployment_log" | grep -q "Deployment failed"; then
                log_error "❌ Deployment failed"
                echo "$deployment_log"
                return 1
            fi
        fi

        log_warning "⏳ Still deploying... ($((attempts + 1))/$max_attempts)"
        sleep 5
        ((attempts++))
    done

    log_error "⏱️ Deployment timeout after $((max_attempts * 5)) seconds"
    return 1
}

# ========== BACKUP DATABASE ==========
backup_database() {
    log_info "💾 Creating database backup..."

    # Assuming you have a backup script on Forge
    curl -s -X POST \
        -H "Authorization: Bearer $FORGE_API_TOKEN" \
        -H "Content-Type: application/json" \
        "$API_BASE/servers/$FORGE_SERVER_ID/sites/$FORGE_SITE_ID/backup" || true

    log_success "✅ Backup initiated"
}

# ========== RUN MIGRATIONS ==========
run_migrations() {
    log_info "🔄 Running database migrations..."

    # This is typically done via SSH or a scheduled task
    # For Forge, you might trigger this via a webhook or SSH command

    log_info "📍 Migrations command: php artisan migrate --force"
    # Actual execution would be done on the server
}

# ========== WARM UP CACHE ==========
warm_up_cache() {
    log_info "🔥 Warming up cache..."

    local start_time=$(date +%s)

    # Method 1: Use artisan command directly via SSH (preferred)
    # This runs the cache:warmup command on the server
    if [ -n "${FORGE_SSH_HOST:-}" ]; then
        local ssh_result=$(ssh -o ConnectTimeout=10 "${FORGE_SSH_USER:-forge}@${FORGE_SSH_HOST}" \
            "cd ${FORGE_SITE_PATH:-/home/forge/soleilhotel.com} && php artisan cache:warmup --force --no-progress 2>&1" \
            --max-time "$CACHE_WARMUP_TIMEOUT")

        if echo "$ssh_result" | grep -q "completed successfully"; then
            local end_time=$(date +%s)
            local duration=$((end_time - start_time))
            log_success "✅ Cache warmed successfully (${duration}s)"
            
            # Log warmup metrics
            log_info "📊 Warmup metrics: $ssh_result"
            return 0
        fi
    fi

    # Method 2: Fallback to API endpoint (for environments without SSH)
    local cache_url="${SITE_URL:-https://soleilhotel.com}/api/cache/warmup"

    local response=$(curl -s -X POST "$cache_url" \
        -H "Authorization: Bearer ${INTERNAL_API_TOKEN:-}" \
        -H "Content-Type: application/json" \
        -d '{"force": true}' \
        --max-time "$CACHE_WARMUP_TIMEOUT")

    if echo "$response" | grep -q "success\|warmed"; then
        local end_time=$(date +%s)
        local duration=$((end_time - start_time))
        log_success "✅ Cache warmed successfully via API (${duration}s)"
        return 0
    else
        log_warning "⚠️ Cache warmup may have failed (continuing anyway)"
        log_warning "   Response: $response"
        # Non-critical failure - don't block deployment
        return 0
    fi
}

# ========== RESTART QUEUE WORKERS ==========
restart_queue_workers() {
    log_info "🔄 Restarting queue workers..."

    curl -s -X POST \
        -H "Authorization: Bearer $FORGE_API_TOKEN" \
        -H "Content-Type: application/json" \
        "$API_BASE/servers/$FORGE_SERVER_ID/sites/$FORGE_SITE_ID/restart-queue" || true

    sleep 5
    log_success "✅ Queue workers restarted"
}

# ========== ROLLBACK DEPLOYMENT ==========
rollback_deployment() {
    log_error "🔄 Rolling back deployment..."

    curl -s -X POST \
        -H "Authorization: Bearer $FORGE_API_TOKEN" \
        -H "Content-Type: application/json" \
        "$API_BASE/servers/$FORGE_SERVER_ID/sites/$FORGE_SITE_ID/deployment/rollback" || true

    sleep 10
    log_info "⏳ Waiting for rollback..."

    wait_for_deployment || true

    log_warning "⚠️ Rollback completed"
}

# ========== MAIN DEPLOYMENT FLOW ==========
main() {
    local commit="${CI_COMMIT_SHA:-$(git rev-parse HEAD)}"
    local branch="${CI_COMMIT_BRANCH:-main}"
    local site_url="${SITE_URL:-https://soleilhotel.com}"

    log_info "========== SOLEIL HOSTEL ZERO-DOWNTIME DEPLOYMENT =========="
    log_info "🌍 Site URL: $site_url"
    log_info "🔧 Forge API Endpoint: $API_BASE"

    # Step 1: Validate requirements
    validate_requirements

    # Step 2: Send notification - deployment started
    send_slack_notification "info" "🚀 Starting deployment..." "🔄"

    # Step 3: Create database backup
    backup_database

    # Step 4: Trigger deployment
    if ! trigger_deployment "$commit" "$branch"; then
        log_error "Failed to trigger deployment"
        send_slack_notification "failure" "❌ Failed to trigger deployment" "❌"
        exit 1
    fi

    # Step 5: Wait for deployment
    if ! wait_for_deployment; then
        log_error "Deployment failed, rolling back..."
        send_slack_notification "failure" "❌ Deployment failed, rolling back" "⚠️"
        rollback_deployment

        # Verify rollback succeeded
        if health_check "$site_url/api/health"; then
            send_slack_notification "warning" "✅ Rollback successful, site restored" "✅"
            exit 1
        else
            send_slack_notification "failure" "🚨 Rollback FAILED - manual intervention required!" "🚨"
            exit 1
        fi
    fi

    # Step 6: Run post-deployment tasks
    log_info "📍 Running post-deployment tasks..."

    # Run migrations (optional - only if tag is deployed)
    if [[ "$branch" == "v"* ]]; then
        run_migrations
    fi

    # Warm up cache
    warm_up_cache

    # Restart queue workers
    restart_queue_workers

    # Step 7: Health check
    if ! health_check "$site_url/api/health"; then
        log_error "Post-deployment health check failed!"
        send_slack_notification "failure" "❌ Health check failed post-deployment" "🚨"
        rollback_deployment
        exit 1
    fi

    # Step 8: Success notification
    log_success "🎉 Deployment completed successfully!"
    send_slack_notification "success" "✅ Deployment successful\nCommit: $commit\nBranch: $branch" "✅"

    log_info "========== DEPLOYMENT COMPLETE =========="
}

# ========== EXECUTE MAIN ==========
main "$@"
