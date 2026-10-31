---
title: Page Property Providers
order: 4
---
# Page Property Providers

Page Property Providers contribute key/value metadata to the Page node in the Neo4j graph. Providers run for every
page that is saved or rebuilt, whether or not it holds Subjects. Implement `PagePropertyProvider`:

```php
class StaticPagePropertyProvider implements PagePropertyProvider {

	public function getProperties( PagePropertyProviderContext $context ): array {
		return [ 'myext_reviewState' => 'approved' ];
	}

}
```

Register with `NeoWikiRegistrar::addPagePropertyProvider()`. Keys from all providers merge into one map, the
last-registered provider winning a collision, so prefix keys with your extension's name. Example:
[`src/StaticPagePropertyProvider.php`](https://github.com/ProfessionalWiki/NeoWiki/blob/master/tests/RedHerb/src/StaticPagePropertyProvider.php).

If your provider throws an exception while a page is saved or refreshed, NeoWiki logs it, and the page keeps its
previous graph data, Subjects included, until its next save or refresh. A database error or timeout instead fails the
projection, which is retried as a job. A full graph rebuild reports a page whose provider throws as failed.

## Context

[`PagePropertyProviderContext`](https://github.com/ProfessionalWiki/NeoWiki/blob/master/src/Domain/Page/PagePropertyProviderContext.php) exposes the page id, title and
namespace, creation and modification times, `categories`, `lastEditor`, and `parserProperties`: the MediaWiki page
properties recorded during parsing, such as those a parser hook sets via `ParserOutput::setPageProperty`.
`parserProperties` are an input from MediaWiki's parse, not the Page Properties the provider returns. The raw
main-slot `content` and its `contentModel` are exposed for content models the parse products do not cover.

## Refreshing a page's data without an edit

NeoWiki writes a page's graph data on edit, on full rebuild, and on a MediaWiki links refresh that changes the page's
`categories` or `parserProperties`, such as one caused by editing a template or module the page uses. When data your
extension contributes changes outside an edit — an approval extension marking a revision approved — trigger a refresh
once you have written that change:

```php
$pageExists = NeoWikiExtension::getInstance()
	->newPageRebuilder()
	->rebuild( $title );
```

NeoWiki updates what the Subject list, suggestions, counts and "Referenced by" read at once, in the current database
transaction. Once that transaction has committed, it re-runs every provider for the page and updates its Page node: a
graph store whose write fails is logged and skipped, and a projection that fails otherwise is retried as a job. When the
[revision policy](revision-policy.md) publishes no revision of the page, the refresh withdraws it from all of these
instead. `rebuild()` returns `false`, and changes nothing, when the page has no current revision. It throws only on
request timeouts and wiki-database errors.
