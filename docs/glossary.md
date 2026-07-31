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
- A `type`: reference to a Schema. Example: Person, Company, Product, etc.
- A `label`: the name of the subject. Example: "John Doe". This is a string, not a reference to a page.
- `statements`: a list of Statements

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

Each Relation has

- An `id`: persistent identifier. Relation IDs start with `r` and are always 15 characters long
- A `target`: Subject ID of the referenced Subject
- `properties`: possibly empty collection of property-value pairs



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

- A **Schema** reference
- A **View Type**
- **Display Rules**: an ordered list that specifies which properties to show and how (see below)
- **Settings**: Layout-level configuration specific to the View Type (e.g., `borderColor` for infobox)
- Optional **description**

### Display Rule

A Display Rule is an entry in a Layout's ordered allowlist: it references a property by name and optionally
overrides its Display Attributes; unspecified ones are inherited from the Property Definition. Unlisted
properties are hidden.



## Page Property

A key-value pair stored on the Page node in the graph database. Page Properties are metadata about the wiki page
itself, as opposed to Subject Statements, which are structured data about the entities described on the page.

Built-in Page Properties include `name`, `namespaceId`, `creationTime`, `lastUpdated`, `categories`, and `lastEditor`.
Extensions can contribute additional Page Properties (see [Extending NeoWiki](extending/extending.md)).
