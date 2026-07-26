# Scalability Targets

Date: 2026-07-26

Status: Draft

## Context

To make good design and implementation decisions, we need to know how far NeoWiki should scale, so we avoid both
degraded performance at scale and accidental complexity via unnecessary optimization.

## Decision

NeoWiki needs to handle these sizes, counted per graph store (a wiki farm sharing one store counts its total):

| Metric                 | Typical (90% case)  | Great up to                      | Acceptable up to |
|------------------------|---------------------|----------------------------------|------------------|
| Total Subjects         | << 1 million        | 1 million                        | 10 million       |
| Total Schemas          | 5-20 (outliers ~50) | 100 (UX-bound; performance: 500) | 500              |
| Subjects per Page      | < 10                | 50                               | 250              |
| Statements per Subject | < 15                | 50                               | 100              |

Great: performs well with great UX. Acceptable: usable, though some degradation of UX or performance is fine.

Beyond the acceptable bound, support cheaply where possible rather than by design — wikis with 100 million Subjects
being the marker case. High Statement counts are a modelling smell rather than a display problem: the data model's
answer is child Subjects, which the Subjects per Page row budgets for.
