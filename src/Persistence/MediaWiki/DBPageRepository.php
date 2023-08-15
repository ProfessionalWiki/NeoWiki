<?php

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki;

use Wikimedia\Rdbms\IResultWrapper;

class DBPageRepository extends DBRepository {

	public function getAllSchemasByNameSpace( int $pageNameSpace ): IResultWrapper {
		return $this->getDb()->select(
			'page',
			[ 'page_title' ],
			[ 'page_namespace' => $pageNameSpace ],
			__METHOD__,
			[ 'ORDER BY' => 'page_id ASC' ]
		);
	}
}
