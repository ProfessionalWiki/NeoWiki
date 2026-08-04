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
time, from the shell or [from the wiki](#background-rebuilds). From the MediaWiki root:

```sh
php maintenance/run.php NeoWiki:RebuildGraphDatabases
```

It re-saves every Subject from each page's latest revision into every graph store, and removes the pages MediaWiki no
longer has. Run it to:

- Recover after a graph store wipe or restore.
- Fix any drift between a graph store's copy and the canonical revision slots.

Two things to plan around:

- It reconciles pages, not stray data. Anything the projection never knew about — a node written directly to Neo4j,
  say, or a named graph left behind in a SPARQL store — is not something the rebuild can find. For a guaranteed-clean
  result, empty the graph store before rebuilding: wipe Neo4j's data volume, or drop the wiki's named graphs from it.
- The time scales with the number of pages times the number of configured stores.

### Rebuilding one store

Every graph store has a name: `neo4j` for Neo4j, a SPARQL store's configured `name` (defaulting to its projection),
and for a backend from another extension, whatever that extension registered it under. Rebuild one on its own with:

```sh
php maintenance/run.php NeoWiki:RebuildGraphDatabases --store EDM
```

Rebuilding every store is one such run per store, so an unreachable store costs only its own rebuild.

### Interrupted rebuilds

The rebuild walks the wiki in batches, recording after each one how far it got. If a run failed before reconciling
the whole wiki, continue it from where it stopped rather than starting over:

```sh
php maintenance/run.php NeoWiki:RebuildGraphDatabases --store EDM --resume
```

A run that finished but left individual pages behind has nothing to continue: rebuild that store instead.

Pass `--batch-size` to change how many pages are projected between recordings; it defaults to 200.

A page the store rejects is counted and the rebuild carries on; the `NeoWiki` channel says which pages failed and why.
A store that stops answering partway is another matter: when a whole batch fails the rebuild reopens the store, and
ends the run for `--resume` to retry that batch only if it cannot. A store that still opens means its pages were at
fault, so they are counted and the walk goes on past them. The script exits non-zero whenever a store was left out of
sync, which covers both a failed run and one that finished having left pages behind. Only the first has anything to
resume; rebuild the store for the second.

A rebuild killed outright — `kill -9`, or the machine going down — leaves its run recorded as still going, and every
later rebuild of that store refuses to start while it is. So does a background rebuild whose first batch never
reached the job queue, or whose job the queue lost: that one is left queued rather than running, and blocks the store
just as hard. Release it by cancelling it on
[Special:GraphStores](#background-rebuilds), which keeps the cursor `--resume` continues from. When the wiki is out of
reach, record the run as cancelled directly:

```sql
UPDATE neowiki_rebuild_runs SET nwrr_status = 'cancelled'
WHERE nwrr_store = '<name>' AND nwrr_status IN ( 'running', 'queued' );
```

## Background rebuilds

Rebuilds can also be started from the wiki, without shell access. **Special:GraphStores** shows each configured
store's state and lets you rebuild it or cancel the rebuild it has queued or running. The
[REST API](../api/rest-api.md#graph-stores) does the same for scripting. Both need the
[`neowiki-admin`](installation.md#user-rights) right.

A rebuild started this way runs on MediaWiki's job queue, a batch of pages per job.

- **A background rebuild is only as fast as the job runner.** A default MediaWiki install runs one job per web
  request, which paces a rebuild at 200 pages per request. Run
  [the job queue](https://www.mediawiki.org/wiki/Manual:Job_queue) from cron or a service instead.
- **A store has one rebuild at a time, whoever started it.** Cancelling is on the page and the API, including a
  rebuild the script is running: it stops at its next batch. Continuing a cancelled or failed run is `--resume` on
  the script.

### Stale stores

A store is reported as stale when the Mapping page defining its projection was edited or deleted after that store's
last successful rebuild started: the store still describes every page projected before that change in the old
vocabulary. Rebuilding it is the fix.

Set `$wgNeoWikiAutoRebuildOnMappingChange = true;` to have saving or deleting a Mapping page rebuild every store
holding that projection in the background. It is off by default: such a rebuild reprojects every page carrying a
Subject, and it lets anyone who may edit Mapping pages set that going — work `neowiki-admin` otherwise gates. A
rebuild somebody started by hand is left to finish rather than restarted; the store shows up stale once it ends.

## What happens during a Neo4j outage

- **Editing pages works.** Edits, deletions and undeletions all commit. NeoWiki logs the projection failure on the
  `NeoWiki` channel.
- **Editing and displaying Subjects fails**, along with queries and anything else that reads the graph.

Once Neo4j is back, [rebuild the graph](#rebuilding-the-graph): it repairs both a failed save and a failed delete.

Route the `NeoWiki` log channel somewhere you read. On a default MediaWiki install it goes nowhere, and it carries
both the outage and the pages a rebuild could not reconcile.

## Backups

Back up the MediaWiki database as usual; it holds the canonical data. Neo4j and any SPARQL store need no backup — they
are regenerable copies you can [rebuild](#rebuilding-the-graph) from the revision slots.
