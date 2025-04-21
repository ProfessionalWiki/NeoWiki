# Use a Dedicated MediaWiki Revision Slot

Date: April 2023

Status: Accepted but modified by ADR 14

## Context

We need unique identifiers for Subjects.

Semantic MediaWiki uses page titles. This is not viable since we support multiple Subjects per page.

Wikibase use incrementing numeric IDs. It generates those by storing the current highest ID in
database table and doing a query to generate a new ID. This means IDs can only be generated with
access to the database, and that conflicts occur when transferring data between wikis.

## Decision

We use GUIDs as identifiers for Subjects. They are generated on the backend upon Subject creation.

We use the UUID version 7 format: https://uuid.ramsey.dev/en/stable/rfc4122/version7.html#rfc4122-version7

> Version 7 UUIDs are binary-compatible with ULIDs (universally unique lexicographically-sortable identifiers).
> Both use a 48-bit timestamp in milliseconds since the Unix Epoch, filling the rest with random data. Version 7
> UUIDs then add the version and variant bits required by the UUID specification, which reduces the randomness
> from 80 bits to 74. Otherwise, they are identical.

## Consequences

* IDs could be generated anywhere (this does not mean we actually allow doing so, just that we have the option).
* We do not need a database table to store the current highest ID.
* Subjects can be transferred between wikis with minimal clashes.
* Subject IDs can be sorted by creation date.
* Subject IDs are not human-readable.
* Subject IDs cannot be derived from the wiki page title. A query is needed to find the page for a given Subject ID.
