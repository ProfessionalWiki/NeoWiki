<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki;

use InvalidArgumentException;
use MediaWiki\Permissions\Authority;
use ProfessionalWiki\NeoWiki\Application\ViewLookup;
use ProfessionalWiki\NeoWiki\Domain\View\View;
use ProfessionalWiki\NeoWiki\Domain\View\ViewName;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\ViewContent;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;

class WikiPageViewLookup implements ViewLookup {

	public function __construct(
		private readonly PageContentFetcher $pageContentFetcher,
		private readonly Authority $authority,
		private readonly ViewPersistenceDeserializer $viewDeserializer,
	) {
	}

	public function getView( ViewName $viewName ): ?View {
		$content = $this->getContent( $viewName );

		if ( $content === null ) {
			return null;
		}

		try {
			return $this->viewDeserializer->deserialize( $viewName, $content->getText() );
		}
		catch ( InvalidArgumentException ) {
			return null;
		}
	}

	private function getContent( ViewName $viewName ): ?ViewContent {
		$content = $this->pageContentFetcher->getPageContent(
			$viewName->getText(),
			$this->authority,
			NeoWikiExtension::NS_VIEW
		);

		if ( $content instanceof ViewContent ) {
			return $content;
		}

		if ( $content === null ) {
			return null;
		}

		throw new \LogicException( 'Unexpected content type: not a ViewContent' );
	}

}
