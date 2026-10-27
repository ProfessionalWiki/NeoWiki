#!/usr/bin/env bash
# Exercise Docker/scripts/move-to-version.sh, which `make upgrade version=X` runs, in throwaway copies of the
# repository: unpacked release archives, and clones of a local origin. A local HTTP server stands in for GitHub's
# release archives and its releases/latest redirect, so nothing reaches the network.

set -u

# Git exports GIT_DIR and its kin to the commands it runs, such as `git rebase -x` and hooks; left
# set, they point the git calls below at the developer's repository.
# shellcheck disable=SC2046 # one variable name per word
unset $(git rev-parse --local-env-vars)

# When `make test-scripts` runs this script, the outer make's global `export` hands it the values
# that make derives, such as NEOWIKI_IMAGE_TAG and COMPOSE_PROFILES, and MAKEFLAGS carries its
# command-line variables; any of them would leak into the makes under test.
unset NEOWIKI_IMAGE_TAG COMPOSE_PROFILES MARIADB_HOST MARIADB_PORT NEO4J_HOST OXIGRAPH_URL QLEVER_URL \
    MW_SMTP_HOST MAKEFLAGS version services

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"

WORK="$(mktemp -d)"
SERVER_PID=
trap '[ -z "$SERVER_PID" ] || kill "$SERVER_PID" 2>/dev/null; rm -rf "$WORK"' EXIT

PASSES=0
FAILS=0
color() { local c=$1; shift; printf '\033[%sm%s\033[0m\n' "$c" "$*"; }
ok()   { color '32' "PASS: $*"; PASSES=$((PASSES + 1)); }
fail() { color '31' "FAIL: $*"; FAILS=$((FAILS + 1)); }

assert_eq() {
    local got=$1 want=$2 msg=$3
    if [ "$got" = "$want" ]; then ok "$msg"; else fail "$msg (got '$got', want '$want')"; fi
}

# Serves $WORK/site. /releases/latest redirects to /releases/tag/<the version in site/latest>, or
# to /releases when there is no such file, as GitHub does before a first release.
mkdir -p "$WORK/site/archive/refs/tags"
cat > "$WORK/server.py" <<'EOF'
import http.server, os, sys

site = sys.argv[1]

class Handler(http.server.SimpleHTTPRequestHandler):
    def __init__(self, *args, **kwargs):
        super().__init__(*args, directory=site, **kwargs)

    def do_GET(self):
        if self.path == '/releases/latest':
            latest = os.path.join(site, 'latest')
            target = '/releases'
            if os.path.exists(latest):
                target = '/releases/tag/' + open(latest).read().strip()
            self.send_response(302)
            self.send_header('Location', 'http://%s:%d%s' % (*self.server.server_address[:2], target))
            self.end_headers()
            return
        super().do_GET()

    def log_message(self, *args):
        pass

server = http.server.HTTPServer(('127.0.0.1', 0), Handler)
print(server.server_address[1], flush=True)
server.serve_forever()
EOF
python3 -I "$WORK/server.py" "$WORK/site" > "$WORK/port" &
SERVER_PID=$!
disown
for _ in $(seq 1 50); do [ -s "$WORK/port" ] && break; sleep 0.1; done
export NEOWIKI_REPOSITORY_URL="http://127.0.0.1:$(cat "$WORK/port")"

# The stub docker logs each call with the image tag, profiles and SPARQL store URLs compose would
# get. The stub curl passes requests for the local server to the real curl and answers the wait for
# MediaWiki, so `make upgrade` runs to the end without reaching a real stack.
REAL_CURL="$(command -v curl)"
mkdir "$WORK/bin"
cat > "$WORK/bin/docker" <<'EOF'
#!/usr/bin/env bash
printf 'tag=%s profiles=%s qlever=%s oxigraph=%s %s\n' "${NEOWIKI_IMAGE_TAG:-}" "${COMPOSE_PROFILES:-}" \
    "${QLEVER_URL:-}" "${OXIGRAPH_URL:-}" "$*" >> "$DOCKER_LOG"
exit 0
EOF
cat > "$WORK/bin/curl" <<EOF
#!/usr/bin/env bash
case "\$*" in *"$NEOWIKI_REPOSITORY_URL"*) exec "$REAL_CURL" "\$@" ;; esac
exit 0
EOF
chmod +x "$WORK/bin/docker" "$WORK/bin/curl"
export PATH="$WORK/bin:$PATH"
export DOCKER_LOG="$WORK/docker.log"

