<?php

declare( strict_types=1 );

namespace ProfessionalWiki\NeoWiki\Application\Actions\SaveSchema;

interface SaveSchemaPresenter {

	public function getJsonArray(): array;

	public function getJson(): string;

	public function presentSchema( array $data ): void;

	public function presentError( string $message ): void;

}
