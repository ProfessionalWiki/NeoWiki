<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Source;

use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaReference;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaReferenceResolver;

/**
 * Resolves each reference once for as long as it lives, which is one response: a long-running process would keep
 * serving a Schema changed meanwhile.
 */
class MemoizingSchemaResolver implements SchemaReferenceResolver {

	/**
	 * @var array<string, ?Schema>
	 */
	private array $schemas = [];

	public function __construct(
		private readonly SchemaReferenceResolver $resolver,
	) {
	}

	public function getSchema( SchemaReference $reference ): ?Schema {
		// Not getText(): a sourced reference reads like a local name holding a colon.
		$key = json_encode( $reference->toJson(), JSON_THROW_ON_ERROR );

		if ( !array_key_exists( $key, $this->schemas ) ) {
			$this->schemas[$key] = $this->resolver->getSchema( $reference );
		}

		return $this->schemas[$key];
	}

}
