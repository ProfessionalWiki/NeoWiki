<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\REST;

use Exception;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use ProfessionalWiki\NeoWiki\Application\Rdf\RdfPageProjector;
use ProfessionalWiki\NeoWiki\Domain\GraphDatabase\BackendFailureMessage;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\Rdbms\DBError;
use Wikimedia\RequestTimeout\TimeoutException;

/**
 * Exports one page's Subjects and metadata as RDF. The `projection` query parameter selects the
 * vocabulary: "native" (default, NativeRdfProjection.md) or the name of a Mapping page
 * (OntologyMapping.md) — an unknown projection is a 400. The `format` query parameter picks
 * the serialization, falling back to the Accept header, then to TriG. TriG keeps the per-page named
 * graph; Turtle emits the same triples without it.
 */
class ExportPageRdfApi extends SimpleHandler {

	use RdfFormatNegotiation;
	use ReadOnlyEndpoint;

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

		// Denial reuses the exact no-data response so unreadable pages are indistinguishable from pages
		// that do not exist. The gate lives here rather than in RdfPageLoader because
		// maintenance/DumpRdf.php shares the loader and must stay unfiltered.
		if ( !$extension->newPageReadAuthorizer( $this->getAuthority() )->authorizeReadByPageId( $page ) ) {
			return $this->noDataResponse( $pageId );
		}

		$format = $this->resolveFormat();

		// Building the page's properties parses it and runs the registered Page Property Providers,
		// either of which can throw for a page MediaWiki can no longer fully handle — one whose content
		// model an uninstalled extension owned, say. Every page has an export now, so such a page is
		// reachable here, and letting the throwable out turns the documented 404 into a 500 carrying the
		// exception message. Report it as no data, the same answer the caller gets for the other page
		// states this export cannot describe. Which throwables are let through matches
		// FailureIsolatingGraphDatabasePlugin: a request timeout must still abort the request, and a
		// wiki-database error belongs to the request rather than to this page.
		try {
			$document = $extension
				->newRdfPageExporterForProjection( $resolution->projection )
				->exportByPageId( $page, $format );
		} catch ( TimeoutException | DBError $e ) {
			throw $e;
		} catch ( Exception $e ) {
			LoggerFactory::getInstance( 'NeoWiki' )->error(
				'NeoWiki could not export page {pageId} as RDF: {message}',
				[
					'pageId' => $pageId,
					'message' => BackendFailureMessage::withoutCredentials( $e->getMessage() ),
					'exception' => $e,
				]
			);

			return $this->noDataResponse( $pageId );
		}

		if ( $document === null ) {
			return $this->noDataResponse( $pageId );
		}

		return $this->rdfResponse( $document, $format, (string)$pageId );
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
				self::PARAM_DESCRIPTION => 'RDF projection to produce: "native" (default) for NeoWiki-native vocabulary, or the name of a Mapping page (e.g. "EDM"). An unknown projection returns 400.',
			],
		];
	}

}
