<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints\REST;

use InvalidArgumentException;
use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use ProfessionalWiki\NeoWiki\Application\SchemaLookup;
use ProfessionalWiki\NeoWiki\Application\SelectStatementResolver;
use ProfessionalWiki\NeoWiki\Application\StatementListBuilder;
use ProfessionalWiki\NeoWiki\Application\SubjectRepository;
use ProfessionalWiki\NeoWiki\Application\Validation\SubjectValidator;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectLabel;
use ProfessionalWiki\NeoWiki\Domain\Validation\Violation;
use Wikimedia\ParamValidator\ParamValidator;

class ValidateSubjectUpdateApi extends SimpleHandler {

	public function __construct(
		private readonly SubjectRepository $subjectRepository,
		private readonly SchemaLookup $schemaLookup,
		private readonly SubjectValidator $subjectValidator,
		private readonly StatementListBuilder $statementListBuilder,
		private readonly SelectStatementResolver $selectStatementResolver,
	) {
	}

	public function run( string $subjectId ): Response {
		try {
			$id = new SubjectId( $subjectId );
		} catch ( InvalidArgumentException $e ) {
			return $this->getResponseFactory()->createHttpError( 400, [
				'status' => 'error',
				'message' => $e->getMessage(),
			] );
		}

		$subject = $this->subjectRepository->getSubject( $id );

		if ( $subject === null ) {
			return $this->getResponseFactory()->createHttpError( 404, [
				'status' => 'error',
				'message' => 'Subject not found: ' . $subjectId,
			] );
		}

		$body = $this->getValidatedBody();

		$schema = $this->schemaLookup->getSchema( $subject->getSchemaName() );

		// Unlike POST /subject/validate (which 404s when the body's schema name is
		// unknown), here the Subject already exists but its Schema page has gone
		// missing — surface this as a violation rather than 404, since the Subject
		// itself is real and the user can recover by re-creating the Schema page.
		if ( $schema === null ) {
			return $this->getResponseFactory()->createJson( [
				'violations' => [
					self::serializeViolation(
						new Violation(
							propertyName: null,
							code: 'schema-not-found',
							args: [ $subject->getSchemaName()->getText() ],
						)
					),
				],
			] );
		}

		$label = is_string( $body['label'] ) ? $body['label'] : '';
		$subject->setLabel( new SubjectLabel( $label ) );
		$subject->setStatements(
			$this->statementListBuilder->build(
				$this->selectStatementResolver->resolveOrLeave( $schema, $body['statements'] )
			)
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

	public function getParamSettings(): array {
		return [
			'subjectId' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
				self::PARAM_DESCRIPTION => 'Persistent identifier of the Subject. 15 characters, starting with "s".',
			],
		];
	}

	public function getBodyParamSettings(): array {
		return [
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
				self::PARAM_DESCRIPTION => 'Proposed Statements (property/value pairs) for the proposed update. Shape matches docs/SubjectFormat.md.',
			],
			'comment' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => false,
				self::PARAM_DESCRIPTION => 'Accepted for parity with PUT /subject/{id} but ignored — this endpoint never persists.',
			],
		];
	}

	public function needsWriteAccess(): bool {
		return false;
	}

}
