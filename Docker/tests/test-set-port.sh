#!/usr/bin/env bash
# Exercise Docker/scripts/set-port.sh against the port-allocation behaviour
# matrix. Each case runs in an isolated ENV_FILE; ports are held with
# in-process python TCP listeners that are torn down after each case.
#
# Uses a high-numbered MW range (PORT_RANGE_START / PORT_RANGE_END) so a busy
# 8484-8499 on the host does not perturb assertions. Mailcatcher range is not
# parameterised by set-port.sh, so MC allocation just happens silently and we
# do not assert on it.

set -u

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
SUT="$SCRIPT_DIR/../scripts/set-port.sh"

# Pick a high range we don't expect anything else to bind on a dev machine.
TEST_RANGE_START=38484
TEST_RANGE_END=38499
OUT_OF_RANGE_PORT=39999

WORK="$(mktemp -d)"
ENV_FILE="$WORK/.env"
HOLDERS=()

PASSES=0
FAILS=0

color() {
    local code=$1; shift
    printf '\033[%sm%s\033[0m\n' "$code" "$*"
}
ok()   { color '32' "PASS: $*"; PASSES=$((PASSES + 1)); }
fail() { color '31' "FAIL: $*"; FAILS=$((FAILS + 1)); }

hold_port() {
    local p=$1
    # Backlog must be large enough AND we must keep accepting to avoid the
    # accept queue filling up and the kernel RSTing subsequent SYNs — which
    # would make is_port_free wrongly report the port as free.
    python3 -c "
import socket, time, sys, threading
s = socket.socket()
s.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
s.bind(('127.0.0.1', $p))
s.listen(128)
def accept_loop():
    while True:
        try:
            conn, _ = s.accept()
            conn.close()
        except Exception:
            break
threading.Thread(target=accept_loop, daemon=True).start()
sys.stdout.write('ready\n'); sys.stdout.flush()
time.sleep(600)
" &
    local pid=$!
    HOLDERS+=("$pid")
    for _ in 1 2 3 4 5 6 7 8 9 10; do
        if ! kill -0 "$pid" 2>/dev/null; then
            return 1
        fi
        if (echo > /dev/tcp/127.0.0.1/"$p") 2>/dev/null; then
            return 0
        fi
        sleep 0.1
    done
    echo "hold_port: gave up waiting for port $p to bind" >&2
    return 1
}

release_all() {
    for pid in "${HOLDERS[@]:-}"; do
        kill "$pid" 2>/dev/null || true
        wait "$pid" 2>/dev/null || true
    done
    HOLDERS=()
}

cleanup() {
    release_all
    rm -rf "$WORK"
}
trap cleanup EXIT

run_sut() {
    local arg=${1:-}
    ENV_FILE="$ENV_FILE" \
    PORT_RANGE_START="$TEST_RANGE_START" \
    PORT_RANGE_END="$TEST_RANGE_END" \
        bash "$SUT" "$arg"
}

reset_env() {
    : > "$ENV_FILE"
    if [ -n "${1:-}" ]; then
        echo "MW_SERVER_PORT=$1" >> "$ENV_FILE"
    fi
}

env_value() {
    grep -E "^$1=" "$ENV_FILE" 2>/dev/null | head -1 | cut -d= -f2-
}

assert_eq() {
    local label=$1 actual=$2 expected=$3
    if [ "$actual" = "$expected" ]; then
        ok "$label (got $actual)"
    else
        fail "$label (expected $expected, got $actual)"
    fi
}

assert_in_range() {
    local label=$1 actual=$2 lo=$3 hi=$4
    if [ -n "$actual" ] && [ "$actual" -ge "$lo" ] && [ "$actual" -le "$hi" ]; then
        ok "$label (got $actual in [$lo..$hi])"
    else
        fail "$label (expected in [$lo..$hi], got '$actual')"
    fi
}

