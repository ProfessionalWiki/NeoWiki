# ECHOLOT

If you are not familiar with the NeoWiki terminology yet, see [the glossary](../glossary.md).

## Statement-Level Requirements

The grant phrases several requirements in Wikibase vocabulary: "qualification of data statements" (T3.1),
"statement-level access", and "Wikibase data model support" (both T3.2). In Wikibase, qualification, references, and
rank are machinery on the Statement itself. NeoWiki Statements are deliberately flat, and the model already
expresses the same information: the value is reified into a linked Subject typed by its own Schema, or, when the link
between two Subjects is what needs qualifying, the Relation carries the properties. See
[Qualifiers and References](../qualifiers-and-references.md), which maps each Wikibase construct to its NeoWiki
expression, including in RDF; that mapping is also the translation a Wikibase-model import (T3.2) will apply. No
statement machinery is needed. Statement-level read access is the Subject payload itself, and Relations are
individually addressable by their stable IDs. What remains open is not the model: inline editing and display of
reified values (the multi-Subject editor question below) and write granularity (the statement-level writes question
below).

## Open Questions

### High Priority

* What will the interaction of the wiki and WP4 look like? WP4 is about import, export, reconciliation,
  image recognition and annotation, enrichment, and quality checks. Which UIs will live in the wiki?
  Which services will live in the wiki backend vs which ones will be microservices?
* What other things do Schemas need to support? Things like Subclasses. See [Glossary](../glossary.md) and
  [SchemaFormat](../api/schema-format.md) for what is already supported. (80% likely)

### Medium Priority

* Provenance scope and ownership. The grant and the D2.1 spec call for fine-grained chain-of-production provenance
  and rights metadata. Intended boundary: NeoWiki core provides the *foundation* — MediaWiki's per-revision
  authorship/versioning of statements, extension points, and per-page named graphs (data origin) — while the
  provenance/rights *model* (T2.4) and a dedicated provenance/rights plug-in (T3.4) provide the fine-grained
  capture on top, rather than it being built into the core data model. Open: verify the data model and named-graph
  design can carry what the plug-in needs, as distinct from operational per-page named graphs (see
  [NativeRdfProjection.md](NativeRdfProjection.md) Q5).
* Rights Statement Selector: what does a rights-entry UI need from NeoWiki? ECHOLOT calls for an easy way to pick the
  correct rights statement for an item or dataset using existing copyright frameworks (Europeana Licensing Framework,
  Creative Commons, RightsStatements.org), ideally also usable as a plug-in by other systems. This likely means a new
  Property Definition type, or a specialized UI component for a property, offering a curated selection interface for
  rights/license values and storing them as structured data with URIs pointing at the canonical definitions. Relates
  to the provenance/rights boundary above (T3.4).
* Does the [native RDF projection strawman proposal](NativeRdfProjection.md) go in the right direction? What needs to
  be adjusted? Same question for the [ontology mapping strawman](OntologyMapping.md).
* Is our [Graph Model](../api/graph-model.md) OK? In particular, is it OK to have non-Subject data in there, like the connected
  MediaWiki pages? (80% likely, briefly covered in Vienna: can filter out these values when querying)
* How important is multilinguality for ECHOLOT? Do we need to provide anything beyond our current data model to support that?
* Statement-level writes: the Subject write API replaces the whole Subject with no base-revision check, so concurrent
  writers lose each other's updates (last write wins). Do WP4 enrichment flows need a partial-update operation or
  defined conflict semantics? (Becomes concrete once a reconciliation service writes alongside human editors.)

### Low Priority

* Is [one Schema per Subject](../adr/008-one-schema-per-subject.md) viable?
  (likely, but let's verify)
* Do we need to have an API that provides Schemas in JSON Schema format? (50% likely, can be deferred, easy to implement)
* ID-generation for bulk import: do we need an API for (bulk) ID gen? (local impact, easy to implement)
* Is multi-Subject support in the editor essential?
  Example: Person has a "Name" property. Name is a Subject with its own PersonName schema. The "Edit Person" form would show the
  PersonName fields and create or update both the Person Subject and linked PersonName Subject.
  Display raises the same question: showing a linked Subject's values on the linking Subject's page currently requires Lua.
