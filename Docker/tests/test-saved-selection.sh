#!/usr/bin/env bash
# Exercise how a stack keeps its optional services and compose profiles from one start to the next,
# in a throwaway copy of the repository. Docker is a stub that logs its calls, so no stack starts.

set -u

# When `make test-scripts` runs this script, the outer make's global `export` hands it that make's
# derived values and command-line variables, such as services=; either would win over the selection
# under test.
unset NEOWIKI_IMAGE_TAG MAKEFLAGS COMPOSE_PROFILES OXIGRAPH_URL NEO4J_HOST QLEVER_URL MW_SMTP_HOST services port
unset NEOWIKI_SERVICES NEOWIKI_PROFILES

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

# The stub docker logs each call with the profiles and Neo4j host it ran under. Asked for the
# services of the stack's containers, it lists STUB_CONTAINERS; every container is healthy and runs
# the image it should, and the mediawiki container runs, so ensure-port leaves the ports alone. The
# stub curl answers the wait for MediaWiki.
mkdir "$WORK/bin"
cat > "$WORK/bin/docker" <<'EOF'
#!/usr/bin/env bash
printf 'profiles=%s neo4j=%s :: %s\n' "${COMPOSE_PROFILES:-}" "${NEO4J_HOST:-}" "$*" >> "$DOCKER_LOG"
case " $* " in
    *" ps -a "*"--format"*) for service in ${STUB_CONTAINERS:-}; do echo "$service"; done ;;
    *" ps "*"service=qlever"*) echo qlever-container ;;
    *" ps -q mediawiki "*) echo mediawiki-container ;;
    *" inspect "*"Health.Status"*) echo healthy ;;
esac
exit 0
EOF
printf '#!/bin/sh\nexit 0\n' > "$WORK/bin/curl"
chmod +x "$WORK/bin/docker" "$WORK/bin/curl"
export PATH="$WORK/bin:$PATH"
export DOCKER_LOG="$WORK/docker.log"

dir="$WORK/NeoWiki"

# A fresh copy of the repository with the given containers, holding the Makefile, what it reads
# before running a goal, the scripts the goals run, a MediaWiki checkout for bootstrap to find, and
# a demo image. It has no Docker/.env yet, as a new copy has none.
new_stack() {
    rm -rf "$dir"
    mkdir -p "$dir/Docker/mediawiki/.git"
    cp "$ROOT/Makefile" "$dir/"
    cp "$ROOT/Docker/.env.dist" "$dir/Docker/"
    cp -R "$ROOT/Docker/scripts" "$dir/Docker/"
    printf '0.1.0\n' > "$dir/Docker/image-tag"
    export STUB_CONTAINERS="$*"
}

# Runs make in the copy, the arguments going to make. Leaves the exit code in RC and docker's calls
# in DOCKER_LOG.
run_make() {
    : > "$DOCKER_LOG"
    make -s --no-print-directory -C "$dir" "$@" > /dev/null 2>&1
    RC=$?
}

saved() { grep -E "^$1=" "$dir/Docker/.env" | tail -1 | cut -d= -f2-; }

# The profiles of the run's `up` call, comma-separated and sorted, and the Neo4j host it ran with.
up_profiles() { grep -E ':: compose .* up ' "$DOCKER_LOG" | head -1 | sed -E 's/^profiles=([^ ]*) .*/\1/' | tr ',' '\n' | sort | paste -sd, -; }
up_neo4j() { grep -E ':: compose .* up ' "$DOCKER_LOG" | head -1 | sed -E 's/^profiles=[^ ]* neo4j=([^ ]*) .*/\1/'; }

echo
color '36' "Case 1: a stack started with services= saves that selection"
new_stack
run_make up services=qlever,neo
assert_eq "$RC" 0 "make up services=qlever,neo succeeds"
assert_eq "$(saved NEOWIKI_SERVICES)" "qlever,neo" "make up saves the services it started"

echo
color '36' "Case 2: a later start without services= runs the saved selection, after make down too"
run_make down
run_make up
assert_eq "$(up_profiles)" "neo,qlever" "make up after make down starts the saved services"
assert_eq "$(up_neo4j)" "neo" "make up after make down points the wiki at the saved Neo4j"
run_make upgrade
assert_eq "$(up_profiles)" "neo,qlever" "make upgrade starts the saved services"

echo
color '36' "Case 3: services=default goes back to the default set and saves that"
run_make up services=default
assert_eq "$(up_profiles)" "mailcatcher,node,qlever" "services=default starts the default set"
assert_eq "$(saved NEOWIKI_SERVICES)" "default" "services=default is saved as default"

echo
color '36' "Case 4: services=none is saved as none"
run_make up services=none
assert_eq "$(saved NEOWIKI_SERVICES)" "none" "services=none is saved as none"
run_make up
assert_eq "$(up_profiles)" "" "a later make up starts no optional service"

echo
color '36' "Case 5: a stack with nothing saved keeps the optional services its containers run"
new_stack db mediawiki neo qlever
run_make upgrade
assert_eq "$(up_profiles)" "neo,qlever" "make upgrade starts the services the stack's containers run"
assert_eq "$(up_neo4j)" "neo" "make upgrade keeps the wiki on the stack's Neo4j"
assert_eq "$(saved NEOWIKI_SERVICES)" "neo,qlever" "make upgrade saves the services it found"

echo
color '36' "Case 6: a new stack with nothing saved and no containers gets the default set"
new_stack
run_make demo
assert_eq "$(up_profiles)" "mailcatcher,node,qlever" "make demo starts the default set"
assert_eq "$(saved NEOWIKI_SERVICES)" "default" "make demo saves the default set as default"

echo
color '36' "Case 7: the dev stack saves and reuses its selection as the demo targets do"
new_stack
run_make dev services=node,neo
assert_eq "$(saved NEOWIKI_SERVICES)" "node,neo" "make dev saves the services it started"
run_make dev
assert_eq "$(up_profiles)" "neo,node" "a later make dev starts the saved services"

echo
color '36' "Case 8: COMPOSE_PROFILES from the environment is saved and reused, until the environment sets it again"
new_stack
COMPOSE_PROFILES=server run_make demo
assert_eq "$(saved NEOWIKI_PROFILES)" "server" "COMPOSE_PROFILES=server make demo saves the server profile"
run_make upgrade
assert_eq "$(up_profiles)" "mailcatcher,node,qlever,server" "a later make upgrade keeps the server profile"
COMPOSE_PROFILES='' run_make up
assert_eq "$(up_profiles)" "mailcatcher,node,qlever" "an empty COMPOSE_PROFILES drops the saved profile"
assert_eq "$(saved NEOWIKI_PROFILES)" "none" "an empty COMPOSE_PROFILES is saved as none"

echo
color '36' "Case 9: a stack with nothing saved and a Caddy container keeps the server profile"
new_stack db mediawiki qlever caddy
run_make upgrade
assert_eq "$(up_profiles)" "qlever,server" "make upgrade keeps the server profile of a stack running Caddy"
assert_eq "$(saved NEOWIKI_PROFILES)" "server" "make upgrade saves the server profile it found"

echo
color '36' "Case 10: goals that do not start the stack leave the saved selection alone"
new_stack
run_make up services=qlever,neo
run_make print-services services=none
run_make down
assert_eq "$(saved NEOWIKI_SERVICES)" "qlever,neo" "print-services and down do not change the saved services"

echo
if [ "$FAILS" -eq 0 ]; then color '32' "All $PASSES checks passed."; exit 0
else color '31' "$FAILS check(s) failed ($PASSES passed)."; exit 1; fi
