<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Presentation;

use InvalidArgumentException;
use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\Queries\ValidateSubject\ValidateSubjectQuery;
use ProfessionalWiki\NeoWiki\Application\SelectStatementResolver;
use ProfessionalWiki\NeoWiki\Application\SelectValueResolver;
use ProfessionalWiki\NeoWiki\Application\StatementListBuilder;
use ProfessionalWiki\NeoWiki\Application\Validation\SubjectValidator;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\PropertyTypeLookup;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\PropertyTypeRegistry;
use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Validation\Violation;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\SchemaPersistenceDeserializer;
use ProfessionalWiki\NeoWiki\Presentation\JsonSchemaSerializer;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSources;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubjectIds;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemorySchemaLookup;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemorySubjectLookup;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\StubIdGenerator;

/**
 * The JSON Schema document promises that a Subject validating against it draws no Violations. This
 * runs both over the same values and requires them to agree.
 *
 * Every row where they may not agree carries the reason and is asserted to disagree: closing one of
 * those gaps fails this test until its row is removed. That makes the rows carrying a reason the
 * list of what the document cannot express, except for one class left out of scope below.
 *
 * The Violation side runs the validate endpoint's own query. Input the wiki refuses outright counts
 * as rejected: those cases reach a client as a 400 rather than as a Violation, which is still the
 * wiki turning the value away.
 * Codes that depend on the wiki's content rather than on the value are dropped, being out of scope
 * for a standalone document.
 *
 * Out of scope, and therefore neither asserted nor listed: values padded with whitespace, or
 * carrying empty parts. The wiki trims and drops those before it measures anything and the document
 * does not, so they diverge across most of the table rather than in any one place.
 *
 * Agreement here is agreement on accept-or-reject, not on the reason for rejecting.
 *
 * @covers \ProfessionalWiki\NeoWiki\Presentation\JsonSchemaSerializer
 */
class JsonSchemaMatchesValidatorTest extends TestCase {

	private const string SCHEMA_NAME = 'Faithfulness';
	private const string DOCUMENT_URL = 'https://example.com/json-schema';
	private const string PROPERTY = 'Field';
	private const string TARGET_ID = 'srt111111111aaa';
	private const string OTHER_TARGET_ID = 'srt111111111bbb';

	/**
	 * Answered by the wiki's content, never by a value on its own.
	 */
	private const array WIKI_DEPENDENT_CODES = [
		'relation-target-not-found',
		'relation-target-schema-mismatch',
		Violation::UNRESOLVABLE_RELATION_TARGET_SOURCE,
		'unregistered-type',
	];

	/**
	 * @dataProvider valueProvider
	 *
	 * @param array<string, mixed> $definition A Property Definition, as written in a Schema page.
	 * @param array<string, mixed> $statements Proposed Statements, as sent to the Subject endpoints.
	 * @param ?string $knownDifference Why the two cannot agree on this value, or null when they must.
	 */
	public function testJsonSchemaAgreesWithTheValidator(
		array $definition,
		array $statements,
		?string $knownDifference = null
	): void {
		$schema = $this->schemaWith( $definition );

		$documentRejection = $this->documentRejection( $schema, $statements );
		$violations = $this->violationsFor( $schema, $statements );

		$documentAccepts = $documentRejection === null;
		$validatorAccepts = $violations === [];

		$verdicts = 'Document: ' . ( $documentRejection ?? 'accepts' )
			. '. Validator: ' . ( implode( ', ', array_column( $violations, 'code' ) ) ?: 'no Violations' );

		if ( $knownDifference !== null ) {
			$this->assertNotSame(
				$validatorAccepts,
				$documentAccepts,
				"This row is listed as a difference the document cannot express ($knownDifference), but the two "
					. "agree. Remove the row from the difference list. $verdicts"
			);
			return;
		}

		$this->assertSame( $validatorAccepts, $documentAccepts, "The two must agree. $verdicts" );
	}

	public static function valueProvider(): iterable {
		yield from self::textRows();
		yield from self::urlRows();
		yield from self::numberRows();
		yield from self::booleanRows();
		yield from self::selectRows();
		yield from self::dateRows();
		yield from self::dateTimeRows();
		yield from self::relationRows();
		yield from self::unregisteredTypeRows();
	}

