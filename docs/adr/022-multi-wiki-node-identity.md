# Multi-wiki Graph Node Identity

Date: 2026-06-22

Status: Accepted (2026-07-19)

## Context

NeoWiki projects its data into a graph ([ADR 3](003-neo4j-as-graph-database.md),
[ADR 19](019-graph-database-architecture.md)). In a **wiki farm** (i.e., BlueSpice Galaxy), several wikis share **one** graph
so that metadata can be queried across them.

A wiki must be able to **restrict graph queries to its own data**.

MediaWiki page ids are per-wiki sequential. In a shared graph, `Page` nodes from different wikis therefore collide on
`id` and overwrite each other. The "`Page.id` is unique" constraint ([graph-model](../api/graph-model.md)) does
not hold across wikis.

The broader Subject Sources work introduces a general `(source, localId)` identity for all Subjects. That is larger and
still being designed; this ADR records the first increment and makes it forward-compatible with that model.

## Decision

In the shared graph, node identity is **scoped per wiki**:

- Every node NeoWiki writes (page and subject nodes) carries a **`wiki_id`** property: the **MediaWiki Wiki ID**
  (wiki name + table prefix, per [Manual:Wiki ID](https://www.mediawiki.org/wiki/Manual:Wiki_ID)).
- **Page-node identity becomes wiki-scoped**: the `MERGE` key and uniqueness constraint change from `id` to
  `(wiki_id, id)`. Page-node lookups in existing queries are updated to match.
- **Subject ids stay bare nanoids** — cross-wiki collision is extremely unlikely by construction
  ([ADR 14](014-improved-id-format.md)); they gain the `wiki_id` property for filtering. Per-subject-id namespacing is
  deferred.
- **Single-wiki installs are unchanged** in behaviour (`wiki_id` is present, but nothing else changes).
- Consumers restrict queries to a wiki with `WHERE n.wiki_id = '<id>'`. Server-side ACL-based query filtering is out of
  scope here.

The `wiki_id` is the same identifier the eventual Subject Sources identity model (`(source, localId)`, with
Source-plugin-provided prefixing) will use, so this is its forward-compatible first increment.

## Forward compatibility

Concretely, when the full model lands:

- A local Subject's `(source, localId)` is just `(wiki_id, nanoid)` — both already stored here — so it is *derived*,
  not migrated. Page nodes keyed `(wiki_id, id)` are already the final shape.
- The subject node keeps the **bare nanoid** as its stored id, with `wiki_id` as a property; `(source, localId)` is the
  *reference* form (relation targets, view ids, fetch), resolved per source. Non-local sources are added later as their
  own nodes — they change nothing stored here.

This holds as long as the full model (a) uses the MW Wiki ID as the local source key, and (b) keeps the subject node's
stored id the bare nanoid (the qualified id being a derived reference form, not the stored id). Changing either is a
re-key — cheap while not in production, but a real change the broader Subject Sources ADR should ratify.

## Consequences

- Fixes cross-wiki `Page` node overwrites and enables per-wiki query filtering for the farm.
- Revises the "`Page.id` is unique" statement in [graph-model](../api/graph-model.md): uniqueness becomes
  per `(wiki_id, id)`. That reference doc is updated when this is implemented.
- The change ripples into existing queries that look up a page node by id: they must include `wiki_id`.
- The full namespacing model (subject-id prefixing via Source plugins) remains deferred to the Subject Sources work.

## Alternatives Considered

- **Full `(source, localId)` namespacing now**, applied to subject ids too. More work; requires the whole Subject
  Sources design to settle first. Deferred — HalloWelt is fine with the interim, and this increment is forward-compatible.
- **A separate graph per wiki** (no shared graph). Loses cross-wiki querying, which is the farm's reason for a graph
  backend. Rejected.

## Related

- [ADR 19: Graph Database Architecture](019-graph-database-architecture.md)
- [ADR 14: Improved Id Format](014-improved-id-format.md)
- [Graph Model reference](../api/graph-model.md)
- [Subject Sources planning doc](../planning/SubjectSources.md); issue
  [#905](https://github.com/ProfessionalWiki/NeoWiki/issues/905).
