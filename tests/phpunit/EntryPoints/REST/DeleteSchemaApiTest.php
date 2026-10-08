<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints\REST;

use MediaWiki\Rest\RequestData;
use MediaWiki\Rest\ResponseInterface;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use ProfessionalWiki\NeoWiki\EntryPoints\REST\DeleteSchemaApi;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;
use Wikimedia\Rdbms\IDBAccessObject;

/**
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\REST\DeleteSchemaApi
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\REST\SchemaPageWriteApi
 * @group Database
 */
class DeleteSchemaApiTest extends NeoWikiIntegrationTestCase {

	use RunsSchemaWriteEndpoints;

	public function testDeletesTheSchemaPage(): void {
		$this->createSchema( 'Person' );

		$response = $this->delete( 'Person' );

		$this->assertSame( 200, $response->getStatusCode() );
		$this->assertSame( '', $this->bodyOf( $response ) );
		$this->assertFalse( $this->schemaPageExists( 'Person' ) );
	}

	public function testCommentBecomesTheDeletionReason(): void {
		$this->createSchema( 'Person' );

		$this->delete( 'Person', comment: 'Merged into Contact' );

		$this->assertSame( 'Merged into Contact', $this->getDeletionReason( 'Person' ) );
	}

	public function testDeletionWithoutCommentGetsTheGeneratedReason(): void {
		$this->createSchema( 'Person' );

		$this->delete( 'Person' );

		$this->assertNotEmpty( $this->getDeletionReason( 'Person' ) );
	}

	public function testUserWithoutTheDeleteRightIsRefused(): void {
		$this->createSchema( 'Person' );

		$response = $this->deleteAs( $this->getTestUser()->getUser(), 'Person' );

		$this->assertSame( 403, $response->getStatusCode() );
		$this->assertTrue( $this->schemaPageExists( 'Person' ) );
	}

	public function testSchemaYouMayNotReadAnswersLikeAMissingOne(): void {
		$this->createSchema( 'Person' );
		$this->denyReadingSchemaPage( 'Person' );

		$unreadable = $this->delete( 'Person' );
		$missing = $this->delete( 'Nobody' );

		$this->assertTrue( $this->schemaPageExists( 'Person' ) );
		$this->assertSame( 404, $missing->getStatusCode() );
		$this->assertSame( $this->bodyWithNameMasked( $missing, 'Nobody' ), $this->bodyWithNameMasked( $unreadable, 'Person' ) );
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

	public function testNameWithASectionDoesNotReachThePage(): void {
		$this->createSchema( 'Person' );

		$response = $this->delete( 'Person#Contact' );

		$this->assertSame( 400, $response->getStatusCode() );
		$this->assertTrue( $this->schemaPageExists( 'Person' ) );
	}

	public function testNameCannotReachAPageOutsideTheSchemaNamespace(): void {
		$this->editPage( Title::makeTitle( NS_HELP, 'Person' ), 'Not a Schema' );

		$this->delete( 'Help:Person' );

		$this->assertTrue( Title::makeTitle( NS_HELP, 'Person' )->exists( IDBAccessObject::READ_LATEST ) );
	}

	private function delete( string $schemaName, ?string $comment = null ): ResponseInterface {
		return $this->deleteAs( $this->getTestSysop()->getUser(), $schemaName, $comment );
	}

	private function deleteAs( User $user, string $schemaName, ?string $comment = null ): ResponseInterface {
		return $this->executeAs(
			$user,
			new DeleteSchemaApi( csrfValidator: $this->newCsrfValidatorStub() ),
			$this->newDeleteRequest( $schemaName, $comment )
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
		return json_decode( str_replace( $schemaName, '<name>', $this->bodyOf( $response ) ), true );
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
