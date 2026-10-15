<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests;

use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\DatabasePublishedSubjectIndex;
use Wikimedia\Rdbms\IResultWrapper;
use Wikimedia\Timestamp\ConvertibleTimestamp;

trait ReadsPublishedSubjectIndex {

	/**
	 * @return array<string, array{schema: string, displayName: ?string, isMain: bool}> By Subject id, in id order
	 */
	private function subjectRowsOf( int $pageId ): array {
		$rows = [];

		foreach ( $this->selectSubjectRows( $pageId ) as $row ) {
			$rows[$row->nws_subject_id] = [
				'schema' => $row->nws_schema,
				'displayName' => $row->nws_display_name,
				'isMain' => (bool)$row->nws_is_main,
			];
		}

		return $rows;
	}

	/**
	 * @return array<string, ?string> By Subject id, in id order
	 */
	private function displayNamesOf( int $pageId ): array {
		return array_map(
			static fn ( array $row ): ?string => $row['displayName'],
			$this->subjectRowsOf( $pageId )
		);
	}

	/**
	 * @return array<string, bool> Whether each Subject's row marks it a Dependent Subject, by Subject id, in id order
	 */
	private function dependentFlagsOf( int $pageId ): array {
		$flags = [];

		foreach ( $this->selectSubjectRows( $pageId ) as $row ) {
			$flags[$row->nws_subject_id] = (bool)$row->nws_dependent;
		}

		return $flags;
	}

	/**
	 * @return array<string, string> By Subject id
	 */
	private function editTimesOf( int $pageId ): array {
		$editTimes = [];

		foreach ( $this->selectSubjectRows( $pageId ) as $row ) {
			$editTimes[$row->nws_subject_id] = (string)ConvertibleTimestamp::convert( TS_MW, $row->nws_edited );
		}

		return $editTimes;
	}

	private function selectSubjectRows( int $pageId ): IResultWrapper {
		return $this->getDb()->newSelectQueryBuilder()
			->select( '*' )
			->from( DatabasePublishedSubjectIndex::SUBJECT_TABLE )
			->where( [ 'nws_page_id' => $pageId ] )
			->orderBy( 'nws_subject_id' )
			->caller( __METHOD__ )
			->fetchResultSet();
	}

	/**
	 * @return list<array{0: string, 1: string, 2: string}> Subject, property and target, sorted
	 */
	private function relationsOf( int $pageId ): array {
		$relations = [];

		$result = $this->getDb()->newSelectQueryBuilder()
			->select( [ 'nwr_subject_id', 'nwr_property', 'nwr_target_id' ] )
			->from( DatabasePublishedSubjectIndex::RELATION_TABLE )
			->where( [ 'nwr_page_id' => $pageId ] )
			->caller( __METHOD__ )
			->fetchResultSet();

		foreach ( $result as $row ) {
			$relations[] = [ $row->nwr_subject_id, $row->nwr_property, $row->nwr_target_id ];
		}

		sort( $relations );

		return $relations;
	}

}
