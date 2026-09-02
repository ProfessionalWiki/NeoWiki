<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests;

use MediaWiki\CommentStore\CommentStoreComment;
use MediaWiki\Content\WikitextContent;
use MediaWiki\Context\RequestContext;
use MediaWiki\MediaWikiServices;
use MediaWiki\Output\OutputPage;
use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\Application\NullSubjectLabelLookup;
use ProfessionalWiki\NeoWiki\Domain\GraphDatabase\GraphBackendNotConfiguredException;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyName;
use ProfessionalWiki\NeoWiki\Domain\Statement;
use ProfessionalWiki\NeoWiki\Domain\Subject\StatementList;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Value\StringValue;
use ProfessionalWiki\NeoWiki\EntryPoints\NeoWikiHooks;
use ProfessionalWiki\NeoWiki\EntryPoints\Scribunto\SubjectDataLookup;
use ProfessionalWiki\NeoWiki\GraphDatabasePlugins\Neo4j\Persistence\Neo4jSubjectLabelLookup;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use TestLogger;

/**
 * A wiki with no graph backend configured is a supported mode: Subjects are stored, found, read and
 * rendered without one, and only the query surfaces a backend brings are absent (#1040).
 *
 * @covers \ProfessionalWiki\NeoWiki\NeoWikiExtension
 * @group Database
 */
class NoGraphBackendTest extends NeoWikiIntegrationTestCase {

	private const SUBJECT_ID = 's1zz1111111azz1';
	private const PROPERTY = 'Motto';
	private const VALUE = 'Beyond the graph';

	public function testGetNeo4jPluginIsNullWithoutBackend(): void {
		$plugin = $this->runWithoutGraphBackend(
			static fn() => NeoWikiExtension::getInstance()->getNeo4jPlugin()
		);

		$this->assertNull( $plugin );
	}

	public function testGetNeo4jPluginIsPresentWhenConfigured(): void {
		$this->assertNotNull( NeoWikiExtension::getInstance()->getNeo4jPlugin() );
	}

	public function testEditSucceedsWithoutBackend(): void {
		$this->runWithoutGraphBackend( function (): void {
			$wikiPage = MediaWikiServices::getInstance()->getWikiPageFactory()
				->newFromTitle( Title::newFromText( 'NoBackendEditPage' ) );

			$updater = $wikiPage->newPageUpdater( $this->getTestSysop()->getUser() );
			$updater->setContent( 'main', new WikitextContent( 'hello' ) );
			$updater->saveRevision( CommentStoreComment::newUnsavedComment( 'no-backend edit' ) );

			$this->assertTrue( $updater->wasRevisionCreated() );
		} );
	}

	public function testSubjectIsStoredAndFoundByIdWithoutBackend(): void {
		$this->runWithoutGraphBackend( function (): void {
			$this->createPageWithMottoSubject( 'NoBackendSubjectPage' );

			$subject = NeoWikiExtension::getInstance()->getSubjectRepository()
				->getSubject( new SubjectId( self::SUBJECT_ID ) );

			$this->assertNotNull( $subject );
			$this->assertSame( self::SUBJECT_ID, $subject->id->text );
		} );
	}

	public function testSubjectIsEditedThroughTheRepositoryWithoutBackend(): void {
		$this->runWithoutGraphBackend( function (): void {
			$this->createPageWithMottoSubject( 'NoBackendEditedSubjectPage' );
			$repository = NeoWikiExtension::getInstance()->getSubjectRepository();

			$repository->updateSubject(
				$this->mottoSubject( 'Rewritten' ),
				'no-backend subject edit'
			);

			$this->assertSame( 'Rewritten', $this->mottoOfIndexedSubject() );
		} );
	}

	public function testSubjectIsDeletedThroughTheRepositoryWithoutBackend(): void {
		$this->runWithoutGraphBackend( function (): void {
			$this->createPageWithMottoSubject( 'NoBackendDeletedSubjectPage' );
			$repository = NeoWikiExtension::getInstance()->getSubjectRepository();

			$repository->deleteSubject( new SubjectId( self::SUBJECT_ID ), 'no-backend subject delete' );

			$this->assertNull( $repository->getSubject( new SubjectId( self::SUBJECT_ID ) ) );
		} );
	}

	public function testValueParserFunctionReadsAValueWithoutBackend(): void {
		$html = $this->runWithoutGraphBackend( function (): string {
			$this->createPageWithMottoSubject( 'NoBackendValuePage' );

			return $this->parseWikitextOn(
				'NoBackendValuePage',
				'{{#neowiki_value: ' . self::PROPERTY . ' | subject=' . self::SUBJECT_ID . ' }}'
			);
		} );

		$this->assertStringContainsString( self::VALUE, $html );
	}

	/**
	 * The engine behind the mw.neowiki.* getters, reached by Subject id, which is the path that needed
	 * the reverse index.
	 */
	public function testLuaGetterReadsAValueBySubjectIdWithoutBackend(): void {
		$value = $this->runWithoutGraphBackend( function (): array {
			$this->createPageWithMottoSubject( 'NoBackendLuaPage' );
			$resolver = NeoWikiExtension::getInstance()->newSubjectResolver( $this->getTestSysop()->getUser() );
			return ( new SubjectDataLookup( $resolver ) )->getValue(
				Title::newFromText( 'NoBackendLuaPage' ),
				self::PROPERTY,
				[ 'subject' => self::SUBJECT_ID ]
			);
		} );

		$this->assertSame( [ self::VALUE ], $value );
	}

