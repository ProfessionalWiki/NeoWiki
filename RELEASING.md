# Releasing NeoWiki

A release is a signed tag on a commit that adds two files to a master commit, both kept off master: the frontend bundle
CI built for it, and `Docker/image-tag`, which names the version's demo image. `scripts/tag-release.sh` makes that
commit and signs the tag; you check the result and push it.

You need this repository as your `origin` remote with push access, an authenticated `gh`, and a signing key in your
git config.

## Prepare master

1. Merge a pull request that sets `version` in `extension.json` to the release's version and removes "Not released
   yet" from the version's `RELEASE-NOTES.md` section. Use `0.1.0`, not `0.1.0-rc.1`, even for pre-releases: the
   release builds on the last pre-release's master commit.
2. Wait for that commit's [Build Docker image][build-docker] run to succeed. It uploads the bundle the tag will hold.

## Tag a pre-release

```bash
scripts/tag-release.sh 0.1.0-rc.1
git show --stat 0.1.0-rc.1
```

The script builds on origin's master head; to build on an earlier master commit, pass it as a second argument. It
prints the master commit it used, which should be the one from step 1, and `git show` should list only
`Docker/image-tag`, `resources/ext.neowiki/dist/neowiki.css` and `resources/ext.neowiki/dist/neowiki.js`. If anything
is off, run `git tag -d 0.1.0-rc.1`: nothing is pushed yet. Otherwise:

```bash
git push origin refs/tags/0.1.0-rc.1
gh release create 0.1.0-rc.1 --verify-tag --prerelease
```

When `gh` asks for notes, use the version's section of `RELEASE-NOTES.md`. Packagist lists the version at once, and
the push builds the `ghcr.io/professionalwiki/neowiki:0.1.0-rc.1` image. Try it with
`composer require neowiki/neowiki:0.1.0-rc.1` in a wiki, or in a clone running the demo stack:

```bash
git fetch --tags && git checkout 0.1.0-rc.1 && make upgrade
```

To fix something, merge the fix, wait for its run as in step 2, and tag `0.1.0-rc.2`.

## Release

Sign the release on the last pre-release, here `0.1.0-rc.2`. The script builds it on that pre-release's master commit
and refuses a result that differs from the pre-release in more than `Docker/image-tag`, the only file `git diff` should
list:

```bash
scripts/tag-release.sh 0.1.0 0.1.0-rc.2
git diff --stat 0.1.0-rc.2 0.1.0
```

Then push it and publish its release with the version's section of `RELEASE-NOTES.md` as its notes, each paragraph and
list item on one line: GitHub breaks release notes at every newline. Its image is `:0.1.0`, and `:latest` moves to it.

```bash
git push origin refs/tags/0.1.0
gh release create 0.1.0 --verify-tag
```

## Rules

* Tag only with the script. A tag made by hand, or by `gh release create` without `--verify-tag`, has no bundle and
  gets no image, and Packagist publishes it all the same.
* Never move, delete or reuse a pushed tag, since Packagist keeps the version. Fix forward with a new pre-release.
* Tags build only on master commits, and both tagging and releasing need the run's bundle, which expires 90 days
  after the run. After that, start from a newer master commit.

[build-docker]: https://github.com/ProfessionalWiki/NeoWiki/actions/workflows/build-docker.yml
