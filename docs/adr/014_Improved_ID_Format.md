# Improved ID Format

Date: 2024-09-16

Status: Accepted

## Context

NeoWiki started off with GUIDs for Subject IDs (ADR 5) and for Relations (ADR 10).

We have since realized that according to some, GUIDs are not a good format. In particular, the blog post
at https://www.unkey.com/blog/uuid-ux, plus seeing nanoid style IDs in various popular modern products.

## Decision

We switch to a nanoid-inspired ID format that retains the time-based sortability of our current format (UUID version 7).

We do not provide backwards compatibility with or migration for the old format since there are no production systems yet.

## Consequences

* We use the new `IdGenerator` service as implemented in the `ProductionIdGenerator` experiment
* Sortability is retained down to the microsecond
* We break backwards compatibility
* Our ID format is less standard than UUID
* Less storage is used due to shorter IDs
* IDs are more aesthetically pleasing and generally cool

## Alternatives Considered

### Staying UUID Version 7

* Least work (no change needed)
* IDs are harder to copy due to the hyphens
* More storage is used due to unnecessary ID length
* IDs might offend some people's sense of aesthetics

### Using Standard Nanoid

* Can use a standard PHP package: https://packagist.org/packages/hidehalo/nanoid-php
    * Less code to maintain
    * Extra dependency
* We lose sortability