	private static function textRows(): iterable {
		$text = [ 'type' => 'text' ];

		yield 'text value' => [ $text, self::value( 'text', [ 'hello' ] ) ];
		yield 'text absent' => [ $text, [] ];

		$required = $text + [ 'required' => true ];
		yield 'text required and given' => [ $required, self::value( 'text', [ 'hello' ] ) ];
		yield 'text required and empty' => [ $required, self::value( 'text', [] ) ];
		yield 'text required and absent' => [ $required, [] ];

		$bounded = $text + [ 'minLength' => 5, 'maxLength' => 8 ];
		yield 'text within its length bounds' => [ $bounded, self::value( 'text', [ 'middle' ] ) ];
		yield 'text below minLength' => [ $bounded, self::value( 'text', [ 'abcd' ] ) ];
		yield 'text above maxLength' => [ $bounded, self::value( 'text', [ 'abcdefghi' ] ) ];
		yield 'text at minLength' => [ $bounded, self::value( 'text', [ 'abcde' ] ) ];
		yield 'text at maxLength' => [ $bounded, self::value( 'text', [ 'abcdefgh' ] ) ];

		$unique = $text + [ 'multiple' => true, 'uniqueItems' => true ];
		yield 'text parts all distinct' => [ $unique, self::value( 'text', [ 'a', 'b' ] ) ];
		yield 'text parts duplicated' => [ $unique, self::value( 'text', [ 'a', 'a' ] ) ];

		yield 'text parts duplicated where duplicates are allowed' => [
			$text + [ 'multiple' => true ],
			self::value( 'text', [ 'a', 'a' ] ),
		];

		yield 'several text parts on a single-valued property' => [
			$text,
			self::value( 'text', [ 'a', 'b' ] ),
			'text does not check multiple, so the wiki stores several parts where the Schema allows '
				. 'one and the document rejects them.',
		];

		yield 'a text value that is a bare string' => [
			$text,
			[ self::PROPERTY => [ 'propertyType' => 'text', 'value' => 'hello' ] ],
			'the wiki reads a bare string as a one-part value; the document describes the list the '
				. 'Subject endpoints return.',
		];

		yield 'a Statement the Schema does not declare' => [
			$text,
			[ 'Undeclared' => [ 'propertyType' => 'text', 'value' => [ 'hello' ] ] ],
			'the wiki keeps Statements of properties the Schema no longer declares; the document '
				. 'describes the Schema.',
		];

		yield 'a Statement declaring another type than the Schema' => [
			$text,
			self::value( 'url', [ 'https://example.com' ] ),
		];
	}

	private static function urlRows(): iterable {
		$url = [ 'type' => 'url', 'multiple' => true ];

		yield 'https url' => [ $url, self::value( 'url', [ 'https://example.com' ] ) ];
		yield 'http url with path, query and fragment' => [
			$url,
			self::value( 'url', [ 'http://example.com/a/b?q=1&r=2#frag' ] ),
		];
		yield 'url without a scheme' => [ $url, self::value( 'url', [ 'example.com/page' ] ) ];
		yield 'uppercase scheme and host' => [ $url, self::value( 'url', [ 'HTTPS://EXAMPLE.COM' ] ) ];
		yield 'localhost with a port' => [ $url, self::value( 'url', [ 'http://localhost:8080/wiki' ] ) ];
		yield 'ipv4 host' => [ $url, self::value( 'url', [ 'http://192.168.0.1:80/a' ] ) ];
		yield 'ftp scheme' => [ $url, self::value( 'url', [ 'ftp://example.com' ] ) ];
		yield 'file scheme' => [ $url, self::value( 'url', [ 'file:///etc/passwd' ] ) ];
		yield 'url with a space' => [ $url, self::value( 'url', [ 'http://example.com/a b' ] ) ];
		yield 'bare word' => [ $url, self::value( 'url', [ 'justtext' ] ) ];
		yield 'host without a top-level domain' => [ $url, self::value( 'url', [ 'http://example' ] ) ];
		yield 'a colon with no port' => [ $url, self::value( 'url', [ 'http://example.com:/a' ] ) ];
		yield 'a host label ending in a hyphen' => [ $url, self::value( 'url', [ 'http://exa-.com' ] ) ];
		yield 'one bad url among good ones' => [
			$url,
			self::value( 'url', [ 'https://example.com', 'ftp://example.com' ] ),
		];

		yield 'urls duplicated where uniqueItems is set' => [
			$url + [ 'uniqueItems' => true ],
			self::value( 'url', [ 'https://example.com', 'https://example.com' ] ),
		];

		yield 'url required and empty' => [
			[ 'type' => 'url', 'required' => true ],
			self::value( 'url', [] ),
		];

		yield 'several urls on a single-valued property' => [
			[ 'type' => 'url' ],
			self::value( 'url', [ 'https://example.com', 'https://example.org' ] ),
			'url does not check multiple either.',
		];
	}

