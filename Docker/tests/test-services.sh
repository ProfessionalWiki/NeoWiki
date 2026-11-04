#!/usr/bin/env bash
# Exercise the services= optional-service selection (Makefile), via `make print-services`.
# Pure Makefile logic: docker is a stub that reports no containers, and every run ignores a selection
# saved in Docker/.env, so a developer's own stack does not decide the outcome.

set -u

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"

STUB_BIN="$(mktemp -d)"
trap 'rm -rf "$STUB_BIN"' EXIT
printf '#!/bin/sh\nexit 0\n' > "$STUB_BIN/docker"
chmod +x "$STUB_BIN/docker"

PASSES=0
FAILS=0
color() { local c=$1; shift; printf '\033[%sm%s\033[0m\n' "$c" "$*"; }
ok()   { color '32' "PASS: $*"; PASSES=$((PASSES + 1)); }
fail() { color '31' "FAIL: $*"; FAILS=$((FAILS + 1)); }

assert_eq() {
    local got=$1 want=$2 msg=$3
    if [ "$got" = "$want" ]; then ok "$msg"; else fail "$msg (got '$got', want '$want')"; fi
}

# Runs print-services as a stack with nothing saved and no containers would.
print_services() {
    ( cd "$ROOT" && PATH="$STUB_BIN:$PATH" make -s print-services NEOWIKI_SERVICES= NEOWIKI_PROFILES= "$@" )
}

run_print_services() {
    # Unset what the Makefile derives from the selection: its global `export` means that when this
    # script itself runs as a recipe of `make test-scripts`, the outer make's computed values are
    # already in this shell's environment and would otherwise leak into the nested make call
    # below, overriding every case regardless of services=. The cases that want one set it back.
    ( unset COMPOSE_PROFILES OXIGRAPH_URL NEO4J_HOST QLEVER_URL && print_services "$@" )
}

# Extracts the value after "<label>:" from print-services output, trimming leading whitespace.
field() {
    printf '%s\n' "$1" | grep "^$2:" | sed -E "s/^$2:[[:space:]]*//"
}

echo
color '36' "Case 1: absent services= flag -> default set, neo and oxigraph deselected"
out="$(run_print_services 2>&1)"
assert_eq "$(field "$out" selected)" "node qlever mailcatcher" "Default selection is node, qlever, mailcatcher"
assert_eq "$(field "$out" deselected)" "neo oxigraph" "Default deselects neo and oxigraph"

echo
color '36' "Case 2: services=none -> nothing selected, all five deselected"
out="$(run_print_services services=none 2>&1)"
assert_eq "$(field "$out" selected)" "" "services=none selects nothing"
assert_eq "$(field "$out" deselected)" "node qlever neo oxigraph mailcatcher" "services=none deselects all five"

echo
color '36' "Case 2b: services=default -> the default set"
out="$(run_print_services services=default 2>&1)"
assert_eq "$(field "$out" selected)" "node qlever mailcatcher" "services=default selects node, qlever, mailcatcher"

echo
color '36' "Case 3: services=node,qlever -> only those two selected"
out="$(run_print_services services=node,qlever 2>&1)"
assert_eq "$(field "$out" selected)" "node qlever" "services=node,qlever selects exactly those"
assert_eq "$(field "$out" deselected)" "neo oxigraph mailcatcher" "services=node,qlever deselects the rest"

echo
color '36' "Case 4: COMPOSE_PROFILES=neo -> unioned in, not deselected"
out="$( unset COMPOSE_PROFILES OXIGRAPH_URL NEO4J_HOST QLEVER_URL && COMPOSE_PROFILES=neo print_services 2>&1 )"
assert_eq "$(field "$out" deselected)" "oxigraph" "COMPOSE_PROFILES=neo is not deselected"
case ",$(field "$out" profiles)," in
    *,neo,*) ok "COMPOSE_PROFILES=neo appears in the resulting profiles" ;;
    *) fail "COMPOSE_PROFILES=neo should appear in the resulting profiles (got '$(field "$out" profiles)')" ;;
esac

echo
color '36' "Case 5: services=bogus -> non-zero exit, error names the unknown service"
rc=0
out="$(run_print_services services=bogus 2>&1)" || rc=$?
if [ "$rc" -ne 0 ]; then ok "services=bogus fails (exit $rc)"; else fail "services=bogus should fail (exit 0)"; fi
if printf '%s' "$out" | grep -qF "bogus"; then ok "Error message names 'bogus'"; else fail "Error message should name 'bogus' (got: $out)"; fi

echo
color '36' "Case 6: absent services= flag -> the wiki is pointed at QLever only"
out="$(run_print_services 2>&1)"
assert_eq "$(field "$out" QLEVER_URL)" "http://qlever:7019/" "Default sets QLEVER_URL"
assert_eq "$(field "$out" NEO4J_HOST)" "" "Default leaves NEO4J_HOST empty"
assert_eq "$(field "$out" OXIGRAPH_URL)" "" "Default leaves OXIGRAPH_URL empty"

echo
color '36' "Case 7: services=neo,oxigraph -> the wiki is pointed at Neo4j and Oxigraph only"
out="$(run_print_services services=neo,oxigraph 2>&1)"
assert_eq "$(field "$out" NEO4J_HOST)" "neo" "services=neo,oxigraph sets NEO4J_HOST"
assert_eq "$(field "$out" OXIGRAPH_URL)" "http://oxigraph:7878" "services=neo,oxigraph sets OXIGRAPH_URL"
assert_eq "$(field "$out" QLEVER_URL)" "" "services=neo,oxigraph leaves QLEVER_URL empty"

echo
color '36' "Case 8: NEO4J_HOST from the environment -> kept, with or without the neo service"
out="$( unset COMPOSE_PROFILES OXIGRAPH_URL QLEVER_URL && NEO4J_HOST=neo4j.example print_services services=none 2>&1 )"
assert_eq "$(field "$out" NEO4J_HOST)" "neo4j.example" "An external NEO4J_HOST is kept when neo is deselected"
out="$( unset COMPOSE_PROFILES OXIGRAPH_URL QLEVER_URL && NEO4J_HOST='' print_services services=neo 2>&1 )"
assert_eq "$(field "$out" NEO4J_HOST)" "neo" "A blank NEO4J_HOST counts as unset"

echo
if [ "$FAILS" -eq 0 ]; then color '32' "All $PASSES checks passed."; exit 0
else color '31' "$FAILS check(s) failed ($PASSES passed)."; exit 1; fi
