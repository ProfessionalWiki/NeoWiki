<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Schema;

enum ValueType {

	case String;
	case Number;
	case Boolean;
	case Array;
	case Relation;

}
