<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\Parser;

use Html;
use Parser;

class TableFunction {

	/**
	 * @return array<mixed, mixed>
	 */
	public function handleParserFunctionCall( Parser $parser, string ...$arguments ): array {
		$parser->getOutput()->addModules( [ 'ext.neowiki.table-editor' ] );

		return [
			Html::element(
				'div',
				[
					'class' => 'nwSubjectTableLoader',
					'data-schema-id' => $arguments[0],
					'data-page-id' => \Title::newFromDBkey( $parser->getPage()?->getDBkey() )->getId(),
				]
			),
			'noparse' => true,
			'isHTML' => true,
		];
	}

}
