# AGENTS.md

Conventions for authoring demo wiki content under `DemoData/`. `maintenance/ImportDemoData.php`
imports this directory into a running NeoWiki demo wiki to showcase NeoWiki to ECHOLOT/GLAM
partners, knowledge managers, MediaWiki ecosystem evaluators, and live-demo audiences.

## File layout

| Directory | Purpose | Wiki destination |
|---|---|---|
| `Schema/<Name>.json` | Schemas (Subject types, property definitions) | `Schema:<Name>` |
| `Subject/<Name>.json` | Subjects (data instances). Optional paired `<Name>.wikitext` for prose. | Main namespace, `<Name>` |
| `Layout/<Name>.json` | Layouts (curated displays for a Schema) | `Layout:<Name>` |
| `Page/<Name>.wikitext` | Free-form wiki pages (hubs, references) | Main namespace, `<Name>` |
| `SparqlPage/<Name>.wikitext` | Pages demoing the SPARQL surfaces. Imported only when `$wgNeoWikiSparqlStores` is non-empty (elsewhere `{{#sparql_raw}}` is unregistered and would render literally). | Main namespace, `<Name>` |
| `Module/<Name>.lua` | Scribunto modules | `Module:<Name>` |

`ImportDemoData.php` is additive: it creates and updates pages but does NOT delete pages whose
source files were removed. Use `make reinstall-db && make load-test-data` from the repo root for
a clean-slate import after renames or deletions.

## Filename and ID conventions

- **Filenames must match the entity's label exactly**, including UTF-8 accents and apostrophes.
  Examples: `Diego_Velázquez.json`, `Musée_d'Orsay.json`, `Starry_Night_Over_the_Rhône.json`.
  Otherwise auto-linked Cypher results and wikilinks dead-end.
- Underscores in filenames render as spaces in page titles.
- **Avoid colons in labels.** MediaWiki interprets a colon in a page title as a namespace prefix.
  Rephrase, e.g. "Lessons from MARC to RDF migration" rather than "MARC to RDF: Lessons learned".

Subject, relation, and option IDs:

- 15 characters total, starting with `s` / `r` / `o` respectively.
- Remaining 14 characters use a base32-ish alphabet that excludes look-alikes: no `0`, `O`, `l`, `I`
  (lowercase `o` and `i` are allowed; see `SubjectId`/`RelationId`).
- Existing conventions:
  - Museum corpus uses random base62 (e.g. `sEpfwJLnxyQy6vR`).
  - Older corpora group by prefix (`s1demo1...` ACME, `s1demo7...` ACME structural,
    `s1demo8...` research). Pick a fresh group prefix for new corpora.
- The only hard requirement is uniqueness across the dataset.

## Wikitext gotchas

1. **Leading whitespace = `<pre>` block.** Never start a continuation line with whitespace. MediaWiki
   renders any line starting with a space as preformatted. Keep long bullets on a single source line.
2. **`[[Page?action=edit|Label]]` does NOT work.** Internal-link syntax does not accept query
   strings, so the question mark becomes part of the page title. Use external-link syntax with `fullurl`:

   ```wikitext
   [{{fullurl:{{FULLPAGENAME}}|action=edit}} Edit this page]
   [{{fullurl:Some Other Page|action=edit}} Edit some other page]
   ```

3. **Subject pages auto-render their Main Subject infobox** at the top of the page, emitted by
   `NeoWikiHooks::handleContentPage` (`BeforePageDisplay`). Don't `{{#view}}` the same subject in
   the page's wikitext, or you get two infoboxes. Use `{{#view}}` only on plain `Page/` hubs (which
   have no Main Subject) or to embed a different subject.
4. **`{{#view}}` takes one positional Subject ID plus named args.** Write
   `{{#view:id|layout=LayoutName}}` or `{{#view:subject=id|layout=LayoutName}}`. The old
   positional form `{{#view:id|LayoutName}}` is gone and now renders a visible parser error.

## Cypher gotchas

- **Text properties land in Neo4j as arrays even when declared `"multiple": false`.** Cypher queries
  on text properties must index: `p.Venue[0] AS Venue`. Otherwise the cell renders as the literal
  string `table` (Lua `tostring()` on a list).
- Use backticks for property and relation names with spaces:

  ```cypher
  MATCH (m:Museum) RETURN m.`Annual visitors`
  MATCH (p:Publication)-[:`Part of project`]->(pr:Project) RETURN pr.name
  ```

