<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Maintenance;

use Maintenance;
use MediaWiki\MainConfigNames;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;

$basePath = getenv( 'MW_INSTALL_PATH' ) !== false ? getenv( 'MW_INSTALL_PATH' ) : __DIR__ . '/../../..';

require_once $basePath . '/maintenance/Maintenance.php';

class GeneratePerformanceDump extends Maintenance {

	public const string SCHEMA_NAME = 'PerfTest';
	public const string PAGE_TITLE_PREFIX = 'Perf test ';

	private const string BASE58_ALPHABET = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';

	/**
	 * The id space is split into a seed part and an index part, so two runs with different seeds
	 * never mint the same Subject id while a re-run with the same seed reproduces it exactly.
	 */
	private const int SEED_ID_LENGTH = 4;
	private const int INDEX_ID_LENGTH = 10;
	private const int MAX_SEED = 58 ** self::SEED_ID_LENGTH - 1;

	/**
	 * Relation targets sit on other pages, at these page offsets. Negative offsets resolve to
	 * Subjects the import has already created; the positive one is a forward reference, which
	 * creates a stub node that a later page upgrades in place.
	 */
	private const array RELATION_PAGE_OFFSETS = [ -1, -13, 1 ];

	private const int DEFAULT_SUBJECTS_PER_PAGE = 10;
	private const int DEFAULT_SEED = 1;
	private const int PROGRESS_INTERVAL = 1000;

	private int $pages;
	private int $subjectsPerPage;
	private int $seed;

	/** @var resource */
	private $output;

	public function __construct() {
		parent::__construct();

		$this->requireExtension( 'NeoWiki' );
		$this->addDescription(
			'Generates a MediaWiki XML dump of synthetic Subject pages for performance testing, plus the '
			. 'Schema they use. Import it with importDump.php. The shape follows the "typical" column of '
			. 'ADR 29: one Schema, 12 Statements per Subject of which 3 are relations to Subjects on other '
			. 'pages. Output is deterministic: the same options always produce the same dump.'
		);
		$this->addOption( 'pages', 'Number of Subject pages to generate.', true, true );
		$this->addOption(
			'subjects-per-page',
			'Subjects per page: one main Subject plus children. Default: ' . self::DEFAULT_SUBJECTS_PER_PAGE . '.',
			false,
			true
		);
		$this->addOption(
			'seed',
			'Seeds the generated ids and values, so dumps with different seeds can be imported into one '
			. 'wiki without colliding. Default: ' . self::DEFAULT_SEED . '.',
			false,
			true
		);
		$this->addOption( 'output', 'File to write the dump to. Defaults to stdout.', false, true );
	}

	public function execute(): void {
		$this->pages = $this->parsePositiveInt( 'pages', (string)$this->getOption( 'pages' ) );
		$this->subjectsPerPage = $this->parsePositiveInt(
			'subjects-per-page',
			(string)$this->getOption( 'subjects-per-page', self::DEFAULT_SUBJECTS_PER_PAGE )
		);
		$this->seed = $this->parseSeed( (string)$this->getOption( 'seed', self::DEFAULT_SEED ) );
		$this->output = $this->openOutput();

		$this->writeHeader();
		$this->writeSchemaPage();
		$this->writeSubjectPages();
		$this->write( "</mediawiki>\n" );

		fclose( $this->output );

		$this->error(
			'Wrote ' . $this->pages . ' pages with ' . $this->pages * $this->subjectsPerPage
			. ' Subjects, plus Schema:' . self::SCHEMA_NAME . '.'
		);
	}

	private function parsePositiveInt( string $optionName, string $value ): int {
		if ( preg_match( '/^[1-9][0-9]*$/', $value ) !== 1 ) {
			$this->fatalError( "--$optionName must be a positive integer, got '$value'." );
		}

		return (int)$value;
	}

	private function parseSeed( string $value ): int {
		if ( preg_match( '/^(0|[1-9][0-9]*)$/', $value ) !== 1 || (int)$value > self::MAX_SEED ) {
			$this->fatalError( '--seed must be an integer between 0 and ' . self::MAX_SEED . ", got '$value'." );
		}

		return (int)$value;
	}

