# Writing these docs

Before writing or editing a page, fix three things: the named reader, the decision they make with the text, and a
length budget. A sentence earns its place only if it changes what that reader predicts or does; otherwise cut it.

- Reference pages state contracts; they never derive, justify, or narrate mechanism.
- Each fact has one home; every other page links to it instead of restating it.
- The text carries no history of its own making: nothing addressed to a reviewer, a diff, or a past design discussion.
- When unsure whether something belongs, leave it out and list it under "considered, omitted" in the PR description.
- Improving an existing page does not lengthen it by default.

## Genres

Every page sits in one genre. Write to that genre's reader and register.

Place each fact by its reader, not its topic: name the reader who needs it for their decision, and put it on a page
for that reader. If no reader needs the fact, leave it out. If its reader has no page for it, leave it out too, and
list it under "considered, omitted" with the reader it is for.

| Where | Reader, and the decision they make | Register, and what to keep out |
| --- | --- | --- |
| `guide/` | Wiki users and admins, deciding the next step of a task | Task steps in UI vocabulary (Special pages, buttons, tabs), linking to reference pages for contracts; no wire formats, no PHP, no restated contracts |
| `glossary.md`, `subject-first-wikis.md`, `qualifiers-and-references.md`, `architecture.md` | Anyone new to NeoWiki, deciding whether it fits their data and how to model it | Intended design; no dev-state caveats, no mechanism |
| `naming.md` | Contributors, choosing a name in code | Rules with examples; terms users see belong in the Glossary |
| `api/` | API consumers, deciding what a request or response means and what to do next | Wire-format vocabulary; no UI behaviour, no PHP class names |
| `authoring/` | Wiki authors and modellers, deciding what to write in wikitext, Lua or a Mapping | Contracts, signatures, examples; no narration |
| `operations/` | Sysadmins, deciding how to install, size, configure, upgrade or repair an instance | Tasks and remedies; system model only where needed to act |
| `extending/` | Extension developers, choosing an extension point and how to build on it | Internal identifiers; point at working RedHerb code over prose |
| `adr/` | Anyone about to change the system, deciding whether the change fits an earlier decision or needs a new one | [ADRs](#adrs) below |
| `planning/` | Collaborators, deciding how to answer an open question | Work in progress, marked as such; not published to the site |

## ADRs

An ADR records one decision that is costly to reverse, such as a stored format, an identifier scheme, an API contract,
a structure or a dependency. Its parts are context, decision and consequences. The decision holds only what is costly
to reverse, and the consequences say what it makes easier or harder. Behaviour that one PR can change back, such as
how the UI or an API behaves, stays out of both: place it by its reader. Give the reasons for the decision, so that a
later change does not reopen it by accident.

A Draft ADR is edited in place and carries no revision history or amendment sections.

An accepted ADR is a dated record whose meaning does not change. A new or changed decision goes in a later ADR that
names the ADR it replaces or amends, and the earlier ADR's Status line links to the later one, as in
`Status: Accepted. Amended by [ADR 39](039-schema-and-property-labels.md)`. Apart from that link, an accepted ADR is
edited in place only to fix a typo or a dead link, or to cut text that has no place in an ADR, such as behaviour.

When your change replaces behaviour that an accepted ADR describes, cut that sentence rather than rewrite it, and
place the new behaviour by its reader. When your change goes against what the ADR decides, it makes a new decision,
which goes in a later ADR. If you cannot tell which of the two a sentence is, leave it and name it in the PR
description.

A Related entry names the ADR it links: `ADR 28: Relations Model`.

## Where a new page goes

The genre table picks the directory. Two placements it does not cover:

- A page every audience needs, cutting across genres (the Glossary, Qualifiers and References, Architecture) → the docs root.
- A worked, end-to-end example → alongside the docs for the surface it walks through.

Link the new page from `README.md`, under the section for its genre.

## Never write

- The justification register: "note that", "importantly", "this ensures", "in order to", or restating the ask.
- Prose that restates what an adjacent table, signature, or example already shows.
