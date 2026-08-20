# Subject-to-Page Index

Date: 2026-08-20

Status: Accepted

## Context

Subject ids are not derived from the page title ([ADR 5](005-subject-guids.md)), so finding the page that holds a
Subject takes a query. The only index answering it was the Neo4j projection: `HasSubject` edges walked backwards.
Subject CRUD, `{{#view}}`, `{{#neowiki_value}}` and the `mw.neowiki.*` getters all resolved ids that way, so a wiki
with no graph backend, or with only a SPARQL store, could not store or read Subjects at all.

Identity resolution has stronger requirements than the projection it was living in. A graph store is a rebuildable
query projection ([ADR 19](019-graph-database-architecture.md)) and its failures during edit, delete and undelete are
swallowed by design so the user's operation still commits. A Subject written while a backend was unreachable would
then be unfindable, and so uneditable, until an administrator rebuilt that store. With more than one backend
configured, per-backend failure isolation makes it worse: two stores can hold different answers to "which page holds
this Subject", and which one replies becomes an accident of wiring order.

## Decision

The subject-to-page mapping is authoritative in a MediaWiki table, `neowiki_subject_page`, keyed on the Subject id
and the page id. It is not a fallback and not a cache: it is the implementation of `PageIdentifiersLookup`.

**Written with the revision it derives from.** `RevisionFromEditComplete` fires inside `PageUpdater`'s atomic section,
so indexing an edit on the wiki's primary connection joins the transaction that writes the revision: it commits with
that revision or not at all. The index can therefore never be staler than the subject slot it reads, and replica reads
inherit the revision's own session-consistency guarantees. It sits outside the projection's failure isolation, so an
index write that fails aborts the edit rather than being logged and passed over.

Delete, undelete and import are indexed from hooks outside that atomic section. A web request still has its
transaction open, so a failure there takes the operation down with it, as on the edit path. A maintenance script has
no such transaction, so there the index write lands after the operation is already done: a failure leaves the index
wrong until a rebuild. Only a delete can strand rows that way, and those resolve to nothing anyway.

**Ids come from the slot's raw JSON**, never from the deserializer. An invalid Subject is a persisted, supported state
([ADR 21](021-add-backend-validation.md), [ADR 26](026-validation-severity-levels.md)), and the lookup is what gets an
editor to the page holding it, so a Subject too broken to deserialize must still be findable. Ids that are not
well-formed are left out, since no caller could ask about them.

**Reads join `page`.** The title and namespace are the page's current ones, so a move needs no index maintenance, and
the rows a deleted page leaves behind resolve to nothing.

**Duplicate ids resolve to the lowest page id.** Cross-wiki transfer may bring one id onto two pages, which must not
fail either page's save ([ADR 5](005-subject-guids.md)), so the key is not unique. Every reader getting the same
answer matters more than which page wins. Ids are keyed bare, as stored ([ADR 22](022-multi-wiki-node-identity.md),
[ADR 23](023-subject-sources.md)): `null` means "no local page holds this", not "does not exist".

Graph backends are unchanged: still rebuildable query projections, each registering its query surfaces only when it is
configured. What changes is that Subjects no longer need one. One editing feature still does: relation-target
suggestions are read from Neo4j, and without it the field offers none.

Because identity is now resolved authoritatively, an unresolvable Subject is refused rather than measured against the
wiki-global `edit` right: every right a Subject write needs is a right on the page holding it, so with no page there
is nothing to allow.

## Consequences

* NeoWiki works with no graph backend configured. That is a supported mode, not a misconfiguration.
* A NeoWiki table is now on the correctness path, whereas `neowiki_rebuild_runs` only carries bookkeeping. Every save
  writes the index, so a wiki that has not run `update.php` cannot be edited at all, rather than degrading to
  Subjects being unfindable.
* `RebuildSubjectPageIndex.php` backfills an existing wiki, registered to run from `update.php`. MediaWiki's web
  updater runs no post-update scripts, so a wiki upgraded that way gets an empty table, leaving every Subject that
  predates the upgrade unresolvable until the script is run by hand.
* Resolving an id is a primary-key read against a local table rather than a network round-trip, on a path taken once
  per Subject per page render.
* The index shares the projection's blind spot: a history merge that leaves the source page as a redirect writes that
  revision without firing a hook either covers. The source page keeps its rows and still exists, so its Subjects go on
  resolving to a page that no longer holds them, and a write to one lands there. The rebuild script is the repair
  path. The impact is worse than for a projection, which only misreports a query.
* A graph rebuild does not write the index. It walks the wiki from a replica, so the revision it projects may already
  have been superseded — eventual consistency the index must not inherit.
* The index rebuild is a plain walk, with none of the graph rebuild's per-store machinery: no resume cursor, no run
  record, no way to start it from the wiki. It runs once at upgrade and rarely after, so a run that dies starts over.

## Alternatives Considered

* **`page_props`**: only indexed by `pp_propname`, so the Subject id would have to be encoded into the property name,
  one name per Subject. `Special:PagesWithProp` and `list=pagepropnames` enumerate distinct property names wiki-wide,
  so that breaks core surfaces NeoWiki cannot patch. `page_props` is also written POSTSEND, deliberately outside
  ChronologyProtector: an eventually-consistent index under a strongly-consistent write path.
* **The search index**: optional infrastructure and eventually consistent, so it cannot carry a correctness
  dependency.
* **Scanning content at query time**: revision content offers no indexed access and may be compressed or external, so
  every lookup would load every Subject page's blob.
* **Making the id the page title**, as Wikidata does: dissolves the problem, but reverses
  [ADR 5](005-subject-guids.md) and the [several-Subjects-per-page model](007-multiple-subjects-per-page.md).
* **A cache in front of the graph**: cannot answer a miss authoritatively, so it optimizes an implementation rather
  than being one.
