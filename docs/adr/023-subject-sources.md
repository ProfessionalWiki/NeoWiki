# Subject Sources

Date: 2026-06-22

Status: Draft

Feedback desired! Also see the **Open questions** section below.

## Context

Prior to this ADR, NeoWiki assumed every Subject is local, editable, versioned, stored in a MediaWiki revision slot
([ADR 4](004-use-dedicated-slot.md)), and projected into the graph ([ADR 19](019-graph-database-architecture.md)).

Several needed capabilities do not fit that assumption: cross-wiki metadata in a wiki farm, page and approval metadata, free-form (Confluence-style)
tables, and structured data drawn from other systems (another NeoWiki, an on-wiki SMW or Wikibase store, external services).

The detailed exploration is in [planning/SubjectSources.md](../planning/SubjectSources.md).

## Decision

### Subjects come from pluggable Sources

A Subject is produced by a **Source**. The local revision slot is the default Source; others, like an on-wiki SMW/Wikibase
store, another NeoWiki, or an external system, can also supply Subjects. A **Source registry** maps a source key to its
Source, which is the authority for its Subjects' capabilities, identity, and schema resolution. A wiki farm is simply
more registered Sources.

### A source decides editability

The distinction:

- **Local Subjects** are editable through the normal editor (subject to access rights) and versioned.
- **Sourced Subjects** are read-only. Writing back to a source is deferred (see Open questions).

Every Subject, whatever its source, renders through the existing Views (sourced ones read-only) and is queryable once
materialised in the graph.

Editability is the only capability the model varies today, and it bundles *editable + versioned* (local) against
*read-only* (sourced). This is not a permanent two-state space: the deferred write-back capability will make some
sourced Subjects writeable, reintroducing per-source variation — so "sourced ⇒ read-only" is the current scope, not a
closed decision.

### Page Properties are not exposed via Subjects

Approval state and system page metadata (`name`, `creationTime`, `lastEditor`, `categories`) are
[Page Properties](../concepts/glossary.md#page-property) — facts stored on the page node, the built-in ones plus any an
extension contributes through the `PagePropertyProvider` plugin. They are surfaced via query (Cypher), not through the
View or editor. The Source model could expose them as read-only Subjects; for this use case it deliberately does not —
they stay Page Properties.

### Identity

A Subject's id is a pair `(source, localId)`. The existing `SubjectId` ([ADR 14](014-improved-id-format.md)) is widened
to carry a source, defaulting to local — a bare nanoid still means a local Subject. `localId` is opaque outside its
Source (a nanoid locally; a page id or a remote id / URI elsewhere); each Source owns its grammar, validation, and
minting. The pair is the canonical **reference** form (relation targets, view ids, fetch); IRI and CURIE forms are
projections of it, with the source-to-base-URI map doubling as the RDF prefix map. ADR 14's fixed-length and
time-sortable guarantees hold only for local nanoids, not for arbitrary `localId`s.

This ratifies the two forward-compatibility assumptions of [ADR 22](022-multi-wiki-node-identity.md), which decided the
first increment (per-wiki node identity in a shared graph): the local source key is the MediaWiki Wiki ID, and a local
Subject stays **stored** as a bare nanoid (with `wiki_id` as a property), the `(source, localId)` pair being derived
from it rather than a re-keying of stored data.

### Schemas come from Sources

A schema is resolved through a Source and may be read-only (a code-defined built-in, or a remote-owned schema) or
writeable (an ordinary local schema). A schema reference is `(source, name)` ([ADR 17](017-names-as-identifiers.md)),
and a schema's source is independent of the subject's source. Rendering a sourced Subject therefore resolves the
schema through *its* Source, which may differ from the subject's. When a schema cannot be resolved — a foreign,
offline, or removed schema — rendering degrades gracefully rather than breaking the page.

## Consequences

- One model spans local data, on-wiki SMW/Wikibase adoption, cross-wiki farm metadata, and external/federated data,
  rather than separate mechanisms.
- The View, query, and editing surfaces stay Subject-shaped; they gain only a read-only state for sourced Subjects.
- Approval and page metadata stay Page Properties, queryable without being exposed as Subjects, so recording them does
  not create a page revision.
- Materialisation is required for a Subject to be queryable; sourced data that is not materialised is fetchable by id
  but not queryable.

## Open questions

Deferred and/or still being designed; consortium feedback is expected here.

- **Federation resolution** — fetch-at-read vs cache/materialise; for triple-store backends, federated queries.
- **RDF / IRI export and ontology mapping** — see [planning/NativeRdfProjection.md](../planning/NativeRdfProjection.md)
  and [planning/OntologyMapping.md](../planning/OntologyMapping.md).
- **Editing sourced Subjects (write-back)** — end-of-roadmap. How useful? Things like editing Wikibase Items
  via NeoWiki UI, or editing data from a remote NeoWiki
- **The Source interface contract** for by-id and query resolution.

## Alternatives Considered

- **Global properties / a single shared schema set.** Rejected ([planning/GlobalProperties.md](../planning/GlobalProperties.md));
  name consistency is handled per-schema.
- **Schemaless Subjects.** Disallowed ([ADR 8](008-one-schema-per-subject.md)); free-form tables use an ordinary
  schema edited through a table UI.
- **Model Page Properties (approval, metadata) as Subjects.** Rejected for this use case: they are facts about a page,
  simpler kept on the page node; modelling them as slot-backed Subjects would make recording them a new, unapproved
  page revision.
- **Materialization only.** We'd avoid adding subject sources and continue without support for displaying anything that is
  not a "normal local Subject" via the View system. You'd only be able to display it via userland scripting on top of queries.

## Related

- [planning/SubjectSources.md](../planning/SubjectSources.md) — detailed exploration behind this ADR.
- [ADR 22: Multi-wiki Graph Node Identity](022-multi-wiki-node-identity.md),
  [ADR 19: Graph Database Architecture](019-graph-database-architecture.md),
  [ADR 14: Improved Id Format](014-improved-id-format.md),
  [ADR 8: One Schema per Subject](008-one-schema-per-subject.md), [ADR 18: Views](018-views.md).
- [planning/NativeRdfProjection.md](../planning/NativeRdfProjection.md),
  [planning/OntologyMapping.md](../planning/OntologyMapping.md),
  [planning/GlobalProperties.md](../planning/GlobalProperties.md).
