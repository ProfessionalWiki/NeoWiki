# Subject Sources

This is the detailed design exploration behind [ADR 23: Subject Sources](../adr/023-subject-sources.md) and
[ADR 22: Multi-wiki Graph Node Identity](../adr/022-multi-wiki-node-identity.md). The ADRs are the authoritative
decision records; this doc goes deeper into the requirements, use cases, and reasoning that fed them.

Started 2026-06-06 by Jeroen De Dauw based on approx 7.5 hours of investigation/design.
Reviewed and refined by Opus 4.8 and Alistair.

## Summary

NeoWiki today assumes every Subject is local, editable, and versioned — stored in a MediaWiki revision slot and
projected into Neo4j. Several needed capabilities do not fit that assumption: page and approval metadata, free-form
tables, cross-wiki metadata in a wiki farm, and data drawn from other systems.

Proposed direction: **Subjects come from pluggable Sources.** The local revision slot is the default Source; other
Sources supply Subjects too — another structured-data store on the same wiki (SMW, Wikibase), another NeoWiki, or an
external system. A Subject's source decides one thing — **editability**: local Subjects are editable (per access
rights) and versioned; sourced Subjects are **read-only** for now (writing back to a source is an end-of-roadmap
option, kept open, not built). Whatever the source, a Subject renders through the existing Views (sourced ones
read-only) and is queryable once materialised — there is no per-Source capability matrix, just the
local-editable / sourced-read-only distinction.

Separately, **page-facts** — approval state, system page metadata — are *not* Subjects. They are facts about a page,
materialised on the page node and surfaced via query (Cypher / dashboards), not the View or editor. The Source system
*could* model them as read-only Subjects, but for this use case we deliberately don't.

## Requirements / use cases

Primary drivers (committed work):

1. **Wiki-farm metadata.** A farm of separate wikis needs unified metadata: each page's metadata and approval state
   queryable, sortable, and filterable **across all wikis** (e.g. "all pages approved before date X, farm-wide"), plus
   cross-wiki references in query results. This is the main reason a graph database was chosen, and the primary
   near-term driver. HW confirmed (2026-06-11) that this metadata **must be materialised in the (shared) graph**, not
   only synthesised on read — materialisation is mandatory for it to be queryable.
2. **External-owned state (document control).** Approval state, last reviewer, etc. are owned by another extension.
   They must be queryable for dashboards, updatable **without creating a page revision**, and survive a graph rebuild.
3. **System page metadata.** `name`, `creationTime`, `lastEditor`, `categories` already exist on the page node as
   page-facts and are queryable — consumers render them in their own dashboards, not through NeoWiki Views.
4. **Free-form tables (Confluence migration).** An inline key/value table that becomes queryable metadata.

Secondary / future (designed-for, not all committed):

5. **On-wiki adoption of existing structured data (SMW / Wikibase).** Install NeoWiki on a wiki that already runs SMW
   or Wikibase and expose that data as read-only Subjects — Cypher querying, Views, and AI-readiness over data you
   already have, with a gradual migration path (eventually edit in NeoWiki and write back until you flip the
   authority). A low-friction adoption wedge, and the easiest sourced case (the store is co-located, no network).
6. **Federation & external sources.** Read-only Subjects from other NeoWiki instances (authority-control; a federation
   of interoperable instances; eventual two-way write-back) or external systems (ExternalData, Wikibase.org).
   Page-scoped computed/`{{#set}}` wikitext values are not a separate case — see free-form tables below.
7. **RDF identity.** Stable per-source IRIs for export, plus coarse origin provenance. (Rich chain-of-production
   provenance and rights are a separate model, out of scope here.)

Orthogonal but interacting (tracked elsewhere):

8. **Subject-level access control** and server-side query filtering by ACL — a separate workstream that intersects
   this one only at cross-wiki query filtering.

## Core model

### Sources and the registry

