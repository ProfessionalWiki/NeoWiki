<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Actions\SetSubjectsOrdering;

interface SetSubjectsOrderingPresenter {

	public function presentOrderingChanged(): void;

	public function presentNoChange(): void;

	public function presentInvalidOrdering( string $reason ): void;

}
