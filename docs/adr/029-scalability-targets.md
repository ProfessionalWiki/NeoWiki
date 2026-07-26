# Scalability Targets

Date: 2026-07-26

Status: Draft

## Context

To make good design and implementation decisions, we need to know how far NeoWiki should scale, so we avoid both
degraded performance at scale and accidental complexity via unnecessary optimization.

The documented scalability failures of comparable systems are on the write path — bulk-import throughput and rebuild
wall-clock — rather than query latency, so the write path gets explicit budgets.

## Decision

NeoWiki needs to handle these sizes, counted per graph store (so for wiki farms we combine all wikis):

| Metric                 | Typical (90% case)  | Great up to                      | Acceptable up to |
|------------------------|---------------------|----------------------------------|------------------|
| Total Subjects         | << 1 million        | 1 million                        | 10 million       |
| Total Schemas          | < 30                | 100 (UX-bound; performance: 500) | 500              |
| Subjects per Page      | < 10                | 50                               | 250              |
| Statements per Subject | < 15                | 50                               | 100              |

Great: performs well with great UX. Acceptable: usable, though some degradation of UX or performance is fine.

The numbers for "Great" and "Acceptable" are lower bounds. Scaling beyond them is nice, and should be done
where it is possible cheaply.

NeoWiki needs to meet these **write-path budgets**:

* Bulk import should sustain at least 100 validated Subjects per second (the fastest tooling in common use by
  comparable systems sustains 1-4 entities per second)
* A full projection rebuild of a wiki at the 1 million Subject tier should take minutes, not hours
