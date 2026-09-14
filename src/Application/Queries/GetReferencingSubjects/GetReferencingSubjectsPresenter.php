<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Queries\GetReferencingSubjects;

interface GetReferencingSubjectsPresenter {

	public function presentReferencingSubjects( GetReferencingSubjectsResponse $response ): void;

}
