# RDF IRIs

Date: 2026-10-06

Status: Draft

## Context

NeoWiki projects its data into RDF, natively and through [Mappings](../authoring/mapping-format.md). Partners build
documentation, mappings and queries on the IRIs it mints, so changing one breaks their work. Property Definitions are
local to their Schema ([ADR 6](006-schemas.md)): "Name" in Person and "Name" in City may differ in type.

## Decision

1. Every IRI a wiki mints for its own data lives under `$wgNeoWikiRdfBaseUri`, which defaults to `$wgCanonicalServer`.
   Wikis share no vocabulary IRIs, since each wiki edits its own Schemas. A Subject from another Source keeps that
   Source's IRI ([ADR 23](023-subject-sources.md)).
2. Each kind of resource has its own path segment, so IRIs of different kinds cannot collide. The
   [RDF Export reference](../api/rdf-export.md) and the [Mapping Format](../authoring/mapping-format.md) list them.
3. Schema, property and Mapping names are the local parts of their IRIs, not opaque IDs like Wikibase's `P123`, since
   names are identifiers ([ADR 17](017-names-as-identifiers.md)). Spaces become underscores. `/`, `#`, `?` and
   characters illegal in an IRI are percent-encoded; all other characters, non-ASCII included, stay as they are.
   Subjects and Relations use their IDs, pages their page IDs.
4. A property's predicate is `$base/prop/{Schema}/{Property}`. A predicate shared by name would equate independent
   definitions, and RDFS would intersect their domains and ranges. `$base/prop/{Name}` is reserved for properties
   shared across Schemas, should they be added, linked to the per-Schema predicates with `rdfs:subPropertyOf`.
5. A Subject has the same IRI in every projection, so projections in one store join without `owl:sameAs`.

## Consequences

- Subject, Relation and page IRIs survive renames. Renaming a property changes its predicate, and renaming or moving a
  Schema changes its class and all its predicates; the old IRIs are not kept.
- Changing the base URI, or the wiki's host when the base is the default, changes every IRI. Wikis on one host each
  need their own `$wgNeoWikiRdfBaseUri`, or their IRIs collide.
- `Has author` and `Has_author` share an IRI.
- Subject IRIs dereference; class and property IRIs do not yet.
- Each predicate in the RDFS export has one domain and one range. The range of a relation property that targets
  several Schemas ([ADR 28](028-relations-model.md)) is open in
  [#1163](https://github.com/ProfessionalWiki/NeoWiki/issues/1163), the class of a Schema from another Source in
  [#1371](https://github.com/ProfessionalWiki/NeoWiki/issues/1371).
- A query across Schemas names each Schema's predicate.
- Flat predicates, `$base/prop/{Property}`, become per Schema once, before the first release
  ([#1163](https://github.com/ProfessionalWiki/NeoWiki/issues/1163),
  [#1553](https://github.com/ProfessionalWiki/NeoWiki/issues/1553)).
