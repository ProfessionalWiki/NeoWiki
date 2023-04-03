<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Actions\CreateSubject;

interface CreateSubjectPresenter {

	public function presentCreated( string $subjectId ): void;

	public function presentInvalidRequest(): void;

}
