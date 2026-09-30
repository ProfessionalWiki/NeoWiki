<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Presentation;

/**
 * Where NeoWiki sends readers in its documentation. Every link from PHP into https://neowiki.ai/docs/ is one of
 * these, so each can be checked against the docs tree. Cases name the topic; their values say where it is covered.
 */
enum DocumentationUrl: string {
	case GettingStarted = 'https://neowiki.ai/docs/guide/getting-started';
	case CreatingSchemas = 'https://neowiki.ai/docs/guide/getting-started#_1-create-a-schema';
	case CreatingSubjects = 'https://neowiki.ai/docs/guide/getting-started#_2-create-a-subject';
	case AuthoringMappings = 'https://neowiki.ai/docs/guide/author-an-ontology-mapping';
	case Subjects = 'https://neowiki.ai/docs/glossary#subject';
	case Layouts = 'https://neowiki.ai/docs/glossary#layout';
	case MappingFormat = 'https://neowiki.ai/docs/authoring/mapping-format';
	case SubjectFormat = 'https://neowiki.ai/docs/api/subject-format';
	case Installation = 'https://neowiki.ai/docs/operations/installation';
	case GraphStoreRebuilds = 'https://neowiki.ai/docs/operations/maintenance#background-rebuilds';
}
