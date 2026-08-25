---
title: Glossary
order: 1
---
# Glossary

Definitions of NeoWiki terms. Concepts are capitalized. Used in the code and UI
([Ubiquitous Language](https://softwaresystemdesign.com/domain-driven-design/ubiquitous-language/)).

## Page

MediaWiki concept. Also known as "Wiki page".

Pages have

* A **title**: shown in the URL and H1, can be changed by "moving" the page.
* An **id**: persistent numeric ID.
* **Content**: wikitext
* **Subjects**: list of Subjects, can be empty ([ADR 7](adr/007-multiple-subjects-per-page.md))
* **Main Subject**: optional identifier of a Subject in the page's Subjects list. Indicates which Subject represents the same entity as the page itself. All other Subjects stored on a page are called **Child Subjects**.

## Subject

Data about one thing. Similar to an Item in Wikibase or a Page/SubObject in SMW.

Subjects have

- An `id`: persistent identifier. Subject IDs start with `s` and are always 15 characters long ([ADR 14](adr/014-improved-id-format.md))
- A `schema`: reference to a Schema by name. Example: Person, Company, Product, etc.
- An optional `label`: the name of the subject. Example: "John Doe". This is a string, not a reference to a page.
  Without one, the Subject is shown under its page name when it is the page's Main Subject, and under its Schema name
  otherwise ([ADR 31](adr/031-optional-subject-labels.md))
- `statements`: a list of Statements

*Avoid using these terms as synonyms: "object", "entity", "item"*

### Statement

Corresponds to one row in an infobox.

Statements have

- A `propertyName`. Refers to the Property Definition with the same name in the Subject's Schema.
- A `propertyType`: the type the referenced property had when the Statement was last changed — "the writer's schema".
- A `value` of type Value

Example: Property Name "age" with Value `42` and Property Type `number`.

NeoWiki Statements are not equivalent to Wikibase Statements. The latter have a rank, qualifiers, references, and an ID. For similar modeling, NeoWiki uses Subjects (multiple per page). See [Qualifiers and References](qualifiers-and-references.md) for how to model these.

### Value

Values have a type, for instance, `string`. This is called the **Value Type**. NeoWiki has a predefined list of
these Value Types; each Property Type stores its values as one of them — a `url` property's value is a StringValue.

String and Relation Values can hold multiple **parts**. For instance, a `url` property's value could be
`["https://pro.wiki", "https://professional.wiki"]`.

Value Types:

- StringValue, identified with `string`. A collection of strings
- NumberValue, identified with `number`. A single number
- BooleanValue, identified with `boolean`. A single boolean
- RelationValue, identified with `relation`. A collection of Relations
- UnregisteredTypeValue, identified with `unregisteredType`. Holds a Statement's value unchanged while its
  Property Type is not registered on the wiki

Each Relation has

- An `id`: persistent identifier. Relation IDs start with `r` and are always 15 characters long
- A `target`: Subject ID of the referenced Subject
- `properties`: possibly empty collection of property-value pairs



## Source

Where a Subject comes from ([ADR 23](adr/023-subject-sources.md)). A Source produces Subjects and resolves the Schemas
they use. The wiki itself is the default Source, and the one Subjects are created in; extensions register others, each
under a source key that prefixes the Subject IDs from it.

Subjects from another Source are read-only.

## Schema

A Schema ([ADR 6](adr/006-schemas.md)) defines a type of Subject. Examples: Person, Company, Product, etc.

Schemas have a name, description, and a list of Property Definitions

### Property Definition

A Property Definition has:

- A **name**. Example: "Website".
- A **type**: a Property Type. Example: "url".
- Boolean **required**
- Optional **description** string
- Optional **default**, which is a Value
- **Constraints**: validation and data rules specific to the Property Type. Example: `"minimum": 42`. Each carries a
  severity of `error` or `warning` (default `warning`) that decides whether violating it can block a write — see
  [Constraint severity](api/schema-format.md#constraint-severity). Not overridable in Layouts.
- **Display Attributes**: presentation configuration specific to the Property Type. Example: `"precision": 2`,
  `"color": "blue"`. These serve as defaults that can be overridden per-Layout via Display Rules.

### Property Type

The kind of data a Property Definition holds, and how it is edited and displayed. Examples: "text", "url",
"number", "relation". Extensions can define additional Property Types ([Extending NeoWiki](extending/extending.md)).
Each Property Type stores its values as one of the Value Types.

*Avoid: "Value Format", "format" — former names of this concept.*

### Violation

A Violation is a single validation finding: a value that does not satisfy one of its Property Definition's
Constraints ([ADR 26](adr/026-validation-severity-levels.md)). Validating a Subject against its Schema yields
Violations; the possible kinds are cataloged in [Validation codes](api/validation-codes.md).

Each Violation carries the **Severity** of the violated Constraint: `warning` Violations inform but never
block, while `error` Violations can block saving, depending on the wiki's validation **Enforcement** setting.



## View

A View is an on-page rendering of a Subject. Views are placed on wiki pages via the `{{#view}}` parser function or
automatically for a page's Main Subject. Each View renders a Subject using a View Type.

A View can optionally reference a Layout to customize which properties are shown and how. Without a Layout, all
properties are shown in Schema-defined order.

### View Type

The visual format used to render a View. Examples: "infobox", "card", "table". View Types can be defined by extensions.



## Layout

A Layout ([ADR 18](adr/018-views.md)) references a Schema and allows customized display of Subjects that use that
Schema. The link is one-directional: Layouts reference Schemas, Schemas do not reference their Layouts.

Example: A company Schema has many properties. You want to display only some of them in your "Finances" page section.
You create a finances Layout for that company Schema that shows only Revenue, Profit, and Assets.

Layouts have:

- A **name**: the title of the Layout's page, used to reference it ([ADR 17](adr/017-names-as-identifiers.md))
- A **Schema** reference
- A **View Type**
- **Display Rules**: an ordered list that specifies which properties to show and how (see below)
- **Settings**: Layout-level configuration specific to the View Type (e.g., `borderColor` for infobox)
- Optional **description**

*Avoid: "View" — the former name of this concept. A View is now the on-page rendering.*

### Display Rule

A Display Rule is an entry in a Layout's ordered allowlist: it references a property by name and optionally
overrides its Display Attributes; unspecified ones are inherited from the Property Definition. Unlisted
properties are hidden.



## Graph Store

A Graph Store is a database that NeoWiki projects wiki data into so it can be queried, such as Neo4j or SPARQL-capable
stores. A wiki can have several Graph Stores, each identified by name. Each holds one or more Projections.

*Avoid: "graph database", "graph backend", "triple store" (as names for this concept).*

## Projection

A Projection is a derived, query-optimized copy of the wiki's data in a Graph Store
([ADR 19](adr/019-graph-database-architecture.md)). Page content remains the source of truth; a Projection can
be rebuilt from it at any time. Every Projection is one of two kinds: the built-in **native projection**,
which uses NeoWiki's own vocabulary, or an **ontology projection**, defined by a Mapping. Export and query
surfaces select a Projection by name.

*Avoid: "base mapping", "target ontology" — former names for the native and ontology projections.*

## Mapping

A Mapping defines how Subjects that follow native Schemas are expressed in an established ontology such as EDM
or CIDOC-CRM ([Ontology Mapping](rdf/ontology-mapping.md)). Each Mapping is a page in the `Mapping:` namespace
and defines one ontology projection; the Mapping page's title is the projection name.



## Page Property

A key-value pair stored on the Page node in graph Projections. Page Properties are metadata about the wiki page
itself, as opposed to Subject Statements, which are structured data about the entities described on the page.

Built-in Page Properties include `name`, `namespaceId`, `creationTime`, `lastUpdated`, `categories`, and `lastEditor`.
Extensions can contribute additional Page Properties (see [Extending NeoWiki](extending/extending.md)).



## Flagged ambiguities

These terms are current but contested; expect them to change:

- **View Type** may be renamed to "Layout Type"
  ([#925](https://github.com/ProfessionalWiki/NeoWiki/issues/925)).
- `RelationType` names two different things in the code: the graph edge label and the `relation` Property Type
  ([#630](https://github.com/ProfessionalWiki/NeoWiki/issues/630)).
- "Allow multiple values", the `multiple` schema field, and multi-part Values name one concept three ways
  ([#712](https://github.com/ProfessionalWiki/NeoWiki/issues/712)).
- Subjects have a `label` while Schemas, Layouts, and Mappings have a `name`, and a Subject without a label is shown
  under a computed `displayName` ([#1283](https://github.com/ProfessionalWiki/NeoWiki/issues/1283)).
