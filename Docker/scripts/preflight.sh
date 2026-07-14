#!/usr/bin/env bash
# Preflight checks for the NeoWiki demo stack. Verifies the Docker runtime is
# usable before the lifecycle targets do expensive work (the multi-GB image
# pull). Runs as the `_preflight` prerequisite of up/pull/demo, and standalone
# (verbose) via `make doctor`.
#
# Scope is the Docker runtime only — the one prerequisite that genuinely varies
# between hosts. Base tooling (git, curl, coreutils) is assumed present. Checks are
# collect-all (every check runs so the user sees all problems at once), except the
# Docker-presence gate, which short-circuits the rest when no runtime is found —
# the downstream checks would only misdirect.
#
# The dev environment does not use this: ddev ships its own diagnostics
# (`ddev debug test`, `ddev utility port-diagnose`).

set -u

DOCKER_BIN="${DOCKER_BIN:-docker}"
PREFLIGHT_VERBOSE="${PREFLIGHT_VERBOSE:-}"

failed=0

pass() { [ -n "$PREFLIGHT_VERBOSE" ] && printf '  \033[32m✓\033[0m %s\n' "$1"; return 0; }
fail() { printf '  \033[31m✗\033[0m %s\n' "$1" >&2; failed=1; }

# Docker (or a compatible runtime) present at all. Without it the compose and daemon
# checks are meaningless, so a miss short-circuits the rest (see run_preflight).
check_docker() {
    command -v "$DOCKER_BIN" >/dev/null 2>&1 && return 0
    fail "Docker (or a compatible runtime such as Podman) was not found."
    {
        echo "      NeoWiki's demo stack runs entirely in containers. Install Docker:"
        echo "        https://docs.docker.com/get-docker/"
    } >&2
    return 1
}

# Docker Compose present. `docker compose version` is a client-side probe and does
# not touch the daemon, so it is necessary but not sufficient (see check_daemon).
check_compose() {
    if "$DOCKER_BIN" compose version >/dev/null 2>&1; then
        pass "Docker Compose available"
        return
    fi
    fail "Docker Compose (the 'docker compose' command) was not found."
    {
        echo "      NeoWiki's demo stack uses the 'docker compose' command (a Docker CLI"
        echo "      plugin). Install it: https://docs.docker.com/compose/install/"
        echo "      Then verify with: docker compose version"
    } >&2
}

# Daemon reachable AND permitted. `docker info` contacts the daemon, so it fails
# both when the daemon is down and when the socket is permission-denied — the gap
# the client-side compose probe leaves open.
check_daemon() {
    if "$DOCKER_BIN" info >/dev/null 2>&1; then
        pass "Docker daemon reachable"
        return
    fi
    fail "Cannot reach the Docker daemon."
    {
        echo "      Ensure it is running and you can access it:"
        echo "        - start Docker Desktop, or enable WSL integration, or: sudo systemctl start docker"
        echo "        - ensure your user can use the socket (the 'docker' group, or a rootless setup)"
    } >&2
}

run_preflight() {
    [ -n "$PREFLIGHT_VERBOSE" ] && echo "Checking demo-stack prerequisites..."
    # Docker itself underlies everything below; if it is absent the compose and
    # daemon checks would only emit redundant, misdirecting errors.
    if check_docker; then
        check_compose
        check_daemon
    fi

    if [ "$failed" -ne 0 ]; then
        echo "" >&2
        echo "Preflight failed. Fix the items marked ✗ above, then retry." >&2
        return 1
    fi
    [ -z "$PREFLIGHT_VERBOSE" ] || echo "All prerequisites satisfied."
    return 0
}

run_preflight
