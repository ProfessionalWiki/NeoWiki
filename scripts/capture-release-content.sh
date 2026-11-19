#!/usr/bin/env bash
# Saves the JSON a wiki stores for each page given into tests/release-content/<version>/, where the tests read it with
# the code on master. Run it against a wiki of the release; see RELEASING.md.
#
# Usage: scripts/capture-release-content.sh <api.php URL> <version> <page title>...
#
# A Schema, Layout or Mapping page is saved under the directory of its content model, named by its title without the
# namespace. Any other page is saved under Subject/ as the Subjects it holds.

set -euo pipefail

if [ $# -lt 3 ]; then
    echo "Usage: $0 <api.php URL> <version> <page title>..." >&2
    exit 1
fi

API=$1
VERSION=$2
shift 2

OUT="$(cd "$(dirname "$0")/.." && pwd)/tests/release-content/$VERSION"

for title in "$@"; do
    revision=$(curl -fsS -G "$API" --data-urlencode "titles=$title" \
        -d 'action=query&prop=revisions&rvprop=content&rvslots=*&formatversion=2&format=json' |
        jq -e '.query.pages[0].revisions[0]') || { echo "No page $title" >&2; exit 1; }

    case $(jq -r '.slots.main.contentmodel' <<< "$revision") in
        NeoWikiSchema) directory=Schema slot=main name=${title#*:} ;;
        NeoWikiLayout) directory=Layout slot=main name=${title#*:} ;;
        NeoWikiMapping) directory=Mapping slot=main name=${title#*:} ;;
        *) directory=Subject slot=neo name=$title ;;
    esac

    content=$(jq -j -e --arg slot "$slot" '.slots[$slot].content' <<< "$revision") ||
        { echo "$title holds no Subjects" >&2; exit 1; }

    mkdir -p "$OUT/$directory"
    printf '%s' "$content" > "$OUT/$directory/${name// /_}.json"
done
