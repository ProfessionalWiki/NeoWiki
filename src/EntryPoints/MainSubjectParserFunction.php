<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints;

use MediaWiki\Parser\Parser;
use ProfessionalWiki\NeoWiki\Application\PageSubjectsLookup;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\SubjectSlotReader;
use ProfessionalWiki\NeoWiki\Presentation\ViewHtmlBuilder;
use Throwable;

class MainSubjectParserFunction {

	public function __construct(
		private readonly PageSubjectsLookup $pageSubjectsLookup
	) {
	}

	/**
	 * @return string|array{0: string, noparse: true, isHTML: true}
	 */
	public function handle( Parser $parser, string ...$args ): string|array {
		$argument = $this->findArgument( $args );

		if ( $argument !== null ) {
			return '<div class="error">' . $parser->msg( 'neowiki-main-subject-error-argument', $argument )->escaped() . '</div>';
		}

		$mainSubjectId = $this->findMainSubjectId( $parser );

		if ( $mainSubjectId === null ) {
			return '';
		}

		return [
			ViewHtmlBuilder::mainSubjectInFullPlaceholderHtml( $mainSubjectId ),
			'noparse' => true,
			'isHTML' => true,
		];
	}

	/**
	 * Null too where the page's Subjects cannot be read: the page then shows without its Main Subject
	 * rather than failing, and the parse is not cached, since the failure may pass.
	 */
	private function findMainSubjectId( Parser $parser ): ?string {
		try {
			return $this->pageSubjectsOf( $parser )?->getMainSubject()?->getId()->text;
		} catch ( Throwable ) {
			$parser->getOutput()->updateCacheExpiry( 0 );
			return null;
		}
	}

	/**
	 * The Subjects of the revision being parsed. A preview parses the edited text as a revision of its
	 * own, which holds none of the page's Subjects, so it takes the page's current ones, as does a parse
	 * of no revision.
	 */
	private function pageSubjectsOf( Parser $parser ): ?PageSubjects {
		$revision = $parser->getRevisionRecordObject();

		if ( $revision === null || $parser->getOptions()->getIsPreview() ) {
			return $this->pageSubjectsLookup->getPageSubjects( new PageId( $parser->getTitle()->getArticleID() ) );
		}

		return SubjectSlotReader::read( $revision )?->getPageSubjects();
	}

	/**
	 * {{#main_subject:}} arrives as one empty argument, and a stray pipe as another.
	 *
	 * @param string[] $args
	 */
	private function findArgument( array $args ): ?string {
		foreach ( $args as $arg ) {
			if ( $arg !== '' ) {
				return $arg;
			}
		}

		return null;
	}

}
