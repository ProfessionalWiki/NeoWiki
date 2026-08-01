---
title: Maintenance
order: 3
---

# Maintaining NeoWiki

Upkeep tasks for an evaluation NeoWiki instance. For first-time setup, see [installation](installation.md); for
moving to a newer version, see [upgrading](upgrading.md).

## Rebuilding the graph

NeoWiki stores its canonical data in MediaWiki revision slots and keeps a regenerable copy in each configured graph
store — Neo4j and any SPARQL store — for querying. You can wipe and rebuild that copy from the canonical slots at any
time. From the MediaWiki root:

```sh
php maintenance/run.php NeoWiki:RebuildGraphDatabases
```

It re-projects every page on the wiki from its latest revision into every backend, and removes the pages MediaWiki
no longer has. Run it to:

- Recover after a graph store wipe or restore.
- Fix any drift between a graph store's copy and the canonical revision slots.

Two things to plan around:

- It reconciles pages, not stray data. Anything the projection never knew about — a node written directly to Neo4j,
  say, or a named graph left behind in a SPARQL store — is not something the rebuild can find. For a guaranteed-clean
  result, empty the graph store before rebuilding: wipe Neo4j's data volume, or drop the wiki's named graphs from it.
- Each store is rebuilt after the previous one, so the time scales with the number of pages times the number of
  configured stores.

### Rebuilding one store

Every graph store has a name: `neo4j` for Neo4j, for a SPARQL store the `name` you configured (defaulting to its
projection), and for one contributed by another extension whatever that extension registered it as. Rebuild one on its
own with:

```sh
php maintenance/run.php NeoWiki:RebuildGraphDatabases --store EDM
```

A rebuild is always of one store, so the run above is what running it for every store does, once per store. A store
that is unreachable therefore costs only its own rebuild: the other stores still reconcile.

### Interrupted rebuilds

The rebuild walks the wiki in batches, recording after each one how far it got. If a run failed before reconciling the
whole wiki, continue it from where it stopped rather than starting over:

```sh
php maintenance/run.php NeoWiki:RebuildGraphDatabases --store EDM --resume
```

A run that finished but left individual pages behind has nothing to continue: rebuild that store instead.

Pass `--batch-size` to change how many pages are projected between recordings; it defaults to 200.

A page the store rejects is logged on the `NeoWiki` channel and counted, and the rebuild carries on. The script exits
non-zero whenever a store was left out of sync, so a scheduled rebuild cannot fail silently. Where a run reports pages
it could not reconcile, the `NeoWiki` channel says which ones and why.

A rebuild killed outright — `kill -9`, or the machine going down — leaves its run recorded as still going in the
`neowiki_rebuild_runs` table, and every later rebuild of that store refuses to start while it is. Release it by
recording the run as cancelled, which keeps the cursor `--resume` continues from:

```sql
UPDATE neowiki_rebuild_runs SET nwrr_status = 'cancelled' WHERE nwrr_id = <id>;
```

## What happens during a Neo4j outage

- **Editing pages works.** Edits, deletions and undeletions all commit. NeoWiki logs the projection failure on the
  `NeoWiki` channel.
- **Editing and displaying Subjects fails**, along with queries and anything else that reads the graph.

Once Neo4j is back, [rebuild the graph](#rebuilding-the-graph): it repairs both a failed save and a failed delete.

Route the `NeoWiki` log channel somewhere you read. On a default MediaWiki install it goes nowhere, and it is where
both the outage and the pages a rebuild could not reconcile are reported.

## Backups

Back up the MediaWiki database as usual; it holds the canonical data. Neo4j and any SPARQL store need no backup — they
are regenerable copies you can [rebuild](#rebuilding-the-graph) from the revision slots.
