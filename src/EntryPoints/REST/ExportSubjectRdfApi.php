<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\REST;

use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use ProfessionalWiki\NeoWiki\Application\Rdf\RdfPageProjector;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use Wikimedia\ParamValidator\ParamValidator;

/**
 * Exports one Subject as RDF: exactly the triples the page projection emits for that Subject — its
 * outbound bounded description, including native relation reification — with none of the page-metadata
 * triples, placed in the hosting page's per-projection named graph. The `projection` query parameter
 * selects the vocabulary ("native" default, or an ontology target a Mapping page declares — an unknown
 * target is a 400); the `format` parameter picks the serialization, falling back to the Accept header,
 * then to TriG.
 *
 * A Subject that is not in the graph, lives on a page the caller cannot read, or is no longer in its
 * hosting page's current revision all return one indistinguishable 404, so the endpoint cannot be used
 * to probe for restricted Subjects (cf. #1046).
 */
class ExportSubjectRdfApi extends SimpleHandler {

	use RdfFormatNegotiation;

	public function run( string $subjectId ): Response {
		// Validate the ID shape first, so a malformed value is a clean 400 rather than reaching
		// the SubjectId constructor below and becoming a 500.
		if ( !SubjectId::isValid( $subjectId ) ) {
			return $this->getResponseFactory()->createHttpError( 400, [
				'message' => 'Invalid Subject ID: ' . $subjectId,
			] );
		}

		$extension = NeoWikiExtension::getInstance();
		$projectionName = $this->getValidatedParams()['projection'] ?? RdfPageProjector::PROJECTION;
		$resolution = $extension->resolveRdfProjection( $projectionName );

		// The projection check runs before the Subject is resolved and before the read gate, so a
		// caller who cannot read the Subject's page sees the same 400 as anyone else and cannot use it
		// to probe existence or readability.
		if ( $resolution->projection === null ) {
			return $this->getResponseFactory()->createHttpError( 400, [
				'message' => 'Unknown RDF projection: "' . $projectionName . '". Known projections: '
					. implode( ', ', $resolution->knownProjectionNames ) . '.',
			] );
		}

		$format = $this->resolveFormat();

		$document = $extension
			->newRdfSubjectExporterForProjection( $resolution->projection, $this->getAuthority() )
			->exportBySubjectId( new SubjectId( $subjectId ), $format );

		if ( $document === null ) {
			return $this->noDataResponse( $subjectId );
		}

		return $this->rdfResponse( $document, $format );
	}

	private function noDataResponse( string $subjectId ): Response {
		return $this->getResponseFactory()->createHttpError( 404, [
			'message' => 'No NeoWiki data found for subject: ' . $subjectId,
		] );
	}

	public function getParamSettings(): array {
		return [
			'subjectId' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
				self::PARAM_DESCRIPTION => 'Persistent identifier of the Subject. 15 characters, starting with "s".',
			],
			'format' => [
				self::PARAM_SOURCE => 'query',
				ParamValidator::PARAM_TYPE => [
					self::FORMAT_TRIG,
					self::FORMAT_TURTLE,
				],
				ParamValidator::PARAM_REQUIRED => false,
				self::PARAM_DESCRIPTION => 'RDF serialization to return: "trig" (default, includes the hosting page\'s named graph) or "turtle". Overrides the Accept header.',
			],
			'projection' => [
				self::PARAM_SOURCE => 'query',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => false,
				self::PARAM_DESCRIPTION => 'RDF projection to produce: "native" (default) for NeoWiki-native vocabulary, or an ontology target declared by a Mapping page (e.g. "edm"). An unknown target returns 400.',
			],
		];
	}

}
