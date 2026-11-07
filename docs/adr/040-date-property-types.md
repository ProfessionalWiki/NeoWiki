# Date Property Types

Date: 2026-11-07

Status: Draft. Amends [ADR 26](026-validation-severity-levels.md): adds `min-precision-month` and
`min-precision-day`, whose severity the Schema sets, and `invalid-edtf`, a fixed `error`.

## Context

Many wikis record some dates as only a year or a month: the year a company was founded, the month a project started.
Heritage wikis also record dates the way their sources give them: "c. 1850", "1620s", "between 1980 and 1985". A
Date property accepts only a full day.

Each Statement records the Property Type its property had when the value was written
([ADR 11](011-include-writers-schema.md)), not the property's Constraints. API clients, Lua modules, exports and
other wikis read a value by that type.

## Decision

NeoWiki has three date Property Types:

| Property Type | Holds | Examples |
|---------------|-------|----------|
| Date (`date`) | a year, a month or a day | `1984`, `1984-06`, `1984-06-15` |
| Date & Time (`dateTime`) | a date with a time of day | `1984-06-15T14:30:00+02:00` |
| EDTF date (`edtf`) | any value in the [Extended Date/Time Format](https://www.loc.gov/standards/datetime/) | `1850~`, `1640/1650`, `162X` |

Date & Time is unchanged.

**Date takes the three forms XML Schema has datatypes for**, `xsd:gYear`, `xsd:gYearMonth` and `xsd:date`, without a
time zone and for the years 1 to 9999. `minimum`, `maximum` and `default` take the same forms. Decades, approximate
dates and ranges need EDTF date.

**A Date property asks for a full date unless its `minPrecision` Constraint allows a month or a year.** A less
precise value gets a Violation, a warning by default, as for every Constraint.

**A year or a month stands for every day in it.** A value breaks a `minimum` or `maximum` only when all its days fall
outside it, so `1984` meets a minimum of `1984-06-01`.

**EDTF date takes everything the EDTF library parses**: approximate and uncertain dates, unspecified digits,
intervals, seasons, sets, years before the common era and date-times.

**Date and EDTF date values are in the proleptic Gregorian calendar.**

## Consequences

- Someone entering a Date value sees no EDTF syntax and no calendar choice.
- Programs that read Date values must now handle a year or a month, not only a day.
- On wikis that enforce validation, a year in an existing Date property now warns instead of blocking, unless the
  Schema sets `minPrecision` to `"day"` with severity `error`.
- Date or EDTF date is chosen per property. Changing it later converts nothing: the existing values are flagged as
  written under the old type. So a property where any value may need EDTF starts as EDTF date.
- A Julian date entered as written is read as Gregorian. A day can be converted before entry; a year or a month only
  into an EDTF interval.

## Implementation Thoughts

- EDTF date is registered only on wikis that enable it: off by default, on in the ECHOLOT installation. On other
  wikis, an EDTF date value, for example from an imported Schema, is kept unchanged and flagged, like a value of a
  Property Type whose extension is not installed.
- Neo4j holds each Date value as its earliest and latest day. EDTF date values keep their text and get those days too
  where they have both.
- The first EDTF date input takes typed EDTF and shows the date in words.

## Out of Scope

Other calendars, such as the Julian. They get their own decision when someone needs them.

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
