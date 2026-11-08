# Date Property Types

Date: 2026-11-07

Status: Draft.

## Context

Many wikis record some dates as only a year or a month: the year a company was founded, the month a project started.
Heritage wikis also record dates the way their sources give them: "c. 1850", "1620s", "between 1980 and 1985". A
Property Definition of type Date accepts only a full day.

Each Statement records the Property Type its property had when the value was written
([ADR 11](011-include-writers-schema.md)), not the property's Constraints. API clients, Lua modules, exports and
other wikis read a value by that type.

## Decision

NeoWiki has three date Property Types:

| Property Type | Holds | Examples |
|---------------|-------|----------|
| Date (`date`) | a day, a month or a year | `1984-06-15`, `1984-06`, `1984` |
| Date & Time (`dateTime`) | a date with a time of day | `1984-06-15T14:30:00+02:00` |
| EDTF date (`edtf`) | any value in the [Extended Date/Time Format](https://www.loc.gov/standards/datetime/) | `1850~`, `1640/1650`, `162X` |

Date & Time is unchanged.

**EDTF date is off by default.** A wiki can enable it, as the ECHOLOT installation does. On a wiki without it, EDTF
date values are kept unchanged but not validated, and they stay out of queries, as for any Property Type whose
extension is not installed.

**Date takes the three forms XML Schema has datatypes for**: `xsd:date`, `xsd:gYearMonth` and `xsd:gYear`.

**A Property Definition of type Date asks for a full date unless its `minPrecision` Constraint allows a month or a
year.** A less precise value gets a Violation, a warning by default.

**EDTF date takes everything the EDTF library parses**: approximate and uncertain dates, unspecified digits,
intervals, seasons, sets, years before the common era and date-times.

**Values of all three date Property Types are in the proleptic Gregorian calendar.**

## Consequences

- Someone entering a Date value sees no EDTF syntax and no calendar choice.
- Programs that read Date values must now handle a year or a month, not only a day.
- On wikis that enforce validation, a year entered for an existing Property Definition of type Date now warns
  instead of blocking, unless the Schema sets its `minPrecision` to `"day"` with severity `error`.
- Changing a Property Definition between Date and EDTF date converts nothing: its existing values get
  `type-mismatch`.
- A Julian date entered as written is read as Gregorian. A day can be converted before entry; a year or a month only
  into an EDTF interval.

## Implementation Thoughts

- Neo4j holds each Date value as its earliest and latest day. EDTF date values keep their text and get those days too
  where they have both.
- The first EDTF date input takes typed EDTF and shows the date in words.

## Out of Scope

Other calendars, such as the Julian.

## Alternatives Considered

### One date type for all of EDTF

Date accepts every EDTF value, and Constraints narrow each property to what it needs: full dates, years, or
approximate dates.

Not chosen because a Statement records its type, not its Constraints, so every program that reads Date values would
need an EDTF parser. Handing those programs a simpler form, such as the earliest and latest day, drops `~` and `?`, so
approximate dates would read as certain.

### Date for full days only

Date keeps taking only a full day, and years and months go to EDTF date.

Not chosen because years and months are the most common imprecise dates, and a wiki should not need EDTF to record
them.

### A timestamp with a precision

Each Date value stores a full timestamp plus a precision, as Wikibase does.

Not chosen because a year then carries month and day digits that every reader must know to ignore, and the timestamp
and the precision can disagree.
