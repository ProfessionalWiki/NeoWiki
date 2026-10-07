<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints\REST;

use MediaWiki\Rest\RequestData;
use MediaWiki\Rest\ResponseInterface;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use ProfessionalWiki\NeoWiki\EntryPoints\REST\DeleteSchemaApi;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiMockAuthorityTrait;

/**
 * Deletes through MediaWiki's real delete path in the test database: behaving exactly like that path is the
 * endpoint's contract.
 *
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\REST\DeleteSchemaApi
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\REST\SchemaPageActionApi
 * @group Database
 */
class DeleteSchemaApiTest extends NeoWikiIntegrationTestCase {

	use HandlerTestTrait;
	use NeoWikiMockAuthorityTrait;
	use RunsSchemaPageActions;

	public function testDeletesTheSchemaPage(): void {
		$this->createSchema( 'Person' );

		$response = $this->delete( 'Person' );

		$this->assertSame( 200, $response->getStatusCode() );
		$this->assertFalse( $this->schemaPageExists( 'Person' ) );
	}

	public function testCommentBecomesTheDeletionReason(): void {
		$this->createSchema( 'Person' );

		$this->executeAs(
			$this->getTestSysop()->getUser(),
			new DeleteSchemaApi( csrfValidator: $this->newCsrfValidatorStub() ),
			$this->newDeleteRequest( 'Person', comment: 'Merged into Contact' )
		);

		$this->assertSame( 'Merged into Contact', $this->getDeletionReason( 'Person' ) );
	}

	public function testUserWithoutTheDeleteRightIsRefused(): void {
		$this->createSchema( 'Person' );

		$response = $this->executeAs(
			$this->getTestUser()->getUser(),
			new DeleteSchemaApi( csrfValidator: $this->newCsrfValidatorStub() ),
			$this->newDeleteRequest( 'Person' )
		);

		$this->assertSame( 403, $response->getStatusCode() );
		$this->assertTrue( $this->schemaPageExists( 'Person' ) );
	}

	public function testMissingSchemaAnswersNotFound(): void {
		$response = $this->delete( 'Person' );

		$this->assertSame( 404, $response->getStatusCode() );
	}

	public function testSchemaYouMayNotReadAnswersLikeAMissingOne(): void {
		$this->createSchema( 'Person' );

		// One Authority for both requests: comparing responses obtained under two different Authorities says
		// nothing about what any single caller can tell apart. It may read every other page, the missing one
		// included, as under an access control that restricts pages one by one.
		$authority = $this->authorityThatCannotReadPageId( $this->schemaTitle( 'Person' )->getArticleID() );

		$unreadable = $this->executeAs(
			$authority,
			new DeleteSchemaApi( csrfValidator: $this->newCsrfValidatorStub() ),
			$this->newDeleteRequest( 'Person' )
		);
		$missing = $this->executeAs(
			$authority,
			new DeleteSchemaApi( csrfValidator: $this->newCsrfValidatorStub() ),
			$this->newDeleteRequest( 'Nobody' )
		);

		$this->assertSame( 404, $unreadable->getStatusCode() );
		$this->assertSame( $this->bodyWithNameMasked( $missing, 'Nobody' ), $this->bodyWithNameMasked( $unreadable, 'Person' ) );
		$this->assertTrue( $this->schemaPageExists( 'Person' ) );
	}

	public function testRequestWithoutCsrfTokenIsRefused(): void {
		$this->createSchema( 'Person' );

		$response = $this->executeAs(
			$this->getTestSysop()->getUser(),
			new DeleteSchemaApi( csrfValidator: $this->newTokenlessCsrfValidator() ),
			$this->newDeleteRequest( 'Person' )
		);

		$this->assertSame( 403, $response->getStatusCode() );
		$this->assertTrue( $this->schemaPageExists( 'Person' ) );
	}

	private function delete( string $schemaName ): ResponseInterface {
		return $this->executeAs(
			$this->getTestSysop()->getUser(),
			new DeleteSchemaApi( csrfValidator: $this->newCsrfValidatorStub() ),
			$this->newDeleteRequest( $schemaName )
		);
	}

	private function newDeleteRequest( string $schemaName, ?string $comment = null ): RequestData {
		return new RequestData( [
			'method' => 'DELETE',
			'pathParams' => [ 'schemaName' => $schemaName ],
			'bodyContents' => json_encode( $comment === null ? (object)[] : [ 'comment' => $comment ] ),
			'headers' => [ 'Content-Type' => 'application/json' ],
		] );
	}

	/**
	 * Masking the echoed name, which is only what the caller supplied, leaves everything that could tell the
	 * two answers apart.
	 */
	private function bodyWithNameMasked( ResponseInterface $response, string $schemaName ): array {
		$response->getBody()->rewind();
		return json_decode( str_replace( $schemaName, '<name>', $response->getBody()->getContents() ), true );
	}

	private function getDeletionReason( string $schemaName ): string|false {
		return $this->getDb()->newSelectQueryBuilder()
			->select( 'comment_text' )
			->from( 'logging' )
			->join( 'comment', null, 'comment_id = log_comment_id' )
			->where( [
				'log_type' => 'delete',
				'log_namespace' => NeoWikiExtension::NS_SCHEMA,
				'log_title' => $schemaName,
			] )
			->caller( __METHOD__ )
			->fetchField();
	}

}
