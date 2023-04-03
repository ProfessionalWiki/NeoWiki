<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Queries\GetSubject;

interface GetSubjectPresenter {

	public function presentSubject( GetSubjectResponse $response ): void;

	public function presentSubjectNotFound(): void;

}
