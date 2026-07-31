<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Queries\GetSubject;

use ProfessionalWiki\NeoWiki\Domain\Page\PageIdentifiers;
use ProfessionalWiki\NeoWiki\Domain\Subject\StatementList;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;

readonly class GetSubjectResponseItem {

	public function __construct(
		public string $id,
		public string $label,
		public string $schemaName,
		/**
		 * @var array<string, mixed>
		 */
		public array $statements,
		public ?int $pageId,
		public ?string $pageTitle,
		public ?int $pageNamespaceId,
	) {
	}

	/**
	 * Null page identifiers leave the page fields unset, which is how a Subject whose hosting page
	 * cannot be resolved, or whose identifiers were not requested, is represented.
	 */
	public static function fromSubject( Subject $subject, ?PageIdentifiers $pageIdentifiers ): self {
		return new self(
			id: $subject->id->text,
			label: $subject->label->text,
			schemaName: $subject->getSchemaName()->getText(),
			statements: self::arrayifyStatements( $subject->getStatements() ),
			pageId: $pageIdentifiers?->getId()->id,
			pageTitle: $pageIdentifiers?->getTitle(),
			pageNamespaceId: $pageIdentifiers?->getNamespaceId(),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function arrayifyStatements( StatementList $statements ): array {
		$array = [];

		foreach ( $statements->asArray() as $statement ) {
			$array[$statement->getPropertyName()->text] = [
				'propertyType' => $statement->getPropertyType(),
				'value' => $statement->getValue()->toScalars()
			];
		}

		return $array;
	}

}
