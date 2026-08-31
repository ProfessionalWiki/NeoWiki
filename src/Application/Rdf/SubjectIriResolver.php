<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Rdf;

use ProfessionalWiki\NeoWiki\Domain\Rdf\Iri;
use ProfessionalWiki\NeoWiki\Domain\Rdf\RdfNamespaces;
use ProfessionalWiki\NeoWiki\Domain\Source\SourceRegistry;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use Psr\Log\LoggerInterface;

/**
 * Names the Subject a relation points at, for every projection alike.
 *
 * A Subject of this wiki is named under this wiki's base IRI. A Subject of another Source is named
 * under that Source's own base IRI, which is what makes the triple resolvable outside this wiki. A
 * Source this wiki does not have leaves nothing to name, so the relation is dropped rather than
 * minted under a base that is not its own, which would assert ownership of an entity elsewhere.
 */
class SubjectIriResolver {

	public function __construct(
		private readonly RdfNamespaces $namespaces,
		private readonly SourceRegistry $sourceRegistry,
		private readonly LoggerInterface $logger,
	) {
	}

	public function targetIri( SubjectId $id ): ?Iri {
		if ( $id->isLocal() ) {
			return $this->namespaces->subject( $id );
		}

		$source = $this->sourceRegistry->getSourceOf( $id );

		if ( $source === null ) {
			$this->logger->warning(
				'Not projecting relation to ' . $id->text . ': its Source is not registered'
			);
			return null;
		}

		return new Iri( $source->getBaseUri() . $id->localId );
	}

}
