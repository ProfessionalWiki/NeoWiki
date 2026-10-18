#!/usr/bin/env bash
# Exercise the Compose project name the Makefile derives from the checkout's directory name, via
# `make -n down`. Pure Makefile logic - no docker needed.

set -u

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"

WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT

PASSES=0
FAILS=0
color() { local c=$1; shift; printf '\033[%sm%s\033[0m\n' "$c" "$*"; }
ok()   { color '32' "PASS: $*"; PASSES=$((PASSES + 1)); }
fail() { color '31' "FAIL: $*"; FAILS=$((FAILS + 1)); }

assert_eq() {
    local got=$1 want=$2 msg=$3
    if [ "$got" = "$want" ]; then ok "$msg"; else fail "$msg (got '$got', want '$want')"; fi
}

# The project name `docker compose -p` gets in a checkout directory named $1.
project_name_in() {
    local dir="$WORK/$1"
    mkdir -p "$dir/Docker"
    cp "$ROOT/Docker/.env.dist" "$dir/Docker/.env.dist"
    ( cd "$dir" && make -s -n -f "$ROOT/Makefile" down ) | sed -nE 's/^docker compose -p ([^ ]+) .*/\1/p'
}

echo
color '36' "Case 1: main checkout"
assert_eq "$(project_name_in NeoWiki)" "neowiki-neowiki" "NeoWiki gives neowiki-neowiki"

echo
color '36' "Case 2: worktree"
assert_eq "$(project_name_in NeoWiki-feature-x)" "neowiki-neowiki-feature-x" \
    "NeoWiki-feature-x gives neowiki-neowiki-feature-x"

echo
color '36' "Case 3: release archive, whose dots Compose rejects in a project name"
assert_eq "$(project_name_in NeoWiki-0.1.0)" "neowiki-neowiki-0-1-0" "NeoWiki-0.1.0 gives neowiki-neowiki-0-1-0"

echo
if [ "$FAILS" -eq 0 ]; then color '32' "All $PASSES checks passed."; exit 0
else color '31' "$FAILS check(s) failed ($PASSES passed)."; exit 1; fi
