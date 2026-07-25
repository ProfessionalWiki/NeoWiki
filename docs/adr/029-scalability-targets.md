# Scalability Targets

Date: 2026-07-26

Status: Draft

## Context

To make good design and implementation decisions, we need to know how far NeoWiki should scale, so we avoid both
degraded performance at scale and accidental complexity via unnecessary optimization.

## Decision

NeoWiki needs to handle these **total Subject counts**:

* Wikis up to 1 million Subjects should perform well across the board.
* Wikis with ~10 million Subjects ideally remain performing well, though some degradation is acceptable
* Higher scalability is nice. Where it is possible to cheaply support 100 million Subject cases, we should do so

NeoWiki needs to handle these **total Schema counts**:

* Wikis up to 500 Schemas should perform well
* Usability/UX of wikis up to 200 Schemas should be good
* Usability/UX of wikis with ~500 Schemas should not be terrible

NeoWiki needs to handle these **Subjects per Page**:

* Pages with up to 50 Subjects should perform well and have great UX
* Pages with ~250 Subjects should not have terrible performance or UX
