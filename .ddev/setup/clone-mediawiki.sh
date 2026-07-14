#!/usr/bin/env bash
# Pre-start hook, run on the host: ensure the MediaWiki core checkout that ddev
# serves as its docroot exists. Idempotent; a populated checkout (including one
# hard-linked from another worktree) is left untouched.
#
# Only the bundled extensions/skins that .ddev/mw/LocalSettings.ddev.php loads
# are fetched as submodules, not MediaWiki's full bundle, to keep the clone
# fast and small. When you wfLoadExtension/wfLoadSkin something new there, add
# its submodule to the list below.

set -e

cd "$(dirname "$0")/../.."

if [ -e Docker/mediawiki/.git ]; then
	exit 0
fi

# A previous `ddev start` may have left empty mount-target directories behind
# (docker creates them). Directories without any files are safe to discard; a
# leftover with files in it needs a human decision.
if [ -d Docker/mediawiki ] && [ -n "$(find Docker/mediawiki -type f -print -quit 2>/dev/null)" ]; then
	echo "Docker/mediawiki exists but is not a git checkout; remove it and re-run 'ddev start'." >&2
	exit 1
fi
rm -rf Docker/mediawiki

echo "Cloning MediaWiki ${MW_BRANCH:-REL1_43} into Docker/mediawiki/..."
git clone --depth 1 \
	--branch "${MW_BRANCH:-REL1_43}" \
	"${MW_GIT_URL:-https://github.com/wikimedia/mediawiki}" \
	Docker/mediawiki

echo "Fetching the bundled extensions/skins NeoWiki loads..."
git -C Docker/mediawiki submodule update --init --recursive --depth 1 \
	extensions/CodeEditor \
	extensions/ParserFunctions \
	extensions/Scribunto \
	extensions/SyntaxHighlight_GeSHi \
	extensions/VisualEditor \
	extensions/WikiEditor \
	skins/MonoBook \
	skins/Timeless \
	skins/Vector