	private static function numberRows(): iterable {
		$number = [ 'type' => 'number' ];

		yield 'number value' => [ $number, self::value( 'number', 42 ) ];
		yield 'number absent' => [ $number, [] ];
		yield 'number required and absent' => [ $number + [ 'required' => true ], [] ];

		$bounded = $number + [ 'minimum' => 0, 'maximum' => 100 ];
		yield 'number within bounds' => [ $bounded, self::value( 'number', 50 ) ];
		yield 'number below minimum' => [ $bounded, self::value( 'number', -1 ) ];
		yield 'number above maximum' => [ $bounded, self::value( 'number', 101 ) ];
		yield 'number at minimum' => [ $bounded, self::value( 'number', 0 ) ];
		yield 'number at maximum' => [ $bounded, self::value( 'number', 100 ) ];

		yield 'number with more decimals than the display precision' => [
			$number + [ 'precision' => 2 ],
			self::value( 'number', 1.2345 ),
		];

		yield 'a number value that is a string' => [ $number, self::value( 'number', 'forty-two' ) ];
	}

	private static function booleanRows(): iterable {
		yield 'boolean value' => [ [ 'type' => 'boolean' ], self::value( 'boolean', true ) ];
		yield 'boolean required and absent' => [ [ 'type' => 'boolean', 'required' => true ], [] ];
		yield 'a boolean value that is a string' => [ [ 'type' => 'boolean' ], self::value( 'boolean', 'yes' ) ];
	}

	private static function selectRows(): iterable {
		$options = [
			[ 'id' => 'oVgd1htUPMmGZYT', 'label' => 'Draft' ],
			[ 'id' => 'oVgd1CpH9LzBa3z', 'label' => 'Review' ],
		];
		$select = [ 'type' => 'select', 'options' => $options ];

		yield 'select option id' => [ $select, self::value( 'select', [ 'oVgd1htUPMmGZYT' ] ) ];
		yield 'select value that is no option' => [ $select, self::value( 'select', [ 'nope' ] ) ];
		yield 'select required and empty' => [
			$select + [ 'required' => true ],
			self::value( 'select', [] ),
		];

		yield 'two options on a single-valued select' => [
			$select,
			self::value( 'select', [ 'oVgd1htUPMmGZYT', 'oVgd1CpH9LzBa3z' ] ),
		];
		yield 'two options on a multi-valued select' => [
			$select + [ 'multiple' => true ],
			self::value( 'select', [ 'oVgd1htUPMmGZYT', 'oVgd1CpH9LzBa3z' ] ),
		];

		yield 'select without options' => [
			[ 'type' => 'select', 'options' => [] ],
			self::value( 'select', [ 'anything' ] ),
		];

		yield 'select option label' => [
			$select,
			self::value( 'select', [ 'Draft' ] ),
			'the write endpoints also take an option label, which they resolve to its id; the document '
				. 'describes the stored form only.',
		];

		yield 'select option as an id and label object' => [
			$select,
			self::value( 'select', [ [ 'id' => 'oVgd1htUPMmGZYT', 'label' => 'Draft' ] ] ),
			'the write endpoints also take an option as an {id, label} object, for the same reason.',
		];
	}

