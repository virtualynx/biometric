#!/usr/bin/env bash

set -u

backend_base="${1:-}"
frontend_origin="${2:-}"
failures=0

if [[ -z "$backend_base" ]]; then
    echo "Usage: $0 <backend-base-url> [frontend-origin]" >&2
    exit 2
fi

backend_base="${backend_base%/}"

if [[ ! "$backend_base" =~ ^https:// ]] \
    && [[ ! "$backend_base" =~ ^http://(localhost|127\.0\.0\.1)(:[0-9]+)?(/|$) ]]; then
    echo "FAIL backend URL must use HTTPS outside localhost"
    exit 1
fi

pass() {
    printf 'PASS %s\n' "$1"
}

fail() {
    printf 'FAIL %s\n' "$1"
    failures=$((failures + 1))
}

request_status() {
    curl --silent --show-error --output /dev/null --write-out '%{http_code}' \
        --connect-timeout 5 --max-time 20 "$@"
}

check_blocked_path() {
    local path="$1"
    local status

    if ! status="$(request_status "$backend_base/$path")"; then
        fail "$path could not be checked"
        return
    fi

    case "$status" in
        403|404)
            pass "$path is not publicly readable ($status)"
            ;;
        *)
            fail "$path is publicly reachable (HTTP $status)"
            ;;
    esac
}

for path in \
    '.env' \
    '.git/config' \
    'uploads/' \
    'src/core/Database.php' \
    'scripts/migrate_security.php' \
    'fingerprint_service/coreComponents/enroll.php'; do
    check_blocked_path "$path"
done

response_headers="$(mktemp)"
trap 'rm -f "$response_headers"' EXIT

if auth_status="$(curl --silent --show-error --output /dev/null \
    --dump-header "$response_headers" --write-out '%{http_code}' \
    --connect-timeout 5 --max-time 20 --request POST \
    --header 'Content-Type: application/json' --data '{}' \
    "$backend_base/api/master/location.php")"; then
    if [[ "$auth_status" == '401' ]]; then
        pass 'business API rejects unauthenticated requests (401)'
    else
        fail "business API returned HTTP $auth_status without authentication"
    fi
else
    fail 'business API authentication check could not connect'
fi

if grep -qi '^X-Content-Type-Options:[[:space:]]*nosniff' "$response_headers"; then
    pass 'X-Content-Type-Options is enabled'
else
    fail 'X-Content-Type-Options header is missing'
fi

if grep -qi '^Cache-Control:.*no-store' "$response_headers"; then
    pass 'API responses disable sensitive caching'
else
    fail 'Cache-Control no-store is missing'
fi

if grep -qi '^X-Request-ID:' "$response_headers"; then
    pass 'request correlation ID is enabled'
else
    fail 'X-Request-ID header is missing'
fi

if [[ -n "$frontend_origin" ]]; then
    : > "$response_headers"
    cors_status="$(curl --silent --show-error --output /dev/null \
        --dump-header "$response_headers" --write-out '%{http_code}' \
        --connect-timeout 5 --max-time 20 --request OPTIONS \
        --header "Origin: $frontend_origin" \
        --header 'Access-Control-Request-Method: POST' \
        "$backend_base/api/master/location.php")" || cors_status='000'

    if [[ "$cors_status" =~ ^(200|204)$ ]] \
        && grep -Fqi "Access-Control-Allow-Origin: $frontend_origin" "$response_headers"; then
        pass "CORS accepts the supplied frontend origin ($cors_status)"
    else
        fail "CORS preflight failed for $frontend_origin (HTTP $cors_status)"
    fi
fi

if ((failures > 0)); then
    printf '\nDeployment verification failed with %d issue(s).\n' "$failures"
    exit 1
fi

echo
echo 'Deployment security checks passed.'
