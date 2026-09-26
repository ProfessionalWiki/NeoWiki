<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Presentation;

use MediaWiki\Html\Html;
use MessageLocalizer;

/**
 * A Subject's label with its id beside it, where the label stands in for the title of a page titled by
 * a Subject id: the page's heading, and links to the page in Recent changes and watchlists. The id
 * tells apart pages whose Subjects share a label.
 */
class SubjectLabelHtml {

	public const string STYLE_MODULE = 'ext.neowiki.subjectLabel.styles';

	/**
	 * Each part isolated, so a label in one script direction does not reorder the id beside it.
	 */
	public static function withId( MessageLocalizer $localizer, string $label, string $subjectId ): string {
		return Html::element( 'bdi', [], $label ) . $localizer->msg( 'word-separator' )->escaped() . Html::rawElement(
			'span',
			[ 'class' => 'ext-neowiki-subject-label-id' ],
			$localizer->msg( 'parentheses' )->rawParams( Html::element( 'bdi', [], $subjectId ) )->escaped()
		);
	}

}