	private static function dateRows(): iterable {
		$date = [ 'type' => 'date' ];

		yield 'date value' => [ $date, self::value( 'date', [ '2025-06-15' ] ) ];
		yield 'date with an impossible month' => [ $date, self::value( 'date', [ '2025-13-01' ] ) ];
		yield 'date in another notation' => [ $date, self::value( 'date', [ '15/06/2025' ] ) ];
		yield 'date carrying a time' => [ $date, self::value( 'date', [ '2025-06-15T00:00:00Z' ] ) ];
		// Agreement here rests on the validator asserting `format`, which the dialect leaves optional.
		yield 'date overflowing its month' => [ $date, self::value( 'date', [ '2025-02-30' ] ) ];
		yield 'date required and empty' => [ $date + [ 'required' => true ], self::value( 'date', [] ) ];

		yield 'two date parts' => [
			$date,
			self::value( 'date', [ '2025-06-15', '2026-01-01' ] ),
			'date and dateTime read only the first part, so the wiki stores the rest unchecked and '
				. 'the document rejects them.',
		];

		$bounded = $date + [ 'minimum' => '2020-01-01', 'maximum' => '2030-12-31' ];
		yield 'date within bounds' => [ $bounded, self::value( 'date', [ '2025-06-15' ] ) ];
		yield 'date before its minimum' => [
			$bounded,
			self::value( 'date', [ '2019-12-31' ] ),
			'standard JSON Schema cannot order dates.',
		];
		yield 'date after its maximum' => [
			$bounded,
			self::value( 'date', [ '2031-01-01' ] ),
			'standard JSON Schema cannot order dates.',
		];
	}

	private static function dateTimeRows(): iterable {
		$dateTime = [ 'type' => 'dateTime' ];

		yield 'dateTime value' => [ $dateTime, self::value( 'dateTime', [ '2025-06-15T14:30:00Z' ] ) ];
		yield 'dateTime with an offset' => [ $dateTime, self::value( 'dateTime', [ '2025-06-15T14:30:00+02:00' ] ) ];
		yield 'dateTime without a timezone' => [ $dateTime, self::value( 'dateTime', [ '2025-06-15T14:30:00' ] ) ];
		yield 'dateTime that is only a date' => [ $dateTime, self::value( 'dateTime', [ '2025-06-15' ] ) ];
		yield 'dateTime with an impossible hour' => [
			$dateTime,
			self::value( 'dateTime', [ '2025-06-15T25:00:00Z' ] ),
		];
		yield 'dateTime required and empty' => [
			$dateTime + [ 'required' => true ],
			self::value( 'dateTime', [] ),
		];

		yield 'dateTime before its minimum' => [
			$dateTime + [ 'minimum' => '2020-01-01T00:00:00Z' ],
			self::value( 'dateTime', [ '2019-12-31T23:59:59Z' ] ),
			'standard JSON Schema cannot order dates.',
		];
		yield 'dateTime after its maximum' => [
			$dateTime + [ 'maximum' => '2030-12-31T23:59:59Z' ],
			self::value( 'dateTime', [ '2031-01-01T00:00:00Z' ] ),
			'standard JSON Schema cannot order dates.',
		];
	}

	private static function relationRows(): iterable {
		$relation = [ 'type' => 'relation', 'relation' => 'Has target', 'targetSchema' => 'Target' ];

		yield 'one relation' => [ $relation, self::value( 'relation', [ self::relationTo( self::TARGET_ID ) ] ) ];
		yield 'relation required and empty' => [
			$relation + [ 'required' => true ],
			self::value( 'relation', [] ),
		];
		yield 'two relations on a single-valued property' => [
			$relation,
			self::value( 'relation', [ self::relationTo( self::TARGET_ID ), self::relationTo( self::OTHER_TARGET_ID ) ] ),
		];
		yield 'two relations on a multi-valued property' => [
			$relation + [ 'multiple' => true ],
			self::value( 'relation', [ self::relationTo( self::TARGET_ID ), self::relationTo( self::OTHER_TARGET_ID ) ] ),
		];
		yield 'a relation value that is a bare target id' => [
			$relation,
			self::value( 'relation', [ self::TARGET_ID ] ),
		];
		yield 'a relation missing its target' => [
			$relation,
			self::value( 'relation', [ [ 'id' => 'rrt111111111aaa' ] ] ),
		];

		yield 'a relation whose target is not a Subject id' => [
			$relation,
			self::value( 'relation', [ [ 'id' => 'rrt111111111aaa', 'target' => 'not an id' ] ] ),
			'the document describes a target as any string: the Subject id grammar is three patterns '
				. 'combined in code, and restating it here would drift from it.',
		];

		yield 'a relation whose id is not a Relation id' => [
			$relation,
			self::value( 'relation', [ [ 'id' => 'not an id', 'target' => self::TARGET_ID ] ] ),
			'the document describes a relation id as any string, for the same reason.',
		];

		yield 'a relation property holding a scalar' => [
			$relation,
			self::value( 'relation', [ self::relationTo( self::TARGET_ID ) + [ 'properties' => [ 'note' => 'a' ] ] ] ),
		];

		yield 'a relation property holding a list' => [
			$relation,
			self::value( 'relation', [ self::relationTo( self::TARGET_ID ) + [ 'properties' => [ 'note' => [ 'a' ] ] ] ] ),
		];
	}

