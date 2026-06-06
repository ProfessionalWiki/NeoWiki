# Subject Sources

Status: Exploration (pre-ADR). Written 2026-06-06 by Jeroen De Dauw and Claude Opus 4.8,
based on approx 4h of investigation and iteration.

This is a WIP exploration doc, intended for team and stakeholder feedback.

## High-level summary

A cluster of new use cases (document-control/approval metadata, Confluence-style free-form tables, cross-wiki
authority data, user-computed and external values) all hit the same two walls in today's model: data that isn't a
local, editable, versioned Subject cannot be **displayed** through our Views, and cannot be **stored/queried**
without bolting it onto the Neo4j page node as untyped properties.

Proposed direction: generalise **where Subjects (and
their Schemas) come from**. A Subject is produced by a **Source**. The local revision-slot store is just the default
Source. Other Sources (built-in page metadata, external/approval providers, remote NeoWiki/Wikibase, organic
wikitext) produce Subjects with **reduced capabilities** — most importantly, not always writeable or versioned.

The UI keeps its Subject assumption everywhere (Views, editors). It only learns to respect per-Subject and
per-Schema capability flags.

## Background: the use cases

1. **External-owned state (BlueSpice document control).** Approval state, last reviewer, etc. are owned by another
   extension (content-stabilisation/approvals). They must be queryable/sortable for dashboards and ideally viewable
   in a page header, must update **without creating a page revision**, and must survive a graph rebuild.
2. **Free-form tables (Confluence migration).** Migrators want an inline key/value table that becomes queryable
   metadata — Confluence's Page Properties / Page Properties Report macros.
3. **Authority control / federation (ECHOLOT).** A shared instance of clean authority records that per-case-study
   instances reference rather than duplicate; more broadly, distributed instances and bi-directional flow.
4. **User-computed and external values.** Values computed in wikitext/Lua, or pulled via ExternalData / Wikibase /
   SMW, surfaced as structured data — the popular SMW + ExternalData pattern.
5. **System page metadata.** `name`, `creationTime`, `lastEditor`, `categories` already exist on the page node but
   cannot be shown via Views.

## Core idea: Subjects and Schemas come from Sources

A **Source** produces Subjects and resolves the Schemas those Subjects use. Schema *lookup* therefore becomes
Source-aware: a schema may be a local Schema-namespace page, a code-defined built-in, or remotely owned.

Each Source (and the Subjects/Schemas it yields) declares four **independent** capabilities:

| Capability | Meaning | Local slot | Engineered adapter | Organic wikitext | Built-in page metadata |
|---|---|---|---|---|---|
| `readable` | can be read | ✓ | ✓ | ✓ | ✓ |
| `writeable` | can be edited through NeoWiki | ✓ | optional (write-back) | ✗ | ✗ |
| `versioned` | an old page revision shows the value as it then was | ✓ | ✗ | ✓ if hard-coded, ✗ if computed/pulled | ✗ |
| provenance | origin we can record | full (ours) | real (system/id/time) | weak (only the host page) | system |

Notes:

- `writeable` is **not** inherent. An engineered adapter can support **write-back** (ECHOLOT bi-directional flow).
  We need not ship a writeable adapter initially, but the plugin system must not preclude one.
- Provenance **quality** varies. Organic wikitext loses real provenance — we only know the page the wikitext is on.
  Engineered adapters can record true provenance, which matters for ECHOLOT (T3.4 chain-of-production).
- Read-only-ness comes **only** from the Source. It is unrelated to schema design (see below).
- `versioned` is really about **history-page behaviour** — what an old page revision shows, which is why it matters.
  Only values re-derived from versioned *input* render history-correct.

### The Source spectrum

- **Engineered end:** dedicated adapters via a plugin system — `RemoteNeoWikiSource`, a Wikibase source, an SMW source.
  Typed, reliable, real provenance, potentially writeable.
- **Organic end:** users wire it themselves — compute in Lua/wikitext, pull via ExternalData — landing as read-only,
  weak-provenance Subjects. No code, no adapter.

Both ends are the **same abstraction**. `RemoteNeoWikiSource` and `WikitextSource` are part of the same plugin system.

## Consumers

- **System page metadata** → a read-only, non-versioned **page-metadata Subject** backed by a code-defined, read-only
  schema (`PageMetadata`: `name`, `creationTime`, `lastEditor`, `categories`). This exposes the page's own metadata as
  a Subject (on retrieval, not in the graph db) — read-only, no slot backing, no versioning — so it can render in Views.
- **External-owned state (e.g. approval)** → a statement provider contributing read-only statements onto the page-metadata Subject
  (approval status maps naturally to the `select` type — Draft/Review/Approved). Storage is unchanged: still projected
  to the Neo4j page node, so Cypher/Lua queries keep working; now also reachable via the Subject read path, hence
  Views. User-set page metadata (page owner, audit date) is different — if user-set it is an ordinary **writeable**
  Subject and a revision on change is correct; a control-document header then composes two Subjects (both render
  through the normal View system).
- **Federation / authority control** → `RemoteNeoWikiSource` (and similar) adapters. Read-only by default, optionally
  writeable. Local materialisation is a per-deployment caching choice, not automatic.
- **Organic computed/external values** → a `{{#set}}`-like, schema-backed wikitext surface producing read-only Subjects.
  Unlocks ExternalData/Wikibase/SMW bridging organically.
- **Confluence free-form tables** → see below.

Some of those might be defined in their own extensions to NeoWiki.

## Confluence free-form tables

