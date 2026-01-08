<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application\Queries\GetSchema;

use ProfessionalWiki\NeoWiki\Application\Queries\GetSchema\GetSchemaPresenter;

class GetSchemaPresenterSpy implements GetSchemaPresenter {

	public string $schemaJson = '';
	public bool $notFound = false;

	public function presentSchema( string $json ): void {
		$this->schemaJson = $json;
	}

	public function presentSchemaNotFound(): void {
		$this->notFound = true;
	}

}