	public function testContentPageRenderInjectsTheAppWithoutBackend(): void {
		$out = $this->newContentPageOutput( 'NoBackendAppPage' );

		$this->runWithoutGraphBackend( static function () use ( $out ): void {
			NeoWikiHooks::onBeforePageDisplay( $out, $out->getSkin() );
		} );

		$this->assertStringContainsString( 'ext-neowiki-app', $out->getHTML() );
	}

	public function testContentPageRenderInjectsTheAppWhenConfigured(): void {
		$out = $this->newContentPageOutput( 'ConfiguredBackendViewPage' );

		NeoWikiHooks::onBeforePageDisplay( $out, $out->getSkin() );

		$this->assertStringContainsString( 'ext-neowiki-app', $out->getHTML() );
	}

	public function testContentPageRenderIsSilentWithoutBackend(): void {
		$out = $this->newContentPageOutput( 'NoBackendQuietPage' );
		$logger = new TestLogger( true );
		$this->setLogger( 'NeoWiki', $logger );

		$this->runWithoutGraphBackend( static function () use ( $out ): void {
			NeoWikiHooks::onBeforePageDisplay( $out, $out->getSkin() );
		} );

		$this->assertSame( [], $logger->getBuffer() );
	}

	/**
	 * Half a Neo4j configuration is not a supported mode but an unfinished one, so it is still reported.
	 */
	public function testHalfConfiguredNeo4jIsReported(): void {
		$out = $this->newContentPageOutput( 'HalfConfiguredPage' );
		$logger = new TestLogger( true );
		$this->setLogger( 'NeoWiki', $logger );

		$this->runWithoutGraphBackend( function () use ( $out ): void {
			$this->overrideConfigValue( 'NeoWikiNeo4jInternalReadUrl', 'bolt://neo:7687' );
			NeoWikiExtension::resetInstance();

			NeoWikiHooks::onBeforePageDisplay( $out, $out->getSkin() );
		} );

		$this->assertStringContainsString( 'only one of the Neo4j read/write Bolt URLs', self::loggedText( $logger ) );
	}

	public function testRelationTargetSuggestionsAreEmptyWithoutBackend(): void {
		$suggestions = $this->runWithoutGraphBackend(
			static fn() => NeoWikiExtension::getInstance()->getSubjectLabelLookup()
				->getSubjectLabelsMatching( 'anything', 10, TestSubject::DEFAULT_SCHEMA_ID )
		);

		$this->assertSame( [], $suggestions );
	}

	public function testSubjectLabelLookupIsNullObjectWithoutBackend(): void {
		$lookup = $this->runWithoutGraphBackend(
			static fn() => NeoWikiExtension::getInstance()->getSubjectLabelLookup()
		);

		$this->assertInstanceOf( NullSubjectLabelLookup::class, $lookup );
	}

	public function testSubjectLabelLookupIsNeo4jWhenConfigured(): void {
		$this->assertInstanceOf(
			Neo4jSubjectLabelLookup::class,
			NeoWikiExtension::getInstance()->getSubjectLabelLookup()
		);
	}

	public function testLuaQueryOfferedWhenConfigured(): void {
		$names = NeoWikiExtension::getInstance()->getNeo4jPlugin()?->getLuaLibraryFunctionNames() ?? [];

		$this->assertContains( 'query', $names );
	}

	public function testLuaQueryNotOfferedWithoutBackend(): void {
		$names = $this->runWithoutGraphBackend(
			static fn() => NeoWikiExtension::getInstance()->getNeo4jPlugin()?->getLuaLibraryFunctionNames() ?? []
		);

		$this->assertSame( [], $names );
	}

	public function testReadOnlyClientThrowsGraphBackendNotConfiguredExceptionWithoutBackend(): void {
		$this->expectException( GraphBackendNotConfiguredException::class );

		$this->runWithoutGraphBackend(
			static fn() => NeoWikiExtension::getInstance()->getReadOnlyNeo4jClient()
		);
	}

	private function createPageWithMottoSubject( string $pageName ): void {
		$this->assertNotNull( $this->createPageWithSubjects( $pageName, $this->mottoSubject( self::VALUE ) ) );
	}

	private function mottoSubject( string $motto ): Subject {
		return TestSubject::build(
			id: self::SUBJECT_ID,
			statements: new StatementList( [
				new Statement( new PropertyName( self::PROPERTY ), 'text', new StringValue( $motto ) ),
			] )
		);
	}

	private function mottoOfIndexedSubject(): ?string {
		$subject = NeoWikiExtension::getInstance()->getSubjectRepository()
			->getSubject( new SubjectId( self::SUBJECT_ID ) );

		$value = $subject?->getStatements()->getStatement( new PropertyName( self::PROPERTY ) )?->getValue();

		return $value instanceof StringValue ? $value->toScalars()[0] : null;
	}

	private function newContentPageOutput( string $pageName ): OutputPage {
		$page = $this->getExistingTestPage( $pageName );

		$context = new RequestContext();
		$context->setTitle( $page->getTitle() );
		$context->setUser( $this->getTestSysop()->getUser() );

		$out = $context->getOutput();
		$out->setArticleFlag( true );
		$out->setRevisionId( $page->getLatest() );

		return $out;
	}

}
