# Label Templates

Date: 2026-09-22

Status: Draft. Amends [ADR 31](031-optional-subject-labels.md).

## Context

A Subject's label is a field of its own, apart from its Statements; without one it is shown under a computed name
([ADR 31](031-optional-subject-labels.md)). People who model their own data give their Schemas a property for the
name, such as Title or Name, and fill it in. Unless the Subject is the Main Subject of a page someone titled, it is
then still shown as `(unnamed Artwork)`, a page created for it is titled with its id, and the relation picker cannot
find it by name. In user testing, participants who had filled such a property read the stand-in as a demand to name the
Subject again.

## Decision

A Schema may carry a `labelTemplate`: text in which `{Property name}` stands for that property's value in the Subject
([Schema format](../api/schema-format.md#label-template)). A Subject without a stored label takes its template label,
ahead of the page name: the Schema's author chose the template for every Subject of the Schema, and a Subject then
keeps one name whether or not it is its page's Main Subject.

The label is computed on read, never written into `label`. A copy would be a stored default, which ADR 31 removed
because it cannot be told apart from a chosen label. It would also go stale when the Statement changes.

The template is a top-level Schema field rather than a flag on the property holding the name. A flag can point at one
property only; a template also composes labels from several, such as `{Museum} attendance {Year}`, without a second
change to the format.

A placeholder names a property of the same Schema whose values read as text or numbers. Reading another Subject's
label through a relation would tie a label to another page, which the Projections cannot follow without reprojecting
every page that depends on it, and relations can form cycles.

A template label counts as chosen: the UI does not mark it as a stand-in, the graph stores it as `name` for every
Subject it labels, and a Mapping's `labelPredicate` carries it. A page created for a Subject no title was given for is
titled by its template label, or by the Subject's id where that page exists or may not be created.

## Consequences

* Adding a template to a Schema renames each label-less Main Subject it gives a label from the page name to that
  label, in page-first wikis too, and a page move no longer renames such a Subject.
* A template edit reaches the graph's `name` and `rdfs:label` of the Schema's existing Subjects only as their pages
  are reprojected.
* The Schema, which described only Statements, now holds a rule about its Subjects. A Schema save that removes a
  property the template names, or retypes it to one whose values do not read as text or numbers, is refused until
  the template changes too.
* The frontend renders templates as well, to show and use the label before a Subject is saved. A change to how a
  template reads values is made in both.
