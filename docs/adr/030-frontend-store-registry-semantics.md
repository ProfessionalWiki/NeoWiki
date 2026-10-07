# Frontend Stores Are Registries, Not Caches

Date: 2026-07-31

Status: Accepted (2026-09-29). Amends [ADR 16](016-frontend-state-management.md).

## Context

The Pinia stores (`SubjectStore`, `SchemaStore`, `LayoutStore`) had grown into caches: reads consulted stored values
to avoid fetches, saves wrote the client's object through, and nothing decided when a value was stale. Components
already routed around them: editing flows fetched fresh data before opening, and two flows reloaded the whole page
to refresh state.

A client-side cache can only be consistent with the session's own writes. Edits by other users and tabs stay
invisible until the client fetches again.

## Decision

A store is a page-scoped registry of server state: the page payload seeded at load plus the session's acknowledged
writes since. Each store keeps a mutation epoch, a count of those writes.

1. Reads do not fetch. Display UIs read the registry with `getX`. Editing UIs open on data fetched through the
   repositories and passed in as props, and use the stores only to mutate and validate; a display catches up on
   commit, not on open. Three reads are the exceptions: `getOrFetchSubject` fetches a Subject the page payload did
   not bundle, on the miss; `fetchAllSchemaSummaries` and `schemaNameExists` fetch every time and retain nothing,
   concurrent summary callers sharing one request in flight.
2. Mutations keep the registry consistent with the session's own writes, on every path. A save records what the
   server committed: the response's entity where the server returns one (a Subject, with its Schema), else the
   object saved. A delete removes the entity, and a listing never names an id the registry cannot resolve. A shared
   request in flight is dropped after any write, even a rejected one, since a lost reply may have committed; the
   next caller starts a new request.
3. Write-backs after a server read are guarded by the epoch. The write-back snapshots the epoch before its request
   and is discarded if the epoch moved, since the response may predate the write; a write-back into several stores
   guards each store's epoch. The epoch is store-wide on purpose: a discarded unrelated fetch costs one refetch and
   keeps the rule simple. The guard covers store write-backs only; a component orders its own async state the same
   way, on local refs.

Reloading the page stays legitimate where server-rendered output must reflect a change.

## Consequences

* Extensions fetch what they edit through the repositories; RedHerb shows how.
* Schema pickers fetch summaries per open, a few requests per dialog, so Schemas created or deleted elsewhere appear
  without a reload.

## Alternatives considered

* Making the cache correct, with epoch guards on every read and invalidation on every mutation: hand-rolls the hard
  parts of a query library to protect hits that stay session-stale anyway.
* Routing editing reads through the stores as the single data-access façade: puts values one component needs into
  shared state, where every fetch needs an epoch guard and a caller must handle a fetch that resolved but wrote
  nothing; ADR 16 already keeps editing UIs off store reads.
* A query library such as TanStack Query or Pinia Colada: one more shared singleton NeoWiki must provide to frontend
  extensions, for request volumes the targets in [ADR 29](029-scalability-targets.md) do not justify. Should
  measured volume outgrow them, adopt the library rather than more cache machinery.
