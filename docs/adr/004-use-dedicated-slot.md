# Use a Dedicated MediaWiki Revision Slot

Date: March 2023

Status: Accepted

## Context

We need to store our structured data JSON somewhere in MediaWiki revisions.

## Decision

We do not create dedicated pages for the JSON like in Wikibase. Instead we allow users to create
wikitext pages as usual and then add the JSON to a dedicated revision slot.

## Consequences

* Improved usability compared to Wikibase by having the data on the same page as the wikitext.
* The JSON is reachable through MediaWiki's generic revision surfaces — `action=raw`, `prop=revisions`,
  `Special:Export` and diffs — each gated on the page's `read` permission. The slot is not an access-control boundary:
  MediaWiki has no way to restrict read access to a single slot separately from the page.
* Editing the JSON, and reading it as structured data, require our own web APIs and interfaces.
