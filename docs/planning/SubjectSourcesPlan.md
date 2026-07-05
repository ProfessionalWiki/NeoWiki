# Subject Sources — Implementation Plan

Work orders for implementing the foundation decided in [ADR 23: Subject Sources](../adr/023-subject-sources.md).
The ADR and the [SubjectSources planning doc](SubjectSources.md) are the design record; this file is the work
breakdown and its state. Tracking issue: [#993](https://github.com/ProfessionalWiki/NeoWiki/issues/993).

A session can be pointed at this file with a one-line prompt ("read docs/planning/SubjectSourcesPlan.md and work
task T1"). Tick items off as they complete and record decisions under each task's Notes.

## How to work this plan

* **Ownership.** Morne owns the implementation, including the design calls left open below. Calls within the ADR's
  decisions and Won'ts are the owner's to make — decide, then record the decision in the PR description and the
  task's Notes. A call that would contradict the ADR text is not a judgment call: stop and raise it first.
* **Order.** T1 → T2 are serial: they rewire the same identity spine, and nothing else touching `SubjectId`
  serialization should be in flight while they are. T3 and T4 are independent of each other and both start only
  after T2 merges.
* **Checkpoint.** The T2 PR freezes the Source contract. Jeroen reviews that PR before T3/T4 build on it.
* **One branch and PR per task**, referencing [#993](https://github.com/ProfessionalWiki/NeoWiki/issues/993). Keep
  mechanical, repetitive changes (renames, signature threading) in their own commits, separate from behavioral
  ones — that is what makes the wide T1 diff reviewable.
* **Commands.** `make ci` (PHPUnit + PHPCS + PHPStan; `make phpunit filter=Foo` narrows) and `make ts-ci`
  (vitest + lint + `vue-tsc` build). Integration tests extend `NeoWikiIntegrationTestCase` and need the dev
  services (MediaWiki + Neo4j) running.
* **Conventions.** Tests mirror `src/` 1:1 — plain `TestCase` for pure units, `NeoWikiIntegrationTestCase` plus an
  `IntegrationTest` suffix otherwise; pure-domain TS specs use `.unit.spec.ts`. Use the
  [glossary](../concepts/glossary.md) terms in code, tests, and docs. Update the reference docs whenever a wire
  format changes.
* **Stop conditions.** Stuck for more than a focused day on one item: stop and write up options instead of
  grinding. Never weaken T1's backward-compatibility guarantees to make a later task easier.

## T1 — Widen `SubjectId` to `(source, localId)`

Goal: `SubjectId` (PHP and TS) becomes a `(source, localId)` pair whose bare-nanoid form remains valid and means
"local", per the ADR's Identity section. This is a type-and-wire-format change only: after T1 nothing new can be
*done* with a sourced id, but every boundary can carry one.

Ratified constraints (not up for revision here):

* A bare id (`s` + 14 nanoid chars) stays valid everywhere and means the local source. Local Subjects keep being
  **stored** bare — slot JSON, Neo4j `id` properties, and API output for local Subjects are unchanged; the pair is
  derived for local ids, never persisted ([ADR 22](../adr/022-multi-wiki-node-identity.md)).
* One type serves as a Subject's own identity and as a Relation's target — no separate "reference" type.
* `localId` is opaque outside its Source. The nanoid grammar applies to local ids only, and
  [ADR 14](../adr/014-improved-id-format.md)'s fixed-length and time-sortable guarantees hold only for local ids.
* Full delegation of localId grammar/validation to Source plugins is T2's job; T1 may hard-code an interim rule.

Owner's design calls (decide, then record):

* **The serialized text form of a qualified id** — separator and source-key grammar. Constraints: unambiguous
  against the bare grammar, and safe as a REST path segment (`/neowiki/v0/subject/{subjectId}`), a JSON object
  key, a Neo4j string property, an HTML `data-` attribute value, and a Lua table value. `source:localId` with a
  conservative key grammar (say `[a-z][a-z0-9_-]*`) satisfies all of these — `:` is a valid path character and
  absent from both the nanoid alphabet and that key grammar — and matches the CURIE-shaped projection the ADR
  describes. But the call is yours.
* **Canonicalization**: whether an explicitly-local-qualified input normalizes to bare at construction
  (recommended — `equals()` and the `->text`-keyed collections (`SubjectMap`, `SubjectIdList`, and their TS
  counterparts) otherwise split one identity in two), or is rejected as invalid. Pick one; test it.
* **Interim validation** for non-local source keys before T2's registry exists (suggestion: syntactic key check
  plus non-empty opaque localId).

Work:

- [ ] PHP `src/Domain/Subject/SubjectId.php`: parse/serialize both forms, a source accessor defaulting to local,
      `createNew()` unchanged (always local). Adjust the boundary parse sites (`grep "new SubjectId("`) — notable
      surfaces: the slot (de)serializers, `StatementDeserializer`/`StatementListBuilder` relation targets, the
      REST handlers, `SubjectResolver`, `Neo4jQueryStore`.
- [ ] TS `resources/ext.neowiki/src/domain/SubjectId.ts` in lockstep (it duplicates the PHP regex), plus the
      places treating ids as strings: `RestSubjectRepository` URL building, `NeoWikiApp.vue` placeholder
      hydration, `useSubjectDrag` row-id parsing, `domain/propertyTypes/Relation.ts` validation.
- [ ] Shared round-trip test vectors consumed by both suites (e.g. a JSON fixture with valid bare, valid
      qualified, canonicalization, and invalid cases) so PHP/TS parity is asserted, not assumed.
- [ ] A regression test pinning that a local-only page's slot JSON serialization is byte-identical to the
      pre-change output.
- [ ] Docs: the REST param descriptions (e.g. "15 characters, starting with `s`"),
      [subject-format.md](../reference/subject-format.md), [rest-api.md](../reference/rest-api.md), and a note in
      [graph-model.md](../reference/graph-model.md).

Non-goals:

* No Source interface, registry, or resolution changes (T2).
* No Neo4j re-keying: Subject nodes keep the bare `id` plus `wiki_id` property and the existing uniqueness
  constraint; shared-graph namespacing stays deferred (ADR 22, planning doc §Storage).
* No path that creates sourced Subjects; nothing user-visible changes.

Acceptance: `make ci` and `make ts-ci` green; the shared vectors pass in both suites; the byte-identical slot
JSON test passes; docs updated.

Notes (owner):

## T2 — Source interface, registry, and `LocalSource`

Goal: define the Source contract, stand up the registry, and refactor the existing local slot store into the
default registered Source — behavior-preserving for everything local. This task closes the ADR's open question on
the Source interface contract; the merged PR is the contract freeze.

Ratified constraints:

* One registry maps `sourceKey → Source`; the Source object is the authority for its Subjects' capabilities,
  identity/localId grammar, schema resolution, and base URI. A wiki farm is simply more registered Sources.
* The local source key is the MediaWiki Wiki ID (`WikiMap::getCurrentWikiId()`, as in
  [#905](https://github.com/ProfessionalWiki/NeoWiki/issues/905)); a bare id resolves to it.
* Editability is the only capability the model varies: local = editable and versioned, sourced = read-only. An
  optional write capability may exist as an unused stub, nothing more (write-back is end-of-roadmap).
* No per-Source capability matrix, no ACL, no provenance in the registry (ADR Won'ts).

Owner's design calls (this is the remaining design work):

* **The interface shape**: by-id resolution, capability exposure, localId validation/minting delegation, base
  URI, and what query participation means given that materialisation is the query gate (planning doc §Storage) —
  plausibly nothing at query time, but make that explicit in the contract.
* Whether **schema resolution** enters the contract now (T3 needs it; a `LocalSource`-only method is fine) or T3
  adds it.
* **Placement** consistent with the domain-centric architecture
  ([ADR 1](../adr/001-domain-centric-architecture.md)): the natural seams are `Application/SubjectLookup`,
  `SubjectRepository`, and `SchemaLookup`, with `LocalSource` wrapping the existing local persistence and wiring
  in the `NeoWikiExtension` composition root.
* **Unresolvable behavior**: an unknown source key or unavailable Source degrades gracefully, never fatally —
  define the exact semantics (null plus logged warning, or a result object).
* **Record the frozen contract** as a new ADR (next free number) or as an amendment to ADR 23.

Work:

- [ ] Source interface (and capability surface) plus registry, with tests.
- [ ] `LocalSource` wrapping the existing local persistence; bare and local-qualified ids route through the
      registry.
- [ ] Composition-root wiring, plus the registration point other extensions use to contribute Sources (follow the
      existing plugin-registry patterns).
- [ ] Unknown-source degradation tests.
- [ ] The contract ADR or ADR-23 amendment.

Non-goals: no non-local Source implementation (no SMW/Wikibase, remote, or wikitext source); no write-back; no
rendering or editor changes; no change to what is materialised.

Acceptance: full suites green with existing local-path expectations unmodified (the behavior-preserving bar);
registry and degradation tests in place; the contract recorded; Jeroen has reviewed and approved the contract PR.

Notes (owner):

## T3 — Schema references become `(source, name)` — after T2

Goal: a schema reference carries a source and resolves through *its* Source (independent of the subject's
source), degrading gracefully when unresolvable. Reference plumbing only — no non-local schema source exists yet.

Ratified constraints:

* Schema identity stays name-based ([ADR 17](../adr/017-names-as-identifiers.md)); the reference form is
  `(source, name)`; a schema's source is independent of the subject's source (ADR §Schemas come from Sources).
* Read-only-ness comes from the Source — no new schema type (ADR Won'ts).
* An unresolvable schema (foreign, offline, removed) degrades gracefully: an informative state, never a broken
  page (cf. `SubjectResolver`'s existing fallback pattern).

Owner's design calls:

* Widen `SchemaName` vs. introduce a schema-reference value object. Note that locally the name *is* the page
  title in the Schema namespace (`WikiPageSchemaLookup`), and a source-qualified reference is not a valid title —
  which argues for a distinct reference type with `SchemaName` intact underneath. Your call.
* Serialization: the subject slot JSON `schema` field and `RelationProperty`'s `targetSchema` are the two
  persisted spots. Local references stay bare (same backward-compatibility rule as T1); the schema-name node
  label in Neo4j stays local-only for now.
* Where degradation surfaces in the View/editor when a schema cannot be resolved.

Work:

- [ ] Reference type, with `SchemaLookup` resolution routed through the Source registry.
- [ ] Serialization surfaces plus round-trip tests (regression-pin that local stays bare).
- [ ] Degradation behavior plus tests (render state, not fatal).
- [ ] Docs: [schema-format.md](../reference/schema-format.md), [subject-format.md](../reference/subject-format.md).

Non-goals: no synthesized schemas (Wikibase-style adapters), no cross-wiki View rendering, no delivered-baseline
farm tooling, no changes to schema editing.

Acceptance: suites green; local schema flows unchanged; degradation covered by tests; docs updated.

Notes (owner):

## T4 — Restrict Relation targets to resolvable Sources — after T2, parallel with T3

Goal: the v1 guard from ADR §Relations across Sources: a Relation target whose source is not
registered/resolvable is rejected at write and validation time with a clear message. Cross-source relations open
up later.

Facts: targets are constructed in `StatementListBuilder` (write path) and `StatementDeserializer` (persistence
path); validation flows through the validate endpoint; client-side checks live in
`domain/propertyTypes/Relation.ts` and the relation editor components. Neo4j's `MERGE` stub behavior for absent
targets stays as-is.

Work:

- [ ] Server-side guard on the write and validate paths: reject targets with an unresolvable source; never reject
      already-persisted data on read.
- [ ] TS mirror validation with a user-facing, localized message.
- [ ] Tests on both sides, including "bare target keeps working" and "unknown source rejected".

Non-goals: no cross-source relation UI, no changes to dangling-target semantics in the graph, no read-path
rejection of persisted data.

Acceptance: suites green; guard covered on both sides; message localized.

Notes (owner):

## Deferred — do not build now

Demand-gated (planning doc §Sequencing 3–4 and §Boundaries), tracked on
[#993](https://github.com/ProfessionalWiki/NeoWiki/issues/993), to be filed separately when demand fires:
rendering sourced Subjects in Views (read-only), the on-wiki SMW/Wikibase source, free-form tables (a separate
extension over the plugin point), remote federation, RDF/IRI export, and write-back.
