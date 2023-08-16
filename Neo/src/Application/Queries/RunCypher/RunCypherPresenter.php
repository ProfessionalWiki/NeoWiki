<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Queries\RunCypher;

use Laudis\Neo4j\Databags\SummarizedResult;

interface RunCypherPresenter {

	public function presentError( RunCypherError $error ): void;

	public function presentSummarizedResult( SummarizedResult $result ): void;

}
