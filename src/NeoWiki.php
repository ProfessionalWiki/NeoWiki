<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki;

use ProfessionalWiki\Neo\CypherQueryFilter;

class NeoWiki {

	public static function getInstance(): self {
		/** @var ?NeoWiki $instance */
		static $instance = null;
		$instance ??= new self();
		return $instance;
	}

	public function newCypherQueryFilter(): CypherQueryFilter {
		return new CypherQueryFilter();
	}

}
