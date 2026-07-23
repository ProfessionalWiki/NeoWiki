# Access Control

Date: 2026-07-22

Status: Draft

## Context

NeoWiki data leaves the system through several kinds of surface: REST lookup endpoints, the REST query endpoints that
execute a caller-supplied Cypher or SPARQL query ([query-api.md](../api/query-api.md)), parse-time accessors (parser
functions and Lua), RDF export, and projection into graph and SPARQL stores.
Earlier ADRs settled individual pieces: Neo4j is reachable only through the backend ([ADR 13](013-restrict-neo4j-access.md)),
SPARQL stores may be exposed directly ([ADR 19](019-graph-database-architecture.md)), and graph nodes carry per-wiki
identity ([ADR 22](022-multi-wiki-node-identity.md), which left ACL-based query filtering out of scope). This ADR
records the overall access-control model those pieces belong to, and lists the decisions that are still open.

Constraints the model rests on:

- In MediaWiki, whether a user may read a page is a per-title decision made by permission hooks: private-wiki mode
  and ACL extensions (page- or namespace-scoped) plug into `Authority`. The hook evaluation *is* the permission;
  there is no static attribute that could be copied elsewhere and stay correct.
- The stores NeoWiki projects into cannot enforce per-user reads: Neo4j's fine-grained access control is
  Enterprise-only, and QLever has a single server-wide access token. Whatever is in a store is readable by anyone who
  can query that store.
- MediaWiki's parser cache is shared across users. Parse output that varies by user either leaks into the shared
  cache or fragments it.
- In a wiki farm (BlueSpice Galaxy), several wikis share one graph, and access is controlled per wiki.

## Decision

- **MediaWiki is the sole permission authority.** Every access decision runs in PHP against the caller's `Authority`;
  no enforcement is delegated to a store.
- **The page is the unit of access control.** Every NeoWiki entity is governed by the page that stores it (Subjects,
  Schemas, Layouts, Mappings): reading requires the page's `read` permission, checked at full rigor
  (`authorizeRead`, so ACL-extension hooks run); writing requires `edit`. NeoWiki defines no ACL model of its own.
- **A denied read is indistinguishable from absent data.** Gated read surfaces answer with a `null`, an empty list, or
  a `404` — never a `403` — and must not reveal existence through side channels such as counts
  ([#1062](https://github.com/ProfessionalWiki/NeoWiki/issues/1062)). Write denials answer `403`, and must equally
  not reveal whether an unreadable page exists ([#1061](https://github.com/ProfessionalWiki/NeoWiki/issues/1061)).
  [rest-api.md](../api/rest-api.md) documents this per endpoint.
- **Page-attributable results are filtered per row.** Read surfaces whose results are traceable to an owning page
  resolve each row's page and drop rows the caller may not read. Because this costs one permission check per row,
  such surfaces must bound their result sizes.
- **Graph projections carry scoping keys, not ACL state.** `wiki_id` and `namespaceId` let query authors scope
  queries ([ADR 22](022-multi-wiki-node-identity.md), [graph-model](../api/graph-model.md)). User groups and page
  restrictions are never projected: the keys are revision-derived, so nothing in a store goes stale when permissions
  change.
- **Raw query surfaces have whole-store read semantics.** A raw query surface executes a caller-supplied Cypher or
  SPARQL query; its result rows are not attributable to pages and are not trimmed. The REST query endpoints are gated
  by the wiki-level `neowiki-query` right
  ([query-api.md](../api/query-api.md)); granting that right grants read access to everything the wiki projects into
  the store. Exposing a store directly (which ADR 19 allows for SPARQL) is a different surface: see "Projection as
  publication" below.

## Open decisions

- **Parse-time read semantics.** Today the parse path is inconsistent: Schema/Mapping lookups are gated per user but
  their output is parser-cached user-agnostically ([#1063](https://github.com/ProfessionalWiki/NeoWiki/issues/1063));
  subject accessors (`{{#neowiki_value}}` and the `nw` data accessors) check only revision-deletion visibility, not
  page `read`; `{{#cypher_raw}}` and `nw.query` check nothing
  ([#1059](https://github.com/ProfessionalWiki/NeoWiki/issues/1059)). `{{#view}}` is the leak-free pattern: a
  placeholder rendered at parse time, data fetched per user over REST. **TODO:** decide the parse-path rule and what
  it means for each surface.
- **Server-side query filter injection.** Farm deployments add scoping predicates in their own query layer; core
  offers no extension point for injecting them server-side. **TODO:** design the seam.
- **Cross-wiki subject display.** Rendering a subject from another wiki goes through REST, not Cypher, so query-side
  scoping does not cover it. **TODO:** decide the check and the degradation behavior when the schema or subject is
  not accessible. Relates to [ADR 23](023-subject-sources.md).
- **Default grant of `neowiki-query`.** The right is granted to `*` by default. **TODO:** decide whether the default
  changes, and how deployments with restricted content are expected to configure it.
- **Projection as publication.** Directly exposed SPARQL endpoints (ADR 19) and RDF dumps bypass per-user checks.
  **TODO:** decide whether projection/dump content is limited to what the public reader may read.
- **TODO:** confirm as a non-goal: per-subject ACLs independent of the owning page.

## Out of scope

- Authentication (single sign-on, federated identity): a MediaWiki/deployment concern.
- Rights and licensing metadata and provenance recording (ECHOLOT T3.4): data features, not access control.

## Consequences

- Every new surface that exposes NeoWiki data must be classified: page-attributable (per-row gate), raw query
  (whole-store semantics), projection/dump (semantics pending above), or parse-time (semantics pending above).
  There is no unclassified option.
- Restricting a page does not remove its data from stores; it changes what the backend returns.

## Alternatives Considered

- **Enforce inside the store** (per-user store accounts, store-level ACLs): Enterprise-only in Neo4j, unavailable in
  QLever, and unable to express hook-based MediaWiki permissions. Rejected, consistent with ADR 13.
- **Project ACL state into the graph for pre-query trimming** (user groups, restriction markers): re-implements an
  open set of permission hooks as data and goes stale, because permission changes produce no revision to sync on.
  Not pursued.

## Related

- [ADR 13: Restrict Neo4j Access](013-restrict-neo4j-access.md), [ADR 19: Graph Database
  Architecture](019-graph-database-architecture.md), [ADR 22: Multi-wiki Graph Node
  Identity](022-multi-wiki-node-identity.md), [ADR 23: Subject Sources](023-subject-sources.md)
- [rest-api.md Permissions](../api/rest-api.md), [query-api.md Permissions](../api/query-api.md),
  [graph-model](../api/graph-model.md)
- Issues: [#1046](https://github.com/ProfessionalWiki/NeoWiki/issues/1046) (per-page read enforcement),
  [#350](https://github.com/ProfessionalWiki/NeoWiki/issues/350) (slot-level access)
