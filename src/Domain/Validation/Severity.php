<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Validation;

enum Severity: string {
	case Error = 'error';
	case Warning = 'warning';
}
