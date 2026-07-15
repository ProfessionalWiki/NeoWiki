<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application;

use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaReference;
use ProfessionalWiki\NeoWiki\Domain\Source\SourceRegistry;
use Psr\Log\LoggerInterface;

/**
 * Resolves a SchemaReference through the Source its key resolves to. A reference whose source key is
 * not registered degrades to not-found (null) plus one logged warning, never fatally (ADR 27),
 * mirroring {@see SourceRoutingSubjectLookup}. A local reference routes through the local Source and
 * behaves exactly as a direct name-keyed schema lookup.
 */
readonly class SourceRoutingSchemaReferenceResolver implements SchemaReferenceResolver {

	public function __construct(
		private SourceRegistry $sourceRegistry,
		private LoggerInterface $logger,
	) {
	}

	public function resolve( SchemaReference $reference ): ?Schema {
		$source = $this->sourceRegistry->getSourceForSchemaReference( $reference );

		if ( $source === null ) {
			$this->logger->warning(
				'Schema resolution for an unregistered Source key.',
				[ 'sourceKey' => $reference->getSource(), 'schemaName' => $reference->getName()->getText() ]
			);
			return null;
		}

		return $source->getSchema( $reference->getName() );
	}

}
