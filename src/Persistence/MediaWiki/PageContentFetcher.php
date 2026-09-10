<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki;

use MediaWiki\Content\Content;
use MediaWiki\Permissions\Authority;
use MediaWiki\Revision\RevisionAccessException;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Title\MalformedTitleException;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleParser;
use MediaWiki\Title\TitleValue;
use ProfessionalWiki\NeoWiki\Application\RevisionPolicy;

/**
 * The content a page publishes: its latest revision, unless a registered revision policy substitutes
 * another. Everything read through here — Schemas, Layouts, Mappings and the on-wiki configuration
 * page — is configuration the whole wiki reads, so on a wiki running an approval extension an
 * unapproved edit to one takes effect only once approved.
 */
class PageContentFetcher {

	public function __construct(
		private readonly TitleParser $titleParser,
		private readonly RevisionLookup $revisionLookup,
		private readonly RevisionPolicy $revisionPolicy
	) {
	}

	public function getPageContent(
		string|Title $pageTitle,
		Authority $authority,
		int $defaultNamespace = NS_MAIN,
		string $slotName = SlotRecord::MAIN
	): ?Content {
		try {
			$titleValue = $this->normalizeTitle( $pageTitle, $defaultNamespace );
		} catch ( MalformedTitleException ) {
			return null;
		}

		$revision = $this->revisionLookup->getRevisionByTitle( Title::newFromLinkTarget( $titleValue ) );
		$revision = $revision === null ? null : $this->revisionPolicy->publishedRevision( $revision );

		try {
			return $revision?->getContent( $slotName, RevisionRecord::FOR_THIS_USER, $authority );
		}
		catch ( RevisionAccessException ) {
			return null;
		}
	}

	/**
	 * @throws MalformedTitleException
	 */
	private function normalizeTitle( string|Title $pageTitle, int $defaultNamespace ): TitleValue {
		if ( is_string( $pageTitle ) ) {
			return $this->titleParser->parseTitle( $pageTitle, $defaultNamespace );
		}

		$value = $pageTitle->getTitleValue();

		if ( $value === null ) {
			throw new MalformedTitleException( $pageTitle->getPrefixedText() );
		}

		return $value;
	}

}
