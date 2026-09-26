<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Presentation;

use MediaWiki\Html\Html;
use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\Application\SubjectContentRepository;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectDisplayName;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\SubjectContent;
use Throwable;

class ViewHtmlBuilder {

	/**
	 * The Subjects read so far, by page and revision: a page view asks for them for both its heading
	 * and its Main Subject, and each read queries the database.
	 *
	 * @var array<string, ?PageSubjects>
	 */
	private array $pageSubjectsRead = [];

	public function __construct(
		private readonly SubjectContentRepository $subjectContentRepository
	) {
	}

	public function mainSubjectHtml( Title $title, ?int $revisionId ): string {
		$subject = $this->getPageSubjects( $title, $revisionId )?->getMainSubject();

		if ( $subject === null ) {
			return '';
		}

		return self::viewPlaceholderHtml( $subject->getId()->text );
	}

	public static function viewPlaceholderHtml( string $subjectId, ?string $layoutName = null ): string {
		$attributes = [
			'class' => 'ext-neowiki-view',
			'data-mw-neowiki-subject-id' => $subjectId,
		];

		if ( $layoutName !== null ) {
			$attributes['data-mw-neowiki-layout-name'] = $layoutName;
		}

		return Html::element( 'div', $attributes );
	}

	/**
	 * The Subject whose label heads the page in place of its title, or null where the title stands.
	 */
	public function subjectInPlaceOfPageTitle( Title $title, ?int $revisionId ): ?Subject {
		if ( !SubjectDisplayName::mayBeTitledBySubjectId( $title->getPrefixedText() ) ) {
			return null;
		}

		$pageSubjects = $this->getPageSubjects( $title, $revisionId );

		return $pageSubjects === null ? null : SubjectDisplayName::inPlaceOfPageTitle( $pageSubjects, $title->getPrefixedText() );
	}

	private function getPageSubjects( Title $title, ?int $revisionId ): ?PageSubjects {
		$key = $title->getPrefixedDBkey() . '#' . ( $revisionId ?? 'latest' );

		if ( !array_key_exists( $key, $this->pageSubjectsRead ) ) {
			$this->pageSubjectsRead[$key] = $this->readPageSubjects( $title, $revisionId );
		}

		return $this->pageSubjectsRead[$key];
	}

	/**
	 * Null too where the Subjects do not deserialize: the page view shows what it can rather than
	 * failing, so the page keeps its title and goes without its Main Subject.
	 */
	private function readPageSubjects( Title $title, ?int $revisionId ): ?PageSubjects {
		try {
			return $this->getSubjectContent( $title, $revisionId )?->getPageSubjects();
		} catch ( Throwable ) {
			return null;
		}
	}

	private function getSubjectContent( Title $title, ?int $revisionId ): ?SubjectContent {
		if ( $revisionId === null ) {
			return $this->subjectContentRepository->getSubjectContentByPageTitle( $title );
		}

		return $this->subjectContentRepository->getSubjectContentByRevisionId( $revisionId );
	}

}
