# Date Types: Research Findings

Gathered 2026-11-07 by Claude Opus 5.5 for Jeroen De Dauw. Supports
[ADR 40: Date Property Types](../adr/040-date-property-types.md).

Status: Work in progress. "Inferred" marks a conclusion nobody checked directly.

## Date models elsewhere

### Wikibase

- One `time` datatype ("Point in time"). A value has `time` (`+2001-12-31T00:00:00Z`), `timezone` and
  `before`/`after` (documented as unused), `precision` (`9` year, `10` month, `11` day, down to `0` for a billion
  years) and `calendarmodel` (an item IRI: Gregorian `Q1985727` or Julian `Q1985786`)
  ([json.md](https://github.com/wikimedia/mediawiki-extensions-Wikibase/blob/master/docs/topics/json.md)).
- The repository caps precision at the day and requires every timestamp to end in `T00:00:00Z`, so it stores no time
  of day (`buildTimeValidators()` in
  [ValidatorBuilders.php](https://github.com/wikimedia/mediawiki-extensions-Wikibase/blob/master/repo/includes/ValidatorBuilders.php)).
- A year is written `+1952-00-00T00:00:00Z`. `TimestampPrecisionValidator` checks the month and day only at month or
  day precision, so a year-precision value can carry other digits.

### Semantic MediaWiki

One Time data item with year, year-month, day and day-with-time precision (`PREC_Y`, `PREC_YM`, `PREC_YMD`,
`PREC_YMDT`) and a Gregorian or Julian calendar model
([Time.php](https://github.com/SemanticMediaWiki/SemanticMediaWiki/blob/master/src/DataItems/Time.php)).

### No-code databases

From each tool's API reference. None of them models uncertain dates or other calendars (inferred from their type
lists).

| Tool | Date types | Options that change the value an API client gets |
|------|------------|--------------------------------------------------|
| [Notion](https://developers.notion.com/reference/page-property-values) | one `date` type; every value is `{start, end, time_zone}` | none; end date and time zone are set per value, formats per view |
| [Coda](https://coda.io/apis/v1/openapi.yaml) | `date`, `dateTime`, `time` and `duration` formats | none; both date formats return ISO date-times with an offset |
| [Airtable](https://airtable.com/developers/web/api/field-model) | one Date field in the UI; `date` (`2022-09-05`) and `dateTime` (`2022-09-05T07:00:00.000Z`) in the API | "Include time" appears to switch between the two (inferred) |
| [Baserow](https://gitlab.com/baserow/baserow) | one `date` type | `date_include_time` switches `2020-01-01` to `2020-01-01T12:00:00Z` |
| [Fibery](https://developers.fibery.com/api-reference/field-types) | one Date field in the UI; `date`, `date-time`, `date-range` and `date-time-range` in the API, fixed at creation | none after creation |
| [Grist](https://github.com/gristlabs/grist-core/blob/main/documentation/grist-data-format.md) | `Date` and `DateTime`, both epoch seconds | none; the time zone affects display only |
| [SharePoint](https://learn.microsoft.com/en-us/graph/api/resources/datetimecolumn) | one column type with `format: dateOnly` or `dateTime` | none; values stay UTC timestamps, so a date-only cell can read as the day before |
| [ClickUp](https://developer.clickup.com/docs/customfields) | Unix milliseconds | none; `"time": true` affects display only |

## Julian calendar use

### Wikidata

Measured 2026-11-07 on [QLever](https://qlever.dev/api/wikidata) (the 2026-08-10 dump plus live updates), with
per-property shares from the Wikidata Query Service. Counts are usages, statement and qualifier values together:

```sparql
SELECT (COUNT(*) AS ?n) WHERE { ?v wikibase:timeCalendarModel wd:Q1985786 . ?x ?p ?v }
```

- 722,769 Julian usages against 250,981,476 Gregorian: 0.29%. 60% are statement values, 40% qualifiers.
- By precision: year 49%, day 35%, century 11%, other 5%. By period: before 1582-10-15 81%, 1582–1923 18%, after 1923
  1%.
- Before 1582, Gregorian usages outnumber Julian ones: 768,430 against 583,904.
- Most Julian usages: date of birth (139,749; 1.7% of its values), start time qualifiers (108,305; 1.0%), date of death
  (93,688; 2.3%), inception (85,041; 2.0%), end time qualifiers (77,896; 1.2%).
- [Help:Dates](https://www.wikidata.org/wiki/Help:Dates): "On manual entry, the software defaults to [Gregorian] from
  1583. Dates before are considered by default as [Julian]." The parser applies this at every precision: "1200"
  becomes a Julian year.
- RDF export converts Julian dates to Gregorian at day precision only, and keeps the original calendar only in
  `wikibase:timeCalendarModel`. [T246731](https://phabricator.wikimedia.org/T246731), open since 2020: "There is no
  way to extract Julian dates from Wikidata."
- [T105100](https://phabricator.wikimedia.org/T105100): in 2016 a bot tagged 24,956 Julian statements more precise
  than a year and 130,714 Gregorian statements before 1584 for review; 5,739 and 89,621 are still tagged.

### Other Wikibases

Measured 2026-11-07 on each public query service. All use Wikidata's calendar IRIs.

| Wikibase | Domain | Time-value usages | Julian |
|----------|--------|-------------------|--------|
| FactGrid | historical research | about 2.58 million | 311,734 (12.1%) |
| Biblissima | medieval manuscripts | 123,171 | 4,702 (3.8%), all before 1582 at year precision or coarser |
| SNARC | Welsh name authority | 84,915 | 39 |
| Digital Scriptorium | medieval manuscripts | 186,012 | 0 |
| Devonshire | Devon history | 105,842 | 1 |
| EHkultura | Basque heritage | 98,280 | 11 |
| Datenbank Nordhausen | local history | 77,071 | 0 |
| MaRDI | mathematics | 14,313 distinct values | 56 |

FactGrid: 279,133 Julian usages before 1583, 28,487 in 1583–1700 and 4,109 after 1700; 90% of its distinct Julian
values have day precision. Its users pick the calendar by hand
([FactGrid:Early Modern](https://database.factgrid.de/wiki/FactGrid:Early_Modern)).

### Elsewhere

- [T207705](https://phabricator.wikimedia.org/T207705), on an EDTF datatype for Wikibase: commenters state that EDTF
  values represent Gregorian dates, and propose a separate Julian variant.
- [SemanticWikiSchema](https://github.com/Klaus-Feldmann/SemanticWikiSchema), a NeoWiki fork, lists "Historical BCE dates
  with precision/calendar information" among its additions.

## The EDTF library

[professional-wiki/edtf](https://github.com/ProfessionalWiki/EDTF), version 3, which
[#1811](https://github.com/ProfessionalWiki/NeoWiki/pull/1811) adds as a dependency:

- Parses every level 0, 1 and 2 form except intervals like `..2004-06-01/2004-06-20`; `../2004-06-20` parses.
- Every value has `getMin()`, `getMax()` and `covers()`.
- Humanizes in many languages, but not significant digits (`1950S2`), group or component qualification
  (`2004-06~-11`, `?2004-06-~11`) or level 2 unspecified digits (`1XXX-1X`).
- For an unspecified year, `ExtDate::calculateMin()` returns 0, which is 1970-01-01 ("FIXME: this is not correct").
  Open interval ends have no date.
- It is PHP only. #1811 shows the date in words through a REST endpoint.
- The Library of Congress datatype IRI for EDTF literals is `http://id.loc.gov/datatypes/edtf/EDTF` (not fetched:
  id.loc.gov answered 403).

## SPARQL stores

Oxigraph leaves `xsd:gYear` and `xsd:gYearMonth` values out of `<` and `>` comparisons with `xsd:date`; QLever
compares them. `YEAR()` covers all three on both. Measured on the dev stack for
[#1514](https://github.com/ProfessionalWiki/NeoWiki/pull/1514).

## NeoWiki behaviour

On master, 2026-11-07, the Subject editor saves each Statement with its stored type (`Schema::statementsFrom()` in
`Schema.ts`, `getSubjectData()` in `SubjectEditor.vue`), so after a property's type changes, saving does not clear
`type-mismatch`.

## User testing

ECHOLOT user testing, September 2026: imprecise dates came up in all five sessions. Years came up in three: one
tester typed them as text, one team used a number property. Decades or half-decade brackets came up in two. One
tester chose Date & Time when they wanted a date. Nobody asked for Julian dates. See also
[#1486](https://github.com/ProfessionalWiki/NeoWiki/issues/1486).

## Related records

- [#767](https://github.com/ProfessionalWiki/NeoWiki/issues/767): Date and Date & Time became separate Property
  Types (April 2026).
- [#679](https://github.com/ProfessionalWiki/NeoWiki/issues/679): the EDTF date Property Type.
- [#1841](https://github.com/ProfessionalWiki/NeoWiki/issues/1841) and
  [Jeroen De Dauw's comment on #1514](https://github.com/ProfessionalWiki/NeoWiki/pull/1514#issuecomment-6590962242):
  Julian dates as a calendar on each value, allowed per property. It is one of the two ways left open for other
  calendars; the other is a Property Type of its own.
