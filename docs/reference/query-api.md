# Query API

`POST /neowiki/v0/query/cypher` runs a read-only Cypher query against the configured Neo4j backend and returns
results as a structured JSON envelope. The endpoint exists only when a Neo4j graph backend is configured; on a
wiki without one the route is absent and does not appear in the OpenAPI spec.

## Stability

Pre-1.0. The endpoint, request shape, response envelope, and error contract may change without notice until 1.0.
Do not treat `/neowiki/v0/query/cypher` as stable for third-party integrations yet.

## Discovery flow

Before writing a query, a client or LLM should learn the data model. The recommended sequence:

1. **`GET /neowiki/v0/schemas`** — Returns a list of available Schema names (e.g. `Person`, `Company`, `Document`).
2. **`GET /neowiki/v0/schema/{name}`** — Returns the full Property Definitions for one Schema: property names,
   types, and constraints. This tells you which node properties exist on `:Subject:SchemaName` nodes and what
   values they hold.
3. *(Optional)* **`GET /neowiki/v0/subject/{id}`** — Fetch a live Subject to see the actual data shape, including
   any Statements that differ from the Schema defaults.
4. **Read the [Graph Model](graph-model.md)** — Describes the Neo4j label and relationship structure in full.
   Essential for writing Cypher beyond simple `MATCH (s:Subject)` patterns: Page nodes, `HasSubject`
   relationships, typed Subject-to-Subject relationships, and available node properties.
5. **`POST /neowiki/v0/query/cypher`** — Run the query.

## Endpoint contract

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
| `columns` | array of string | RETURN aliases in declaration order. Separate from `rows` because JSON object key order is not guaranteed. |
| `rows` | array of object | Each row keyed by RETURN alias. |
| `truncated` | boolean | `true` when the result was cut at `maxRows`. Narrow the query or use the `expensive` tier for more rows. |
| `resultCount` | integer | Number of rows returned (always ≤ `maxRows`). |
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
| `datetime` / zoned `time` | ISO 8601 string with offset, e.g. `"2023-09-13T14:22:23+00:00"` / `"14:22:23+02:00"` |
| `localdatetime` / `localtime` | ISO 8601 string without offset, e.g. `"2023-09-13T14:22:23"` / `"09:30:00"` |
| `duration` | `{ "months": ..., "days": ..., "seconds": ..., "nanoseconds": ... }` |
| `point` | `{ "x": ..., "y": ..., "crs": "...", "srid": ... }` (plus `"z"` for 3D points) |

Temporal values render as ISO 8601 strings (timezone offsets to whole-minute precision). `duration` and
spatial `point` values render as component objects.

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
| `backendUnavailable` | 503 | No graph backend is reachable (graceful degradation, see [ADR 19](../adr/019-graph-database-architecture.md)). |
| `internalError` | 500 | Anything else. |

`errorType` strings are stable across releases. Clients and LLMs should branch on `errorType`, not on `message`
text. The REST `message` is always English and may change between releases; it is intended for logging and
debugging, not display. User-facing localization applies only to the wikitext surfaces — the `{{#cypher_raw}}`
parser function and `nw.query()` in Lua — which render translated messages derived from the same `errorType`
classification.

## Example walkthrough

This example follows the full discovery flow for a wiki that has a `Person` schema, then queries for all people
born after the year 2000.

### Step 1 — List available schemas

```bash
curl http://localhost:8484/rest.php/neowiki/v0/schemas
```

Response (abbreviated):

```json
[
    { "name": "Company" },
    { "name": "Document" },
    { "name": "Person" }
]
```

### Step 2 — Fetch the Person schema

```bash
curl http://localhost:8484/rest.php/neowiki/v0/schema/Person
```

Response (abbreviated):

```json
{
    "name": "Person",
    "description": "A human being.",
    "properties": [
        { "name": "Birth year", "type": "number", "required": false },
        { "name": "Nationality", "type": "select", "required": false },
        { "name": "Employer",   "type": "relation", "required": false }
    ]
}
```

From this you know: the Neo4j node for a Person has a `Birth year` numeric property, a `Nationality` string
property, and outgoing `Employer` relationships to other Subject nodes.

### Step 3 — (Optional) Sample a subject

```bash
curl http://localhost:8484/rest.php/neowiki/v0/subject/s1abc5def6ghi78
```

### Step 4 — Run the query

Using the property name discovered in Step 2 (`Birth year`). Property names with spaces must be backtick-escaped
in Cypher.

```bash
curl -X POST http://localhost:8484/rest.php/neowiki/v0/query/cypher \
     -H 'Content-Type: application/json' \
     -d '{
       "cypher": "MATCH (s:Subject:Person) WHERE s.`Birth year` > $minYear RETURN s.name, s.`Birth year` AS year",
       "parameters": { "minYear": 2000 }
     }'
```

Response:

```json
{
    "columns":     ["name", "year"],
    "rows":        [
        { "name": "Alice Fontaine", "year": 2001 },
        { "name": "Ben Markov",     "year": 2003 }
    ],
    "truncated":   false,
    "resultCount": 2,
    "durationMs":  11
}
```

Always prefer parameterized queries (`$name` syntax) over string concatenation. Parameters are passed safely to
the database driver and protect against injection.

## Limits and tiers

Two resource tiers control per-query timeout and row cap:

| Tier | Who | Timeout | Row cap |
|---|---|---|---|
| `default` | Any user with `neowiki-query` | 30 s | 5,000 rows |
| `expensive` | Any user with the core `apihighlimits` right | 300 s | 50,000 rows |

