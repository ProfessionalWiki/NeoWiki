# Monolingual Text Value Type

Date: 2026-09-19

Status: Accepted

## Context

A `text` value is a list of plain strings, so a property holding a film's original title in Basque and in Spanish
cannot say which is which; no [value shape](../api/subject-format.md#value-formats) carries a language. A Mapping can
tag a property with [`lang`](../authoring/mapping-format.md#format-version-1), but that is one tag for every value of
that property.

## Decision

`monolingualText` is a Property Type, and a Value Type of its own beside `string`, `number`, `boolean` and
`relation`. Its value is a list of `{ "text", "language" }` objects.

**The language is a field, not a convention inside the text.** Semantic MediaWiki writes `Text@en` in the stored
string. That needs no new value shape, but every consumer then parses the tag back out, and a text that itself ends
in `@en` cannot be told from a tagged one.

**A value, not a Subject.** Text-plus-language could be a Subject linked by a relation, but it has no identity of
its own, and would project as a node where a consumer of an ontology expects a literal.

**A type, not an option on `text`.** One property would otherwise hold either shape, and what tells a reader which
it has — the writer's schema on the Statement ([ADR 11](011-include-writers-schema.md)) — records a type.

**A tag is accepted on its shape**: hyphen-separated subtags of one to eight characters, the primary one alphabetic.
It is not checked against MediaWiki's language list, so a vocabulary using `und` or a script subtag is not locked
out. Tags are stored lowercase, making `pt-BR` and `pt-br` one language.

**A language may repeat**: a thing can have two names in Spanish. `uniqueItems` therefore compares text and
language together.

**Constraints apply per part.** `minLength` and `maxLength` bound each part's text, and `required` is satisfied by
any one part. A part whose text is empty is dropped, so no part carries a language and nothing else.

**A projection has to keep each part's text and language separately recoverable.** RDF does so natively: one
language-tagged literal per part (`rdf:langString`). Neo4j has neither tagged strings nor maps on properties, so a
part becomes `Zinema@eu`, split on the last `@`. The in-string tag rejected above is acceptable here: a projection is
rebuilt from the stored parts and never edited, so reading the tag back out is a query's job rather than a writer's.

**A Mapping's `lang` and `datatype` do not reach such a value.** The tag is data and differs per part, so a
property-wide override could only make it less specific. `lang` keeps its job on a `text` property whose values are
all in one language.

## Consequences

A new value shape reaches every contract that enumerates them: the [Subject JSON format](../api/subject-format.md),
the [graph model](../api/graph-model.md), [RDF export](../api/rdf-export.md) and the [Lua API](../authoring/lua-api.md).

Nothing converts values written while the property was `text`.

A View shows the parts in the reader's interface language, falling back along MediaWiki's language fallback chain and
then to the content language, with the other parts one click away; the scalar accessors give the text without its tag.
