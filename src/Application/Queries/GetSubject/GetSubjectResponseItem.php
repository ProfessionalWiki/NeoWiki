<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Queries\GetSubject;

use ProfessionalWiki\NeoWiki\Domain\Page\PageIdentifiers;
use ProfessionalWiki\NeoWiki\Domain\Subject\StatementList;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;

readonly class GetSubjectResponseItem {

	public function __construct(
		public string $id,
		/**
		 * The stored label, absent on a Subject that has none. Read $displayName to show a Subject.
		 */
		public ?string $label,
		public string $displayName,
		/**
		 * Whether $displayName fell back to the Schema name, which is the one name nobody chose. A
		 * client cannot derive it by comparing $displayName with the Schema name: a Main Subject on a
		 * page titled after its Schema matches too, and that name was chosen.
		 */
		public bool $displayNameIsGenerated,
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
	 *
	 * The name someone chose is passed in rather than derived here, because only the caller holding
	 * the page knows whether the Subject is its Main Subject. Null means nobody chose one, which is
	 * what makes the name and the verdict on it one answer rather than two that could disagree.
	 */
	public static function fromSubject(
		Subject $subject,
		?PageIdentifiers $pageIdentifiers,
		?string $chosenName
	): self {
		return new self(
			id: $subject->id->text,
			label: $subject->getLabel()?->text,
			displayName: $chosenName ?? $subject->getSchemaName()->getText(),
			displayNameIsGenerated: $chosenName === null,
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
