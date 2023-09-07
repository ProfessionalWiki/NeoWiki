<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\Parser;

use Html;
use MediaWiki\Page\PageReference;
use Parser;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\SubjectContentRepository;
use Title;

class InfoboxFunction {

	public function __construct(
		private SubjectContentRepository $repository
	) {
	}

	/**
	 * @return array<mixed, mixed>
	 */
	public function handleParserFunctionCall( Parser $parser, string ...$arguments ): array {
		 $parser->getOutput()->addModules( [ 'ext.neowiki.table-editor' ] );

		if ( $arguments[0] !== '' ) {
			$subjectId = $arguments[0];
		} else {
			$subjectId = $this->getPageMainSubjectId( $parser->getPage() );
		}

		return $this->buildParserFunctionHtmlResponse(
			Html::element(
				'div',
				[
					'class' => 'nwInfoboxLoader',
					'data-subject-id' => $subjectId,
				]
			)
		);
	}

	private function buildParserFunctionHtmlResponse( string $html ): array {
		return [
			$html,
			'noparse' => true,
			'isHTML' => true,
		];
	}

	private function getPageMainSubjectId( PageReference $page ): string {
		return $this->repository->getSubjectContentByPageTitle( Title::castFromPageReference( $page ) )
			?->getPageSubjects()
			->getMainSubject()
			?->id->text ?? '';
	}

}
