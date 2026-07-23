<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Actions\SetMainSubject;

interface SetMainSubjectPresenter {

	public function presentMainSubjectChanged(): void;

	public function presentNoChange(): void;

	public function presentSubjectNotFound(): void;

	/**
	 * Called when the target page cannot be resolved: it does not exist, or the caller may not read
	 * it. Both take this one shape so a caller cannot tell a hidden page apart from an absent one by
	 * sweeping page ids (see PageReadAuthorizer).
	 */
	public function presentPageNotFound(): void;

}
