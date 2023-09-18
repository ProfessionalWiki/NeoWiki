<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki;

use Content;
use MalformedTitleException;
use MediaWiki\Permissions\Authority;
use MediaWiki\Revision\RevisionAccessException;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\SlotRecord;
use Title;
use TitleParser;
use TitleValue;

class PageContentFetcher {

	public function __construct(
		private readonly TitleParser $titleParser,
		private readonly RevisionLookup $revisionLookup
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

		$revision = $this->revisionLookup->getRevisionByTitle( $titleValue );

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
