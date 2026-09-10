<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Holds the one revision policy an extension may contribute: two policies cannot both decide which
 * revision a page publishes, and composing their answers would mean inventing a precedence nobody asked
 * for. A second registration is refused with a warning and the first keeps deciding, so the outcome
 * does not depend on which extension happened to load last.
 */
class RevisionPolicyRegistry {

	private ?RevisionPolicy $policy = null;

	public function __construct(
		private readonly LoggerInterface $logger = new NullLogger(),
	) {
	}

	public function setPolicy( RevisionPolicy $policy ): void {
		if ( $this->policy !== null ) {
			$this->logger->warning(
				'Ignoring the revision policy registered by {class}: a policy is already registered, '
				. 'and only one extension can decide which revision a page publishes.',
				[ 'class' => $policy::class ]
			);
			return;
		}

		$this->policy = $policy;
	}

	public function getPolicy(): RevisionPolicy {
		return $this->policy ?? new NullRevisionPolicy();
	}

}
