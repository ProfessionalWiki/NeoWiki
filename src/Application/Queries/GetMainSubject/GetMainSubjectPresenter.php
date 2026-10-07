<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Queries\GetMainSubject;

interface GetMainSubjectPresenter {

	public function presentMainSubject( GetMainSubjectResponse $response ): void;

}
