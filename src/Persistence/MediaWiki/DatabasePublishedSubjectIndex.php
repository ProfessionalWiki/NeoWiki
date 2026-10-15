<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki;

use ProfessionalWiki\NeoWiki\Domain\Page\DependentSubjectRules;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectDisplayName;
use ProfessionalWiki\NeoWiki\Persistence\PublishedSubjectIndex;
use stdClass;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * Its writes go through the connection the operation already uses, so on the edit path they join the revision's
 * transaction.
 * Only rows that differ are written, each by its primary key: rewriting every row of a page with many Subjects
 * would hold that transaction open for long.
 */
class DatabasePublishedSubjectIndex implements PublishedSubjectIndex {

	public const string SUBJECT_TABLE = 'neowiki_subject';
	public const string RELATION_TABLE = 'neowiki_relation';

	/**
	 * Subject data can name a Schema or property at any length, so a longer name is cut here, on a character
	 * boundary: the database could split a character and leave a row that never equals the one built for it.
	 */
	private const int NAME_COLUMN_BYTES = 255;

	/**
	 * Each row is a condition of its own, and SQLite refuses an expression nested deeper than 1000.
	 */
	private const int RELATION_DELETE_BATCH_SIZE = 100;

	public function __construct(
		private readonly IDatabase $db,
		private readonly DependentSubjectRules $dependentSubjectRules,
	) {
	}

	public function setSubjectsOfPage(
		PageId $pageId,
		string $pageName,
		PageSubjects $subjects,
		string $publishedTimestamp
	): void {
		$this->writePage(
			$pageId,
			$this->subjectRows( $pageName, $subjects, $publishedTimestamp ),
			$this->relationRows( $subjects )
		);
	}

	public function removePage( PageId $pageId ): void {
		$this->writePage( $pageId, [], [] );
	}

	/**
	 * @param array<string, array<string, string|int|null>> $wantedSubjectRows
	 * @param array<string, array<string, string>> $wantedRelationRows
	 */
	private function writePage( PageId $pageId, array $wantedSubjectRows, array $wantedRelationRows ): void {
		// No lock: on the edit path, a read stale enough to matter implies a stale parent revision, which
		// PageUpdater refuses.
		$storedSubjectRows = $this->storedSubjectRows( $pageId );

		// Relation rows only ever come with Subject rows, so a page that has and wants none has nothing else
		// to compare.
		if ( $storedSubjectRows === [] && $wantedSubjectRows === [] ) {
			return;
		}

		$subjectChanges = RowChanges::between(
			array_map( self::withoutEditTime( ... ), $storedSubjectRows ),
			array_map( self::withoutEditTime( ... ), $wantedSubjectRows )
		);
		$keptSubjectRows = array_diff_key( $storedSubjectRows, $subjectChanges->deletions );
		$newEditTime = self::newEditTimeOfKeptRows( $keptSubjectRows, $wantedSubjectRows );
		$relationChanges = RowChanges::between( $this->storedRelationRows( $pageId ), $wantedRelationRows );

		if ( $subjectChanges->isEmpty() && $newEditTime === null && $relationChanges->isEmpty() ) {
			return;
		}

		// Atomic even outside a transaction, as in a maintenance script, so a page is never left half written.
		$this->db->startAtomic( __METHOD__ );

		$this->deleteSubjectRows( $pageId, array_keys( $subjectChanges->deletions ) );

		if ( $newEditTime !== null ) {
			$this->updateEditTime( $pageId, array_keys( $keptSubjectRows ), $newEditTime );
		}

		$this->insertSubjectRows( $pageId, array_intersect_key( $wantedSubjectRows, $subjectChanges->insertions ) );
		$this->deleteRelationRows( $pageId, $relationChanges->deletions );
		$this->insertRelationRows( $pageId, $relationChanges->insertions );

		$this->db->endAtomic( __METHOD__ );
	}

	/**
	 * @return array<string, array<string, string|int|null>> Rows by Subject id, without the page id
	 */
	private function subjectRows( string $pageName, PageSubjects $subjects, string $publishedTimestamp ): array {
		$pageKey = CaseInsensitiveKey::of( $pageName );
		$rows = [];

		foreach ( $subjects->getAllSubjects()->asArray() as $subject ) {
			$displayName = SubjectDisplayName::labelOrPageName( $subject, $subjects, $pageName );

			$rows[$subject->id->text] = self::subjectRow(
				subjectId: $subject->id->text,
				schema: self::fitNameColumn( $subject->getSchemaName()->getText() ),
				displayName: $displayName,
				displayNameKey: $displayName === null ? null : CaseInsensitiveKey::of( $displayName ),
				pageKey: $pageKey,
				isMain: $subjects->isMainSubject( $subject->id ),
				isDependent: $this->dependentSubjectRules->isDependent( $subject ),
				edited: $publishedTimestamp,
			);
		}

		return $rows;
	}

