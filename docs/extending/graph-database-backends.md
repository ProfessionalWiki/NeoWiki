---
title: Graph Database Backends
order: 8
---
# Graph Database Backends

The bundled Neo4j and SPARQL stores are `GraphDatabasePlugin` implementations; a store of your own is another one.
It receives every page change and takes part in rebuilds, but gets no query surface of its own yet: the parser
function, REST route and `mw.neowiki` Lua function are wired in core for the bundled stores.

```php
class MyGraphDatabasePlugin implements GraphDatabasePlugin {

	public function initialize(): void {
		// Create any store-level structures your backend needs (e.g. constraints or indexes).
	}

	public function savePage( Page $page ): void {
		// Project the page and its subjects into your store.
	}

	public function deletePage( PageId $pageId ): void {
		// Remove the page from your store.
	}

}
```

Register with `NeoWikiRegistrar::addGraphDatabasePlugin( $name, $plugin )`. Example:
[`src/RedHerbGraphDatabasePlugin.php`](https://github.com/ProfessionalWiki/NeoWiki/blob/master/tests/RedHerb/src/RedHerbGraphDatabasePlugin.php).

## Naming

The name is what [`--store`](../operations/maintenance.md#rebuilding-one-store) addresses and what a rebuild files
its run records under; namespace it to your extension. A name is refused with a warning on the `NeoWiki` channel
when another backend already holds it, when it is `neo4j` in any casing, or when it is longer than 255 bytes. A
refused backend receives no page changes and cannot be rebuilt.

## What NeoWiki calls

`savePage` hands you the page with all of its Subjects and the Page Properties contributed by every
`PagePropertyProvider`, and runs for every new revision, so subject edits, undeletions and page moves all reach you
as a save.

`initialize` runs on `update.php`, at the start of a `RebuildGraphDatabases` run of your store before any page is
projected, once per batch of a rebuild started from the wiki, and again as a liveness probe after a batch in which
every page failed. Make it idempotent and cheap; it never runs on an individual edit.

Make `deletePage` idempotent: a rebuild deletes every page MediaWiki no longer has, whether or not your store still
holds it.

## Failures

Signal failure by throwing. On an edit, delete or undelete, NeoWiki logs the failure and lets the user's operation
commit; your projection is out of sync for that page until it is
[rebuilt](page-properties.md#refreshing-a-pages-data-without-an-edit) or the store is. During a rebuild, failures
reach the rebuild instead: a page you refuse is logged and counted and the rebuild carries on, while an `initialize`
throw ends the run, whichever of its calls it happens on. On `update.php` a failing `initialize` is reported and
the update carries on, though the backends registered after yours do not initialize on that run.
