---
title: Performance
order: 4
---

# Performance

How fast NeoWiki writes data, so you can judge whether it keeps up with your collection. The sizes and speeds it is
built to sustain are recorded in [ADR 29: Scalability Targets](../adr/029-scalability-targets.md). Read and query
performance is not measured yet.

## Measured write throughput

Neo4j projection only, against a graph already holding a million nodes — this corpus projects roughly one node per
Subject.

| Measured | NeoWiki | Bulk import | Full rebuild | Validation |
|---|---|---:|---:|---:|
| 2026-08-01 | `851f44ae` | 602 Subjects/second | ~600 Subjects/second | 1.65 ms/Subject |

Each rate is the mean of two probes run alternately against the same graph. Import throughput does not decay as the
graph grows: the rate holds flat across a 2 000-page import, and the probes against the million-node graph run no
slower than those against a small one.

That import rate is 16.6 ms per page of ten Subjects, MediaWiki's own revision write included. Page saves project in
the same request rather than through the job queue, so an edit does that projection work too.

Reference machine: a 16-core desktop (Ryzen 9 9950X, 60 GiB RAM, NVMe) running MediaWiki, MariaDB and Neo4j in one
Docker stack, with Neo4j held to the stack's default 512 MB heap and each SPARQL engine, when configured, to 1.5 GB.
The million-node graph is padded with synthetic nodes carrying no relationships, where a real graph of that size
carries millions of them, and the wiki behind it held 4 000 pages rather than 100 000.

## With a SPARQL store

A [SPARQL store](installation.md#optional-sparql-graph-stores) adds the
[RDF projection and its SPARQL query surface](../api/rdf-export.md) alongside Neo4j, and every write then pays for
it — how much depends on the engine. Measured 2026-08-09 on `adbdd656`, the QLever column 2026-10-24 on `3534b882`:
a fresh wiki grown to 20 000 Subjects / 590 000 triples (~30 triples per Subject), two projections per page into one
store, whole-run means of two imports per engine. These rates are not comparable to the million-node table above; this
corpus is fifty times smaller and unpadded.

| Write path | No SPARQL store | With Oxigraph | With QLever |
|---|---:|---:|---:|
| Bulk import | 402 Subjects/second | 282 Subjects/second | 206 Subjects/second |
| Full rebuild | 1 035 Subjects/second | 415 Subjects/second | 252 Subjects/second |
| Interactive save | 40 ms | 44–54 ms | 59–61 ms |

The QLever column assumes you enable QLever's automatic background index rebuild, as the bundled Docker stacks do:
`--rebuild-index-strategy automatic:10000:50000:0.1`. Without it, a save at 20 000 Subjects costs about 0.7 s more
than without a SPARQL store. With it, at 100 000 Subjects a save costs 26 ms more right after a rebuild and 48 ms
more just before the next, and a rebuild takes under a second; at 200 000 Subjects a save costs 39–45 ms and
52–118 ms more respectively. Both engines end up holding the identical projection, triple for triple.

For an actively edited wiki, project into Oxigraph: at the measured size it meets
[ADR 29](../adr/029-scalability-targets.md)'s import and interactive-save targets with room and misses its rebuild
target by about a fifth. QLever meets the same two targets up to at least 100 000 Subjects, with less room (it
imported at 128 Subjects/second there), and misses the rebuild target by half, by more as the wiki grows
(144 Subjects/second at 100 000) — the cost of an engine built for query speed over large corpora, which this page does
not measure. From about 200 000 Subjects its saves can exceed the interactive-save target. If you configure QLever
alongside Oxigraph, every save still pays QLever's latency. Beyond the measured sizes both engine columns are
extrapolation: Oxigraph's curve has stayed near-flat (within one import its rate declined about 1.2×), QLever's rises.

## How it was measured

Bulk import is MediaWiki's `importDump.php`, run with secondary link updates off (a real import still runs those
afterwards); loading over the REST API instead pays interactive-save latency per Subject. Backend validation does not
run on the import path, so the import figures are unvalidated writes — the REST saves are validated — and validation is
also measured on its own: 20 000 validations of one Subject in a single process. The rebuild figure is
[`NeoWiki:RebuildGraphDatabases`](maintenance.md#rebuilding-the-graph) re-projecting the whole wiki. Interactive save is
the median of 60 to 100 REST subject creates and replaces, with the store at the size above; QLever's figures at 100 000
and 200 000 Subjects come from a wiki grown in 10 000-Subject imports, measured right after and just before a rebuild.
The corpus is synthetic, from `NeoWiki:GeneratePerformanceDump`: ten Subjects per page, twelve Statements per Subject,
three of them relations to Subjects on other pages.

## Measure it yourself

From a [development stack](https://github.com/ProfessionalWiki/NeoWiki/blob/master/README.md#performance-test-data):

```sh
make perf-generate pages=1000   # writes a dump of 1 000 pages carrying 10 000 Subjects
make perf-import                # imports it, reporting pages and Subjects per second
```

To time a rebuild of what you imported, run [`NeoWiki:RebuildGraphDatabases`](maintenance.md#rebuilding-the-graph)
under `time`.

A stock development stack projects into QLever, so this measures the slowest column above. Point
`$wgNeoWikiSparqlStores` in `Docker/LocalSettings.local.php` at the configuration you are evaluating — `[]` for
none.
