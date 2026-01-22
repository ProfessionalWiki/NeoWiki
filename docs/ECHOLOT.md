# ECHOLOT

## Open Questions

### High Priority

* Interaction with and architecture of WP4: import, export, reconciliation, image recognition and annotation, enrichment,
  and quality checks. Which UIs will live in the wiki? Which services will live in the wiki backend vs which ones will
  be microservices?
* What other things do Schemas need to support? Things like Subclasses. (80% likely)

### Medium Priority

* Are we sure we should switch to Global Properties, replacing the current "[Local Properties](adr/006_Schemas.md)"
  approach? (90% likely)
* [Validation](Validation.md): do we need to add backend validation?
  (somewhat likely, but can be deferred)
* Verify the current data model (Property-graph-like Subjects and multi-Subject support) is workable for provenance.
* Is multi-Subject support in the editor essential?
  Example: Person has a "Name" property. Name is a Subject with its own PersonName schema. The "Edit Person" form would show the
  PersonName fields and create or update both the Person Subject and linked PersonName Subject.

### Low Priority

* Is [one Schema per Subject](adr/008_One_Schema_per_Subject.md) viable?
  (likely, but let's verify)
* Do we need to have an API that provides Schemas in JSON Schema format? (50% likely, can be deferred, easy to implement)
