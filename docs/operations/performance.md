---
title: Performance
order: 3
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

Each rate is the mean of two probes run alternately against the same graph. Throughput does not decay as the graph grows:
the rate holds flat across a 2 000-page import, and the probes against
the million-node graph run no slower than those against a small one.

That import rate is 16.6 ms per page of ten Subjects, MediaWiki's own revision write included. Page saves project in
the same request rather than through the job queue, so an edit does that projection work too.

Reference machine: a 16-core desktop (Ryzen 9 9950X, 60 GiB RAM, NVMe) running MediaWiki, MariaDB and Neo4j together
in one Docker stack, so all three compete for the same cores, with Neo4j held to the stack's default 512 MB heap. The
million-node graph is padded with synthetic nodes carrying no relationships, where a real graph of that size carries
millions of them, and the wiki behind it held 4 000 pages rather than 100 000.

## With a SPARQL store

Project into a SPARQL store as well and bulk import averages 36 Subjects/second, falling as the store fills: over one
2 000-page import — two projections, native RDF and EDM, into one QLever store — the rate dropped from 101 to
21 Subjects/second as the store grew to 590 000 triples. About 90% of the per-page cost across that run was the SPARQL
projection, which is the write path's remaining bottleneck. It had not levelled off by the end, so a fuller store is
slower than 21 Subjects/second, not the 36 above. ADR 29's targets count every configured projection, so this is the
configuration to judge a SPARQL-backed wiki by.

## How it was measured

Bulk import is MediaWiki's `importDump.php`, run with secondary link updates off. Backend validation does not run on
that path, so the import figures are unvalidated writes, and validation is measured on its own: 20 000 validations of
one Subject in a single process. The rebuild figure is
[`NeoWiki:RebuildGraphDatabases`](maintenance.md#rebuilding-the-graph) re-projecting the whole wiki. The corpus is
synthetic, from `NeoWiki:GeneratePerformanceDump`: ten Subjects per page, twelve Statements per Subject, three of them
relations to Subjects on other pages.

## Measure it yourself

From a [development stack](https://github.com/ProfessionalWiki/NeoWiki/blob/master/README.md#performance-test-data):

```sh
make perf-generate pages=1000   # writes a dump of 1 000 pages carrying 10 000 Subjects
make perf-import                # imports it, reporting pages and Subjects per second
```

To time a rebuild of what you imported, run [`NeoWiki:RebuildGraphDatabases`](maintenance.md#rebuilding-the-graph)
under `time`.
