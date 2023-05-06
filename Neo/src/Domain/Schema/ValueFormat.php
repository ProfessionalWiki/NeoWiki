<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Schema;

enum ValueFormat: string {

	case Text = 'text';

	case Email = 'email';
	case Url = 'url';
	case PhoneNumber = 'phoneNumber';

	case Date = 'date';
	case Time = 'time';
	case DateTime = 'dateTime';
	case Duration = 'duration';

	case Number = 'number';
	case Percentage = 'percentage';
	case Currency = 'currency';
	case Slider = 'slider';

	case Checkbox = 'checkbox';
	case Toggle = 'toggle';

	case Relation = 'relation';

}
