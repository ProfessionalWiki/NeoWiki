# Views

Date: 2025-05-09

Status: Draft

## Context

We will need a way to let users customize how Subjects are displayed.

Examples of things users might wish to change:
* The precision of a number
* The color of a progress bar
* The ordering of the shown Statements
* Which Statements to show

It should be possible for users to have multiple displays of a Subject shown on one page, and it should be possible
for those displays to be different. For instance, on a page about a company, there might be an infobox at the top of
the page with key data, while in the financial section there is an infobox with detailed information via Properties
like Revenue, Net income, Total assets, etc.

## Decision

We introduce a View concept.

A View is linked to a Schema, and allows customized display of Subjects that use that Schema.

Views have a View Type like "infobox", "factbox", or "table". The View Type affects what View Attributes can be set.

Things that would be implemented as View Attributes:
* Which Statements to show (specified via Property names)
* Ordering of Statements (specified via Property names)
* Statement-level display information like precision and color. This depends on the Property Type of the Statement.
* View Type specific display information like the border color of an Infobox

## Consequences



## Alternatives Considered
