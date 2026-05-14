<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\REST;

use InvalidArgumentException;
use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use ProfessionalWiki\NeoWiki\Application\SchemaLookup;
use ProfessionalWiki\NeoWiki\Application\SelectStatementResolver;
use ProfessionalWiki\NeoWiki\Application\StatementListBuilder;
use ProfessionalWiki\NeoWiki\Application\Validation\SubjectValidator;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectLabel;
use ProfessionalWiki\NeoWiki\Domain\Validation\Violation;
use Wikimedia\ParamValidator\ParamValidator;

class ValidateSubjectApi extends SimpleHandler {

	private const string PLACEHOLDER_SUBJECT_ID = 's111111111aaaat';

	public function __construct(
		private readonly SchemaLookup $schemaLookup,
		private readonly SubjectValidator $subjectValidator,
		private readonly StatementListBuilder $statementListBuilder,
		private readonly SelectStatementResolver $selectStatementResolver,
	) {
	}

	public function run(): Response {
		$body = $this->getValidatedBody();

		try {
			$schemaName = new SchemaName( $body['schema'] );
		} catch ( InvalidArgumentException $e ) {
			return $this->getResponseFactory()->createHttpError( 400, [
				'status' => 'error',
				'message' => $e->getMessage(),
			] );
		}

		$schema = $this->schemaLookup->getSchema( $schemaName );

		if ( $schema === null ) {
			return $this->getResponseFactory()->createHttpError( 404, [
				'status' => 'error',
				'message' => 'Schema not found: ' . $schemaName->getText(),
			] );
		}

		$label = is_string( $body['label'] ) ? $body['label'] : '';

		$subject = new Subject(
			id: new SubjectId( self::PLACEHOLDER_SUBJECT_ID ),
			label: SubjectLabel::createForValidation( $label ),
			schemaName: $schemaName,
			statements: $this->statementListBuilder->build(
				$this->selectStatementResolver->resolve( $schema, $body['statements'] )
			),
		);

		$violations = $this->subjectValidator->validate( $subject, $schema );

		return $this->getResponseFactory()->createJson( [
			'violations' => array_map(
				static fn( Violation $v ): array => self::serializeViolation( $v ),
				$violations
			),
		] );
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function serializeViolation( Violation $violation ): array {
		$serialized = [
			'propertyName' => $violation->propertyName?->__toString(),
			'code' => $violation->code,
			'args' => $violation->args,
		];

		if ( $violation->valuePartIndex !== null ) {
			$serialized['valuePartIndex'] = $violation->valuePartIndex;
		}

		return $serialized;
	}

	public function getBodyParamSettings(): array {
		return [
			'schema' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
				self::PARAM_DESCRIPTION => 'Name of the Schema to validate the proposed Subject against.',
			],
			'label' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
				self::PARAM_DESCRIPTION => 'Proposed display label for the Subject. May be empty; an empty label produces a label-required violation rather than a 400.',
			],
			'statements' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'array',
				ParamValidator::PARAM_REQUIRED => true,
				self::PARAM_DESCRIPTION => 'Proposed Statements (property/value pairs). Shape matches docs/SubjectFormat.md.',
			],
		];
	}

	public function needsWriteAccess(): bool {
		return false;
	}

}
