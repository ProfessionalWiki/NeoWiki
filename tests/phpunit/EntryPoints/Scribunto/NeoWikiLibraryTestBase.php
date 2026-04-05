<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints\Scribunto;

if ( !class_exists( \MediaWiki\Extension\Scribunto\Tests\Engines\LuaCommon\LuaEngineTestBase::class ) ) {
	return;
}

use MediaWiki\CommentStore\CommentStoreComment;
use MediaWiki\Content\TextContent;
use MediaWiki\Extension\Scribunto\Tests\Engines\LuaCommon\LuaEngineTestBase;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyName;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Statement;
use ProfessionalWiki\NeoWiki\Domain\Subject\StatementList;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectLabel;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\Domain\Value\NumberValue;
use ProfessionalWiki\NeoWiki\Domain\Value\StringValue;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\SubjectContent;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\MediaWikiSubjectRepository;

/**
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\Scribunto\ScribuntoLuaLibrary
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\Scribunto\SubjectDataLookup
 * @covers \ProfessionalWiki\NeoWiki\Application\SubjectResolver
 * @group Lua
 * @group Database
 */
abstract class NeoWikiLibraryTestBase extends LuaEngineTestBase {

	// phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingAnyTypeHint -- parent class has no type hint
	protected static $moduleName = 'NeoWikiLibraryTests';

	protected function setUp(): void {
		parent::setUp();

		// Suppress NeoWiki's revision handler during test data creation
		// to avoid graph DB and property type registration dependencies
		$this->setTemporaryHook( 'RevisionFromEditComplete', static function (): void {
		} );
		$this->createTestData();
	}

	protected function getTestModules(): array {
		return parent::getTestModules() + [
			'NeoWikiLibraryTests' => __DIR__ . '/NeoWikiLibraryTests.lua',
		];
	}

	private function createTestData(): void {
		$this->createPageWithSubjects(
			'NeoWikiLuaTestPage',
			mainSubject: new Subject(
				id: new SubjectId( 's1test5aaaaaaaa' ),
				label: new SubjectLabel( 'Test Company' ),
				schemaName: new SchemaName( 'Company' ),
				statements: new StatementList( [
					new Statement( new PropertyName( 'City' ), 'text', new StringValue( 'Berlin' ) ),
					new Statement( new PropertyName( 'Tags' ), 'text', new StringValue( 'alpha', 'beta', 'gamma' ) ),
					new Statement( new PropertyName( 'Founded' ), 'number', new NumberValue( 2019 ) ),
				] ),
			),
		);

		$this->createPageWithSubjects(
			'NeoWikiLuaTestPageChildren',
			mainSubject: new Subject(
				id: new SubjectId( 's1test5cccccccc' ),
				label: new SubjectLabel( 'Parent' ),
				schemaName: new SchemaName( 'Company' ),
				statements: new StatementList(),
			),
			childSubjects: new SubjectMap(
				new Subject(
					id: new SubjectId( 's1test5dddddddd' ),
					label: new SubjectLabel( 'Child Entry' ),
					schemaName: new SchemaName( 'Entry' ),
					statements: new StatementList( [
						new Statement( new PropertyName( 'Note' ), 'text', new StringValue( 'A child subject' ) ),
					] ),
				),
			),
		);
	}

	private function createPageWithSubjects(
		string $pageName,
		Subject $mainSubject,
		SubjectMap $childSubjects = new SubjectMap(),
	): void {
		$wikiPage = MediaWikiServices::getInstance()->getWikiPageFactory()->newFromTitle(
			Title::newFromText( $pageName )
		);

		$updater = $wikiPage->newPageUpdater( $this->getTestSysop()->getUser() );
		$updater->setContent( 'main', new TextContent( '' ) );
		$updater->setContent(
			MediaWikiSubjectRepository::SLOT_NAME,
			SubjectContent::newFromData( new PageSubjects( $mainSubject, $childSubjects ) ),
		);

		$updater->saveRevision( CommentStoreComment::newUnsavedComment( 'Lua test data' ) );
	}

}
