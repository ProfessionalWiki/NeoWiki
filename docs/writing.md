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

| Where | Reader, and the decision they make | Register, and what to keep out |
| --- | --- | --- |
| `glossary.md`, `qualifiers-and-references.md` | New users and evaluators forming the mental model | Intended design; no dev-state caveats, no mechanism |
| `api/` | API consumers deciding what a request or response means and what to do next | Wire-format vocabulary; no PHP class names |
| `authoring/`, `rdf/`, `examples/` | Admins and integrators looking up exact behavior | Contracts, signatures, examples; no narration |
| `operations/` | Sysadmins keeping an instance healthy | Tasks and remedies; system model only where needed to act |
| `extending/` | Extension developers, whose interface is our internal identifiers | Point at working RedHerb code over prose |
| `adr/` | Maintainers recording a decision | A dated record: context, decision, consequences; not retro-edited apart from status links |
| `planning/` | Collaborators exploring an open question | A work-in-progress register, marked as such; not published to the site |

## Never write

- The justification register: "note that", "importantly", "this ensures", "in order to", or restating the ask.
- Prose that restates what an adjacent table, signature, or example already shows.
