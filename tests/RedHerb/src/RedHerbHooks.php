<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\RedHerb;

use ProfessionalWiki\NeoWiki\Domain\Rdf\RdfLiteralFactory;
use ProfessionalWiki\NeoWiki\EntryPoints\NeoWikiRegistrar;

class RedHerbHooks {

	public static function onNeoWikiRegistration( NeoWikiRegistrar $registrar ): void {
		$registrar->addPropertyType( new ColorType() );
		$registrar->addNeo4jValueBuilder( ColorType::NAME, static fn( $value ) => $value->toScalars() );
		$registrar->addRdfValueMapper(
			ColorType::NAME,
			static fn( $value ) => array_map(
				static fn( string $color ) => RdfLiteralFactory::typed( $color, 'string' ),
				$value->toScalars()
			)
		);
		$registrar->addPagePropertyProvider( new StaticPagePropertyProvider() );
		$registrar->addGraphDatabasePlugin( new RedHerbGraphDatabasePlugin() );
	}

}
