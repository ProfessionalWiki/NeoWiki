<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application;

use ProfessionalWiki\NeoWiki\Domain\Source\SourceRegistry;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use Psr\Log\LoggerInterface;

/**
 * Read-side Subject lookup that routes each id to the Source its key resolves to. An id whose
 * source key is not registered degrades to not-found (null) plus one logged warning, never
 * fatally (ADR 27). For bare ids this is the local lookup with the registry indirection in front.
 */
readonly class SourceRoutingSubjectLookup implements SubjectLookup {

	public function __construct(
		private SourceRegistry $sourceRegistry,
		private LoggerInterface $logger,
	) {
	}

	public function getSubject( SubjectId $subjectId ): ?Subject {
		$source = $this->sourceRegistry->getSourceForId( $subjectId );

		if ( $source === null ) {
			$this->logger->warning(
				'Subject lookup for an unregistered Source key.',
				[ 'sourceKey' => $subjectId->getSource(), 'subjectId' => $subjectId->text ]
			);
			return null;
		}

		return $source->getSubject( $subjectId );
	}

}
