# Access Control

Date: 2026-07-22

Status: Accepted (2026-09-02)

## Context

NeoWiki data leaves the system through the REST API (lookups, [caller-supplied Cypher/SPARQL
queries](../api/query-api.md), RDF export), through parse-time accessors (parser functions and Lua), and through
projection into graph and SPARQL stores and RDF dumps.
Earlier ADRs settled individual pieces: Neo4j is reachable only through the backend ([ADR 13](013-restrict-neo4j-access.md)),
SPARQL stores may be exposed directly ([ADR 19](019-graph-database-architecture.md)), and graph nodes carry per-wiki
identity ([ADR 22](022-multi-wiki-node-identity.md)). This ADR
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
- **Per-subject ACLs are a non-goal.** There is no access control finer than the page: no recorded use case needs
  it, deployments separate sensitive data by page or wiki, and sub-page ACLs would complicate every read surface.
  Revisit only with a concrete use case.
- **A denied read is indistinguishable from absent data.** Read surfaces gated on the page's `read` permission answer
  with a `null`, an empty list, or a `404` — never a `403` — and must not reveal existence through side channels such
  as counts ([#1062](https://github.com/ProfessionalWiki/NeoWiki/issues/1062)). Write denials answer `403`, and must
  equally not reveal whether an unreadable page exists
  ([#1061](https://github.com/ProfessionalWiki/NeoWiki/issues/1061)). [rest-api.md](../api/rest-api.md) documents this
  per endpoint.
- **Page-attributable results are filtered per row.** Read surfaces whose results are traceable to an owning page
  resolve each row's page and drop rows the caller may not read. Because this costs one permission check per row,
  such surfaces must bound their result sizes.
- **Graph projections carry scoping keys, not ACL state.** `wiki_id` and `namespaceId` let query authors scope
  queries ([ADR 22](022-multi-wiki-node-identity.md), [graph-model](../api/graph-model.md)). User groups and page
  restrictions are never projected, so nothing in a store goes stale when permissions change.
- **Raw query surfaces have whole-store read semantics.** A raw query surface executes a caller-supplied Cypher or
  SPARQL query; its result rows are not attributable to pages and are not trimmed. The REST query endpoints are gated
  by the wiki-level `neowiki-query` right; granting that right gives read access to everything the wiki projects into
  the store. Exposing a store directly (which ADR 19 allows for SPARQL) is a different surface: see the projection
  decision below.
- **Parse-time reads run as the user the page is parsed for, and their output is cached per access class.**
  The parser functions and the Lua library read as the user recorded in the parser options: the viewer on a
  page view, or the anonymous user for the save-time parse, the job queue, and Parsoid renders. They are gated
  like the REST endpoints: page `read` for page-attributable reads, `neowiki-query` for raw queries. Query
  limits use the default tier regardless of user. Output that depends on such a read is parser-cached under
  the parsing user's access class, derived from the user's effective groups and the wiki-level `read` and
  `neowiki-query` grants, so a cached copy is shared only within one class. The class is a proxy for the
  permission hooks: exact wherever page access follows group membership, wrong for hooks that grant per
  user (the revision-deletion rights included), so wikis with such hooks must run without a parser cache.
  `{{#view}}` needs no class: it emits a marker at parse time, and the Subject behind it is fetched per
  viewer over REST, under that viewer's permissions.
- **Raw queries will support server-side filter injection.** A deployment can register scoping predicates (such as
  restricting to the current wiki) that core applies to every caller-supplied query, so scoping is enforced rather
  than left to each caller.
- **Projections and dumps are generated without permission checks.** We may add such support later, enabling a
  public projection (a public store with a public query endpoint, holding no restricted content) alongside a private
  one that includes restricted content.

## Open decisions

These decisions remain open at acceptance; each is deferred to the tracking issue named with it.

- **Cross-wiki subject display.** Rendering a subject from another wiki goes through REST, not Cypher, so query-side
  scoping does not cover it. Deferred to [#1341](https://github.com/ProfessionalWiki/NeoWiki/issues/1341): the
  check and the degradation behavior when the schema or subject is not accessible. Relates to
  [ADR 23](023-subject-sources.md).
- **Default grant of `neowiki-query`.** The right is granted to `*` by default. Deferred to
  [#1342](https://github.com/ProfessionalWiki/NeoWiki/issues/1342): whether the default changes, and how deployments
  with restricted content are expected to configure it.

## Out of scope

- Authentication (single sign-on, federated identity): a MediaWiki/deployment concern.
- Rights and licensing metadata and provenance recording (ECHOLOT T3.4): data features, not access control.

## Consequences

- Every new surface that exposes NeoWiki data must be classified: page-attributable (per-row gate), raw query
  (whole-store semantics), projection/dump (no permission checks), or parse-time (parsing user's authority,
  output keyed by access class). There is no unclassified option.
- A page that reads Subjects or runs queries at parse time holds one parser-cache entry per access class
  among its viewers. Current-revision views of other pages are unaffected; old-revision views are keyed per
  class wiki-wide, because core's revision-output cache keys on every cache-varying option rather than the
  ones a page used. Saving such a page parses it twice when the editor is logged in: once canonically, once
  for the editor's class, as pages using `{{int:}}` already do for editors with a non-default interface
  language.
- Restricting content does not invalidate what is already cached: the class describes the reader, not the
  page, so a page that embeds newly restricted data keeps serving it to its class until the page is edited
  or purged, or `$wgParserCacheExpireTime` elapses.
- Data derived from the canonical parse is computed as the anonymous user: the categories, page properties and
  links tables written on save and by jobs, and the categories and parser properties of the Page node in graph
  projections. On a wiki where anonymous users cannot read, a category or page property derived from a
  parse-time read is therefore never set. A designated reader for canonical parses would lift this; it is not
  decided.
- Restricting a page does not remove its data from stores; it changes what the backend returns.
- The filter-injection extension point must be designed and implemented for farms like BlueSpice Galaxy.
- Dumps and projections contain restricted content (unless it is omitted via a non-permission mechanism such as the
  Mappings), so they must not be exposed to readers who may not access that content.

## Alternatives Considered

- **Enforce inside the store** (per-user store accounts, store-level ACLs): Enterprise-only in Neo4j, unavailable in
  QLever, and unable to express hook-based MediaWiki permissions. Rejected, consistent with ADR 13.
- **Project ACL state into the graph for pre-query trimming** (user groups, restriction markers): re-implements an
  open set of permission hooks as data and goes stale, because permission changes produce no revision to sync on.
- **Evaluate parse-time reads as a fixed anonymous authority**: user-independent output by construction, but
  every privileged reader sees less than they may read, and on a private wiki the functions show nothing.
- **Cache the superset and trim per viewer after the cache**, as core does for section edit links: sound for opaque
  display fragments, but the cache then holds restricted data for every consumer that bypasses the output pipeline,
  and an ungated parse leaks through categories, page properties and Lua control flow, which no HTML trim retracts.
- **Leave the cache alone and require wikis with restricted content to disable it**: no machinery, but nothing
  detects a violation, and a privileged first parse silently caches restricted values for everyone.

## Related

- [ADR 13: Restrict Neo4j Access](013-restrict-neo4j-access.md), [ADR 19: Graph Database
  Architecture](019-graph-database-architecture.md), [ADR 22: Multi-wiki Graph Node
  Identity](022-multi-wiki-node-identity.md), [ADR 23: Subject Sources](023-subject-sources.md)
- [rest-api.md Permissions](../api/rest-api.md), [query-api.md Permissions](../api/query-api.md),
  [graph-model](../api/graph-model.md)
- Issues: [#1046](https://github.com/ProfessionalWiki/NeoWiki/issues/1046) (per-page read enforcement),
  [#350](https://github.com/ProfessionalWiki/NeoWiki/issues/350) (slot-level access)
