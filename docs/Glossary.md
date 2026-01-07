# Glossary

NeoWiki terms definitions. Concepts are capitalized. Used in the code and UI (Ubiquitous Language).

## Subject

Data about one thing. Similar to an Item in Wikibase or a Page/SubObject in SMW.

Subjects have

- An `id`: persistent identifier. Subject IDs start with `s` and is always 15 characters long
- A `type`: reference to a Schema. Example: Person, Company, Product, etc.
- A `label`: the name of the subject. Example: "John Doe". This is a string, not a reference to a page.
- `statements`: a list of Statements

Pages can have multiple Subjects. They can only have a single **Main Subject**. This Subject represents the same entity as the page itself. All other Subjects stored on a page are called **Child Subjects**.

TODO: The label of a main subject is the same as the page title.

### Statement

Corresponds to one row in an infobox.

Statements have

- A `propertyName`. Refers to the Property Definition with the same name.
- A `propertyType`. This is the type of the referenced property at the time the Statement was last changed. This is called “the writer’s schema”. (”Property Type” was formerly “Value Format”)
- A `value` of type Value

Example: Property Name "age" with Value `42` and Property Type `number`.

### Value

Values have a type, for instance, "url". This is called the **Value Type**. NeoWiki has a predefined list of these Value Types.

Values can have multiple **parts**. For instance, a "url" value could be `["https://pro.wiki", "https://professional.wiki"]`.

Value Types:

- StringValue, identified with `string`. A non-empty collection of strings
- NumberValue, identified with `number`
- BooleanValue, identified with `boolean`
- RelationValue, identified with `relation`. A non-empty collection of Relation

Each Relation has

- An `id`: persistent identifier. Relation IDs start with `r` and is always 15 characters long
- A `target`: Subject ID of the referenced Subject
- `properties`: Possibly empty collection of property-value pairs. TODO: rename like we did with statements

###

## Schema

Defines a type of Subject. Examples: Person, Company, Product, etc.

Schemas have a name, description, and a list of Property Definitions

### Property Definition

They always have a Property Name and a Property Type. Depending on the Type, they might have additional information, such as constraints or display info. These Type-specific things are Property Attributes. Property Types are registered via a plugin system and can be defined by extensions.

- A **name**. Example: "Website".
- A **type**. Example: "url". (formerly “format”)
- Boolean **required**
- Optional **description** string
- Optional **default**, which is a Value
- **Attributes**
  - Possibly: additional constraints. Example: `"minimum": 42`
  - Possibly: additional display information. Example: `"color": "blue"`



## View

Views are introduced via [ADR 18](../adr/018_Views.md)

A View is linked to a Schema, and allows customized display of Subjects that use that Schema.

Example: A company Schema has many properties. You want to display only some of them in your “Finances” page section. Thus, you create a finances View for that company Schema that hides all properties except for Revenue, Profit, and Assets.

Views have a View Type, such as "infobox", "factbox", or "table". The View Type affects what View Attributes can be set.

View Attributes:

- Which Statements to show (specified via Property names)
- Ordering of Statements (specified via Property names)
- Statement-level display information like precision and color. This depends on the Property Type of the Statement.
- Display information specific to the View Type, like the border color of an Infobox

**Not to be confused with** the yet to be clearly named concept of “display container” such as infobox or table.  To make things extra confusing, Notion uses the term View for “display container”:
