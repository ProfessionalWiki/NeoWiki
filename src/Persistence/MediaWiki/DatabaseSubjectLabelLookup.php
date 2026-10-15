<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki;

use MediaWiki\Title\TitleFactory;
use ProfessionalWiki\NeoWiki\Application\PageReadAuthorizer;
use ProfessionalWiki\NeoWiki\Application\SubjectLabelLookup;
use ProfessionalWiki\NeoWiki\Application\SubjectLabelLookupResult;
use stdClass;
use Wikimedia\Rdbms\IExpression;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IResultWrapper;
use Wikimedia\Rdbms\LikeValue;

/**
 * A Subject whose name is generated has none to match.
 */
class DatabaseSubjectLabelLookup implements SubjectLabelLookup {

	/**
	 * Reading this many times the limit tends to fill it despite Subjects on pages the caller cannot read; a
	 * longer run of those still shortens the result.
	 */
	private const int OVERFETCH_FACTOR = 4;

	public function __construct(
		private readonly IConnectionProvider $connectionProvider,
		private readonly PageReadAuthorizer $readAuthorizer,
		private readonly TitleFactory $titleFactory,
	) {
	}

	/**
	 * @param ?string[] $schemaNames
	 * @return SubjectLabelLookupResult[]
	 */
	public function getSubjectLabelsMatching( string $search, int $limit, ?array $schemaNames ): array {
		if ( trim( $search ) === '' || $schemaNames === [] ) {
			return [];
		}

		$results = [];
		$readablePages = [];

		/** @var stdClass $row */
		foreach ( $this->fetchRows( $search, $limit * self::OVERFETCH_FACTOR, $schemaNames ) as $row ) {
			if ( count( $results ) >= $limit ) {
				break;
			}

			$title = $this->titleFactory->newFromRow( $row );
			$readablePages[(int)$row->page_id] ??= $this->readAuthorizer->authorizeReadByPageTitle( $title );

			if ( $readablePages[(int)$row->page_id] ) {
				$results[] = new SubjectLabelLookupResult(
					id: (string)$row->nws_subject_id,
					label: (string)$row->nws_display_name,
					pageTitle: $title->getPrefixedText(),
				);
			}
		}

		return $results;
	}

	/**
	 * Ordered by id after name: by name alone, the order and, under the limit, the membership of a run of namesakes
	 * is undefined, so one search could answer with a namesake and the next with its twin.
	 *
	 * @param ?non-empty-array<string> $schemaNames
	 */
	private function fetchRows( string $search, int $limit, ?array $schemaNames ): IResultWrapper {
		$db = $this->connectionProvider->getReplicaDatabase();

		$query = $db->newSelectQueryBuilder()
			->select( [ 'nws_subject_id', 'nws_display_name', 'page_id', 'page_namespace', 'page_title' ] )
			->from( DatabasePublishedSubjectIndex::SUBJECT_TABLE, 'subject' )
			->join( 'page', null, 'page_id = subject.nws_page_id' )
			->where( $db->expr(
				'subject.nws_display_name_key',
				IExpression::LIKE,
				new LikeValue( CaseInsensitiveKey::of( $search ), $db->anyString() )
			) )
			->andWhere( [ 'subject.nws_dependent' => 0 ] )
			->andWhere( LowestPageRows::condition( $db, 'subject' ) )
			->orderBy( [ 'subject.nws_display_name_key', 'subject.nws_subject_id' ] )
			->limit( $limit )
			->caller( __METHOD__ );

		if ( $schemaNames !== null ) {
			$query->andWhere( [ 'subject.nws_schema' => array_values( $schemaNames ) ] );
		}

		return $query->fetchResultSet();
	}

}
