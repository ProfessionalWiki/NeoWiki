<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject;

use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Domain\Statement;
use ProfessionalWiki\NeoWiki\Domain\Subject\StatementList;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;

class SubjectContentDataSerializer {

	/**
	 * Mirrors @see SubjectContentDataDeserializer::deserialize
	 */
	public function serialize( PageSubjects $contentData ): string {
		return json_encode(
			[
				'mainSubject' => $contentData->getMainSubject()?->id->text,
				'subjects' => $this->serializeSubjects( $contentData->getAllSubjects() ),
			],
			JSON_PRETTY_PRINT
		);
	}

	private function serializeSubjects( SubjectMap $subjectMap ): object {
		$serializedSubjects = [];

		foreach ( $subjectMap->asArray() as $subject ) {
			$serialized = [];

			// A Subject nobody named stores no label.
			if ( $subject->label !== null ) {
				$serialized['label'] = $subject->label->text;
			}

			$serialized['schema'] = $subject->getSchemaName()->getText();
			$serialized['statements'] = $this->serializeStatementList( $subject->getStatements() );

			$serializedSubjects[$subject->id->text] = $serialized;
		}

		return (object)$serializedSubjects;
	}

	private function serializeStatementList( StatementList $statementList ): object {
		return (object)array_map(
			$this->serializeStatement( ... ),
			$statementList->asArray()
		);
	}

	private function serializeStatement( Statement $statement ): array {
		return [
			'propertyType' => $statement->getPropertyType(),
			'value' => $statement->getValue()->toScalars(),
		];
	}

}
