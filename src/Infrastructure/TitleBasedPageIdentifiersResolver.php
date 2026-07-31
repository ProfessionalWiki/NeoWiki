<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Infrastructure;

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

		// The prefixed text is what the graph projection stores as the page name, so a Subject
		// served from either source carries the same title.
		return new PageIdentifiers(
			id: $pageId,
			title: $title->getPrefixedText(),
			namespaceId: $title->getNamespace(),
		);
	}

}
