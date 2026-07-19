<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\REST;

use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use MediaWiki\Rest\StringStream;
use ProfessionalWiki\NeoWiki\Application\Rdf\RdfPageProjector;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Rdf\RdfFormat;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use Wikimedia\ParamValidator\ParamValidator;

/**
 * Exports one page's Subjects and metadata as RDF. The `projection` query parameter selects the
 * vocabulary: "native" (default, NativeRdfProjection.md) or the name of a Mapping page
 * (OntologyMapping.md) — an unknown projection is a 400. The `format` query parameter picks
 * the serialization, falling back to the Accept header, then to TriG. TriG keeps the per-page named
 * graph; Turtle emits the same triples without it.
 */
class ExportPageRdfApi extends SimpleHandler {

	private const string FORMAT_TRIG = 'trig';
	private const string FORMAT_TURTLE = 'turtle';

	private const string CONTENT_TYPE_TRIG = 'application/trig; charset=utf-8';
	private const string CONTENT_TYPE_TURTLE = 'text/turtle; charset=utf-8';

	public function run( int $pageId ): Response {
		$extension = NeoWikiExtension::getInstance();
		$projectionName = $this->getValidatedParams()['projection'] ?? RdfPageProjector::PROJECTION;
		$resolution = $extension->resolveRdfProjection( $projectionName );

		if ( $resolution->projection === null ) {
			// Read-filter the known-projection list with the caller's own authority, so the 400 never
			// leaks the titles of Mapping pages they may not read — the same gate placement as the
			// per-page read check below.
			$knownProjections = $extension->filterReadableProjectionNames(
				$resolution->knownProjectionNames,
				$this->getAuthority()
			);

			return $this->getResponseFactory()->createHttpError( 400, [
				'message' => 'Unknown RDF projection: "' . $projectionName . '". Known projections: '
					. implode( ', ', $knownProjections ) . '.',
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

	private function resolveFormat(): RdfFormat {
		$requested = $this->getValidatedParams()['format'] ?? null;

		if ( $requested === self::FORMAT_TURTLE ) {
			return RdfFormat::Turtle;
		}

		if ( $requested === self::FORMAT_TRIG ) {
			return RdfFormat::TriG;
		}

		return $this->formatFromAcceptHeader();
	}

	private function formatFromAcceptHeader(): RdfFormat {
		$accept = $this->getRequest()->getHeaderLine( 'Accept' );

		// TriG is a superset of Turtle, so only pick Turtle when the client asks for it specifically.
		if ( str_contains( $accept, 'text/turtle' ) && !str_contains( $accept, 'application/trig' ) ) {
			return RdfFormat::Turtle;
		}

		return RdfFormat::TriG;
	}

	private function rdfResponse( string $document, RdfFormat $format ): Response {
		$response = $this->getResponseFactory()->create();
		$response->setHeader(
			'Content-Type',
			$format === RdfFormat::Turtle ? self::CONTENT_TYPE_TURTLE : self::CONTENT_TYPE_TRIG
		);
		$response->setBody( new StringStream( $document ) );

		return $response;
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
				self::PARAM_DESCRIPTION => 'RDF projection to produce: "native" (default) for NeoWiki-native vocabulary, or the name of a Mapping page (e.g. "EDM"). An unknown projection returns 400.',
			],
		];
	}

}
