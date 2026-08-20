<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki;

use MediaWiki\Title\TitleFormatter;
use MediaWiki\Title\TitleValue;
use ProfessionalWiki\NeoWiki\Application\PageIdentifiersLookup;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Page\PageIdentifiers;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectIdList;
use stdClass;
use Wikimedia\Rdbms\IReadableDatabase;

/**
 * Resolves Subject ids through the `neowiki_subject_page` index (ADR 32).
 *
 * The index is joined to `page`, which is what makes the title and namespace current without the
 * index knowing about moves, and what makes the rows a deleted page leaves behind resolve to nothing.
 */
class DatabasePageIdentifiersLookup implements PageIdentifiersLookup {

	public function __construct(
		private readonly IReadableDatabase $db,
		private readonly TitleFormatter $titleFormatter,
	) {
	}

	public function getPageIdOfSubject( SubjectId $subjectId ): ?PageIdentifiers {
		return $this->getPageIdsOfSubjects( new SubjectIdList( [ $subjectId ] ) )[$subjectId->text] ?? null;
	}

	/**
	 * @return array<string, PageIdentifiers>
	 */
	public function getPageIdsOfSubjects( SubjectIdList $subjectIds ): array {
		$ids = $subjectIds->asStringArray();

		if ( $ids === [] ) {
			return [];
		}

		$result = $this->db->newSelectQueryBuilder()
			->select( [ 'nwsp_subject_id', 'page_id', 'page_namespace', 'page_title' ] )
			->from( DatabaseSubjectPageIndex::TABLE )
			->join( 'page', null, 'page_id = nwsp_page_id' )
			->where( [ 'nwsp_subject_id' => $ids ] )
			->orderBy( [ 'nwsp_subject_id', 'nwsp_page_id' ] )
			->caller( __METHOD__ )
			->fetchResultSet();

		$pageIdentifiers = [];

		/** @var stdClass $row */
		foreach ( $result as $row ) {
			// Ordered by page id, so the first row of each Subject is the lowest page id holding it.
			// An id on more than one page is what cross-wiki transfer produces (ADR 5); resolving it
			// the same way for every reader matters more than which page wins.
			$pageIdentifiers[$row->nwsp_subject_id] ??= new PageIdentifiers(
				id: new PageId( (int)$row->page_id ),
				title: $this->titleFormatter->getPrefixedText(
					new TitleValue( (int)$row->page_namespace, $row->page_title )
				),
				namespaceId: (int)$row->page_namespace,
			);
		}

		return $pageIdentifiers;
	}

}
