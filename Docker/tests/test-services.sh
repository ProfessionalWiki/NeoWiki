#!/usr/bin/env bash
# Exercise the services= optional-service selection (Makefile), via `make print-services`.
# Pure Makefile logic - no docker needed.

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

run_print_services() {
    # Unset COMPOSE_PROFILES: the Makefile's global `export` means that when this script itself
    # runs as a recipe of `make test-scripts`, the outer make's computed COMPOSE_PROFILES is
    # already in this shell's environment and would otherwise leak into the nested make call
    # below, unioning itself into every case regardless of services=. Case 4 sets it back
    # explicitly for the one case that wants it.
    ( cd "$ROOT" && unset COMPOSE_PROFILES && make -s print-services "$@" )
}

# Extracts the value after "<label>:" from print-services output, trimming leading whitespace.
field() {
    printf '%s\n' "$1" | grep "^$2:" | sed -E "s/^$2:[[:space:]]*//"
}

echo
color '36' "Case 1: absent services= flag -> default set, oxigraph deselected"
out="$(run_print_services 2>&1)"
assert_eq "$(field "$out" selected)" "node qlever mailcatcher" "Default selection is node, qlever, mailcatcher"
assert_eq "$(field "$out" deselected)" "oxigraph" "Default deselects only oxigraph"

echo
color '36' "Case 2: services=none -> nothing selected, all four deselected"
out="$(run_print_services services=none 2>&1)"
assert_eq "$(field "$out" selected)" "" "services=none selects nothing"
assert_eq "$(field "$out" deselected)" "node qlever mailcatcher oxigraph" "services=none deselects all four"

echo
color '36' "Case 3: services=node,qlever -> only those two selected"
out="$(run_print_services services=node,qlever 2>&1)"
assert_eq "$(field "$out" selected)" "node qlever" "services=node,qlever selects exactly those"
assert_eq "$(field "$out" deselected)" "mailcatcher oxigraph" "services=node,qlever deselects the rest"

echo
color '36' "Case 4: COMPOSE_PROFILES=oxigraph -> unioned in, not deselected"
out="$( cd "$ROOT" && unset COMPOSE_PROFILES && COMPOSE_PROFILES=oxigraph make -s print-services 2>&1 )"
assert_eq "$(field "$out" deselected)" "" "COMPOSE_PROFILES=oxigraph is not deselected"
case ",$(field "$out" profiles)," in
    *,oxigraph,*) ok "COMPOSE_PROFILES=oxigraph appears in the resulting profiles" ;;
    *) fail "COMPOSE_PROFILES=oxigraph should appear in the resulting profiles (got '$(field "$out" profiles)')" ;;
esac

echo
color '36' "Case 5: services=bogus -> non-zero exit, error names the unknown service"
rc=0
out="$( cd "$ROOT" && unset COMPOSE_PROFILES && make -s print-services services=bogus 2>&1 )" || rc=$?
if [ "$rc" -ne 0 ]; then ok "services=bogus fails (exit $rc)"; else fail "services=bogus should fail (exit 0)"; fi
if printf '%s' "$out" | grep -qF "bogus"; then ok "Error message names 'bogus'"; else fail "Error message should name 'bogus' (got: $out)"; fi

echo
if [ "$FAILS" -eq 0 ]; then color '32' "All $PASSES checks passed."; exit 0
else color '31' "$FAILS check(s) failed ($PASSES passed)."; exit 1; fi
