#!/usr/bin/env bash
# Exercise Docker/scripts/preflight.sh. The Docker runtime is faked with a stub
# `docker` whose exit codes are set per case via STUB_* env vars, so every
# branch runs through the script's real logic without a real daemon.

set -u

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
SUT="$SCRIPT_DIR/../scripts/preflight.sh"
WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT

PASSES=0
FAILS=0
color() { local c=$1; shift; printf '\033[%sm%s\033[0m\n' "$c" "$*"; }
ok()   { color '32' "PASS: $*"; PASSES=$((PASSES + 1)); }
fail() { color '31' "FAIL: $*"; FAILS=$((FAILS + 1)); }

# A stub `docker`: compose version / info exit per STUB_*.
DOCKER_STUB="$WORK/docker"
cat > "$DOCKER_STUB" <<'STUB'
#!/usr/bin/env bash
if [ "${1:-}" = "compose" ] && [ "${2:-}" = "version" ]; then exit "${STUB_COMPOSE_RC:-0}"; fi
if [ "${1:-}" = "info" ]; then exit "${STUB_INFO_RC:-0}"; fi
exit 0
STUB
chmod +x "$DOCKER_STUB"

# Run the SUT against the stub.
run_sut() {
    DOCKER_BIN="${DOCKER_BIN:-$DOCKER_STUB}" \
    STUB_COMPOSE_RC="${STUB_COMPOSE_RC:-0}" \
    STUB_INFO_RC="${STUB_INFO_RC:-0}" \
        bash "$SUT"
}

assert_exit() {
    local label=$1 expected=$2 actual=$3
    if [ "$actual" = "$expected" ]; then ok "$label (exit $actual)"; else fail "$label (expected exit $expected, got $actual)"; fi
}
assert_contains() {
    local label=$1 needle=$2 haystack=$3
    if printf '%s' "$haystack" | grep -qF "$needle"; then ok "$label"; else fail "$label (missing: $needle)"; fi
}

echo
color '36' "Case 1: healthy runtime -> exit 0, silent in non-verbose mode"
rc=0; out=$(run_sut 2>/dev/null) || rc=$?
assert_exit "Healthy runtime passes" 0 "$rc"
if [ -z "$out" ]; then ok "Non-verbose success is silent"; else fail "Non-verbose success should be silent (got: $out)"; fi

echo
color '36' "Case 2: docker compose missing -> exit 1 + install guidance"
rc=0; out=$(STUB_COMPOSE_RC=1 run_sut 2>&1) || rc=$?
assert_exit "Missing compose fails" 1 "$rc"
assert_contains "Names the compose command" "docker compose" "$out"
assert_contains "Links the install docs" "docs.docker.com/compose/install" "$out"

echo
color '36' "Case 3: daemon unreachable -> exit 1 + daemon guidance"
rc=0; out=$(STUB_INFO_RC=1 run_sut 2>&1) || rc=$?
assert_exit "Unreachable daemon fails" 1 "$rc"
assert_contains "Mentions the daemon" "daemon" "$out"

echo
color '36' "Case 4: compose AND daemon both broken -> exit 1, reports both"
rc=0; out=$(STUB_COMPOSE_RC=1 STUB_INFO_RC=1 run_sut 2>&1) || rc=$?
assert_exit "Both broken fails" 1 "$rc"
assert_contains "Reports compose failure" "docker compose" "$out"
assert_contains "Reports daemon failure" "daemon" "$out"

echo
color '36' "Case 5: verbose mode (make doctor) prints checks + summary"
rc=0; out=$(PREFLIGHT_VERBOSE=1 run_sut 2>&1) || rc=$?
assert_exit "Verbose healthy run passes" 0 "$rc"
assert_contains "Verbose shows the compose check" "Docker Compose available" "$out"
assert_contains "Verbose shows the success summary" "All prerequisites satisfied" "$out"

echo
color '36' "Case 6: docker binary absent -> install-Docker message, compose/daemon skipped"
rc=0; out=$(DOCKER_BIN="neowiki-no-such-docker" run_sut 2>&1) || rc=$?
assert_exit "Absent docker fails" 1 "$rc"
assert_contains "Says Docker was not found" "Docker (or a compatible runtime" "$out"
assert_contains "Links the get-docker docs" "docs.docker.com/get-docker" "$out"
if printf '%s' "$out" | grep -qF "docker compose"; then fail "Compose check should be skipped when docker is absent"; else ok "Compose/daemon checks skipped when docker absent"; fi

echo
if [ "$FAILS" -eq 0 ]; then color '32' "All $PASSES checks passed."; exit 0
else color '31' "$FAILS check(s) failed ($PASSES passed)."; exit 1; fi
