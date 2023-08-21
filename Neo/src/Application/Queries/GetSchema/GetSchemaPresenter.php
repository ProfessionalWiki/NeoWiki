<?php

declare( strict_types=1 );

namespace ProfessionalWiki\NeoWiki\Application\Queries\GetSchema;

interface GetSchemaPresenter {

	public function presentSchema( string $json ): void;

	public function presentSchemaNotFound(): void;

}
