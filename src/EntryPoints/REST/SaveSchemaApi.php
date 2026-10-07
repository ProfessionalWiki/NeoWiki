<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\REST;

use MediaWiki\Request\WebResponse;
use MediaWiki\Rest\LocalizedHttpException;
use MediaWiki\Rest\Response;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\SchemaContent;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Presentation\DocumentationUrl;
use ProfessionalWiki\NeoWiki\Presentation\RestGetSchemaPresenter;
use Wikimedia\Message\MessageValue;
use Wikimedia\ParamValidator\ParamValidator;

class SaveSchemaApi extends SchemaPageActionApi {

	protected function getActionParameters(): array {
		return [
			'action' => 'edit',
			'contentmodel' => SchemaContent::CONTENT_MODEL_ID,
			'text' => $this->getSchemaJson(),
		] + $this->getCommentAs( 'summary' );
	}

	/**
	 * Read from the raw body because MediaWiki's REST layer decodes JSON objects into PHP arrays, and an empty
	 * object, such as an empty `propertyDefinitions`, would come back out as a list.
	 */
	private function getSchemaJson(): string {
		$body = json_decode( (string)$this->getRequest()->getBody() );

		return (string)json_encode( is_object( $body ) ? $body->schema ?? null : null );
	}

	protected function mapActionModuleResult( array $data ): mixed {
		if ( ( $data['edit']['result'] ?? null ) !== 'Success' ) {
			// An extension can hold an edit back with a result instead of an error, as ConfirmEdit does to ask
			// for a CAPTCHA.
			throw new LocalizedHttpException(
				new MessageValue( 'hookaborted' ),
				400,
				[ 'actionModuleResult' => $data['edit'] ?? null ]
			);
		}

		$presenter = new RestGetSchemaPresenter();

		NeoWikiExtension::getInstance()->newGetSchemaQuery( $presenter )->execute( $this->getSchemaPage()->getText() );

		return json_decode( $presenter->getJson() );
	}

	protected function mapActionModuleResponse(
		WebResponse $actionModuleResponse,
		array $actionModuleResult,
		Response $response
	): void {
		parent::mapActionModuleResponse( $actionModuleResponse, $actionModuleResult, $response );

		if ( $actionModuleResult['edit']['new'] ?? false ) {
			$response->setStatus( 201 );
		}
	}

	public function getBodyParamSettings(): array {
		return [
			'schema' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'array',
				ParamValidator::PARAM_REQUIRED => true,
				self::PARAM_DESCRIPTION => 'The whole Schema, in the shape GET /neowiki/v0/schema/{schemaName} returns under '
					. '`schema`. Format documented at ' . DocumentationUrl::SchemaFormat->value . '.',
			],
			'comment' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => false,
				self::PARAM_DESCRIPTION => 'Optional edit summary.',
			],
		];
	}

}
