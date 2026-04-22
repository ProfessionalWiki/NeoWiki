<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\REST;

use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use MediaWiki\Rest\StringStream;
use ProfessionalWiki\NeoWiki\Domain\Layout\Layout;
use ProfessionalWiki\NeoWiki\Domain\Layout\LayoutName;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\ParamValidator\TypeDef\IntegerDef;

class GetLayoutSummariesApi extends SimpleHandler {

	public function run(): Response {
		$params = $this->getValidatedParams();
		$extension = NeoWikiExtension::getInstance();
		$layoutNameLookup = $extension->getLayoutNameLookup();
		$layoutLookup = $extension->getLayoutLookup();

		$summaries = [];

		foreach ( $layoutNameLookup->getLayoutNames( $params['limit'], $params['offset'] ) as $title ) {
			$layout = $layoutLookup->getLayout( new LayoutName( $title->getText() ) );

			if ( $layout === null ) {
				continue;
			}

			$summaries[] = $this->layoutToSummary( $layout );
		}

		$result = [
			'layouts' => $summaries,
			'totalRows' => $layoutNameLookup->getLayoutCount(),
		];

		$response = $this->getResponseFactory()->create();
		$response->setBody( new StringStream( json_encode( $result ) ) );
		$response->setHeader( 'Content-Type', 'application/json' );

		return $response;
	}

	public function getParamSettings(): array {
		return [
			'limit' => [
				self::PARAM_SOURCE => 'query',
				ParamValidator::PARAM_TYPE => 'integer',
				ParamValidator::PARAM_REQUIRED => false,
				ParamValidator::PARAM_DEFAULT => 10,
				IntegerDef::PARAM_MIN => 1,
				IntegerDef::PARAM_MAX => 50,
				self::PARAM_DESCRIPTION => 'Maximum number of items to return.',
			],
			'offset' => [
				self::PARAM_SOURCE => 'query',
				ParamValidator::PARAM_TYPE => 'integer',
				ParamValidator::PARAM_REQUIRED => false,
				ParamValidator::PARAM_DEFAULT => 0,
				IntegerDef::PARAM_MIN => 0,
				self::PARAM_DESCRIPTION => 'Zero-based index of the first item to return.',
			],
		];
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
