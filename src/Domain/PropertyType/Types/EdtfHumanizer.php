<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\PropertyType\Types;

use EDTF\EdtfFactory;

class EdtfHumanizer {

	public function humanize( string $edtfString, string $language ): ?string {
		$parsingResult = EdtfFactory::newParser()->parse( $edtfString );

		if ( !$parsingResult->isValid() ) {
			return null;
		}

		$humanization = EdtfFactory::newStructuredHumanizerForLanguage( $language, 'en' )
			->humanize( $parsingResult->getEdtfValue() );

		if ( !$humanization->wasHumanized() ) {
			return null;
		}

		if ( $humanization->isOneMessage() ) {
			$simpleHumanization = $humanization->getSimpleHumanization();

			return $edtfString === $simpleHumanization ? null : $simpleHumanization;
		}

		$parts = [];

		foreach ( $humanization->getStructuredHumanization() as $singleHumanization ) {
			$parts[] = $singleHumanization;
		}

		return $humanization->getContextMessage() . ': ' . implode( '; ', $parts );
	}

}
