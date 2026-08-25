<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Source;

use Psr\Log\LoggerInterface;
use ProfessionalWiki\NeoWiki\Application\SubjectLookup;
use ProfessionalWiki\NeoWiki\Domain\Source\SourceRegistry;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectIdList;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;

/**
 * Resolves each Subject through the Source that produced it (ADR 23). Ids are grouped by Source before
 * being fetched, so a Source that can answer a whole list in one call still does.
 *
 * An id whose Source this wiki does not have resolves to no Subject, logged once per lookup: a page
 * naming a Source that was removed, renamed or never installed degrades where that Subject is read
 * rather than breaking.
 */
readonly class SourceRoutingSubjectLookup implements SubjectLookup {

	public function __construct(
		private SourceRegistry $sourceRegistry,
		private LoggerInterface $logger,
	) {
	}

	public function getSubject( SubjectId $id ): ?Subject {
		$source = $this->sourceRegistry->getSourceOf( $id );

		if ( $source === null ) {
			$this->logUnknownSources( [ $id->text ] );
			return null;
		}

		return $source->getSubject( $id );
	}

	public function getSubjects( SubjectIdList $subjectIds ): SubjectMap {
		$idsBySourceKey = [];
		$unresolvable = [];

		foreach ( $subjectIds->asArray() as $id ) {
			$sourceKey = $id->source ?? $this->sourceRegistry->getLocalSourceKey();

			if ( $this->sourceRegistry->getSource( $sourceKey ) === null ) {
				$unresolvable[] = $id->text;
				continue;
			}

			$idsBySourceKey[$sourceKey][] = $id;
		}

		if ( $unresolvable !== [] ) {
			$this->logUnknownSources( $unresolvable );
		}

		$subjects = new SubjectMap();

		foreach ( $idsBySourceKey as $sourceKey => $ids ) {
			$source = $this->sourceRegistry->getSource( (string)$sourceKey );

			if ( $source !== null ) {
				$subjects = $subjects->union( $source->getSubjects( new SubjectIdList( $ids ) ) );
			}
		}

		return $subjects;
	}

	/**
	 * @param string[] $idTexts
	 */
	private function logUnknownSources( array $idTexts ): void {
		$this->logger->warning(
			'NeoWiki: no registered Source for Subject id(s): ' . implode( ', ', $idTexts )
		);
	}

}
