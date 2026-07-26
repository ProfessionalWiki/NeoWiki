<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Maintenance;

use MediaWiki\Tests\Maintenance\MaintenanceBaseTestCase;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\SubjectContent;
use ProfessionalWiki\NeoWiki\Maintenance\GeneratePerformanceDump;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\SchemaContentValidator;
use SimpleXMLElement;

// The maintenance script is not PSR-4 autoloadable (it lives outside src/), so load it explicitly.
// Its RUN_MAINTENANCE_IF_MAIN guard is a no-op under PHPUnit, so this does not execute the script.
require_once __DIR__ . '/../../../maintenance/GeneratePerformanceDump.php';

/**
 * @covers \ProfessionalWiki\NeoWiki\Maintenance\GeneratePerformanceDump
 * @group Database
 */
class GeneratePerformanceDumpTest extends MaintenanceBaseTestCase {

	protected function getMaintenanceClass(): string {
		return GeneratePerformanceDump::class;
	}

	public function testEmitsTheSchemaFollowedByOnePagePerRequestedPage(): void {
		$dump = $this->generate( pages: 2 );

		$this->assertSame(
			[ 'Schema:PerfTest', 'Perf test 1-0000000', 'Perf test 1-0000001' ],
			$this->pageTitles( $dump )
		);
	}

	public function testTheGeneratedSchemaIsValidSchemaContent(): void {
		$schema = $this->schemaJson( $this->generate( pages: 1 ) );

		$validator = SchemaContentValidator::newInstance();

		$this->assertTrue( $validator->validate( $schema ), implode( "\n", $validator->getErrors() ) );
	}

	public function testEveryGeneratedPropertyDefinitionUsesARegisteredPropertyType(): void {
		$types = array_column( $this->schema( $this->generate( pages: 1 ) )['propertyDefinitions'], 'type' );
		$lookup = NeoWikiExtension::getInstance()->getPropertyTypeLookup();

		$this->assertSame(
			[],
			array_values( array_filter(
				array_unique( $types ),
				static fn ( string $type ): bool => $lookup->getType( $type ) === null
			) )
		);
	}

	public function testEachPageCarriesTheRequestedNumberOfSubjects(): void {
		$slot = $this->subjectSlot( $this->generate( pages: 2, subjectsPerPage: 3 ), 0 );

		$this->assertCount( 3, $slot['subjects'] );
	}

	public function testTheMainSubjectIsOneOfThePageSubjects(): void {
		$slot = $this->subjectSlot( $this->generate( pages: 2, subjectsPerPage: 3 ), 0 );

		$this->assertArrayHasKey( $slot['mainSubject'], $slot['subjects'] );
	}

	/**
	 * The Schema and the Statements are built separately, so they can drift apart into Subjects
	 * carrying properties their Schema does not define.
	 */
	public function testTheSchemaDefinesExactlyThePropertiesTheSubjectsUse(): void {
		$dump = $this->generate( pages: 2 );
		$slot = $this->subjectSlot( $dump, 0 );

		$this->assertSame(
			array_keys( $this->schema( $dump )['propertyDefinitions'] ),
			array_keys( $slot['subjects'][$slot['mainSubject']]['statements'] )
		);
	}

	/**
	 * Loading the slot through production code proves the generated ids and value shapes are the
	 * ones NeoWiki accepts: SubjectId and RelationId reject anything malformed.
	 */
	public function testTheSubjectSlotLoadsIntoTwelveStatementsPerSubject(): void {
		$dump = $this->generate( pages: 2 );

		$pageSubjects = ( new SubjectContent( $this->subjectSlotJson( $dump, 0 ) ) )->getPageSubjects();

		$this->assertCount( 12, $pageSubjects->getMainSubject()->getStatements()->asArray() );
	}

	public function testRelationsTargetSubjectsThatExistInTheDump(): void {
		$dump = $this->generate( pages: 20 );

		$this->assertSame(
			[],
			array_diff( $this->relationTargetsOfMainSubject( $dump, 0 ), $this->allSubjectIds( $dump ) )
		);
	}

	/**
	 * @dataProvider runSizeProvider
	 */
	public function testRelationsTargetSubjectsOnOtherPages( int $pages ): void {
		$dump = $this->generate( pages: $pages );

		$this->assertSame(
			[],
			array_intersect(
				$this->relationTargetsOfMainSubject( $dump, 0 ),
				array_keys( $this->subjectSlot( $dump, 0 )['subjects'] )
			)
		);
	}

	public function runSizeProvider(): iterable {
		yield 'run larger than every page offset' => [ 20 ];
		// A run of exactly the largest offset is where an unclamped offset wraps onto its own page.
		yield 'run the size of the largest page offset' => [ 13 ];
		yield 'smallest run that has another page' => [ 2 ];
	}

