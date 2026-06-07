# Subject Sources

Status: Exploration (pre-ADR). Started 2026-06-06 by Jeroen De Dauw and Claude Opus 4.8, expanded over subsequent
sessions of investigation and iteration.

A WIP exploration doc for team and stakeholder feedback, and a context seed for future work. If the direction is
confirmed it should graduate to an ADR.

## Summary

NeoWiki today assumes every Subject is local, editable, and versioned — stored in a MediaWiki revision slot and
projected into Neo4j. Several needed capabilities do not fit that assumption: page and approval metadata, free-form
tables, cross-wiki metadata in a wiki farm, references to other systems, and user-computed values.

Proposed direction: **a Subject (and the Schema it uses) is produced by a pluggable Source.** The local revision slot
is the default Source. Other Sources produce Subjects with reduced capabilities — notably not always writeable or
versioned. The UI continues to treat everything as Subjects and only learns to respect per-Source capability flags;
sourced data is surfaced *as Subjects*, so it flows through the existing View, query, and editing surfaces.

## Requirements / use cases

Primary drivers (committed work):

1. **Wiki-farm metadata.** A farm of separate wikis needs unified metadata: each page's metadata and approval state
   queryable, sortable, and filterable **across all wikis** (e.g. "all pages approved before date X, farm-wide"), plus
   cross-wiki references in query results. This is the main reason a graph database was chosen, and the primary
   near-term driver. Cross-wiki queryability implies the metadata must be **materialised in the (shared) graph**, not
   only synthesised on read.
2. **External-owned state (document control).** Approval state, last reviewer, etc. are owned by another extension.
   They must be queryable for dashboards, viewable alongside Subject data, updatable **without creating a page
   revision**, and survive a graph rebuild.
3. **System page metadata.** `name`, `creationTime`, `lastEditor`, `categories` already exist on the page node; they
   need to be viewable through Views.
4. **Free-form tables (Confluence migration).** An inline key/value table that becomes queryable metadata.

Secondary / future (designed-for, not all committed):

5. **Federation / authority control.** A shared authority instance other instances reference; more generally a
   federation of interoperable instances with eventual two-way (write-back) flow.
6. **User-computed / external values.** Values computed in wikitext/Lua or pulled via ExternalData / Wikibase / SMW,
   surfaced as structured data.
7. **RDF identity.** Stable per-source IRIs for export, plus coarse origin provenance. (Rich chain-of-production
   provenance and rights are a separate model, out of scope here.)

Orthogonal but interacting (tracked elsewhere):

8. **Subject-level access control** and server-side query filtering by ACL — a separate workstream that intersects
   this one only at cross-wiki query filtering.

## Core model

### Sources and the registry

A **Source plugin** embodies a *kind*: `LocalSource`, `PageMetadataSource`, `WikitextSource`, `RemoteNeoWikiSource`,
etc. A **registered Source** binds a plugin to config (often a specific wiki/instance). A single **Source registry**
maps `sourceKey → Source`, and the Source object is the authority for everything about its Subjects: capabilities,
base URI, how to resolve/produce them, and its localId grammar.

A wiki farm is simply **more registered Sources** (per wiki, per kind); the identity format does not change. ACL and
rich provenance are deliberately not part of this registry.

### Identity

A Subject id is a flat pair: **`(source, localId)`**.

- The existing `SubjectId` is widened to carry a source, defaulting to local, so a bare nanoid still means a local
  Subject (backward compatible). It is a qualified *id*, not a separate "reference" type: the same type is a Subject's
  own identity and a Relation's target, much like a primary key and a foreign key.
- `localId` is **polymorphic and opaque outside its Source**: a nanoid for local Subjects, a page id for page
  metadata (`pagemeta:123`), a remote id or URI for federation. Each Source owns its localId grammar, validation,
  stability, and minting. Generic code treats `(source, localId)` as opaque and routes resolution to the Source; the
  global id-format check becomes source-delegated.
- The structured pair is canonical; IRI and CURIE forms are escaped projections of it (the registry's
  `source → base-URI` map is also the RDF prefix/URI map).
- The source qualifier is about **provenance/resolvability, not uniqueness**. Nanoids are already collision-resistant
  across wikis (enabling import/export); but *derived* ids such as `pagemeta:123` are not unique across wikis, so the
  source key is what namespaces them in a shared graph.
