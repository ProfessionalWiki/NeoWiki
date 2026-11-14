#!/usr/bin/env bash
# Opens a draft pull request for each patch a Simplification run left in the run directory given as $1, and a report
# issue that links them and lists the possible bugs and rejected ideas the run noted. Runs in a clean checkout of the
# commit the agents started from, and runs nothing they wrote: a patch outside the limits below is dropped, and the
# report says why.
#
# Reads SIMPLIFY_BASE (the commit before the merges the run read), SIMPLIFY_DATE, SIMPLIFY_MODEL, SIMPLIFY_EFFORT,
# GITHUB_REPOSITORY and GITHUB_RUN_ID. Commits with the git identity the environment sets.

set -euo pipefail

RUN_DIR=$1
LABEL=maintainability
MAX_PULL_REQUESTS=3
MAX_CHANGED_LINES=600
MAX_TEXT_BYTES=20000
MAX_TITLE_BYTES=200
ALLOWED_PATH='^(src|tests/phpunit|resources/ext\.neowiki/(src|tests))/.+\.(php|ts|vue|less|js)$'
ZERO_WIDTH_SPACE=$(printf '\342\200\213')
RUN_URL="https://github.com/$GITHUB_REPOSITORY/actions/runs/$GITHUB_RUN_ID"

head=$(git rev-parse HEAD)
work=$(mktemp -d)
trap 'rm -rf "$work"' EXIT
pull_requests=()
dropped=()

# Prints standard input capped at $1 bytes, without a multibyte character the cap cut.
capped() {
    # iconv -c drops what is not UTF-8, but still exits non-zero for it.
    head -c "$1" | { iconv -f UTF-8 -t UTF-8 -c 2>/dev/null || true; }
}

# Prints standard input with a zero-width space after every @, so that the agents' text pings nobody on GitHub.
inert() {
    sed "s/@/@$ZERO_WIDTH_SPACE/g"
}

# Succeeds when the file $1 holds more than whitespace.
has_content() {
    grep -q '[^[:space:]]' "$1" 2>/dev/null
}

title_of() {
    # Read through head -c, so that no write into the pipe is big enough to fail when a reader stops early.
    head -c 4096 "$1/title.txt" | head -n 1 | capped $MAX_TITLE_BYTES
}

compare_link() {
    echo "[${SIMPLIFY_BASE:0:8}...${head:0:8}](https://github.com/$GITHUB_REPOSITORY/compare/$SIMPLIFY_BASE...$head)"
}

# Prints the attribution line, with the further fields $1 after the model.
attribution() {
    printf '\n> <sub>AI-authored — Claude Code, `%s (%s)`%s</sub>\n' "$SIMPLIFY_MODEL" "$SIMPLIFY_EFFORT" "$1"
}

# Prints why the patch directory $1, titled $2, must not become a pull request, or nothing.
patch_problem() {
    local patch=$1/change.patch summary added=0 deleted=0 add delete path

    if [[ ! $2 =~ [^[:space:]] ]] || ! has_content "$1/message.md"; then
        echo 'it lacks a title or a message'
        return
    fi
    if ! git apply --check "$patch" 2>/dev/null; then
        echo "it does not apply to $head"
        return
    fi
    # Captured whole: a reader that stops early would let pipefail turn a match into a miss.
    summary=$(git apply --summary "$patch")
    if [ -n "$(grep -v -E '^ (create|delete) mode 100644 ' <<< "$summary")" ]; then
        echo 'it does more than edit, add or delete regular files'
        return
    fi

    while IFS=$'\t' read -r add delete path; do
        if [ "$add" = - ]; then
            echo "it changes the binary file $path"
            return
        fi
        if [[ ! $path =~ $ALLOWED_PATH ]]; then
            echo "it changes $path"
            return
        fi
        added=$((added + add))
        deleted=$((deleted + delete))
    done < <(git apply --numstat "$patch")

    if [ "$added" -gt "$deleted" ]; then
        echo "it adds $added lines and removes $deleted"
    elif [ $((added + deleted)) -gt $MAX_CHANGED_LINES ]; then
        echo "it changes $((added + deleted)) lines, more than $MAX_CHANGED_LINES"
    fi
}

