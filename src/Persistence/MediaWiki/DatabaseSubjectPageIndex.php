<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki;

use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectHeader;
use ProfessionalWiki\NeoWiki\Persistence\SubjectPageIndex;
use stdClass;
use Wikimedia\Rdbms\IDatabase;

/**
 * Maintains `neowiki_subject_page` on the wiki's primary database (ADR 32).
 *
 * Writes go through the connection the surrounding operation is already using, so indexing an edit joins
 * the transaction that writes its revision: the index commits with that revision or not at all, and can
 * never hold a Subject set no revision has. The delete, undelete and import paths are hooked outside that
 * atomic section, but a web request holds the whole operation in one transaction (DBO_TRX), so a failure
 * there aborts the operation rather than leaving it committed. A CLI maintenance script runs without that
 * transaction, so there the write is best-effort and a failure leaves the index wrong until a rebuild.
 */
class DatabaseSubjectPageIndex implements SubjectPageIndex {

	public const string TABLE = 'neowiki_subject_page';

	private const array COLUMNS = [
		'nwsp_subject_id',
		'nwsp_page_id',
		'nwsp_schema',
		'nwsp_label',
		'nwsp_is_main',
	];

	public function __construct(
		private readonly IDatabase $db,
	) {
	}

	/**
	 * @param SubjectHeader[] $subjectHeaders
	 */
	public function setSubjectsOfPage( PageId $pageId, array $subjectHeaders ): void {
		$wanted = $this->rowsFor( $pageId, $subjectHeaders );
		$stored = $this->rowsOfPage( $pageId );

		// Most pages hold no Subjects and most edits change none, and this runs inside the transaction
		// that writes the revision. A page whose rows already say this is left alone, so the common edit
		// touches the index not at all. The comparison covers every column, so renaming a Subject or
		// making it the Main one is a change the index sees.
		//
		// Reading them takes no lock, which is safe because PageUpdater derives the parent revision from
		// an equally unlocked READ_LATEST read taken first, then CAS-compares it against
		// lockAndGetLatest() before writing. A read stale enough to matter here therefore implies a
		// stale parent revision, which is already refused as an edit conflict.
		if ( $stored === $wanted ) {
			return;
		}

		// Replacing the rows is one step. The maintenance rebuild runs under CLI without DBO_TRX, so
		// without this the delete and the insert commit separately, leaving a window in which the page's
		// Subjects resolve to nothing. On the edit path this nests inside PageUpdater's atomic section,
		// where it is bookkeeping that issues no SQL of its own.
		$this->db->startAtomic( __METHOD__ );

		if ( $stored !== [] ) {
			$this->removeSubjectsOfPage( $pageId, array_column( $stored, 'nwsp_subject_id' ) );
		}

		if ( $wanted !== [] ) {
			$this->insertRows( $wanted );
		}

		$this->db->endAtomic( __METHOD__ );
	}

	/**
	 * Deletes by primary key rather than by page id, which is what {@see removePage} does. Deleting by
	 * page id alone walks the `nwsp_page_id` index and gap-locks the range around it, so two pages saved
	 * at once whose ids sit next to each other in that index block one another, and can deadlock. Naming
	 * both key columns turns each row into its own lookup, and Subject ids are random, so the rows a page
	 * holds are scattered rather than adjacent. MediaWiki core does the same for `page_restrictions`
	 * (T214035).
	 *
	 * @param string[] $subjectIds
	 */
	private function removeSubjectsOfPage( PageId $pageId, array $subjectIds ): void {
		$this->db->newDeleteQueryBuilder()
			->deleteFrom( self::TABLE )
			->where( [ 'nwsp_subject_id' => $subjectIds, 'nwsp_page_id' => $pageId->id ] )
			->caller( __METHOD__ )
			->execute();
	}

	/**
	 * @param list<array{nwsp_subject_id: string, nwsp_page_id: int, nwsp_schema: ?string, nwsp_label: ?string, nwsp_is_main: int}> $rows
	 */
	private function insertRows( array $rows ): void {
		$this->db->newInsertQueryBuilder()
			->insertInto( self::TABLE )
			->rows( $rows )
			->caller( __METHOD__ )
			->execute();
	}

	/**
	 * @param SubjectHeader[] $subjectHeaders
	 * @return list<array{nwsp_subject_id: string, nwsp_page_id: int, nwsp_schema: ?string, nwsp_label: ?string, nwsp_is_main: int}>
	 */
	private function rowsFor( PageId $pageId, array $subjectHeaders ): array {
		return self::sortedBySubjectId( array_map(
			static fn ( SubjectHeader $header ): array => [
				'nwsp_subject_id' => $header->id,
				'nwsp_page_id' => $pageId->id,
				'nwsp_schema' => $header->schemaName,
				'nwsp_label' => $header->label,
				'nwsp_is_main' => $header->isMainSubject ? 1 : 0,
			],
			$subjectHeaders
		) );
	}

	/**
	 * @return list<array{nwsp_subject_id: string, nwsp_page_id: int, nwsp_schema: ?string, nwsp_label: ?string, nwsp_is_main: int}>
	 */
	private function rowsOfPage( PageId $pageId ): array {
		$result = $this->db->newSelectQueryBuilder()
			->select( self::COLUMNS )
			->from( self::TABLE )
			->where( [ 'nwsp_page_id' => $pageId->id ] )
			->caller( __METHOD__ )
			->fetchResultSet();

		$rows = [];

		/** @var stdClass $row */
		foreach ( $result as $row ) {
			$rows[] = [
				'nwsp_subject_id' => (string)$row->nwsp_subject_id,
				'nwsp_page_id' => (int)$row->nwsp_page_id,
				// The database answers with strings, and a column it holds as NULL stays null, which is
				// what the header side offers for a Subject whose slot named neither.
				'nwsp_schema' => $row->nwsp_schema === null ? null : (string)$row->nwsp_schema,
				'nwsp_label' => $row->nwsp_label === null ? null : (string)$row->nwsp_label,
				'nwsp_is_main' => (int)$row->nwsp_is_main,
			];
		}

		return self::sortedBySubjectId( $rows );
	}

	/**
	 * Sorted here rather than by the database, so the comparison does not depend on the column's
	 * collation.
	 *
	 * @param list<array{nwsp_subject_id: string, nwsp_page_id: int, nwsp_schema: ?string, nwsp_label: ?string, nwsp_is_main: int}> $rows
	 * @return list<array{nwsp_subject_id: string, nwsp_page_id: int, nwsp_schema: ?string, nwsp_label: ?string, nwsp_is_main: int}>
	 */
	private static function sortedBySubjectId( array $rows ): array {
		usort(
			$rows,
			static fn ( array $first, array $second ): int
				=> strcmp( $first['nwsp_subject_id'], $second['nwsp_subject_id'] )
		);

		return $rows;
	}

	public function removePage( PageId $pageId ): void {
		$this->db->newDeleteQueryBuilder()
			->deleteFrom( self::TABLE )
			->where( [ 'nwsp_page_id' => $pageId->id ] )
			->caller( __METHOD__ )
			->execute();
	}

}
