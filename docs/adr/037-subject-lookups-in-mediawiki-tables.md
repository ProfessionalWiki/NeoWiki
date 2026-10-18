# Subject Lookups in MediaWiki Tables

Date: 2026-10-15

Status: Draft

## Context

Four NeoWiki features find Subjects by something other than their id: relation-target suggestions, the Subject list
(on Special:Subjects, a Schema's page and Special:Schemas), "Referenced by" on Special:Subject, and the Subject counts
per Schema. They read the Neo4j Projection, so a wiki without Neo4j, including one with only a SPARQL store, gets none
of them. [ADR 19](019-graph-database-architecture.md) let such features fall back or degrade without a Graph Store,
and [ADR 32](032-subject-page-index.md) moved Subject identity into a MediaWiki table but left suggestions on Neo4j.

A Graph Store is optional, and a failed write to it is logged while the save commits, so a feature that reads one
behaves differently from wiki to wiki and misses what the store missed. Suggestions must not lag either: an editor
creates a Subject and links to it straight after.

## Decision

**Only the query surfaces read Graph Stores**: the Cypher and SPARQL that users write, run from parser functions, Lua
or the REST API. The four features read MediaWiki tables on every wiki, here `neowiki_subject` and `neowiki_relation`,
and a feature that needs Subject data these tables lack extends them. This replaces the fallbacks ADR 19 allowed and
ADR 32's suggestions from Neo4j.

**The tables follow the published revision**, the one a revision policy chooses, as the Projections do, so lists,
suggestions and counts show what readers see. `neowiki_subject_page` keeps following the latest revision, so that an
editor reaches a Subject before its page is published.

**They are written with the change that alters them**, in its transaction where it has one, so they are never staler
than the revision they derive from.

## Consequences

* The four features work the same on every wiki, with or without a Graph Store. Under a revision policy that holds
  edits for approval, suggestions too wait for the approval.
* Every save of a page that holds Subjects also writes the tables, and a failing write fails the save, as one to
  ADR 32's index does.
* Search in the Subject list matches anywhere in a name, which no index serves, so a term few Subjects match reads
  every row, as does sorting by page or last edit: about a second per million Subjects. On MySQL and MariaDB,
  `$wgMaxExecutionTimeForExpensiveQueries`, off by default, caps each search query; nothing caps the sorts.
* MediaWiki's web updater does not fill the tables; `RebuildPublishedSubjectIndex.php`, which `update.php` runs, fills
  and repairs them.

## Alternatives Considered

* **A Graph Store where there is one, a table elsewhere**: two implementations of each lookup, and the Graph Store's
  gaps where it is used.
* **The search index** (CirrusSearch with OpenSearch): its documents are pages, not Subjects; it is updated through the
  job queue, from the latest revision rather than the published one; and it is optional.
* **More columns on `neowiki_subject_page`**: that index follows the latest revision.
* **Writing after the save commits**, as MediaWiki's link tables are: it would run after the response, so suggestions
  would lag.
