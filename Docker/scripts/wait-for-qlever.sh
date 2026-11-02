#!/usr/bin/env bash
# Wait up to a minute for the qlever container of compose project $1 to become healthy.
# Exits 0 once it is healthy, 2 when its last start failed because it cannot read its index, and 1
# when the project has no qlever container or it is neither after a minute.

set -u

PROJECT="$1"

container="$(docker ps -a -q --filter "label=com.docker.compose.project=$PROJECT" \
    --filter label=com.docker.compose.service=qlever --filter label=com.docker.compose.oneoff=False)"
[ -n "$container" ] || exit 1

# Reading only the log since the last start leaves out failures from before the index was wiped.
# Through json, both Docker and Podman print the start time in RFC 3339, which logs --since needs;
# Podman's plain rendering of it is not.
started="$(docker inspect -f '{{json .State.StartedAt}}' "$container" | tr -d '"')"

for _ in $(seq 1 60); do
    [ "$(docker inspect -f '{{.State.Health.Status}}' "$container")" = healthy ] && exit 0

    # QLever's messages for an index it cannot read.
    if docker logs --since "$started" "$container" 2>&1 \
        | grep -qE 'The index format changed|The index is too old for this version of QLever'; then
        echo "QLever cannot read its index, which another QLever build wrote."
        exit 2
    fi

    sleep 1
done

echo "QLever is not healthy after a minute; see its log: docker logs $container" >&2
exit 1
