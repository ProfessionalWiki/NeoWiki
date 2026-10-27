#!/usr/bin/env bash
# Signs a release tag on a build commit: a commit holding a master commit's tree plus the frontend bundle that the
# master run of build-docker.yml built for it, and Docker/image-tag, which names the version's demo image for the
# bundled Makefile. Master holds neither; the tag, and so Composer and GitHub's archives, do. Given a pre-release's
# build commit instead, such as its tag, it builds on that pre-release's master commit and refuses a result that
# differs from the pre-release in more than Docker/image-tag. Leaves the checkout and its index untouched, and the tag
# unpushed.

set -euo pipefail

BUNDLE_DIR=resources/ext.neowiki/dist
BUNDLE_FILES=( neowiki.css neowiki.js )
IMAGE_TAG_FILE=Docker/image-tag
ARTIFACT=frontend-bundle
MASTER=refs/remotes/origin/master
NUMBER='(0|[1-9][0-9]*)'

die() {
    echo "tag-release: $*" >&2
    exit 1
}

on_master() {
    git merge-base --is-ancestor "$1" "$MASTER"
}

# Sets $tree to the master commit $1's tree plus the bundle its master run built and the image tag of $version, and
# $url to that run.
build_tree() {
    local runs run file blob

    runs=$(gh run list --workflow build-docker.yml --branch master --commit "$1" --status success --limit 1 \
        --json databaseId,url --jq '.[] | "\(.databaseId) \(.url)"')
    read -r run url <<< "$runs"
    [ -n "$run" ] || die "build-docker.yml has no successful master run for $1"

    gh run download "$run" --name "$ARTIFACT" --dir "$work/bundle" ||
        die "cannot download the $ARTIFACT artifact of $url; it expires after 90 days, and older runs have none"

    export GIT_INDEX_FILE=$work/index
    git read-tree "$1"
    for file in "${BUNDLE_FILES[@]}"; do
        blob=$(git hash-object -w --no-filters "$work/bundle/$file")
        git update-index --add --cacheinfo "100644,$blob,$BUNDLE_DIR/$file"
    done
    blob=$(printf '%s\n' "$version" | git hash-object -w --stdin)
    git update-index --add --cacheinfo "100644,$blob,$IMAGE_TAG_FILE"
    tree=$(git write-tree)
    unset GIT_INDEX_FILE
}

[ $# -ge 1 ] && [ $# -le 2 ] ||
    die "usage: $0 <version> [<master commit, default origin's master> | <pre-release's build commit>]"
version=$1
target=${2:-$MASTER}
[[ $version =~ ^$NUMBER\.$NUMBER\.$NUMBER(-(alpha|beta|rc)\.$NUMBER)?$ ]] ||
    die "$version is not a version such as 0.1.0 or 0.1.0-rc.1"

# The repository whose master runs build the bundle; gh would otherwise pick one from the remotes.
export GH_REPO=ProfessionalWiki/NeoWiki

git fetch -q --tags origin "+refs/heads/master:$MASTER" ||
    die "cannot fetch from origin; 'git fetch --tags origin' shows why, such as a local tag that differs from origin's"
if git rev-parse -q --verify "refs/tags/$version" >/dev/null; then
    die "tag $version already exists"
fi
commit=$(git rev-parse -q --verify "$target^{commit}") || die "$target is not a commit"

work=$(mktemp -d)
trap 'rm -rf "$work"' EXIT

if on_master "$commit"; then
    base=$commit
    build_tree "$base"
elif base=$(git rev-parse -q --verify "$commit^") && on_master "$base" &&
    [ "$(git log -1 --format=%P "$commit")" = "$base" ]; then
    build_tree "$base"
    [ "$(git diff --name-only "$commit" "$tree")" = "$IMAGE_TAG_FILE" ] ||
        die "$target differs in more than $IMAGE_TAG_FILE from its parent plus the bundle that $url built"
else
    die "$target is neither on origin's master nor a build commit on top of it"
fi

build=$(git commit-tree -S "$tree" -p "$base" -m "Add the frontend bundle and the $version image tag" \
    -m "Built by $url")
git tag -s "$version" -m "NeoWiki $version" "$build"

echo "Signed $version on $build, which adds the frontend bundle and $IMAGE_TAG_FILE to $base."
echo "Check it with 'git show --stat $version', then publish it with 'git push origin refs/tags/$version'."
