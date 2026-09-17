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
 * Normalizes through the Schema's own page title, which is what makes the stored name and the name the
 * Schema is found under the same string: {@see \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\CachingSchemaLookup}
 * resolves a Schema with this very call. Asking MediaWiki also keeps the rule whole and correct per wiki
 * — the first character is capitalized only where `$wgCapitalLinks` says so, and in the content language
 * rather than by byte — where a rule written out here would drift from it.
 */
class TitleBasedSchemaReferenceNormalizer implements SchemaReferenceNormalizer {

	/**
	 * A Subject slot names a handful of Schemas across all its Subjects, and a rebuild reads every slot
	 * on the wiki, while parsing a title is neither free nor cached by MediaWiki for a namespace other
	 * than NS_MAIN. Keyed by the name as written, which is all {@see normalizeName} reads.
	 *
	 * @var array<string, ?SchemaName>
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

		$normalized = $this->normalizedNameOf( $reference->name );

		return $normalized === null ? $reference : SchemaReference::local( $normalized );
	}

	private function normalizedNameOf( SchemaName $name ): ?SchemaName {
		$written = $name->getText();

		if ( !array_key_exists( $written, $this->normalizedNames ) ) {
			$this->normalizedNames[$written] = $this->normalizeName( $name );
		}

		return $this->normalizedNames[$written];
	}

	/**
	 * Null where the name has no normal form to give, which leaves it as written for whoever looks the
	 * Schema up to report as missing. That covers a name no title can be made of, one whose normal form
	 * no Schema may be called, and — the case worth stating — one carrying a prefix or fragment.
	 * MediaWiki reads `Help:Person` as a page of the Help namespace and hands back the bare `Person`,
	 * which names a different Schema than the one written down, so only a title that really does sit in
	 * the Schema namespace of this wiki is allowed to rename anything.
	 */
	private function normalizeName( SchemaName $name ): ?SchemaName {
		$title = $this->titleFactory->newFromText( $name->getText(), NeoWikiExtension::NS_SCHEMA );

		if ( $title === null
			|| $title->isExternal()
			|| !$title->inNamespace( NeoWikiExtension::NS_SCHEMA )
			|| $title->hasFragment()
		) {
			return null;
		}

		try {
			return new SchemaName( $title->getText() );
		} catch ( InvalidArgumentException ) {
			return null;
		}
	}

}
