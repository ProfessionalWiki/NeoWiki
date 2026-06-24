# RedHerb

RedHerb is a minimal MediaWiki extension shipped inside the NeoWiki repository as a live, test-backed
reference for how to extend NeoWiki. NeoWiki's own tests exercise it, so the examples here stay working.
RedHerb does not cover every extension point and grows over time as new examples are added.

The narrative reference for NeoWiki's extension points lives in the published docs:
**[Extending NeoWiki](../../docs/reference/extending.md)**. This README is just an index mapping each
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
