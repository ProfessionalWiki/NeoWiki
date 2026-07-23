---
title: Query API
order: 5
---
# Query API

`POST /neowiki/v0/query/cypher` runs a read-only Cypher query against the configured Neo4j backend and returns
results as a structured JSON envelope. The route exists only when a Neo4j backend is configured; on a wiki without
one it is absent and does not appear in the OpenAPI spec.

## Stability

Pre-1.0. The endpoint, request shape, response envelope, and error contract may change without notice before 1.0;
not yet stable for third-party integrations.

## Discovery flow

To write a query you need the graph's labels, properties, and relationships. Learn them from:

1. **`GET /neowiki/v0/schemas`** — the available Schema names (e.g. `Person`, `Company`, `Document`).
2. **`GET /neowiki/v0/schema/{name}`** — one Schema's Property Definitions: the names, types, and constraints of the
   properties carried by its `:Subject:SchemaName` nodes.
3. **`GET /neowiki/v0/subject/{id}`** — a live Subject, to see the actual stored data shape (optional).
4. **[Graph Model](graph-model.md)** — the full Neo4j label, relationship, and node-property structure; required
   for anything beyond simple `MATCH (s:Subject)` patterns.

## Endpoint contract

The POST is read-only and needs no CSRF/edit token. Access is governed solely by the `neowiki-query` right; a caller
with that right on a wiki where it is not granted to anonymous users authenticates with normal MediaWiki session
credentials.

### Request

```
POST /rest.php/neowiki/v0/query/cypher
Content-Type: application/json
```

```json
{
    "cypher": "MATCH (s:Subject:Person) WHERE s.`Birth year` > $minYear RETURN s.name AS name, s.`Birth year` AS year",
    "parameters": { "minYear": 2000 }
}
```

| Field | Type | Required | Description |
|---|---|---|---|
| `cypher` | string | Yes | A read-only Cypher query. Single statement. Queries with write keywords cause a `writeQueryRejected` error. |
| `parameters` | object | No | Parameter name → value map. Reference as `$name` in the query. Defaults to `{}`. |

Property names containing spaces must be backtick-escaped in Cypher, e.g. `` s.`Birth year` ``. Pass values via
`parameters` rather than concatenating them into the query string — parameters reach the driver injection-safe.

### Successful response (200)

```json
{
    "columns": ["name", "year"],
    "rows": [
        { "name": "Alice Fontaine", "year": 2001 },
        { "name": "Ben Markov",     "year": 2003 }
    ],
    "truncated": false,
    "resultCount": 2,
    "durationMs": 14
}
```

| Field | Type | Description |
|---|---|---|
| `columns` | array of string | RETURN aliases in declaration order. Row object key order is not guaranteed; use this for column order. |
| `rows` | array of object | Each row keyed by RETURN alias. |
| `truncated` | boolean | `true` when the result was cut at `maxRows`; narrow the query or add `LIMIT`. |
| `resultCount` | integer | Number of rows in `rows`. |
| `durationMs` | integer | Server-measured query execution time in milliseconds, excluding network round-trip. |

#### Cell normalization

Scalar Cypher values (strings, integers, floats, booleans, `null`) pass through as-is. Complex types surface as:

| Cypher type | JSON shape |
|---|---|
| Node | `{ "id": ..., "labels": [...], "properties": {...} }` |
| Relationship | `{ "id": ..., "type": "...", "startNodeId": ..., "endNodeId": ..., "properties": {...} }` |
| UnboundRelationship | `{ "id": ..., "type": "...", "properties": {...} }` (no start/end node ids; appears in undirected pattern matches) |
| Path | `{ "nodes": [...], "relationships": [...] }` |
| `date` | ISO 8601 date string, e.g. `"2023-10-01"` |
| `datetime` / zoned `time` | ISO 8601 string with a whole-minute offset, e.g. `"2023-09-13T14:22:23+00:00"` / `"14:22:23+02:00"` |
| `localdatetime` / `localtime` | ISO 8601 string without offset, e.g. `"2023-09-13T14:22:23"` / `"09:30:00"` |
| `duration` | `{ "months": ..., "days": ..., "seconds": ..., "nanoseconds": ... }` |
| `point` | `{ "x": ..., "y": ..., "crs": "...", "srid": ... }` (plus `"z"` for 3D points) |

### Error response

```json
{
    "errorType": "queryTimeout",
    "message": "Query exceeded the 30 second timeout."
}
```

| `errorType` | HTTP | When |
|---|---|---|
| `emptyQuery` | 400 | `cypher` is empty or contains only whitespace. |
| `parameterMissing` | 400 | Query references `$x` but `parameters` contains no key `x`. |
| `cypherSyntaxError` | 400 | Neo4j reports a parse or syntax error for the query. |
| `writeQueryRejected` | 422 | Query contains a write keyword or otherwise fails the read-only validator. |
| `queryTimeout` | 408 | Query execution exceeded `timeoutSeconds` for the caller's tier. |
| `rateLimitExceeded` | 429 | Request frequency exceeded the `neowiki-query` rate limit. |
| `permissionDenied` | 403 | Caller lacks the `neowiki-query` right. |
| `backendUnavailable` | 503 | No graph backend is reachable. |
| `internalError` | 500 | Anything else. |

