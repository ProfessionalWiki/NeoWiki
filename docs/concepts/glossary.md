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
* An **id**: persistent numeric auto-increment ID, sometimes useful for programmatic interaction, like via the API.
* **Content**: wikitext, editable both via source and visual editors
* **Subjects**: list of Subjects, can be empty ([ADR 7](../adr/007-multiple-subjects-per-page.md))
* **Main Subject**: optional identifier of a Subject in the page's Subjects list. Indicates which Subject represents the same entity as the page itself. All other Subjects stored on a page are called **Child Subjects**.

## Subject

Data about one thing. Similar to an Item in Wikibase or a Page/SubObject in SMW.

Subjects have

- An `id`: persistent identifier. Subject IDs start with `s` and are always 15 characters long ([ADR 14](../adr/014-improved-id-format.md))
- A `type`: reference to a Schema. Example: Person, Company, Product, etc.
- A `label`: the name of the subject. Example: "John Doe". This is a string, not a reference to a page.
- `statements`: a list of Statements

### Statement

Corresponds to one row in an infobox.

Statements have

- A `propertyName`. Refers to the Property Definition with the same name.
- A `propertyType`. This is the type of the referenced property at the time the Statement was last changed. This is called "the writer's schema". ("Property Type" was formerly "Value Format")
- A `value` of type Value

Example: Property Name "age" with Value `42` and Property Type `number`.

NeoWiki Statements are not equivalent to Wikibase Statements. The latter has a rank, qualifiers,  references, and an ID. For similar modeling, NeoWiki uses Subjects (multiple per page).

### Value

Values have a type, for instance, "url". This is called the **Value Type**. NeoWiki has a predefined list of these Value Types.

Values can have multiple **parts**. For instance, a "url" value could be `["https://pro.wiki", "https://professional.wiki"]`.

Value Types:

- StringValue, identified with `string`. A non-empty collection of strings
- NumberValue, identified with `number`
- BooleanValue, identified with `boolean`
- RelationValue, identified with `relation`. A non-empty collection of Relation

Each Relation has

- An `id`: persistent identifier. Relation IDs start with `r` and are always 15 characters long
- A `target`: Subject ID of the referenced Subject
- `properties`: Possibly empty collection of property-value pairs. TODO: rename like we did with statements



## Schema

A Schema ([ADR 6](../adr/006-schemas.md)) defines a type of Subject. Examples: Person, Company, Product, etc.

Schemas have a name, description, and a list of Property Definitions

### Property Definition

They always have a Property Name and a Property Type. Depending on the Type, they might have additional information.
Property Types are registered via a plugin system and can be defined by extensions (see
[Extending NeoWiki](../reference/extending.md)).

- A **name**. Example: "Website".
- A **type**. Example: "url". (formerly "format")
- Boolean **required**
- Optional **description** string
- Optional **default**, which is a Value
- **Constraints**: validation and data rules specific to the Property Type. Example: `"minimum": 42`. Not overridable
  in Layouts.
- **Display Attributes**: presentation configuration specific to the Property Type. Example: `"precision": 2`,
  `"color": "blue"`. These serve as defaults that can be overridden per-Layout via Display Rules.



## View

A View is an on-page rendering of a Subject. Views are placed on wiki pages via the `{{#view}}` parser function or
automatically for a page's Main Subject. Each View renders a Subject using a **View Type** (e.g., infobox, card,
table).

A View can optionally reference a Layout to customize which properties are shown and how. Without a Layout, all
properties are shown in Schema-defined order.

### View Type

The visual format used to render a View. Examples: "infobox", "card", "table". View Types are designed to be
registered via a plugin system so extensions can define new View Types, each plugin defining how to render a Subject
given a configuration. This plugin system is not yet available to extensions: only the built-in `infobox` View Type
exists (see [Extending NeoWiki](../reference/extending.md#not-yet-extensible)).



## Layout

A Layout ([ADR 18](../adr/018-views.md)) references a Schema and allows customized display of Subjects that use that
Schema. The link is one-directional: Layouts reference Schemas, Schemas do not reference their Layouts.

Example: A company Schema has many properties. You want to display only some of them in your "Finances" page section.
You create a finances Layout for that company Schema that shows only Revenue, Profit, and Assets.

Layouts have:

- A **Schema** reference
- A **View Type**, such as "infobox", "factbox", or "table"
- **Display Rules**: an ordered list that specifies which properties to show and how (see below)
- **Settings**: Layout-level configuration specific to the View Type (e.g., `borderColor` for infobox)
- Optional **description**

When a Subject is displayed without a specified Layout, all properties are shown in Schema-defined order (fallback
behavior). There is no stored "Default Layout" entity.

### Display Rule

A Display Rule is an entry in a Layout's ordered allowlist. Each Display Rule references a property by name and
optionally overrides Display Attributes for that property. Unlisted properties are hidden.

Display Rules have:

- A **property** reference (the property name from the Schema's Property Definitions)
- Optional **Display Attributes** overrides (e.g., `precision`, `color`). These override the defaults from the
  Property Definition. Unspecified Display Attributes are inherited from the Property Definition.



## Page Property

A key-value pair stored on the Page node in the graph database. Page Properties are metadata about the wiki page
itself, as opposed to Subject Statements, which are structured data about the entities described on the page.

Built-in Page Properties include `name`, `creationTime`, `lastUpdated`, `categories`, and `lastEditor`. Extensions can
contribute additional Page Properties via the Page Property Provider plugin system (see
[Extending NeoWiki](../reference/extending.md)).

When using Neo4j, Page Properties are available on every Page node and are queryable via Cypher
(e.g., `MATCH (page:Page) WHERE page.lastUpdated > datetime("2024-01-01")`).
