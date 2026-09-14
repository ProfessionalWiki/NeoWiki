<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\REST;

use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Presentation\RestGetReferencingSubjectsPresenter;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\ParamValidator\TypeDef\IntegerDef;

class GetReferencingSubjectsApi extends SimpleHandler {

	use ReadOnlyEndpoint;

	public function run( string $subjectId ): Response {
		// Validate the ID shape first, so a malformed value is a clean 400 rather than becoming a 500.
		if ( NeoWikiExtension::getInstance()->getSubjectIdParser()->parse( $subjectId ) === null ) {
			return $this->getResponseFactory()->createHttpError( 400, [
				'message' => 'Invalid Subject ID: ' . $subjectId,
			] );
		}

		$presenter = new RestGetReferencingSubjectsPresenter();

		NeoWikiExtension::getInstance()
			->newGetReferencingSubjectsQuery( $presenter, $this->getAuthority() )
			->execute( subjectId: $subjectId, limit: $this->getValidatedParams()['limit'] );

		return $this->getResponseFactory()->createJson( $presenter->getJsonArray() );
	}

	public function getParamSettings(): array {
		return [
			'subjectId' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
				self::PARAM_DESCRIPTION => 'Persistent identifier of the Subject the returned Subjects point at.',
			],
			'limit' => [
				self::PARAM_SOURCE => 'query',
				ParamValidator::PARAM_TYPE => 'integer',
				ParamValidator::PARAM_REQUIRED => false,
				ParamValidator::PARAM_DEFAULT => 10,
				IntegerDef::PARAM_MIN => 1,
				IntegerDef::PARAM_MAX => 50,
				self::PARAM_DESCRIPTION => 'Maximum number of referencing Subjects to return.',
			],
		];
	}

}
