# Views and Layouts

Date: 2026-02-23

Status: Accepted (terminology updated 2026-03)

## Context

We will need a way for users to customize how Subjects are displayed.

Examples of things users might wish to change:
* Which Property Definitions to show Statements for
* The ordering of the shown Statements
* The precision of a number
* The color of a progress bar

It should be possible for users to have multiple displays of a Subject shown on one page, and it should be possible
for those displays to be different. For instance, on a page about a company, there might be an infobox at the top of
the page with key data, while in the financial section, there is an infobox with detailed information via Properties
like Revenue, Net income, Total assets, etc.

## Decision

We distinguish between two concepts:

* A **View** is an on-page rendering of a Subject. Views are placed on wiki pages via the `{{#view}}` parser function
  or automatically for a page's Main Subject.
* A **Layout** is a stored configuration that defines how to render Subjects of a certain Schema. Layouts are
  referenced by name from Views.

### Layouts

A Layout references a Schema and allows customized display of Subjects that use that Schema. Schemas do not reference
their Layouts — the link is one-directional from Layout to Schema.

#### Identification and Storage

Layouts are identified by their name (following the same pattern as Schemas per
[ADR 17](017-names-as-identifiers.md)). Layout names are immutable. Layouts are stored as pages in a Layout namespace.

#### View Types

Layouts have a View Type like "infobox", "factbox", or "table". View Types are registered via a plugin system, so
extensions can define new View Types. Each View Type plugin defines:

* What `settings` it supports (e.g., `borderColor` for infobox)
* How to render a Subject given a Layout configuration
* Editing UI for its type-specific options

#### Display Rules

A Layout contains an ordered list of Display Rules. Each Display Rule references a property by name and optionally
overrides display attributes for that property. This serves as an allowlist — unlisted properties are hidden.

When a Layout has no Display Rules (empty or absent list), all properties are shown in Schema-defined order. Note that
adding the first Display Rule switches from "show all" to "show only listed" — there is no way to show all properties
while overriding display attributes for some of them via Display Rules.

Display attribute overrides (e.g., precision, color) override the defaults from the Property Definition in the
Schema. A Display Rule only needs to specify overrides — unspecified display attributes are inherited from the
Property Definition.

#### Display Attributes in Property Definitions

Property Type plugins declare which of their attributes are **Display Attributes**: presentation configuration
(e.g., `precision: 2`) that can be overridden per-Layout via Display Rules. All other attributes (e.g., validation
rules like `minimum: 42`) are not overridable in Layouts.

The Schema JSON format stores all attributes flat. The display/non-display distinction is determined by the Property
Type plugin, not by the data format.

#### Settings

Layout-level configuration specific to the View Type (e.g., `borderColor` for infobox) is stored in `settings`.
This is distinct from `displayAttributes`, which are per-property overrides.

#### No Default Layout Entity

There is no stored "Default Layout" on a Schema. When a Subject is displayed without a specified Layout, the fallback
is to show all Statements in Schema-defined order (the default infobox behavior). This fallback also applies when a
referenced Layout no longer exists. Usage sites (parser functions, Page Schemas, etc.) specify which Layout to use.

#### Example

```json
{
  "schema": "Company",
  "type": "infobox",
  "description": "Key financial data",
  "displayRules": [
    { "property": "Revenue", "displayAttributes": { "precision": 0 } },
    { "property": "Net Income" },
    { "property": "Total Assets" }
  ],
  "settings": {
    "borderColor": "#336699"
  }
}
```

### Views

A View is what users see on a page — a rendered Subject. The `{{#view}}` parser function places a View:

* `{{#view: }}` — renders the current page's Main Subject with the default infobox (no Layout)
* `{{#view: SubjectId}}` — renders the specified Subject with the default infobox (no Layout)
* `{{#view: layout=LayoutName}}` — renders the current page's Main Subject with the specified Layout
* `{{#view: SubjectId | layout=LayoutName}}` — renders the specified Subject using the Layout's Display Rules
* `{{#view: subject=SubjectId | layout=LayoutName}}` — same, with the Subject specified as a named argument

Views are not stored entities. They exist as HTML placeholders that the frontend hydrates with Vue components.

### Terminology rationale

"View" is used for the on-page rendering because it's what users naturally say ("I added two views to this page").
"Layout" is used for the stored configuration because it describes what the entity does — it lays out which properties
to show, in what order, with what settings — and it avoids overloading "View" for both the thing you see and the thing
you configure.

## Consequences

* View Types must be registered via the plugin system. Infobox is the first built-in type.
* Property Type plugins must declare which of their attributes are Display Attributes.
* The default infobox becomes the fallback rendering for Subjects without a specified Layout.

## Alternatives Considered

* **Blocklist instead of allowlist**: A Layout would list properties to hide, showing everything else. Rejected because
  the allowlist gives explicit control over both selection and ordering, and avoids Statements appearing unexpectedly
  when a Schema is modified.
* **Default Layout as a stored entity**: Each Schema would have a Default Layout, either auto-created or explicitly
  set. Rejected because the fallback behavior (show all Statements in Schema order) is sufficient and avoids coupling
  Schemas to Layouts.
* **Schema references its Layouts**: The Schema would store references to its Layouts. Rejected to keep Schemas simple
  and decoupled from the display layer.
* **`statements` or `properties` as the Display Rules key**: `statements` was rejected because the entries don't
  contain Statements — they configure how Statements are displayed. `properties` was rejected because "add a property
  to the Layout" is ambiguous (could mean adding a Display Rule, a Property Definition, or a Statement value).
* **Explicit `constraints` and `displayAttributes` keys in Schema JSON** ([PR #628](https://github.com/ProfessionalWiki/NeoWiki/pull/628)):
  The split would be visible in the stored data format rather than determined by the plugin. Rejected because it adds
  schema migration complexity and duplicates information already defined by the Property Type plugin.
* **Generated IDs for Layouts**: Layouts would get opaque IDs (like Subjects) for stable references. Rejected because
  Layouts are referenced in wikitext, Page Schemas, and conversation where readability matters. Immutable names
  provide the same reference stability with better ergonomics.
* **"View" for both concepts**: The stored entity was originally called "View", same as the on-page rendering. Rejected
  because `{{#view}}` works without a stored entity (it's just "render this Subject"), making the overloaded
  terminology confusing. "Layout" for the stored entity avoids this ambiguity.
