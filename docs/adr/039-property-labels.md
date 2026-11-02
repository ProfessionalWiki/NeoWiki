# Property Labels

Date: 2026-11-02

Status: Draft. Amends [ADR 17](017-names-as-identifiers.md): the UIs show new property labels that are editable
so property names can remain stable.

## Context

A Property name is both what users see in the UIs and the identifier that everything else uses to refer to the Property Definition
([ADR 17](017-names-as-identifiers.md)).

Take a Person Schema with a Property Definition "Birth date". Renaming it to "Date of birth" leaves every Person's date
stored under "Birth date". The Schema no longer has that name, so the dates stop showing until the name changes back,
and every Layout, Mapping and template that names the Property has to change with it. ADR 17 accepted this for the
first version.

Wikis in several languages need more: a German reader should see "Geburtsdatum", while queries and templates keep
using one name.

## Decision

A Property's name identifies its Property Definition and does not change once its Schema is saved with it, as a
Schema's name does not.

What people see is the Property's label. A Property Definition can have one label per language, keyed by language tag
as [Text with language](034-monolingual-text-value-type.md) values are:

```json
"Birth date": {
	"type": "date",
	"labels": {
		"en": "Date of birth",
		"de": "Geburtsdatum"
	}
}
```

NeoWiki shows a Property under its label in the reader's interface language, falling back along MediaWiki's language
fallback chain, then to the label in the content language, and then to the name.

Labels are for people only. Statements, Layouts, Mappings, wikitext, Lua, queries and API requests refer to a
Property by its name, and Neo4j keys and RDF predicates are made from it.

## Consequences

- Renaming a Property in the Schema editor changes its label, not its name. Nothing that refers to the Property
  breaks, and Subject JSON keeps descriptive keys.
- Labels are optional, so a Schema without them works as before.
- A name keeps a typo or an outdated term for good. Readers see the label, but people writing wikitext, Lua, Layouts,
  Mappings or queries use the name, so the Schema page shows both.
- Changing a name means removing the Property and adding another. Values stored under the old name stay, unshown, as
  for any removed Property.

## Out of Scope

Labels for Schemas and for select options, descriptions per language, Layouts that override a label, and a tool that
moves values from one Property to another.

## Alternatives Considered

### Generated identifiers

Give each Property a generated id that Statements and references use, and turn its name into a label, as Wikibase does
with ids such as P569. ADR 17 expected this step.

Not chosen because references become opaque: Subject JSON, API requests, Layouts, Mappings, wikitext, Lua, Cypher and
SPARQL would carry ids that people have to look up. Every stored Subject, Schema, Layout and Mapping would also need
rewriting. Labels already let what people see change, which is what the ids were for.

### Ids next to names

Keep names editable and as the keys of stored Statements, and also store a generated id with each Property and each
Statement, so that a Statement still finds its Property after a rename.

Not chosen because each Statement then has two keys that can disagree, and everything that reads Statements has to
reconcile them. After a rename, a client that writes by name adds a second Statement for the Property beside the one
the id finds, and the readable key in Subject JSON is a name the Schema no longer has. References by name, in
Layouts, Mappings, wikitext, Lua and the graph, still break.

### Rewriting stored data on a rename

Keep names editable, and on a rename rewrite every Subject, Layout and Mapping that uses the old name.

Not chosen because it adds a revision to every page holding a Subject of the Schema, can take long and fail halfway on
a large wiki, and cannot reach wikitext, Lua, saved queries or Subjects from other Sources.

### Old names as aliases

Keep names editable, and keep each Property's earlier names so that values and references under them still count, as
Semantic MediaWiki does with redirects between property pages.

Not chosen because everything that reads names would have to resolve aliases, and Neo4j keys and RDF predicates would
still change with each rename.

### Labels as MediaWiki messages

Take labels from system messages such as `neowiki-property-label-Person.Birth_date`, which a wiki translates like any
interface text.

Not chosen because the labels would live outside the Schema: they would not travel with it through export, import and
Sources, and editing one would take the right to edit the interface rather than the Schema.

### Names that can change until used

Let a name change until some Subject has a value for the Property.

Not chosen because Layouts, Mappings and wikitext can refer to a Property before any Subject does.
