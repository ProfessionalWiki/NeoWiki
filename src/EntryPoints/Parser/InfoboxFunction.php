<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\Parser;

use Html;
use Parser;

class InfoboxFunction {

	/**
	 * @return array<mixed, mixed>
	 */
	public function handleParserFunctionCall( Parser $parser, string ...$arguments ): array {
		// TODO $parser->getOutput()->addModules( [ 'ext.neowiki.table-editor' ] );

		return $this->buildParserFunctionHtmlResponse(
			Html::element(
				'div',
				[
					'class' => 'nwInfoboxLoader',
					'data-subject-id' => $arguments[0],
				]
			)
		);
	}

	private function buildParserFunctionHtmlResponse( string $html ): array {
		return [
			$html,
			'noparse' => true,
			'isHTML' => true,
		];
	}

}
