---
title: Installation
order: 1
---

# Installing NeoWiki

NeoWiki is not production-ready yet. From 0.1 on, every breaking change to stored data ships with a migration script or
written upgrade steps.

Install the newest release in one of two ways:

- [Try NeoWiki in Docker](#try-neowiki-in-docker): a self-contained wiki with demo data, the fastest way to evaluate
  NeoWiki.
- [Add NeoWiki to an existing wiki](#add-neowiki-to-an-existing-wiki): install it with Composer into a MediaWiki you
  already run.

To run master, or to work on NeoWiki itself, see [Unreleased code](#unreleased-code).

## Try NeoWiki in Docker

Requirements:

- Docker (or a compatible runtime such as Podman)
- Docker Compose v2+ (with the `docker compose` subcommand, not the legacy standalone `docker-compose` v1. Verify with `docker compose version`)
- GNU Make, in a Unix-like shell (on Windows, WSL)

### 1. Download the release

Download and unpack the newest [release](https://github.com/ProfessionalWiki/NeoWiki/releases/latest).

### 2. Start the demo

Run this from the release's root:

```sh
make demo
```

This pulls the demo image of that release, starts the stack, installs the wiki, and loads the demo data.

The stack runs QLever as its graph store. For other graph stores, such as Neo4j, see
[Optional services](../../Docker/README.md#optional-services).

### 3. Log in

Open `http://localhost:8484` and log in as `AdminName` with the password `AdminPassword`.

### Stop, remove or upgrade the demo

`make down` stops and removes the containers, and `make remove` also deletes the data volumes. To move a running stack
to a newer NeoWiki, see [Upgrading](upgrading.md).

### Share the demo with others

By default, the stack runs on localhost. To let others reach it, host it on a server. Edit `Docker/.env` and change
every value marked `# Change for production`, which covers the passwords and `MW_SERVER`. Then start the stack with the
`server` profile, which adds automatic HTTPS through Caddy:

```sh
COMPOSE_PROFILES=server make demo
```

This is still an evaluation setup, not a production deployment.

## Add NeoWiki to an existing wiki

Requirements:

- MediaWiki 1.43.0 or later
- PHP 8.3 or later
- Composer

These extensions are recommended. NeoWiki runs without them, but you lose the matching functionality:

- **Scribunto** adds the Lua API and its `nw.*` functions.
- **CodeEditor** adds JSON syntax highlighting when you edit Schema, Layout, and Mapping pages.
- **ParserFunctions** is commonly used alongside NeoWiki's parser functions.

### 1. Install the extension

Run this from the MediaWiki root:

```sh
COMPOSER=composer.local.json composer require --no-update neowiki/neowiki:^0.1
composer update neowiki/neowiki --no-dev -o
```

This installs the newest 0.1 release at `extensions/NeoWiki/`.

### 2. Load the extension

Add the following to your `LocalSettings.php`:

```php
wfLoadExtension( 'NeoWiki' );

// Recommended soft dependencies, unless your wiki already loads them.
wfLoadExtension( 'Scribunto' );
wfLoadExtension( 'CodeEditor' );
wfLoadExtension( 'ParserFunctions' );
```

### 3. Run the updater

Run this from the MediaWiki root:

```sh
php maintenance/run.php update --quick
```

### 4. Verify your install

Your install is complete once you can walk through [Getting started](../guide/getting-started.md) on it. Its query
step needs a SPARQL store.

### Optional: connect a graph store

Queries run against graph stores that NeoWiki keeps in sync with the wiki: [Neo4j](#optional-neo4j) for Cypher, and
[SPARQL stores](#optional-sparql-graph-stores) for SPARQL. Without one, everything but the query surfaces works.

A store receives a page when it is next saved. Fill it with the wiki's existing pages after connecting it, and again
after pages arrive by a path other than an edit, such as an import. From the MediaWiki root:

```sh
php maintenance/run.php NeoWiki:RebuildGraphDatabases
```

### Optional: Pretty URLs for the Data tab

Serve the Data tab at `/wiki/PageName/subjects` instead of `index.php?action=subjects` with:

```php
$wgActionPaths['subjects'] = "/wiki/$1/subjects";
```

## Unreleased code

Master holds the changes not yet in a release. To run it:

- **Docker:** run `make demo` in a clone of the repository on master. It runs master's demo image, rebuilt on every
  push to master.
- **An existing wiki:** in place of step 1 of [Add NeoWiki to an existing wiki](#add-neowiki-to-an-existing-wiki),
  clone the repository from the MediaWiki root:

  ```sh
  git clone https://github.com/ProfessionalWiki/NeoWiki.git extensions/NeoWiki
  ```

  Add its `composer.json` to your wiki's `composer.local.json`, then run `composer update` from the MediaWiki root:

  ```json
  { "extra": { "merge-plugin": { "include": [ "extensions/NeoWiki/composer.json" ] } } }
  ```

  Master holds no frontend bundle, so build it, with Node.js 24 or later:

  ```sh
  cd extensions/NeoWiki/resources/ext.neowiki
  npm ci && npm run build
  ```

  Then continue with step 2.

To work on NeoWiki itself, use the development stack in the
[README on GitHub](https://github.com/ProfessionalWiki/NeoWiki/blob/master/README.md#development).

## Key settings

These are the settings you are most likely to change. For the full list with descriptions and defaults, see the
`config` section of [`extension.json`](https://github.com/ProfessionalWiki/NeoWiki/blob/master/extension.json).

| Setting | Purpose | Default | Required |
|---|---|---|---|
| `$wgNeoWikiNeo4jInternalWriteUrl` | Bolt URL for writing the graph projection | _none_ | For Neo4j features |
| `$wgNeoWikiNeo4jInternalReadUrl` | Bolt URL for read and query traffic | _none_ | For Neo4j features |
| `$wgNeoWikiEnableDevelopmentUI` | Enables development-only UIs | `false` | No |
| `$wgNeoWikiEnableLua` | Registers the `mw.neowiki` Lua library with Scribunto | `true` | No |
| `$wgNeoWikiEnforceValidation` | Rejects writes that introduce new `error`-severity violations | `false` | No |
| `$wgNeoWikiAutoRenderMainSubject` | Automatically renders a page's Main Subject as an infobox, or in full on a subject-first wiki | `true` | No |
| `$wgNeoWikiSubjectFirst` | Puts Subjects before pages: see [Choosing page-first or subject-first](#choosing-page-first-or-subject-first) | `false` | No |
| `$wgNeoWikiSubjectPageNamespace` | Namespace a subject-first wiki creates each Subject's own page in: see [Choosing page-first or subject-first](#choosing-page-first-or-subject-first) | `7480` (`Subject`) | No |
| `$wgNeoWikiDataLanguages` | Languages the wiki's data is in: see [Data languages](#data-languages) | `[]` (the content language) | No |
| `$wgNeoWikiSparqlStores` | SPARQL 1.1 graph stores to keep in sync and query, e.g. QLever | `[]` | No |
| `$wgNeoWikiAutoRebuildOnMappingChange` | Rebuilds every store holding a Mapping's projection when that Mapping changes | `false` | No |

## Choosing page-first or subject-first

You can make your wiki [page-first or subject-first](../subject-first-wikis.md). The default is page-first,
and you can change it in the [on-wiki settings](#on-wiki-settings) or with `$wgNeoWikiSubjectFirst`.

On a subject-first wiki, Subjects are stored in the `Subject` namespace by default. You can change this with
`$wgNeoWikiSubjectPageNamespace`.

## Data languages

`$wgNeoWikiDataLanguages` lists the languages the wiki's data is in, as BCP 47 language tags, such as
`[ 'de', 'fr', 'it' ]`. When editing [text with language](../api/schema-format.md#text-with-language-monolingualtext),
the editor offers them in this order beside each text, and starts a new text in the first of them the value lacks.
Other languages stay one search away, and texts already in them keep their language. The default, an empty list,
stands for the wiki's content language (`$wgLanguageCode`). You can also set the list in the
[on-wiki settings](#on-wiki-settings).

## Logging

NeoWiki logs on the `NeoWiki` channel: a graph store failing on save, the pages a rebuild could not project, a store
entry or name it will not accept, and RDF a projection had to drop. Below `warning` it also records rebuild decisions
and denied page reads. MediaWiki routes no channel anywhere by default, so none of it reaches you until you route it:

```php
$wgDebugLogGroups['NeoWiki'] = '/var/log/mediawiki/neowiki.log';
```

Add a `level` to drop everything below it:

```php
$wgDebugLogGroups['NeoWiki'] = [ 'destination' => '/var/log/mediawiki/neowiki.log', 'level' => 'warning' ];
```

The [Docker install](#try-neowiki-in-docker) routes the channel to stderr: entries logged while serving requests appear in
`make logs`, and those logged by a maintenance command, such as `make rebuild-graph-databases`, print only in the
terminal that ran it.

## User rights

`neowiki-admin` allows viewing and rebuilding the wiki's graph stores, through
[Special:GraphStores](maintenance.md#background-rebuilds) or the matching REST endpoints. Administrators have it by
default.

`neowiki-query` gates the query surfaces; see [Permissions](../api/query-api.md#permissions). The remaining rights —
`neowiki-schema-edit`, `neowiki-layout-edit` and `neowiki-mapping-edit` — gate editing in NeoWiki's own namespaces and
are granted to logged-in users.

A wiki whose readers do not all see the same pages needs more than these defaults; see
[Restricted content](restricted-content.md).

## On-wiki settings

Some settings, such as whether the wiki is [page-first or subject-first](#choosing-page-first-or-subject-first) and its
[data languages](#data-languages), can also be set in a form at `MediaWiki:NeoWiki`. By default, only administrators can
change them. Everything else, such as the Neo4j and SPARQL store connections, can only be set in `LocalSettings.php`.

![A form with radio buttons, a reorderable language list and checkboxes](on-wiki-settings.png)

Set `$wgNeoWikiEnableInWikiConfig` to `false` to disable on-wiki settings.

Per setting, a valid value on the page overrides `LocalSettings.php`. Pages rendered before a change can lag behind it
until purged with `?action=purge`.

## Optional: Neo4j

Cypher queries (`{{#cypher_raw}}`, `nw.query` and `POST /neowiki/v0/query/cypher`) need Neo4j 5.26.4 or later. Connect
NeoWiki to it over Bolt in `LocalSettings.php`. NeoWiki uses Neo4j only when both URLs are set; for a simple setup, use
the same user for both:

```php
$wgNeoWikiNeo4jInternalWriteUrl = 'bolt://neo4j:SECRET@neo4j-host:7687';
$wgNeoWikiNeo4jInternalReadUrl  = 'bolt://neo4j:SECRET@neo4j-host:7687';
```

To check the connection once you [filled it](#optional-connect-a-graph-store), add a Cypher query that lists the
stored pages, independent of your data model, to any page:

```
{{#cypher_raw: MATCH (p:Page) RETURN p.name }}
```

The result renders as JSON. If Neo4j is unreachable, it renders an error instead.

### Separate read and write Neo4j users

On Neo4j Enterprise, you can give read and query traffic its own user that cannot modify the graph. Create a
read-only user:

```cypher
CREATE USER neowiki_read SET PASSWORD 'SECRET' CHANGE NOT REQUIRED;
GRANT ROLE reader TO neowiki_read;
```

Then update the read URL to use it. Leave the write URL on the full-access user:

```php
$wgNeoWikiNeo4jInternalWriteUrl = 'bolt://neo4j:SECRET@neo4j-host:7687';
$wgNeoWikiNeo4jInternalReadUrl  = 'bolt://neowiki_read:SECRET@neo4j-host:7687';
```

On Community Edition, keep both URLs on the same user.

## Optional: SPARQL graph stores

NeoWiki can keep one or more SPARQL 1.1 graph stores in sync with page changes. This works with QLever, Oxigraph,
Fuseki and any other SPARQL 1.1 store. Each configured store receives the NeoWiki data as RDF: every page becomes a
named graph, replaced on each edit and dropped on deletion.

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

Each store's `name` identifies it when [rebuilding one store](maintenance.md#rebuilding-one-store), so no two entries
may share one, and none may be `neo4j` in any casing — reserved for the bundled Neo4j backend. Since the name defaults
to the projection, two entries holding the same projection — mirroring it to a second endpoint, say — collide until
one of them sets an explicit `name`.

An entry whose `updateUrl` is missing or empty, or whose name cannot identify it, is skipped with a warning on the
[`NeoWiki` log channel](#logging) rather than failing the wiki, so its store receives no page changes.

### QLever

Start `qlever-server`, from a build of August 2026 or later, with:

- `--persist-updates`, or a restart loses NeoWiki's writes.
- `--rebuild-index-strategy automatic:10000:50000:0.1`, or
  [saves slow as the wiki grows](performance.md#with-a-sparql-store).
- `--rebuild-keep-previous-index-dirs none`, or each index rebuild leaves the index it replaced on disk.

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
the same `updateUrl`. Each projection writes its own [named graphs](../api/rdf-export.md#iri-scheme), so they never
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
[Person-to-EDM example](../guide/person-to-edm.md#querying-via-sparql) shows such a query.

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
the store's choice; see [RDF Export](../api/rdf-export.md#iri-scheme).

Both bundled Docker stacks, demo and dev, run QLever wired up this way, and Oxigraph when selected — see
[`Docker/README.md`](../../Docker/README.md#qlever-sparql-store) for the services and how to query them.

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

Oxigraph has no equivalent setting and will attempt the outbound request. Other stores vary — consult their
documentation. Where you cannot restrict the store, restrict around it: block its outbound traffic at the network
layer, and narrow who holds the `neowiki-query` right — see
[Narrowing `neowiki-query`](restricted-content.md#narrowing-neowiki-query). The bundled Docker stacks put their
Oxigraph services on a network that reaches only the wiki, so a federated query fails unless the stack runs with
`Docker/docker-compose.tools.yml`.