	/**
	 * Builds the rows written and the rows read back alike, since the two are compared as arrays.
	 *
	 * @return array<string, string|int|null>
	 */
	private static function subjectRow(
		string $subjectId,
		string $schema,
		?string $displayName,
		?string $displayNameKey,
		string $pageKey,
		bool $isMain,
		bool $isDependent,
		string $edited
	): array {
		return [
			'nws_subject_id' => $subjectId,
			'nws_schema' => $schema,
			'nws_display_name' => $displayName,
			'nws_display_name_key' => $displayNameKey,
			'nws_page_key' => $pageKey,
			'nws_is_main' => $isMain ? 1 : 0,
			'nws_dependent' => $isDependent ? 1 : 0,
			'nws_edited' => $edited,
		];
	}

	/**
	 * @param array<string, string|int|null> $row
	 * @return array<string, string|int|null>
	 */
	private static function withoutEditTime( array $row ): array {
		unset( $row['nws_edited'] );
		return $row;
	}

	/**
	 * Null when the kept rows carry the wanted edit time already.
	 *
	 * @param array<string, array<string, string|int|null>> $keptRows
	 * @param array<string, array<string, string|int|null>> $wantedRows
	 */
	private static function newEditTimeOfKeptRows( array $keptRows, array $wantedRows ): ?string {
		foreach ( $keptRows as $subjectId => $storedRow ) {
			if ( $wantedRows[$subjectId]['nws_edited'] !== $storedRow['nws_edited'] ) {
				return (string)$wantedRows[$subjectId]['nws_edited'];
			}
		}

		return null;
	}

	/**
	 * A relation to a Subject of another Source gets no row: no Subject of this wiki can be its target. One to
	 * a Subject nobody created yet does.
	 *
	 * @return array<string, array<string, string>> By {@see self::relationKey()}, which collapses duplicates
	 */
	private function relationRows( PageSubjects $subjects ): array {
		$rows = [];

		foreach ( $subjects->getAllSubjects()->asArray() as $subject ) {
			foreach ( $subject->getTypedRelations()->relations as $relation ) {
				if ( $relation->targetId->isLocal() ) {
					$row = self::relationRow(
						$subject->id->text,
						self::fitNameColumn( $relation->propertyName->text ),
						$relation->targetId->text
					);
					$rows[self::relationKey( $row )] = $row;
				}
			}
		}

		return $rows;
	}

	private static function fitNameColumn( string $name ): string {
		return mb_strcut( $name, 0, self::NAME_COLUMN_BYTES, 'UTF-8' );
	}

	/**
	 * @return array<string, string>
	 */
	private static function relationRow( string $subjectId, string $property, string $targetId ): array {
		return [
			'nwr_subject_id' => $subjectId,
			'nwr_property' => $property,
			'nwr_target_id' => $targetId,
		];
	}

	/**
	 * Ids hold no spaces, so the property, which may, goes last without letting two rows share a key.
	 *
	 * @param array<string, string> $row
	 */
	private static function relationKey( array $row ): string {
		return $row['nwr_subject_id'] . ' ' . $row['nwr_target_id'] . ' ' . $row['nwr_property'];
	}

	/**
	 * @return array<string, array<string, string|int|null>>
	 */
	private function storedSubjectRows( PageId $pageId ): array {
		$result = $this->db->newSelectQueryBuilder()
			->select( [
				'nws_subject_id',
				'nws_schema',
				'nws_display_name',
				'nws_display_name_key',
				'nws_page_key',
				'nws_is_main',
				'nws_dependent',
				'nws_edited',
			] )
			->from( self::SUBJECT_TABLE )
			->where( [ 'nws_page_id' => $pageId->id ] )
			->caller( __METHOD__ )
			->fetchResultSet();

		$rows = [];

		/** @var stdClass $row */
		foreach ( $result as $row ) {
			$rows[(string)$row->nws_subject_id] = self::subjectRow(
				subjectId: (string)$row->nws_subject_id,
				schema: (string)$row->nws_schema,
				displayName: $row->nws_display_name === null ? null : (string)$row->nws_display_name,
				displayNameKey: $row->nws_display_name_key === null ? null : (string)$row->nws_display_name_key,
				pageKey: (string)$row->nws_page_key,
				isMain: (int)$row->nws_is_main === 1,
				isDependent: (int)$row->nws_dependent === 1,
				// Converted, since PostgreSQL hands back a format of its own.
				edited: (string)ConvertibleTimestamp::convert( TS_MW, (string)$row->nws_edited ),
			);
		}

		return $rows;
	}

