<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Presentation;

use ProfessionalWiki\NeoWiki\Application\Queries\GetSubject\GetSubjectPresenter;
use ProfessionalWiki\NeoWiki\Application\Queries\GetSubject\GetSubjectResponse;
use ProfessionalWiki\NeoWiki\Application\Queries\GetSubject\GetSubjectResponseItem;

/**
 * Keeps the name of the Subject a {@see \ProfessionalWiki\NeoWiki\Application\Queries\GetSubject\GetSubjectQuery}
 * was asked for, for a caller that wants to name it and nothing more. Both answers come from the one
 * captured Subject, so they cannot disagree.
 */
class SubjectNamePresenter implements GetSubjectPresenter {

	private ?GetSubjectResponseItem $requestedSubject = null;

	public function presentSubject( GetSubjectResponse $response ): void {
		$this->requestedSubject = $response->subjects[$response->requestedId] ?? null;
	}

	public function presentSubjectNotFound(): void {
		$this->requestedSubject = null;
	}

	/**
	 * The name to show, or null when the query served no Subject — because the wiki holds none with
	 * that id, or because it is on a page the caller may not read (#1046).
	 */
	public function getDisplayName(): ?string {
		return $this->requestedSubject?->displayName;
	}

	/**
	 * Whether that name is the Schema-derived stand-in rather than one somebody chose, which every
	 * surface marks as such.
	 */
	public function displayNameIsGenerated(): bool {
		return $this->requestedSubject?->displayNameIsGenerated ?? false;
	}

}
