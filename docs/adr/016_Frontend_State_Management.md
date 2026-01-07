# Frontend State Management

Date: 2025-04-17

Status: Accepted

## Context

We are creating a good number of UIs using Vue and are relatively new to Vue. We want a clear state management strategy.

Our UIs include "display UIs" such as the AutomaticInfobox and "editing UIs" such as the Schema editor.

## Decision

We keep the existing `SubjectStore` and `SchemaStore`.

We use the stores in our display UIs instead of passing Subjects and Schemas via props.

We do NOT use the stores in our editing UIs. We update the stores upon closure of the dialog if a save happened.

Also see the [living State Management document](https://coda.io/d/NeoWiki_dMgp4yhUS-A/State-Management_sufNohUm#_luN-nIdb)

## Consequences

* We need to refactor `AutomaticInfobox` to stop receiving Subject and Schema via props and instead us the stores.
* We need to refactor the editing dialogs to use props instead of the stores.
* We end up with a conceptually simple approach that we can easily follow: one page-wide set of state in the form
  of SchemaStore and SubjectStore that is used by everything that needs to be fully reactive and avoided by other components.

## Alternatives Considered

Also see the [Mattermost thread](https://chat.professional.wiki/pro-wiki/pl/11z1k4twebbipftofad69uhppy)

### Avoiding Vue Stores Altogether

Investigated to increase simplicity and reduce coupling to Vue.

We decided to keep using Stores because they provide reactivity to child components without prop drilling.

### Merging The Stores

To have one `NeoWikiStore` instead of `SubjectStore` and `SchemaStore`.

We decided against this because these entities can evolve independently, and in some places we only need Schemas.
