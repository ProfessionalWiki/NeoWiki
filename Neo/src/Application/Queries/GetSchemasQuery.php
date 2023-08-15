<?php

namespace ProfessionalWiki\NeoWiki\Application\Queries;

use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\DBPageRepository;

class GetSchemasQuery {

	public function __construct(
		private readonly DBPageRepository $pageRepository
	) {
	}

	public function execute(): array {
		$result = [];
		$resultWrapper = $this->pageRepository->getAllSchemasByNameSpace( (int)NS_NEOWIKI_SCHEMA );

		foreach ( $resultWrapper as $row ) {
			if ( $row && is_object( $row ) && isset( $row->page_title ) ) {
				$result[] = (string)$row->page_title;
			}
		}

		return $result;
	}
}
