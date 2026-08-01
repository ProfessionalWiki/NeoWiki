# Scalability Targets

Date: 2026-07-26

Status: Draft

## Context

To make good design and implementation decisions, we need to know how far NeoWiki should scale, so we avoid both
degraded performance at scale and accidental complexity via unnecessary optimization.

Informed estimates of what real deployments look like:

| Metric                 | 80% of wikis stay under | 99% of wikis stay under |
|------------------------|-------------------------|-------------------------|
| Total Subjects         | 100 thousand            | 10 million              |
| Total Schemas          | 30                      | 100                     |
| Subjects per Page      | 10                      | 50                      |
| Statements per Subject | 15                      | 50                      |

The Great tier below matches the 99% column.

## Decision

The targets are lower bounds. Scaling beyond them is nice and should be done where it is possible and cheap.

### Scalability Targets

NeoWiki needs to handle these sizes, counted per graph store (so for wiki farms we combine all wikis):

| Metric                 | Great up to                      | Acceptable up to |
|------------------------|----------------------------------|------------------|
| Total Subjects         | 10 million                       | 50 million       |
| Total Schemas          | 100 (UX-bound; performance: 500) | 500              |
| Subjects per Page      | 50                               | 250              |
| Statements per Subject | 50                               | 100              |

Definitions:

* **Great**: performs well with great UX, covers essentially all real deployments
* **Acceptable**: usable, though some degradation of UX or performance is fine. To keep outliers functional

### Writing Speed Targets

NeoWiki needs to meet these write speeds at any size within the scalability targets above, with all configured
projections included. The duration columns follow from the target rate:

| Write path                                       | Subjects/second | 100 thousand Subjects | 1 million Subjects | 10 million Subjects |
|--------------------------------------------------|-----------------|-----------------------|--------------------|---------------------|
| Import throughput (`importDump.php`)             | 100+            | < 20 minutes          | < 3 hours          | ~1 day              |
| Projection rebuild (`RebuildGraphDatabases.php`) | 500+            | ~3 minutes            | ~30 minutes        | < 6 hours           |

Max latency an interactive save gains from projections: 100 ms
