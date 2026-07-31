# Frontend Stores Are Registries, Not Caches

Date: 2026-07-31

Status: Draft

Amends [ADR 16](016-frontend-state-management.md), which settles which components read the stores but is silent on
fetching, caching and invalidation. Its editing-UI rule now applies to reads; mutations and validation still go through
store actions.

## Context

The frontend keeps server state in Pinia stores (`SchemaStore`, `LayoutStore`, `SubjectStore`). Each store grew the
same trio of actions — `fetchX`, `getOrFetchX` and `saveX` — treating the store as a cache: reads consulted stored
values to avoid fetches, and saves wrote the client's object through. An invalidation model was never decided
([#1211](https://github.com/ProfessionalWiki/NeoWiki/issues/1211)). Reads assigned awaited results to shared state
unconditionally ([#1177](https://github.com/ProfessionalWiki/NeoWiki/issues/1177)), writes invalidated only on their
own success path, and deletes invalidated nothing. The components already routed around the cache: editing flows
fetched fresh data before opening, and two flows reloaded the whole page as their invalidation primitive.

A client-side cache can only be consistent with the session's own writes. Edits from other tabs and other users stay
invisible until the client fetches again. Query libraries such as TanStack Query and Pinia Colada default to treating
cached data as immediately stale for the same reason.

## Decision

Stores are page-scoped registries of server state, not caches. A registry holds the page payload seeded at load and
what acknowledged mutations put there: for Subjects the mutation response's canonical entity, for Schemas and Layouts
the authored object the save committed.

1. **Reads are explicit.** `getX` reads the registry synchronously. Editing UIs always open on freshly fetched data:
   read through the repositories and passed in as props ([ADR 16](016-frontend-state-management.md)). They still call
   store actions to mutate and to validate. That fetch is the job of the code that opens the editor: a display catches
   up on commit, not on open. The store API offers no fetch-on-miss reads, with one exception, plus one read that
   always fetches:
   - `SubjectStore.getOrFetchSubject` resolves display labels over the seeded page payload, which bundles referenced
     Subjects so N relation displays do not fire N requests.
   - `SchemaStore.fetchAllSchemaSummaries` lets concurrent callers share one in-flight pagination. Results are not
     retained, so every new caller cohort fetches fresh data.
2. **Mutations keep the registry consistent with the session's own writes, on every path.** Each store has a
   `mutationEpoch` counter, bumped when the backend acknowledges a write. Saves write through, deletes remove the
   entity from all registry state — at the latest on the next successful refresh — and invalidation that must survive
   a rejected save runs in a `finally` block. Three further obligations:
   - The store records the Subject write's response, not the client's copy: the response carries the persisted Subject
     with the page context the editor's copy lacks, plus the Schema the Subject instantiates.
   - A mutation must detach any derived in-flight request slot its store holds, so the next caller starts a fresh
     request instead of joining one that predates the write. Current instance: `SchemaStore`'s `summariesRequest`,
     which both `saveSchema` and `removeSchema` null out.
   - Where a store holds both a listing and the entity map that listing's ids resolve through, a write that changes
     membership must keep the two in lockstep at every synchronous point: no id the listing still names may be missing
     from the map. Current instance: `SubjectStore.deleteSubject` drops the map entry only once `pageSubjects` no
     longer lists the id, so no render sees a name `getSubject` cannot resolve.
3. **Asynchronous write-backs are epoch-guarded.** Every store write that follows a server read snapshots that store's
   `mutationEpoch` beforehand and discards the write when the epoch moved, because a response that raced a mutation
   may predate that mutation. Page seeding (`StoreStateLoader`), `SubjectStore.loadPageSubjects`,
   `getOrFetchSubject`'s miss path and the Schema bundled with a Subject write all follow this rule. A write into more
   than one store snapshots and guards each store's epoch separately. The epoch is store-wide by design: discarding an
   unrelated in-flight fetch costs one refetch and keeps the rule simple.

The epoch guard covers store write-backs only. Ordering component-local async state — a debounced validation run, an
editor's schema fetch — remains each component's own job: the `requestSequence` counter in `useSubjectValidation`,
mirrored in the creator and lookup components, applies the same discard-the-stale-response idea to local refs instead
of store state.

Store action names follow a fixed vocabulary. The `fetchX` entity reads are gone, since reads for editing belong to the
repositories. `removeX` is a local registry removal after another code path already deleted the entity server-side:
Schema and Layout deletion goes through the shared `DeletePageDialog`, so `removeSchema`/`removeLayout` tell the store
its copy is gone. `fetchAllSchemaSummaries` returns its data directly; nothing retains it.

Reloading the page remains legitimate where server-rendered output must reflect a change: no store update can refresh
views the wiki rendered. The Subject-creation flow reloads for this reason.

## Consequences

* Fetching a Schema, Layout or Subject to edit is the caller's job, not a store action. Extensions read through the
  repositories and pass the result down; RedHerb demonstrates it.
* Schema pickers fetch summaries per mount cohort. A few requests per dialog open — bounded by the scale targets in
  [ADR 29](029-scalability-targets.md) — buy freshness: Schemas created or deleted elsewhere appear without a reload.
* New store actions must follow the three rules above; the store test suites show the shape.

## Alternatives Considered

### Making the cache correct

Epoch-guarding every read and invalidating on every mutation while keeping the `getOrFetch` semantics. Rejected because
it hand-rolls the hard parts of a query library to protect cache hits that stay session-stale anyway, and every future
action would have to remember the discipline.

### Stores as the single data-access façade

Routing editing reads through store fetch actions, so all server reads have one entry point. Rejected on two counts: it
puts values only one component needs into shared state — every fetch would then need an epoch guard, and callers would
have to handle a resolved fetch that wrote nothing to the registry — and [ADR 16](016-frontend-state-management.md)
already decided editing UIs do not use the stores.

### Adopting a query library (TanStack Query or Pinia Colada)

Such a library solves invalidation, deduplication and response ordering declaratively. Rejected because it would add
another shared singleton that NeoWiki must provide to frontend extensions, for request volumes that the scale targets
in [ADR 29](029-scalability-targets.md) do not justify. Revisit this choice when a measured request-volume problem
appears; the migration path is the library, not more hand-rolled cache machinery.
