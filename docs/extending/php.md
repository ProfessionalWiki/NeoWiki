---
title: Using NeoWiki from PHP
order: 9
---
# Using NeoWiki from PHP

`NeoWikiExtension::getInstance()` exposes services usable from any MediaWiki extension point (hooks, special pages):

- `newSubjectPermissionHints( Authority )` — side-effect-free subject permission checks for showing or hiding
  affordances; a positive answer is a hint, not authorization to write.
- `newPageSubjectsLookup()` — the Subjects on a page.
- `newSubjectContentRepository( Authority )` — read and write a page's Subject slot. The authority sets the
  revision-deletion audience only; the read is not gated on the page's `read` permission.
- `newFrontendModuleLoader()` — mount NeoWiki's UI on any page.
- `newPageRebuilder()` —
  [refresh a page's graph data without an edit](page-properties.md#refreshing-a-pages-data-without-an-edit).

Examples: [`src/RedHerbSidebarHook.php`](https://github.com/ProfessionalWiki/NeoWiki/blob/master/tests/RedHerb/src/RedHerbSidebarHook.php) and
[`src/Specials/SpecialRedHerbSubjectFinder.php`](https://github.com/ProfessionalWiki/NeoWiki/blob/master/tests/RedHerb/src/Specials/SpecialRedHerbSubjectFinder.php).

## Running Cypher queries

`NeoWikiExtension::getInstance()->newCypherQueryService( $authority )` runs read-only Cypher for an `Authority`. It
checks the authority's `neowiki-query` right; rejects write queries by a keyword check plus `EXPLAIN`, which also
rejects `CALL` and `SHOW` (see the [parser function notes](../authoring/parser-functions.md)); enforces the timeout
against the backend; and truncates results to the row cap, but only after the query has run in full, so bound
expensive queries with `LIMIT` in the Cypher itself. Resolve the limits configured in
[`$wgNeoWikiQueryLimits`](../api/query-api.md) with `Neo4jQueryLimits::forUser()`:

```php
$result = NeoWikiExtension::getInstance()->newCypherQueryService( $this->getAuthority() )->execute( new Neo4jQueryRequest(
	cypher: 'MATCH (s:Subject:Person) WHERE s.`Birth year` > $minYear RETURN s.name AS name',
	parameters: [ 'minYear' => 2000 ],
	limits: Neo4jQueryLimits::forUser( $this->getUser() ),
) );
```

Example: [`src/Specials/SpecialRedHerbContentPageCount.php`](https://github.com/ProfessionalWiki/NeoWiki/blob/master/tests/RedHerb/src/Specials/SpecialRedHerbContentPageCount.php).

`execute()` returns a `Neo4jQueryResult` (columns, rows, truncation flag) and throws a `QueryException` subclass on
failure, `QueryPermissionDeniedException` when the authority lacks the right; `newCypherQueryService()` throws a
`LogicException` on a wiki with no Neo4j backend configured.

The `User` in `forUser()` only sizes the limits: how heavy a single query may be, not how often. Rate limit
user-supplied queries yourself.
