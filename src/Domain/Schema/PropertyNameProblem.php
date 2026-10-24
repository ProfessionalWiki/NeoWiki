<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Schema;

readonly class PropertyNameProblem {

	/**
	 * @param string[] $details For CanonicalForm, the canonical form, followed by the hidden or look-alike characters
	 *  that set the name apart from it, as code points, or by nothing when only spaces differ. For Characters, each
	 *  character the name cannot hold, as a code point when it is invisible. For NearDuplicate, the name it is too
	 *  similar to.
	 */
	public function __construct(
		public string $name,
		public PropertyNameRule $rule,
		public array $details = [],
	) {
	}

	/**
	 * In English, for the API client that sent the name.
	 */
	public function explanation(): string {
		return 'Property name "' . $this->name . '" ' . match ( $this->rule ) {
			PropertyNameRule::CanonicalForm => $this->canonicalFormExplanation(),
			PropertyNameRule::Empty => 'cannot be empty',
			PropertyNameRule::Characters => 'cannot contain ' . implode( ', ', $this->details ),
			PropertyNameRule::Integer => 'cannot be an integer',
			PropertyNameRule::DotSegment => 'cannot be "." or ".."',
			PropertyNameRule::Length => 'cannot be longer than ' . PropertyNameRules::MAX_BYTES . ' bytes',
			PropertyNameRule::Reserved => 'is reserved',
			PropertyNameRule::NearDuplicate => 'is too similar to "' . $this->details[0] . '"',
		};
	}

	private function canonicalFormExplanation(): string {
		$hiddenCharacters = array_slice( $this->details, 1 );

		return 'must be written as "' . $this->details[0] . '"'
			. ( $hiddenCharacters === [] ? '' : ': it contains ' . implode( ', ', $hiddenCharacters ) );
	}

}
