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

They always have a Property Name and a Property Type. Depending on the Type, they might have additional information such as constraints or display info. These Type specific things are Property Attributes. Property Types are registered via a plugin system and can be defined by extensions.

- A **name**. Example: "Website".
- A **type**. Example: "url". (formerly “format”)
- Boolean **required**
- Optional **description** string
- Optional **default**, which is a Value
- **Attributes**
  - Possibly: additional constraints. Example: `"minimum": 42`
  - Possibly: additional display information. Example: `"color": "blue"`



## View

Work in progress concept similar to Views in Coda.

A View is linked to a Schema, and allows customized display of Subjects that use that Schema.

Example: A company Schema has many properties. You want display only some of them in your “Finances” page section. Thus you create a finances View for that company Schema that hides all properties except for Revenue, Profit, and Assets.

Views allow you to specify:

- **View Type** i.e. infobox, factbox, table
- Which statements to show, by specifying the Property names
- Ordering of statements based on Property names (null by default, resolving to Schema order)
- Likely: display formatting information like precision and color
- Possibly: display information that affects the “display container”
- Possibly in the far future: things like conditional highlighting

Coda:



Note that the Property Attributes equivalent in Coda contains display formatting information and that it is shared between all views.



Should we do the same and keep this information in the property definitions (i.e. number precision is always shown the same, and a sliders color is the same in all views), or do we place this information in the View? It probably makes sense to have a Default View for a Schema. If we place the display formatting information in the View, then it might be best to have all Views for a Schema inherit from the Default View, so that if you have 10 views for your company Schema, all changing colors for whatever reason, and you want to change the precision, you do not have to go edit all Views.

**Not to be confused with** the yet to be clearly named concept of “display container” such as infobox or table.  To make things extra confusing, Notion uses the term View for “display container”:
