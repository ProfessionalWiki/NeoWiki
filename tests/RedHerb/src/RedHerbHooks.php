<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\RedHerb;

use ProfessionalWiki\NeoWiki\EntryPoints\NeoWikiRegistrar;

class RedHerbHooks {

	public static function onNeoWikiRegistration( NeoWikiRegistrar $registrar ): void {
		$registrar->addPropertyType( new ColorType() );
		$registrar->addNeo4jValueBuilder( ColorType::NAME, static fn( $value ) => $value->toScalars() );
		$registrar->addPagePropertyProvider( new StaticPagePropertyProvider() );
	}

}
