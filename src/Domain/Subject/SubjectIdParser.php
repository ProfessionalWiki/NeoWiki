<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Subject;

/**
 * Parses Subject ID strings at system boundaries. An id that explicitly names
 * the local source canonicalizes to the bare form, so one Subject never has
 * two textual identities.
 */
class SubjectIdParser {

	public function __construct(
		private readonly string $localSourceKey
	) {
	}

	/**
	 * @throws \InvalidArgumentException
	 */
	public function parse( string $text ): SubjectId {
		$id = new SubjectId( $text );

		if ( $id->getSource() === $this->localSourceKey ) {
			return $this->newLocalId( $id->getLocalId(), $text );
		}

		return $id;
	}

	private function newLocalId( string $localId, string $originalText ): SubjectId {
		$bareId = new SubjectId( $localId );

		if ( $bareId->getSource() !== null ) {
			throw new \InvalidArgumentException( "Local Subject IDs must be bare: '$originalText'" );
		}

		return $bareId;
	}

}
