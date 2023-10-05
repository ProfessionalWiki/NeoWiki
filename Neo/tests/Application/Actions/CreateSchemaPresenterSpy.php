<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application\Actions;

use ProfessionalWiki\NeoWiki\Application\Actions\CreateSchema\CreateSchemaPresenter;

class CreateSchemaPresenterSpy implements CreateSchemaPresenter {

	public bool $presentedInvalidTitle = false;

	public function presentSchema( string $json ): void {
		// TODO: Implement presentSchema() method.
	}

	public function presentInvalidArguments(): void {
		// TODO: Implement presentInvalidArguments() method.
	}

	public function presentPermissionsDenied(): void {
		// TODO: Implement presentPermissionsDenied() method.
	}

	public function presentSchemaAlreadyExists(): void {
		// TODO: Implement presentSchemaAlreadyExists() method.
	}

	public function presentSchemaCreationError( ?string $message ): void {
		// TODO: Implement presentSchemaCreationError() method.
	}

	public function presentInvalidTitle(): void {
		$this->presentedInvalidTitle = true;
	}

	public function presentNoChanges(): void {
		// TODO: Implement presentNoChanges() method.
	}

}