- The [ADR 14](../adr/014-improved-id-format.md) properties (fixed length, time-sortable) hold only for local
  nanoids, not for arbitrary ids.

### Capabilities

Each Source declares its capabilities; because the id carries the source, capabilities are reachable from the id.
Example sources:

| Capability | Meaning | Local slot | Engineered adapter | Wikitext | Page metadata |
|---|---|---|---|---|---|
| `readable` | can be read | ✓ | ✓ | ✓ | ✓ |
| `writeable` | editable through NeoWiki | ✓ | optional (write-back) | ✗ | ✗ |
| `versioned` | an old page revision shows the then-value | ✓ | ✗ | ✓ if hard-coded | ✗ |
| `queryable` | reachable via Cypher | ✓ | only if materialised | only if materialised | ✓ (page node) |
| provenance | origin recorded | local | real (system/id/time) | weak (host page) | system |

- `writeable` is additionally gated by **instance-locality**: a Subject is editable in its home wiki; cross-wiki is
  read-only until a write-back adapter exists.
- `queryable` is distinct from `readable`: a Subject is Cypher-queryable only if materialised in the (local or shared)
  graph. A sourced-but-not-materialised Subject is viewable and fetchable by id, but not queryable.

### Schemas from Sources

Schemas are resolved through Sources too and can be read-only: the page-metadata schema is code-defined (read-only by
construction); a remote schema is remote-owned (read-only); a local schema is an ordinary writeable schema. There is
one schema type throughout — read-only-ness comes from the Source, never from a schema "kind"
([ADR 8](../adr/008-one-schema-per-subject.md)).

## Storage and query (Neo4j)

- The **page node stays** as the bookkeeping / index node.
- **Page and approval metadata are materialised on the page node**, so they are queryable (including cross-wiki in a
  shared graph). The read-only page-metadata **Subject** is a display projection over that data — synthesised on read,
  with no separate Subject node. (Whether to *also* materialise it as a Subject node, to allow uniform `:Subject`
  querying across page and subject metadata, is an open question.)
- **Genuinely-new data** (free-form tables; optionally-cached federation) materialises as normal Subject nodes — never
  as arbitrary keys on the page node (which would risk reserved-key collisions and pollute the index node).
- **Wiki farm:** the shared graph tags every node (Subject and page) with its **instance**; uniqueness becomes
  per-instance and derived ids are namespaced by source. Cross-wiki read/query is then a single query over the shared
  graph (the owning wiki's page node is already present). Complexity concentrates in **write-routing** (edits go to the
  home wiki) and **ACL-filtered cross-wiki queries**.
- Rule of thumb: **materialise iff the data is not otherwise in the graph.**

## Rendering

Views render via a by-subject-id placeholder that the client hydrates (revision-aware through the per-revision
endpoint). For sourced Subjects this means the real work is (a) an addressable id and (b) a **source-aware
subject-load path** — `SubjectContentRepository` server-side, the subject store and fetch endpoints client-side —
with capability flags layered on top. "Per-page" resolution collapses into "by-id" (`pagemeta:<pageId>` is a
deterministic id), so the access patterns reduce to **by-id and query**.

## Relations across Sources

A Relation targets a `(source, localId)` id; resolution routes to the target's Source. Cross-source relations are not
blocked (Neo4j `MERGE` creates a stub for an absent target), but display needs the Source and `targetSchema`
validation is limited when the target is remote. For an initial version, restrict Relation targets to resolvable
Sources and open up cross-source relations later.

## Refresh without an edit

