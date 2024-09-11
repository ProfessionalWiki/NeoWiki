<?php

declare( strict_types=1 );

namespace ProfessionalWiki\NeoWiki\Application\Actions\CreateSchema;

interface CreateSchemaPresenter {

	public function presentSchema( string $json ): void;

	public function presentInvalidArguments(): void;

	public function presentPermissionsDenied(): void;

	public function presentSchemaAlreadyExists(): void;

	public function presentSchemaCreationError( string|null $message ): void;

	public function presentInvalidTitle(): void;

	public function presentNoChanges(): void;

}
