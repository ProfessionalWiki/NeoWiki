<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Queries\GetPageSubjects;

interface GetPageSubjectsPresenter {

	public function presentPageSubjects( GetPageSubjectsResponse $response ): void;

}
