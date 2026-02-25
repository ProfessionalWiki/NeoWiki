<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Presentation;

use MediaWiki\Html\Html;
use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\SubjectContent;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\SubjectContentRepository;

class ViewHtmlBuilder {

	public function __construct(
		private readonly SubjectContentRepository $subjectContentRepository
	) {
	}

	public function mainSubjectHtml( Title $title, ?int $revisionId ): string {
		$content = $this->getSubjectContent( $title, $revisionId );
		$subject = $content?->getPageSubjects()->getMainSubject();

		if ( $subject === null ) {
			return '';
		}

		return Html::element( 'div', [
			'class' => 'ext-neowiki-view',
			'data-mw-neowiki-subject-id' => $subject->getId()->text,
		] );
	}

	public function pageHasSubjects( Title $title ): bool {
		return $this->subjectContentRepository
			->getSubjectContentByPageTitle( $title )
			?->hasSubjects() === true;
	}

	private function getSubjectContent( Title $title, ?int $revisionId ): ?SubjectContent {
		if ( $revisionId === null ) {
			return $this->subjectContentRepository->getSubjectContentByPageTitle( $title );
		}

		return $this->subjectContentRepository->getSubjectContentByRevisionId( $revisionId );
	}

}
