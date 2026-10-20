# Codex workarounds

Code that works around Codex behaviour in the controls NeoWiki renders. NeoWiki code built on Codex's behaviour, such
as how a picker handles Codex's Lookup, stays in its component.

Each file works around one Codex behaviour, is named after the Codex component and what it works around, and opens
with two lines:

```
 * Codex: <what Codex does, with a link to its Codex task if there is one>
 * Remove when: <the condition, such as: the Codex of the oldest MediaWiki NeoWiki supports is 2.3.4 or later>
```

MediaWiki core's `resources/lib/foreign-resources.yaml` says which Codex each MediaWiki release bundles.

Adding a workaround is a new file here, imported in `neowiki.ts`; removing one is deleting both.
`git grep -n "Remove when" resources/ext.neowiki/src/codex` lists the conditions.

Nothing here imports NeoWiki code; lint checks this for TypeScript. The workarounds apply within the scope that
`mountNeoWikiApp` in `neowiki.ts` describes.
