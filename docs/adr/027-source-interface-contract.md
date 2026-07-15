# Source Interface Contract

Date: 2026-07-15

Status: Draft (frozen when the T2 PR merges)

## Context

ADR 23 decided that Subjects come from pluggable Sources and left the Source interface contract as
an open question. Issue #993's T2 work order assigns freezing it to the T2 PR. The contract must
serve the wiki-farm case (more registered Sources), the future on-wiki SMW/Wikibase and federation
adapters, and T3's schema references, without a per-Source capability matrix (ADR 23 Won'ts).

## Decision

A single `Source` interface (Domain\Source) with exactly five members:

- `getSubject( SubjectId $id ): ?Subject` — by-id resolution; the registry routes, the Source
  interprets its own localIds.
- `getSchema( SchemaName $schemaName ): ?Schema` — schemas come from Sources; a schema's source is
  independent of a subject's source.
- `isEditable(): bool` — the entire capability surface: local editable + versioned, sourced
  read-only.
- `isValidLocalId( string $localId ): bool` — each Source owns its localId grammar; the serialized
  qualified form additionally restricts localIds to URL-path-safe characters at the wire level.
- `getBaseUri(): ?string` — IRI projection base for this Source's ids. Native RDF projection mints
  all IRIs from the per-wiki base (`NeoWikiConfig->rdfBaseUri`, see [RDF Export](../rdf/rdf-export.md)); a
  per-Source base generalizes this for sourced Subjects. `LocalSource` returns the wiki base; no
  per-source consumer exists yet.

A `SourceRegistry` maps source key to Source, constructed with the local source key
(`WikiMap::getCurrentWikiId()`), maps bare ids to the local Source, returns null for unknown keys,
and throws at registration time on duplicate keys. Keys are compared byte-for-byte: case-insensitive
comparison is foreclosed because dbnames on Linux MySQL/MariaDB (`lower_case_table_names=0`) can
differ only in case, making two such wikis distinct, and the graph's `wiki_id` stores the wiki ID
verbatim (ADR 22), so case-folding would desynchronize the derived pair from stored data. The
registry may additionally warn at registration when a new key differs from an existing one only by
case, as an operator-error guard; comparison semantics stay byte-exact. Extensions register Sources through
`NeoWikiRegistrar::addSource()` in the `NeoWikiRegistration` hook; core registers `LocalSource`
first.

Sources play no part at query time: queryability is materialisation into the graph (ADR 23). Write
paths do not consult the registry; sourced Subjects are read-only. An unresolvable source key
degrades to not-found (null) plus one logged warning, never fatally.

## Consequences

- T3 resolves schema references through the schema's own Source with no contract change; T4 gets
  per-source target validation via `isValidLocalId`.
- Write-back, when designed, arrives as a separate `WritableSource` interface extending `Source`
  (non-breaking for existing implementers), under its own ADR.
- The local persistence path gains read-side indirection (the routing lookup, the registry,
  `LocalSource`, and lazy construction wrappers around the local lookups) and is otherwise
  byte-for-byte unchanged.
- The RDF projection path (`RdfPageProjector`) consumes `SchemaLookup` directly; when T3 routes
  schema resolution through Sources, that path becomes part of its surface. The page-scoped RDF
  export (`ExportPageRdfApi` / `RdfPageExporter`) reads Subjects outside `SubjectLookup` and is
  outside T2's routing surface.

## Alternatives considered

- **localId-string resolution parameter**: rejected; remote Sources need the full id to construct
  qualified Subjects.
- **Result object for unresolvable Sources**: rejected; unknown-source behaves exactly like
  not-found at every surface, and a result object would rewrite all read signatures for it.
- **Unused write-capability stub**: rejected in favor of the `WritableSource` evolution path.
- **Amending ADR 23**: rejected; one decision per ADR.

## Related

- [ADR 23: Subject Sources](023-subject-sources.md) (the model this contract serves)
- [ADR 22: Multi-wiki Graph Node Identity](022-multi-wiki-node-identity.md) (node identity)
- [ADR 1: Domain-centric Architecture](001-domain-centric-architecture.md) (layering)
- [Issue #993](https://github.com/ProfessionalWiki/NeoWiki/issues/993) (the T2 work order)
