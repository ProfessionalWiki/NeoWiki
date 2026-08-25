<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Subject;

use ProfessionalWiki\NeoWiki\Domain\Page\Page;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;

/**
 * The name to show for a Subject. A Subject need not have a label, so every surface that displays one
 * needs a value to fall back on, and they all need the same one.
 *
 * Mirrored by resources/ext.neowiki/src/domain/placeholderSubjectLabel.ts, which offers the same name
 * for a Subject that does not exist yet. Change one and change the other.
 */
class SubjectDisplayName {

	/**
	 * A Main Subject represents the page's own topic, so it falls back to the page name. Every other
	 * Subject falls back to its Schema name, since the page name would give all of them the same
	 * misleading name.
	 */
	public static function forSubject(
		?SubjectLabel $label,
		bool $isMainSubject,
		string $pageName,
		SchemaName $schemaName
	): string {
		return self::labelOrPageName( $label, $isMainSubject, $pageName ) ?? $schemaName->getText();
	}

	/**
	 * The name without the Schema tier: the stored label, else the page name for a Main Subject, else
	 * nothing. What the graph materializes, since the Schema name there would make every unnamed
	 * Subject of a Schema indistinguishable in query results while the Schema is already on the node.
	 */
	public static function labelOrPageName( ?SubjectLabel $label, bool $isMainSubject, string $pageName ): ?string {
		if ( $label !== null ) {
			return $label->text;
		}

		if ( $isMainSubject && $pageName !== '' ) {
			return $pageName;
		}

		return null;
	}

	/**
	 * The same rule for a Subject read alongside the other Subjects of its page, which is what knows
	 * whether it is the Main Subject.
	 */
	public static function forSubjectIn( Subject $subject, PageSubjects $pageSubjects, string $pageName ): string {
		return self::forSubject(
			label: $subject->getLabel(),
			isMainSubject: $pageSubjects->isMainSubject( $subject->getId() ),
			pageName: $pageName,
			schemaName: $subject->getSchemaName()
		);
	}

	/**
	 * The same rule for a Subject read off a whole Page, as the projectors hold one.
	 */
	public static function forSubjectOnPage( Subject $subject, Page $page ): string {
		return self::forSubjectIn( $subject, $page->getSubjects(), $page->getProperties()->getName() );
	}

}
