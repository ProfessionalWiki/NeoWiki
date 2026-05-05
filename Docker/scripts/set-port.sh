#!/usr/bin/env bash
# Set MW_SERVER_PORT in .env. If $1 is set, use it. Otherwise auto-allocate
# from PORT_RANGE_START..PORT_RANGE_END.

set -e

REQUESTED="${1:-}"
ENV_FILE="${ENV_FILE:-Docker/.env}"
LOCK_FILE="/tmp/.neowiki-port-allocation.lock"
RANGE_START="${PORT_RANGE_START:-8484}"
RANGE_END="${PORT_RANGE_END:-8499}"

is_port_free() {
    local p=$1
    # Free if nothing is listening on it.
    ! (echo > /dev/tcp/127.0.0.1/"$p") 2>/dev/null
}

write_mw_port() {
    local p=$1
    if grep -qE '^MW_SERVER_PORT=' "$ENV_FILE"; then
        sed -i.bak -E "s|^MW_SERVER_PORT=.*|MW_SERVER_PORT=$p|" "$ENV_FILE"
        rm -f "$ENV_FILE.bak"
    else
        echo "MW_SERVER_PORT=$p" >> "$ENV_FILE"
    fi
}

# Derive mailcatcher port from MW_SERVER_PORT (1180 maps to 8484, etc.) and
# write it to the env file. Always called so renaming a worktree's MW port
# keeps mailcatcher in sync.
write_mailcatcher_port() {
    local p=$1
    local mc=$(( 1180 + p - RANGE_START ))
    if grep -qE '^MAILCATCHER_PORT=' "$ENV_FILE"; then
        sed -i.bak -E "s|^MAILCATCHER_PORT=.*|MAILCATCHER_PORT=$mc|" "$ENV_FILE"
        rm -f "$ENV_FILE.bak"
    else
        echo "MAILCATCHER_PORT=$mc" >> "$ENV_FILE"
    fi
}

write_port() {
    write_mw_port "$1"
    write_mailcatcher_port "$1"
    echo "Using MW_SERVER_PORT=$1, MAILCATCHER_PORT=$(( 1180 + $1 - RANGE_START ))"
}

if [ -n "$REQUESTED" ]; then
    write_port "$REQUESTED"
    exit 0
fi

# Read the existing .env value.
EXISTING="$(grep -E '^MW_SERVER_PORT=' "$ENV_FILE" | head -1 | cut -d= -f2- || true)"
if [ -n "$EXISTING" ] && is_port_free "$EXISTING"; then
    # Reuse the configured MW port; ensure mailcatcher port is in sync.
    write_mailcatcher_port "$EXISTING"
    echo "Using existing MW_SERVER_PORT=$EXISTING, MAILCATCHER_PORT=$(( 1180 + EXISTING - RANGE_START ))"
    exit 0
fi

# Auto-allocate.
exec 200>"$LOCK_FILE"
flock -x 200

for p in $(seq "$RANGE_START" "$RANGE_END"); do
    if is_port_free "$p"; then
        write_port "$p"
        exit 0
    fi
done

echo "No free port in range $RANGE_START-$RANGE_END" >&2
exit 1
