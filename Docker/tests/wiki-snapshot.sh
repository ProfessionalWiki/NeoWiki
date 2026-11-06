#!/usr/bin/env bash
# Prints whether each page and Subject of the running stack's wiki loads, and which Subjects its graph stores hold, one
# sorted line each, so that snapshots taken before and after an upgrade differ where the upgrade lost one. It leaves out
# what Subjects hold, which an upgrade may rewrite on purpose. The stack must run the qlever and neo services.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
. "$SCRIPT_DIR/../.env"

WIKI="http://localhost:$MW_SERVER_PORT/w"

CYPHER='MATCH (:Page)-[:HasSubject]->(subject:Subject) RETURN subject.id AS id'
SPARQL='SELECT DISTINCT ?subject WHERE {
    ?page ?predicate ?subject FILTER(STRENDS(STR(?predicate), "/ontology/hasSubject"))
}'

get() {
    curl -fsS "$WIKI/$1" || { echo "wiki-snapshot: GET $1 failed" >&2; return 1; }
}

run_query() {
    curl -fsS -H 'Content-Type: application/json' --data "$2" "$WIKI/rest.php/neowiki/v0/query/$1" ||
        { echo "wiki-snapshot: the $1 query failed" >&2; return 1; }
}

render_status() {
    curl -sSL -o /dev/null -w '%{http_code}' -G --data-urlencode "title=$1" "$WIKI/index.php"
}

subject_status() {
    local body

    if body=$(curl -fsS "$WIKI/rest.php/neowiki/v0/subject/$1"); then
        jq -r --arg id "$1" 'if .subjects[$id] then "loads" else "missing" end' <<< "$body"
    else
        echo unreadable
    fi
}

pages_and_subjects() {
    local namespaces namespace pages page_id title subject_ids subject_id

    namespaces=$(get 'api.php?action=query&meta=siteinfo&siprop=namespaces&format=json' |
        jq -r '.query.namespaces[] | select(.id >= 0) | .id')

    for namespace in $namespaces; do
        pages=$(get "api.php?action=query&list=allpages&apnamespace=$namespace&aplimit=max&format=json" |
            jq -r '.query.allpages[] | "\(.pageid) \(.title)"')
        [ -n "$pages" ] || continue

        while read -r page_id title; do
            echo "page $title $(render_status "$title")"

            subject_ids=$(get "rest.php/neowiki/v0/page/$page_id/subjects" | jq -r '.subjects | keys[]')
            for subject_id in $subject_ids; do
                echo "subject $subject_id $(subject_status "$subject_id")"
            done
        done <<< "$pages"
    done
}

graph_store_subjects() {
    run_query cypher "$(jq -n --arg cypher "$CYPHER" '{$cypher}')" | jq -r '.rows[] | "neo4j \(.id)"'

    run_query sparql "$(jq -n --arg query "$SPARQL" '{$query}')" |
        jq -r '.results.bindings[] | "sparql \(.subject.value | sub(".*/"; ""))"'
}

# A file rather than a command substitution, which would not exit on a failed request.
snapshot=$(mktemp)
trap 'rm -f "$snapshot"' EXIT

{ pages_and_subjects; graph_store_subjects; } | LC_ALL=C sort > "$snapshot"

# A seed that failed would otherwise pass as an upgrade that kept everything.
for kind in subject neo4j sparql; do
    grep -q "^$kind " "$snapshot" || { echo "wiki-snapshot: found no $kind lines" >&2; exit 1; }
done

cat "$snapshot"
