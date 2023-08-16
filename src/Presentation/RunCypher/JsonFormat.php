<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Presentation\RunCypher;

use Laudis\Neo4j\Databags\SummarizedResult;

class JsonFormat {

	public function createJsonOutput( SummarizedResult $result ): string {
		$pre = \Html::element(
			'pre',
			[],
			(string)json_encode( $result->getResults()->toRecursiveArray(), JSON_PRETTY_PRINT )
		);

		return <<<HTML
<div class="mw-collapsible mw-collapsed">
	<div>Query result JSON</div>
	<div class="mw-collapsible-content">$pre</div>
</div>
HTML;
	}

}