	/** @return resource */
	private function openOutput() {
		$path = $this->getOption( 'output' );

		if ( $path === null ) {
			return fopen( 'php://stdout', 'w' );
		}

		$handle = fopen( $path, 'w' );

		if ( $handle === false ) {
			$this->fatalError( "Cannot write to '$path'." );
		}

		return $handle;
	}

	private function writeHeader(): void {
		$this->write(
			'<mediawiki xmlns="http://www.mediawiki.org/xml/export-0.11/" '
			. 'xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" '
			. 'xsi:schemaLocation="http://www.mediawiki.org/xml/export-0.11/ '
			. 'http://www.mediawiki.org/xml/export-0.11.xsd" version="0.11" xml:lang="en">' . "\n"
		);

		// Read by the perf-import make target to report throughput without scanning the whole dump.
		$this->write(
			'  <!-- neowiki-perf pages="' . $this->pages . '" subjects="' . $this->pages * $this->subjectsPerPage
			. '" subjects-per-page="' . $this->subjectsPerPage . '" seed="' . $this->seed . '" -->' . "\n"
		);

		$config = $this->getConfig();

		$this->write(
			"  <siteinfo>\n"
			. '    ' . $this->element( 'sitename', (string)$config->get( MainConfigNames::Sitename ) ) . "\n"
			. '    ' . $this->element( 'dbname', (string)$config->get( MainConfigNames::DBname ) ) . "\n"
			. '    ' . $this->element( 'base', Title::newMainPage()->getCanonicalURL() ) . "\n"
			. '    ' . $this->element( 'generator', 'NeoWiki GeneratePerformanceDump' ) . "\n"
			. '    ' . $this->element( 'case', 'first-letter' ) . "\n"
			. "    <namespaces>\n"
			. '      ' . $this->namespaceElement( NS_MAIN ) . "\n"
			. '      ' . $this->namespaceElement( NS_NEOWIKI_SCHEMA ) . "\n"
			. "    </namespaces>\n"
			. "  </siteinfo>\n"
		);
	}

	private function namespaceElement( int $namespace ): string {
		$name = $this->namespaceName( $namespace );

		if ( $name === '' ) {
			return '<namespace key="' . $namespace . '" case="first-letter" />';
		}

		return '<namespace key="' . $namespace . '" case="first-letter">'
			. $this->escape( $name ) . '</namespace>';
	}

	private function namespaceName( int $namespace ): string {
		return MediaWikiServices::getInstance()->getContentLanguage()->getFormattedNsText( $namespace );
	}

	/**
	 * The Schema is part of the dump so an import is self-contained: without it every generated
	 * Subject would reference a Schema the wiki does not have.
	 */
	private function writeSchemaPage(): void {
		$propertyDefinitions = [
			'Description' => [ 'type' => 'text' ],
			'Code' => [ 'type' => 'text' ],
			'Founded' => [ 'type' => 'number' ],
			'Rating' => [ 'type' => 'number', 'precision' => 2 ],
			'Website' => [ 'type' => 'url' ],
			'Tags' => [ 'type' => 'text', 'multiple' => true ],
			'Active' => [ 'type' => 'boolean' ],
			'Started' => [ 'type' => 'date' ],
			'Notes' => [ 'type' => 'text' ],
		];

		foreach ( $this->relationPropertyNames() as $index => $propertyName ) {
			$propertyDefinitions[$propertyName] = [
				'type' => 'relation',
				'relation' => 'Perf relation ' . ( $index + 1 ),
				'targetSchema' => self::SCHEMA_NAME,
			];
		}

		$this->writePage(
			title: $this->namespaceName( NS_NEOWIKI_SCHEMA ) . ':' . self::SCHEMA_NAME,
			namespace: NS_NEOWIKI_SCHEMA,
			model: 'NeoWikiSchema',
			text: $this->toJson( [
				'description' => 'Synthetic Schema used by the generated performance-test Subjects.',
				'propertyDefinitions' => $propertyDefinitions,
			] ),
			subjectSlot: null
		);
	}

