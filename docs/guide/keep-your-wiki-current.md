---
title: Keep your wiki current
order: 4
---

# Keep your wiki current

On a Docker install, back up anything you care about ([backups](../operations/maintenance.md#backups)), then run this
from the directory the stack runs from:

```sh
make upgrade version=latest
```

[Upgrading](../operations/upgrading.md) covers what that does, the steps for an existing wiki, and how to
[refresh the demo content](../operations/upgrading.md#optional-refresh-the-demo-content) to the current set.

Tell us what this guide is missing: https://github.com/ProfessionalWiki/NeoWiki/issues/1336.
