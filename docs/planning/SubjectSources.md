# Subject Sources

This is an early design document for stakeholder feedback before we start implementation and record decisions via a new ADR.

Started 2026-06-06 by Jeroen De Dauw based on approx 6 hours of investigation/design.
Reviewed and refined by Opus 4.8 and Alistair.

## Summary

NeoWiki today assumes every Subject is local, editable, and versioned — stored in a MediaWiki revision slot and
projected into Neo4j. Several needed capabilities do not fit that assumption: page and approval metadata, free-form
tables, cross-wiki metadata in a wiki farm, and data drawn from other systems.

Proposed direction: **a Subject (and the Schema it uses) is produced by a pluggable Source.** The local revision slot
is the default Source. Other Sources produce Subjects with reduced capabilities — notably not always writeable or
versioned. The UI continues to treat everything as Subjects and only learns to respect per-Source capability flags;
sourced data is surfaced *as Subjects*, so it flows through the existing View, query, and editing surfaces.

## Requirements / use cases

Primary drivers (committed work):

1. **Wiki-farm metadata.** A farm of separate wikis needs unified metadata: each page's metadata and approval state
   queryable, sortable, and filterable **across all wikis** (e.g. "all pages approved before date X, farm-wide"), plus
   cross-wiki references in query results. This is the main reason a graph database was chosen, and the primary
   near-term driver. HW confirmed (2026-06-11) that this metadata **must be materialised in the (shared) graph**, not
   only synthesised on read — materialisation is mandatory for it to be queryable.
2. **External-owned state (document control).** Approval state, last reviewer, etc. are owned by another extension.
   They must be queryable for dashboards, viewable alongside Subject data, updatable **without creating a page
   revision**, and survive a graph rebuild.
3. **System page metadata.** `name`, `creationTime`, `lastEditor`, `categories` already exist on the page node; they
   need to be viewable through Views.
4. **Free-form tables (Confluence migration).** An inline key/value table that becomes queryable metadata.

Secondary / future (designed-for, not all committed):

5. **Federation & external sources.** Read-only Subjects drawn from elsewhere: other NeoWiki instances
   (authority-control, or a federation of interoperable instances, with eventual two-way write-back), or whole records
   pulled from external systems (ExternalData, Wikibase, SMW). Page-scoped values computed/`{{#set}}` in wikitext are
   not a separate case — see free-form tables below.
6. **RDF identity.** Stable per-source IRIs for export, plus coarse origin provenance. (Rich chain-of-production
   provenance and rights are a separate model, out of scope here.)

Orthogonal but interacting (tracked elsewhere):

7. **Subject-level access control** and server-side query filtering by ACL — a separate workstream that intersects
   this one only at cross-wiki query filtering.

## Core model

### Sources and the registry

A **Source plugin** embodies a *kind* — core ones like `LocalSource` and `PageMetadataSource`, plus
extension-registered ones such as a `RemoteNeoWikiSource` federation adapter or a `WikitextSource` for
free-form tables. A **registered Source** binds a plugin to config (often a specific wiki/instance). A single **Source registry**
maps `sourceKey → Source`, and the Source object is the authority for everything about its Subjects: capabilities,
base URI, how to resolve/produce them, and its localId grammar.

A wiki farm is simply **more registered Sources** (per wiki, per kind); the identity format does not change. ACL and
rich provenance are deliberately not part of this registry.

Two composing mechanisms sit behind this: a **Source** produces whole Subjects, while a **statement provider**
contributes statements to an existing Subject (the page-metadata Subject, for instance, aggregates providers for
system metadata and approval). Data from elsewhere is never a loose value — it is either a sourced Subject or
statements on one.

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
construction); a remote schema is remote-owned (read-only); a local schema is an ordinary writeable schema.

### Schema identity

Schemas are identified by **name** ([ADR 17](../adr/017-names-as-identifiers.md)), not a generated id. A schema
reference is `(source, name)`, and the schema's source is **independent of the subject's source**: a page-metadata
Subject uses the built-in `PageMetadata` schema; a free-form-table Subject a local catalog schema; a federated Subject
its home instance's schema; a Wikibase item a read-only schema the adapter **synthesizes** (Wikibase has none — the
strategy, e.g. one schema per item or per type, is plugin-internal).

In a farm, schemas are **per-wiki with a delivered common baseline** (HW, 2026-06-11): a Galaxy ships shared schemas to
every wiki (e.g. the control-document schema), and each wiki may also define its own local schemas (e.g. an R&D wiki's
product-line schema the others lack). The shared graph still allows **cross-wiki Cypher queries**, but a Subject whose
schema is local to another wiki **cannot be rendered or edited cross-wiki via the View system or the normal editing
UIs** — HW accepted this limitation for the MVP. Such cross-wiki access must **degrade gracefully** (an informative
error or an import option), not break the page. Across *independent* instances no shared registry can be assumed, so
references stay `(source, name)` with the mapping layer ([RdfMapping.md](RdfMapping.md)) aligning vocabularies.

## Storage and query (Neo4j)

- The **page node stays** as the bookkeeping / index node.
- **Sourced data is materialised in the graph** — HW confirmed materialisation is **mandatory** (not read-time-only),
  since everything must be Cypher-queryable. Page metadata lives on the page node; extension/approval data is a Subject
  node. (A read-only page-metadata *Subject* for View-system display remains a model option but is **not** on HW's MVP
  path, which is query-driven — see Open questions.)
- **Genuinely-new data** (free-form tables; optionally-cached federation) materialises as normal Subject nodes — never
  as arbitrary keys on the page node (which would risk reserved-key collisions and pollute the index node).
