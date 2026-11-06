#!/usr/bin/env bash
# Exercise scripts/publish-simplifications.sh against a throwaway origin, with gh stubbed.

set -u

# Git exports GIT_DIR and its kin to the commands it runs, such as `git rebase -x`
# and hooks; left set, they point the git calls below at the developer's repository.
# shellcheck disable=SC2046 # one variable name per word
unset $(git rev-parse --local-env-vars)

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
SUT="$SCRIPT_DIR/../publish-simplifications.sh"

WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT
# Should a fixture step fail, git must not fall back to a repository around $WORK.
export GIT_CEILING_DIRECTORIES="$WORK"

PASSES=0
FAILS=0

color() {
    local code=$1; shift
    printf '\033[%sm%s\033[0m\n' "$code" "$*"
}
ok()   { color '32' "PASS: $*"; PASSES=$((PASSES + 1)); }
fail() { color '31' "FAIL: $*"; FAILS=$((FAILS + 1)); }

assert_eq() {
    local label=$1 actual=$2 expected=$3
    if [ "$actual" = "$expected" ]; then
        ok "$label"
    else
        fail "$label (expected '$expected', got '$actual')"
    fi
}

assert_contains() {
    local label=$1 haystack=$2 needle=$3
    if [[ $haystack == *"$needle"* ]]; then
        ok "$label"
    else
        fail "$label (no '$needle' in: $haystack)"
    fi
}

assert_lacks() {
    local label=$1 haystack=$2 needle=$3
    if [[ $haystack == *"$needle"* ]]; then
        fail "$label (unexpected '$needle' in: $haystack)"
    else
        ok "$label"
    fi
}

# Passes when the logged gh call $2 has each further argument, as a flag with its value, in any order.
assert_flags() {
    local label=$1 call=$2 flag
    shift 2
    for flag in "$@"; do
        if [[ " $call " != *" $flag "* ]]; then
            fail "$label (no '$flag' in: $call)"
            return
        fi
    done
    ok "$label"
}

# --file rather than --global: git older than 2.32 ignores GIT_CONFIG_GLOBAL and
# would write to the developer's ~/.gitconfig.
export GIT_CONFIG_GLOBAL="$WORK/gitconfig"
export GIT_CONFIG_NOSYSTEM=1
git config --file "$GIT_CONFIG_GLOBAL" user.name 'Fixture Author'
git config --file "$GIT_CONFIG_GLOBAL" user.email 'fixture@example.org'
git config --file "$GIT_CONFIG_GLOBAL" init.defaultBranch master

export GIT_AUTHOR_NAME=ProWikiExperts GIT_AUTHOR_EMAIL=bot@example.org
export GIT_COMMITTER_NAME=ProWikiExperts GIT_COMMITTER_EMAIL=bot@example.org
export SIMPLIFY_BASE=1111111111111111111111111111111111111111 SIMPLIFY_DATE=2026-11-07
export SIMPLIFY_MODEL='Opus 5.5' SIMPLIFY_EFFORT=xhigh
export GITHUB_REPOSITORY=example/NeoWiki GITHUB_RUN_ID=7

# Stands in for gh: logs each call's arguments to $GH_LOG, one call per line, copies a pull request's --body-file to
# $GH_BODIES/<call number>.md and the issue's to $GH_BODIES/report.md, and prints the URL it pretends to create.
mkdir "$WORK/bin"
cat > "$WORK/bin/gh" <<'EOF'
#!/usr/bin/env bash
command="$1 $2"
echo "$*" >> "$GH_LOG"
calls=$(wc -l < "$GH_LOG" | tr -d ' ')
while [ $# -gt 0 ]; do
    if [ "$1" = --body-file ]; then
        case "$command" in
            'pr create') cp "$2" "$GH_BODIES/$calls.md" ;;
            'issue create') cp "$2" "$GH_BODIES/report.md" ;;
        esac
    fi
    shift
done
case "$command" in
    'pr create') echo "https://github.com/example/NeoWiki/pull/$((1800 + calls))" ;;
    'issue create') echo "https://github.com/example/NeoWiki/issues/$((1800 + calls))" ;;
