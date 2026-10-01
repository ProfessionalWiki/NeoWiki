<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\SubjectSummaries;

enum SubjectSummarySort: string {

	/** Subject id descending: ids sort by creation time (ADR 14). Takes no direction. */
	case Newest = 'newest';
	case Name = 'name';
	case Schema = 'schema';
	case Page = 'page';
	case Edited = 'edited';

}
