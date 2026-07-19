<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\REST;

use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use ProfessionalWiki\NeoWiki\Application\Rdf\RdfPageProjector;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use Wikimedia\ParamValidator\ParamValidator;

/**
 * Exports one page's Subjects and metadata as RDF. The `projection` query parameter selects the
 * vocabulary: "native" (default, NativeRdfProjection.md) or an ontology target that a Mapping page
 * declares (OntologyMapping.md) — an unknown projection is a 400. The `format` query parameter picks
 * the serialization, falling back to the Accept header, then to TriG. TriG keeps the per-page named
 * graph; Turtle emits the same triples without it.
 */
class ExportPageRdfApi extends SimpleHandler {

	use RdfFormatNegotiation;

	public function run( int $pageId ): Response {
		$extension = NeoWikiExtension::getInstance();
		$projectionName = $this->getValidatedParams()['projection'] ?? RdfPageProjector::PROJECTION;
		$resolution = $extension->resolveRdfProjection( $projectionName );

		if ( $resolution->projection === null ) {
			return $this->getResponseFactory()->createHttpError( 400, [
				'message' => 'Unknown RDF projection: "' . $projectionName . '". Known projections: '
					. implode( ', ', $resolution->knownProjectionNames ) . '.',
			] );
		}

		$page = new PageId( $pageId );

		// Denial reuses the exact no-data response so unreadable pages are indistinguishable
		// from pages without NeoWiki data. The gate lives here rather than in RdfPageLoader
		// because maintenance/DumpRdf.php shares the loader and must stay unfiltered.
		if ( !$extension->newPageReadAuthorizer( $this->getAuthority() )->authorizeReadByPageId( $page ) ) {
			return $this->noDataResponse( $pageId );
		}

		$format = $this->resolveFormat();

		$document = $extension
			->newRdfPageExporterForProjection( $resolution->projection )
			->exportByPageId( $page, $format );

		if ( $document === null ) {
			return $this->noDataResponse( $pageId );
		}

		return $this->rdfResponse( $document, $format );
	}

	private function noDataResponse( int $pageId ): Response {
		return $this->getResponseFactory()->createHttpError( 404, [
			'message' => 'No NeoWiki data found for page: ' . $pageId,
		] );
	}

	public function getParamSettings(): array {
		return [
			'pageId' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'integer',
				ParamValidator::PARAM_REQUIRED => true,
				self::PARAM_DESCRIPTION => 'MediaWiki page ID.',
			],
			'format' => [
				self::PARAM_SOURCE => 'query',
				ParamValidator::PARAM_TYPE => [
					self::FORMAT_TRIG,
					self::FORMAT_TURTLE,
				],
				ParamValidator::PARAM_REQUIRED => false,
				self::PARAM_DESCRIPTION => 'RDF serialization to return: "trig" (default, includes the per-page named graph) or "turtle". Overrides the Accept header.',
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
