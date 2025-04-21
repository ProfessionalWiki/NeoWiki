# Include Writer's Schema in Subjects

Date: 2023-06-22

Status: [Implemented](https://github.com/ProfessionalWiki/NeoWiki/commit/07a9c75b665cede0270c9300ea6f813e1c8ada9c) (https://github.com/ProfessionalWiki/LegacyNeoWiki/issues/402)

## Context

We have Subjects that hold data following a linked Schema that defines properties. The Schema and its property
definitions can change over time. This means that without information about the Schema as it was when the Subject
was created, we might not be able to interpret its data correctly.

Wikibase and AVRO solve this problem by including a "writer's schema". For instance, you can change a Wikibase property
from string to URI. Existing values can still be shown in the UI if they are valid URIs and invalid URIs are shown as
invalid. Further changing the property to be a number will result in the values being shown as invalid.

Notion and Coda appear to be using a similar mechanism. They display and transform values even if the column type
changed, unless such transformation is not possible, in which case the value is shown as invalid. Notably, invalid
values do not prevent editing of other values, and they are not modified unless directly edited. Upon such editing
they need to be made valid.

## Options

### 1. Do not include additional information

Stick with the current state.

* Pro: We do not need to change anything.
* Con: Likely poor ability to handle schema changes. Limited ability to detect value type mismatches and limited ability to translate values.
* Con: We need to always retrieve the Schema to make sense of the Subject.

### 2. Include writer's schema

Switch to the approach taken by Wikibase by including the property definitions used on write time for each value.

* Con: We need to store additional information for each property in the Subject.
* Con: We need to refactor a bunch of code.
* Pro: We can show and edit values even if the property definition changed.
* Pro: We can make some sense of the Subject without retrieving the Schema.
* Pro: We know this works well in Wikibase.
* ???: We can update values one-by-one (like in Notion) rather than having to do so in one go when the Schema changes.

Possible JSON structure for a Subject *encoding only the types* of the properties:

Option A: dedicated writers schema section

```json
{
	"label": "ProWiki",
	"schema": "Product",
	"properties": {
		"Available since": 2022,
		"Website": "https://pro.wiki",
		"Operator": [
			{
				"id": "00000000-1111-2222-1100-000000000044",
				"target": "12345678-0000-0000-0000-000000000055"
			}
		]
	},
	"writersSchema": {
		"Available since": "number",
		"Website": "url",
		"Operator": "relation"
	}
}
```

Option B: include info on the statement (property) level

```json
{
	"label": "ProWiki",
	"schema": "Product",
	"statements": {
		"Available since": {
			"value": 2022,
			"format": "number"
		},
		"Website": {
			"value": "https://pro.wiki",
			"format": "url"
		},
		"Operator": {
			"value": [
				{
					"id": "00000000-1111-2222-1100-000000000044",
					"target": "12345678-0000-0000-0000-000000000055"
				}
			],
			"format": "relation"
		}
	}
}
```

Option B2: option B with possible approach for [special values](https://github.com/ProfessionalWiki/NeoWiki/issues/356)

```json
{
	"label": "ProWiki",
	"schema": "Product",
	"statements": {
		"Available since": {
			"value": 2022,
			"format": "number"
		},
		"Website": {
			"value": {
				"specialValue": "noValue"
			},
			"format": "url"
		},
		"Operator": {
			"value": [
				{
					"id": "00000000-1111-2222-1100-000000000044",
					"target": "12345678-0000-0000-0000-000000000055"
				},
				{
					"specialValue": "unknownValue"
				}
			],
			"format": "relation"
		}
	}
}
```

Option C: include info on the value level

```json
{
	"label": "ProWiki",
	"schema": "Product",
	"statements": {
		"Available since": {
			"value": 2022,
			"format": "number"
		},
		"Website": {
			"value": "https://pro.wiki",
			"format": "url"
		},
		"Operator": [
			{
				"value": {
					"id": "00000000-1111-2222-1100-000000000044",
					"target": "12345678-0000-0000-0000-000000000055"
				},
				"format": "relation"
			},
			{
				"specialValue": "unknownValue"
			}
		]
	}
}
```

### 3. Include schema version

Reference a specific version of the schema for the whole Subject.

* Con: We need to refactor a bunch of code.
* Con: We still need to retrieve the schema to make sense of the Subject. (In many cases we need to do this anyway.)
* Pro: We only need to store the schema version rather than a list of property definitions in each Subject.
* Pro: We can show and edit values even if the property definition changed.
* ???: We need to update the entire Subject in one go when the Schema changes.

## Decision

Use approach 2 "Include writer's schema" with JSON format B "info on statement level"
