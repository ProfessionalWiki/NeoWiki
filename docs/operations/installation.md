---
title: Installation
order: 1
---

# Installing NeoWiki

NeoWiki is pre-release software. It is not production ready, and breaking changes can land at any time without a
migration path. Treat any install as an evaluation or pilot and run it on disposable data.

The Docker stack is self-contained and the fastest way to a working wiki, so use it for evaluation. The manual
install adds NeoWiki to a MediaWiki you already run.

To set up the development environment instead, see the [README on GitHub](https://github.com/ProfessionalWiki/NeoWiki/blob/master/README.md).

## Method A: Docker

Prerequisites:

- Docker (or a compatible runtime such as Podman)
- Docker Compose v2+ (with the `docker compose` subcommand, not the legacy standalone `docker-compose` v1. Verify with `docker compose version`)
- GNU Make

The below commands assume a Unix-like shell, so on Windows run them under WSL.

Download the `ProfessionalWiki/NeoWiki` repository and run this from its root:

```sh
make demo
```

This pulls the latest demo image, starts the stack, installs the wiki, and loads the demo data. Later, `make down`
stops and removes the containers, and `make remove` also deletes the data volumes. To move a running stack to the
latest NeoWiki, see [Upgrading](upgrading.md).

For an empty wiki without the sample data, run `make up && make install-db && make load-neo4j-users` instead. That
wiki has no `Mapping:EDM` page, so the stack's preconfigured EDM projection reports a failure on every save until you
create that Mapping or remove its entry from `$wgNeoWikiSparqlStores`.

Open `http://localhost:8484` and log in as `AdminName` with the password `AdminPassword`.

### Optional: share the demo with others

By default, the stack runs on localhost. To let others reach it, host it on a server. Edit `Docker/.env` and change
every value marked `# Change for production`, which covers the passwords and `MW_SERVER`. Then start the stack with the
`server` profile, which adds automatic HTTPS through Caddy. Run it from the repository root so the follow-up `make`
commands target the same stack:

```sh
docker compose -p neowiki-neowiki --env-file Docker/.env -f Docker/docker-compose.yml --profile server up -d
```

Then run `make install-db`, `make load-neo4j-users` and `make import-demo-data` against it. This is still an
evaluation setup, not a production deployment.

## Method B: Add to an existing MediaWiki

Use this to add NeoWiki to a MediaWiki you already run. You provide the surrounding services yourself.

### Requirements

| Requirement               | Notes |
|---------------------------|--|
| MediaWiki 1.43.0 or later | |
| PHP 8.3 with `ext-json`   | |
| Composer                  | Installs NeoWiki's runtime dependencies. No `vendor/` is shipped. |
| Neo4j 5.x over Bolt       | Optional. The graph backend behind Cypher queries and relation-target suggestions. |
| Node.js 24 or later       | Needed only to build the frontend bundle in step 2. |

These extensions are recommended. NeoWiki runs without them, but you lose the matching functionality:

- **Scribunto** adds the Lua API and its `nw.*` functions.
- **CodeEditor** adds JSON syntax highlighting when you edit Schema, Layout, and Mapping pages.
- **ParserFunctions** is commonly used alongside NeoWiki's parser functions.

Check the extension out at `extensions/NeoWiki/` under your MediaWiki root:

```sh
git clone https://github.com/ProfessionalWiki/NeoWiki.git extensions/NeoWiki
```

### 1. Install Composer dependencies

NeoWiki's runtime dependencies are installed through MediaWiki's Composer merge plugin. Add NeoWiki's `composer.json` to
your wiki's root `composer.local.json`:

```json
{ "extra": { "merge-plugin": { "include": [ "extensions/NeoWiki/composer.json" ] } } }
```

Then run this from the MediaWiki root:

```sh
composer update
```

### 2. Build the frontend bundle

A fresh checkout ships no compiled frontend, so there is no UI until you build it:

```sh
cd extensions/NeoWiki/resources/ext.neowiki
npm ci && npm run build
```

This produces `dist/neowiki.js` and `dist/neowiki.css`.

### 3. Load and configure the extension

Add the following to your `LocalSettings.php`:

```php
wfLoadExtension( 'NeoWiki' );

// Point both at your Neo4j instance; for a simple setup, use the same user for both.
$wgNeoWikiNeo4jInternalWriteUrl = 'bolt://neo4j:SECRET@neo4j-host:7687';
$wgNeoWikiNeo4jInternalReadUrl  = 'bolt://neo4j:SECRET@neo4j-host:7687';

// Recommended soft dependencies.
wfLoadExtension( 'Scribunto' );
wfLoadExtension( 'CodeEditor' );
wfLoadExtension( 'ParserFunctions' );
```

Without both Neo4j URLs set, NeoWiki's structured-data features still work. You lose the Cypher query surfaces
(`{{#cypher_raw}}`, `nw.query`, `POST /neowiki/v0/query/cypher`) and relation-target suggestions.

### 4. Run the updater

Run this from the MediaWiki root:

```sh
php maintenance/run.php update --quick
```

Build the Neo4j projection from MediaWiki whenever it is out of sync with the wiki — after installing on a wiki that
already has pages, since a page is otherwise projected on its next edit, and after data arrives by a path other than a
normal edit (such as an import):

```sh
php maintenance/run.php NeoWiki:RebuildGraphDatabases
```

### 5. Verify your install

1. **Create a Schema.** Go to Special:Schemas and create your first Schema
2. **Add a Subject.** On an ordinary wiki page, use "Create subject" or "Manage subjects" to create your first Subject
3. **Render a View.** On that same page, source edit the wikitext and add the following. With no id it renders the
   page's Main Subject:
   ```
   {{#view:}}
   ```

Your install is complete once those three steps work.

With Neo4j configured, one more step checks its projection. On any page, add a Cypher query that lists the stored
pages, independent of your data model:

```
{{#cypher_raw: MATCH (p:Page) RETURN p.name }}
```

The result renders as JSON. If Neo4j is unreachable, it renders an error instead.

### Optional: Pretty URLs for the Data tab

Serve the Data tab at `/wiki/PageName/subjects` instead of `index.php?action=subjects` with:

```php
$wgActionPaths['subjects'] = "/wiki/$1/subjects";
```

## Key settings

These are the settings you are most likely to change. For the full list with descriptions and defaults, see the
`config` section of [`extension.json`](https://github.com/ProfessionalWiki/NeoWiki/blob/master/extension.json).

| Setting | Purpose | Default | Required |
|---|---|---|---|
| `$wgNeoWikiNeo4jInternalWriteUrl` | Bolt URL for writing the graph projection | _none_ | For Neo4j features |
| `$wgNeoWikiNeo4jInternalReadUrl` | Bolt URL for read and query traffic | _none_ | For Neo4j features |
| `$wgNeoWikiEnableDevelopmentUI` | Enables development-only UIs | `false` | No |
| `$wgNeoWikiEnforceValidation` | Rejects writes that introduce new `error`-severity violations | `false` | No |
| `$wgNeoWikiAutoRenderMainSubject` | Automatically renders a page's Main Subject as an infobox | `true` | No |
| `$wgNeoWikiSparqlStores` | SPARQL 1.1 graph stores to keep in sync and query, e.g. QLever | `[]` | No |
| `$wgNeoWikiAutoRebuildOnMappingChange` | Rebuilds every store holding a Mapping's projection when that Mapping changes | `false` | No |

## User rights

`neowiki-admin` allows viewing and rebuilding the wiki's graph stores, through
[Special:GraphStores](maintenance.md#background-rebuilds) or the matching REST endpoints. Administrators have it by
default.

`neowiki-query` gates the query surfaces; see [Permissions](../api/query-api.md#permissions). The remaining rights —
`neowiki-schema-edit`, `neowiki-layout-edit` and `neowiki-mapping-edit` — gate editing in NeoWiki's own namespaces and
are granted to logged-in users.

Parser functions and Lua read as the user the page is parsed for, and their output is parser-cached per combination
of user groups and wiki-level rights. A permission extension that grants page access per user rather than per group
is not followed by that cache key: run such a wiki with the parser cache off (`$wgParserCacheType = CACHE_NONE`).

## On-wiki configuration

A wiki administrator without server access can set part of NeoWiki's configuration on the `MediaWiki:NeoWiki`
page. It holds JSON and, like other site configuration, is editable only with the `editinterface` and
`editsitejson` rights. Two settings are exposed: `dereferenceSubjectsToDataTab` (overriding
`$wgNeoWikiDereferenceSubjectsToDataTab`) and `autoRenderMainSubject` (overriding `$wgNeoWikiAutoRenderMainSubject`).
Editing the page shows a reference table of the exposed keys and their accepted values, and creating it
preloads a working example.

A valid value on the page takes precedence over `LocalSettings.php`, per setting. A missing page, a
wrong-shaped value, or an unavailable database falls back to the `LocalSettings.php` value, so a
configuration typo cannot take down the wiki. Saving the page rejects unknown keys and wrong-typed values.

Every other setting stays in `LocalSettings.php` — deliberately, for secrets and infrastructure
(`$wgNeoWikiSparqlStores`), for settings too consequential for a wiki page (`$wgNeoWikiRdfBaseUri` re-mints
every IRI when changed), and for development toggles (`$wgNeoWikiEnableDevelopmentUI`).

`autoRenderMainSubject` changes take effect as pages are re-parsed; already-cached pages keep their previous
rendering until then, or until purged with `?action=purge`.

Set `$wgNeoWikiEnableInWikiConfig` to `false` to disable the page entirely: it is then never given the JSON
content model, validated, or read.

## Optional: SPARQL graph stores

With or without Neo4j, NeoWiki can keep one or more SPARQL 1.1 graph stores in sync with page changes. This works with
QLever, Oxigraph, Fuseki and any other SPARQL 1.1 store. Each configured store receives the NeoWiki data as RDF:
every page becomes a named graph, replaced on each edit and dropped on deletion.

Configure the stores with `$wgNeoWikiSparqlStores`, a list of objects:

```php
$wgNeoWikiSparqlStores = [
	[
		// Required: the store's SPARQL 1.1 Update endpoint (the write path posts here).
		'updateUrl' => 'https://qlever.example/api/neowiki',
		// Optional: the store's SPARQL 1.1 Query endpoint (the read path posts here).
		// Defaults to updateUrl, which is correct for QLever, where the two are the same.
		'queryUrl' => 'https://qlever.example/api/neowiki',
		// Optional: sent as an HTTP Bearer token (e.g. a QLever access token) on both update and
		// query requests — QLever only requires it for updates, but a read-protected store needs it too.
		'accessToken' => 'SECRET',
		// Optional: the RDF vocabulary written to this store. Defaults to 'native'; otherwise a
		// Mapping page title without the prefix ('EDM' for Mapping:EDM). As with any page title,
		// only the first letter is case-insensitive.
		'projection' => 'native',
	],
];
```

A store entry whose `updateUrl` is missing or empty is skipped with a warning rather than failing the wiki.

Each store's `name` identifies it when [rebuilding one store](maintenance.md#rebuilding-one-store), so no two entries
may share one, and none may be `neo4j` in any casing — reserved for the bundled Neo4j backend. Since the name defaults
to the projection, two entries holding the same projection — mirroring it to a second endpoint, say — collide until
one of them sets an explicit `name`. An entry whose name cannot identify it is skipped with a warning, so its store
receives no page changes.

### Oxigraph

Oxigraph serves queries on `/query` and updates on `/update`, so an entry needs both:

```php
$wgNeoWikiSparqlStores = [
	[
		'updateUrl' => 'https://oxigraph.example/update',
		'queryUrl' => 'https://oxigraph.example/query',
		'projection' => 'native',
	],
];
```

Oxigraph has no authentication and ignores `accessToken`: it accepts every request, updates included, from anyone who
can reach it. Put it behind network isolation or an authenticating reverse proxy, and see
[Restricting federation](#restricting-federation).

### Fuseki

Fuseki serves each dataset under its own path, queries on `/query` and updates on `/update` below it:

```php
$wgNeoWikiSparqlStores = [
	[
		'updateUrl' => 'https://fuseki.example/ds/update',
		'queryUrl' => 'https://fuseki.example/ds/query',
		'projection' => 'native',
	],
];
```

A dataset's endpoints accept every request, updates included, from anyone who can reach them: Fuseki's shipped
configuration protects its admin interface, not its data. Put it behind network isolation or an authenticating
reverse proxy, and see [Restricting federation](#restricting-federation).

### Several projections in one store

Each entry carries one projection, so a store that should hold several gets one entry per projection, all with
the same `updateUrl`. Each projection writes its own [named graphs](../rdf/rdf-export.md#iri-scheme), so they never
overwrite one another:

```php
$wgNeoWikiSparqlStores = [
	[
		'updateUrl' => 'https://qlever.example/api/neowiki',
		'accessToken' => 'SECRET',
		'projection' => 'native',
	],
	[
		'updateUrl' => 'https://qlever.example/api/neowiki',
		'accessToken' => 'SECRET',
		'projection' => 'EDM',
	],
];
```

Sibling projections mint the same entity IRIs, so one query can combine data from both — the target ontology's terms
alongside a property that ontology does not model. The
[Person-to-EDM example](../rdf/person-to-edm.md#querying-via-sparql) shows such a query.

A newly added entry only receives pages saved from then on. Backfill it, and only it, by
[rebuilding that one store](maintenance.md#rebuilding-one-store).

Editing a Mapping page leaves every page already projected under it in the old vocabulary until the store is
rebuilt: see [stale stores](maintenance.md#stale-stores), which also covers `$wgNeoWikiAutoRebuildOnMappingChange`.

### Querying a SPARQL store

When at least one store is configured, three read-only query surfaces become available and target the **first**
configured store:

- The [`{{#sparql_raw}}`](../authoring/parser-functions.md#sparql_raw) parser function.
- The [`nw.sparqlQuery()`](../authoring/lua-api.md#nwsparqlquerysparql) Lua function.
- The [`POST /neowiki/v0/query/sparql`](../api/query-api.md#sparql-query-endpoint) REST endpoint.

Each is read-only: the query is sent as a SPARQL 1.1 *query* operation, posted only to `queryUrl` and never
`updateUrl`.

A projection configured on a different endpoint is therefore not reachable from these surfaces.

NeoWiki writes pages into named graphs and nothing into the default graph, so whether an unscoped query finds them is
the store's choice; see [RDF Export](../rdf/rdf-export.md#iri-scheme).

Both bundled Docker stacks, demo and dev, ship working QLever and Oxigraph examples wired up this way — see
[`Docker/README.md`](../../Docker/README.md#qlever-sparql-store) for the services, QLever's `--persist-updates`
requirement, and how to query them.

### Restricting federation

The query surfaces are read-only, but SPARQL's `SERVICE` clause lets a query direct the store to fetch results
from another endpoint. The store makes those requests itself, from its own network position — often one that
reaches internal services the wiki's visitors cannot. Restrict federation at the store unless you mean to offer it.

QLever allows every `SERVICE` IRI unless `--service-allowed-iri-prefixes` is given. Pass the IRI prefixes you want
to allow, or the deny-all value `-` (an invalid prefix that no IRI matches):

```sh
# No federation:
qlever-server -i neowiki -p 7019 -m 1G --service-allowed-iri-prefixes -

# Or allow only specific endpoints:
qlever-server -i neowiki -p 7019 -m 1G --service-allowed-iri-prefixes https://sparql.example.org/
```

The bundled Docker stacks set the deny-all value on their QLever services, so those do not federate unless you
change it. The setting can also be changed at runtime through the store's endpoint by anyone holding its access
token, so keep that token secret.

Oxigraph has no equivalent setting and will attempt the outbound request; the stacks' Oxigraph services federate
for that reason. Other stores vary — consult their documentation. Where the store cannot be restricted, restrict
around it: block its outbound traffic at the network layer, and narrow the `neowiki-query` right, which by default
is granted to everyone including anonymous visitors — see [Permissions](../api/query-api.md#permissions).

## Production hardening

NeoWiki is pre-release, so this is not needed for an evaluation. It applies later, when NeoWiki is production-ready.

### Separate read and write Neo4j users

Give read and query traffic its own Neo4j user that cannot modify the graph. Create a read-only user:

```cypher
CREATE USER neowiki_read SET PASSWORD 'SECRET' CHANGE NOT REQUIRED;
GRANT ROLE reader TO neowiki_read;
```

Then update the read URL to use it. Leave the write URL on the full-access user:

```php
$wgNeoWikiNeo4jInternalWriteUrl = 'bolt://neo4j:SECRET@neo4j-host:7687';
$wgNeoWikiNeo4jInternalReadUrl  = 'bolt://neowiki_read:SECRET@neo4j-host:7687';
```

This needs Neo4j Enterprise. On Community Edition, keep both URLs on the same user.