`apihighlimits` is a MediaWiki core right granted by default to `bot` and `sysop` groups. It exists precisely for
"heavier API queries" and is reused rather than introducing a NeoWiki-specific right.

When `truncated` is `true`, the result was cut at `maxRows`. Narrow the query with `WHERE` clauses, add `LIMIT`
to the Cypher, or use an account with `apihighlimits` for a higher cap.

### Overriding limits

```php
// LocalSettings.php
$wgNeoWikiQueryLimits = [
    'default'   => [ 'timeoutSeconds' => 15,   'maxRows' => 1000  ],
    'expensive' => [ 'timeoutSeconds' => 120,  'maxRows' => 20000 ],
];
```

Both keys must be present. The values above are examples; tune for your deployment's hardware and expected query
patterns.

### Rate limits

Request frequency is controlled via MediaWiki's standard `$wgRateLimits` mechanism, keyed as `neowiki-query`.
Defaults shipped by NeoWiki:

| Caller type | Default limit |
|---|---|
| Anonymous | 10 requests / 60 s |
| Logged-in user | 60 requests / 60 s |
| Bot | 1000 requests / 60 s |
| Sysop / Bureaucrat | Unlimited (via core `noratelimit` right) |

Other accounts can be exempted from rate limits by granting them the core `noratelimit` right.

Override for your site:

```php
// LocalSettings.php — example: tighten anonymous access, raise for logged-in users
$wgRateLimits['neowiki-query'] = [
    'anon' => [ 5, 60 ],
    'user' => [ 120, 60 ],
];
```

## Permissions

| Right | Default groups | Purpose |
|---|---|---|
| `neowiki-query` | `*` (everyone, including anonymous visitors) | Required to call the query endpoint. |
| `apihighlimits` | `bot`, `sysop` (core defaults) | Grants the `expensive` resource tier. |

To restrict the endpoint on a closed wiki:

```php
// LocalSettings.php
$wgGroupPermissions['*']['neowiki-query'] = false;
$wgGroupPermissions['user']['neowiki-query'] = true;  // logged-in users only
```

Or remove it from all groups and grant it selectively:

```php
$wgGroupPermissions['*']['neowiki-query'] = false;
$wgGroupPermissions['researcher']['neowiki-query'] = true;
```

## Deployment notes

NeoWiki enforces a transaction timeout in-process via `Neo4jQueryLimits::$timeoutSeconds`. This is the primary
protection against long-running queries. For defense-in-depth, configure Neo4j itself with matching server-side
limits so that a bug or misconfiguration in the application layer does not leave the database exposed:

```ini
# neo4j.conf
db.transaction.timeout=60s
db.memory.transaction.max=512m
```

The `db.transaction.timeout` value should be slightly above the `expensive` tier timeout (300 s by default) so
that legitimate long queries are not killed server-side before the application timeout fires, but runaway queries
that bypass the application layer are still bounded. A value such as `360s` gives a 60-second grace margin.
`db.memory.transaction.max` caps per-transaction heap to prevent a single query from exhausting server memory.

These settings apply to all connections to the Neo4j instance, not only NeoWiki traffic. Adjust to your
deployment's needs.

## SPARQL query endpoint

`POST /neowiki/v0/query/sparql` runs a read-only SPARQL query against the first configured
[SPARQL store](../operations/installation.md#optional-sparql-graph-stores) and returns the results. Like the Cypher
endpoint, it exists only when a store is configured; on a wiki without one the route is absent and does not appear in
the OpenAPI spec. Multi-store query addressing is a later addition — for now the endpoint always targets the first
configured store.

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

`CONSTRUCT` and `DESCRIBE` are not supported yet: they return an RDF graph rather than the
`application/sparql-results+json` document this endpoint negotiates.

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

### Differences from the Cypher endpoint

- **Read-only by protocol, not by a validator.** The query is sent as a SPARQL 1.1 *query* operation
  (`Content-Type: application/sparql-query`), and the SPARQL query grammar contains no update forms, so no read-only
  validator is needed. The endpoint only ever posts to the store's `queryUrl`, never to `updateUrl`.
- **The result document is returned unmodified, with no envelope**, so there is no `columns` / `rows` / `resultCount`
  reshaping and no cell normalization — the store's JSON already carries typed RDF terms.
- **Limits: the per-tier timeout is applied** (as the HTTP client timeout), reusing the same `$wgNeoWikiQueryLimits`
  tiers and `neowiki-query` right and rate limits as the Cypher endpoint. The `maxRows` cap is **not** applied:
  truncating `results.bindings` would alter the W3C document, and signaling the truncation would require inventing a
  field, so neither is done. Bound result size with `LIMIT` in the query.

## Related

- [REST API](rest-api.md) — OpenAPI spec and how it is generated; how to browse it with Swagger UI.
- [Lua API](lua-api.md) — `nw.query()` for the same Cypher surface from wikitext templates, and `nw.sparqlQuery()` for
  the SPARQL surface.
- [Parser Functions](parser-functions.md) — `{{#cypher_raw}}` and `{{#sparql_raw}}` for inline query output in
  wikitext.
- [Graph Model](graph-model.md) — Neo4j node labels, relationships, and properties; essential reading before
  writing Cypher.
- [ADR 13: Restrict Neo4j Access](../adr/013-restrict-neo4j-access.md) — Why Neo4j is not exposed directly.
- [ADR 19: Graph Database Architecture](../adr/019-graph-database-architecture.md) — Per-backend query languages,
  no NeoWiki-specific query abstraction.
