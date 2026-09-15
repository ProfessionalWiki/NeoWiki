---
title: Naming Conventions
order: 3
---
# Naming Conventions

Code-facing naming rules for NeoWiki development. Domain vocabulary is defined in the [Glossary](glossary.md);
this page covers names that never reach users.

## Data access and selection

- `<X>Lookup` — a read-only data-access interface (e.g. `SubjectLookup`).
- `<X>Repository` — a read-write data-access interface (e.g. `SubjectRepository`).
- `<X>Picker` — a UI component for selecting an existing X (e.g. `SchemaPicker`).

A UI component never shares its name with a data-access interface.

## "Store"

Always qualify which store is meant, in identifiers, docs, and discussion:

- **Pinia store** — a frontend state store, e.g. `SubjectStore`
  ([ADR 30](adr/030-frontend-store-registry-semantics.md)).
- **Graph Store** — a configured projection target ([Glossary](glossary.md)).
- **Technology name** — "SPARQL store", "triple store", used only when the statement is about that technology.

## Registry

`<X>Registry` is reserved for extension-point lookup tables (`PropertyTypeRegistry`, `ViewTypeRegistry`).
Pinia stores follow registry semantics (ADR 30) but are not named `Registry`.

## Vue component suffixes

Components are named noun-first with a role suffix:

- `<X>Display` — renders an X read-only.
- `<X>Input` — a form control for entering a value.
- `<X>Editor` — edits an existing X.
- `<X>Creator` — creates a new X.
- `<X>Picker` — selects an existing X.
- `<X>Dialog` — a modal container; combined as `<X><Role>Dialog` (e.g. `SubjectCreatorDialog`).

## Known unresolved collisions

Do not add new uses of these names until the linked decision lands:

- `RelationType` currently names both the graph edge label and the `relation` Property Type
  ([#630](https://github.com/ProfessionalWiki/NeoWiki/issues/630)).
- `Neo4jPlugin` vs `GraphDatabasePlugin`: which concept "Plugin" names is unsettled
  ([#1001](https://github.com/ProfessionalWiki/NeoWiki/pull/1001)).
