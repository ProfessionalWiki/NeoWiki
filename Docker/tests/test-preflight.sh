#!/usr/bin/env bash
# Exercise Docker/scripts/preflight.sh. The Docker runtime is faked with a stub
# `docker` whose exit codes/output are set per case via STUB_* env vars, so every
# branch runs through the script's real logic without a real daemon.

set -u

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
SUT="$SCRIPT_DIR/../scripts/preflight.sh"
WORK="$(mktemp -d)"
HOLDERS=()
trap 'for p in "${HOLDERS[@]:-}"; do kill "$p" 2>/dev/null; done; rm -rf "$WORK"' EXIT

PASSES=0
FAILS=0
color() { local c=$1; shift; printf '\033[%sm%s\033[0m\n' "$c" "$*"; }
ok()   { color '32' "PASS: $*"; PASSES=$((PASSES + 1)); }
fail() { color '31' "FAIL: $*"; FAILS=$((FAILS + 1)); }

# A stub `docker`: compose version / info exit per STUB_*; --version echoes STUB_VERSION.
DOCKER_STUB="$WORK/docker"
cat > "$DOCKER_STUB" <<'STUB'
#!/usr/bin/env bash
if [ "${1:-}" = "compose" ] && [ "${2:-}" = "version" ]; then exit "${STUB_COMPOSE_RC:-0}"; fi
if [ "${1:-}" = "info" ]; then exit "${STUB_INFO_RC:-0}"; fi
if [ "${1:-}" = "--version" ]; then echo "${STUB_VERSION:-Docker version 27.0.0, build test}"; exit 0; fi
exit 0
STUB
chmod +x "$DOCKER_STUB"

# Run the SUT against the stub. A high, normally-free port range and an absent
# PODMAN_BIN keep the warning checks quiet during hard-check cases.
run_sut() {
    DOCKER_BIN="$DOCKER_STUB" \
    PODMAN_BIN="${PODMAN_BIN:-neowiki-no-such-podman}" \
    PORT_RANGE_START="${PORT_RANGE_START:-38484}" \
    PORT_RANGE_END="${PORT_RANGE_END:-38499}" \
    STUB_COMPOSE_RC="${STUB_COMPOSE_RC:-0}" \
    STUB_INFO_RC="${STUB_INFO_RC:-0}" \
    STUB_VERSION="${STUB_VERSION:-Docker version 27.0.0}" \
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
color '36' "Case 2: compose v2 missing -> exit 1 + install guidance"
rc=0; out=$(STUB_COMPOSE_RC=1 run_sut 2>&1) || rc=$?
assert_exit "Missing compose fails" 1 "$rc"
assert_contains "Names the compose subcommand" "docker compose" "$out"
assert_contains "Gives an install hint" "docker-compose-v2" "$out"

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
color '36' "Case 5: docker --version reports podman -> engine warning, exit 0"
rc=0; out=$(STUB_VERSION="podman version 4.9.0" run_sut 2>&1) || rc=$?
assert_exit "Podman engine still passes" 0 "$rc"
assert_contains "Warns about podman" "Podman" "$out"

echo
color '36' "Case 6: a podman binary present (Docker --version) -> engine warning"
# PODMAN_BIN=sh stands in for an installed podman binary that `command -v` finds.
rc=0; out=$(PODMAN_BIN="sh" run_sut 2>&1) || rc=$?
assert_exit "Podman-binary misdetection still passes" 0 "$rc"
assert_contains "Warns on stray podman binary" "Podman" "$out"

echo
color '36' "Case 7: entire dev port range occupied -> saturation warning, exit 0"
# Robust holder mirroring test-set-port.sh: a large backlog plus an accept loop so
# the kernel does not RST probes and make is_port_free wrongly report the port free.
# The PID is registered in HOLDERS so the EXIT trap reaps it on interrupt.
hold_port() {
    python3 -c "
import socket, time, sys, threading
s = socket.socket(); s.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
s.bind(('127.0.0.1', $1)); s.listen(128)
def accept_loop():
    while True:
        try:
            conn, _ = s.accept(); conn.close()
        except Exception:
            break
threading.Thread(target=accept_loop, daemon=True).start()
sys.stdout.write('ready\n'); sys.stdout.flush(); time.sleep(60)
" & HELD_PID=$!
    HOLDERS+=("$HELD_PID")
    for _ in 1 2 3 4 5 6 7 8 9 10; do
        (echo > /dev/tcp/127.0.0.1/"$1") 2>/dev/null && return 0; sleep 0.1
    done
    return 1
}
TESTPORT=39191
hold_port "$TESTPORT" || fail "Could not hold test port $TESTPORT (occupied?) — Case 7 result unreliable"
rc=0; out=$(PORT_RANGE_START="$TESTPORT" PORT_RANGE_END="$TESTPORT" run_sut 2>&1) || rc=$?
kill "$HELD_PID" 2>/dev/null; wait "$HELD_PID" 2>/dev/null || true
assert_exit "Saturated range still passes" 0 "$rc"
assert_contains "Warns range is saturated" "No free host port" "$out"

echo
color '36' "Case 8: a free port in range -> no port warning"
rc=0; out=$(PORT_RANGE_START=39192 PORT_RANGE_END=39199 run_sut 2>&1) || rc=$?
assert_exit "Free range passes" 0 "$rc"
if printf '%s' "$out" | grep -qF "No free host port"; then fail "Should not warn when a port is free"; else ok "No spurious port warning"; fi

echo
color '36' "Case 9: verbose mode (make doctor) prints checks + summary"
rc=0; out=$(PREFLIGHT_VERBOSE=1 run_sut 2>&1) || rc=$?
assert_exit "Verbose healthy run passes" 0 "$rc"
assert_contains "Verbose shows the compose check" "Docker Compose v2 available" "$out"
assert_contains "Verbose shows the success summary" "All prerequisites satisfied" "$out"

echo
if [ "$FAILS" -eq 0 ]; then color '32' "All $PASSES checks passed."; exit 0
else color '31' "$FAILS check(s) failed ($PASSES passed)."; exit 1; fi
