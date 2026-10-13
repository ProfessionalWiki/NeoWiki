<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints\REST;

use MediaWiki\Context\RequestContext;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\RequestData;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * Needs the database for the pages and revisions it lists from.
 *
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\REST\GetRecentlyEditedSubjectsApi
 * @group Database
 */
class GetRecentlyEditedSubjectsApiTest extends NeoWikiIntegrationTestCase {

	use HandlerTestTrait;

	public function setUp(): void {
		$this->createSchema( 'Computer' );
	}

	public function testListsTheSubjectsEditedLastWithTheirPages(): void {
		ConvertibleTimestamp::setFakeTime( '2030-01-07T09:00:00Z' );
		$pageId = $this->createSubjectOnPage( 'sTestGRE1111111', 'ZX Spectrum 48K', 'Sinclair ZX Spectrum' );

		$this->assertEquals(
			[
				'id' => 'sTestGRE1111111',
				'displayName' => 'ZX Spectrum 48K',
				'displayNameIsGenerated' => false,
				'schema' => 'Computer',
				'pageId' => $pageId,
				'pageTitle' => 'Sinclair ZX Spectrum',
				'lastEdited' => '2030-01-07T09:00:00Z',
			],
			$this->get( [] )['subjects'][0]
		);
	}

	public function testListsNoMoreSubjectsThanTheLimit(): void {
		$this->createPageWithSubjects(
			'Home computers',
			TestSubject::build( id: 'sTestGRE1111111', schemaName: new SchemaName( 'Computer' ) ),
			new SubjectMap( TestSubject::build( id: 'sTestGRE1111112', schemaName: new SchemaName( 'Computer' ) ) )
		);

		$this->assertCount( 1, $this->get( [ 'limit' => '1' ] )['subjects'] );
	}

	public function testLimitBeyondTheMaximumIsRejected(): void {
		$this->expectException( HttpException::class );
		$this->expectExceptionCode( 400 );

		$this->get( [ 'limit' => '51' ] );
	}

	public function testListsWithoutAGraphStore(): void {
		$subjects = $this->runWithoutGraphBackend( function (): array {
			$this->createSubjectOnPage( 'sTestGRE1111111', 'ZX Spectrum 48K', 'Sinclair ZX Spectrum' );

			return $this->get( [] )['subjects'];
		} );

		$this->assertSame( [ 'sTestGRE1111111' ], array_column( $subjects, 'id' ) );
	}

	public function testLeavesOutSubjectsOnPagesTheCallerMayNotRead(): void {
		$this->createSubjectOnPage( 'sTestGRE1111111', 'Open', 'Open' );
		$this->createSubjectOnPage( 'sTestGRE1111112', 'Secret', 'Secret' );

		RequestContext::getMain()->setUser( $this->getTestUser()->getUser() );
		NeoWikiExtension::resetInstance();
		$this->setTemporaryHook(
			'getUserPermissionsErrors',
			static function ( $title, $user, $action, &$result ): bool {
				if ( $action === 'read' && $title->getText() === 'Secret' ) {
					$result = [ 'badaccess-group0' ];
					return false;
				}
				return true;
			}
		);

		$this->assertSame( [ 'sTestGRE1111111' ], array_column( $this->get( [] )['subjects'], 'id' ) );
	}

	private function createSubjectOnPage( string $id, string $label, string $pageName ): int {
		$revision = $this->createPageWithSubjects(
			$pageName,
			TestSubject::build( id: $id, label: $label, schemaName: new SchemaName( 'Computer' ) )
		);
		$this->assertNotNull( $revision );

		return $revision->getPageId();
	}

	/**
	 * @param array<string, string> $queryParams
	 * @return array<string, mixed>
	 */
	private function get( array $queryParams ): array {
		return json_decode( $this->executeHandler(
			NeoWikiExtension::newGetRecentlyEditedSubjectsApi(),
			new RequestData( [ 'method' => 'GET', 'queryParams' => $queryParams ] )
		)->getBody()->getContents(), true );
	}

}