A **Source plugin** embodies a *kind* — core ones like `LocalSource`, plus extension-registered ones such as a
`RemoteNeoWikiSource` federation adapter, an `SMWSource` / `WikibaseSource` for an on-wiki store, or a `WikitextSource`
for free-form tables. A **registered Source** binds a plugin to config (often a specific wiki/instance). A single
**Source registry** maps `sourceKey → Source`, and the Source object is the authority for everything about its
Subjects: capabilities, base URI, how to resolve/produce them, and its localId grammar.

A wiki farm is simply **more registered Sources** (per wiki, per kind); the identity format does not change. ACL and
rich provenance are deliberately not part of this registry.

Two mechanisms sit behind this: a **Source** produces whole Subjects, while **providers** contribute **page-facts**
(system metadata, approval state) as properties on the page node. Data from elsewhere is never a loose value — it is
either a sourced Subject or a page-fact on the page node.

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

### Editability and queryability

There is no per-Source capability matrix — just one distinction:

- **Local Subjects** are editable through the normal editor (subject to access rights) and versioned.
- **Sourced Subjects** are **read-only** through NeoWiki for now. Writing back to a source (so an edit propagates to
  the origin) is an **end-of-roadmap** option — kept open via an optional per-Source write capability, not built.

Querying is independent of source: a Subject is Cypher-queryable once **materialised** in the graph (materialisation
is mandatory for query). History follows the origin — the local slot is versioned; a sourced Subject's history, if
any, lives at its source. (Page-facts are not Subjects; see below.)

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
- **Page-facts** (system metadata, approval state) live as properties on the **page node** — query-only, not Subjects.
  HW confirmed they must be **materialised** (not read-time-only) to be queryable.
- **Subjects** materialise as Subject nodes — local ones from the slot, sourced ones (free-form tables, on-wiki
  SMW/Wikibase, cached federation) as their own nodes; never as arbitrary keys on the page node (which would risk
  reserved-key collisions and pollute the index node).
- **Wiki farm:** the shared graph tags every node (Subject and page) with its **instance**; uniqueness becomes
  per-instance and derived ids are namespaced by source. Cross-wiki read/query is then a single query over the shared
  graph (the owning wiki's page node is already present). Complexity concentrates in **write-routing** (edits go to the
  home wiki) and **ACL-filtered cross-wiki queries**.

## Rendering

