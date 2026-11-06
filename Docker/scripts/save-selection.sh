#!/usr/bin/env bash
# Save the optional services and compose profiles a stack starts with to Docker/.env, where the
# Makefile reads them back when the stack next starts. Usage: save-selection.sh <services> <profiles>

set -e

ENV_FILE="${ENV_FILE:-Docker/.env}"

# shellcheck source-path=SCRIPTDIR source=env-file.sh
. "$(dirname "$0")/env-file.sh"

set_env_var NEOWIKI_SERVICES "$1"
set_env_var NEOWIKI_PROFILES "$2"