- **Wiki farm:** the shared graph tags every node (Subject and page) with its **instance**; uniqueness becomes
  per-instance and derived ids are namespaced by source. Cross-wiki read/query is then a single query over the shared
  graph (the owning wiki's page node is already present). Complexity concentrates in **write-routing** (edits go to the
  home wiki) and **ACL-filtered cross-wiki queries**.

## Rendering

Views render via a by-subject-id placeholder that the client hydrates (revision-aware through the per-revision
endpoint). For sourced Subjects this means the real work is (a) an addressable id and (b) a **source-aware
subject-load path** — server-side `SubjectLookup`, the by-id port behind the subject fetch endpoint and
`SubjectResolver` (both current implementations resolve ids through the graph index, so Source dispatch sits in front
of this port; `SubjectContentRepository` is page/revision-keyed and stays slot-only) — plus the subject store and
fetch endpoints client-side, with capability flags layered on top. The schema-load path is part of the same seam: the
frontend currently fetches schemas as raw `Schema:` page source via core's REST API rather than the NeoWiki schema
endpoint, so source-resolved schemas (e.g. the code-defined page-metadata schema) need the fetch rerouted through the
NeoWiki endpoint, and `Schema:` links need an answer for schemas with no page. "Per-page" resolution collapses into
"by-id" (`pagemeta:<pageId>` is a deterministic id), so the access patterns reduce to **by-id and query**.

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
invoked on graph rebuild, so re-injection on rebuild exists for pages carrying the subject slot (rebuild does not
visit other pages); the gap is the on-demand trigger.

## Free-form tables (Confluence)

The data is schema-backed like any Subject (there are no schemaless Subjects). The schema is an ordinary schema with
mostly-optional, mostly-`text` properties; a single shared schema (such tables typically have well under 100
properties) acts as a managed vocabulary, giving name consistency and letting standard Views render the data. The
"free-form" behaviour — pick an existing property or add a row — lives **in a table UI** that calls the normal schema-
and subject-edit operations; it is not a model change and not a new schema type.

Page-scoped values authored as `{{#set}}`-style wikitext are the same mechanism with a different authoring syntax:
they become statements on a page-level Subject, not loose values.

The authoring/storage mechanism lives in an **extension**, not core: an extension can register its own read-only
engineered Source that parses page wikitext (a `<propertiestable>`-style tag), or offer a dedicated editor over
ordinary local Subjects. Core supplies only the engineered-source plugin point and the schema-backing — nothing
Confluence- or wikitext-specific. The wikitext-vs-editor choice is HW's product decision.

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

### Resolved with HW (2026-06-11)

- **In-View vs query-enough → query-enough.** HW does not need page/approval metadata rendered via the View system for
  the MVP; they query the shared graph (Cypher) and render dashboards in their own UI. So View-rendering of sourced
  Subjects — the §Rendering load-seam work and the in-View parts of §Sequencing — is **off HW's MVP path**.
- **Page owner / approval → split, accepted.** Page owner is user-set (edited via the page → a writeable Subject);
  approval data is extension-managed (a separate Subject). HW accepts the two-Subject split and combines the data in
  its own rendering.
- **Farm topology → shared graph; per-wiki schemas.** Single shared graph confirmed. Schemas are per-wiki with a
  delivered common baseline (not a single global set); the cross-wiki View limitation and graceful degradation are in
  §Schema identity.
- **Materialisation → mandatory** for all HW use cases (not read-time-only).

### Still open

- **Source interface contract** for by-id and query (per-page being a deterministic by-id).
- **Federation resolution** (fetch-at-read vs cache/materialise) and **shared-graph instance tagging** (the farm
  deliverable proper).
- **History-page rendering** for non-history-correct sourced Subjects (show current values, or hide them?).

## Sequencing

HW's MVP path is **materialise sourced data as queryable Subjects, then query it** (their dashboards) — View-system
rendering is off that path (query-enough). So:

1. **HW MVP core:** materialise approval/page metadata as queryable Subjects, plus the refresh-without-edit operation
   so extension data updates without a page revision. Largely reuses the existing page-property provider mechanism.
2. **De-risk the model:** pin down the Source interface contract (by-id + query) and the `(source, localId)` identity
   type — foundational, no stakeholder input needed.
3. **Architecture-proving slice (lower HW priority):** a page-metadata read-only Subject rendered in a View — proves
   read-only Subjects, read-only Schemas and the source-aware load seam, but View display is not on HW's MVP path.
4. **Later:** the multi-wiki shared-graph + per-wiki-schema + graceful-degradation + ACL-filtered-query work; and, for
   the general model rather than HW's MVP, in-View composition of multiple Subjects.

## Related

- ADRs: [008 one-schema-per-subject](../adr/008-one-schema-per-subject.md),
  [014 improved-id-format](../adr/014-improved-id-format.md), [015 dedicated editors](../adr/015-dedicated-editors.md),
  [018 views](../adr/018-views.md), [019 graph database architecture](../adr/019-graph-database-architecture.md).
- Planning: [GlobalProperties](GlobalProperties.md), [RdfMapping](RdfMapping.md).
- Issues: [#830](https://github.com/ProfessionalWiki/NeoWiki/issues/830) (earlier RfC on this topic, now stale;
  re-framed here), [#782](https://github.com/ProfessionalWiki/NeoWiki/issues/782) (refresh),
  [#789](https://github.com/ProfessionalWiki/NeoWiki/issues/789) (public PHP API),
  [#831](https://github.com/ProfessionalWiki/NeoWiki/issues/831) (typed statement values — its shared-vs-separate
  design question is gated on #830, which this doc re-frames; independent for the first slice, but a prerequisite for
  type-aware querying of Subjects materialised from Sources, such as free-form tables and cached federation).
