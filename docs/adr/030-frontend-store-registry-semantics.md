# Frontend Stores Are Registries, Not Caches

Date: 2026-07-29

Status: Accepted

Extends [ADR 16](016-frontend-state-management.md), which settles which components read the
stores but is silent on fetching, caching and invalidation.

## Context

The frontend keeps server state in Pinia stores (`SchemaStore`, `LayoutStore`, `SubjectStore`).
Each store grew the same trio of actions: `fetchX`, `getOrFetchX` and `saveX`. The trio treated
the store as a cache: reads consulted stored values to avoid fetches, and saves wrote the
client's object through. An invalidation model was never decided
([#1211](https://github.com/ProfessionalWiki/NeoWiki/issues/1211)). Reads assigned awaited
results to shared state unconditionally
([#1177](https://github.com/ProfessionalWiki/NeoWiki/issues/1177)), writes invalidated only on
their own success path, and deletes invalidated nothing. The components already routed around
the cache: editing flows fetched fresh data before opening, and two flows reloaded the whole
page as their invalidation primitive.

A client-side cache can only be consistent with the session's own writes. Edits from other tabs
and other users stay invisible until the client fetches again. Query libraries such as TanStack
Query and Pinia Colada default to treating cached data as immediately stale for the same
reason.

## Decision

Stores are page-scoped registries of server state, not caches. A registry holds the page
payload seeded at load, the session's own acknowledged writes, and explicitly fetched values.

1. **Reads are explicit.** `fetchX` always calls the backend and records the result. `getX`
   reads the registry synchronously. The store API offers no fetch-on-miss reads, with two
   exceptions:
   - `SubjectStore.getOrFetchSubject` resolves display labels over the deliberately seeded
     page payload. Referenced Subjects arrive bundled with the page payload precisely so that
     N relation displays do not fire N requests.
   - `SchemaStore.fetchAllSchemaSummaries` lets concurrent callers share one in-flight
     pagination. Results are not retained, so every new caller cohort fetches fresh data.
2. **Mutations keep the registry consistent with the session's own writes, on every path.**
   Each store has a `mutationEpoch` counter that is bumped when the backend acknowledges a
   write. Saves write through, deletes remove the entity from all registry state, and
   invalidation that must survive a rejected save runs in a `finally` block. A mutation must
   also detach any derived in-flight request slot its store holds, so the next caller starts a
   fresh request instead of joining one that predates the write — `SchemaStore`'s
   `summariesRequest` slot (behind `fetchAllSchemaSummaries`) is the current instance: both
   `saveSchema` and `removeSchema` null it out. Where a store holds both a listing and the
   entity map that listing's ids resolve through, a write that changes membership must keep the
   two in lockstep at every synchronous point: no id the listing still names may be missing
   from the map. `SubjectStore.deleteSubject`/`pageSubjects` is the current instance — the map
   entry is dropped only once `pageSubjects` itself no longer lists the id, so a render caught
   between the two writes never sees a name `getSubject` cannot resolve.
3. **Asynchronous write-backs are epoch-guarded.** Every fetch that writes into a store
   snapshots that store's `mutationEpoch` before awaiting and discards the write into it when
   the epoch moved, because a response that raced a mutation may predate that mutation. A fetch
   that writes into more than one store snapshots and guards each store's epoch separately —
   `SubjectStore.loadPageSubjects` and `StoreStateLoader.loadForSubject` both snapshot the
   Subject and Schema stores' epochs up front and apply each write only if its own store's
   epoch still matches by the time the response lands. The epoch is store-wide by design:
   discarding an unrelated in-flight fetch costs one refetch and keeps the rule simple.

The epoch guard covers store write-backs only; ordering component-local async state — a
debounced validation run, a picker's in-flight request — remains each component's own job. The
`requestSequence` counter in `useSubjectValidation` (mirrored in `SchemaCreator.vue`,
`LayoutCreator.vue` and `SubjectLookup.vue`) applies the same discard-the-stale-response idea to
local refs instead of store state.

Store action names follow a fixed vocabulary. `fetchX` hits the network, returns nothing, and
records into the registry. `getX` reads the registry synchronously. `saveX` and `deleteSubject`
are the server mutations. `removeX` is a local registry removal after a delete some other code
path already executed server-side — Schema and Layout deletion goes through the shared
`DeletePageDialog`, not a store action, so `removeSchema`/`removeLayout` exist to tell the store
its copy is now gone. `fetchAllSchemaSummaries` is the deliberate exception that returns data
directly: its results are not retained in the registry, so returning them is the only way
callers see them at all.

Editing UIs always open on freshly fetched data. That obligation sits with the code that opens
the editor.

Reloading the page remains legitimate where server-rendered output must reflect a change,
because no store update can refresh views that the wiki rendered. The Subject-creation flow
reloads for this reason.

## Consequences

* The public store API offers no fetch-on-miss reads for Schemas or Layouts
  (`getOrFetchSchema` and `getOrFetchLayout` were removed). Extensions fetch explicitly and
  read the registry; the RedHerb example extension demonstrates the pattern.
* Schema pickers fetch summaries per mount cohort. A few requests per dialog open, bounded by
  the scale targets in [ADR 29](029-scalability-targets.md), buy freshness: Schemas created or
  deleted elsewhere appear without a reload.
* New store actions must follow the three rules above. The store test suites demonstrate the
  expected shape.

## Alternatives Considered

### Making the cache correct

Epoch-guarding every read and invalidating on every mutation while keeping the `getOrFetch`
semantics. Rejected because it hand-rolls the hard parts of a query library to protect cache
hits that stay session-stale anyway, and because every future action would have to remember
the discipline.

### Adopting a query library (TanStack Query or Pinia Colada)

Such a library solves invalidation, deduplication and response ordering declaratively.
Rejected because it would add another shared singleton that the frontend-extension
externalisation contract must cover, for request volumes that the scale targets in
[ADR 29](029-scalability-targets.md) do not justify. Revisit this choice when a measured
request-volume problem appears; the migration path is the library, not more hand-rolled cache
machinery.
