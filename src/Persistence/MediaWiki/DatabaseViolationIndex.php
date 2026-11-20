<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki;

use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Validation\SubjectViolation;
use ProfessionalWiki\NeoWiki\Persistence\ViolationIndex;
use Wikimedia\Rdbms\IDatabase;

class DatabaseViolationIndex implements ViolationIndex {

	public const string TABLE = 'neowiki_violation';

	private const int COLUMN_BYTES = 255;

	public function __construct(
		private readonly IDatabase $db,
	) {
	}

	public function setViolationsOfPage( PageId $pageId, SubjectViolation ...$violations ): void {
		$this->db->startAtomic( __METHOD__ );

		$this->deleteRowsOf( $pageId );

		if ( $violations !== [] ) {
			$this->db->newInsertQueryBuilder()
				->insertInto( self::TABLE )
				->rows( array_values( array_map(
					fn ( SubjectViolation $violation ): array => $this->rowOf( $pageId, $violation ),
					$violations
				) ) )
				->caller( __METHOD__ )
				->execute();
		}

		$this->db->endAtomic( __METHOD__ );
	}

	public function removePage( PageId $pageId ): void {
		$this->deleteRowsOf( $pageId );
	}

	/**
	 * By primary key: deleting by page id would lock the gap after the page's rows on MySQL (T214035).
	 */
	private function deleteRowsOf( PageId $pageId ): void {
		$ids = $this->db->newSelectQueryBuilder()
			->select( 'nwv_id' )
			->from( self::TABLE )
			->where( [ 'nwv_page_id' => $pageId->id ] )
			->forUpdate()
			->caller( __METHOD__ )
			->fetchFieldValues();

		if ( $ids !== [] ) {
			$this->db->newDeleteQueryBuilder()
				->deleteFrom( self::TABLE )
				->where( [ 'nwv_id' => $ids ] )
				->caller( __METHOD__ )
				->execute();
		}
	}

	/**
	 * @return array<string, mixed>
	 */
	private function rowOf( PageId $pageId, SubjectViolation $subjectViolation ): array {
		$violation = $subjectViolation->violation;

		return [
			'nwv_page_id' => $pageId->id,
			'nwv_subject_id' => $subjectViolation->subjectId->text,
			'nwv_schema' => self::fitColumn( $subjectViolation->schemaName ),
			'nwv_property' => $violation->propertyName === null ? null : self::fitColumn( (string)$violation->propertyName ),
			'nwv_code' => self::fitColumn( $violation->code ),
			'nwv_severity' => $violation->severity->value,
			'nwv_value_part' => $violation->valuePartIndex,
			'nwv_target_id' => $subjectViolation->relationTarget?->text,
			'nwv_args' => json_encode( $violation->args, JSON_THROW_ON_ERROR ),
		];
	}

	private static function fitColumn( string $text ): string {
		return mb_strcut( $text, 0, self::COLUMN_BYTES, 'UTF-8' );
	}

}