`errorType` strings are stable across releases; branch on them, not on `message`. The `message` is English, may change
between releases, and is meant for logging, not display. Localized messages are rendered only by the wikitext surfaces
— `{{#cypher_raw}}` and `nw.query()` — from the same `errorType` classification.

## Limits and tiers

Two resource tiers control per-query timeout and row cap:

| Tier | Who | Timeout | Row cap |
|---|---|---|---|
| `default` | Any user with `neowiki-query` | 30 s | 5,000 rows |
| `expensive` | Any user with the core `apihighlimits` right | 300 s | 50,000 rows |

The tier is selected automatically from the caller's rights; it cannot be requested per call.

### Rate limits

Request frequency is controlled via MediaWiki's standard `$wgRateLimits` mechanism, keyed as `neowiki-query`.
Defaults shipped by NeoWiki:

| Caller type | Default limit |
|---|---|
| Anonymous | 10 requests / 60 s |
| Logged-in user | 60 requests / 60 s |
| Bot | 1000 requests / 60 s |
| Sysop / Bureaucrat | Unlimited (via core `noratelimit` right) |

## Permissions

| Right | Default groups | Purpose |
|---|---|---|
| `neowiki-query` | `*` (everyone, including anonymous visitors) | Required to call the query endpoint. |
| `apihighlimits` | `bot`, `sysop` (core defaults) | Grants the `expensive` resource tier. |

## SPARQL query endpoint

`POST /neowiki/v0/query/sparql` runs a read-only SPARQL query against the first configured
[SPARQL store](../operations/installation.md#optional-sparql-graph-stores) and returns its results. Like the Cypher
endpoint, it exists only when a store is configured; otherwise the route is absent and does not appear in the OpenAPI
spec.

For the RDF vocabulary (predicates, graph shape) to query, see [RDF Export](../rdf/rdf-export.md) and
[Ontology Mapping](../rdf/ontology-mapping.md).

### Request

```
POST /rest.php/neowiki/v0/query/sparql
Content-Type: application/json
```

```json
{
    "query": "SELECT ?label WHERE { ?s <http://www.w3.org/2000/01/rdf-schema#label> ?label } LIMIT 10"
}
```

| Field | Type | Required | Description |
|---|---|---|---|
| `query` | string | Yes | A read-only SPARQL 1.1 `SELECT` or `ASK` query. |

`CONSTRUCT` and `DESCRIBE` are not supported.

### Successful response (200)

The body is the W3C [`application/sparql-results+json`](https://www.w3.org/TR/sparql11-results-json/) document from the
store, **unmodified** — the standard `head` / `results` structure (or `boolean` for an `ASK`), plus any store-specific
extras (e.g. QLever's `meta`). NeoWiki wraps no envelope of its own around it.

```json
{
    "head": { "vars": ["label"] },
    "results": { "bindings": [ { "label": { "type": "literal", "value": "Johann Sebastian Bach" } } ] }
}
```

### Error response

Same shape as the Cypher endpoint (`{ "errorType", "message" }`); branch on `errorType`, not `message`.

| `errorType` | HTTP | When |
|---|---|---|
| `emptyQuery` | 400 | `query` is empty or contains only whitespace. |
| `sparqlSyntaxError` | 400 | The store rejected the query (a 4xx response, most commonly a SPARQL syntax error). The store's own detail is relayed in `message`. |
| `sparqlStoreUnavailable` | 503 | The store could not be reached or failed to serve the query (a 5xx response or a transport error). |
| `internalError` | 500 | The store returned a 2xx response whose body is not a JSON results document, or any other unexpected failure. |
| `rateLimitExceeded` | 429 | Request frequency exceeded the `neowiki-query` rate limit. |
| `permissionDenied` | 403 | Caller lacks the `neowiki-query` right. |

### Limits

Reuses the Cypher endpoint's `$wgNeoWikiQueryLimits` tiers, `neowiki-query` right, and rate limits. The per-tier
timeout applies (as the HTTP client timeout); the `maxRows` cap does not, so bound result size with `LIMIT` in the
query. There is no distinct timeout `errorType`: a timed-out query surfaces as `sparqlStoreUnavailable` (503), the
same as an unreachable store.

## Related

- [REST API](rest-api.md) — OpenAPI spec and how it is generated; how to browse it with Swagger UI.
- [Lua API](../authoring/lua-api.md) — `nw.query()` for the same Cypher surface from wikitext templates, and
  `nw.sparqlQuery()` for the SPARQL surface.
- [Parser Functions](../authoring/parser-functions.md) — `{{#cypher_raw}}` and `{{#sparql_raw}}` for inline query
  output in wikitext.
- [Graph Model](graph-model.md) — Neo4j node labels, relationships, and properties; essential reading before
  writing Cypher.
- [ADR 13: Restrict Neo4j Access](../adr/013-restrict-neo4j-access.md) — Why Neo4j is not exposed directly.
- [ADR 19: Graph Database Architecture](../adr/019-graph-database-architecture.md) — Per-backend query languages,
  no NeoWiki-specific query abstraction.
