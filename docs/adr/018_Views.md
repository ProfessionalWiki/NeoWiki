# Views

Date: 2026-02-21

Status: Draft

## Context

We will need a way to let users customize how Subjects are displayed.

Examples of things users might wish to change:
* The precision of a number
* The color of a progress bar
* The ordering of the shown Statements
* Which Statements to show

It should be possible for users to have multiple displays of a Subject shown on one page, and it should be possible
for those displays to be different. For instance, on a page about a company, there might be an infobox at the top of
the page with key data, while in the financial section there is an infobox with detailed information via Properties
like Revenue, Net income, Total assets, etc.

## Decision

We introduce a View concept.

A View references a Schema and allows customized display of Subjects that use that Schema. Schemas do not reference
their Views — the link is one-directional from View to Schema.

### View Types

Views have a View Type like "infobox", "factbox", or "table". View Types are registered via a plugin system —
extensions can define new View Types. Each View Type plugin defines:
* What `settings` it supports (e.g., `borderColor` for infobox)
* What `displayAttributes` it supports per Display Rule (may depend on the Property Type)
* How to render a Subject given a View configuration
* Editing UI for its type-specific options

### Display Rules

A View contains an ordered list of Display Rules. Each Display Rule references a property by name and optionally
overrides display attributes for that property. This serves as an allowlist — unlisted properties are hidden. If
the list is empty or absent, all properties are shown in Schema-defined order.

Display attribute overrides (e.g., precision, color) override the defaults from the Property Definition in the
Schema. A Display Rule only needs to specify overrides — unspecified display attributes are inherited from the
Property Definition.

### Constraints and Display Attributes in Property Definitions

Property Definition Attributes are split into two explicit groups:
* **`constraints`** — validation and data rules (e.g., `minimum: 42`). Not overridable in Views.
* **`displayAttributes`** — presentation configuration (e.g., `precision: 2`). Overridable in Views via Display Rules.

The Property Type plugin defines which attributes belong to which group. Using `displayAttributes` in both Property
Definitions and View Display Rules makes the connection explicit: the View overrides the same values that the Property
Definition defines as defaults.

### Settings

View-level configuration specific to the View Type (e.g., `borderColor` for infobox) is stored in `settings`.
This is distinct from `displayAttributes`, which are per-property overrides.

### No Default View Entity

There is no stored "Default View" on a Schema. When a Subject is displayed without a specified View, the fallback
is to show all Statements in Schema-defined order (the current AutomaticInfobox behavior). This fallback also applies
when a referenced View no longer exists. Usage sites (parser functions, Page Schemas, etc.) specify which View to use.

### Example

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

## Consequences

* View Types must be registered via the plugin system. Infobox is the first built-in type.
* Property Definition Attributes must be split into `constraints` and `displayAttributes`. The Property Type plugin
  defines which attributes belong to which group.
* The AutomaticInfobox becomes the fallback rendering for Subjects without a specified View.

## Alternatives Considered

* **Blocklist instead of allowlist**: A View would list properties to hide, showing everything else. Rejected because
  the allowlist gives explicit control over both selection and ordering, and avoids Statements appearing unexpectedly
  when a Schema is modified.
* **Default View as a stored entity**: Each Schema would have a Default View, either auto-created or explicitly set.
  Rejected because the fallback behavior (show all Statements in Schema order) is sufficient and avoids coupling
  Schemas to Views.
* **Schema references its Views**: The Schema would store references to its Views. Rejected to keep Schemas simple
  and decoupled from the display layer.
* **`statements` or `properties` as the Display Rules key**: `statements` was rejected because the entries don't
  contain Statements — they configure how Statements are displayed. `properties` was rejected because "add a property
  to the View" is ambiguous (could mean adding a Display Rule, a Property Definition, or a Statement value).
* **Flat `attributes` in Property Definitions with plugin-only split**: The constraint/display distinction would
  exist only in Property Type plugin metadata, not in the data. Rejected because making the split visible in the
  Schema format is clearer for users and developers.
