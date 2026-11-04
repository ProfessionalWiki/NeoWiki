#!/usr/bin/env bash
# Move this copy of the repository to a NeoWiki release, for `make upgrade version=X`, which then
# runs the release's demo image. A git checkout checks out the release's tag, and git refuses when
# that would overwrite a local change. Any other copy, such as an unpacked release archive, gets the
# release's archive unpacked over it: files of the user's own, such as Docker/.env, stay, and
# changes to the release's own files are overwritten.
#
# Usage: move-to-version.sh <version|latest>

set -euo pipefail

REPOSITORY_URL="${NEOWIKI_REPOSITORY_URL:-https://github.com/ProfessionalWiki/NeoWiki}"
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"

die() { echo "$*" >&2; exit 1; }

resolve_latest() {
    local location
    location="$(curl -fsS -o /dev/null -w '%{redirect_url}' "$REPOSITORY_URL/releases/latest")" \
        || die "Could not reach $REPOSITORY_URL to find the latest release."
    case "$location" in
        */releases/tag/*) printf '%s\n' "${location##*/releases/tag/}" ;;
        *) die "$REPOSITORY_URL has no release yet." ;;
    esac
}

# A version as tag-release.sh tags it, such as 0.2.0 or 0.2.0-rc.1. Anything else never reaches a
# URL or a git command.
is_version() { [[ "$1" =~ ^[0-9]+\.[0-9]+\.[0-9]+(-[0-9A-Za-z.]+)?$ ]]; }

# Compares only major.minor.patch, so a release counts as no older than its pre-releases.
older_than() {
    local a b i
    IFS=. read -r -a a <<< "${1%%-*}"
    IFS=. read -r -a b <<< "${2%%-*}"
    for i in 0 1 2; do
        if [ "${a[i]}" -ne "${b[i]}" ]; then [ "${a[i]}" -lt "${b[i]}" ]; return; fi
    done
    return 1
}

# MediaWiki's updater only moves a database forward. A copy that names no version, such as a
# checkout of master, has nothing to compare.
refuse_downgrade() {
    local current
    current="$(cat "$ROOT/Docker/image-tag" 2>/dev/null || true)"
    if is_version "$current" && older_than "$1" "$current"; then
        die "$1 is older than this copy's $current, and MediaWiki cannot move a database back to an older version."
    fi
}

move_checkout() {
    git -C "$ROOT" fetch --quiet --tags || die "Could not fetch the release tags."
    git -C "$ROOT" rev-parse --quiet --verify "refs/tags/$1^{commit}" >/dev/null || die "There is no release $1."
    git -C "$ROOT" cat-file -e "refs/tags/$1:Docker/image-tag" 2>/dev/null || die "Release $1 names no demo image."
    git -C "$ROOT" checkout --quiet "refs/tags/$1"
}

# Unpacks into a scratch directory first, so a failed download or a release without an image
# leaves the copy as it was.
move_unpacked() {
    SCRATCH="$(mktemp -d)"
    trap 'rm -rf "$SCRATCH"' EXIT
    curl -fsSL -o "$SCRATCH/release.tar.gz" "$REPOSITORY_URL/archive/refs/tags/$1.tar.gz" \
        || die "Could not download release $1 from $REPOSITORY_URL."
    mkdir "$SCRATCH/release"
    tar -xzf "$SCRATCH/release.tar.gz" -C "$SCRATCH/release" --strip-components=1
    [ "$(cat "$SCRATCH/release/Docker/image-tag" 2>/dev/null || true)" = "$1" ] || die "Release $1 names no demo image."
    cp -R "$SCRATCH/release/." "$ROOT/"
}

main() {
    local version="${1:-}"
    [ "$version" != latest ] || version="$(resolve_latest)"
    is_version "$version" || die "'${1:-}' is not a version, such as 0.2.0, or latest."
    refuse_downgrade "$version"

    echo "Moving this copy to NeoWiki $version."
    if [ -e "$ROOT/.git" ]; then
        move_checkout "$version"
    else
        move_unpacked "$version"
    fi
}

# Unpacking overwrites this file while bash reads it. Bash has read this whole line before it runs
# main, so it exits rather than read on into the new release's script.
main "$@"; exit
