# shellcheck shell=bash
# Read and write KEY=VALUE entries of $ENV_FILE. Sourced by the scripts that keep values in Docker/.env.

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
