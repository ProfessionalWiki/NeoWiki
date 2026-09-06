<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Queries\GetSubject;

use ProfessionalWiki\NeoWiki\Domain\Page\PageIdentifiers;
use ProfessionalWiki\NeoWiki\Domain\Subject\StatementList;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectDisplayName;

readonly class GetSubjectResponseItem {

	public function __construct(
		public string $id,
		/**
		 * The stored label, absent on a Subject that has none.
		 */
		public ?string $label,
		/**
		 * The name to show, for clients that would rather not derive it.
		 */
		public string $displayName,
		/**
		 * Whether the hosting page treats this Subject as its own topic, which is what decides
		 * the name of a Subject without a label. Together with $label and $pageTitle it is what a
		 * client needs to derive that name itself. It cannot be read off $displayName: a Main
		 * Subject on a page titled after its Schema is named its Schema name too.
		 */
		public bool $isMainSubject,
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
	 * $pageName is a parameter of its own because a caller that withholds the identifiers from the
	 * response still names the Subject from them.
	 */
	public static function fromSubject(
		Subject $subject,
		?PageIdentifiers $pageIdentifiers,
		bool $isMainSubject,
		string $pageName
	): self {
		return new self(
			id: $subject->id->text,
			label: $subject->getLabel()?->text,
			displayName: SubjectDisplayName::forSubject(
				$subject->getLabel(),
				$isMainSubject,
				$pageName,
				$subject->getSchemaName()
			),
			isMainSubject: $isMainSubject,
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
