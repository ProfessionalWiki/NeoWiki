<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\REST;

use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use MediaWiki\Rest\StringStream;
use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\ParamValidator\TypeDef\IntegerDef;

/**
 * Public REST endpoint: returns a paginated flat list of every subject on the given page.
 *
 * Response shape (public API contract):
 *   {
 *       "subjects": [ { "id": string, "label": string, "schema": string, "isMain": bool }, ... ],
 *       "totalRows": int  // count of all subjects on the page, before pagination is applied
 *   }
 *
 * Query parameters:
 *   - limit:  integer, default 10, min 1, max 50
 *   - offset: integer, default 0, min 0
 *
 * Sort order (public API contract):
 *   1. The page's main subject (if present).
 *   2. Child subjects by label ascending, case-insensitive.
 */
class GetPageSubjectsApi extends SimpleHandler {

	public function run( int $pageId ): Response {
		$title = Title::newFromID( $pageId );

		if ( $title === null ) {
			return $this->getResponseFactory()->createHttpError( 404, [
				'message' => 'Page not found: ' . $pageId,
			] );
		}

		if ( !$this->getAuthority()->authorizeRead( 'read', $title ) ) {
			return $this->getResponseFactory()->createHttpError( 403, [
				'message' => 'Permission denied: ' . $title->getPrefixedText(),
			] );
		}

		$params = $this->getValidatedParams();
		$limit = $params['limit'];
		$offset = $params['offset'];

		$ordered = $this->buildOrderedSubjects( $title );

		$totalRows = count( $ordered );
		$page = array_slice( $ordered, $offset, $limit );

		$result = [
			'subjects' => array_map( [ $this, 'toSummary' ], $page ),
			'totalRows' => $totalRows,
		];

		$response = $this->getResponseFactory()->create();
		$response->setBody( new StringStream( json_encode( $result ) ) );
		$response->setHeader( 'Content-Type', 'application/json' );

		return $response;
	}

	/**
	 * @return list<array{subject: Subject, isMain: bool}>
	 */
	private function buildOrderedSubjects( Title $title ): array {
		$content = NeoWikiExtension::getInstance()
			->newSubjectContentRepository()
			->getSubjectContentByPageTitle( $title );

		if ( $content === null ) {
			return [];
		}

		$pageSubjects = $content->getPageSubjects();
		$ordered = [];

		$mainSubject = $pageSubjects->getMainSubject();
		if ( $mainSubject !== null ) {
			$ordered[] = [ 'subject' => $mainSubject, 'isMain' => true ];
		}

		$children = $pageSubjects->getChildSubjects()->asArray();
		usort(
			$children,
			static fn ( Subject $a, Subject $b ): int =>
				strcmp( mb_strtolower( $a->getLabel()->text ), mb_strtolower( $b->getLabel()->text ) )
		);

		foreach ( $children as $child ) {
			$ordered[] = [ 'subject' => $child, 'isMain' => false ];
		}

		return $ordered;
	}

	/**
	 * @param array{subject: Subject, isMain: bool} $entry
	 * @return array{id: string, label: string, schema: string, isMain: bool}
	 */
	private function toSummary( array $entry ): array {
		$subject = $entry['subject'];

		return [
			'id' => $subject->getId()->text,
			'label' => $subject->getLabel()->text,
			'schema' => $subject->getSchemaName()->getText(),
			'isMain' => $entry['isMain'],
		];
	}

	public function getParamSettings(): array {
		return [
			'pageId' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'integer',
				ParamValidator::PARAM_REQUIRED => true,
			],
			'limit' => [
				self::PARAM_SOURCE => 'query',
				ParamValidator::PARAM_TYPE => 'integer',
				ParamValidator::PARAM_REQUIRED => false,
				ParamValidator::PARAM_DEFAULT => 10,
				IntegerDef::PARAM_MIN => 1,
				IntegerDef::PARAM_MAX => 50,
			],
			'offset' => [
				self::PARAM_SOURCE => 'query',
				ParamValidator::PARAM_TYPE => 'integer',
				ParamValidator::PARAM_REQUIRED => false,
				ParamValidator::PARAM_DEFAULT => 0,
				IntegerDef::PARAM_MIN => 0,
			],
		];
	}

}
