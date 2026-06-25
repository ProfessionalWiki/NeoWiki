#!/usr/bin/env bash
# Preflight checks for the NeoWiki dev environment. Verifies the Docker runtime is
# usable before the lifecycle targets do expensive work (image build, the
# multi-hundred-MB MediaWiki-core clone). Runs as the `_preflight` prerequisite of
# up/demo/dev/dev-tools, and standalone (verbose) via `make doctor`.
#
# Scope is the Docker runtime only — the one prerequisite that genuinely varies
# between hosts. Base tooling (git, curl, coreutils) is assumed present. Checks are
# collect-all: every check runs so the user sees all problems at once.

set -u

DOCKER_BIN="${DOCKER_BIN:-docker}"
PREFLIGHT_VERBOSE="${PREFLIGHT_VERBOSE:-}"
PODMAN_BIN="${PODMAN_BIN:-podman}"
PORT_RANGE_START="${PORT_RANGE_START:-8484}"
PORT_RANGE_END="${PORT_RANGE_END:-8499}"

failed=0

pass() { [ -n "$PREFLIGHT_VERBOSE" ] && printf '  \033[32m✓\033[0m %s\n' "$1"; return 0; }
fail() { printf '  \033[31m✗\033[0m %s\n' "$1" >&2; failed=1; }
warn() { printf '  \033[33m!\033[0m %s\n' "$1" >&2; }

is_port_free() {
    ! (echo > "/dev/tcp/127.0.0.1/$1") 2>/dev/null
}

# Compose v2 present. `docker compose version` is a client-side probe and does not
# touch the daemon, so it is necessary but not sufficient (see check_daemon).
check_compose() {
    if "$DOCKER_BIN" compose version >/dev/null 2>&1; then
        pass "Docker Compose v2 available"
        return
    fi
    fail "Docker Compose v2 (the 'docker compose' subcommand) was not found."
    {
        echo "      NeoWiki's dev environment calls 'docker compose' (with a space). A standalone"
        echo "      'docker-compose' binary or the legacy V1 does not satisfy this. Install the plugin:"
        echo "        Ubuntu/Debian:  sudo apt-get install docker-compose-v2"
        echo "        Other systems:  https://docs.docker.com/compose/install/"
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

# Surface the detected engine so a Podman misdetection (a stray podman binary on a
# Docker host) is visible rather than silently writing root-owned files into bind
# mounts. Mirrors the Makefile IS_PODMAN heuristic.
check_engine() {
    if "$DOCKER_BIN" --version 2>/dev/null | grep -qi podman || command -v "$PODMAN_BIN" >/dev/null 2>&1; then
        warn "Podman detected — tooling runs as the container user. If this host actually uses Docker, a stray 'podman' binary can misroute bind-mount file ownership."
        return
    fi
    pass "Engine: Docker (tooling runs as host uid:gid)"
}

# Warn only when the whole dev port range is saturated, never on a stack holding
# its own single port.
check_ports() {
    local p
    for p in $(seq "$PORT_RANGE_START" "$PORT_RANGE_END"); do
        if is_port_free "$p"; then
            pass "Free host port available in $PORT_RANGE_START-$PORT_RANGE_END"
            return
        fi
    done
    warn "No free host port in $PORT_RANGE_START-$PORT_RANGE_END — the entire dev range is in use."
}

run_preflight() {
    [ -n "$PREFLIGHT_VERBOSE" ] && echo "Checking dev-environment prerequisites..."
    check_compose
    check_daemon
    check_engine
    check_ports

    if [ "$failed" -ne 0 ]; then
        echo "" >&2
        echo "Preflight failed. Fix the items marked ✗ above, then retry." >&2
        return 1
    fi
    [ -z "$PREFLIGHT_VERBOSE" ] || echo "All prerequisites satisfied."
    return 0
}

run_preflight
