<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application;

use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Domain\Relation\Relation;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\SubjectContentRepository;

class SubjectResolver {

	public function __construct(
		private readonly SubjectContentRepository $subjectContentRepository,
		private readonly SubjectLookup $subjectLookup,
	) {
	}

	public function resolveById( string $subjectIdText ): ?Subject {
		if ( !SubjectId::isValid( $subjectIdText ) ) {
			return null;
		}

		try {
			return $this->subjectLookup->getSubject( new SubjectId( $subjectIdText ) );
		} catch ( \Exception ) {
			return null;
		}
	}

	public function resolveMainByPageName( string $pageName ): ?Subject {
		$title = Title::newFromText( $pageName );

		if ( $title === null ) {
			return null;
		}

		return $this->resolveMainByTitle( $title );
	}

	public function resolveMainByTitle( Title $title ): ?Subject {
		return $this->getPageSubjectsByTitle( $title )?->getMainSubject();
	}

	public function getPageSubjectsByTitle( Title $title ): ?PageSubjects {
		return $this->subjectContentRepository
			->getSubjectContentByPageTitle( $title )
			?->getPageSubjects();
	}

	public function resolveRelationLabel( Relation $relation ): string {
		try {
			$subject = $this->subjectLookup->getSubject( $relation->targetId );

			if ( $subject !== null ) {
				return $subject->getLabel()->text;
			}
		} catch ( \Exception ) {
			// Fall through to ID
		}

		return $relation->targetId->text;
	}

}
