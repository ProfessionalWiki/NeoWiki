<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Schema;

enum ValueFormat {

	case Text;

	case Email;
	case Url;
	case PhoneNumber;

	case Date;
	case Time;
	case DateTime;
	case Duration;

	case Percentage;
	case Currency;
	case Slider;

	case Checkbox;
	case Toggle;

	case Relation;

}