The data must be **schema-backed** like any Subject (there are no schemaless Subjects — ADR 008). The schema might have
mostly-optional, mostly-`text` properties; each page's
table-Subject fills in whatever subset it uses. A single shared schema (such tables typically have well under 100
properties) acts as a managed vocabulary, which is what gives name consistency and lets standard Views (infobox)
render the data.

The "free-form" behaviour lives **entirely in a table UI**, not in the model: "pick an existing property or add a
new row" simply calls the normal schema-edit and subject-edit operations. This is **not** an exception to ADR 015 —
that ADR constrains our *standard dedicated editor*; a purpose-built table UI making a different UX choice over the
same plain operations is fine. Picking an existing property converges users on `Status` instead of `status`/`State`;
only genuinely-new properties edit the schema page.

Two designs for the editing/storage surface, **undecided** — needs HW input:

- **Wikitext approach.** A `<propertiestable>` tag holds the table in the page body;
  VE edits the tag content inline (PageExcerpts-style); on save it is parsed into a **read-only derived Subject**.
  Wikitext is the source of truth. This is a Source, so it depends on the Source backbone. The tag can live in a
  *separate extension* supporting both SMW and NeoWiki backends, keeping the wikitext/parsing out of NeoWiki core.
- **Dedicated editor approach.** A NeoWiki table editor over a normal **writeable, versioned** slot Subject. No
  wikitext. Loses the in-body / Confluence-parity authoring UX.

Perhaps the tag would name its Schema, e.g. `<propertiestable schema="Company">`, and/or there could be a default Schema.

Sequencing: the Source backbone is needed regardless (approval, federation, page metadata). The wikitext approach depends on it;
The dedicated editor approach does not but is a separate editor effort. So we **build the Source backbone first**, resolve the approach choice
with HW in parallel, then deliver the Confluence capability (or just the foundations for HW to build it).

## Neo4j consequences

- The **page node stays** as our bookkeeping/index node, unchanged.
- **Read-only synthesised Subjects** (page metadata, approval) add **no new nodes** — they are assembled at read time
  from the existing page-node data. Toggling the Page-metadata Source off therefore costs nothing.
- **Genuinely-new data** (Confluence tables; optionally cached federation) materialises as normal Subject nodes —
  **never** as arbitrary keys on the page node (avoids reserved-key collisions and keeps the infra node clean).

## The refresh primitive (generalises #782)

[#782](https://github.com/ProfessionalWiki/NeoWiki/issues/782) (refresh a page's projected data without an edit) is
not approval-specific. The same operation is needed for any derived/sourced data that can go stale without a re-save:
approval state, computed wikitext values, cached external pulls. Design it as a first-class "refresh this page's
sourced data" operation, shaped by the public-PHP-API decision in
[#789](https://github.com/ProfessionalWiki/NeoWiki/issues/789), rather than a narrow approval hook. Note the existing
`PagePropertyProvider` registry is already invoked on full rebuild, so rebuild re-injection largely exists; the gap is
the on-demand trigger.

## What we are deliberately not doing

- **Schemaless Subjects** — disallowed by ADR 008; everything stays schema-backed.
- **Page as an editable, slot-backed Subject with "computed statements"** (the #830 Option A shape) — reintroduces the
  approval revision-loop (an approval edit creates a new, unapproved revision). The page Subject is read-only instead.
- **Generalising display away from Subjects** — keeps the View/editor UIs Subject-shaped; we relax only the
  writeable/versioned assumptions.
- **Global properties** — rejected previously ([GlobalProperties.md](GlobalProperties.md)); name consistency for
  free-form tables is handled by the table UI's property picker over a normal shared schema.
- **A new schema type** — one schema type only; read-only-ness comes from the Source, not the schema.

## Open questions

Use case / product questions:

1. **Confluence-like table approach** — is in-body VE authoring (Confluence parity) a hard requirement, or is a dedicated
   NeoWiki table editor acceptable? (Decides whether the wikitext path exists at all.)
2. **Is in-View rendering of page/approval metadata actually required**, or is queryable-for-dashboards enough for v1?
   (The dashboard need is already serviceable via the REST/Cypher API; the header may be over-specified.)
3. **Page owner / audit date** — user-set (→ ordinary writeable Subject, revision-on-change is correct) or
   system-managed? Confirms which bucket they fall in and whether the header composes one Subject or two.

Product detail:

4. **Which Subject does `<propertiestable>` bind to** — a dedicated child "properties" Subject, or the page's main
   Subject? Likely differs for the free-form vs structured cases.

Technical:

5. **Source interface contract** — the three access patterns stress it differently: per-page resolution (page
   metadata), by-id (federation), and query. Does one interface cover all, or do they fracture into special cases?
6. **Federation materialisation** — fetch-at-read (no pollution, no local joins) vs cache/materialise (joins, but
   staleness). Per-deployment, or a fixed default?

## Related

- ADRs: [008 one-schema-per-subject](../adr/008-one-schema-per-subject.md),
  [015 dedicated editors](../adr/015-dedicated-editors.md), [018 views](../adr/018-views.md),
  [019 graph database architecture](../adr/019-graph-database-architecture.md).
- Planning: [GlobalProperties](GlobalProperties.md), [RdfMapping](RdfMapping.md).
- Issues: [#830](https://github.com/ProfessionalWiki/NeoWiki/issues/830) (an earlier RfC on this topic, now stale; this doc re-frames it),
  [#782](https://github.com/ProfessionalWiki/NeoWiki/issues/782),
  [#789](https://github.com/ProfessionalWiki/NeoWiki/issues/789).
  [#831](https://github.com/ProfessionalWiki/NeoWiki/issues/831) is related but out of scope here (persistence-layer
  typed values).

