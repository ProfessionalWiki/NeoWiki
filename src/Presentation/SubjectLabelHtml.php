<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Presentation;

use MediaWiki\Html\Html;
use MediaWiki\Output\OutputPage;
use MediaWiki\Parser\Parser;
use MediaWiki\Title\Title;
use MessageLocalizer;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;

/**
 * A Subject's label with its id beside it, where the label stands in for a title: the heading of a page
 * titled by a Subject id and of Special:Subject, and links to such a page in Recent changes and
 * watchlists. The id tells apart Subjects that share a label, and names one that has none.
 */
class SubjectLabelHtml {

	public const string STYLE_MODULE = 'ext.neowiki.subjectLabel.styles';

	/**
	 * The browser tab gets the label alone, or the bracketed id of a Subject without one: a tab reading
	 * "No label defined" would not say which Subject it is.
	 */
	public static function headPage( OutputPage $out, ?string $label, SubjectId $subjectId ): void {
		$out->setPageTitle( self::withId( $out, $label, $subjectId->text ) );
		$out->setHTMLTitle(
			$out->msg( 'pagetitle' )
				->plaintextParams( SubjectNameMessage::from( $out, $subjectId, $label )->text() )
				->inContentLanguage()
		);
		$out->addModuleStyles( [ self::STYLE_MODULE ] );
	}

	/**
	 * Core builds an action's heading around the page title, as in "$1: Revision history", so the
	 * Subject's name takes the title's place and the action's wording stays. The title is there as
	 * text, or, for some actions from MediaWiki 1.46 on, as Parser::formatPageTitle() markup. The name
	 * stands alone, as in the browser tab: the page the action is reached from carries the id in its
	 * heading. A heading that does not name the page, such as an error, is left alone.
	 */
	public static function nameInHeading( OutputPage $out, Title $title, ?string $label, SubjectId $subjectId ): void {
		$heading = str_replace(
			[
				htmlspecialchars( $title->getPrefixedText() ),
				Parser::formatPageTitle( str_replace( '_', ' ', (string)$title->getNsText() ), ':', $title->getText() ),
			],
			Html::element( 'bdi', [], SubjectNameMessage::from( $out, $subjectId, $label )->text() ),
			$out->getPageTitle()
		);

		if ( $heading !== $out->getPageTitle() ) {
			$out->setPageTitle( $heading );
		}
	}

	/**
	 * Each part isolated, so a label in one script direction does not reorder the id beside it.
	 */
	public static function withId( MessageLocalizer $localizer, ?string $label, string $subjectId ): string {
		return self::label( $localizer, $label ) . $localizer->msg( 'word-separator' )->escaped() . Html::rawElement(
			'span',
			[ 'class' => 'ext-neowiki-subject-label-id' ],
			$localizer->msg( 'parentheses' )->rawParams( Html::element( 'bdi', [], $subjectId ) )->escaped()
		);
	}

	private static function label( MessageLocalizer $localizer, ?string $label ): string {
		if ( $label === null ) {
			return Html::element(
				'span',
				[ 'class' => 'ext-neowiki-subject-label-none' ],
				$localizer->msg( 'neowiki-subject-no-label' )->text()
			);
		}

		return Html::element( 'bdi', [], $label );
	}

}
