<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\SubjectSummaries;

use RuntimeException;

/**
 * A search ran past the time the wiki allows it.
 */
class SubjectSummarySearchTimeoutException extends RuntimeException {
}
