<?php

declare( strict_types=1 );

namespace ProfessionalWiki\NeoWiki\Application\Queries\GetLayout;

interface GetLayoutPresenter {

	public function presentLayout( string $json ): void;

	public function presentLayoutNotFound(): void;

}
