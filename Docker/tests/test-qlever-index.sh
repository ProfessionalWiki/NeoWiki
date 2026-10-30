#!/usr/bin/env bash
# Exercise how the stack goals treat a QLever that cannot read its index, in a throwaway copy of the
# repository. Docker is a stub that logs its calls, so no stack starts.

set -u

# When `make test-scripts` runs this script, the outer make's global `export` hands it that make's
# derived values and command-line variables, such as services=; either would win over the selection
# under test.
unset NEOWIKI_IMAGE_TAG MAKEFLAGS COMPOSE_PROFILES OXIGRAPH_URL NEO4J_HOST QLEVER_URL MW_SMTP_HOST services port

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"

WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT

PASSES=0
FAILS=0
color() { local c=$1; shift; printf '\033[%sm%s\033[0m\n' "$c" "$*"; }
ok()   { color '32' "PASS: $*"; PASSES=$((PASSES + 1)); }
fail() { color '31' "FAIL: $*"; FAILS=$((FAILS + 1)); }

# A wipe of QLever's index is a one-off qlever container acting on /index.
export WIPE='^compose .* run .*qlever .*/index'

# The stub docker logs each call. The stack has no qlever container when STUB_QLEVER is empty;
# otherwise QLever is healthy, fails every start on an index in an old format (old-index), or is
# neither (broken). After a wipe, the first look finds it starting and later ones healthy. Its log
# keeps the lines from before the wipe, so only a read since the last start leaves them out. The
# start time renders as Podman renders it: a Go time through a plain template, RFC 3339 through json
# (as on Docker), and logs --since takes only RFC 3339. A graph rebuild fails when STUB_REBUILD is
# fails. The mediawiki container runs, so ensure-port leaves the ports alone. The stub curl answers
# the wait for MediaWiki, and the stub sleep returns at once.
mkdir "$WORK/bin"
cat > "$WORK/bin/docker" <<'EOF'
#!/usr/bin/env bash
printf '%s\n' "$*" >> "$DOCKER_LOG"
wiped() { grep -qE "$WIPE" "$DOCKER_LOG"; }
health_looks_since_wipe() { awk -v wipe="$WIPE" '$0 ~ wipe { w = 1 } w && /Health/ { n++ } END { print n + 0 }' "$DOCKER_LOG"; }
case " $* " in
    *" ps "*"service=qlever"* | *" ps -a -q qlever "*) [ -z "${STUB_QLEVER:-}" ] || echo qlever-container ;;
    *" ps -q mediawiki "*) echo mediawiki-container ;;
    *" inspect "*"Health.Status"*" qlever-container "*)
        if wiped; then
            if [ "$(health_looks_since_wipe)" -gt 1 ]; then echo healthy; else echo starting; fi
        elif [ "$STUB_QLEVER" = healthy ]; then echo healthy
        else echo unhealthy; fi ;;
    *" inspect "*"json .State.StartedAt"*" qlever-container "*) echo '"2026-10-29T12:00:00.123456789Z"' ;;
    *" inspect "*"State.StartedAt"*" qlever-container "*) echo '2026-10-29 12:00:00.123456789 +0000 UTC' ;;
    *" logs "*" qlever-container "*)
        since=
        prev=
        for arg in "$@"; do [ "$prev" = --since ] && since=$arg; prev=$arg; done
        if [ -n "$since" ] && ! [[ $since =~ ^[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9:.]+Z$ ]]; then
            echo "Error: unable to interpret time value $since" >&2
            exit 125
        fi
        if [ "$STUB_QLEVER" = old-index ] && { [ -z "$since" ] || ! wiped; }; then
            echo 'ERROR: The index format changed on 2026-09-01 (PR = 3159), but your index uses the previous format'
        fi ;;
    *" exec "*"NeoWiki:RebuildGraphDatabases"*) [ "${STUB_REBUILD:-}" != fails ] || exit 1 ;;
esac
exit 0
EOF
printf '#!/bin/sh\nexit 0\n' > "$WORK/bin/curl"
printf '#!/bin/sh\nexit 0\n' > "$WORK/bin/sleep"
chmod +x "$WORK/bin/docker" "$WORK/bin/curl" "$WORK/bin/sleep"
export PATH="$WORK/bin:$PATH"
export DOCKER_LOG="$WORK/docker.log"

# Holds the Makefile, what it reads before running a goal, the scripts the goals run, a MediaWiki
# checkout for bootstrap to find, and a demo image for the image goals.
dir="$WORK/NeoWiki"
mkdir -p "$dir/Docker/mediawiki/.git"
cp "$ROOT/Makefile" "$dir/"
cp "$ROOT/Docker/.env.dist" "$dir/Docker/"
cp -R "$ROOT/Docker/scripts" "$dir/Docker/"
printf '0.1.0\n' > "$dir/Docker/image-tag"

# Runs make in the copy, with the qlever container in state $1, the further arguments going to make.
# Leaves the output in OUT, the exit code in RC and docker's calls in DOCKER_LOG.
run_make() {
    local qlever=$1; shift
    : > "$DOCKER_LOG"
    OUT="$(export STUB_QLEVER="$qlever"; make -s --no-print-directory -C "$dir" "$@" 2>&1)"
    RC=$?
}

