---
title: Subject Sources
order: 7
---
# Subject Sources

A Source supplies Subjects and Schemas from somewhere other than this wiki's revision slots — another wiki of a
farm, an on-wiki SMW or Wikibase store, a remote instance. Implement `Source` and register it with
`NeoWikiRegistrar::addSource()`:

```php
$registrar->addSource( 'myext_catalog', new CatalogSource() );
```

The key is what a Subject id names before its colon (`myext_catalog:widget-7`), so it identifies your Source
across runs and installs: pick a stable one and namespace it to your extension. It must be a letter followed by up
to 63 letters, digits, underscores or hyphens; anything else throws. Registering a key already in use replaces that
Source. This wiki's own Source is registered under its
[Wiki ID](https://www.mediawiki.org/wiki/Manual:Wiki_ID) — that key is what a bare Subject id resolves to, and it
cannot be taken over.

Registration runs under an [early hook](extending.md#backend-registration) on every request, whether or not
anything reads a Subject. If building your Source costs anything — a client, a connection, a file read — pass a
closure returning it instead of the Source itself; the closure is called at most once, the first time something
resolves that Source.

Your Source answers fetch-by-id (one Subject or a list), Schema-by-name, whether its Subjects are editable, which
localIds it recognises, and its RDF base URI. It is never asked to run a query: a Subject becomes queryable by
being materialised in a graph store.

A Source that cannot reach its store answers as though the Subject or Schema is absent, so a page that names it
degrades rather than breaking. Sourced Subjects are read-only, and rendering them in Views is not built yet, so
today a Source's Subjects are reachable by id and as relation targets. See
[ADR 23](../adr/023-subject-sources.md).

Your Source vouches for everything it returns: NeoWiki serves it to every reader of the wiki and performs no
per-user authorization on it, because a sourced Subject has no page here to authorize against. If your data has
restrictions of its own, serve only the part that is unrestricted — a question you answer without knowing who is
asking. Per-user granularity for sourced data does not exist yet.
