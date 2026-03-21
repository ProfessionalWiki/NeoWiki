<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints;

use MediaWiki\Parser\Parser;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\SubjectContentRepository;
use ProfessionalWiki\NeoWiki\Presentation\ViewHtmlBuilder;

class ViewParserFunction {

	public function __construct(
		private readonly SubjectContentRepository $subjectContentRepository
	) {
	}

	/**
	 * @return string|array{0: string, noparse: true, isHTML: true}
	 */
	public function handle( Parser $parser, string $subjectId = '', string $layoutName = '' ): string|array {
		$subjectId = trim( $subjectId );
		$layoutName = trim( $layoutName );

		$resolvedSubjectId = $subjectId !== '' ? $subjectId : $this->resolveMainSubjectId( $parser );

		if ( $resolvedSubjectId === null ) {
			return '';
		}

		return [
			ViewHtmlBuilder::viewPlaceholderHtml( $resolvedSubjectId, $layoutName !== '' ? $layoutName : null ),
			'noparse' => true,
			'isHTML' => true,
		];
	}

	private function resolveMainSubjectId( Parser $parser ): ?string {
		$title = $parser->getTitle();

		if ( $title === null ) {
			return null;
		}

		$subject = $this->subjectContentRepository
			->getSubjectContentByPageTitle( $title )
			?->getPageSubjects()
			->getMainSubject();

		return $subject?->getId()->text;
	}

}
