<?php

declare( strict_types=1 );

namespace ProfessionalWiki\NeoWiki\Application\Queries\GetView;

use ProfessionalWiki\NeoWiki\Application\ViewLookup;
use ProfessionalWiki\NeoWiki\Domain\View\ViewName;
use ProfessionalWiki\NeoWiki\Presentation\ViewPresentationSerializer;

readonly class GetViewQuery {

	public function __construct(
		private GetViewPresenter $presenter,
		private ViewLookup $viewLookup,
		private ViewPresentationSerializer $serializer,
	) {
	}

	public function execute( string $viewName ): void {
		$view = $this->viewLookup->getView( new ViewName( $viewName ) );

		if ( $view === null ) {
			$this->presenter->presentViewNotFound();
			return;
		}

		$this->presenter->presentView( $this->serializer->serialize( $view ) );
	}

}
