<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Validation;

use ProfessionalWiki\NeoWiki\Application\SubjectSchemaNamesLookup;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Persistence\PublishedSubjectIndex;

/**
 * Has Violations checked again whenever the Subjects a page publishes are recorded: every change to them,
 * whether a save, a deletion, an import or an approval, reaches the index. The page itself is checked, and
 * so are the pages relating to any of its Subjects that went away or changed Schema, and the pages that found
 * any of its Subjects missing that now appeared.
 */
readonly class ViolationCheckingPublishedSubjectIndex implements PublishedSubjectIndex {

	public function __construct(
		private PublishedSubjectIndex $index,
		private SubjectSchemaNamesLookup $schemaNamesLookup,
		private ViolationCheckQueue $checkQueue,
	) {
	}

	public function setSubjectsOfPage(
		PageId $pageId,
		string $pageName,
		PageSubjects $subjects,
		string $publishedTimestamp
	): void {
		$before = $this->schemaNamesLookup->getSchemaNamesOfSubjectsOn( $pageId );

		$this->index->setSubjectsOfPage( $pageId, $pageName, $subjects, $publishedTimestamp );

		$this->queueChecks( $pageId, $before, self::schemaNamesOf( $subjects ) );
	}

	public function removePage( PageId $pageId ): void {
		$before = $this->schemaNamesLookup->getSchemaNamesOfSubjectsOn( $pageId );

		$this->index->removePage( $pageId );

		$this->queueChecks( $pageId, $before, [] );
	}

	/**
	 * @param array<string, string> $before
	 * @param array<string, string> $after
	 */
	private function queueChecks( PageId $pageId, array $before, array $after ): void {
		$this->checkQueue->queuePageCheck( $pageId );

		$appeared = array_keys( array_diff_key( $after, $before ) );
		$wentAwayOrChangedSchema = array_keys( array_diff_assoc( $before, $after ) );

		if ( $appeared !== [] ) {
			$this->checkQueue->queueNotFoundTargetCheck( ...array_map( 'strval', $appeared ) );
		}

		if ( $wentAwayOrChangedSchema !== [] ) {
			$this->checkQueue->queueReferringPagesCheck( ...array_map( 'strval', $wentAwayOrChangedSchema ) );
		}
	}

	/**
	 * @return array<string, string>
	 */
	private static function schemaNamesOf( PageSubjects $subjects ): array {
		$schemaNames = [];

		foreach ( $subjects->getAllSubjects()->asArray() as $subject ) {
			$schemaNames[$subject->id->text] = $subject->getSchemaReference()->name->getText();
		}

		return $schemaNames;
	}

}
