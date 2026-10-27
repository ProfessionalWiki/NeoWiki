#!/usr/bin/env bash
# Exercise what the Makefile derives from the copy, the demo image tag (NEOWIKI_IMAGE_TAG) and the
# compose project name, and the guards of the goals that run the image, in throwaway copies of the
# repository. Docker is a stub that logs its calls, so no stack starts.

set -u

# Git exports GIT_DIR and its kin to the commands it runs, such as `git rebase -x` and hooks; left
# set, they point the git calls below at the developer's repository.
# shellcheck disable=SC2046 # one variable name per word
unset $(git rev-parse --local-env-vars)

# When `make test-scripts` runs this script, the outer make's global `export` hands it that make's
# NEOWIKI_IMAGE_TAG, and MAKEFLAGS carries its command-line variables; either would win over the
# derivation under test.
unset NEOWIKI_IMAGE_TAG MAKEFLAGS

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

# The stub docker logs each call. The stack's mediawiki container runs the image with ID
# STUB_MEDIAWIKI_RUNS, or there is no such container when that is empty. Every image is present
# locally, with ID id-of-<tag>; with STUB_PULL_MOVES set, a pull moves each tag to a newer image.
# The stub curl answers the wait for MediaWiki, so a goal that gets past its guards runs to the
# end without reaching a real stack.
mkdir "$WORK/bin"
cat > "$WORK/bin/docker" <<'EOF'
#!/usr/bin/env bash
printf '%s\n' "$*" >> "$DOCKER_LOG"
case " $* " in
    *" ps "*"service=mediawiki"*) [ -z "${STUB_MEDIAWIKI_RUNS:-}" ] || echo mediawiki-container ;;
    *" image inspect "*)
        ref="${!#}"
        id="id-of-${ref##*:}"
        if [ -n "${STUB_PULL_MOVES:-}" ] && grep -q ' pull$' "$DOCKER_LOG"; then id="$id-pulled"; fi
        echo "$id" ;;
    *" inspect "*" mediawiki-container "*) echo "$STUB_MEDIAWIKI_RUNS" ;;
esac
exit 0
EOF
printf '#!/bin/sh\nexit 0\n' > "$WORK/bin/curl"
chmod +x "$WORK/bin/docker" "$WORK/bin/curl"
export PATH="$WORK/bin:$PATH"
export DOCKER_LOG="$WORK/docker.log"

# Holds the Makefile, what it reads before running a target, and the preflight script the image
# goals run. The Makefile is copied rather than passed with -f, which the goals' recursive makes
# would not inherit.
new_copy() {
    mkdir -p "$WORK/$1/Docker"
    cp "$ROOT/Makefile" "$WORK/$1/"
    cp "$ROOT/Docker/.env.dist" "$WORK/$1/Docker/"
    cp -R "$ROOT/Docker/scripts" "$WORK/$1/Docker/"
    printf '%s\n' "$WORK/$1"
}

git_in() {
    git -C "$1" -c user.name=Test -c user.email=test@example.invalid -c commit.gpgSign=false \
        -c tag.gpgSign=false -c core.hooksPath=/dev/null "${@:2}" >/dev/null
}

# Writes the Docker/image-tag that scripts/tag-release.sh puts in a version tag's commit.
name_image() { printf '%s\n' "$2" > "$1/Docker/image-tag"; }

new_checkout() {
    local dir
    dir="$(new_copy "$1")"
    git_in "$dir" init -q -b master
    git_in "$dir" commit -q --allow-empty -m 'First commit'
    printf '%s\n' "$dir"
}

# A linked worktree of a new checkout: its .git is a file, not a directory.
new_worktree() {
    git_in "$(new_checkout "$1-main")" worktree add -q "$WORK/$1"
    new_copy "$1"
}

make_in() {
    local dir=$1; shift
    make -s --no-print-directory -C "$dir" "$@"
}

# A makefile of its own rather than --eval, which the make 3.81 of stock macOS lacks.
PRINT_IMAGE_TAG="$WORK/print-image-tag.mk"
# shellcheck disable=SC2016 # make, not the shell, expands the $$
printf '%s\n' 'print-image-tag: ; @echo "$$NEOWIKI_IMAGE_TAG"' > "$PRINT_IMAGE_TAG"

