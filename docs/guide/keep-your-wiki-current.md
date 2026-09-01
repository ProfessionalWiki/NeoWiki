---
title: Keep your wiki current
order: 3
---

# Keep your wiki current

NeoWiki has no releases yet: the latest state is master, and upgrading means moving to it.

On a Docker install, back up anything you care about ([backups](../operations/maintenance.md#backups)), `git pull`
the repository the stack runs from, then:

```sh
make upgrade
```

[Upgrading](../operations/upgrading.md) covers what that does, the steps for a manual install, and how to
[refresh the demo content](../operations/upgrading.md#optional-refresh-the-demo-content) to the current set.

If an upgrade can no longer read your evaluation data, `make remove && make demo` starts fresh.

Tell us what this guide is missing: https://github.com/ProfessionalWiki/NeoWiki/issues/1336.