esac
EOF
chmod +x "$WORK/bin/gh"
export PATH="$WORK/bin:$PATH"

# The fixture every case copies: an origin whose master holds two source files, and a clone of it.
TEMPLATE="$WORK/template"
git init -q --bare "$TEMPLATE/origin.git"
git clone -q "$TEMPLATE/origin.git" "$TEMPLATE/clone" 2>/dev/null
mkdir -p "$TEMPLATE/clone/src" "$TEMPLATE/clone/.github/workflows"
printf '<?php\n\nfunction a() {\n\treturn 1;\n}\n\nfunction b() {\n\treturn 1;\n}\n' > "$TEMPLATE/clone/src/Helpers.php"
echo 'name: CI' > "$TEMPLATE/clone/.github/workflows/ci.yml"
seq 1 700 | sed 's/^/\/\/ /' > "$TEMPLATE/clone/src/Big.php"
git -C "$TEMPLATE/clone" add -A
git -C "$TEMPLATE/clone" commit -q -m first
git -C "$TEMPLATE/clone" push -q origin master

# Sets $ORIGIN, $CLONE, $RUN, $GH_LOG and $GH_BODIES to a fresh copy of the fixture and a run directory holding the
# empty files the workflow creates.
setup_case() {
    local dir="$WORK/$1"
    cp -R "$TEMPLATE" "$dir"
    ORIGIN="$dir/origin.git"
    CLONE="$dir/clone"
    RUN="$dir/run"
    git -C "$CLONE" remote set-url origin "$ORIGIN"
    mkdir -p "$RUN/patches" "$dir/bodies"
    touch "$RUN/bugs.md" "$RUN/rejected.md"
    export GH_LOG="$dir/gh.log" GH_BODIES="$dir/bodies"
    touch "$GH_LOG"
}

# Writes patch number $1 of the run, titled $2, from the change the function $3 makes to the clone.
add_patch() {
    local dir="$RUN/patches/$1"
    mkdir -p "$dir"
    "$3"
    git -C "$CLONE" add -A
    git -C "$CLONE" diff --cached --no-renames > "$dir/change.patch"
    git -C "$CLONE" reset -q --hard
    echo "$2" > "$dir/title.txt"
    printf 'Both helpers returned 1, so b() goes.\nThanks @someone.\n' > "$dir/message.md"
    echo '- Where your judgment helps: whether a() is a good name, says @someone.' > "$dir/calls.md"
    echo '- HelpersTest: 3 tests pass, as on master, checked by @someone.' > "$dir/evidence.md"
}

remove_helper() {
    printf '<?php\n\nfunction a() {\n\treturn 1;\n}\n' > "$CLONE/src/Helpers.php"
}

# Adds three lines to the first file of the patch and removes one, and removes one from the last.
grow_helper() {
    { tail -n +2 "$CLONE/src/Big.php"; printf '// More.\n// Lines.\n// Here.\n'; } > "$CLONE/src/Big.next"
    mv "$CLONE/src/Big.next" "$CLONE/src/Big.php"
    sed '$d' "$CLONE/src/Helpers.php" > "$CLONE/src/Helpers.next"
    mv "$CLONE/src/Helpers.next" "$CLONE/src/Helpers.php"
}

vendor_library() {
    remove_helper
    mkdir -p "$CLONE/vendor/@someone/lib/src"
    echo '<?php' > "$CLONE/vendor/@someone/lib/src/Lib.php"
}

add_executable() {
    remove_helper
    echo '<?php' > "$CLONE/src/Run.php"
    chmod +x "$CLONE/src/Run.php"
}

remove_big_file() {
    rm "$CLONE/src/Big.php"
}

make_helpers_executable() {
    remove_helper
    chmod +x "$CLONE/src/Helpers.php"
}

link_workflow() {
    remove_helper
    ln -s ../.github/workflows/ci.yml "$CLONE/src/Workflow.php"
}

touch_workflow() {
    echo 'name: Hijacked' > "$CLONE/.github/workflows/ci.yml"
}

