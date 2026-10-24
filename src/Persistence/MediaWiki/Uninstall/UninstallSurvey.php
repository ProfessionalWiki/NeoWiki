<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Uninstall;

use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\MediaWikiSubjectRepository;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\SubjectSlotPages;
use stdClass;
use Wikimedia\Rdbms\IReadableDatabase;

/**
 * What uninstalling NeoWiki would remove from the wiki.
 */
class UninstallSurvey {

	public function __construct(
		private readonly IReadableDatabase $db,
	) {
	}

	public function countPagesHoldingSubjects(): int {
		return SubjectSlotPages::newQuery( $this->db )
			->caller( __METHOD__ )
			->fetchRowCount();
	}

	public function countRevisionsHoldingSubjects(): int {
		return $this->db->newSelectQueryBuilder()
			->from( 'slots' )
			->join( 'slot_roles', null, 'slot_role_id = role_id' )
			->where( [ 'role_name' => MediaWikiSubjectRepository::SLOT_NAME ] )
			->caller( __METHOD__ )
			->fetchRowCount();
	}

	public function countPagesInNeoWikiNamespaces(): int {
		return $this->countRows( 'page', [ 'page_namespace' => NeoWikiNamespacePurger::NAMESPACES ] );
	}

	/**
	 * @return string[] Page titles, as they are written in wikitext
	 */
	public function getPagesInNeoWikiNamespaces( int $limit ): array {
		$rows = $this->db->newSelectQueryBuilder()
			->select( [ 'page_namespace', 'page_title' ] )
			->from( 'page' )
			->where( [ 'page_namespace' => NeoWikiNamespacePurger::NAMESPACES ] )
			->orderBy( 'page_id' )
			->limit( $limit )
			->caller( __METHOD__ )
			->fetchResultSet();

		$titles = [];

		/** @var stdClass $row */
		foreach ( $rows as $row ) {
			$titles[] = Title::makeTitle( (int)$row->page_namespace, $row->page_title )->getPrefixedText();
		}

		return $titles;
	}

	public function countDeletedRevisionsInNeoWikiNamespaces(): int {
		return $this->countRows( 'archive', [ 'ar_namespace' => NeoWikiNamespacePurger::NAMESPACES ] );
	}

	private function countRows( string $table, array $conditions ): int {
		return $this->db->newSelectQueryBuilder()
			->from( $table )
			->where( $conditions )
			->caller( __METHOD__ )
			->fetchRowCount();
	}

}