	public function testTheSameOptionsProduceAnIdenticalDump(): void {
		$this->assertSame( $this->generate( pages: 3 ), $this->generate( pages: 3 ) );
	}

	public function testAnotherSeedMintsDifferentSubjectIds(): void {
		$this->assertSame(
			[],
			array_intersect(
				array_keys( $this->subjectSlot( $this->generate( pages: 3, seed: 1 ), 0 )['subjects'] ),
				array_keys( $this->subjectSlot( $this->generate( pages: 3, seed: 2 ), 0 )['subjects'] )
			)
		);
	}

	public function testAnotherSeedUsesDifferentPageTitles(): void {
		// Colliding titles would make importing a second dump overwrite the first dump's pages
		// instead of adding to them.
		$this->assertSame(
			[],
			array_intersect(
				$this->subjectPageTitles( $this->generate( pages: 3, seed: 1 ) ),
				$this->subjectPageTitles( $this->generate( pages: 3, seed: 2 ) )
			)
		);
	}

	/**
	 * The bound keeps every id at the 14 characters SubjectId and RelationId accept: one seed past
	 * it would wrap around and mint a colliding id instead of a longer one.
	 */
	public function testTheHighestSeedStillMintsIdsOfTheAcceptedLength(): void {
		$slot = $this->subjectSlot( $this->generate( pages: 1, seed: 11316495 ), 0 );

		$this->assertTrue( SubjectId::isValid( $slot['mainSubject'] ), $slot['mainSubject'] );
	}

	public function testASeedPastTheHighestIsRejected(): void {
		$this->maintenance->setOption( 'pages', '1' );
		$this->maintenance->setOption( 'seed', '11316496' );

		$this->expectCallToFatalError();

		$this->maintenance->execute();
	}

	public function testAPageCountThatIsNotAPositiveIntegerIsRejected(): void {
		$this->maintenance->setOption( 'pages', '0' );

		$this->expectCallToFatalError();

		$this->maintenance->execute();
	}

	private function generate( int $pages, ?int $subjectsPerPage = null, ?int $seed = null ): string {
		// A fresh instance per call: Maintenance keeps the options it was given.
		$this->maintenance = $this->createMaintenance();
		$this->maintenance->setOption( 'pages', (string)$pages );

		if ( $subjectsPerPage !== null ) {
			$this->maintenance->setOption( 'subjects-per-page', (string)$subjectsPerPage );
		}

		if ( $seed !== null ) {
			$this->maintenance->setOption( 'seed', (string)$seed );
		}

		$path = $this->getNewTempFile();
		$this->maintenance->setOption( 'output', $path );
		$this->maintenance->execute();

		return (string)file_get_contents( $path );
	}

	private function schemaJson( string $dump ): string {
		return (string)$this->pages( $dump )[0]->revision->text;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function schema( string $dump ): array {
		return json_decode( $this->schemaJson( $dump ), true );
	}

	/**
	 * @return list<SimpleXMLElement>
	 */
	private function pages( string $dump ): array {
		return iterator_to_array( ( new SimpleXMLElement( $dump ) )->page, false );
	}

	/**
	 * @return list<string>
	 */
	private function pageTitles( string $dump ): array {
		return array_map(
			static fn ( SimpleXMLElement $page ): string => (string)$page->title,
			$this->pages( $dump )
		);
	}

	/**
	 * The Schema page is shared between seeds by design, so it is not part of this comparison.
	 *
	 * @return list<string>
	 */
	private function subjectPageTitles( string $dump ): array {
		return array_slice( $this->pageTitles( $dump ), 1 );
	}

	private function subjectSlotJson( string $dump, int $pageIndex ): string {
		// The Schema page comes first and carries no subject slot, so the subject pages follow it.
		return (string)$this->pages( $dump )[$pageIndex + 1]->revision->content->text;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function subjectSlot( string $dump, int $pageIndex ): array {
		return json_decode( $this->subjectSlotJson( $dump, $pageIndex ), true );
	}

	/**
	 * @return list<string>
	 */
	private function relationTargetsOfMainSubject( string $dump, int $pageIndex ): array {
		$slot = $this->subjectSlot( $dump, $pageIndex );
		$statements = $slot['subjects'][$slot['mainSubject']]['statements'];

		return [
			$statements['Related A']['value'][0]['target'],
			$statements['Related B']['value'][0]['target'],
			$statements['Related C']['value'][0]['target'],
		];
	}

	/**
	 * @return list<string>
	 */
	private function allSubjectIds( string $dump ): array {
		$ids = [];

		foreach ( array_slice( $this->pages( $dump ), 1 ) as $page ) {
			$slot = json_decode( (string)$page->revision->content->text, true );
			$ids = array_merge( $ids, array_keys( $slot['subjects'] ) );
		}

		return $ids;
	}

}
