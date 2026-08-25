<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject;

use Psr\Log\LoggerInterface;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Subject\StatementList;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectLabel;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;

class SubjectContentDataDeserializer {

	public function __construct(
		private readonly StatementDeserializer $statementDeserializer,
		private readonly LoggerInterface $logger,
	) {
	}

	/**
	 * Mirrors @see SubjectContentDataSerializer::serialize
	 */
	public function deserialize( string $json ): PageSubjects {
		$jsonArray = json_decode( $json, true );
		$subjects = $this->deserializeSubjects( $jsonArray );

		$mainSubject = $jsonArray['mainSubject'] ?? null;

		if ( !is_string( $mainSubject ) || !$this->isLocalId( $mainSubject, 'mainSubject' ) ) {
			return new PageSubjects(
				null,
				$subjects,
			);
		}

		$mainSubjectId = new SubjectId( $mainSubject );

		return new PageSubjects(
			$subjects->getSubject( $mainSubjectId ),
			$subjects->without( $mainSubjectId ),
		);
	}

	private function deserializeSubjects( array $jsonArray ): SubjectMap {
		$subjects = [];

		foreach ( $jsonArray['subjects'] ?? [] as $id => $subject ) {
			// A Subject id that looks like a decimal integer comes back from json_decode as an int key.
			$id = (string)$id;

			if ( $this->isLocalId( $id, 'subject key' ) ) {
				$subjects[] = $this->deserializeSubject( $id, $subject );
			}
		}

		return new SubjectMap( ...$subjects );
	}

	/**
	 * A local slot holds local Subjects only (ADR 23): a Subject from another Source is fetched from
	 * that Source, never stored here. Anything else in the slot is skipped rather than refused, because
	 * imported or hand-edited content must still render — and because the subject-to-page index reads
	 * these same keys as bare local ids (ADR 32), so admitting one here would put a Subject in the graph
	 * that nothing can find its page for.
	 */
	private function isLocalId( string $id, string $field ): bool {
		if ( SubjectId::isValidLocalId( $id ) ) {
			return true;
		}

		$this->logger->warning(
			"NeoWiki: skipping $field '$id' in a Subject slot: not a Subject id of this wiki"
		);

		return false;
	}

	private function deserializeSubject( string $id, array $jsonArray ): Subject {
		return new Subject(
			id: new SubjectId( $id ),
			label: $this->deserializeLabel( $jsonArray['label'] ?? null ),
			schemaName: new SchemaName( $jsonArray['schema'] ),
			statements: $this->buildStatementList( $jsonArray ),
		);
	}

	private function deserializeLabel( mixed $label ): ?SubjectLabel {
		return is_string( $label ) ? SubjectLabel::fromText( $label ) : null;
	}

	private function buildStatementList( array $jsonArray ): StatementList {
		$statements = [];

		foreach ( $jsonArray['statements'] ?? [] as $propertyName => $value ) {
			if ( $value !== null ) {
				// A property named like a decimal integer comes back from json_decode as an int key.
				$statements[] = $this->statementDeserializer->deserialize( (string)$propertyName, $value );
			}
		}

		return new StatementList( $statements );
	}

	/**
	 * The ids the JSON holds, read without deserializing, so that a Subject too broken to deserialize
	 * still has an id. Ids no caller could ask about are left out, since nothing can be answered with
	 * them.
	 *
	 * Static and dependency-free: update.php rebuilds the subject -> page index on wikis whose NeoWiki
	 * configuration is not readable yet.
	 *
	 * @return string[]
	 */
	public static function deserializeSubjectIds( string $json ): array {
		// Cast so that content that is not a JSON object becomes an array without the key, leaving one
		// thing to check.
		$subjects = ( (array)json_decode( $json, true ) )['subjects'] ?? null;

		if ( !is_array( $subjects ) ) {
			return [];
		}

		// A Subject id that looks like a decimal integer comes back from json_decode as an int key.
		// The local Source's grammar, and the same rule deserialize() applies: only local ids are
		// stored in a local slot, and a qualified id is longer than the index column, so admitting one
		// would truncate rather than record it.
		return array_values( array_filter(
			array_map( 'strval', array_keys( $subjects ) ),
			SubjectId::isValidLocalId( ... )
		) );
	}

}
