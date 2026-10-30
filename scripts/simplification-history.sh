#!/usr/bin/env bash
# Prints, as Markdown, what earlier Simplification runs proposed and what maintainers said about it: the pull requests
# the machine account opened from simplify/ branches, by state, and its latest report issues. The agents take these
# comments as maintainers' word, so only owners, members, collaborators and contributors count: anyone can comment on a
# public repository. Contributors, people with a merged commit, count because a member whose membership is private
# shows as one.
#
# Reads SIMPLIFY_MACHINE_ACCOUNT; gh needs a token that can read the repository's issues and pull requests.

set -euo pipefail

REMARKS='
    def remarks: [ (.comments[]?, .reviews[]?)
        | select(
            ( .authorAssociation | IN( "OWNER", "MEMBER", "COLLABORATOR", "CONTRIBUTOR" ) ) and ( .body // "" ) != ""
        )
        | "  - \( .author.login ): \( .body | gsub( "\\s+"; " " ) | .[:1000] )" ] | .[];
'

pull_requests=$(gh pr list --author "$SIMPLIFY_MACHINE_ACCOUNT" --state all --limit 60 \
    --json title,url,state,headRefName,comments,reviews)

# Prints the pull requests in state $1 under the heading $2.
pull_requests_in() {
    printf '## %s\n\n' "$2"
    jq -r --arg state "$1" "$REMARKS"'
        .[] | select( .state == $state and ( .headRefName | startswith( "simplify/" ) ) )
        | "- [\( .title )](\( .url ))", remarks
    ' <<< "$pull_requests"
    echo
}

echo '# Earlier Simplification runs'
echo
pull_requests_in OPEN 'Open'
pull_requests_in MERGED 'Merged'
pull_requests_in CLOSED 'Closed without merging'

echo '## Latest reports'
echo
# Sorted, since a search otherwise returns its best matches first rather than the latest.
gh issue list --author "$SIMPLIFY_MACHINE_ACCOUNT" --state all --limit 3 \
    --search 'Simplification report in:title sort:created-desc' \
    --json title,url,body,comments |
    jq -r "$REMARKS"'.[] | "### [\( .title )](\( .url ))\n\n\( .body )\n\nMaintainers'"'"' comments:\n", remarks, ""'
