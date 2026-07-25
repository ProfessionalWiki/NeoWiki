# Scalability Targets

Date: 2026-07-26

Status: Accepted

## Context

Design decisions keep trading implementation simplicity against behavior at scale — most recently the orphan-stub
sweep in [PR #1171](https://github.com/ProfessionalWiki/NeoWiki/pull/1171), where a per-save scan acceptable at demo
scale would not survive a large deployment — without a recorded target to judge them by. The unit that drives cost is
the number of Subjects in one graph store, farm-wide when wikis share it.

## Decision

* Need to work well with 1 million Subjects
* Good to still work well with 10 million Subjects, not critical, and some degradation acceptable
* Scalability beyond 50 million Subjects is a plus but with diminishing returns

## Consequences

* Per-write work must be proportional to what the write changed, not to the size of the graph.
* Bulk import and full projection rebuilds get wall-clock budgets derived from these sizes.
* Existing code that predates these targets is brought into line through tracked issues as gaps are found.
