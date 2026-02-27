<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Presentation;

use MediaWiki\Html\Html;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\SubjectContentRepository;

class FactBox {

	public function __construct(
		private readonly SubjectContentRepository $subjectContentRepository
	) {
	}

	public function htmlFor( Title $title ): string {
		$subjects = $this->subjectContentRepository->getSubjectContentByPageTitle( $title )?->getPageSubjects()->getAllSubjects() ?? new SubjectMap();

		$url = SpecialPage::getTitleFor( 'NeoJson', $title->getFullText() )->getFullURL();

		return Html::noticeBox(
			'This page defines ' . $subjects->count() . ' NeoWiki subjects.'
			. Html::element( 'br' )
			. Html::element( 'a', [ 'href' => $url ], 'View or edit JSON' ),
			''
		);
	}

}
