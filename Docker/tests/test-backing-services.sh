#!/usr/bin/env bash
# Exercise the backing-service address resolution (Makefile), via `make print-backing-services`.
# Pure Makefile logic - no docker needed. Runs make in a sandbox holding a copy of the Makefile
# and a synthetic Docker/.env, so the real checkout's .env is never read or written.

set -u

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"

PASSES=0
FAILS=0
color() { local c=$1; shift; printf '\033[%sm%s\033[0m\n' "$c" "$*"; }
ok()   { color '32' "PASS: $*"; PASSES=$((PASSES + 1)); }
fail() { color '31' "FAIL: $*"; FAILS=$((FAILS + 1)); }

assert_eq() {
    local got=$1 want=$2 msg=$3
    if [ "$got" = "$want" ]; then ok "$msg"; else fail "$msg (got '$got', want '$want')"; fi
}

SANDBOX="$(mktemp -d)"
trap 'rm -rf "$SANDBOX"' EXIT
mkdir "$SANDBOX/Docker"
cp "$ROOT/Makefile" "$SANDBOX/Makefile"

write_env() { printf '%s\n' "$@" > "$SANDBOX/Docker/.env"; }

# One scrubbed environment for every nested make: the Makefile's global `export` — and MAKEFLAGS,
# which carries the outer make's command-line variables — would otherwise leak the outer
# `make test-scripts` state into the sandbox and beat the very precedence being tested.
SCRUB=(env -u MAKEFLAGS -u MFLAGS \
    -u MARIADB_HOST -u MARIADB_PORT -u NEO4J_SCHEME -u NEO4J_HOST -u NEO4J_PORT \
    -u _ENV_MARIADB_HOST -u _ENV_MARIADB_PORT -u _ENV_NEO4J_SCHEME -u _ENV_NEO4J_HOST -u _ENV_NEO4J_PORT \
    -u _SET_MARIADB_HOST -u _SET_MARIADB_PORT -u _SET_NEO4J_SCHEME -u _SET_NEO4J_HOST -u _SET_NEO4J_PORT)

# Leading VAR=val arguments become environment values (set after the scrub).
run_make() {
    ( cd "$SANDBOX" && "${SCRUB[@]}" "$@" make -s print-backing-services )
}

# Arguments go to make itself, e.g. a command-line variable assignment.
run_make_cli() {
    ( cd "$SANDBOX" && "${SCRUB[@]}" make -s print-backing-services "$@" )
}

field() {
    printf '%s\n' "$1" | grep "^$2:" | sed -E "s/^$2:[[:space:]]*//"
}

echo
color '36' "Case 1: empty Docker/.env -> the documented defaults"
write_env ""
out="$(run_make 2>&1)"
assert_eq "$(field "$out" mariadb)" "db:3306" "MariaDB defaults to db:3306"
assert_eq "$(field "$out" neo4j)" "bolt://neo:7687" "Neo4j defaults to bolt://neo:7687"

echo
color '36' "Case 2: Docker/.env values beat the defaults"
write_env "MARIADB_HOST=filedb" "MARIADB_PORT=3307" "NEO4J_SCHEME=neo4j+s" "NEO4J_HOST=fileneo" "NEO4J_PORT=7688"
out="$(run_make 2>&1)"
assert_eq "$(field "$out" mariadb)" "filedb:3307" "MariaDB address comes from Docker/.env"
assert_eq "$(field "$out" neo4j)" "neo4j+s://fileneo:7688" "Neo4j address comes from Docker/.env"

echo
color '36' "Case 3: environment beats Docker/.env, as it does for docker compose"
write_env "MARIADB_HOST=filedb" "NEO4J_HOST=fileneo"
out="$(run_make MARIADB_HOST=envdb NEO4J_HOST=envneo 2>&1)"
assert_eq "$(field "$out" mariadb)" "envdb:3306" "Environment MARIADB_HOST beats the Docker/.env value"
assert_eq "$(field "$out" neo4j)" "bolt://envneo:7687" "Environment NEO4J_HOST beats the Docker/.env value"

echo
color '36' "Case 4: make command line beats Docker/.env"
write_env "MARIADB_HOST=filedb"
out="$(run_make_cli MARIADB_HOST=clidb 2>&1)"
assert_eq "$(field "$out" mariadb)" "clidb:3306" "Command-line MARIADB_HOST beats the Docker/.env value"

echo
color '36' "Case 5: blank environment value hides Docker/.env and falls back to the default"
write_env "MARIADB_HOST=filedb"
out="$(run_make MARIADB_HOST= 2>&1)"
assert_eq "$(field "$out" mariadb)" "db:3306" "Blank environment MARIADB_HOST resolves to the default"

echo
color '36' "Case 6: Docker/.env values survive trailing whitespace from inline comments"
write_env "MARIADB_HOST=filedb # external box" "NEO4J_HOST=fileneo # external box"
out="$(run_make 2>&1)"
assert_eq "$(field "$out" mariadb)" "filedb:3306" "MARIADB_HOST is stripped of an inline comment's whitespace"
assert_eq "$(field "$out" neo4j)" "bolt://fileneo:7687" "NEO4J_HOST is stripped of an inline comment's whitespace"

echo
if [ "$FAILS" -eq 0 ]; then color '32' "All $PASSES checks passed."; exit 0
else color '31' "$FAILS check(s) failed ($PASSES passed)."; exit 1; fi
