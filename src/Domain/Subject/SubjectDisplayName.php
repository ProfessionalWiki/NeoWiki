<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Subject;

use ProfessionalWiki\NeoWiki\Domain\Page\Page;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;

/**
 * The name to show for a Subject. A Subject need not have a label, so every surface that displays one
 * needs a value to fall back on, and they all need the same one.
 *
 * Mirrored by resources/ext.neowiki/src/domain/chosenSubjectName.ts, which answers the same question
 * for a Subject that does not exist yet. Change one and change the other.
 */
class SubjectDisplayName {

	/**
	 * A Main Subject represents the page's own topic, so it falls back to the page name. Every other
	 * Subject falls back to its Schema name, since the page name would give all of them the same
	 * misleading name.
	 */
	public static function forSubject( Subject $subject, bool $isMainSubject, string $pageName ): string {
		return self::labelOrPageName( $subject, $isMainSubject, $pageName )
			?? $subject->getSchemaName()->getText();
	}

	/**
	 * The name without the Schema tier: the stored label, else the page name for a Main Subject, else
	 * nothing. What the graph materializes, since the Schema name there would make every unnamed
	 * Subject of a Schema indistinguishable in query results while the Schema is already on the node.
	 */
	public static function labelOrPageName( Subject $subject, bool $isMainSubject, string $pageName ): ?string {
		return self::chosenName( $subject->getLabel(), $isMainSubject, $pageName, [ $subject->getId()->text ] );
	}

	/**
	 * The same rule for a Subject read alongside the other Subjects of its page, which is what knows
	 * whether it is the Main Subject, and which of the page's Subjects could have titled it.
	 */
	public static function forSubjectIn( Subject $subject, PageSubjects $pageSubjects, string $pageName ): string {
		return self::labelOrPageNameIn( $subject, $pageSubjects, $pageName )
			?? $subject->getSchemaName()->getText();
	}

	/**
	 * labelOrPageName() for a Subject read alongside the other Subjects of its page, which is what
	 * knows whether it is the Main Subject, and which of the page's Subjects could have titled it.
	 */
	public static function labelOrPageNameIn( Subject $subject, PageSubjects $pageSubjects, string $pageName ): ?string {
		return self::chosenName(
			label: $subject->getLabel(),
			isMainSubject: $pageSubjects->isMainSubject( $subject->getId() ),
			pageName: $pageName,
			pageSubjectIds: $pageSubjects->getAllSubjects()->getIdsAsTextArray()
		);
	}

	/**
	 * @param string[] $pageSubjectIds The ids of the Subjects the page holds, as far as the caller
	 *   knows them: all of them where the page was read as a whole, and the Subject's own id where
	 *   it was read by itself.
	 */
	private static function chosenName(
		?SubjectLabel $label,
		bool $isMainSubject,
		string $pageName,
		array $pageSubjectIds
	): ?string {
		if ( $label !== null ) {
			return $label->text;
		}

		if ( $isMainSubject && $pageName !== '' && !self::isTitledBySubjectOnIt( $pageName, $pageSubjectIds ) ) {
			return $pageName;
		}

		return null;
	}

	/**
	 * Whether the page was titled by the system rather than by anyone: entity-first creation titles a
	 * page by the id of the Subject it creates when no label gives it a title (ADR 31). Any Subject
	 * the page holds counts, not only the one being named: which Subject such a page carries can
	 * change, while the title stays as unchosen as it was.
	 *
	 * The comparison is against the ids the page holds rather than against the shape of an id,
	 * because ordinary titles have that shape too - Standardization is fifteen letters starting with
	 * an s - and naming a page after one must not unname its Subject.
	 *
	 * The first letter is set aside, because a wiki that capitalizes page titles - the default -
	 * stores such a page under an upper-case S. Nothing else of an id can differ: its grammar
	 * (ADR 14) is ASCII that MediaWiki leaves alone.
	 *
	 * @param string[] $pageSubjectIds
	 */
	private static function isTitledBySubjectOnIt( string $pageName, array $pageSubjectIds ): bool {
		return in_array( lcfirst( $pageName ), $pageSubjectIds, true );
	}

	/**
	 * The same rule for a Subject read off a whole Page, as the projectors hold one.
	 */
	public static function forSubjectOnPage( Subject $subject, Page $page ): string {
		return self::forSubjectIn( $subject, $page->getSubjects(), $page->getProperties()->getName() );
	}

}