Local Subjects render through the existing View/editor as today — a by-subject-id placeholder hydrated client-side,
revision-aware. **Sourced Subjects render the same way, read-only** (clearly marked as from their source, degrading
gracefully if the source is unavailable). Making that work needs a **source-aware subject-load seam** (resolve the
id's source, fetch from there, resolve its schema) — real work that rides on Sources existing, so it is **deferred,
not dropped**.

**Page-facts are not rendered through Views** — they are query-only (Cypher / dashboards).

## Relations across Sources

A Relation targets a `(source, localId)` id; resolution routes to the target's Source. Cross-source relations are not
blocked (Neo4j `MERGE` creates a stub for an absent target), but display needs the Source and `targetSchema`
validation is limited when the target is remote. For an initial version, restrict Relation targets to resolvable
Sources and open up cross-source relations later.

## Refresh without an edit

Generalise the "refresh page properties" need ([#782](https://github.com/ProfessionalWiki/NeoWiki/issues/782), now
[#889](https://github.com/ProfessionalWiki/NeoWiki/issues/889)) into a first-class **"refresh this page's sourced
data"** operation — it serves approval state, computed wikitext values, and cached external pulls alike — shaped by the
public-PHP-API decision ([#789](https://github.com/ProfessionalWiki/NeoWiki/issues/789)). The page-property provider
registry is already invoked on graph rebuild, so re-injection on rebuild exists for pages carrying the subject slot
(rebuild does not visit other pages); the gap is the on-demand trigger.

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

## Boundaries

**Deferred (designed-for, not foreclosed) — build by demand, on top of the source foundation:**

- Rendering sourced Subjects in Views (read-only) — see Rendering.
- Editing sourced Subjects / write-back to a source — end-of-roadmap; enables, e.g., gradual migration off an on-wiki
  SMW/Wikibase store and ECHOLOT bi-directional flow.
- Remote federation and RDF/IRI export — gated on the foundation and on ECHOLOT input.

**Won't (deliberate):**

- Schemaless Subjects ([ADR 8](../adr/008-one-schema-per-subject.md)).
- A page modelled as an editable, slot-backed Subject with "computed statements" — an approval edit would create a
  new, unapproved revision; page-facts stay non-Subject instead.
- Modelling page-facts (approval, system metadata) as Subjects, or rendering them through the View/editor — they are
  query-only by choice.
- A per-Source capability matrix in the user-facing model — the single local-editable / sourced-read-only distinction
  replaces it.
- Global properties ([GlobalProperties.md](GlobalProperties.md)); name consistency for free-form tables is handled by
  the table UI over a shared schema.
- A new schema type; read-only-ness comes from the Source.
- Enforcing a remote instance's ACLs locally; rich provenance inside the Source model.

## Open questions

### Resolved with HW (2026-06-11)

- **In-View vs query-enough → query-enough.** HW does not need page/approval metadata in the View system for the MVP;
  they query the shared graph (Cypher) and render dashboards in their own UI. So page-facts are query-only, and in-View
  rendering of sourced Subjects is **deferred** (valuable, but not on HW's MVP path — see Boundaries).
- **Page owner / approval → split, accepted.** User-set control-document metadata (owner, audit date) is an ordinary
  editable Subject; approval state is an extension-owned page-fact. HW accepts the split and combines the two in its
  own rendering.
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

1. **HW MVP core (now):** materialise approval / page metadata (page-facts, query-only) via the existing page-property
   mechanism; the refresh-without-edit operation ([#889](https://github.com/ProfessionalWiki/NeoWiki/issues/889));
   and multi-wiki node identity ([#905](https://github.com/ProfessionalWiki/NeoWiki/issues/905)) for per-wiki query
   filtering. Forward-compatible down-payments, not a separate system.
2. **Source foundation:** the `(source, localId)` identity, the Source registry/interface (by-id + query +
   capabilities, including an optional write capability), and the local store refactored as the default `LocalSource`.
   The single system everything else builds on.
3. **Source consumers (by demand, on the foundation):** sourced Subjects in Views (read-only); the on-wiki
   SMW/Wikibase source (read-only) — the easiest sourced case and an adoption/migration wedge; remote federation and
   RDF/IRI export (gated on ECHOLOT).
4. **End-of-roadmap, by demand:** editing sourced Subjects / write-back (enables SMW/Wikibase migration and
   bi-directional flow).

## Related

- ADRs: [023 subject sources](../adr/023-subject-sources.md) (the decision record for this doc),
  [022 multi-wiki node identity](../adr/022-multi-wiki-node-identity.md),
  [008 one-schema-per-subject](../adr/008-one-schema-per-subject.md),
  [014 improved-id-format](../adr/014-improved-id-format.md), [015 dedicated editors](../adr/015-dedicated-editors.md),
  [018 views](../adr/018-views.md), [019 graph database architecture](../adr/019-graph-database-architecture.md).
- Planning: [GlobalProperties](GlobalProperties.md), [RdfMapping](RdfMapping.md).
- Issues: [#889](https://github.com/ProfessionalWiki/NeoWiki/issues/889) (refresh-without-edit; replaces the closed
  #782), [#905](https://github.com/ProfessionalWiki/NeoWiki/issues/905) (multi-wiki node identity),
  [#789](https://github.com/ProfessionalWiki/NeoWiki/issues/789) (public PHP API),
  [#831](https://github.com/ProfessionalWiki/NeoWiki/issues/831) (typed statement values — prerequisite for type-aware
  querying of materialised Subjects), [#830](https://github.com/ProfessionalWiki/NeoWiki/issues/830) (earlier RfC,
  closed/superseded).
