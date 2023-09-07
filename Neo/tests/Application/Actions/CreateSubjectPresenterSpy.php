<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application\Actions;

use ProfessionalWiki\NeoWiki\Application\Actions\CreateSubject\CreateSubjectPresenter;

class CreateSubjectPresenterSpy implements CreateSubjectPresenter {

	public string $result = '';

	public function presentCreated( string $subjectId ): void {
		$this->result = $subjectId;
	}

	public function presentSubjectAlreadyExists(): void {
		$this->result = 'presentSubjectAlreadyExists';
	}

}
