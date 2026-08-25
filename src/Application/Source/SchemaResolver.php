<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Source;

use Psr\Log\LoggerInterface;
use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaReference;
use ProfessionalWiki\NeoWiki\Domain\Source\SourceRegistry;

/**
 * Resolves a Schema through the Source it is referenced from (ADR 23), which need not be the Source of
 * the Subject using it.
 *
 * A reference to a Source this wiki does not have resolves to no Schema, logged as a warning, the same
 * as a Schema that was never created: callers already report an unresolvable Schema as a degraded state
 * rather than failing.
 */
readonly class SchemaResolver {

	public function __construct(
		private SourceRegistry $sourceRegistry,
		private LoggerInterface $logger,
	) {
	}

	public function getSchema( SchemaReference $reference ): ?Schema {
		$source = $this->sourceRegistry->getSource(
			$reference->source ?? $this->sourceRegistry->getLocalSourceKey()
		);

		if ( $source === null ) {
			$this->logger->warning(
				'NeoWiki: no registered Source for Schema reference: ' . $reference->getText()
			);
			return null;
		}

		return $source->getSchema( $reference->name );
	}

}
