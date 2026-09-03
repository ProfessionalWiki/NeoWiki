<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject;

use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Subject\StatementList;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectHeader;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectLabel;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;

class SubjectContentDataDeserializer {

	public function __construct(
		private readonly StatementDeserializer $statementDeserializer,
	) {
	}

	/**
	 * Mirrors @see SubjectContentDataSerializer::serialize
	 */
	public function deserialize( string $json ): PageSubjects {
		$jsonArray = json_decode( $json, true );
		$subjects = $this->deserializeSubjects( $jsonArray );

		if ( ( $jsonArray['mainSubject'] ?? null ) === null ) {
			return new PageSubjects(
				null,
				$subjects,
			);
		}

		$mainSubjectId = new SubjectId( $jsonArray['mainSubject'] );

		return new PageSubjects(
			$subjects->getSubject( $mainSubjectId ),
			$subjects->without( $mainSubjectId ),
		);
	}

	private function deserializeSubjects( array $jsonArray ): SubjectMap {
		$subjectsArray = $jsonArray['subjects'] ?? [];

		return new SubjectMap(
			...array_map(
				fn( string $id, array $subject ) => $this->deserializeSubject( $id, $subject ),
				array_keys( $subjectsArray ),
				$subjectsArray,
			)
		);
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
	 * The headers the JSON holds, read without deserializing, so a Subject too broken to deserialize is
	 * still indexed under the name it claims (ADR 32). Ids that are not well-formed are left out, since
	 * no caller could ask about them.
	 *
	 * Static and dependency-free: update.php fills the subject -> page index on wikis whose NeoWiki
	 * configuration is not readable yet.
	 *
	 * @return SubjectHeader[]
	 */
	public static function deserializeSubjectHeaders( string $json ): array {
		$data = (array)json_decode( $json, true );
		$subjects = $data['subjects'] ?? null;

		if ( !is_array( $subjects ) ) {
			return [];
		}

		$mainSubjectId = self::presentStringOrNull( $data['mainSubject'] ?? null );

		$headers = [];

		foreach ( $subjects as $id => $subject ) {
			// A Subject id that looks like a decimal integer comes back from json_decode as an int key.
			$idText = (string)$id;

			if ( !SubjectId::isValid( $idText ) ) {
				continue;
			}

			$fields = is_array( $subject ) ? $subject : [];

			$headers[] = new SubjectHeader(
				id: $idText,
				schemaName: self::presentStringOrNull( $fields['schema'] ?? null ),
				label: self::presentStringOrNull( $fields['label'] ?? null ),
				isMainSubject: $idText === $mainSubjectId,
			);
		}

		return $headers;
	}

	/**
	 * Whitespace is absence, as it is for {@see SubjectLabel::fromText} and {@see SchemaName}: both
	 * come back null, so a reader of the index never has to tell the two apart. The text itself is
	 * kept as the slot wrote it, again like SubjectLabel.
	 */
	private static function presentStringOrNull( mixed $value ): ?string {
		return is_string( $value ) && trim( $value ) !== '' ? $value : null;
	}

}
