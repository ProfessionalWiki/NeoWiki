<?php

declare( strict_types=1 );

namespace ProfessionalWiki\NeoWiki\Application\Queries\GetLayout;

use ProfessionalWiki\NeoWiki\Application\LayoutLookup;
use ProfessionalWiki\NeoWiki\Domain\Layout\LayoutName;
use ProfessionalWiki\NeoWiki\Presentation\LayoutPresentationSerializer;

class GetLayoutQuery {

	public function __construct(
		private GetLayoutPresenter $presenter,
		private LayoutLookup $layoutLookup,
		private LayoutPresentationSerializer $serializer,
	) {
	}

	public function execute( string $layoutName ): void {
		$layout = $this->layoutLookup->getLayout( new LayoutName( $layoutName ) );

		if ( $layout === null ) {
			$this->presenter->presentLayoutNotFound();
			return;
		}

		$this->presenter->presentLayout( $this->serializer->serialize( $layout ) );
	}

}
