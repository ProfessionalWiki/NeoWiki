# Multiple Subjects Per Page

Date: April 2023

Status: Accepted

## Requirements

* Native support for "subobjects" like in Semantic MediaWiki
* Ability to add Notion-like tables to wiki pages

## Decision

We allow creating multiple subjects on one page.

One subject is the "main subject" and the others are "child subjects". They are all stored in the same revision slot.

If we should have some automatic relation between the main and child subjects is left as an open question.

## Consequences

* There no longer is a one-to-one mapping between pages and subjects.
