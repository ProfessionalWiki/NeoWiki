<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\REST;

use MediaWiki\MediaWikiServices;
use MediaWiki\Rest\Response;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Presentation\DocumentationUrl;
use ProfessionalWiki\NeoWiki\Presentation\RestReplaceSubjectPresenter;
use ProfessionalWiki\NeoWiki\Presentation\RestUpdateStatementPresenter;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\Rdbms\IDBAccessObject;

trait TakesBaseRevision {

	/**
	 * @return array<string, array<string, mixed>>
	 */
	private function baseRevisionParamSettings(): array {
		return [
			'baseRevisionId' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'integer',
				ParamValidator::PARAM_REQUIRED => true,
				self::PARAM_DESCRIPTION => 'The revision the write is based on: the `revisionId` of the `?latest=1` '
					. 'read or the write response the change was made from. A write that would overwrite a change '
					. 'made after that revision answers 409. See ' . DocumentationUrl::SubjectFormat->value . '.',
			],
		];
	}

	/**
	 * @param array<string, mixed> $body Validated against the settings above.
	 */
	private function baseRevisionIdOf( array $body ): int {
		return $body['baseRevisionId'];
	}

	/**
	 * A 409 shows the Subject as the page's current revision holds it, which the wiki's revision policy may keep from
	 * the caller, as `GET ?latest=1` does.
	 */
	private function writeResponse( RestReplaceSubjectPresenter|RestUpdateStatementPresenter $presenter ): Response {
		$json = $presenter->getJsonArray();

		if ( $presenter->getStatusCode() === 409 && array_key_exists( 'subject', $json ) && !$this->mayReadRevision( $json['revisionId'] ) ) {
			unset( $json['subject'] );
		}

		$response = $this->getResponseFactory()->createJson( $json );
		$response->setStatus( $presenter->getStatusCode() );
		return $response;
	}

	private function mayReadRevision( int $revisionId ): bool {
		$revision = MediaWikiServices::getInstance()->getRevisionLookup()
			->getRevisionById( $revisionId, IDBAccessObject::READ_LATEST );

		return $revision !== null
			&& NeoWikiExtension::getInstance()->getRevisionPolicy()->revisionIsReadableBy( $revision, $this->getAuthority() );
	}

}
