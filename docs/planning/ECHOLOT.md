# ECHOLOT

If you are not familiar with the NeoWiki terminology yet, see [the glossary](../Glossary.md).

## Open Questions

### High Priority

* What will the interaction of the wiki and WP4 look like? WP4 is about import, export, reconciliation,
  image recognition and annotation, enrichment, and quality checks. Which UIs will live in the wiki?
  Which services will live in the wiki backend vs which ones will be microservices?
* What other things do Schemas need to support? Things like Subclasses. See [Glossary](../Glossary.md) and
  [SchemaFormat](../SchemaFormat.md) for what is already supported. (80% likely)

### Medium Priority

* Are we sure we should switch to "[Global Properties](GlobalProperties.md)", replacing the current
  "[Local Properties](../adr/006_Schemas.md)" approach?
* [Validation](Validation.md): do we need to add backend validation?
  (80% likely, but can be deferred)
* Verify the current data model (Property-graph-like Subjects and multi-Subject support) is workable for provenance.
* Is multi-Subject support in the editor essential?
  Example: Person has a "Name" property. Name is a Subject with its own PersonName schema. The "Edit Person" form would show the
  PersonName fields and create or update both the Person Subject and linked PersonName Subject.
* Is our [Graph Model](../GraphModel.md) OK? In particular, is it OK to have non-Subject data in there, like the connected
  MediaWiki pages? (80% likely, briefly covered in Vienna: can filter out these values when querying)
* How important is multilinguality for ECHOLOT? Do we need to provide anything beyond our current data model to support that?

### Low Priority

* Is [one Schema per Subject](../adr/008_One_Schema_per_Subject.md) viable?
  (likely, but let's verify)
* Do we need to have an API that provides Schemas in JSON Schema format? (50% likely, can be deferred, easy to implement)
* ID-generation for bulk import: do we need an API for (bulk) ID gen? (local impact, easy to implement)