run_sut() {
    (cd "$CLONE" && bash "$SUT" "$RUN") >/dev/null 2>&1 || fail "The script exited with status $?"
}

gh_calls() {
    cut -d' ' -f1-2 "$GH_LOG" | tr '\n' ',' | sed 's/,$//'
}

gh_call() {
    sed -n "${1}p" "$GH_LOG"
}

gh_body() {
    cat "$GH_BODIES/$1.md" 2>/dev/null
}

# ---- Cases -------------------------------------------------------------------

color '36' "Case 1: a patch within the limits becomes a draft pull request, listed in the report issue"
setup_case accepted
add_patch 1 'Drop the duplicate helper' remove_helper
echo '- Inline a() into its caller: it has three, says @someone.' > "$RUN/rejected.md"
run_sut
assert_eq "Creates one pull request, then the report" "$(gh_calls)" "pr create,issue create"
assert_eq "Pushes the branch on master's commit" \
    "$(git -C "$ORIGIN" rev-parse -q --verify simplify/7-1^)" "$(git -C "$ORIGIN" rev-parse master)"
assert_eq "Commits the patch" "$(git -C "$ORIGIN" show simplify/7-1:src/Helpers.php | grep -c function)" 1
assert_eq "Gives the commit the patch's title, message and trailer" \
    "$(git -C "$ORIGIN" log -1 --format=%B simplify/7-1)" \
    "$(printf '%s\n\n%s\n%s\n\n%s' 'Drop the duplicate helper' 'Both helpers returned 1, so b() goes.' \
        'Thanks @someone.' 'Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>')"
assert_flags "Opens the pull request as a labelled draft" "$(gh_call 1)" \
    --draft '--base master' '--head simplify/7-1' '--label maintainability' '--title Drop the duplicate helper'
assert_contains "Puts the message in the pull request" "$(gh_body 1)" "Both helpers returned 1, so b() goes."
assert_contains "Says no human has reviewed it" "$(gh_body 1)" "**Outcome:** not reviewed by a human."
assert_contains "Carries Claude's calls" "$(gh_body 1)" "whether a() is a good name"
assert_contains "Carries the evidence" "$(gh_body 1)" "HelpersTest: 3 tests pass"
assert_lacks "Pings nobody from the pull request" "$(gh_body 1)" "@someone"
assert_flags "Titles the report with the run's date" "$(gh_call 2)" \
    '--label maintainability' '--title Simplification report 2026-11-07'
assert_contains "Links the pull request from the report" "$(gh_body report)" \
    "- https://github.com/example/NeoWiki/pull/1801"
assert_contains "Lists the rejected ideas" "$(gh_body report)" "Inline a() into its caller"
assert_lacks "Pings nobody from the report" "$(gh_body report)" "@someone"
assert_contains "Links the merges the run read" "$(gh_body report)" \
    "https://github.com/example/NeoWiki/compare/$SIMPLIFY_BASE...$(git -C "$CLONE" rev-parse master)"

color '36' "Case 2: patches outside the limits are dropped, and the report says why"
setup_case dropped
add_patch 1 'Simplify the CI for @someone' touch_workflow
add_patch 2 'Grow the helpers' grow_helper
add_patch 3 'Drop the helper again' remove_helper
printf 'garbage\n' > "$RUN/patches/3/change.patch"
add_patch 4 'Vendor a library' vendor_library
add_patch 5 'Untitled' remove_helper
rm "$RUN/patches/5/title.txt"
run_sut
assert_eq "Creates no pull request, only the report" "$(gh_calls)" "issue create"
assert_eq "Pushes no branch" "$(git -C "$ORIGIN" for-each-ref --format=x refs/heads/simplify | wc -l | tr -d ' ')" 0
assert_contains "Names the file outside the allowed paths" "$(gh_body report)" \
    "dropped because it changes .github/workflows/ci.yml"
assert_contains "Counts the lines of every file" "$(gh_body report)" \
    "Grow the helpers: dropped because it adds 3 lines and removes 2"
assert_contains "Names a patch that does not apply" "$(gh_body report)" \
    "Drop the helper again: dropped because it does not apply"
