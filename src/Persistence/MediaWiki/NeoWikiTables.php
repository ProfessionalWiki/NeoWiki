<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki;

use Wikimedia\Rdbms\IMaintainableDatabase;

class NeoWikiTables {

	public const array NAMES = [
		DatabaseRebuildRunRepository::TABLE,
		DatabaseSubjectPageIndex::TABLE,
		DatabasePublishedSubjectIndex::SUBJECT_TABLE,
		DatabasePublishedSubjectIndex::RELATION_TABLE,
	];

	/**
	 * @param string[] $names
	 */
	public function __construct(
		private readonly IMaintainableDatabase $db,
		private readonly array $names,
	) {
	}

	/**
	 * @return string[]
	 */
	public function getExisting(): array {
		return array_values( array_filter(
			$this->names,
			fn ( string $name ): bool => $this->db->tableExists( $name, __METHOD__ )
		) );
	}

	/**
	 * @return string[]
	 */
	public function getMissing(): array {
		return array_values( array_diff( $this->names, $this->getExisting() ) );
	}

	public function drop(): void {
		foreach ( $this->names as $name ) {
			$this->db->dropTable( $name, __METHOD__ );
		}
	}

}
