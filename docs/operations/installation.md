---
title: Installation
order: 1
---

# Installing NeoWiki

NeoWiki is **pre-release software** (`0.0.0-alpha`, an experimental proof of concept). It is not production ready,
and breaking changes — including breaking schema and data-format changes — can land at any time without a migration
path. Treat any install as an **evaluation or pilot**, run it on disposable data, and expect to rebuild. Before
exposing an instance to others, read the [security policy](../../SECURITY.md).

This guide covers two installation methods: a self-contained Docker stack (recommended for evaluation) and a manual
install into an existing MediaWiki.

## How NeoWiki stores data

NeoWiki has two stores, and understanding the split explains the rest of this guide:

- **Canonical data lives in MediaWiki revision slots.** Every Schema, Subject, and Layout is stored as page content
  in a dedicated `neo` revision slot. This is the source of truth, versioned like any other wiki content.
- **Neo4j holds a regenerable secondary projection.** The graph database is a query-optimized copy of the canonical
  data. It can be wiped and rebuilt at any time from the revision slots via
  `maintenance/RebuildGraphDatabases.php`, so it never holds data you cannot recover.

Although the projection is regenerable, **Neo4j is effectively required today**. The wiki throws a `RuntimeException`
on *every* request until both Neo4j connection URLs are configured, and page edits that touch structured data fail if
Neo4j is unreachable. [ADR 019](../adr/019-graph-database-architecture.md) describes the longer-term intent of
treating the graph backend as an optional, pluggable component; that is the direction, not the current behaviour.

## Prerequisites

| Requirement | Notes |
|---|---|
| MediaWiki ≥ 1.43.0 | The minimum supported core version. |
| PHP 8.3 with `ext-json` | `composer.json` requires `^8.3` (PHP 8.3 or a later 8.x). |
| Composer | NeoWiki has runtime Composer dependencies (e.g. `laudis/neo4j-php-client`, `opis/json-schema`). `vendor/` is not shipped, so `composer install` is mandatory. |
| Neo4j 5.x reachable over Bolt | The graph backend. Must be reachable from the wiki over the Bolt protocol. |
| Node (manual install only) | The frontend bundle must be built from source. Built and verified on **Node 24**. Not needed for the Docker method, which builds the bundle inside the image. |

Recommended soft-dependency extensions. NeoWiki loads and runs without them, but you lose the corresponding
functionality:

- **Scribunto** — the Lua API (`nw.*` functions).
- **CodeEditor** — syntax-highlighted JSON editing of Schema, Subject, and Layout pages.
- **ParserFunctions** — commonly used alongside NeoWiki's own parser functions.

## Method A — Docker (recommended)

The repository ships a self-contained Docker stack that is the fastest way to get a working evaluation instance. Do
not duplicate the recipe here — follow the **"Server deployment"** section of the
[Docker deployment README](../../Docker/README.md). In short, after bringing the stack up you run:

```sh
make install-db
make load-neo4j-users
make import-demo-data
```

A few things about the bundled stack that matter for an evaluation:

- **The stack ships its own databases.** It includes its own MariaDB and Neo4j services — you do not need to provide
  either. The bundled Neo4j image is **Enterprise edition on an EVAL (non-production) license**. That license is fine
  for evaluation but is not a basis for production use.
- **The development UI is left ON.** The bundled `SettingsTemplate.php` enables the development-only UIs. For anything
  beyond a throwaway demo, set `$wgNeoWikiEnableDevelopmentUI = false`, and change **every** value marked
  `# Change for production` in `Docker/.env.dist` — at minimum `MARIADB_PASSWORD`, `MW_ADMIN_PASSWORD`,
  `NEO4J_PASSWORD`, `NEO4J_PASSWORD_READ`, and `MW_SERVER`. The defaults are well-known and unsafe to expose.

## Method B — Add to an existing MediaWiki (manual)

Use this method to add NeoWiki to a MediaWiki you already run. The steps below assume the extension is checked out
at `extensions/NeoWiki/` under your MediaWiki root.

### 1. Wire up Composer dependencies

NeoWiki's runtime dependencies are installed via MediaWiki's Composer merge plugin. Add NeoWiki's `composer.json` to
your wiki's root `composer.local.json`:

```json
{ "extra": { "merge-plugin": { "include": [ "extensions/NeoWiki/composer.json" ] } } }
```

Then, from the **MediaWiki root**, install the dependencies:

```sh
composer update
```

### 2. Build the frontend bundle

The compiled frontend lives in `resources/ext.neowiki/dist/`, which is **git-ignored**. A bare clone therefore ships
no JavaScript or CSS, and ResourceLoader has nothing to serve — there is no working UI until you build it. The build
runs standalone and requires Node (built and verified on **Node 24**):

```sh
cd extensions/NeoWiki/resources/ext.neowiki
npm ci && npm run build   # produces dist/neowiki.js + dist/neowiki.css
```

Shipping pre-built JavaScript with releases is the intended future fix, so that operators will not need Node at all.
That is **not in place yet**, so building from source is currently mandatory.

### 3. Load and configure the extension

Add the following to your `LocalSettings.php`:

