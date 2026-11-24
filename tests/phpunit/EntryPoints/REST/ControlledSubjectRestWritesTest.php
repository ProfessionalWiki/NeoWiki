<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints\REST;

use MediaWiki\Context\RequestContext;
use MediaWiki\Rest\Handler;
use MediaWiki\Rest\RequestData;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\EntryPoints\REST\CreateSubjectPageApi;
use ProfessionalWiki\NeoWiki\EntryPoints\REST\MoveSubjectApi;
use ProfessionalWiki\NeoWiki\EntryPoints\REST\ReplaceSubjectApi;
use ProfessionalWiki\NeoWiki\EntryPoints\REST\SetMainSubjectApi;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Presentation\CsrfValidator;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSchema;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;

/**
 * Goes through real pages: the rules for Controlled Subjects are held where MediaWiki saves, which every Subject
 * write endpoint reaches.
 *
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\PageContentSaver
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\MediaWikiSubjectRepository
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\REST\CreateSubjectPageApi
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\REST\ReplaceSubjectApi
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\REST\MoveSubjectApi
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\REST\SetMainSubjectApi
 * @group Database
 */
class ControlledSubjectRestWritesTest extends NeoWikiIntegrationTestCase {

	use HandlerTestTrait;
	use WritesOnTheCurrentRevision;

	private const string TERM = 'sCSRWWoman1111a';

	public function setUp(): void {
		$this->createSchema( 'Gender', '{"subjectsAreControlled":true,"propertyDefinitions":{}}' );
	}

	private function csrfValidator(): CsrfValidator {
		$validator = $this->createStub( CsrfValidator::class );
		$validator->method( 'verifyCsrfToken' )->willReturn( true );
		return $validator;
	}

	/**
	 * @param array<string, mixed> $pathParams
	 * @param array<string, mixed> $body
	 * @return array{0: int, 1: array<string, mixed>}
	 */
	private function callWithoutTheRight( Handler $handler, string $method, array $pathParams, array $body ): array {
		$user = $this->getTestUser()->getUser();
		RequestContext::getMain()->setUser( $user );

		$response = $this->executeHandler(
			$handler,
			new RequestData( [
				'method' => $method,
				'pathParams' => $pathParams,
				'bodyContents' => json_encode( $body ),
				'headers' => [ 'Content-Type' => 'application/json' ],
			] ),
			authority: $user
		);

		return [ $response->getStatusCode(), json_decode( $response->getBody()->getContents() ?: 'null', true ) ?? [] ];
	}

	/**
	 * @param array{0: int, 1: array<string, mixed>} $response
	 */
	private function assertRefusalNamingTheSchema( array $response ): void {
		$this->assertSame( 403, $response[0] );
		$this->assertStringContainsString( 'Gender', $response[1]['message'] );
	}

	private function pageWithTheTerm(): int {
		return $this->createPageWithoutMainSubject(
			'Woman',
			TestSubject::build( id: self::TERM, label: 'Woman', schemaName: TestSchema::reference( 'Gender' ) )
		);
	}

	/**
	 * @return string[]
	 */
	private function idsOn( int $pageId ): array {
		return NeoWikiExtension::getInstance()->newSubjectRepository()->getSubjectsByPageId( new PageId( $pageId ) )
			->getAllSubjects()->getIdsAsTextArray();
	}

	public function testCreatingAPageForAControlledSubjectWithoutTheRightAnswers403(): void {
		$this->assertRefusalNamingTheSchema( $this->callWithoutTheRight(
			new CreateSubjectPageApi( $this->csrfValidator() ),
			'POST',
			[],
			[ 'label' => 'Non-binary', 'schema' => 'Gender', 'statements' => [] ]
		) );
	}

	public function testReplacingAControlledSubjectWithoutTheRightAnswers403(): void {
		$this->pageWithTheTerm();

		$this->assertRefusalNamingTheSchema( $this->callWithoutTheRight(
			new ReplaceSubjectApi( $this->csrfValidator() ),
			'PUT',
			[ 'subjectId' => self::TERM ],
			$this->onTheCurrentRevision( self::TERM, [ 'label' => 'Female', 'statements' => [] ] )
		) );
	}

	public function testMovingAControlledSubjectWithoutTheRightLeavesItWhereItWas(): void {
		$sourcePageId = $this->pageWithTheTerm();
		$targetPageId = $this->createPageWithSubjects( 'Genders' )->getPageId();

		$this->assertRefusalNamingTheSchema( $this->callWithoutTheRight(
			new MoveSubjectApi( $this->csrfValidator() ),
			'POST',
			[ 'subjectId' => self::TERM ],
			[ 'targetPageId' => $targetPageId ]
		) );
		$this->assertSame( [ self::TERM ], $this->idsOn( $sourcePageId ) );
		$this->assertSame( [], $this->idsOn( $targetPageId ) );
	}

	public function testMakingAControlledSubjectTheMainSubjectNeedsNoRight(): void {
		$pageId = $this->pageWithTheTerm();

		[ $status ] = $this->callWithoutTheRight(
			new SetMainSubjectApi( $this->csrfValidator() ),
			'PUT',
			[ 'pageId' => $pageId ],
			[ 'subjectId' => self::TERM ]
		);

		$this->assertSame( 200, $status );
	}

}