first_line() { grep -nE "$1" "$DOCKER_LOG" | head -1 | cut -d: -f1; }
rebuilds() { grep -c 'NeoWiki:RebuildGraphDatabases' "$DOCKER_LOG"; }
wiped() { grep -qE "$WIPE" "$DOCKER_LOG"; }

# Whether the last run_make stopped QLever, wiped its index, started it again, then rebuilt the
# graph stores once.
wiped_then_refilled() {
    local stop wipe start rebuild
    stop="$(first_line '^compose .* stop qlever$')"
    wipe="$(first_line "$WIPE")"
    start="$(first_line '^compose .* (start|up) (.* )?qlever$')"
    rebuild="$(first_line 'NeoWiki:RebuildGraphDatabases')"
    [ "$RC" -eq 0 ] && [ -n "$stop" ] && [ -n "$wipe" ] && [ -n "$start" ] && [ -n "$rebuild" ] &&
        [ "$stop" -lt "$wipe" ] && [ "$wipe" -lt "$start" ] && [ "$start" -lt "$rebuild" ] && [ "$(rebuilds)" -eq 1 ]
}

echo
color '36' "Case 1: dev stack whose QLever cannot read its index -> wiped and refilled from the wiki"
for goal in dev dev-tools; do
    run_make old-index "$goal"
    if wiped_then_refilled; then
        ok "make $goal wipes QLever's index, then refills it"
    else
        fail "make $goal should wipe QLever's index, then refill it (exit $RC, got: $OUT; docker calls: $(cat "$DOCKER_LOG"))"
    fi
done

echo
color '36' "Case 2: dev stack whose QLever is healthy -> index and graph stores left alone"
run_make healthy dev
if [ "$RC" -eq 0 ] && ! wiped && [ "$(rebuilds)" -eq 0 ] && ! printf '%s' "$OUT" | grep -qF 'QLever'; then
    ok "make dev leaves a healthy QLever's index alone, without a word"
else
    fail "make dev should leave a healthy QLever's index alone, without a word (exit $RC, got: $OUT; docker calls: $(cat "$DOCKER_LOG"))"
fi

echo
color '36' "Case 3: stack without QLever, whose stopped container cannot read its index -> left alone"
for goal in dev upgrade; do
    run_make old-index "$goal" services=none
    if [ "$RC" -eq 0 ] && ! wiped; then
        ok "make $goal services=none leaves a deselected QLever alone"
    else
        fail "make $goal services=none should leave a deselected QLever alone (exit $RC, docker calls: $(cat "$DOCKER_LOG"))"
    fi
done

echo
color '36' "Case 4: QLever fails for another reason -> a warning, index left alone"
for goal in dev upgrade; do
    run_make broken "$goal"
    if [ "$RC" -eq 0 ] && ! wiped && printf '%s' "$OUT" | grep -qF 'QLever is not healthy'; then
        ok "make $goal warns that QLever is not healthy and carries on"
    else
        fail "make $goal should warn that QLever is not healthy and carry on (exit $RC, got: $OUT)"
    fi
done

echo
color '36' "Case 5: upgrade with a QLever that cannot read its index -> wiped before the one rebuild"
run_make old-index upgrade
if wiped_then_refilled && [ "$(first_line '^compose .* up -d$')" -lt "$(first_line "$WIPE")" ]; then
    ok "make upgrade wipes QLever's index after starting the stack, and its own rebuild refills it"
else
    fail "make upgrade should wipe QLever's index after starting the stack, before its one rebuild (exit $RC, docker calls: $(cat "$DOCKER_LOG"))"
fi

echo
color '36' "Case 6: reset-qlever -> wiped and refilled, even when QLever is healthy"
run_make healthy reset-qlever
if wiped_then_refilled; then
    ok "make reset-qlever wipes QLever's index, then refills it"
else
    fail "make reset-qlever should wipe QLever's index, then refill it (exit $RC, got: $OUT; docker calls: $(cat "$DOCKER_LOG"))"
fi

echo
color '36' "Case 7: reset-qlever on a stack without a qlever container, as after make down -> refused"
run_make '' reset-qlever
if [ "$RC" -ne 0 ] && ! wiped && [ "$(rebuilds)" -eq 0 ]; then
    ok "make reset-qlever refuses to wipe an index no container can serve again"
else
    fail "make reset-qlever should refuse to wipe an index no container can serve again (exit $RC, docker calls: $(cat "$DOCKER_LOG"))"
fi

echo
color '36' "Case 8: dev stack whose refill fails after the wipe -> fails, pointing to make reset-qlever"
STUB_REBUILD=fails run_make old-index dev
if [ "$RC" -ne 0 ] && printf '%s' "$OUT" | grep -qF 'make reset-qlever'; then
    ok "make dev fails and says how to refill the wiped index"
else
    fail "make dev should fail and say how to refill the wiped index (exit $RC, got: $OUT)"
fi

echo
if [ "$FAILS" -eq 0 ]; then color '32' "All $PASSES checks passed."; exit 0
else color '31' "$FAILS check(s) failed ($PASSES passed)."; exit 1; fi
