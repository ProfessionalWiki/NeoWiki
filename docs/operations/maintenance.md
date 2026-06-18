---
title: Maintenance
order: 2
---

# Maintaining NeoWiki

This covers the upkeep an evaluation NeoWiki instance needs: rebuilding the graph and the current limitations to be
aware of. For first-time setup, see [installation](installation.md).

## Rebuilding the graph

NeoWiki stores its canonical data in MediaWiki revision slots and keeps a regenerable copy in Neo4j for querying. You
can wipe and rebuild the Neo4j copy from the canonical slots at any time. From the MediaWiki root:

```sh
php maintenance/run.php NeoWiki:RebuildGraphDatabases
```

It re-saves every Subject from each page's latest revision. Run it to:

- Recover after a Neo4j wipe or restore.
- Fix any drift between the Neo4j copy and the canonical revision slots.

Two things to plan around:

- It does not clear the graph first, so orphan nodes from a drifted projection can survive a rebuild. For a
  guaranteed-clean result, wipe the Neo4j data volume before rebuilding.
- It runs as a single sequential process with no batching or resume, so the time scales with the number of pages. Plan
  downtime on large wikis.

## Upgrades

NeoWiki adds no database tables of its own, so an upgrade is a code swap plus MediaWiki's standard updater. Update the
NeoWiki code, then run the updater from the MediaWiki root:

```sh
php maintenance/run.php update --quick
```

If the new version changes how data is stored in the graph, [rebuild the projection](#rebuilding-the-graph) afterwards
so it matches.

NeoWiki is pre-release, so a new version can change the schema or data format with no migration path. Your evaluation
data may not survive an upgrade, so be ready to rebuild it from scratch.

## Current limitations

One known limitation while NeoWiki is pre-release:

- **A Neo4j outage blocks writes.** Edits, deletes, and undeletes fail while Neo4j is unreachable; only reads and
  queries keep working. Tracked in #877.