	private function writeSubjectPages(): void {
		for ( $pageIndex = 0; $pageIndex < $this->pages; $pageIndex++ ) {
			$this->writePage(
				title: self::PAGE_TITLE_PREFIX . $this->formatPageNumber( $pageIndex ),
				namespace: NS_MAIN,
				model: 'wikitext',
				text: 'Synthetic performance-test page ' . $pageIndex . ' with ' . $this->subjectsPerPage
					. ' Subjects in the NeoWiki subject slot.',
				subjectSlot: $this->buildPageSubjects( $pageIndex )
			);

			if ( ( $pageIndex + 1 ) % self::PROGRESS_INTERVAL === 0 ) {
				$this->error( 'Generated ' . ( $pageIndex + 1 ) . ' of ' . $this->pages . ' pages...' );
			}
		}
	}

	private function formatPageNumber( int $pageIndex ): string {
		return str_pad( (string)$pageIndex, 7, '0', STR_PAD_LEFT );
	}

	private function buildPageSubjects( int $pageIndex ): string {
		$subjects = [];

		for ( $subjectIndex = 0; $subjectIndex < $this->subjectsPerPage; $subjectIndex++ ) {
			$subjects[$this->subjectId( $pageIndex, $subjectIndex )] = $this->buildSubject( $pageIndex, $subjectIndex );
		}

		return $this->toJson( [
			'mainSubject' => $this->subjectId( $pageIndex, 0 ),
			'subjects' => $subjects,
		] );
	}

	/** @return array<string, mixed> */
	private function buildSubject( int $pageIndex, int $subjectIndex ): array {
		$ordinal = $this->subjectOrdinal( $pageIndex, $subjectIndex );
		$variation = $ordinal + $this->seed;

		$statements = [
			'Description' => [
				'type' => 'text',
				'value' => [ 'Synthetic Subject ' . $subjectIndex . ' on performance-test page ' . $pageIndex . '.' ],
			],
			'Code' => [
				'type' => 'text',
				'value' => [ 'PT-' . $this->formatPageNumber( $pageIndex ) . '-' . $subjectIndex ],
			],
			'Founded' => [ 'type' => 'number', 'value' => 1800 + $variation % 200 ],
			'Rating' => [ 'type' => 'number', 'value' => round( $variation % 500 / 100, 2 ) ],
			'Website' => [
				'type' => 'url',
				'value' => [ 'https://example.org/perf/' . $pageIndex . '/' . $subjectIndex ],
			],
			'Tags' => [
				'type' => 'text',
				'value' => [ 'tag' . $variation % 17, 'tag' . $variation * 7 % 23, 'tag' . $variation * 13 % 31 ],
			],
			'Active' => [ 'type' => 'boolean', 'value' => $variation % 2 === 0 ],
			'Started' => [ 'type' => 'date', 'value' => [ $this->buildDate( $variation ) ] ],
			'Notes' => [ 'type' => 'text', 'value' => [ 'Notes for Subject ' . $ordinal . '.' ] ],
		];

		foreach ( $this->relationPropertyNames() as $index => $propertyName ) {
			$statements[$propertyName] = [
				'type' => 'relation',
				'value' => [
					[
						'id' => $this->relationId( $ordinal, $index ),
						'target' => $this->relationTarget( $pageIndex, $subjectIndex, $index ),
					],
				],
			];
		}

		return [
			'label' => 'Perf ' . $this->formatPageNumber( $pageIndex ) . ' ' . $subjectIndex,
			'schema' => self::SCHEMA_NAME,
			'statements' => $statements,
		];
	}

	private function buildDate( int $variation ): string {
		return sprintf( '20%02d-%02d-%02d', 10 + $variation % 15, 1 + $variation % 12, 1 + $variation % 28 );
	}

	/** @return list<string> */
	private function relationPropertyNames(): array {
		return array_map(
			static fn ( int $index ): string => 'Related ' . chr( ord( 'A' ) + $index ),
			array_keys( self::RELATION_PAGE_OFFSETS )
		);
	}