- The Neo4j projection can lag after edits. If a query returns 0 rows when you expect data, run
  `make rebuild-graph-databases` from the repo root, then reload.

## Reusable modules

| Module | Purpose | Example invocation |
|---|---|---|
| `Module:NeoWikiDemo` `query` | Renders Cypher results as a wikitable. `columns=Col1, Col2` orders columns; `linkColumns=Col1` wraps cells in `[[...]]`. | `{{#invoke:NeoWikiDemo\|query\|MATCH (m:Museum) RETURN m.name AS Museum, m.Founded AS Founded\|columns=Museum, Founded\|linkColumns=Museum}}` |
| `Module:NeoWikiDemo` `childTable` | Builds a wikitable from the current page's child Subjects (via `mw.neowiki.getChildSubjects`). Avoids a Cypher query when the data is page-local. Optional `schema=` filter, `sortBy=`/`sortDir=` (default `desc`), `numberColumns=` for thousand-separator formatting. | `{{#invoke:NeoWikiDemo\|childTable\|schema=Attendance\|columns=Year, Visitors\|sortBy=Year\|numberColumns=Visitors}}` |
| `Module:SubjectRow` | Renders a row of Subject views in a centered, scrollable container. Optional `layout=` named arg sets a row default. Append `@LayoutName` to a subject ID to override per view. | `{{#invoke:SubjectRow\|render\|<id1>\|<id2>@LayoutName}}` |
| `Module:Card` | Renders a row of styled cards (used by `Main_Page`). | `{{#invoke:Card\|cards\|card1_title=...\|card1_link=...}}` |
| `Module:LuaExample` | Educational example for the Developers hub showing direct `mw.neowiki` use. | `{{#invoke:LuaExample\|foundedYear\|Rijksmuseum}}` |

## Hub skeleton

Use-case hub pages follow a five-section pattern:

1. **Scenario**. One short paragraph framing who the dataset is for and what story it tells.
2. **Featured**. `{{#invoke:SubjectRow|render|<id>|<id>}}` showing 2 representative subjects.
   **Skip this section on Subject-as-page hubs** (e.g. `ACME_Inc`). The auto-rendered infobox at
   the top already serves as the featured view.
3. **Question Answered**. A natural-language question heading followed by a Cypher result table.
4. **Browse**. A curated table of subjects in the dataset.
5. **How this is built**. Links to schemas, layouts, and an "Edit this page" link.

Use `linkColumns=` on hub-page Cypher tables so subject names render as wikilinks.

## Codex CSS

Always pick design values from [Codex 1.14.0](https://doc.wikimedia.org/codex/v1.14.0/design-tokens/overview.html)
tokens. Don't invent arbitrary colors, spacings, or sizes. MediaWiki 1.43 ships this version.

The Vector skin exposes color and border-color tokens as CSS custom properties at `:root`, without
the `--cdx-` prefix. Spacing, border-radius, font-size, font-weight, and line-height tokens are not
exposed as variables.

For colors and borders, use the CSS variable with a hex fallback so the design degrades gracefully
where Codex isn't loaded:

```css
background: var(--background-color-interactive-subtle, #eaecf0);
border: 1px solid var(--border-color-subtle, #c8ccd1);
color: var(--color-subtle, #54595d);
```

For sizes, spacing, radii, and weights, use the Codex token's value as a numeric literal so the
result still aligns with the design system.

## Subject prose conventions

Prose `.wikitext` files paired with subjects follow Wikipedia conventions:

- **Bold the first mention** of the page's title: `'''Subject Name''' is...`.
- **Wikilink other demo-wiki subjects** on first mention: `[[Page Name]]`, or `[[Page Name|short
  label]]` if the displayed text differs. Link the first occurrence only.
- Don't link the page's own title (Wikipedia self-link rule).
- 1-2 short factual paragraphs. No headings, no markup beyond plain prose.
- Match the existing `Subject/Claude_Monet.wikitext` style.

Reserve prose for subjects visitors are likely to land on (museums, artists, featured publications,
ACME entities). Reference data such as attendance records doesn't need prose; the auto-infobox is
the content.

## Verifying changes

From the repo root:

```sh
# Incremental import (does not delete removed pages).
make load-test-data

# Clean-slate import (drops the wiki database first). Use after renames or deletions.
make reinstall-db && make load-test-data

# Reproject the Neo4j graph if Cypher results look stale.
make rebuild-graph-databases
```

A successful import ends with `Import finished` and zero `FAILED` lines. The wiki runs at
`http://localhost:8484/`.