# Writes, into directory $1, the files of release $2: what make needs to run a goal, the
# Docker/image-tag naming image $3 (none when $3 is empty), and a VERSION file to tell releases apart.
fill_release() {
    mkdir -p "$1/Docker"
    cp "$ROOT/Makefile" "$1/"
    cp "$ROOT/Docker/.env.dist" "$1/Docker/"
    cp -R "$ROOT/Docker/scripts" "$1/Docker/"
    printf '%s\n' "$2" > "$1/VERSION"
    if [ -n "$3" ]; then printf '%s\n' "$3" > "$1/Docker/image-tag"; fi
}

build_dir() { printf '%s\n' "$WORK/build/NeoWiki-$1"; }

# Publishes release $1, its archive naming image $2 (default: $1), as GitHub lays it out: one
# top-level directory, NeoWiki-<version>.
publish() {
    fill_release "$(build_dir "$1")" "$1" "${2-$1}"
    pack "$1"
}

# Publishes release $1's build directory again, after a case changed it.
pack() { tar -czf "$WORK/site/archive/refs/tags/$1.tar.gz" -C "$WORK/build" "NeoWiki-$1"; }

set_latest() { printf '%s\n' "$1" > "$WORK/site/latest"; }

# A copy unpacked from release $2's archive, with a Docker/.env and a file of the user's own.
new_archive_copy() {
    local dir="$WORK/$1"
    fill_release "$dir" "$2" "$2"
    printf 'MW_ADMIN_PASSWORD=mine\n' > "$dir/Docker/.env"
    printf 'mine\n' > "$dir/my-notes.txt"
    printf '%s\n' "$dir"
}

git_in() {
    git -C "$1" -c user.name=Test -c user.email=test@example.invalid -c commit.gpgSign=false \
        -c tag.gpgSign=false -c core.hooksPath=/dev/null "${@:2}" >/dev/null 2>&1
}

# A local origin holding a tag per release, each naming its image as tag-release.sh does. A tag
# given as name:none names no image, as a tag made by hand.
ORIGIN="$WORK/origin"
git_in "$WORK" init -q -b master "$ORIGIN"
for spec in 0.1.0 0.2.0 0.3.0:none; do
    v="${spec%%:*}"
    image="$v"
    [ "$spec" = "$v:none" ] && image=
    rm -f "$ORIGIN/Docker/image-tag"
    fill_release "$ORIGIN" "$v" "$image"
    git_in "$ORIGIN" add -A
    git_in "$ORIGIN" commit -q -m "Release $v"
    git_in "$ORIGIN" tag "$v"
done

# A clone of the origin on release $2's tag. Fetched tags arrive later only through origin.
new_clone() {
    local dir="$WORK/$1"
    git_in "$WORK" clone -q "$ORIGIN" "$dir"
    git_in "$dir" checkout -q "refs/tags/$2"
    printf '%s\n' "$dir"
}

# Runs the script in copy $1 for version $2, leaving the output in OUT and the exit code in RC.
move() {
    OUT="$("$1/Docker/scripts/move-to-version.sh" "$2" 2>&1)"
    RC=$?
}

# Whether the last move failed with a message containing every further argument.
failed_with() {
    local text
    [ "$RC" -ne 0 ] || return 1
    for text in "$@"; do
        printf '%s' "$OUT" | grep -qF -- "$text" || return 1
    done
}

release_of() { cat "$1/VERSION"; }
image_of() { cat "$1/Docker/image-tag" 2>/dev/null; }

publish 0.1.0
publish 0.2.0
publish 0.3.0 ''

echo
color '36' "Case 1: unpacked release -> the new release's files, the user's own kept"
dir="$(new_archive_copy archive 0.1.0)"
move "$dir" 0.2.0
assert_eq "$RC" 0 "The move succeeds"
assert_eq "$(release_of "$dir")" "0.2.0" "The copy holds the new release's files"
assert_eq "$(image_of "$dir")" "0.2.0" "The copy names the new release's image"
assert_eq "$(cat "$dir/Docker/.env")" "MW_ADMIN_PASSWORD=mine" "Docker/.env is kept"
assert_eq "$(cat "$dir/my-notes.txt")" "mine" "A file of the user's own is kept"