assert_contains "Checks the path of every file" "$(gh_body report)" \
    "Vendor a library: dropped because it changes vendor/"
assert_contains "Names a patch without a title by its number" "$(gh_body report)" \
    "Patch 5: dropped because it lacks a title or a message"
assert_contains "Says no pull request was opened" "$(gh_body report)" "None."
assert_lacks "Pings nobody from the report" "$(gh_body report)" "@someone"

color '36' "Case 3: one accepted patch next to a dropped one is numbered by its place in the run"
setup_case mixed
add_patch 1 'Simplify the CI' touch_workflow
add_patch 2 'Drop the duplicate helper for @someone' remove_helper
run_sut
assert_eq "Creates one pull request, then the report" "$(gh_calls)" "pr create,issue create"
assert_lacks "Pings nobody from the pull request's title" "$(gh_call 1)" "@someone"
assert_eq "Names the branch after the run and the patch's place" \
    "$(git -C "$ORIGIN" for-each-ref --format='%(refname:short)' refs/heads/simplify)" "simplify/7-2"

color '36' "Case 4: a run with no patch and no bug files nothing"
setup_case empty
printf '\n \n' > "$RUN/bugs.md"
echo '- Inline a() into its caller: it has three.' > "$RUN/rejected.md"
run_sut
assert_eq "Calls gh for nothing" "$(gh_calls)" ""

color '36' "Case 5: a run with only a bug files the report"
setup_case bug-only
echo '### The editor traps the selection, says @someone' > "$RUN/bugs.md"
run_sut
assert_eq "Creates only the report" "$(gh_calls)" "issue create"
assert_contains "Lists the bug" "$(gh_body report)" "### The editor traps the selection"
assert_lacks "Pings nobody from the report" "$(gh_body report)" "@someone"

color '36' "Case 6: a run opens at most three pull requests"
setup_case capped
for n in 1 2 3 4; do
    add_patch "$n" "Drop the duplicate helper, take $n" remove_helper
done
run_sut
assert_eq "Creates three pull requests, then the report" "$(gh_calls)" "pr create,pr create,pr create,issue create"
assert_contains "Names the fourth as over the cap" "$(gh_body report)" \
    "Drop the duplicate helper, take 4: dropped because the run already opened 3 pull requests"

color '36' "Case 7: a patch changing more than 600 lines is dropped"
setup_case big
add_patch 1 'Remove the big file' remove_big_file
run_sut
assert_contains "Names the size" "$(gh_body report)" \
    "Remove the big file: dropped because it changes 700 lines, more than 600"

color '36' "Case 8: a patch that does more than edit, add or delete regular files is dropped"
setup_case mode
add_patch 1 'Drop the helper, executably' make_helpers_executable
add_patch 2 'Link the workflow' link_workflow
add_patch 3 'Add a script' add_executable
run_sut
assert_contains "Names the mode change" "$(gh_body report)" \
    "Drop the helper, executably: dropped because it does more than edit, add or delete regular files"
assert_contains "Names the link" "$(gh_body report)" \
    "Link the workflow: dropped because it does more than edit, add or delete regular files"
assert_contains "Names the new executable" "$(gh_body report)" \
    "Add a script: dropped because it does more than edit, add or delete regular files"

color '36' "Case 9: text over the caps is cut on a character boundary"
setup_case long
add_patch 1 "$(printf 'y%.0s' {1..300})" remove_helper
{ printf 'x%.0s' {1..19999}; printf '\342\200\224 cut off\n'; } > "$RUN/patches/1/message.md"
run_sut
assert_eq "Still creates the pull request, then the report" "$(gh_calls)" "pr create,issue create"
assert_lacks "Cuts the message" "$(gh_body 1)" "cut off"
assert_flags "Cuts the title" "$(gh_call 1)" "--title $(printf 'y%.0s' {1..200})"

# ---- Summary -----------------------------------------------------------------

echo
if [ "$FAILS" -eq 0 ]; then
    color '32' "All $PASSES checks passed."
    exit 0
else
    color '31' "$FAILS check(s) failed ($PASSES passed)."
    exit 1
fi
