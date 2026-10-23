---
title: Query API
order: 5
---
# Query API

NeoWiki runs read-only queries against the wiki's graph stores, through one endpoint per kind of store:

| Endpoint | Query language | On a wiki with |
|---|---|---|
| [`POST /neowiki/v0/query/sparql`](#sparql-query-endpoint) | SPARQL 1.1 | a SPARQL store |
| [`POST /neowiki/v0/query/cypher`](#cypher-query-endpoint) | Cypher | Neo4j |

A wiki can have both. On a wiki without that kind of store, the endpoint answers `404`.

Both endpoints need the [`neowiki-query`](#permissions) right but no CSRF token. To call them as a user rather than
anonymously, log in as for the rest of the [REST API](rest-api.md). The [Schema endpoints](rest-api.md#schemas) list
the Schema and property names to query.

Both report a failed query with a body such as `{ "errorType": "emptyQuery", "message": "Query is empty." }`. Branch
on `errorType`, not on `message`, which is English and may change between releases. When the store rejects a query,
the message carries the store's own detail. Besides their own errors, listed below, both endpoints answer:

| `errorType` | HTTP | When |
|---|---|---|
| `emptyQuery` | 400 | The query is empty or contains only whitespace. |
| `permissionDenied` | 403 | The caller lacks the `neowiki-query` right. |
| `rateLimitExceeded` | 429 | The caller exceeded the [rate limit](#rate-limits). |
| `internalError` | 500 | Anything else. |

## SPARQL query endpoint

The query runs against the wiki's first configured
[SPARQL store](../operations/installation.md#optional-sparql-graph-stores). It holds one or more projections, in a
[named graph per page and projection](rdf-export.md#iri-scheme). [RDF Export](rdf-export.md) describes the native
projection; a [Mapping](../authoring/mapping-format.md) defines the vocabulary of an ontology projection, such as EDM.

### Request

```
POST /rest.php/neowiki/v0/query/sparql
Content-Type: application/json
```

```json
{
    "query": "SELECT ?label WHERE { GRAPH ?page { ?s <http://www.w3.org/2000/01/rdf-schema#label> ?label } } LIMIT 10"
}
```

| Field | Type | Required | Description |
|---|---|---|---|
| `query` | string | Yes | A SPARQL 1.1 `SELECT` or `ASK` query. `CONSTRUCT` and `DESCRIBE` are not supported. |

### Successful response (200)

The body is the store's W3C [SPARQL results JSON](https://www.w3.org/TR/sparql11-results-json/) document, including
any extras the store adds, such as QLever's `meta`. NeoWiki caps no rows.

```json
{
    "head": { "vars": ["label"] },
    "results": { "bindings": [ { "label": { "type": "literal", "value": "Johann Sebastian Bach" } } ] }
}
```

### Errors

| `errorType` | HTTP | When |
|---|---|---|
| `sparqlSyntaxError` | 400 | The store rejected the query, most often for its syntax. |
| `sparqlStoreUnavailable` | 503 | The store could not be reached, failed, or did not answer within the caller's [timeout](#limits-and-tiers). |

## Cypher query endpoint

[Graph Model](graph-model.md) describes what Neo4j holds: the node labels, relationships and properties to match.

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
| `cypher` | string | Yes | A single read-only Cypher statement. |
| `parameters` | object | No | Parameter name → value map, referenced as `$name` in the query. Defaults to `{}`. |

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
| `columns` | array of string | The column names, in RETURN order. Row object key order is not guaranteed. |
| `rows` | array of object | Each row keyed by column name. |
| `truncated` | boolean | `true` when rows beyond the caller's [row cap](#limits-and-tiers) were dropped. |
| `resultCount` | integer | Number of rows in `rows`. |
| `durationMs` | integer | Query time in milliseconds, measured by the wiki. |

#### Cell normalization

Scalar Cypher values (strings, integers, floats, booleans, `null`) pass through as-is. Other types surface as:

| Cypher type | JSON shape |
|---|---|
| Node | `{ "id": ..., "labels": [...], "properties": {...} }` |
| Relationship | `{ "id": ..., "type": "...", "startNodeId": ..., "endNodeId": ..., "properties": {...} }` |
| Path | `{ "nodes": [...], "relationships": [...] }`, its relationships without `startNodeId` and `endNodeId` |
| `date` | ISO 8601 date string, e.g. `"2023-10-01"` |
| `datetime` / zoned `time` | ISO 8601 string with a whole-minute offset, e.g. `"2023-09-13T14:22:23+00:00"` / `"14:22:23+02:00"` |
| `localdatetime` / `localtime` | ISO 8601 string without offset, e.g. `"2023-09-13T14:22:23"` / `"09:30:00"` |
| `duration` | `{ "months": ..., "days": ..., "seconds": ..., "nanoseconds": ... }` |
| `point` | `{ "x": ..., "y": ..., "crs": "...", "srid": ... }` (plus `"z"` for 3D points) |

`id`, `startNodeId` and `endNodeId` are Neo4j's internal ids. The ID of a Subject or Relation is the `id` in its
`properties`.

### Errors

| `errorType` | HTTP | When |
|---|---|---|
| `parameterMissing` | 400 | The query references `$x` but `parameters` has no key `x`. |
| `cypherSyntaxError` | 400 | Neo4j reports a syntax error in the query. |
| `queryTimeout` | 408 | The query ran past the caller's [timeout](#limits-and-tiers). |
| `writeQueryRejected` | 422 | The query is not read-only, or uses `CALL` or `SHOW`. |
| `backendUnavailable` | 503 | Neo4j could not be reached. |

## Limits and tiers

Unless `$wgNeoWikiQueryLimits` changes them, two tiers cap each query:

| Tier | Who | Timeout (`timeoutSeconds`) | Cypher row cap (`maxRows`) |
|---|---|---|---|
| `default` | Callers without `apihighlimits` | 30 s | 5,000 rows |
| `expensive` | Callers with the core `apihighlimits` right | 300 s | 50,000 rows |

Queries run from wikitext or Lua always use the `default` tier.

### Rate limits

Calls to either endpoint count against MediaWiki's `$wgRateLimits` key `neowiki-query`. NeoWiki's defaults:

| Caller | Limit |
|---|---|
| All anonymous callers together | 10 requests / 60 s |
| Each logged-in user | 60 requests / 60 s |
| Each bot | 1,000 requests / 60 s |
| Administrators and bureaucrats | Unlimited (core `noratelimit` right) |

## Permissions

| Right | Default groups | Grants |
|---|---|---|
| `neowiki-query` | `*` (everyone, including anonymous visitors) | Calling the query endpoints, running queries from wikitext or Lua, and seeing Subject counts. A holder reads whole graph stores: results are not filtered per page. |
| `apihighlimits` | `bot`, `sysop` (core defaults) | The `expensive` tier. |

## Related

- [REST API](rest-api.md) — the other endpoints, and the OpenAPI spec.
- [Parser Functions](../authoring/parser-functions.md) — `{{#sparql_raw}}` and `{{#cypher_raw}}` for query output in
  wikitext.
- [Lua API](../authoring/lua-api.md) — `nw.sparqlQuery()` and `nw.query()` for queries from Lua modules.
- [ADR 19: Graph Database Architecture](../adr/019-graph-database-architecture.md) — why each store keeps its own
  query language, with no NeoWiki query language on top.
