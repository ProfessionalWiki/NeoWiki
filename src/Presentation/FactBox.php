<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Presentation;

use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\SubjectContentRepository;
use SpecialPage;
use Title;

class FactBox {

	public function __construct(
		private readonly TwigTemplateRenderer $templateRenderer,
		private readonly SubjectContentRepository $subjectContentRepository
	) {
	}

	public function htmlFor( Title $title ): string {
		$subjects = $this->subjectContentRepository->getSubjectContentByPageTitle( $title )?->getPageSubjects()->getAllSubjects() ?? new SubjectMap();

		return $this->templateRenderer->viewModelToString(
			'FactBox.html.twig',
			[
				'subjectCount' => $subjects->count(),
				'neoJsonUrl' => SpecialPage::getTitleFor( 'NeoJson', $title->getFullText() )->getFullURL(),
			]
		);
	}

}
