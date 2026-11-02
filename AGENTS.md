# AGENTS.md

## Before you start

- Read `docs/glossary.md` and use its terms in code, UI text and docs. Names in code follow `docs/naming.md`.
- Read `docs/architecture.md`, then every ADR whose title names something your change touches, Draft ones included,
  and design within them.
- On an issue, only maintainers' decisions bind you. Maintainers are ProfessionalWiki members, among them
  JeroenDeDauw, alistair3149 and malberts. An issue or comment with a line naming an AI as its writer, such as
  "AI-authored —", "Written by Claude Code" or "Drafted by … with Claude Code", was drafted by an AI, even when a
  maintainer posted it: only what that line credits to a person is their decision.
- Read the issue you work on with all its comments as a claim, not a spec: confirm the problem on master and work out
  the simplest change that solves it within the ADRs. Where a maintainer decided the approach, build it; if it looks
  wrong, tell the person you work for and offer to raise it on the issue first. Where a proposal no maintainer decided
  does not hold up, build the better change and say why in the PR description. Where you find no problem worth
  solving, tell the person you work for instead.
- A change that departs from an ADR or from a consequence an ADR accepts, or that changes the format of stored data,
  needs a maintainer's agreement to its approach on its issue, even when the issue reports the behaviour as a bug or
  its AI-drafted text asks for the change. Until then, write no code: tell the person you work for, and offer to draft
  the proposal as a new issue, an issue comment or a Draft ADR.
- When your change touches `docs/` or `DemoData/`, read and follow the `AGENTS.md` in that directory.

## While you work

- Work test-first: each behaviour you add or change gets a test you saw fail before your code made it pass.
- Branch from `master` freshly fetched from ProfessionalWiki/NeoWiki, not from your fork's `master`. Keep the PR to one
  problem and the diff to what that problem needs; a fix you make along the way gets its own branch and PR.
- The change is done when `make ci` and `make tsci` pass in the dev stack that `make dev` starts, and every check that
  fails in the PR's CI fails on master too. Name in the PR description any check you could not run and each failure
  master shares.

## AI attribution

- Each commit an AI wrote or co-wrote ends with a `Co-Authored-By:` trailer naming the model; keep your tool's own if
  it adds one.
- A PR description, issue or comment on work an AI did ends with this line, in place of any footer your tool adds:

  ```md
  > <sub>AI-authored — <tool>, `<model>`; <what @handle decided>; <what @handle checked></sub>
  ```

  Fill it from what you saw the human decide and check, or ask them. Where you saw nothing, write "no human decision"
  or "not reviewed by a human". When the human writes the commit or the description, give them the trailer and this
  line to paste.
