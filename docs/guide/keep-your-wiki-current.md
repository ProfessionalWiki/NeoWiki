---
title: Keep your wiki current
order: 4
---

# Keep your wiki current

On a Docker install, back up anything you care about ([backups](../operations/maintenance.md#backups)), move the
repository the stack runs from to the [version you want](../operations/upgrading.md#upgrading-a-docker-install), then:

```sh
make upgrade
```

[Upgrading](../operations/upgrading.md) covers what that does, the steps for a manual install, and how to
[refresh the demo content](../operations/upgrading.md#optional-refresh-the-demo-content) to the current set.

Tell us what this guide is missing: https://github.com/ProfessionalWiki/NeoWiki/issues/1336.