# What docker compose receives from the Makefile in copy $1; the further arguments go to make.
image_tag() { make_in "$1" -f Makefile -f "$PRINT_IMAGE_TAG" "${@:2}" print-image-tag; }
project_name() { make_in "$1" -n "${@:2}" ps | sed -nE 's/^docker compose -p ([^ ]+) .*/\1/p'; }

# Runs make in copy $1 for real, the further arguments going to make, with the stack's mediawiki
# container running the image with ID MEDIAWIKI_RUNS, and pulls moving tags when PULL_MOVES is set.
# Leaves the output in OUT, the exit code in RC and docker's calls in DOCKER_LOG.
run_make() {
    local dir=$1; shift
    : > "$DOCKER_LOG"
    OUT="$(export STUB_MEDIAWIKI_RUNS="${MEDIAWIKI_RUNS:-}" STUB_PULL_MOVES="${PULL_MOVES:-}"; make_in "$dir" "$@" 2>&1)"
    RC=$?
}

# What the last run_make did with the demo image.
started() { grep -qE '^compose .* up -d$' "$DOCKER_LOG"; }
ran_image_command() { started || grep -qE '^compose .* pull$|^build ' "$DOCKER_LOG"; }

# Whether the last run_make failed with a message containing every further argument.
failed_with() {
    local text
    [ "$RC" -ne 0 ] || return 1
    for text in "$@"; do
        printf '%s' "$OUT" | grep -qF -- "$text" || return 1
    done
}

echo
color '36' "Case 1: checkout of a release tag -> that release"
dir="$(new_checkout release)"
name_image "$dir" 0.1.0
assert_eq "$(image_tag "$dir")" "0.1.0" "A release tag checkout runs that release's image"

echo
color '36' "Case 2: checkout of a pre-release tag -> that pre-release"
dir="$(new_checkout pre-release)"
name_image "$dir" 0.1.0-rc.1
assert_eq "$(image_tag "$dir")" "0.1.0-rc.1" "A pre-release tag checkout runs that pre-release's image"

echo
color '36' "Case 3: copy without .git, as unpacked from a release archive -> that release, fixed project name"
dir="$(new_copy NeoWiki-0.1.0)"
name_image "$dir" 0.1.0
assert_eq "$(image_tag "$dir")" "0.1.0" "A release archive runs that release's image"
assert_eq "$(project_name "$dir")" "neowiki" "A copy without .git runs as project neowiki"

echo
color '36' "Case 4: git checkout without Docker/image-tag -> master"
dir="$(new_checkout plain)"
assert_eq "$(image_tag "$dir")" "master" "A git checkout without Docker/image-tag runs master's image"
dir="$(new_checkout hand-made-tag)"
git_in "$dir" tag 0.1.0
assert_eq "$(image_tag "$dir")" "master" "A version tag made without tag-release.sh runs master's image"

echo
color '36' "Case 5: copy without .git or Docker/image-tag, as master's Download ZIP -> the image goals stop"
dir="$(new_copy NeoWiki-master)"
for goal in pull up demo upgrade wiki-production-image; do
    run_make "$dir" "$goal"
    if failed_with 'releases/latest' NEOWIKI_IMAGE_TAG && ! ran_image_command; then
        ok "make $goal stops, pointing to the latest release and NEOWIKI_IMAGE_TAG"
    else
        fail "make $goal should stop, pointing to the latest release and NEOWIKI_IMAGE_TAG (exit $RC, got: $OUT)"
    fi
done
image_tag "$dir" >/dev/null 2>&1
assert_eq "$?" 0 "A goal that runs no image still works"
assert_eq "$(project_name "$dir")" "neowiki" "A copy without .git runs as project neowiki"

echo
color '36' "Case 6: git worktree -> master, project named after its directory"
dir="$(new_worktree NeoWiki-feature-x)"
assert_eq "$(image_tag "$dir")" "master" "A git worktree runs master's image"
assert_eq "$(project_name "$dir")" "neowiki-neowiki-feature-x" "A git worktree's project is named after its directory"

echo
color '36' "Case 7: git checkout in a directory with dots -> a project name compose accepts"
dir="$(new_checkout NeoWiki-0.1.0-clone)"
assert_eq "$(project_name "$dir")" "neowiki-neowiki-0-1-0-clone" "Dots in the directory name become hyphens"

