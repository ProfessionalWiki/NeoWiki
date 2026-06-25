#!/usr/bin/env bash
# Allocate host port mappings for the dev stack and write them to .env.
# Allocates independently per service so an explicit MW_SERVER_PORT (e.g.
# port=8488) does not push the mailcatcher port outside its range.

set -e

REQUESTED="${1:-}"
ENV_FILE="${ENV_FILE:-Docker/.env}"
LOCK_FILE="${LOCK_FILE:-${TMPDIR:-/tmp}/.neowiki-port-allocation.$(id -u).lock}"
LOCK_DIR="${LOCK_DIR:-${TMPDIR:-/tmp}/.neowiki-port-allocation.$(id -u).lock.d}"
LOCK_TIMEOUT="${LOCK_TIMEOUT:-100}"
FLOCK_BIN="${FLOCK_BIN:-flock}"

# Reserved ranges. Document any change in Docker/README.md.
MW_RANGE_START="${PORT_RANGE_START:-8484}"
MW_RANGE_END="${PORT_RANGE_END:-8499}"
MAILCATCHER_RANGE_START=8025
MAILCATCHER_RANGE_END=8040

is_port_free() {
    local p=$1
    ! (echo > /dev/tcp/127.0.0.1/"$p") 2>/dev/null
}

# Update or append a KEY=VALUE entry in $ENV_FILE.
set_env_var() {
    local key=$1 value=$2
    if grep -qE "^$key=" "$ENV_FILE"; then
        sed -i.bak -E "s|^$key=.*|$key=$value|" "$ENV_FILE"
        rm -f "$ENV_FILE.bak"
    else
        echo "$key=$value" >> "$ENV_FILE"
    fi
}

# Read current value of a key from $ENV_FILE, empty if unset.
get_env_var() {
    local key=$1
    grep -E "^$key=" "$ENV_FILE" | head -1 | cut -d= -f2- || true
}

# Allocate or reuse a port for $key in [$start..$end]. If the existing value
# in .env is still free on the host, reuse it; otherwise pick the first free.
# A held value outside [$start..$end] is treated as a deliberate user override
# and surfaces as an error rather than being silently replaced.
allocate() {
    local key=$1 start=$2 end=$3
    local existing
    existing=$(get_env_var "$key")
    if [ -n "$existing" ] && is_port_free "$existing"; then
        echo "$existing"
        return 0
    fi
    if [ -n "$existing" ] && { [ "$existing" -lt "$start" ] || [ "$existing" -gt "$end" ]; }; then
        echo "Error: configured $key=$existing is in use and outside the auto-allocation range $start-$end." >&2
        echo "Edit Docker/.env or stop the conflicting service." >&2
        return 1
    fi
    for p in $(seq "$start" "$end"); do
        if is_port_free "$p"; then
            set_env_var "$key" "$p"
            echo "$p"
            return 0
        fi
    done
    echo "No free port in range $start-$end for $key" >&2
    return 1
}

# Serialize concurrent allocation passes by the same user (e.g. parallel
# worktrees) so two runs do not both read the same port as free and claim it.
# Prefer flock(1): a kernel-managed lock released automatically on process death,
# even on SIGKILL. Where flock is unavailable (stock macOS does not ship it), fall
# back to a portable directory mutex. The lock is per-user (paths carry the uid)
# so distinct users on a shared host never contend.
#
# Best-effort either way: the lock is released when this pass exits, before the
# caller binds the port, so it narrows but cannot fully eliminate a same-port
# race between cold-start worktrees.
acquire_lock() {
    if command -v "$FLOCK_BIN" >/dev/null 2>&1; then
        exec 200>"$LOCK_FILE"
        "$FLOCK_BIN" -x 200
        return
    fi
    acquire_dir_lock
}

# Directory-mutex fallback for hosts without flock(1). A directory has no kernel
# auto-release, so a lock orphaned by a crashed run is reclaimed by checking the
# recorded holder PID's liveness. The steal renames first so only one racer can
# reclaim a given stale lock (an unconditional rm could delete a fresh lock that
# another run just took). A reused holder PID reads as alive, so recovery waits
# out LOCK_TIMEOUT instead — a bounded delay, never a deadlock.
acquire_dir_lock() {
    local waited=0 holder
    until mkdir "$LOCK_DIR" 2>/dev/null; do
        holder=$( cat "$LOCK_DIR/pid" 2>/dev/null || true )
        if [ -n "$holder" ] && ! kill -0 "$holder" 2>/dev/null; then
            mv "$LOCK_DIR" "$LOCK_DIR.stale.$$" 2>/dev/null && rm -rf "$LOCK_DIR.stale.$$"
            continue
        fi
        waited=$(( waited + 1 ))
        if [ "$waited" -ge "$LOCK_TIMEOUT" ]; then
            echo "Timed out waiting for the port-allocation lock ($LOCK_DIR)." >&2
            return 1
        fi
        sleep 0.1
    done
    echo $$ > "$LOCK_DIR/pid"
    trap 'rm -rf "$LOCK_DIR"' EXIT
}

acquire_lock

if [ -n "$REQUESTED" ]; then
    if ! is_port_free "$REQUESTED"; then
        echo "Error: requested MW_SERVER_PORT=$REQUESTED is already in use." >&2
        echo "Pick a different port or stop the conflicting service." >&2
        exit 1
    fi
    set_env_var MW_SERVER_PORT "$REQUESTED"
    mw_port="$REQUESTED"
else
    mw_port=$(allocate MW_SERVER_PORT "$MW_RANGE_START" "$MW_RANGE_END")
fi

mc_port=$(allocate MAILCATCHER_PORT "$MAILCATCHER_RANGE_START" "$MAILCATCHER_RANGE_END")

echo "Using MW_SERVER_PORT=$mw_port, MAILCATCHER_PORT=$mc_port"
