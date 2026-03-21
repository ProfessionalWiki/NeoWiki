<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Presentation;

use ProfessionalWiki\NeoWiki\Domain\Layout\DisplayRule;
use ProfessionalWiki\NeoWiki\Domain\Layout\Layout;

class LayoutPresentationSerializer {

	public function serialize( Layout $layout ): string {
		$data = [
			'schema' => $layout->getSchema()->getText(),
			'type' => $layout->getType(),
		];

		if ( $layout->getDescription() !== '' ) {
			$data['description'] = $layout->getDescription();
		}

		if ( !$layout->getDisplayRules()->isEmpty() ) {
			$data['displayRules'] = array_map(
				static function ( DisplayRule $rule ): array {
					$entry = [ 'property' => (string)$rule->getProperty() ];
					if ( $rule->getDisplayAttributes() !== [] ) {
						$entry['displayAttributes'] = $rule->getDisplayAttributes();
					}
					return $entry;
				},
				iterator_to_array( $layout->getDisplayRules() ),
			);
		}

		if ( $layout->getSettings() !== [] ) {
			$data['settings'] = $layout->getSettings();
		}

		$json = json_encode( $data );

		if ( $json === false ) {
			throw new \RuntimeException( 'Failed to JSON encode layout data' );
		}

		return $json;
	}

}
