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
	 * The whole name titles the page, and is never split into a namespace or interwiki prefix, so
	 * "Help:Person" names the page Schema:Help:Person. A name that titles no page, or whose page is
	 * titled as no Schema may be named, is left as written for the lookup to report as missing.
	 */
	private function nameOfSchemaPage( SchemaName $name ): SchemaName {
		$title = $this->titleFactory->makeTitleSafe( NeoWikiExtension::NS_SCHEMA, $name->getText() );

		if ( $title === null ) {
			return $name;
		}

		try {
			return new SchemaName( $title->getText() );
		} catch ( InvalidArgumentException ) {
			return $name;
		}
	}

}
