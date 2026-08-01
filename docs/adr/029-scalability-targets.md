# Scalability Targets

Date: 2026-07-26

Status: Draft

## Context

To make good design and implementation decisions, we need to know how far NeoWiki should scale, so we avoid both
degraded performance at scale and accidental complexity via unnecessary optimization.

## Decision

### Scalability Targets

NeoWiki needs to handle these sizes, counted per graph store (so for wiki farms we combine all wikis):

| Metric                 | Great up to                      | Acceptable up to |
|------------------------|----------------------------------|------------------|
| Total Subjects         | 10 million                       | 50 million       |
| Total Schemas          | 100 (UX-bound; performance: 500) | 500              |
| Subjects per Page      | 50                               | 250              |
| Statements per Subject | 50                               | 100              |

Most real wikis sit well below these targets: under ~100 thousand Subjects, 30 Schemas, 10 Subjects per page and
15 Statements per Subject (per surveyed GLAM collection sizes; enterprise wikis run smaller still).

### Writing Speed Targets

NeoWiki needs to meet these write speeds at any size within the scalability targets above, with all configured
projections included. The duration columns are at the Great rate:

| Write path                                          | Great                | Acceptable          | 1 million    | 10 million |
|-----------------------------------------------------|----------------------|---------------------|--------------|------------|
| Import throughput (`importDump.php`)                | 100+ Subjects/second | 25 Subjects/second  | < 3 hours    | ~1 day     |
| Projection rebuild (`RebuildGraphDatabases.php`)    | 500+ Subjects/second | 100 Subjects/second | ~30 minutes  | < 6 hours  |
| Latency an interactive save gains from projections  | under 100 ms         | under 1 second      | —            | —          |

Common Wikibase tooling measures 1-4 entities per second, putting a 1 million import there at days to weeks.

### Definitions

* **Great**: performs well with great UX, covers essentially all real deployments
* **Acceptable**: usable, though some degradation of UX or performance is fine. To keep outliers functional

The numbers for "Great" and "Acceptable" are lower bounds. Scaling beyond them is nice, and should be done
where it is possible cheaply.
