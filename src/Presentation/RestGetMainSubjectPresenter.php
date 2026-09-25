<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Presentation;

use ProfessionalWiki\NeoWiki\Application\Queries\GetMainSubject\GetMainSubjectPresenter;
use ProfessionalWiki\NeoWiki\Application\Queries\GetMainSubject\GetMainSubjectResponse;

class RestGetMainSubjectPresenter implements GetMainSubjectPresenter {

	private array $apiResponse = [];
	private readonly SubjectPresentationSerializer $subjectSerializer;

	public function __construct() {
		$this->subjectSerializer = new SubjectPresentationSerializer();
	}

	public function getJsonArray(): array {
		return $this->apiResponse;
	}

	public function presentMainSubject( GetMainSubjectResponse $response ): void {
		$this->apiResponse = [
			'pageId' => $response->pageId,
			'subject' => $response->subject === null ? null : $this->subjectSerializer->serialize( $response->subject ),
		];
	}

}
