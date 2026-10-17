#!/usr/bin/env bash
# Exercise the demo image tag the Makefile derives from the checkout (NEOWIKI_IMAGE_TAG), in
# throwaway copies of the repository. Pure Makefile logic - no docker needed.

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

# Holds only what the Makefile reads before running a target.
new_copy() {
    mkdir -p "$WORK/$1/Docker"
    cp "$ROOT/Docker/.env.dist" "$WORK/$1/Docker/"
    printf '%s\n' "$WORK/$1"
}

git_in() {
    git -C "$1" -c user.name=Test -c user.email=test@example.invalid -c commit.gpgSign=false \
        -c tag.gpgSign=false "${@:2}" >/dev/null
}

new_checkout() {
    local dir
    dir="$(new_copy "$1")"
    git_in "$dir" init -q -b master
    git_in "$dir" commit -q --allow-empty -m 'First commit'
    printf '%s\n' "$dir"
}

# Prints the tag the Makefile settles on in copy $1, with any further arguments set in its
# environment.
image_tag() {
    local dir=$1; shift
    # Unset NEOWIKI_IMAGE_TAG: the Makefile's global `export` means that when this script runs as
    # a recipe of `make test-scripts`, the outer make's derived value is already in this shell's
    # environment and would win over the derivation under test.
    ( unset NEOWIKI_IMAGE_TAG && env "$@" make -s --no-print-directory -C "$dir" -f "$ROOT/Makefile" \
        --eval 'print-image-tag: ; @echo $(NEOWIKI_IMAGE_TAG)' print-image-tag )
}

echo
color '36' "Case 1: checkout of a release tag -> that release"
dir="$(new_checkout release)"
git_in "$dir" tag 0.1.0
assert_eq "$(image_tag "$dir")" "0.1.0" "A release tag checkout runs that release's image"

echo
color '36' "Case 2: commit after a release tag -> master"
dir="$(new_checkout after-release)"
git_in "$dir" tag 0.1.0
git_in "$dir" commit -q --allow-empty -m 'Second commit'
assert_eq "$(image_tag "$dir")" "master" "A commit after a release runs master's image"

echo
color '36' "Case 3: checkout of a tag that is not a release -> master"
dir="$(new_checkout perf-tag)"
git_in "$dir" tag perf/2026-07-31-batching
assert_eq "$(image_tag "$dir")" "master" "A non-release tag runs master's image"

echo
color '36' "Case 4: copy without .git -> latest"
dir="$(new_copy archive)"
assert_eq "$(image_tag "$dir")" "latest" "A copy without .git runs the newest release's image"

echo
color '36' "Case 5: NEOWIKI_IMAGE_TAG from the environment -> that tag"
dir="$(new_checkout override)"
git_in "$dir" tag 0.1.0
assert_eq "$(image_tag "$dir" NEOWIKI_IMAGE_TAG=0.0.9)" "0.0.9" \
    "NEOWIKI_IMAGE_TAG from the environment wins over the checkout's tag"

echo
if [ "$FAILS" -eq 0 ]; then color '32' "All $PASSES checks passed."; exit 0
else color '31' "$FAILS check(s) failed ($PASSES passed)."; exit 1; fi