echo
color '36' "Case 8: NEOWIKI_IMAGE_TAG from the environment -> that tag"
dir="$(new_checkout override)"
name_image "$dir" 0.1.0
assert_eq "$(NEOWIKI_IMAGE_TAG=0.0.9 image_tag "$dir")" "0.0.9" \
    "NEOWIKI_IMAGE_TAG from the environment wins over Docker/image-tag"

echo
color '36' "Case 9: blank NEOWIKI_IMAGE_TAG -> Docker/image-tag"
dir="$(new_checkout blank)"
name_image "$dir" 0.1.0
assert_eq "$(image_tag "$dir" NEOWIKI_IMAGE_TAG=)" "0.1.0" "A blank NEOWIKI_IMAGE_TAG counts as unset"

echo
color '36' "Case 10: NEOWIKI_IMAGE_TAG in Docker/.env with a comment -> that tag"
dir="$(new_checkout env-file)"
printf 'NEOWIKI_IMAGE_TAG=0.0.9   # pinned\n' >> "$dir/Docker/.env.dist"
assert_eq "$(image_tag "$dir")" "0.0.9" "Spaces before a comment in Docker/.env are not part of the tag"

echo
color '36' "Case 11: git checkout -> the image goals run"
dir="$(new_checkout readable)"
run_make "$dir" pull
if [ "$RC" -eq 0 ] && ran_image_command; then ok "make pull pulls"; else fail "make pull should pull (exit $RC, got: $OUT)"; fi

echo
color '36' "Case 12: production image -> built under the copy's tag"
dir="$(new_checkout production)"
name_image "$dir" 0.1.0
run_make "$dir" wiki-production-image
if grep -qF -- '-t ghcr.io/professionalwiki/neowiki:0.1.0' "$DOCKER_LOG"; then
    ok "make wiki-production-image tags the image with the copy's tag"
else
    fail "make wiki-production-image should tag the image 0.1.0 (docker calls: $(cat "$DOCKER_LOG"))"
fi

echo
color '36' "Case 13: stack on another version's image -> up and demo stop, upgrade moves it"
dir="$(new_checkout moved)"
name_image "$dir" 0.2.0
MEDIAWIKI_RUNS=id-of-0.1.0
for goal in up demo; do
    run_make "$dir" "$goal"
    if failed_with 'neowiki:0.2.0' 'make upgrade' && ! started; then
        ok "make $goal stops, naming this copy's image and make upgrade"
    else
        fail "make $goal should stop, naming this copy's image and make upgrade (exit $RC, got: $OUT)"
    fi
done
run_make "$dir" upgrade
if [ "$RC" -eq 0 ] && started; then ok "make upgrade moves the stack"; else fail "make upgrade should move the stack (exit $RC, got: $OUT)"; fi

echo
color '36' "Case 14: stack on this copy's image, or no stack -> up starts it"
MEDIAWIKI_RUNS=id-of-0.2.0
run_make "$dir" up
if [ "$RC" -eq 0 ] && started; then ok "make up starts a stack on the same image"; else fail "make up should start a stack on the same image (exit $RC, got: $OUT)"; fi
MEDIAWIKI_RUNS=
run_make "$dir" up
if [ "$RC" -eq 0 ] && started; then ok "make up starts a new stack"; else fail "make up should start a new stack (exit $RC, got: $OUT)"; fi

echo
color '36' "Case 15: newer image pulled under the same tag -> demo stops before starting it"
dir="$(new_checkout master-moved)"
MEDIAWIKI_RUNS=id-of-master
PULL_MOVES=1
run_make "$dir" demo
if failed_with 'make upgrade' && ! started; then ok "make demo stops after pulling a newer master image"; else fail "make demo should stop after pulling a newer master image (exit $RC, got: $OUT)"; fi
PULL_MOVES=
run_make "$dir" demo
if [ "$RC" -eq 0 ] && started; then ok "make demo starts a stack whose image is unchanged"; else fail "make demo should start a stack whose image is unchanged (exit $RC, got: $OUT)"; fi
MEDIAWIKI_RUNS=

echo
if [ "$FAILS" -eq 0 ]; then color '32' "All $PASSES checks passed."; exit 0
else color '31' "$FAILS check(s) failed ($PASSES passed)."; exit 1; fi