pull_request_body() {
    capped $MAX_TEXT_BYTES < "$1/message.md"
    cat <<EOF

## Human input

**Goal and shape:** Claude's, from the weekly Simplification workflow's [run]($RUN_URL) over the merges in
$(compare_link).

**Outcome:** not reviewed by a human. Left for you: the diff.
EOF
    if has_content "$1/calls.md"; then
        printf "\n## Claude's calls\n\n"
        capped $MAX_TEXT_BYTES < "$1/calls.md"
    fi
    if has_content "$1/evidence.md"; then
        printf '\n<details><summary>Verification evidence</summary>\n\n'
        capped $MAX_TEXT_BYTES < "$1/evidence.md"
        printf '\n</details>\n'
    fi
    attribution ''
}

# Opens patch directory $1, titled $3, as the run's pull request number $2.
open_pull_request() {
    local branch="simplify/$GITHUB_RUN_ID-$2"

    git switch -q -c "$branch" "$head"
    git apply --index "$1/change.patch"
    printf '%s\n\n%s\n\nCo-Authored-By: Claude %s <noreply@anthropic.com>\n' \
        "$3" "$(capped $MAX_TEXT_BYTES < "$1/message.md")" "$SIMPLIFY_MODEL" | git commit -q -F -
    git push -q origin "$branch"
    git switch -q --detach "$head"

    pull_request_body "$1" | inert > "$work/body.md"
    pull_requests+=( "$(gh pr create --draft --base master --head "$branch" --label "$LABEL" \
        --title "$(inert <<< "$3")" --body-file "$work/body.md")" )
}

report_body() {
    local fields="; Claude's, unasked; not read by a human before posting;"
    fields+=" each pull request and possible bug checked by a fresh-context Claude review, no bug reproduced."

    echo "The weekly Simplification workflow's [run]($RUN_URL) read the merges in $(compare_link)."
    printf '\n## Pull requests\n\n'
    if [ ${#pull_requests[@]} -eq 0 ]; then
        echo 'None.'
    else
        printf -- '- %s\n' "${pull_requests[@]}"
    fi
    if [ ${#dropped[@]} -gt 0 ]; then
        printf '\n## Dropped by the checks\n\n'
        printf -- '- %s\n' "${dropped[@]}"
    fi
    if has_content "$RUN_DIR/bugs.md"; then
        printf '\n## Possible bugs\n\n'
        capped $MAX_TEXT_BYTES < "$RUN_DIR/bugs.md"
    fi
    if has_content "$RUN_DIR/rejected.md"; then
        printf '\n## Considered, rejected\n\n'
        capped $MAX_TEXT_BYTES < "$RUN_DIR/rejected.md"
    fi
    attribution "$fields"
}

number=0
for dir in "$RUN_DIR"/patches/*/; do
    [ -d "$dir" ] || continue
    number=$((number + 1))
    title=$(title_of "$dir" 2>/dev/null) || true
    problem=$(patch_problem "$dir" "$title")
    if [ -z "$problem" ] && [ ${#pull_requests[@]} -ge $MAX_PULL_REQUESTS ]; then
        problem="the run already opened $MAX_PULL_REQUESTS pull requests"
    fi

    if [ -n "$problem" ]; then
        dropped+=( "${title:-Patch $number}: dropped because $problem" )
    else
        open_pull_request "$dir" "$number" "$title"
    fi
done

if [ ${#pull_requests[@]} -gt 0 ] || [ ${#dropped[@]} -gt 0 ] || has_content "$RUN_DIR/bugs.md"; then
    report_body | inert > "$work/report.md"
    gh issue create --label "$LABEL" --title "Simplification report $SIMPLIFY_DATE" --body-file "$work/report.md"
fi
