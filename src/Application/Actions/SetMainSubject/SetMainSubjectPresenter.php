<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Actions\SetMainSubject;

interface SetMainSubjectPresenter {

	public function presentMainSubjectChanged(): void;

	public function presentNoChange(): void;

	public function presentSubjectNotFound(): void;

}
