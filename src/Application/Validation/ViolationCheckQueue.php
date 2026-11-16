<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Validation;

use ProfessionalWiki\NeoWiki\Domain\Page\PageId;

interface ViolationCheckQueue {

	public function queuePageCheck( PageId $pageId ): void;

	/**
	 * Checks again every page publishing Subjects of the Schema, as after the Schema changed.
	 */
	public function queueSchemaCheck( string $schemaName ): void;

	/**
	 * Checks again every page whose Subjects relate to these Subjects, as after they appeared, went away
	 * or changed Schema.
	 */
	public function queueReferringPagesCheck( string ...$targetIds ): void;

	/**
	 * Checks again the pages recorded with Violations about these Subjects, as after they appeared: only
	 * those found them missing.
	 */
	public function queueNotFoundTargetCheck( string ...$targetIds ): void;

}
