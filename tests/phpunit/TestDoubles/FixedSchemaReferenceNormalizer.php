<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\TestDoubles;

use ProfessionalWiki\NeoWiki\Application\Schema\SchemaReferenceNormalizer;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaReference;

/**
 * Renames local Schemas per a fixed map, standing in for MediaWiki's page-name normalization without
 * needing MediaWiki. Names outside the map are left alone, so a test that does not care about
 * normalization constructs this with no arguments and gets a normalizer that changes nothing.
 *
 * The real rule is MediaWiki's and is tested against it in
 * {@see \ProfessionalWiki\NeoWiki\Tests\Persistence\MediaWiki\TitleSchemaReferenceNormalizerTest}.
 */
class FixedSchemaReferenceNormalizer implements SchemaReferenceNormalizer {

	/**
	 * @param array<string, string> $names Name as written => name it normalizes to.
	 */
	public function __construct(
		private readonly array $names = [],
	) {
	}

	public function normalize( SchemaReference $reference ): SchemaReference {
		if ( !$reference->isLocal() ) {
			return $reference;
		}

		$normalized = $this->names[$reference->name->getText()] ?? null;

		return $normalized === null ? $reference : SchemaReference::local( new SchemaName( $normalized ) );
	}

}