assert_exit_nonzero() {
    local label=$1 rc=$2
    if [ "$rc" -ne 0 ]; then
        ok "$label (exit $rc)"
    else
        fail "$label (expected non-zero exit, got 0)"
    fi
}

# ---- Cases -------------------------------------------------------------------

echo
color '36' "Case 1: empty .env, nothing held -> allocates in-range"
reset_env ""
run_sut "" >/dev/null
assert_in_range "MW port" "$(env_value MW_SERVER_PORT)" "$TEST_RANGE_START" "$TEST_RANGE_END"

echo
color '36' "Case 2: .env=$TEST_RANGE_START baked, that port held by other -> reallocates next free"
reset_env "$TEST_RANGE_START"
hold_port "$TEST_RANGE_START"
run_sut "" >/dev/null
got=$(env_value MW_SERVER_PORT)
if [ "$got" != "$TEST_RANGE_START" ] && [ "$got" -ge "$TEST_RANGE_START" ] && [ "$got" -le "$TEST_RANGE_END" ]; then
    ok "MW port reassigned (got $got)"
else
    fail "MW port should differ from baked $TEST_RANGE_START and stay in range (got '$got')"
fi
release_all

echo
color '36' "Case 3: .env=$((TEST_RANGE_START + 1)) free -> reuses it (no reallocation)"
reset_env "$((TEST_RANGE_START + 1))"
run_sut "" >/dev/null
assert_eq "MW port preserved" "$(env_value MW_SERVER_PORT)" "$((TEST_RANGE_START + 1))"

echo
color '36' "Case 4: .env=$OUT_OF_RANGE_PORT (out-of-range), free -> preserved (deliberate override)"
reset_env "$OUT_OF_RANGE_PORT"
run_sut "" >/dev/null
assert_eq "Out-of-range value preserved" "$(env_value MW_SERVER_PORT)" "$OUT_OF_RANGE_PORT"

echo
color '36' "Case 5: .env=$OUT_OF_RANGE_PORT, held -> exits non-zero, does not overwrite"
reset_env "$OUT_OF_RANGE_PORT"
hold_port "$OUT_OF_RANGE_PORT"
set +e
err_output=$(run_sut "" 2>&1)
rc=$?
set -e
assert_exit_nonzero "Held out-of-range value errors" "$rc"
assert_eq "Out-of-range value untouched on error" "$(env_value MW_SERVER_PORT)" "$OUT_OF_RANGE_PORT"
if echo "$err_output" | grep -q "outside the auto-allocation range"; then
    ok "Error message mentions auto-allocation range"
else
    fail "Error message should mention range. Got: $err_output"
fi
release_all

echo
color '36' "Case 6: explicit port=$((TEST_RANGE_START + 5)) free -> writes it"
reset_env ""
run_sut "$((TEST_RANGE_START + 5))" >/dev/null
assert_eq "Explicit port written" "$(env_value MW_SERVER_PORT)" "$((TEST_RANGE_START + 5))"

echo
color '36' "Case 7: explicit port=$((TEST_RANGE_START + 6)) held -> exits non-zero, does not overwrite"
reset_env ""
hold_port "$((TEST_RANGE_START + 6))"
set +e
err_output=$(run_sut "$((TEST_RANGE_START + 6))" 2>&1)
rc=$?
set -e
assert_exit_nonzero "Held explicit port errors" "$rc"
assert_eq "No .env write on explicit error" "$(env_value MW_SERVER_PORT)" ""
if echo "$err_output" | grep -q "already in use"; then
    ok "Error message mentions in-use"
else
    fail "Error message should mention in-use. Got: $err_output"
fi
release_all

# ---- Summary -----------------------------------------------------------------

echo
if [ "$FAILS" -eq 0 ]; then
    color '32' "All $PASSES checks passed."
    exit 0
else
    color '31' "$FAILS check(s) failed ($PASSES passed)."
    exit 1
fi
