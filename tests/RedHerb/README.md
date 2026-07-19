# RedHerb

RedHerb is a minimal MediaWiki extension shipped inside the NeoWiki repository as a live, test-backed
reference for how to extend NeoWiki. NeoWiki's own tests exercise it, so the examples here stay working.
RedHerb does not cover every extension point and grows over time as new examples are added.

The narrative reference for NeoWiki's extension points lives in the published docs:
**[Extending NeoWiki](../../docs/extending/extending.md)**. This README is just an index mapping each
extension point to the file that demonstrates it.

> **Stability:** NeoWiki is pre-1.0. Every extension point is alpha and may change without notice.

## Example index

### Backend (PHP)

- **Declare the NeoWiki dependency** — [`extension.json`](extension.json) (`requires.extensions`).
- **`NeoWikiRegistration` hook** (Property Type, Neo4j value builder, Page Property Provider) —
  [`src/RedHerbHooks.php`](src/RedHerbHooks.php).
- **Property Type** — [`src/ColorType.php`](src/ColorType.php) (`implements PropertyType`) and
  [`src/ColorProperty.php`](src/ColorProperty.php) (`extends PropertyDefinition`).
- **Page Property Provider** — [`src/StaticPagePropertyProvider.php`](src/StaticPagePropertyProvider.php).
- **`NeoWikiGetFrontendModules` hook** —
  [`src/RedHerbFrontendModulesHook.php`](src/RedHerbFrontendModulesHook.php).
- **Reading NeoWiki data / authorization** — [`src/RedHerbSidebarHook.php`](src/RedHerbSidebarHook.php) and
  [`src/Specials/SpecialRedHerbSubjectFinder.php`](src/Specials/SpecialRedHerbSubjectFinder.php).

### Frontend (JS/Vue)

- **Register a Property Type frontend** (`neowiki.registration` hook) —
  [`resources/init.js`](resources/init.js) with [`resources/ColorDisplay.vue`](resources/ColorDisplay.vue),
  [`resources/ColorInput.vue`](resources/ColorInput.vue), and
  [`resources/ColorAttributesEditor.vue`](resources/ColorAttributesEditor.vue).
- **Register a View Type frontend** (`neowiki.registration` hook) —
  [`resources/init.js`](resources/init.js) with [`resources/RedHerbCard.vue`](resources/RedHerbCard.vue).
- **Mount standalone Vue features wired to NeoWiki services** —
  [`resources/createChild/`](resources/createChild), [`resources/editMainSubject/`](resources/editMainSubject),
  and [`resources/subjectFinder/`](resources/subjectFinder).

## Frontend linting

RedHerb carries its own lint setup so the example is self-contained and can be lifted into a
real extension. Everything lives in this directory: [`package.json`](package.json) (dev
dependencies and scripts), [`.eslintrc.json`](.eslintrc.json) (the Wikimedia plain-JavaScript
profile, `wikimedia/client` plus `wikimedia/mediawiki`, for the `.js` and Vue SFC resources),
and [`.stylelintrc.json`](.stylelintrc.json) (`stylelint-config-wikimedia` for the
`<style lang="less">` blocks). NeoWiki's own TypeScript frontend under `resources/ext.neowiki`
is linted separately; RedHerb does not share its toolchain.

The aim is to mirror a real third-party MediaWiki extension, so RedHerb follows MediaWiki's
standard plain-JavaScript lint defaults and code style (the `wikimedia/client` +
`wikimedia/mediawiki` profile) even where those differ from `ext.neowiki`'s TypeScript-specific
choices: for example, the profile requires explicit-close Vue component tags
(`<cdx-icon></cdx-icon>`), whereas `ext.neowiki` allows self-closing. A NeoWiki choice is carried
over only where it is deliberate and applies here, namely the dependency versions (which track
`ext.neowiki` rather than MediaWiki core's older pins) and the two rule relaxations below.

```bash
npm install
npm run lint        # ESLint, Stylelint, and banana-checker (i18n)
```

Three rules are disabled. `max-len` (`.eslintrc.json`) and `no-descending-specificity`
(`.stylelintrc.json`) are off in `ext.neowiki`'s config too: the example's Vue templates and
MediaWiki service calls have some long lines, and `no-descending-specificity` is noisy with the
nested BEM-style LESS these components use. `compat/compat` (`.eslintrc.json`) is off because
NeoWiki targets a modern browser baseline above the `browserslist-config-wikimedia` floor the
rule checks against (`queueMicrotask` and similar APIs are used throughout NeoWiki); it comes
from the `wikimedia/mediawiki` profile, which `ext.neowiki`'s TypeScript setup does not include,
so there is no equivalent disable there.
