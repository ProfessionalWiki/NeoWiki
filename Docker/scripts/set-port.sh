#!/usr/bin/env bash
# Allocate host port mappings for the dev stack and write them to .env.
# Allocates independently per service so an explicit MW_SERVER_PORT (e.g.
# port=8488) does not push the mailcatcher port outside its range.

set -e

REQUESTED="${1:-}"
ENV_FILE="${ENV_FILE:-Docker/.env}"
LOCK_FILE="/tmp/.neowiki-port-allocation.lock"

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
allocate() {
    local key=$1 start=$2 end=$3
    local existing
    existing=$(get_env_var "$key")
    if [ -n "$existing" ] && is_port_free "$existing"; then
        echo "$existing"
        return 0
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

# Take the lock once around the whole allocation pass so concurrent worktrees
# do not pick the same port between is_port_free and the eventual bind.
exec 200>"$LOCK_FILE"
flock -x 200

if [ -n "$REQUESTED" ]; then
    set_env_var MW_SERVER_PORT "$REQUESTED"
    mw_port="$REQUESTED"
else
    mw_port=$(allocate MW_SERVER_PORT "$MW_RANGE_START" "$MW_RANGE_END")
fi

mc_port=$(allocate MAILCATCHER_PORT "$MAILCATCHER_RANGE_START" "$MAILCATCHER_RANGE_END")

echo "Using MW_SERVER_PORT=$mw_port, MAILCATCHER_PORT=$mc_port"