echo
color '36' "Case 2: version=latest -> the release GitHub calls latest"
set_latest 0.2.0
dir="$(new_archive_copy latest 0.1.0)"
move "$dir" latest
assert_eq "$(release_of "$dir")" "0.2.0" "latest resolves to the latest release"

echo
color '36' "Case 3: version=latest before any release -> stops, copy unchanged"
rm -f "$WORK/site/latest"
dir="$(new_archive_copy no-release 0.1.0)"
move "$dir" latest
if failed_with 'no release'; then ok "The move stops, saying there is no release"; else fail "The move should stop, saying there is no release (exit $RC, got: $OUT)"; fi
assert_eq "$(release_of "$dir")" "0.1.0" "The copy is unchanged"
set_latest 0.2.0

echo
color '36' "Case 4: a version with no release -> stops, copy unchanged"
dir="$(new_archive_copy unknown 0.1.0)"
move "$dir" 9.9.9
if failed_with '9.9.9'; then ok "The move stops, naming the version"; else fail "The move should stop, naming the version (exit $RC, got: $OUT)"; fi
assert_eq "$(release_of "$dir")" "0.1.0" "The copy is unchanged"

echo
color '36' "Case 5: something other than a version -> stops, copy unchanged"
dir="$(new_archive_copy malformed 0.1.0)"
move "$dir" '0.2.0/../../x'
if failed_with 'version'; then ok "The move stops"; else fail "The move should stop (exit $RC, got: $OUT)"; fi
assert_eq "$(release_of "$dir")" "0.1.0" "The copy is unchanged"

echo
color '36' "Case 6: an older release -> stops, copy unchanged"
dir="$(new_archive_copy older 0.2.0)"
move "$dir" 0.1.0
if failed_with '0.1.0' '0.2.0'; then ok "The move stops, naming both versions"; else fail "The move should stop, naming both versions (exit $RC, got: $OUT)"; fi
assert_eq "$(release_of "$dir")" "0.2.0" "The copy is unchanged"

echo
color '36' "Case 7: the same release again -> the copy stays on it"
dir="$(new_archive_copy same 0.2.0)"
move "$dir" 0.2.0
assert_eq "$RC" 0 "The move succeeds"
assert_eq "$(release_of "$dir")" "0.2.0" "The copy stays on the release"

echo
color '36' "Case 8: a release whose archive names no image -> stops, copy unchanged"
dir="$(new_archive_copy no-image 0.2.0)"
move "$dir" 0.3.0
if failed_with '0.3.0' 'image'; then ok "The move stops, saying the release names no image"; else fail "The move should stop, saying the release names no image (exit $RC, got: $OUT)"; fi
assert_eq "$(release_of "$dir")" "0.2.0" "The copy is unchanged"

echo
color '36' "Case 9: clone on a release tag -> the new release's tag"
dir="$(new_clone clone 0.1.0)"
move "$dir" 0.2.0
assert_eq "$RC" 0 "The move succeeds"
assert_eq "$(git -C "$dir" describe --tags --exact-match HEAD 2>/dev/null)" "0.2.0" "The clone has the new release's tag checked out"
assert_eq "$(image_of "$dir")" "0.2.0" "The clone names the new release's image"

echo
color '36' "Case 10: clone with an edit the new tag would overwrite -> stops, edit kept"
dir="$(new_clone clone-edited 0.1.0)"
printf 'edited\n' > "$dir/VERSION"
move "$dir" 0.2.0
if [ "$RC" -ne 0 ]; then ok "The move stops"; else fail "The move should stop (got: $OUT)"; fi
assert_eq "$(release_of "$dir")" "edited" "The edit is kept"
assert_eq "$(git -C "$dir" describe --tags --exact-match HEAD 2>/dev/null)" "0.1.0" "The clone stays on its tag"

echo
color '36' "Case 11: clone, tag that names no image -> stops, clone unchanged"
dir="$(new_clone clone-no-image 0.2.0)"
move "$dir" 0.3.0
if failed_with '0.3.0' 'image'; then ok "The move stops, saying the release names no image"; else fail "The move should stop, saying the release names no image (exit $RC, got: $OUT)"; fi
assert_eq "$(git -C "$dir" describe --tags --exact-match HEAD 2>/dev/null)" "0.2.0" "The clone stays on its tag"

