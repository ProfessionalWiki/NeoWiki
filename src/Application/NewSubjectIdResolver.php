<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application;

use InvalidArgumentException;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectIdParser;
use ProfessionalWiki\NeoWiki\Infrastructure\IdGenerator;

/**
 * The id a Subject about to be created gets: the one the caller minted for it, or a fresh one where
 * they minted none. Minting up front is what lets relations between Subjects be wired before any of
 * them exists, so every create endpoint offers it and they all have to answer the same questions
 * about a supplied id — which is why they ask them here.
 */
readonly class NewSubjectIdResolver {

	public function __construct(
		private SubjectIdParser $subjectIdParser,
		private IdGenerator $idGenerator,
		private PageIdentifiersLookup $pageIdentifiersLookup,
	) {
	}

	/**
	 * A Subject is only ever created in the local Source, so a caller-supplied id must be a local one.
	 *
	 * @throws InvalidArgumentException When $suppliedId is malformed or names another Source.
	 */
	public function resolve( ?string $suppliedId ): SubjectId {
		if ( $suppliedId === null ) {
			return SubjectId::createNew( $this->idGenerator );
		}

		$subjectId = $this->subjectIdParser->parseOrThrow( $suppliedId );

		if ( !$subjectId->isLocal() ) {
			throw new InvalidArgumentException( "Subjects can only be created in the local Source: '$suppliedId'" );
		}

		return $subjectId;
	}

	/**
	 * Best-effort global uniqueness check: the subject -> page index is read from a replica, so this
	 * can miss a Subject another request just created; ID entropy carries the rest (same posture as
	 * relation IDs). Worth asking only of an id a caller supplied — a freshly minted one is nobody's.
	 *
	 * Reads the unfiltered index on purpose, not SubjectHostingPageResolver: a collision with a Subject
	 * on a page the caller cannot read is still a collision, and a read-gated check would let a client
	 * mint an id that shadows a hidden Subject.
	 */
	public function isInUse( SubjectId $id ): bool {
		return $this->pageIdentifiersLookup->getPageIdOfSubject( $id ) !== null;
	}

}
