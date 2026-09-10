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

## Context

[`PagePropertyProviderContext`](https://github.com/ProfessionalWiki/NeoWiki/blob/master/src/Domain/Page/PagePropertyProviderContext.php) exposes the page id, title and
namespace, creation and modification times, `categories`, `lastEditor`, and `parserProperties`: the MediaWiki page
properties recorded during parsing, such as those a parser hook sets via `ParserOutput::setPageProperty`.
`parserProperties` are an input from MediaWiki's parse, not the Page Properties the provider returns. The raw
main-slot `content` and its `contentModel` are exposed for content models the parse products do not cover.

## Refreshing a page's data without an edit

A page's graph data is written on edit and on full rebuild. When data your extension contributes changes outside an
edit — an approval extension marking a revision approved — trigger a refresh:

```php
$outcome = NeoWikiExtension::getInstance()
	->newPageRebuilder()
	->rebuild( $title );
```

NeoWiki re-runs every provider for the page, re-reads its subject slot, and updates the Page node synchronously; no
revision is created. `rebuild()` returns a `PageRefreshOutcome`:

- `Refreshed` — the Page node was updated.
- `Unpublished` — the [revision policy](revision-policy.md) publishes no revision of the page, so the call withdrew
  it from the graph stores.
- `SkippedMissingRevision` — the page has no current revision.
- `SkippedUnreadableSubjects` — the page's subject slot holds content NeoWiki cannot read as Subjects.
- `SkippedUnreadablePageProperties` — the page's properties could not be built, for instance because a provider or
  the page's own parse threw.

A graph store whose write fails is logged and skipped, exactly as on a normal page save; only request timeouts and
wiki-database errors throw.
