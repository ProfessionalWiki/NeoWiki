<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Subject;

use InvalidArgumentException;

/**
 * Turns the text form of a Subject id into a {@see SubjectId}, canonicalizing an id that names the
 * local Source explicitly (`localWikiId:s...`) to its bare form. One Subject therefore has one
 * identity, which is what the `->text`-keyed collections ({@see SubjectMap}, {@see SubjectIdList}) and
 * {@see SubjectId::equals()} rely on.
 *
 * Every id crossing a boundary — a REST path, a revision slot, a Lua call — is parsed here rather than
 * constructed directly, because only this class knows which Source key is the local one.
 */
readonly class SubjectIdParser {

	public function __construct(
		private string $localSourceKey
	) {
	}

	/**
	 * The parsed id, or null when $text is not a well-formed Subject id.
	 */
	public function parse( string $text ): ?SubjectId {
		try {
			return $this->parseOrThrow( $text );
		} catch ( InvalidArgumentException ) {
			return null;
		}
	}

	/**
	 * @throws InvalidArgumentException When $text is not a well-formed Subject id.
	 */
	public function parseOrThrow( string $text ): SubjectId {
		$id = new SubjectId( $text );

		if ( $id->source !== $this->localSourceKey ) {
			return $id;
		}

		// An id qualified with the local key is a local id, so what follows the key must satisfy the
		// local grammar. Checked here rather than by reparsing the remainder, which would read a
		// second colon as another Source key and quietly turn a malformed id into a different one.
		if ( !SubjectId::isValidLocalId( $id->localId ) ) {
			throw new InvalidArgumentException( "Subject ID has the wrong format: '$text'" );
		}

		return new SubjectId( $id->localId );
	}

	public function getLocalSourceKey(): string {
		return $this->localSourceKey;
	}

}
