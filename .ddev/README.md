# NeoWiki dev environment (ddev)

The NeoWiki dev environment runs on [ddev](https://ddev.com). One command brings up the whole
stack; there is no image to build and no port to pick.

Every checkout gets a stable hostname behind ddev's shared router: the main checkout serves at
`https://neowiki.ddev.site`, a worktree directory `NeoWiki-feature-x` at
`https://neowiki-feature-x.ddev.site`. Parallel checkouts need no port coordination at all.

The prebuilt demo image and its compose stack are a separate concern and do not use ddev — see
[`../Docker/README.md`](../Docker/README.md).

## Prerequisites

- Docker
- [ddev](https://docs.ddev.com/en/stable/users/install/ddev-installation/)

## Usage

```sh
ddev start        # brings up web, db, neo, test_neo, qlever, test_qlever, node watcher; installs + seeds on first run
ddev describe     # URLs (wiki, Mailpit), service status; -j for JSON
ddev exec <cmd>   # run a command in the web container
ddev stop         # stop this project's containers; ddev's shared router and ssh-agent keep running
ddev poweroff     # stop those too, along with every other running ddev project
```

`ddev start` also brings up two containers shared by all ddev projects on the machine: the router
(which serves the `*.ddev.site` hostnames) and the ssh-agent. `ddev stop` deliberately leaves them
running — other checkouts may be using them; `ddev poweroff` is the full shutdown.

The first `ddev start` on a fresh clone does everything: it clones MediaWiki core into
`Docker/mediawiki/`, installs the composer dependencies, installs the wiki, provisions the Neo4j
users, and seeds the demo data. Expect it to take a few minutes; later starts are fast.

Log in as `AdminName` / `AdminPassword`.

Day-to-day tooling (`make phpunit`, `make cs`, `make tsci`, ...) is wrapped by the
[`Makefile`](../Makefile) in the extension root; the targets exec into the ddev containers.

## Reaching the services

- **Wiki**: `https://<project>.ddev.site` (main checkout: https://neowiki.ddev.site).
- **Mailpit** (catches all wiki mail): URL in `ddev describe`, or `ddev launch -m`.
- **Neo4j Browser**: `https://neo4j-<project>.ddev.site` — routed by hostname through the shared
  router, no extra host port. Inside the Browser, connect with
  `bolt+s://neo4j-<project>.ddev.site:17687`, user `neo4j`, password `password`.
- **QLever SPARQL endpoint**: `https://qlever-<project>.ddev.site` — routed by hostname through the
  shared router, no extra host port. Reads are open; writes need the `neowiki_dev_token` Bearer token.
- **From code/containers**: services resolve by compose name — `bolt://neo:7687`,
  `bolt://test_neo:7689`, SPARQL `http://qlever:7019/` (and `http://test_qlever:7019/`, the isolated
  store the SPARQL query system test writes to), SMTP `127.0.0.1:1025` (Mailpit lives in the web
  container).
- **Shells and CLIs**: `ddev exec -s neo cypher-shell -u neo4j -p password '...'`,
  `ddev ssh -s <service>`, `ddev logs -s <service>`, `ddev mysql` (DB also gets an
  auto-published host port for GUI clients — see `ddev describe`).
- A host-side tool needing raw bolt (`bolt://localhost:7687`, e.g. Neo4j Desktop) is the one
  case still requiring a direct port publish: add it per checkout in the gitignored
  `.ddev/config.local.yaml`.

## QLever SPARQL store

The stack bundles a [QLever](https://github.com/ad-freiburg/qlever) SPARQL 1.1 store as a working
example of NeoWiki's SPARQL projection plugin. `qlever` backs the wiki: `mw/LocalSettings.ddev.php`
points `$wgNeoWikiSparqlStores` at `http://qlever:7019/` (`native` projection), so every page save and
`RebuildGraphDatabases.php` also projects the page's RDF into it. `test_qlever` is the isolated store the
SPARQL query system test (`QuerySparqlEndToEndTest`) writes to, reached in-network at
`http://test_qlever:7019/`.

The demo store runs with `--persist-updates`, so projected data survives container restarts — updates are
written to a named volume and reloaded on startup, and the empty index is built only once. Query it from
the host at `https://qlever-<project>.ddev.site`, or in-network:

```sh
ddev exec curl http://qlever:7019/ \
  --data-urlencode 'query=SELECT (COUNT(*) AS ?n) WHERE { GRAPH ?g { ?s ?p ?o } }' \
  -H 'Accept: application/sparql-results+json'
```

Writes require the `neowiki_dev_token` Bearer token; reads do not.

## What the pieces are

- `config.yaml` — php 8.3, apache-fpm (MediaWiki's rest.php/api.php need PATH_INFO, which ddev's
  default nginx does not route), mariadb 11.8, docroot `Docker/mediawiki`. Deliberately has no
  `name:` so worktrees self-name from their directory. Also carries the lifecycle hooks: the
  pre-start MediaWiki-core clone and the post-start Neo4j-user + install steps.
- `docker-compose.neo.yaml` / `docker-compose.qlever.yaml` / `docker-compose.node.yaml` — the Neo4j
  pair (wiki + test instance), the QLever SPARQL pair (wiki + test instance, for the SPARQL
  projection plugin), and the TypeScript build watcher.
- `docker-compose.extension-mount.yaml` — bind-mounts this checkout into the MediaWiki core tree
  at `extensions/NeoWiki`, so tools see the same nested layout as CI.
- `mw/LocalSettings.ddev.php` — ddev-flavored settings (flat docroot, ddev DB credentials,
  Mailpit, `$wgServer` from `DDEV_PRIMARY_URL`). Required by the `Docker/mediawiki/LocalSettings.php`
  stub the install hook writes. Per-checkout overrides go in `.ddev/mw/LocalSettings.local.php`
  (gitignored) — extra extensions for integration work can be cloned straight into
  `Docker/mediawiki/extensions/` and loaded from there.
- `setup/clone-mediawiki.sh` — pre-start hook (host): clones MediaWiki core and the bundled
  extensions/skins on first run.
- `setup/install-wiki.sh` — post-start hook (web container): composer dependencies, first-run
  MediaWiki install, demo-data seed. Idempotent.

## Worktrees

A parallel environment is just another checkout:

```sh
git worktree add ../NeoWiki-feature-x feature/x
cd ../NeoWiki-feature-x
ddev start    # serves at https://neowiki-feature-x.ddev.site
```

Each worktree clones its own MediaWiki core on first start. To skip the download, hard-link it
from an existing checkout first: `cp -al ../NeoWiki/Docker/mediawiki Docker/mediawiki`. Treat a
hard-linked core as read-only — in-place edits would mutate every linked checkout.

## Known limitations

- HTTPS uses ddev's local CA; without `mkcert -install` browsers warn (http works regardless).
- Windows: if `ddev start` reports a blocked port, see `ddev utility port-diagnose`; relocating
  ddev's Mailpit router ports (`ddev config global --mailpit-http-port=...`) may be needed on
  machines with Hyper-V reserved ranges.
