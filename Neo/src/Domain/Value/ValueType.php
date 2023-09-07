<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Value;

enum ValueType: string {

	case String = 'string';
	case Number = 'number';
	case Boolean = 'boolean';
	case Relation = 'relation';

}
