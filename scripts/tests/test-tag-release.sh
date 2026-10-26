#!/usr/bin/env bash
# Exercise scripts/tag-release.sh against a throwaway origin, with gh stubbed and
# tags signed by a throwaway SSH key.

set -u

# Git exports GIT_DIR and its kin to the commands it runs, such as `git rebase -x`
# and hooks; left set, they point the git calls below at the developer's repository.
# shellcheck disable=SC2046 # one variable name per word
unset $(git rev-parse --local-env-vars)

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
SUT="$SCRIPT_DIR/../tag-release.sh"
BUNDLE_DIR=resources/ext.neowiki/dist

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

# --file rather than --global: git older than 2.32 ignores GIT_CONFIG_GLOBAL and
# would write to the developer's ~/.gitconfig.
export GIT_CONFIG_GLOBAL="$WORK/gitconfig"
export GIT_CONFIG_NOSYSTEM=1
ssh-keygen -q -t ed25519 -N '' -f "$WORK/signing-key"
git config --file "$GIT_CONFIG_GLOBAL" user.name 'Release Test'
git config --file "$GIT_CONFIG_GLOBAL" user.email 'release-test@example.org'
git config --file "$GIT_CONFIG_GLOBAL" init.defaultBranch master
git config --file "$GIT_CONFIG_GLOBAL" gpg.format ssh
git config --file "$GIT_CONFIG_GLOBAL" user.signingkey "$WORK/signing-key"

# Stands in for gh, newest run first: 4241 failed and 4243 ran off master, both for
# $BUILT_COMMIT and holding the decoy bundle; 4242 is $BUILT_COMMIT's successful
# master run, holding $ARTIFACT_DIR. Prints `id url`, as the script's --jq does.
# Without a run id, gh downloads the newest artifact: the decoy. Downloads are
# logged to $DOWNLOAD_LOG.
mkdir "$WORK/bin"
cat > "$WORK/bin/gh" <<'EOF'
#!/usr/bin/env bash
command="$1 $2"; shift 2
workflow='' branch='' status='' commit='' name='' dir=. run=''
while [ $# -gt 0 ]; do
    case "$1" in
        --workflow) workflow=$2; shift ;;
        --name) name=$2; shift ;;
        --branch) branch=$2; shift ;;
        --status) status=$2; shift ;;
        --commit) commit=$2; shift ;;
        --dir) dir=$2; shift ;;
        --*) shift ;;
        *) run=$1 ;;
    esac
    shift
