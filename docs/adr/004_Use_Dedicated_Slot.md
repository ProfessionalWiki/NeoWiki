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
* The structured data JSON is only accessible via our own interfaces. This improves security and control but also means
  that we need to create our own web APIs and interfaces.
