<?php

declare( strict_types=1 );

namespace ProfessionalWiki\NeoWiki\Application\Queries\GetView;

interface GetViewPresenter {

	public function presentView( string $json ): void;

	public function presentViewNotFound(): void;

}
