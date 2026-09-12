<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Actions\CreateSubjectPage;

use ProfessionalWiki\NeoWiki\Application\Queries\GetSubject\GetSubjectResponseItem;
use ProfessionalWiki\NeoWiki\Domain\Page\PageIdentifiers;
use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;
use ProfessionalWiki\NeoWiki\Domain\Validation\Violation;

interface CreateSubjectPagePresenter {

	/**
	 * Receives the persisted Subject and the page created for it. A null Schema means the Subject
	 * names a Schema that does not exist.
	 *
	 * @param Violation[] $violations
	 */
	public function presentCreated(
		GetSubjectResponseItem $subject,
		PageIdentifiers $page,
		?Schema $schema,
		array $violations
	): void;

	/**
	 * Called when a page of the title the Subject would get is already there. Nothing is created;
	 * the caller decides whether to add the Subject to that page instead. Only the title they
	 * supplied goes back: which page holds it is not theirs to learn from a title they typed.
	 */
	public function presentPageTitleTaken( string $pageTitle ): void;

	/**
	 * Called when validation enforcement rejects the Subject the request would have created. No
	 * page is created either.
	 *
	 * @param Violation[] $violations
	 */
	public function presentValidationFailed( array $violations ): void;

	/**
	 * Called when the write did not land for a reason the caller cannot act on. The message is the
	 * one the write itself reported, where it reported any.
	 */
	public function presentPageNotCreated( string $pageTitle, ?string $errorMessage ): void;

}
