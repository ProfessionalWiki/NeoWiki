<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Presentation;

use ProfessionalWiki\NeoWiki\Domain\View\DisplayRule;
use ProfessionalWiki\NeoWiki\Domain\View\View;

class ViewPresentationSerializer {

	public function serialize( View $view ): string {
		$data = [
			'schema' => $view->getSchema()->getText(),
			'type' => $view->getType(),
		];

		if ( $view->getDescription() !== '' ) {
			$data['description'] = $view->getDescription();
		}

		if ( !$view->getDisplayRules()->isEmpty() ) {
			$data['displayRules'] = array_map(
				static function ( DisplayRule $rule ): array {
					$entry = [ 'property' => (string)$rule->getProperty() ];
					if ( $rule->getDisplayAttributes() !== [] ) {
						$entry['displayAttributes'] = $rule->getDisplayAttributes();
					}
					return $entry;
				},
				iterator_to_array( $view->getDisplayRules() ),
			);
		}

		if ( $view->getSettings() !== [] ) {
			$data['settings'] = $view->getSettings();
		}

		$json = json_encode( $data );

		if ( $json === false ) {
			throw new \RuntimeException( 'Failed to JSON encode view data' );
		}

		return $json;
	}

}