	private function relationTarget( int $pageIndex, int $subjectIndex, int $index ): string {
		$offset = self::RELATION_PAGE_OFFSETS[$index];

		// PHP's modulo keeps the sign of the dividend, so normalize a negative offset into range.
		$targetPage = ( ( $pageIndex + $offset ) % $this->pages + $this->pages ) % $this->pages;

		return $this->subjectId( $targetPage, ( $subjectIndex + $index + 1 ) % $this->subjectsPerPage );
	}

	private function subjectOrdinal( int $pageIndex, int $subjectIndex ): int {
		return $pageIndex * $this->subjectsPerPage + $subjectIndex;
	}

	private function subjectId( int $pageIndex, int $subjectIndex ): string {
		return $this->encodeId( 's', $this->subjectOrdinal( $pageIndex, $subjectIndex ) );
	}

	private function relationId( int $subjectOrdinal, int $index ): string {
		return $this->encodeId( 'r', $subjectOrdinal * count( self::RELATION_PAGE_OFFSETS ) + $index );
	}

	private function encodeId( string $prefix, int $number ): string {
		return $prefix
			. $this->encodeBase58( $this->seed, self::SEED_ID_LENGTH )
			. $this->encodeBase58( $number, self::INDEX_ID_LENGTH );
	}

	private function encodeBase58( int $number, int $length ): string {
		$encoded = '';

		for ( $i = 0; $i < $length; $i++ ) {
			$encoded = self::BASE58_ALPHABET[$number % 58] . $encoded;
			$number = intdiv( $number, 58 );
		}

		return $encoded;
	}

	private function writePage(
		string $title,
		int $namespace,
		string $model,
		string $text,
		?string $subjectSlot
	): void {
		$this->write(
			"  <page>\n"
			. '    ' . $this->element( 'title', $title ) . "\n"
			. '    ' . $this->element( 'ns', (string)$namespace ) . "\n"
			. "    <revision>\n"
			. '      ' . $this->element( 'timestamp', '2026-01-01T00:00:00Z' ) . "\n"
			. "      <contributor>\n"
			. '        ' . $this->element( 'username', 'NeoWiki' ) . "\n"
			. "      </contributor>\n"
			. '      ' . $this->element( 'comment', 'Performance test data' ) . "\n"
			. '      ' . $this->element( 'model', $model ) . "\n"
			. '      ' . $this->element( 'format', $model === 'wikitext' ? 'text/x-wiki' : 'application/json' ) . "\n"
			. '      ' . $this->preservedElement( 'text', $text ) . "\n"
			. ( $subjectSlot === null ? '' : $this->subjectSlotElement( $subjectSlot ) )
			. "    </revision>\n"
			. "  </page>\n"
		);
	}

	private function subjectSlotElement( string $subjectSlot ): string {
		return "      <content>\n"
			. '        ' . $this->element( 'role', 'neo' ) . "\n"
			. '        ' . $this->element( 'model', 'NeoWikiSubject' ) . "\n"
			. '        ' . $this->element( 'format', 'application/json' ) . "\n"
			. '        ' . $this->preservedElement( 'text', $subjectSlot ) . "\n"
			. "      </content>\n";
	}

	private function element( string $name, string $content ): string {
		return '<' . $name . '>' . $this->escape( $content ) . '</' . $name . '>';
	}

	private function preservedElement( string $name, string $content ): string {
		return '<' . $name . ' xml:space="preserve">' . $this->escape( $content ) . '</' . $name . '>';
	}

	/**
	 * Only element content is escaped, never an attribute value, so quotes are left alone. That
	 * keeps the JSON subject slots — which are mostly quotes — about three times smaller than
	 * escaping them would.
	 */
	private function escape( string $text ): string {
		return htmlspecialchars( $text, ENT_NOQUOTES | ENT_XML1, 'UTF-8' );
	}

	/** @param array<string, mixed> $data */
	private function toJson( array $data ): string {
		return json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	}

	private function write( string $text ): void {
		if ( fwrite( $this->output, $text ) === false ) {
			$this->fatalError( 'Writing the dump failed.' );
		}
	}

}

$maintClass = GeneratePerformanceDump::class;
require_once RUN_MAINTENANCE_IF_MAIN;
