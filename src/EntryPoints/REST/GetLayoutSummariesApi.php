<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\REST;

use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use MediaWiki\Rest\StringStream;
use MediaWiki\Title\TitleValue;
use ProfessionalWiki\NeoWiki\Domain\Layout\Layout;
use ProfessionalWiki\NeoWiki\Domain\Layout\LayoutName;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;

class GetLayoutSummariesApi extends SimpleHandler {

	use CursorPaginationTrait;

	public function run(): Response {
		$params = $this->getValidatedParams();
		$extension = NeoWikiExtension::getInstance();
		$layoutLookup = $extension->getLayoutLookup();

		$page = $this->buildPage(
			$extension->getLayoutNameLookup()->getReadableLayoutNames( $this->pageIdFromCursor( $params['cursor'] ) ),
			$params['limit'],
			function ( TitleValue $title ) use ( $layoutLookup ): ?array {
				$layout = $layoutLookup->getLayout( new LayoutName( $title->getText() ) );
				return $layout === null ? null : $this->layoutToSummary( $layout );
			}
		);

		$result = [
			'layouts' => $page['items'],
			'nextCursor' => $page['nextCursor'],
		];

		$response = $this->getResponseFactory()->create();
		$response->setBody( new StringStream( json_encode( $result ) ) );
		$response->setHeader( 'Content-Type', 'application/json' );

		return $response;
	}

	public function getParamSettings(): array {
		return $this->paginationParamSettings();
	}

	/**
	 * @return array{name: string, schema: string, type: string, description: string, ruleCount: int}
	 */
	private function layoutToSummary( Layout $layout ): array {
		return [
			'name' => $layout->getName()->getText(),
			'schema' => $layout->getSchema()->getText(),
			'type' => $layout->getType(),
			'description' => $layout->getDescription(),
			'ruleCount' => count( iterator_to_array( $layout->getDisplayRules() ) ),
		];
	}

}