```php
wfLoadExtension( 'NeoWiki' );

// Required — NeoWiki has no working state without a Neo4j connection.
// Both URLs may point at the same Neo4j instance; use a least-privilege user for the read URL.
$wgNeoWikiNeo4jInternalWriteUrl = 'bolt://neo4j:SECRET@neo4j-host:7687';
$wgNeoWikiNeo4jInternalReadUrl  = 'bolt://mediawiki_read:SECRET@neo4j-host:7687';

// Recommended soft-deps for full functionality.
wfLoadExtension( 'Scribunto' );      // Lua API
wfLoadExtension( 'CodeEditor' );     // JSON editing of Schema/Subject/Layout pages
wfLoadExtension( 'ParserFunctions' );

$wgNeoWikiEnableDevelopmentUI = false; // keep dev UI off
```

Both Neo4j URL settings are **required**: until both are set, the wiki throws a `RuntimeException` on every request.
The URL format is `bolt://user:password@host:7687`; the `neo4j://` scheme and the other connection schemes supported
by the laudis client also work.

### 4. Create the read-only Neo4j user

NeoWiki expects the read URL to use a **least-privilege** user, so that read/query traffic cannot modify the graph
([ADR 013](../adr/013-restrict-neo4j-access.md)). Create that user in Neo4j:

```cypher
CREATE USER mediawiki_read SET PASSWORD 'SECRET' CHANGE NOT REQUIRED;
GRANT ROLE reader TO mediawiki_read;
```

Role-based access control is a **Neo4j Enterprise** feature. On **Community Edition** you cannot create the
role-restricted user, so point both `$wgNeoWikiNeo4jInternalWriteUrl` and `$wgNeoWikiNeo4jInternalReadUrl` at the same
user. This works, but you lose the read/write privilege-separation security layer described in ADR 013.

### 5. Create uniqueness constraints

NeoWiki does **not** create its graph uniqueness constraints automatically yet
([issue #874](https://github.com/ProfessionalWiki/NeoWiki/issues/874)). Until that is wired into install, create them
manually in Neo4j:

```cypher
CREATE CONSTRAINT Page_id IF NOT EXISTS FOR (n:Page) REQUIRE n.id IS UNIQUE;
CREATE CONSTRAINT Subject_id IF NOT EXISTS FOR (n:Subject) REQUIRE n.id IS UNIQUE;
```

### 6. Finish the install

NeoWiki adds no SQL tables of its own, but run MediaWiki's updater anyway — it is standard practice when installing
any extension. From the MediaWiki root:

```sh
php maintenance/run.php update --quick
```

If subject pages already exist in the wiki (for example after an import), build the Neo4j projection from the
canonical revision-slot data:

```sh
php extensions/NeoWiki/maintenance/RebuildGraphDatabases.php
```

## Configuration reference

| Setting | Purpose | Default | Required |
|---|---|---|---|
| `$wgNeoWikiNeo4jInternalWriteUrl` | Bolt URL NeoWiki writes the graph projection through | none | Yes |
| `$wgNeoWikiNeo4jInternalReadUrl` | Bolt URL for read/query traffic (least-privilege user) | none | Yes |
| `$wgNeoWikiEnableDevelopmentUI` | Enables development-only UIs | `false` | No — keep `false` in production |
| `$wgNeoWikiEnforceValidation` | Reject writes that introduce new constraint violations; when `false`, violations are reported but the write still persists | `false` | No |
| `$wgNeoWikiQueryLimits` | Per-tier Cypher caps: `default` 30s/5000 rows, `expensive` 300s/50000 rows | see code | No |

NeoWiki also registers the following access-control and namespace defaults you may want to tune:

- **Rights:** `neowiki-schema-edit`, `neowiki-layout-edit`, and `neowiki-query`. `neowiki-query` is granted to
  **everyone** by default; restrict or grant it per group via `$wgGroupPermissions`.
- **Rate limits** on `neowiki-query`: anonymous 10/60s, logged-in users 60/60s, bots 1000/60s. Adjust via
  `$wgRateLimits`.
- **Namespaces 7474–7477** are registered by NeoWiki. Watch for ID collisions with other extensions that use the same
  range.

## Verify your install

Confirm the install end to end:

1. **Create a Schema.** Create a page in the Schema namespace defining a simple type (e.g. a `Person` schema with a
   `name` property). The CodeEditor soft-dependency gives you JSON syntax highlighting here.
2. **Add a Subject.** On an ordinary wiki page, add a Subject that uses that Schema and set the property. Subjects
   live on normal pages (in the `neo` slot), not in a dedicated namespace.
3. **Render a View.** On a normal wiki page, add a view of the Subject and confirm it renders:

   ```
   {{#view: <your-subject-id>}}
   ```

4. **Hit a REST route.** Confirm the API responds. See the [REST API reference](../reference/rest-api.md) for the
   full list of routes; for example, to fetch a Subject:

   ```sh
   curl -s 'https://your-wiki.example/rest.php/neowiki/v0/subject/<your-subject-id>'
   ```

If all four steps work, the canonical store, the Neo4j projection, the frontend bundle, and the API are all wired up
correctly.
