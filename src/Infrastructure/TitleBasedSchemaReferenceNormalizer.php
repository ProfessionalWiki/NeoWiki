<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Infrastructure;

use InvalidArgumentException;
use MediaWiki\Title\TitleFactory;
use ProfessionalWiki\NeoWiki\Application\Schema\SchemaReferenceNormalizer;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaReference;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;

/**
 * Normalizes through the Schema's own page title, which is what makes the stored name and the name
 * the Schema is found under the same string: {@see \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\CachingSchemaLookup}
 * resolves a Schema with this very call. Asking MediaWiki also keeps the rule whole and correct per
 * wiki — the first character is capitalized only where `$wgCapitalLinks` says so, and in the content
 * language rather than by byte — where a rule written out here would drift from it.
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

		$normalized = $this->normalizeName( $reference->name );

		return $normalized === null ? $reference : SchemaReference::local( $normalized );
	}

	/**
	 * Null where the name has no normal form to give: a name no title can be made of, or one whose
	 * normal form no Schema may be called. Both name a Schema that cannot exist, so they are left as
	 * written and reported as missing by whoever looks the Schema up.
	 */
	private function normalizeName( SchemaName $name ): ?SchemaName {
		$title = $this->titleFactory->newFromText( $name->getText(), NeoWikiExtension::NS_SCHEMA );

		if ( $title === null ) {
			return null;
		}

		try {
			return new SchemaName( $title->getText() );
		} catch ( InvalidArgumentException ) {
			return null;
		}
	}

}
