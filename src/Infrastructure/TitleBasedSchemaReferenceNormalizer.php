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
 * Normalizes through the Schema's own page title, which is what makes the stored name and the name the
 * Schema is found under the same string: {@see \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\CachingSchemaLookup}
 * resolves a Schema with this very call. Asking MediaWiki also keeps the rule whole and correct per wiki
 * — the first character is capitalized only where `$wgCapitalLinks` says so, and in the content language
 * rather than by byte — where a rule written out here would drift from it.
 */
class TitleBasedSchemaReferenceNormalizer implements SchemaReferenceNormalizer {

	/**
	 * A wiki names few Schemas and every Subject read asks after one, so the names seen are remembered
	 * for as long as this normalizer lives — which NeoWikiExtension makes the process, since parsing a
	 * title is not free and MediaWiki caches it for NS_MAIN only.
	 *
	 * @var array<string, SchemaName>
	 */
	private array $normalizedNames = [];

	public function __construct(
		private readonly TitleFactory $titleFactory,
	) {
	}

	public function normalize( SchemaReference $reference ): SchemaReference {
		if ( !$reference->isLocal() ) {
			return $reference;
		}

		return SchemaReference::local( $this->normalizedName( $reference->name ) );
	}

	private function normalizedName( SchemaName $name ): SchemaName {
		$written = $name->getText();

		if ( !array_key_exists( $written, $this->normalizedNames ) ) {
			$this->normalizedNames[$written] = $this->nameOfSchemaPage( $name );
		}

		return $this->normalizedNames[$written];
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