	/**
	 * @return array<string, array<string, string>>
	 */
	private function storedRelationRows( PageId $pageId ): array {
		$result = $this->db->newSelectQueryBuilder()
			->select( [ 'nwr_subject_id', 'nwr_property', 'nwr_target_id' ] )
			->from( self::RELATION_TABLE )
			->where( [ 'nwr_page_id' => $pageId->id ] )
			->caller( __METHOD__ )
			->fetchResultSet();

		$rows = [];

		/** @var stdClass $row */
		foreach ( $result as $row ) {
			$relationRow = self::relationRow(
				(string)$row->nwr_subject_id,
				(string)$row->nwr_property,
				(string)$row->nwr_target_id
			);

			$rows[self::relationKey( $relationRow )] = $relationRow;
		}

		return $rows;
	}

	/**
	 * @param array<array-key, int|string> $subjectIds
	 */
	private function deleteSubjectRows( PageId $pageId, array $subjectIds ): void {
		if ( $subjectIds === [] ) {
			return;
		}

		$this->db->newDeleteQueryBuilder()
			->deleteFrom( self::SUBJECT_TABLE )
			->where( self::subjectRowsByKey( $pageId, $subjectIds ) )
			->caller( __METHOD__ )
			->execute();
	}

	/**
	 * By full primary key: a page's rows are adjacent in it, so an update by page id would gap-lock them and
	 * block, or deadlock with, a save that adds a Subject to the neighbouring page (as in T214035).
	 *
	 * @param array<array-key, int|string> $subjectIds
	 */
	private function updateEditTime( PageId $pageId, array $subjectIds, string $editTime ): void {
		$this->db->newUpdateQueryBuilder()
			->update( self::SUBJECT_TABLE )
			->set( [ 'nws_edited' => $this->db->timestamp( $editTime ) ] )
			->where( self::subjectRowsByKey( $pageId, $subjectIds ) )
			->caller( __METHOD__ )
			->execute();
	}

	/**
	 * @param array<array-key, int|string> $subjectIds
	 * @return array<string, int|list<string>>
	 */
	private static function subjectRowsByKey( PageId $pageId, array $subjectIds ): array {
		return [ 'nws_page_id' => $pageId->id, 'nws_subject_id' => array_values( array_map( 'strval', $subjectIds ) ) ];
	}

	/**
	 * Ignores a row another write of the page stored since this one read it, as when an approval refreshes a
	 * page while it is edited. The row stays as that write left it until the page is next written.
	 *
	 * @param array<string, array<string, string|int|null>> $rows
	 */
	private function insertSubjectRows( PageId $pageId, array $rows ): void {
		if ( $rows === [] ) {
			return;
		}

		$this->db->newInsertQueryBuilder()
			->insertInto( self::SUBJECT_TABLE )
			->ignore()
			->rows( array_map(
				fn ( array $row ): array => [
					'nws_page_id' => $pageId->id,
					...$row,
					'nws_edited' => $this->db->timestamp( (string)$row['nws_edited'] ),
				],
				array_values( $rows )
			) )
			->caller( __METHOD__ )
			->execute();
	}

	/**
	 * Each row's condition is built on its own rather than factored: factoring makes values array keys, and a
	 * property named like an integer would then compare as a number.
	 *
	 * @param array<array-key, array<string, mixed>> $rows
	 */
	private function deleteRelationRows( PageId $pageId, array $rows ): void {
		foreach ( array_chunk( $rows, self::RELATION_DELETE_BATCH_SIZE ) as $batch ) {
			$this->db->newDeleteQueryBuilder()
				->deleteFrom( self::RELATION_TABLE )
				->where( [ 'nwr_page_id' => $pageId->id ] )
				->where( $this->db->makeList(
					array_map(
						fn ( array $row ): string => $this->db->makeList( $row, IDatabase::LIST_AND ),
						$batch
					),
					IDatabase::LIST_OR
				) )
				->caller( __METHOD__ )
				->execute();
		}
	}

	/**
	 * Ignores a row another write stored since this one read the page, as insertSubjectRows() does.
	 *
	 * @param array<array-key, array<string, mixed>> $rows
	 */
	private function insertRelationRows( PageId $pageId, array $rows ): void {
		if ( $rows === [] ) {
			return;
		}

		$this->db->newInsertQueryBuilder()
			->insertInto( self::RELATION_TABLE )
			->ignore()
			->rows( array_map(
				static fn ( array $row ): array => [ 'nwr_page_id' => $pageId->id, ...$row ],
				array_values( $rows )
			) )
			->caller( __METHOD__ )
			->execute();
	}

}
