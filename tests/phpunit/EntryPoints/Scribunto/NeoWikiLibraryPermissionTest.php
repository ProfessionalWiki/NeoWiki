<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints\Scribunto;

if ( !class_exists( \MediaWiki\Extension\Scribunto\Tests\Engines\LuaCommon\LuaEngineTestBase::class ) ) {
	return;
}

use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyName;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Statement;
use ProfessionalWiki\NeoWiki\Domain\Subject\StatementList;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectLabel;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\Domain\Value\StringValue;

/**
 * The library reads as the user the page is parsed for, which the engine under test makes the
 * anonymous user, while the request belongs to a sysop who may read and query everything.
 *
 * @group Lua
 * @group Database
 */
class NeoWikiLibraryPermissionTest extends NeoWikiLibraryTestBase {

	private const string RESTRICTED_PAGE = 'NeoWikiLuaRestrictedPage';
	private const string RESTRICTED_SCHEMA = 'RestrictedSchema';

	// phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingAnyTypeHint -- parent class has no type hint
	protected static $moduleName = 'NeoWikiLibraryPermissionTests';

	protected function setUp(): void {
		parent::setUp();

		$this->grantTheQueryRightToSysopsOnly();

		$this->createPageWithMainSubject(
			self::RESTRICTED_PAGE,
			mainSubject: new Subject(
				id: new SubjectId( 's1test5eeeeeeee' ),
				label: new SubjectLabel( 'Restricted Company' ),
				schemaName: new SchemaName( 'Company' ),
				statements: new StatementList( [
					new Statement( new PropertyName( 'City' ), 'text', new StringValue( 'Secret City' ) ),
				] ),
			),
			childSubjects: new SubjectMap(
				new Subject(
					id: new SubjectId( 's1test5ffffffff' ),
					label: new SubjectLabel( 'Restricted Entry' ),
					schemaName: new SchemaName( 'Entry' ),
					statements: new StatementList(),
				),
			),
		);
		$this->createSchemaPage( self::RESTRICTED_SCHEMA, json_encode( [
			'description' => 'Readable by logged-in users only',
			'propertyDefinitions' => [ 'Name' => [ 'type' => 'text' ] ],
		] ) );
		$this->denyAnonymousReadOf( self::RESTRICTED_PAGE, 'Schema:' . self::RESTRICTED_SCHEMA );
	}

	protected function getTestModules(): array {
		return parent::getTestModules() + [
			'NeoWikiLibraryPermissionTests' => __DIR__ . '/NeoWikiLibraryPermissionTests.lua',
		];
	}

}
