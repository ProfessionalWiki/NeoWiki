<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Schema;

enum ValueType: string {

	case String = 'string';
	case Number = 'number';
	case Boolean = 'boolean';
	case Array = 'array';
	case Relation = 'relation';

}