done
case "$command" in
    'run list')
        for listed in '4241 master failure' '4243 feature success' '4242 master success'; do
            set -- $listed
            if [ "$workflow" = build-docker.yml ] && [ "$commit" = "$BUILT_COMMIT" ] &&
                { [ -z "$branch" ] || [ "$branch" = "$2" ]; } &&
                { [ -z "$status" ] || [ "$status" = "$3" ]; }; then
                echo "$1 https://github.com/example/actions/runs/$1"
                break
            fi
        done
        ;;
    'run download')
        echo "$run" >> "$DOWNLOAD_LOG"
        [ "$name" = frontend-bundle ] || { echo 'no artifact matches any of the names' >&2; exit 1; }
        case "$run" in
            4241|4243|'') source_dir=$DECOY_DIR ;;
            4242) source_dir=$ARTIFACT_DIR ;;
            *) echo 'no valid artifacts found to download' >&2; exit 1 ;;
        esac
        mkdir -p "$dir" && cp "$source_dir"/* "$dir"/
        ;;
esac
EOF
chmod +x "$WORK/bin/gh"
export PATH="$WORK/bin:$PATH" DOWNLOAD_LOG="$WORK/downloads"

export DECOY_DIR="$WORK/decoy"
mkdir "$DECOY_DIR"
echo 'console.log( "decoy" );' > "$DECOY_DIR/neowiki.js"
echo '.decoy {}' > "$DECOY_DIR/neowiki.css"

# The fixture every case copies: an origin whose master has the commits $FIRST and
# $SECOND, a clone of it, and the artifact of $SECOND's run.
TEMPLATE="$WORK/template"
git init -q --bare "$TEMPLATE/origin.git"
git clone -q "$TEMPLATE/origin.git" "$TEMPLATE/clone" 2>/dev/null
mkdir -p "$TEMPLATE/clone/resources/ext.neowiki"
echo 'dist/' > "$TEMPLATE/clone/resources/ext.neowiki/.gitignore"
echo first > "$TEMPLATE/clone/README.md"
git -C "$TEMPLATE/clone" add -A
git -C "$TEMPLATE/clone" commit -q -m first
FIRST=$(git -C "$TEMPLATE/clone" rev-parse HEAD)
echo second > "$TEMPLATE/clone/README.md"
git -C "$TEMPLATE/clone" commit -q -am second
SECOND=$(git -C "$TEMPLATE/clone" rev-parse HEAD)
git -C "$TEMPLATE/clone" push -q origin master
mkdir "$TEMPLATE/artifact"
echo 'console.log( "built" );' > "$TEMPLATE/artifact/neowiki.js"
echo '.built {}' > "$TEMPLATE/artifact/neowiki.css"

# Sets $ORIGIN, $CLONE and $ARTIFACT_DIR to a fresh copy of the fixture.
setup_case() {
    local dir="$WORK/$1"
    cp -R "$TEMPLATE" "$dir"
    ORIGIN="$dir/origin.git"
    CLONE="$dir/clone"
    git -C "$CLONE" remote set-url origin "$ORIGIN"
    export ARTIFACT_DIR="$dir/artifact" BUILT_COMMIT=$SECOND
    rm -f "$DOWNLOAD_LOG"
}

# Pushes a commit onto origin's master from another clone, and prints it.
push_to_master() {
    local other
    other=$(mktemp -d "$WORK/other.XXXXXX")
    git clone -q "$ORIGIN" "$other" 2>/dev/null
    git -C "$other" commit -q --allow-empty -m "$1"
    git -C "$other" push -q origin master
    git -C "$other" rev-parse HEAD
}

# Tags, from another clone, a commit on top of master that only origin then has.
tag_on_origin() {
    local other
    other=$(mktemp -d "$WORK/other.XXXXXX")
    git clone -q "$ORIGIN" "$other" 2>/dev/null
    git -C "$other" commit -q --allow-empty -m "$1 elsewhere"
    git -C "$other" tag "$1"
    git -C "$other" push -q origin "refs/tags/$1"
}

# Commits the bundle in the given directory by hand in $CLONE, on top of HEAD.
commit_bundle_by_hand() {
    mkdir -p "$CLONE/$BUNDLE_DIR"
    cp "$1"/* "$CLONE/$BUNDLE_DIR/"
    git -C "$CLONE" add -f "$BUNDLE_DIR"
    git -C "$CLONE" commit -q -m 'bundle by hand'
}

run_sut() {
    (cd "$CLONE" && bash "$SUT" "$@") >/dev/null 2>&1
}

sut_errors() {
    # shellcheck disable=SC2069 # stderr alone, on purpose
    (cd "$CLONE" && bash "$SUT" "$@") 2>&1 >/dev/null
}

tagged_commit() {
    git -C "$CLONE" rev-parse -q --verify "refs/tags/$1^{commit}" || echo none
}

tag_parent() {
    git -C "$CLONE" rev-parse -q --verify "refs/tags/$1^{commit}^" || echo none
}

added_files() {
    git -C "$CLONE" diff --name-only "$1" "$2" | tr '\n' ' '
}

# Runs the script with the arguments after the label, the first being the version,
# and asserts that it fails, leaving the clone with origin's tag of that version, if any.
assert_refused() {
    local label=$1 version=$2 origin_tag
    shift
    origin_tag=$(git -C "$ORIGIN" rev-parse -q --verify "refs/tags/$version^{commit}" || echo none)

    assert_eq "$label: exits with an error" "$(run_sut "$@" && echo success || echo error)" error
    assert_eq "$label: signs no tag" "$(tagged_commit "$version")" "$origin_tag"
}

# ---- Cases -------------------------------------------------------------------

color '36' "Case 1: the build commit adds the bundle on top of master's head"
setup_case head
run_sut 0.1.0
assert_eq "Parent is master's head" "$(tag_parent 0.1.0)" "$SECOND"
assert_eq "Adds only the bundle" "$(added_files "$SECOND" 0.1.0)" "$BUNDLE_DIR/neowiki.css $BUNDLE_DIR/neowiki.js "
assert_eq "Holds the successful master run's JavaScript" \
    "$(git -C "$CLONE" show "0.1.0:$BUNDLE_DIR/neowiki.js" 2>&1)" 'console.log( "built" );'
assert_eq "Holds the successful master run's stylesheet" \
    "$(git -C "$CLONE" show "0.1.0:$BUNDLE_DIR/neowiki.css" 2>&1)" '.built {}'
assert_eq "Links the run that built it" \
    "$(git -C "$CLONE" log -1 --format=%B 0.1.0 | grep -c 'https://github.com/example/actions/runs/4242')" "1"

echo
color '36' "Case 2: the tag and its build commit are signed"
setup_case signed
run_sut 0.1.0
assert_eq "Tag carries an SSH signature" \
    "$(git -C "$CLONE" cat-file tag 0.1.0 2>/dev/null | grep -c 'BEGIN SSH SIGNATURE')" "1"
assert_eq "Build commit carries an SSH signature" \
    "$(git -C "$CLONE" cat-file commit '0.1.0^{commit}' 2>/dev/null | grep -c 'BEGIN SSH SIGNATURE')" "1"

echo
color '36' "Case 3: an older master commit can be tagged"
setup_case older
BUILT_COMMIT=$FIRST
run_sut 0.1.0 "$FIRST"
assert_eq "Parent is the given commit" "$(tag_parent 0.1.0)" "$FIRST"
assert_eq "Adds only the bundle to it" "$(added_files "$FIRST" 0.1.0)" \
    "$BUNDLE_DIR/neowiki.css $BUNDLE_DIR/neowiki.js "

echo
color '36' "Case 4: the default is origin's current master, whatever the clone tracks"
setup_case default
BUILT_COMMIT=$(push_to_master third)
git -C "$CLONE" branch origin/master "$FIRST"
git -C "$CLONE" config remote.origin.fetch '+refs/heads/other:refs/remotes/origin/other'
run_sut 0.1.0
assert_eq "Parent is origin's master" "$(tag_parent 0.1.0)" "$BUILT_COMMIT"
assert_eq "Leaves the branch named origin/master alone" \
    "$(git -C "$CLONE" rev-parse refs/heads/origin/master)" "$FIRST"

echo
color '36' "Case 5: the checkout, its index and origin are left as they were"
setup_case untouched
echo staged > "$CLONE/README.md"
git -C "$CLONE" add README.md
run_sut 0.1.0
assert_eq "Tag exists" "$(tag_parent 0.1.0)" "$SECOND"
assert_eq "Checkout holds only the staged change" "$(git -C "$CLONE" status --porcelain --ignored)" \
    "M  README.md"
assert_eq "Origin has no tag" "$(git -C "$ORIGIN" tag)" ""

echo
color '36' "Case 6: a commit without a successful master run is refused"
setup_case unbuilt
BUILT_COMMIT=$FIRST
assert_refused "Unbuilt commit" 0.1.0

echo
color '36' "Case 7: an artifact without the stylesheet is refused"
setup_case no-css
rm "$ARTIFACT_DIR/neowiki.css"
assert_refused "Incomplete artifact" 0.1.0

echo
color '36' "Case 8: a version tagged on origin is refused before any download or signing"
setup_case existing
tag_on_origin 0.1.0
assert_refused "Existing version" 0.1.0
assert_eq "Downloads nothing" "$(cat "$DOWNLOAD_LOG" 2>/dev/null)" ""

echo
color '36' "Case 9: a local tag that differs from origin's stops the script with a message"
setup_case clobber
tag_on_origin 0.0.9
git -C "$CLONE" tag 0.0.9 "$FIRST"
assert_refused "Differing local tag" 0.1.0
assert_eq "Says why" "$(sut_errors 0.1.0 | grep -c '^tag-release: ')" "1"

echo
color '36' "Case 10: versions other than X.Y.Z and X.Y.Z-alpha|beta|rc.N are refused"
setup_case versions
assert_refused "v prefix" v0.1.0
assert_refused "Pre-release suffix Composer skips" 0.1.0-preview.1
assert_refused "Leading zero" 0.01.0
assert_refused "Leading zero in the pre-release number" 0.1.0-rc.01
assert_refused "A third argument" 0.1.0 "$SECOND" extra

echo
color '36' "Case 11: alpha and beta pre-releases are tagged"
setup_case pre-releases
run_sut 0.1.0-alpha.1
run_sut 0.1.0-beta.1
assert_eq "Alpha is a build commit on master's head" "$(tag_parent 0.1.0-alpha.1)" "$SECOND"
assert_eq "Beta is a build commit on master's head" "$(tag_parent 0.1.0-beta.1)" "$SECOND"

echo
color '36' "Case 12: a pre-release's build commit, tagged in another clone, is tagged as is"
setup_case promote
git clone -q "$ORIGIN" "$WORK/promote/older-clone" 2>/dev/null
# SSH signatures are deterministic, so a build commit made again within the same
# second would get the pre-release's hash; an older date keeps them apart.
GIT_COMMITTER_DATE='2026-01-01T00:00:00Z' run_sut 0.1.0-rc.1
pre_release=$(tagged_commit 0.1.0-rc.1)
git -C "$CLONE" push -q origin 0.1.0-rc.1
push_to_master later >/dev/null
CLONE="$WORK/promote/older-clone"
run_sut 0.1.0 0.1.0-rc.1
assert_eq "Pre-release is a build commit on master" "$(git -C "$CLONE" rev-parse "$pre_release^" 2>&1)" "$SECOND"
assert_eq "Release tag points at the pre-release's commit" \
    "$(git -C "$CLONE" cat-file tag 0.1.0 2>/dev/null | sed -n 's/^object //p')" "$pre_release"

echo
color '36' "Case 13: commits that are neither on master nor its run's build commit are refused"
setup_case unmerged
git -C "$CLONE" commit -q --allow-empty -m unmerged
BUILT_COMMIT=$(git -C "$CLONE" rev-parse HEAD)
assert_refused "Unmerged commit" 0.1.0 HEAD

setup_case unmerged-base
git -C "$CLONE" commit -q --allow-empty -m unmerged
BUILT_COMMIT=$(git -C "$CLONE" rev-parse HEAD)
commit_bundle_by_hand "$ARTIFACT_DIR"
git -C "$CLONE" tag 0.1.0-rc.1
assert_refused "Bundle on an unmerged commit" 0.1.0 0.1.0-rc.1

setup_case extra-change
echo changed > "$CLONE/README.md"
git -C "$CLONE" add README.md
commit_bundle_by_hand "$ARTIFACT_DIR"
git -C "$CLONE" tag 0.1.0-rc.1
assert_refused "Bundle plus a change" 0.1.0 0.1.0-rc.1

setup_case other-bundle
commit_bundle_by_hand "$DECOY_DIR"
git -C "$CLONE" tag 0.1.0-rc.1
assert_refused "Bundle unlike the run's" 0.1.0 0.1.0-rc.1

setup_case second-parent
git -C "$CLONE" commit -q --allow-empty -m unmerged
side=$(git -C "$CLONE" rev-parse HEAD)
git -C "$CLONE" checkout -q "$SECOND"
commit_bundle_by_hand "$ARTIFACT_DIR"
git -C "$CLONE" tag 0.1.0-rc.1 \
    "$(git -C "$CLONE" commit-tree 'HEAD^{tree}' -p "$SECOND" -p "$side" -m 'bundle with a second parent')"
assert_refused "Bundle with a second parent" 0.1.0 0.1.0-rc.1

# ---- Summary -----------------------------------------------------------------

echo
if [ "$FAILS" -eq 0 ]; then
    color '32' "All $PASSES checks passed."
    exit 0
else
    color '31' "$FAILS check(s) failed ($PASSES passed)."
    exit 1
fi
