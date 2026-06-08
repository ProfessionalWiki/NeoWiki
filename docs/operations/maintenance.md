---
title: Maintenance
order: 2
---

# Maintaining NeoWiki

This guide covers keeping an evaluation or pilot NeoWiki instance running: rebuilding the graph, backups, upgrades,
operational caveats, and light hardening notes. For first-time setup, see [installation](installation.md).

## Rebuilding the graph

NeoWiki has two stores: the **canonical data** in MediaWiki revision slots (the source of truth) and a **regenerable
secondary projection** in Neo4j. The graph can be wiped and rebuilt at any time from the canonical slots. From the
MediaWiki root:

```sh
php extensions/NeoWiki/maintenance/RebuildGraphDatabases.php
```

This is run by **full path**, not as a registered MediaWiki maintenance-script name (it is not registered with
`maintenance/run.php`). It re-saves every Subject from each page's latest revision.

Run it when you need to:

- **Recover after a Neo4j wipe or restore** — rebuild the projection from the canonical store.
- **Apply a projection-changing upgrade** — see [Upgrades](#upgrades) below.
- **Fix projection drift** — for example, stale `Page.name` values after page moves (see
  [Operational caveats](#operational-caveats)).

Two caveats to plan around:

- **It does not truncate the graph first.** Orphan nodes from a drifted projection can persist after a rebuild. If you
  need a guaranteed-clean result, wipe the Neo4j data directory or volume before rebuilding.
- **It is single-process and sequential.** There is no batching, resume, or throttling, so a rebuild scales linearly
  with the number of pages. Plan downtime on large wikis.

## Backups

Back up the **canonical store and standard MediaWiki state**:

- The **MediaWiki database** — this holds the canonical revision slots, which are the source of truth for all Schemas,
  Subjects, and Layouts.
- **Uploaded files and images**, and **`LocalSettings.php`** — the same MediaWiki state you would back up for any wiki.

**Neo4j does not need to be backed up.** The graph is a regenerable projection that you can always recreate from the
canonical store with the rebuild script above. Backing it up is optional and only a recovery-time optimization — it
saves a rebuild, nothing more.

## Upgrades

NeoWiki adds no SQL tables, so an upgrade is a **code swap** plus MediaWiki's standard updater. From the MediaWiki
root:

```sh
php maintenance/run.php update --quick
```

After upgrades that **change the projection encoding**, also rebuild the graph so the projection matches the new
format:

```sh
php extensions/NeoWiki/maintenance/RebuildGraphDatabases.php
```

Because NeoWiki is **pre-release software**, breaking schema and data-format changes can land between versions without
a migration path. Read the release notes before upgrading, and keep a backup of the MediaWiki database first.

## Operational caveats

Current limitations to be aware of when operating an instance:

- **Page moves leave `Page.name` stale.** Moving or renaming a page does not update the projection, so the `Page.name`
  value in Neo4j drifts until you run a rebuild. Tracked in
  [#875](https://github.com/ProfessionalWiki/NeoWiki/issues/875).
- **A Neo4j outage hard-fails writes.** Graph writes are synchronous with no retry or queue, so edits, deletes, and
  undeletes fail while Neo4j is unreachable; only the read/query path degrades gracefully. Treat Neo4j availability as
  required for write availability. Tracked in [#877](https://github.com/ProfessionalWiki/NeoWiki/issues/877).
- **Keep the development UI off.** Set `$wgNeoWikiEnableDevelopmentUI = false` on any instance others can reach.

## Hardening notes

NeoWiki restricts all graph access to the backend ([ADR 013](../adr/013-restrict-neo4j-access.md)); the wiki is the
only client that talks to Neo4j. To keep an evaluation or pilot instance safe:

- **Use encrypted Bolt.** Prefer the `neo4j+s://` scheme so the connection between the wiki and Neo4j is encrypted.
- **Use strong credentials.** Replace any default passwords, and use a least-privilege user for the read URL.
- **Do not expose graph endpoints publicly.** The Neo4j Bolt endpoint — and any SPARQL endpoint — must not be
  reachable from the public internet.
- **Keep the dev UI off.** As above, `$wgNeoWikiEnableDevelopmentUI = false`.

Before exposing an instance to others, read the [security policy](../../SECURITY.md).