	private static function unregisteredTypeRows(): iterable {
		$gone = [ 'type' => 'noExtensionRegistersThis' ];

		yield 'a property whose Property Type is not registered' => [ $gone, [] ];
		yield 'a required property whose Property Type is not registered' => [
			$gone + [ 'required' => true ],
			[],
			'the wiki reports the missing type instead of required, so the document is stricter here.',
		];
		yield 'a value for a property whose Property Type is not registered' => [
			$gone,
			self::value( 'noExtensionRegistersThis', [ 'anything' ] ),
		];
	}

	/**
	 * @return array<string, array<string, string>>
	 */
	private static function relationTo( string $targetId ): array {
		return [ 'id' => 'r' . substr( $targetId, 1 ), 'target' => $targetId ];
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	private static function value( string $propertyType, mixed $value ): array {
		return [ self::PROPERTY => [ 'propertyType' => $propertyType, 'value' => $value ] ];
	}

	/**
	 * @param array<string, mixed> $definition
	 */
	private function schemaWith( array $definition ): Schema {
		return ( new SchemaPersistenceDeserializer( $this->propertyTypeLookup() ) )->deserialize(
			new SchemaName( self::SCHEMA_NAME ),
			(string)json_encode( [ 'propertyDefinitions' => [ self::PROPERTY => $definition ] ] )
		);
	}

	/**
	 * Why the generated document rejects the proposed Subject, or null when it accepts it.
	 *
	 * @param array<string, mixed> $statements
	 */
	private function documentRejection( Schema $schema, array $statements ): ?string {
		$document = ( new JsonSchemaSerializer( documentUrl: self::DOCUMENT_URL ) )->serialize( $schema );

		// Cast so that a Subject with no Statements encodes as an object rather than as [].
		$subject = [ 'schema' => self::SCHEMA_NAME, 'statements' => (object)$statements ];

		$error = ( new Validator() )->validate(
			json_decode( (string)json_encode( $subject ) ),
			json_decode( $document )
		)->error();

		return $error === null ? null : (string)json_encode( ( new ErrorFormatter() )->format( $error, false ) );
	}

	/**
	 * The validate endpoint's own pipeline, so that the wiki's verdict here is the one a client gets.
	 *
	 * @param array<string, mixed> $statements
	 *
	 * @return Violation[]
	 */
	private function violationsFor( Schema $schema, array $statements ): array {
		try {
			$violations = $this->newValidateQuery( $schema )->validate( self::SCHEMA_NAME, $statements );
		} catch ( InvalidArgumentException ) {
			// A value whose shape does not fit its declared type never reaches validation: the
			// endpoints answer 400. Treated as a rejection, which is what the document must do too.
			return [ new Violation( propertyName: null, code: 'refused-by-the-wiki' ) ];
		}

		return array_values( array_filter(
			$violations,
			static fn ( Violation $violation ): bool
				=> !in_array( $violation->code, self::WIKI_DEPENDENT_CODES, true )
		) );
	}

	private function newValidateQuery( Schema $schema ): ValidateSubjectQuery {
		return new ValidateSubjectQuery(
			schemaResolver: TestSources::newSchemaResolver( new InMemorySchemaLookup( $schema ) ),
			subjectValidator: new SubjectValidator(
				propertyTypeLookup: $this->propertyTypeLookup(),
				subjectLookup: new InMemorySubjectLookup(),
				sourceRegistry: TestSources::newRegistry(),
			),
			statementListBuilder: new StatementListBuilder(
				propertyTypeLookup: $this->propertyTypeLookup(),
				idGenerator: new StubIdGenerator( 'Generated11111' ),
				subjectIdParser: TestSubjectIds::newParser(),
			),
			selectStatementResolver: new SelectStatementResolver( new SelectValueResolver() ),
			localSourceKey: TestSubjectIds::LOCAL_SOURCE_KEY,
		);
	}

	private function propertyTypeLookup(): PropertyTypeLookup {
		return PropertyTypeRegistry::withCoreTypes( TestSubjectIds::LOCAL_SOURCE_KEY );
	}

}
