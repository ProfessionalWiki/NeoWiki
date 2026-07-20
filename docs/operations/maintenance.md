---
title: Maintenance
order: 2
---

# Maintaining NeoWiki

Upkeep tasks for an evaluation NeoWiki instance. For first-time setup, see [installation](installation.md).

## Rebuilding the graph

NeoWiki stores its canonical data in MediaWiki revision slots and keeps a regenerable copy in Neo4j for querying. You
can wipe and rebuild the Neo4j copy from the canonical slots at any time. From the MediaWiki root:

```sh
php maintenance/run.php NeoWiki:RebuildGraphDatabases
```

It re-saves every Subject from each page's latest revision, and removes the pages MediaWiki no longer has. Run it to:

- Recover after a Neo4j wipe or restore.
- Fix any drift between the Neo4j copy and the canonical revision slots.

Two things to plan around:

- It reconciles pages, not stray data. A node the projection never knew about — one written directly to Neo4j, say —
  is not something the rebuild can find. For a guaranteed-clean result, wipe the Neo4j data volume before rebuilding.
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

Back up the MediaWiki database as usual; it holds the canonical data. Neo4j needs no backup — it is a regenerable copy
you can [rebuild](#rebuilding-the-graph) from the revision slots.
