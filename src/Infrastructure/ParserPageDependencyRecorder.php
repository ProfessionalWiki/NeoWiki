<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Infrastructure;

use Closure;
use MediaWiki\Page\PageReference;
use MediaWiki\Parser\Parser;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\Application\PageDependencyRecorder;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaReference;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;

/**
 * Records a page a parse depends on as a template of the parsed page, as `{{REVISIONID:…}}` does, so
 * that the parsed page is refreshed when that page changes. A page that does not exist is recorded too:
 * creating it refreshes the pages depending on it, as for a red-linked template. The revision recorded
 * is the page's latest, the one NeoWiki reads.
 *
 * The parsed page itself is left out, as its own edits refresh it, and so is a title that cannot be a
 * page, such as a special page's.
 */
class ParserPageDependencyRecorder implements PageDependencyRecorder {

	/**
	 * @param Closure(): ParserOutput $output
	 */
	private function __construct(
		private readonly Closure $output,
		private readonly ?PageReference $parsedPage,
	) {
	}

	/**
	 * Reads the parser's output at each record: Parsoid gives each transclusion on a page a new one,
	 * keeping the parser, and a recorder made for it, from one transclusion to the next.
	 */
	public static function forParser( Parser $parser ): self {
		return new self( $parser->getOutput( ... ), $parser->getPage() );
	}

	public static function forParserOutput( ParserOutput $output, PageReference $parsedPage ): self {
		return new self( static fn (): ParserOutput => $output, $parsedPage );
	}

	public function recordDependencyOn( Title $page ): void {
		if ( !$page->canExist() || $this->isBeingParsed( $page ) ) {
			return;
		}

		( $this->output )()->addTemplate( $page, $page->getArticleID(), $page->getLatestRevID() );
	}

	public function recordDependencyOnSchema( SchemaReference $schema ): void {
		$page = $schema->isLocal() ? Title::makeTitleSafe( NeoWikiExtension::NS_SCHEMA, $schema->name->getText() ) : null;

		if ( $page !== null ) {
			$this->recordDependencyOn( $page );
		}
	}

	private function isBeingParsed( Title $page ): bool {
		return $this->parsedPage !== null && $page->isSamePageAs( $this->parsedPage );
	}

}
