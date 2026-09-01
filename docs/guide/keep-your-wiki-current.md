---
title: Keep your wiki current
order: 3
---

# Keep your wiki current

NeoWiki has no releases yet: the latest state is master. The demo Docker image is kept in sync with the public demo
wiki, so once you have upgraded, your wiki has everything [neowiki.dev](https://neowiki.dev) shows.

## Upgrade

On a Docker install, `git pull` the repository the stack runs from, then:

```sh
make upgrade
```

[Upgrading](../operations/upgrading.md) covers what that does, and the steps for a manual install.

To move the demo pages to the current demo set as well, see
[refresh the demo content](../operations/upgrading.md#optional-refresh-the-demo-content).

## If an upgrade breaks your data

NeoWiki is pre-release, so breaking changes can land at any time. If an upgrade can no longer read your evaluation
data, `make remove && make demo` starts fresh.

This guide is new and deliberately short. Tell us where it falls short:
https://github.com/ProfessionalWiki/NeoWiki/issues/1336.
