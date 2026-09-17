<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Infrastructure;

use InvalidArgumentException;
use MediaWiki\Title\TitleFactory;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaReference;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaReferenceNormalizer;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;

/**
 * Derives the name from the Schema page's title, so MediaWiki's own rules apply: the first character
 * is capitalized only where `$wgCapitalLinks` says so, and in the content language rather than by byte.
 */
class TitleBasedSchemaReferenceNormalizer implements SchemaReferenceNormalizer {

	public function __construct(
		private readonly TitleFactory $titleFactory,
	) {
	}

	public function normalize( SchemaReference $reference ): SchemaReference {
		if ( !$reference->isLocal() ) {
			return $reference;
		}

		return SchemaReference::local( $this->nameOfSchemaPage( $reference->name ) );
	}

	/**
	 * The name as written wherever it has no normal form to give, leaving it to whoever looks the Schema
	 * up to report as missing. canExist() rules out the empty, the invalid, the special and the
	 * interwiki; the namespace check rules out "Help:Person", which MediaWiki reads as a page of the
	 * Help namespace and hands back as the bare "Person" — a different Schema than the one written down.
	 * hasFragment() rules out "Person#Details", whose fragment the title would silently drop. What is
	 * left is a name no Schema may be called, which SchemaName refuses.
	 */
	private function nameOfSchemaPage( SchemaName $name ): SchemaName {
		$title = $this->titleFactory->newFromText( $name->getText(), NeoWikiExtension::NS_SCHEMA );

		if ( $title === null || !$title->canExist() || $title->hasFragment()
			|| !$title->inNamespace( NeoWikiExtension::NS_SCHEMA ) ) {
			return $name;
		}

		try {
			return new SchemaName( $title->getText() );
		} catch ( InvalidArgumentException ) {
			return $name;
		}
	}

}
