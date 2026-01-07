# Dedicated Editors

Date: 2025-04-15

Status: Accepted

## Context

We designed the InfoboxEditor to edit both Subjects and their Schemas.

This is inspired by tools like Notion and Coda, where users can seamlessly add new properties/columns to their
tables/databases. We discovered that in our "one dialog to edit a single Subject" approach, this leads to unclear
UI, where it is not obvious to the user that if they click on "Add Property", they are affecting all Subjects that
use the same Schema.

Additionally, the InfoboxEditor implementation is full of technical issues and is our main source of technical debt.

## Decision

We implement freshly designed dedicated Subject editing and Schema UIs from scratch
and delete InfoboxEditor and its child components.

## Consequences

* We need to reimplement both Schema and Subject editing UIs
* The UI confusion issue around Schema modification is solved
* The technical debt in InfoboxEditor and child components is solved by deleting the code
* Various UI issues and bugs in InfoboxEditor and child components are solved by deleting the code
* We get an opportunity to carefully redesign the UIs via Figma
* We get an opportunity to cleanly reimplement the UIs and establish better coding patterns for Vue components
* We can use the dedicated Schema editor on pages in the Schema namespace

## Alternatives Considered

Also see the [Mattermost thread](https://chat.professional.wiki/pro-wiki/pl/quyjcga9mpra8r54j7e6u1f57h)

### Refactoring InfoboxEditor

* We need to refactor the code. This might leave us stuck in a local maximum and have low returns per unit of effort.
* The UI confusion issue around Schema modification remains
* We would need to implement an additional dedicated Schema editor to get editing on pages in the Schema namespace

### Reimplementing The Combined UI

* We need to reimplement the UI
* We get an opportunity to redesign the UI and cleanly reimplement the UIs and establish better coding patterns for Vue components
* The UI confusion issue around Schema modification remains
* We would need to implement an additional dedicated Schema editor to get editing on pages in the Schema namespace
