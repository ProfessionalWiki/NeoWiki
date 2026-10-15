<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Data;

use ProfessionalWiki\NeoWiki\Application\HostSubjectBuilder;
use ProfessionalWiki\NeoWiki\Application\PageSubjectsLookup;
use ProfessionalWiki\NeoWiki\Application\SchemaLookup;
use ProfessionalWiki\NeoWiki\Application\SubjectRepository;
use ProfessionalWiki\NeoWiki\Application\SubjectResponseItemFactory;
use ProfessionalWiki\NeoWiki\Domain\Page\DependentSubjectRules;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemorySchemaLookup;

class TestNaming {

	public static function newResponseItemFactory(
		SubjectRepository $subjectRepository,
		SchemaLookup $schemaLookup = new InMemorySchemaLookup()
	): SubjectResponseItemFactory {
		return new SubjectResponseItemFactory(
			new PageSubjectsLookup( $subjectRepository ),
			self::newHostSubjectBuilder( $schemaLookup )
		);
	}

	public static function newHostSubjectBuilder( SchemaLookup $schemaLookup = new InMemorySchemaLookup() ): HostSubjectBuilder {
		return new HostSubjectBuilder( self::newDependentSubjectRules( $schemaLookup ) );
	}

	public static function newDependentSubjectRules( SchemaLookup $schemaLookup = new InMemorySchemaLookup() ): DependentSubjectRules {
		return new DependentSubjectRules( TestSources::newSchemaResolver( $schemaLookup ) );
	}

}
