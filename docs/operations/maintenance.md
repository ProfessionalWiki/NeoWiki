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

It re-saves every Subject from each page's latest revision, and removes the pages MediaWiki no longer has. Run it to:

- Recover after a Neo4j wipe or restore.
- Fix any drift between the Neo4j copy and the canonical revision slots.

Two things to plan around:

- It reconciles pages, not stray data. A node the projection never knew about — one written directly to Neo4j, say —
  is not something the rebuild can find. For a guaranteed-clean result, wipe the Neo4j data volume before rebuilding.
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

## What happens during a Neo4j outage

The graph is a regenerable copy, so NeoWiki never fails an edit because it could not write to it. Ordinary wiki
editing survives an outage; structured data does not.

- **Editing pages works.** Edits, deletions and undeletions all commit. NeoWiki logs the projection failure on the
  `NeoWiki` channel and stays out of the way.
- **Editing and displaying Subjects fails**, along with queries and anything else that reads the graph. Subjects are
  resolved to their page through an index that lives only in Neo4j.

Once Neo4j is back, [rebuild the graph](#rebuilding-the-graph). It re-saves the pages that still exist and removes the
ones MediaWiki no longer has, repairing both a failed save and a failed delete, and it names any page it could not
reconcile.

Route the `NeoWiki` log channel somewhere you read. On a default MediaWiki install it goes nowhere, which leaves the
rebuild's output as your only sign that anything went wrong.

## Current limitations

One known limitation while NeoWiki is pre-release:

- **NeoWiki requires a configured graph backend.** The subject-to-page index lives only in Neo4j, so a wiki with no
  backend configured cannot create, edit or display Subjects. Tracked in
  [#895](https://github.com/ProfessionalWiki/NeoWiki/issues/895).
