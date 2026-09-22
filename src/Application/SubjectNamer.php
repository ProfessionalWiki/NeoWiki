<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application;

use ProfessionalWiki\NeoWiki\Application\Source\SchemaResolver;
use ProfessionalWiki\NeoWiki\Domain\Page\Page;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectDisplayName;

/**
 * The name to show for a Subject: the rule {@see SubjectDisplayName} states, given the label the Subject's
 * Schema gives it through its label template. The Schema is resolved through $schemaResolver, so a namer
 * sees the Schemas the reader it was built for may read.
 */
readonly class SubjectNamer {

	public function __construct(
		private LabelTemplateRenderer $labelTemplateRenderer,
		private SchemaResolver $schemaResolver,
	) {
	}

	/**
	 * The name the Subject carries wherever it is: its stored label, else its template label; null when
	 * it has neither.
	 */
	public function ownLabel( Subject $subject ): ?string {
		return $subject->getLabel()?->text ?? $this->templateLabel( $subject );
	}

	public function chosenName( Subject $subject, PageSubjects $pageSubjects, string $pageName ): ?string {
		return SubjectDisplayName::chosenName( $subject, $this->templateLabel( $subject ), $pageSubjects, $pageName );
	}

	public function displayName( Subject $subject, PageSubjects $pageSubjects, string $pageName ): string {
		return SubjectDisplayName::forSubject( $subject, $this->templateLabel( $subject ), $pageSubjects, $pageName );
	}

	public function displayNameOnPage( Subject $subject, Page $page ): string {
		return SubjectDisplayName::forSubjectOnPage( $subject, $this->templateLabel( $subject ), $page );
	}

	public function displayNameWithoutPage( Subject $subject ): string {
		return SubjectDisplayName::forSubjectWithoutPage( $subject, $this->templateLabel( $subject ) );
	}

	/**
	 * Null for a Subject with a stored label, which wins over it, so that one costs no Schema lookup.
	 */
	private function templateLabel( Subject $subject ): ?string {
		if ( $subject->getLabel() !== null ) {
			return null;
		}

		$schema = $this->schemaResolver->getSchema( $subject->getSchemaReference() );

		return $schema === null ? null : $this->labelTemplateRenderer->render( $subject, $schema );
	}

}
