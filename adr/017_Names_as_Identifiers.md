## Names as Identifiers

Date: 2025-05-02

Status: Accepted

## Context

Schemas have a name, and so do Property Definitions.

While working on our new UI flows, we started wondering if these names should continue to have the dual function
of being both a label in the UI and an identifier used to reference the Schema or Property Definition. We also
questioned if these names should be editable by the user after creation of the Schema or Property Definition.

## Decision

We keep using Schema and Property names as both labels and identifiers.

Schema names are not editable by users after creating the Schema, while Property Definition names remain editable.

This decision is made with the understanding that we can make Schema names editable and introduce dedicated internal
identifiers decoupled from the name used as UI label when NeoWiki reaches greater maturity. See "Alternatives Considered"
for more details.

We treat the Page name of a Schema as the name of a Schema, to keep lookups simple.

## Consequences

* When implementing SchemaEditor, we do not need to make the Schema name editable.
* Users will not be able to edit Schema names via the SchemaEditor UI in the MVP.
* If the user changes Property Names, references to those properties will break. This is on-par with behavior in SMW.
* We do not need to implement dedicated identifiers for the MVP.

## Alternatives Considered

Also see the [Mattermost thread](https://chat.professional.wiki/pro-wiki/pl/enb9b3i4mbfmfr5gi6usmw5fda)

### Dedicated Identifiers

With this approach, the name becomes purely presentational, and a new dedicated internal identifier is used to reference
the Schema or Property Definition is introduced.

This approach is similar to what Wikibase does for its properties (i.e. P123 identifier and "Website" as label). Like
in Wikibase, we probably would want to automatically generate those identifiers.

Pros:
* The presentational part can be renamed (i.e. "Website" to "Official Website") without breaking references

Cons:
* References become opaque strings like "P123" instead of "Website"
* Additional implementation and maintenance work to generate and store the unique internal identifier
