---
title: Maintenance
order: 2
---

# Maintaining NeoWiki

Upkeep tasks for an evaluation NeoWiki instance. For first-time setup, see [installation](installation.md).

## Rebuilding the graph

NeoWiki stores its canonical data in MediaWiki revision slots and keeps a regenerable copy in each configured backend
— Neo4j and any SPARQL store — for querying. You can wipe and rebuild that copy from the canonical slots at any time.
From the MediaWiki root:

```sh
php maintenance/run.php NeoWiki:RebuildGraphDatabases
```

It re-projects every page on the wiki from its latest revision into every backend, and removes the pages MediaWiki
no longer has. Run it to:

- Recover after a backend wipe or restore.
- Fix any drift between a backend's copy and the canonical revision slots.

Two things to plan around:

- It reconciles pages, not stray data. Anything the projection never knew about — a node written directly to Neo4j,
  say, or a named graph left behind in a SPARQL store — is not something the rebuild can find. For a guaranteed-clean
  result, empty the backend before rebuilding: wipe Neo4j's data volume, or drop the wiki's named graphs from the store.
- It runs as a single sequential process with no batching or resume, so the time scales with the number of pages. Plan
  downtime on large wikis.

## Upgrades

Update the NeoWiki code, then run MediaWiki's standard updater from the root:

```sh
php maintenance/run.php update --quick
```

If the new version changes how data is stored in the graph, [rebuild the projection](#rebuilding-the-graph) afterwards.

NeoWiki is pre-release, so a new version can change the canonical revision-slot format with no migration path. Your
evaluation data may not survive an upgrade, so be ready to recreate it from scratch — a projection rebuild does not
recover data the new version can no longer read.

## What happens during a Neo4j outage

- **Editing pages works.** Edits, deletions and undeletions all commit. NeoWiki logs the projection failure on the
  `NeoWiki` channel.
- **Editing and displaying Subjects fails**, along with queries and anything else that reads the graph.

Once Neo4j is back, [rebuild the graph](#rebuilding-the-graph): it repairs both a failed save and a failed delete. It
names any page it could not reconcile; re-run it once you have cleared the cause.

Route the `NeoWiki` log channel somewhere you read. On a default MediaWiki install it goes nowhere, which leaves the
rebuild's output as your only sign that anything went wrong.

## Backups

Back up the MediaWiki database as usual; it holds the canonical data. Neo4j and any SPARQL store need no backup — they
are regenerable copies you can [rebuild](#rebuilding-the-graph) from the revision slots.
