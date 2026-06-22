---
title: Installation
order: 1
---

# Installing the NeoWiki Demo

NeoWiki is pre-release software. It is not production ready, and breaking changes can land at any time without a
migration path. Treat any install as an evaluation or pilot and run it on disposable data.

There are two ways to install NeoWiki, both covered below. The Docker stack is self-contained and the fastest way to a
working wiki, so use it for evaluation. The manual install adds NeoWiki to a MediaWiki you already run.

To install the development environment, see the [README](../../README.md) instead.

## Method A: Docker

You need:

- Docker with Docker Compose
- GNU Make

The commands assume a Unix-like shell, so on Windows run them under WSL.

Download the `ProfessionalWiki/NeoWiki` repository and run this from its root:

```sh
make demo
```

This pulls the latest demo image, starts the stack, installs the wiki, and loads the demo
data. Later, `make pull` refreshes the image and `make down` stops and removes the containers.

For an empty wiki without the sample data, run `make up && make install-db && make load-neo4j-users` instead.

Open `http://localhost:8484` and log in as `AdminName` with the password `AdminPassword`.

You now have a complete evaluation instance with the development UI enabled and demo data loaded.

### Optional: share the demo with others

By default, the stack runs on localhost. To let others reach it, host it on a server. Edit `Docker/.env` and change
every value marked `# Change for production`, which covers the passwords and `MW_SERVER`. Then start the stack with the
`server` profile, which adds automatic HTTPS through Caddy:

```sh
docker compose --profile server up -d
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
| Neo4j 5.x over Bolt       | The graph backend. A reachable instance is required. |
| Node.js 24 or later       | Needed only to build the frontend bundle in step 2. |

These extensions are recommended. NeoWiki runs without them, but you lose the matching functionality:

- **Scribunto** adds the Lua API and its `nw.*` functions.
- **CodeEditor** adds JSON syntax highlighting when you edit Schema and Layout pages.
- **ParserFunctions** is commonly used alongside NeoWiki's parser functions.

The steps below assume the extension is checked out at `extensions/NeoWiki/` under your MediaWiki root.

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

The compiled frontend is git-ignored, so a fresh clone ships no UI until you build it. The build is standalone and
needs Node.js 24 or later:

```sh
cd extensions/NeoWiki/resources/ext.neowiki
npm ci && npm run build
```

This produces `dist/neowiki.js` and `dist/neowiki.css`.

### 3. Load and configure the extension

Add the following to your `LocalSettings.php`:

```php
wfLoadExtension( 'NeoWiki' );

// Required. NeoWiki has no working state without a Neo4j connection.
// For a simple setup, point both URLs at the same Neo4j user.
$wgNeoWikiNeo4jInternalWriteUrl = 'bolt://neo4j:SECRET@neo4j-host:7687';
$wgNeoWikiNeo4jInternalReadUrl  = 'bolt://neo4j:SECRET@neo4j-host:7687';

// Recommended soft dependencies.
wfLoadExtension( 'Scribunto' );
wfLoadExtension( 'CodeEditor' );
wfLoadExtension( 'ParserFunctions' );
```

Both URLs are required. Until both are set, the wiki throws a `RuntimeException` on every request. The format is
`bolt://user:password@host:7687`.

### 4. Run the updater

Run this from the MediaWiki root:

```sh
php maintenance/run.php update --quick
```

If your wiki already has subject pages, build the Neo4j projection from MediaWiki:

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

4. **Query the graph.** Only this step checks the Neo4j projection. On any page, add a Cypher query that lists the
   stored pages, independent of your data model:
   ```
   {{#cypher_raw: MATCH (p:Page) RETURN p.name }}
   ```
   The result renders as JSON. If Neo4j is unreachable, it renders an error instead.

If all four steps work, your install is complete.

## Key settings

These are the settings you are most likely to change. For the full list with descriptions and defaults, see the
`config` section of [`extension.json`](https://github.com/ProfessionalWiki/NeoWiki/blob/master/extension.json).

| Setting | Purpose | Default | Required |
|---|---|---|---|
| `$wgNeoWikiNeo4jInternalWriteUrl` | Bolt URL for writing the graph projection | _none_ | Yes |
| `$wgNeoWikiNeo4jInternalReadUrl` | Bolt URL for read and query traffic | _none_ | Yes |
| `$wgNeoWikiEnableDevelopmentUI` | Enables development-only UIs | `false` | No |
| `$wgNeoWikiEnforceValidation` | Rejects writes that introduce new constraint violations | `false` | No |

## Production hardening

NeoWiki is pre-release, so this is not needed for an evaluation. It applies later, when NeoWiki is production-ready.

### Separate read and write Neo4j users

This applies only to wikis that use Neo4j as the graph backend.

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
