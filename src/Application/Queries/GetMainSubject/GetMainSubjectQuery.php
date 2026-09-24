<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Queries\GetMainSubject;

use ProfessionalWiki\NeoWiki\Application\PageIdentifiersResolver;
use ProfessionalWiki\NeoWiki\Application\PageReadAuthorizer;
use ProfessionalWiki\NeoWiki\Application\PageSubjectsLookup;
use ProfessionalWiki\NeoWiki\Application\Queries\GetSubject\GetSubjectResponseItem;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectDisplayName;

readonly class GetMainSubjectQuery {

	public function __construct(
		private GetMainSubjectPresenter $presenter,
		private PageSubjectsLookup $pageSubjectsLookup,
		private PageIdentifiersResolver $pageIdentifiersResolver,
		private PageReadAuthorizer $readAuthorizer,
	) {
	}

	public function execute( int $pageId ): void {
		$id = new PageId( $pageId );

		// A denied page takes exactly the path a page without a Main Subject takes, so the response
		// is byte-identical to absence and cannot be used to probe page readability (#1046).
		$pageSubjects = $this->readAuthorizer->authorizeReadByPageId( $id )
			? $this->pageSubjectsLookup->getPageSubjects( $id )
			: PageSubjects::newEmpty();

		$mainSubject = $pageSubjects->getMainSubject();

		$this->presenter->presentMainSubject(
			new GetMainSubjectResponse(
				pageId: $pageId,
				subject: $mainSubject === null ? null : $this->newResponseItem( $mainSubject, $pageSubjects, $id ),
			)
		);
	}

	/**
	 * The page asked about is the Main Subject's hosting page by definition, so its identifiers come
	 * from the page itself rather than from the subject-to-page index.
	 */
	private function newResponseItem( Subject $mainSubject, PageSubjects $pageSubjects, PageId $pageId ): GetSubjectResponseItem {
		$pageIdentifiers = $this->pageIdentifiersResolver->getIdentifiersOfPage( $pageId );

		return GetSubjectResponseItem::fromSubject(
			$mainSubject,
			$pageIdentifiers,
			SubjectDisplayName::labelOrPageName( $mainSubject, $pageSubjects, $pageIdentifiers?->getTitle() ?? '' )
		);
	}

}
