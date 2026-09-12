<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Infrastructure;

use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
use ProfessionalWiki\NeoWiki\Application\PageIdentifiersResolver;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Page\PageIdentifiers;

class TitleBasedPageIdentifiersResolver implements PageIdentifiersResolver {

	public function __construct(
		private readonly TitleFactory $titleFactory
	) {
	}

	public function getIdentifiersOfPage( PageId $pageId ): ?PageIdentifiers {
		$title = $this->titleFactory->newFromID( $pageId->id );

		if ( $title === null ) {
			return null;
		}

		return $this->identifiersOf( $title, $pageId );
	}

	public function getIdentifiersOfTitle( string $pageTitle ): ?PageIdentifiers {
		$title = $this->titleFactory->newFromText( $pageTitle );

		if ( $title === null || !$title->exists() ) {
			return null;
		}

		return $this->identifiersOf( $title, new PageId( $title->getId() ) );
	}

	public function getMainNamespaceTitle( string $text ): ?string {
		$title = $this->titleFactory->newFromText( $text );

		// canExist() rules out the empty, the invalid, the special and the interwiki - the last
		// parses into the main namespace of another wiki and cannot be created here. hasFragment()
		// rules out "Rembrandt#1642", whose fragment the title would silently drop.
		if ( $title === null || !$title->canExist() || $title->hasFragment()
			|| $title->getNamespace() !== NS_MAIN ) {
			return null;
		}

		return $title->getPrefixedText();
	}

	/**
	 * The prefixed text is what the graph projection stores as the page name, so a Subject served
	 * from either source carries the same title.
	 */
	private function identifiersOf( Title $title, PageId $pageId ): PageIdentifiers {
		return new PageIdentifiers(
			id: $pageId,
			title: $title->getPrefixedText(),
			namespaceId: $title->getNamespace(),
		);
	}

}
