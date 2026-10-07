<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Infrastructure;

use MediaWiki\Parser\Parser;
use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\Application\PageDependencyRecorder;

/**
 * Records a page a parse read as a template of the parsed page, as `{{REVISIONID:…}}` does, so that the
 * parsed page is refreshed when the read one changes. A page that does not exist is recorded too:
 * creating it refreshes the pages reading it, as for a red-linked template. The revision recorded is
 * the page's latest, the one NeoWiki reads.
 *
 * The parsed page itself is left out, as its own edits refresh it, and so is a title that cannot be a
 * page, such as a special page's.
 */
class ParserPageDependencyRecorder implements PageDependencyRecorder {

	public function __construct(
		private readonly Parser $parser,
	) {
	}

	public function recordDependencyOn( Title $page ): void {
		if ( !$page->canExist() || $this->isBeingParsed( $page ) ) {
			return;
		}

		$this->parser->getOutput()->addTemplate( $page, $page->getArticleID(), $page->getLatestRevID() );
	}

	private function isBeingParsed( Title $page ): bool {
		$parsedPage = $this->parser->getPage();

		return $parsedPage !== null && $page->isSamePageAs( $parsedPage );
	}

}