Generalise the "refresh page properties" need ([#782](https://github.com/ProfessionalWiki/NeoWiki/issues/782)) into a
first-class **"refresh this page's sourced data"** operation — it serves approval state, computed wikitext values, and
cached external pulls alike — shaped by the public-PHP-API decision
([#789](https://github.com/ProfessionalWiki/NeoWiki/issues/789)). The page-property provider registry is already
invoked on graph rebuild, so re-injection on rebuild exists; the gap is the on-demand trigger.

## Free-form tables (Confluence)

The data is schema-backed like any Subject (there are no schemaless Subjects). The schema is an ordinary schema with
mostly-optional, mostly-`text` properties; a single shared schema (such tables typically have well under 100
properties) acts as a managed vocabulary, giving name consistency and letting standard Views render the data. The
"free-form" behaviour — pick an existing property or add a row — lives **in a table UI** that calls the normal schema-
and subject-edit operations; it is not a model change and not a new schema type.

Two editing/storage options, undecided (needs HW input):

- **Wikitext-sourced.** A `<propertiestable>` tag holds the table in the page body, edited inline in the visual
  editor; on save it is parsed into a read-only derived Subject. Wikitext is the source of truth; this is a Source and
  depends on the Source backbone. The tag and parsing can live in a separate extension (supporting both SMW and
  NeoWiki backends), keeping wikitext out of NeoWiki core.
- **Dedicated editor.** A NeoWiki table editor over a normal writeable, versioned slot Subject; no wikitext, but loses
  the in-body authoring parity.

The tag may name its schema (`<propertiestable schema="...">`), optionally defaulting to a page-type's schema or a
configured default.

## Fit with the farm and with ECHOLOT

The wiki farm and a federation of instances **share the identity model and differ in resolution**: the farm uses a
shared graph (Subjects tagged by instance, cross-wiki read = one query); a federation uses separate instances reached
by remote adapters. Same Source abstraction, pluggable resolution. The `source → base-URI` registry doubles as the RDF
prefix/URI map, so federation identity and RDF identity are one mechanism. Coarse origin provenance is free from the
id; rich chain-of-production provenance is a separate model.

## Out of scope / boundaries

- Schemaless Subjects ([ADR 8](../adr/008-one-schema-per-subject.md)).
- A page modelled as an editable, slot-backed Subject with "computed statements" — an approval edit would create a
  new, unapproved revision. The page-metadata Subject is read-only instead.
- A parallel non-Subject rendering path: sourced data is surfaced as Subjects so the existing UIs apply.
- Global properties ([GlobalProperties.md](GlobalProperties.md)); name consistency for free-form tables is handled by
  the table UI over a shared schema.
- A new schema type; read-only-ness comes from the Source.
- Enforcing a remote instance's ACLs locally; rich provenance inside the Source model.

## Open questions

Needs stakeholder (HW) input:

1. **In-View vs query-enough.** Is rendering page/approval metadata inside a View required, or is
   queryable-for-dashboards enough for a first version? (Sizes the work substantially.)
2. **Free-form tables:** wikitext-sourced (in-body parity) or dedicated editor?
3. **Page owner / audit date:** user-set (ordinary writeable Subject, a revision on change being correct) or
   system-managed?
4. **Farm topology:** confirm the farm uses a single shared graph.

Technical / design:

5. **Source interface contract** for by-id and query (per-page being a deterministic by-id).
6. **Materialisation of page metadata as Subject nodes** vs page-node-only — driven by whether uniform `:Subject`
   querying across page and subject metadata is wanted.
7. **Federation resolution** (fetch-at-read vs cache/materialise) and **shared-graph instance tagging** (the farm
   deliverable proper).
8. **History-page rendering** for non-history-correct sourced Subjects (show current values, or hide them?).

## Sequencing

1. **First slice:** a single-wiki page-metadata read-only Subject rendered in a View — the smallest end-to-end path
   that proves read-only Subjects, read-only Schemas, the source-aware load seam, and the `(source, localId)`
   identity. No external dependencies and no instance axis yet, but the id is designed so an instance qualifier can be
   added without repaint.
2. **No-regret win:** the refresh-without-edit operation for approval, independent of the larger questions.
3. **De-risk:** pin down the Source interface contract (by-id + query) and the identity type.
4. **Gated on the answers above:** in-View composition for control-document headers, free-form tables, and the
   multi-wiki shared-graph + ACL-filtered query work.

## Related

- ADRs: [008 one-schema-per-subject](../adr/008-one-schema-per-subject.md),
  [014 improved-id-format](../adr/014-improved-id-format.md), [015 dedicated editors](../adr/015-dedicated-editors.md),
  [018 views](../adr/018-views.md), [019 graph database architecture](../adr/019-graph-database-architecture.md).
- Planning: [GlobalProperties](GlobalProperties.md), [RdfMapping](RdfMapping.md).
- Issues: [#830](https://github.com/ProfessionalWiki/NeoWiki/issues/830) (earlier RfC on this topic, now stale;
  re-framed here), [#782](https://github.com/ProfessionalWiki/NeoWiki/issues/782) (refresh),
  [#789](https://github.com/ProfessionalWiki/NeoWiki/issues/789) (public PHP API),
  [#831](https://github.com/ProfessionalWiki/NeoWiki/issues/831) (typed values — related but separate).
