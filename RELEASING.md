# Releasing NeoWiki

A release is a signed tag on a commit that adds the frontend bundle CI built for a master commit, kept off master.
`scripts/tag-release.sh` makes that commit and signs the tag; you check the result and push it.

You need this repository as your `origin` remote with push access, an authenticated `gh`, and a signing key in your
git config.

## Prepare master

1. Merge a pull request that sets `version` in `extension.json` to the release's version and replaces "Not released
   yet" in the version's `RELEASE-NOTES.md` section with the planned release date. Use `0.1.0`, not `0.1.0-rc.1`,
   even for pre-releases: the release reuses the last pre-release's commit unchanged.
2. Wait for that commit's [Build Docker image][build-docker] run to succeed. It uploads the bundle the tag will hold.

## Tag a pre-release

```bash
scripts/tag-release.sh 0.1.0-rc.1
git show --stat 0.1.0-rc.1
```

The script builds on origin's master head; to build on an earlier master commit, pass it as a second argument. It
prints the master commit it used, which should be the one from step 1, and `git show` should list only
`resources/ext.neowiki/dist/neowiki.css` and `resources/ext.neowiki/dist/neowiki.js`. If anything is off, run
`git tag -d 0.1.0-rc.1`: nothing is pushed yet. Otherwise:

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

Sign the release on the last pre-release, here `0.1.0-rc.2`, and check that both tags name the same commit:

```bash
scripts/tag-release.sh 0.1.0 0.1.0-rc.2
git rev-parse '0.1.0^{commit}' '0.1.0-rc.2^{commit}'
```

Then push it and publish its release, again with the version's notes. Its image is `:0.1.0`, and `:latest` moves to it.

```bash
git push origin refs/tags/0.1.0
gh release create 0.1.0 --verify-tag
```

## Rules

* Tag only with the script. A tag made by hand, or by `gh release create` without `--verify-tag`, has no bundle, and
  Packagist publishes it all the same.
* Never move, delete or reuse a pushed tag, since Packagist keeps the version. Fix forward with a new pre-release.
* Tags build only on master commits, and both tagging and releasing need the run's bundle, which expires 90 days
  after the run. After that, start from a newer master commit.

[build-docker]: https://github.com/ProfessionalWiki/NeoWiki/actions/workflows/build-docker.yml
