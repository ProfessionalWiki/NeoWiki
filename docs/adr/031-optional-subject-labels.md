# Optional Subject Labels

Date: 2026-08-13

Status: Draft

Retires the `label-required` violation, which [ADR 26](026-validation-severity-levels.md) classifies as a
fixed-severity error on the ground that a label is Subject identity: "a labelless Subject cannot be displayed or
found". Neither half holds once a name is computed at the point of use.

## Context

A stored default cannot be told apart from a chosen label. That is what keeps a moved page from renaming the Subjects
that took their name from it, and what would make an importer's synthesized names look curated.

## Decision

A Subject may have no label. Every surface then shows a computed name: the page name for a Main Subject, the Schema
name otherwise. A page titled with the id of a Subject stored on it names nothing, so its Main Subject shows the Schema
name too. The contract is in the [glossary](../glossary.md#subject) and [subject-format.md](../api/subject-format.md);
the choices worth recording are where the surfaces differ.

**The UI shows the Schema tier as the Subject's id, in brackets.** Such a Subject is shown as `(s1ab2cd3ef4gh5i)`, from
the `neowiki-subject-generated-name` message — the bracketing MediaWiki documents on `blanknamespace`, "(Main)". Test
users read a Schema name, even bracketed, as a demand to add a label; an id is expected to name the Subject without
suggesting that anything is missing, and the brackets set it apart from the bare id a relation shows for a target that
cannot be found. Since the id does not say what the Subject is, an infobox and a Manage Subjects row show the Schema
beside it. Where a label would head the Subject, in the editor pane and in the headings of Special:Subject and of a page
titled by its id, “No label defined” stands in its place, with the id beside or above it. A Main Subject's page name is
left unmarked, having been chosen by whoever titled the page. The id is put in place by the presentation layer, so
`rdfs:label`, Lua and `{{#neowiki_value}}` keep the bare Schema name; REST carries `displayNameIsGenerated`, which a
client cannot derive, since a Main Subject on a page titled after its Schema is named its Schema name without anyone
having generated it.

**The graph materializes the fallback for Main Subjects only.** Any other label-less Subject gets no `name`
property: the Schema name there would make every unnamed Subject of a Schema indistinguishable in query results, and
the Schema is already on the node as its other label. Should a consumer ever need it, materializing them too is the
escape hatch.

**RDF emits `rdfs:label` for every Subject.** Consumers key on it, and the Schema appears there as `rdf:type` rather
than as a label, so the argument above does not carry over. A Mapping's `labelPredicate` is emitted only from a stored
label: it states what the thing is called in the target ontology's vocabulary, and a Schema name under `foaf:name`
would assert a type as a name, which reconciliation keys on.

**Lua diverges from REST on purpose.** REST returns the stored `label`, now nullable, beside a non-null `displayName`.
Lua's `subject.label` is the display name and `subject.storedLabel` the nullable one, because an absent key is `nil`
there and concatenating it replaces the rendered page with a script error.

**Existing stored defaults are cleared once**, by `NeoWiki:ClearDefaultSubjectLabels`: the fix reaches only Subjects
that have no stored label, so without it every existing Subject keeps the bug.

## Consequences

A Subject node's `name` is null for a label-less Subject that is not the Main Subject, so a stub is identified by its
node labels rather than by a missing name, and such a Subject is not findable by name in label search. Outside the
UI, several such Subjects of one Schema display identically; the computation can gain discriminators later, which
stored defaults could not.

Clearing costs a revision per page, and a Subject that is not the Main Subject and carried the older page-name default
is renamed to its Schema name by it. `Subject.getLabel()` in the frontend bundle can return null; display code goes
through `presentation/subjectDisplayName.ts`, which shows the Schema tier as the bracketed id.
