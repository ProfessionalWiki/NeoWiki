<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject;

use Closure;
use MediaWiki\Permissions\Authority;
use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\Application\PageReadAuthorizer;
use ProfessionalWiki\NeoWiki\Application\SubjectLabelLookupResult;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectDisplayName;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\DatabasePublishedSubjectIndex;
use stdClass;
use Wikimedia\Rdbms\IReadableDatabase;

/**
 * The Main Subject whose label stands in for the title of a page titled by a Subject id (ADR 31), as the
 * wiki publishes the page (ADR 37). What it reads is kept for the request, so the many links to one page
 * read it once, and a list can read all of its pages in one query up front.
 */
class SubjectInPlaceOfPageTitleLookup {

	/** @var array<string, ?SubjectLabelLookupResult> Keyed by prefixed database key, null where the title stands */
	private array $subjects = [];

	/** @var array<string, bool> Keyed by reader and page, so a list's many links to one page ask once */
	private array $readable = [];

	/**
	 * @param Closure(Authority): PageReadAuthorizer $newReadAuthorizer
	 */
	public function __construct(
		private readonly IReadableDatabase $db,
		private readonly Closure $newReadAuthorizer,
	) {
	}

	/**
	 * @param int[] $pageIds
	 */
	public function prefetch( array $pageIds ): void {
		if ( $pageIds !== [] ) {
			$this->read( [ 'page_id' => array_values( array_unique( $pageIds ) ) ] );
		}
	}

	/**
	 * Only for a reader who may read the page, since the label is its content.
	 */
	public function forPage( Title $title, Authority $reader ): ?SubjectLabelLookupResult {
		$key = $title->getPrefixedDBkey();

		if ( !array_key_exists( $key, $this->subjects ) ) {
			$this->subjects[$key] = null;
			$this->read( [ 'page_namespace' => $title->getNamespace(), 'page_title' => $title->getDBkey() ] );
		}

		$subject = $this->subjects[$key];

		if ( $subject === null || !$this->mayRead( $reader, $title ) ) {
			return null;
		}

		return $subject;
	}

	private function mayRead( Authority $reader, Title $title ): bool {
		$key = $reader->getUser()->getName() . '|' . $title->getPrefixedDBkey();
		$this->readable[$key] ??= ( $this->newReadAuthorizer )( $reader )->authorizeReadByPageTitle( $title );

		return $this->readable[$key];
	}

	/**
	 * A page without Subjects is read too, so that links to it do not read it again.
	 *
	 * @param array<string, int|string|int[]> $pageCondition
	 */
	private function read( array $pageCondition ): void {
		$rows = $this->db->newSelectQueryBuilder()
			->select( [ 'page_namespace', 'page_title', 'nws_subject_id', 'nws_display_name', 'nws_is_main' ] )
			->from( 'page' )
			->leftJoin( DatabasePublishedSubjectIndex::SUBJECT_TABLE, null, 'nws_page_id = page_id' )
			->where( $pageCondition )
			->caller( __METHOD__ )
			->fetchResultSet();

		$rowsByPage = [];

		/** @var stdClass $row */
		foreach ( $rows as $row ) {
			$rowsByPage[$row->page_namespace . ':' . $row->page_title][] = $row;
		}

		foreach ( $rowsByPage as $pageRows ) {
			$title = Title::makeTitle( (int)$pageRows[0]->page_namespace, (string)$pageRows[0]->page_title );
			$this->subjects[$title->getPrefixedDBkey()] = $this->subjectInPlaceOfTitle( $title, $pageRows );
		}
	}

	/**
	 * Only a labelled one: a link saying "No label defined" would not tell apart the pages it links to,
	 * and the title carries the id.
	 *
	 * @param stdClass[] $pageRows
	 */
	private function subjectInPlaceOfTitle( Title $title, array $pageRows ): ?SubjectLabelLookupResult {
		$subjectIds = array_map( static fn ( stdClass $row ): string => (string)$row->nws_subject_id, $pageRows );

		if ( !SubjectDisplayName::isTitledByIdAmong( $title->getPrefixedText(), $subjectIds ) ) {
			return null;
		}

		foreach ( $pageRows as $row ) {
			if ( (int)$row->nws_is_main === 1 && $row->nws_display_name !== null ) {
				return new SubjectLabelLookupResult(
					id: (string)$row->nws_subject_id,
					label: (string)$row->nws_display_name,
					pageTitle: $title->getPrefixedText()
				);
			}
		}

		return null;
	}

}