echo
color '36' "Case 12: make upgrade version=X -> the stack moves to X's image, with the selected services"
dir="$(new_archive_copy make-upgrade 0.1.0)"
: > "$DOCKER_LOG"
OUT="$(make -s --no-print-directory -C "$dir" upgrade version=0.2.0 services=qlever,neo 2>&1)"
RC=$?
if [ "$RC" -eq 0 ]; then ok "make upgrade version=0.2.0 succeeds"; else fail "make upgrade version=0.2.0 should succeed (exit $RC, got: $OUT)"; fi
assert_eq "$(release_of "$dir")" "0.2.0" "The copy holds the new release's files"
if grep -qE '^tag=0\.2\.0 profiles=neo,qlever .*compose .* up -d$' "$DOCKER_LOG"; then
    ok "The stack starts on the new release's image, with the selected services"
else
    fail "The stack should start on 0.2.0 with neo,qlever (docker calls: $(cat "$DOCKER_LOG"))"
fi
if grep -q '^tag=0\.1\.0 .*compose -p ' "$DOCKER_LOG"; then fail "Nothing should run the old release's image (docker calls: $(cat "$DOCKER_LOG"))"; else ok "Nothing runs the old release's image"; fi

echo
color '36' "Case 13: make upgrade version=X that stops -> the stack is left alone"
dir="$(new_archive_copy make-upgrade-older 0.2.0)"
: > "$DOCKER_LOG"
OUT="$(make -s --no-print-directory -C "$dir" upgrade version=0.1.0 2>&1)"
RC=$?
if [ "$RC" -ne 0 ] && ! grep -q ' compose -p ' "$DOCKER_LOG"; then ok "make upgrade stops before touching the stack"; else fail "make upgrade should stop before touching the stack (exit $RC, docker calls: $(cat "$DOCKER_LOG"))"; fi

echo
color '36' "Case 14: unpacked release whose script is longer -> the move ends without running the new script"
publish 0.4.0
# A last line that only bash reading on past the end of the old script, into the new one, runs.
printf 'echo "Ran the new release'\''s script." >&2; exit 3\n' >> "$(build_dir 0.4.0)/Docker/scripts/move-to-version.sh"
pack 0.4.0
dir="$(new_archive_copy longer-script 0.1.0)"
move "$dir" 0.4.0
assert_eq "$RC" 0 "The move succeeds without running the new script"
assert_eq "$(release_of "$dir")" "0.4.0" "The copy holds the new release's files"

echo
color '36' "Case 15: make upgrade version=X to a release with other defaults -> X's defaults, with the user's profiles"
publish 0.5.0
sed 's/^DEFAULT_SERVICES := .*/DEFAULT_SERVICES := node oxigraph/' "$ROOT/Makefile" > "$(build_dir 0.5.0)/Makefile"
pack 0.5.0
dir="$(new_archive_copy make-upgrade-defaults 0.1.0)"
: > "$DOCKER_LOG"
OUT="$(COMPOSE_PROFILES=server make -s --no-print-directory -C "$dir" upgrade version=0.5.0 2>&1)"
RC=$?
if [ "$RC" -eq 0 ]; then ok "make upgrade version=0.5.0 succeeds"; else fail "make upgrade version=0.5.0 should succeed (exit $RC, got: $OUT)"; fi
up="$(grep ' up -d$' "$DOCKER_LOG")"
if printf '%s' "$up" | grep -qF ' profiles=node,oxigraph,server '; then
    ok "The stack starts with the new release's default services and the user's profile"
else
    fail "The stack should start with node,oxigraph,server (docker calls: $(cat "$DOCKER_LOG"))"
fi
if printf '%s' "$up" | grep -qF ' qlever= oxigraph=http://oxigraph:7878 '; then
    ok "The wiki gets the SPARQL stores the new release's services hold"
else
    fail "The wiki should get Oxigraph and no QLever (docker calls: $(cat "$DOCKER_LOG"))"
fi

echo
if [ "$FAILS" -eq 0 ]; then color '32' "All $PASSES checks passed."; exit 0
else color '31' "$FAILS check(s) failed ($PASSES passed)."; exit 1; fi
