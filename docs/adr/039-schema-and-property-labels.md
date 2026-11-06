# Schema and Property Labels

Date: 2026-11-02

Status: Accepted

Amends [ADR 17](017-names-as-identifiers.md): the UI shows new Schema and Property labels that are editable so
Schema and Property names can remain stable.

## Context

A Schema name and a Property name are both what users see in the UI and the identifiers that everything else uses to
refer to the Schema or Property Definition ([ADR 17](017-names-as-identifiers.md)). A Schema's name is the title of its
page, and is not editable after the Schema is created.

Take a Company Schema with a Property Definition "Founding date". Renaming it to "Date founded" leaves every Company's
date stored under "Founding date". The Schema no longer has that name, so the dates stop showing until the name changes
back, and every Layout, Mapping and template that names the Property has to change with it. ADR 17 accepted this for
the first version.

For multilingual wikis, we also want the UI to show Schemas and Properties in the user's language: a German reader
should see "Unternehmen" and "Gründungsdatum".

## Decision

You continue to identify and reference Schemas and Property Definitions by their name. We change what people see in
the UI to a multilingual label, allowing names to stay stable when UI display needs change.

A Schema and a Property Definition can now each have one label per language, keyed by language tag
like [Text with language](034-monolingual-text-value-type.md) values are:

```json
{
	"labels": {
		"de": "Unternehmen"
	},
	"propertyDefinitions": {
		"Founding date": {
			"type": "date",
			"labels": {
				"en": "Date founded",
				"de": "Gründungsdatum"
			}
		}
	}
}
```

The UI shows a Schema or Property using its label in the reader's interface language, falling back along MediaWiki's
language fallback chain, then to the label in the content language, and then to the name.

Labels are for display only. Subjects, Statements, Layouts, Mappings, wikitext, Lua, queries and API requests refer to
a Schema or Property by its name, and Neo4j labels and keys and RDF classes and predicates continue to be based on the
name.

Property definitions can still be renamed via the API, as we cannot differentiate renames from removing one property
definition and adding another. The existing behavior is retained: values under the old name stop showing in the UIs but
are not removed.

## Consequences

- Changing a label breaks nothing that refers to the Schema or Property.
- We keep descriptive keys in Subject JSON and in references to Schemas and Property Definitions, such as in wikitext,
  Lua and Cypher.
- The multilingual use case is supported: users see Schemas and Properties in their language when a translation is
  available.
- The name people write in wikitext, Lua, Layouts, Mappings and queries can differ from the label they see, such as
  "Founding date" shown as "Date founded".

## Implementation Thoughts

- The Schema editor could let users rename a Property only while no Subject uses the Schema.
- The Schema page likely should show each Property's name beside its label, for people writing references.

## Out of Scope

Labels for Layouts and for select options, descriptions per language, Layouts that override a label, and a tool that
moves values from one Property to another.

## Alternatives Considered

### Generated identifiers

Give each Schema and Property a generated id that Subjects, Statements and references use, and turn its name into a
label, as Wikibase does with ids such as Q5 and P569. We expected this approach when we wrote ADR 17.

Not chosen because references become opaque: Subject JSON, API requests, Layouts, Mappings, wikitext, Lua, Cypher and
SPARQL would carry ids that people have to look up. Every stored Subject, Schema, Layout and Mapping would also need
rewriting. We'd also need to generate the IDs. Our chosen approach avoids these downsides.

### Ids next to names

Keep Property names editable and as the keys of stored Statements, and also store a generated id with each Property
and each Statement, so that a Statement still finds its Property after a rename.

Not chosen because each Statement then has two keys that can disagree, and everything that reads Statements has to
reconcile them. After a rename, a client that writes by name adds a second Statement for the Property beside the one
the id finds, and the readable key in Subject JSON is a name the Schema no longer has. References by name, in
Layouts, Mappings, wikitext, Lua and the graph, still break.

### Rewriting stored data on a rename

Keep Property names editable, and on a rename rewrite every Subject, Layout and Mapping that uses the old name.

Not chosen because it adds a revision to every page holding a Subject of the Schema, can take long and fail halfway on
a large wiki, and cannot reach wikitext, Lua, saved queries or Subjects from other Sources. Old revisions would
also stop showing their data when viewing them via the history page.

### Old names as aliases

Keep Property names editable, and keep each Property's earlier names so that values and references under them still
count, as Semantic MediaWiki does with redirects between property pages.

Not chosen because everything that reads names would have to resolve aliases, and Neo4j keys and RDF predicates would
still change with each rename.

### Labels as MediaWiki messages

Take labels from system messages such as `neowiki-schema-label-Company` and
`neowiki-property-label-Company.Founding_date`, which a wiki translates like any interface text.

Not chosen because the labels would live outside the Schema: they would not travel with it through export, import and
Sources, and editing one would take the right to edit the interface rather than the Schema.
