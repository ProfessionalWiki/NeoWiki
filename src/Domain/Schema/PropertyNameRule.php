<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Schema;

enum PropertyNameRule: string {

	case CanonicalForm = 'canonical-form';
	case Empty = 'empty';
	case Characters = 'characters';
	case Integer = 'integer';
	case DotSegment = 'dot-segment';
	case Length = 'length';
	case Reserved = 'reserved';
	case NearDuplicate = 'near-duplicate';

}
