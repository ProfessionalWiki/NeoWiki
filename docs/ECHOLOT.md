# ECHOLOT

## Open Questions

### High Priority

* Interaction with and architecture of WP4: import, export, reconciliation, image recognition and annotation, enrichment,
  and quality checks. Which UIs will live in the wiki? Which services will live in the wiki backend vs which ones will
  be microservices?
* What other things do Schemas need to support? Things like Subclasses. (80% likely)

### Medium Priority

* Are we sure we should switch to Global Properties? (90% likely)
* [Validation](Validation.md): do we need to add backend validation?
  (somewhat likely, but can be deferred)

### Low Priority

* Is [one Schema per Subject](adr/008_One_Schema_per_Subject.md) viable?
  (likely, but let's verify)
* Do we need to have an API that provides Schemas in JSON Schema format? (50% likely, can be deferred, easy to implement)
