<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Application\Actions\MoveSubject;

interface MoveSubjectPresenter {

	public function presentMoved(): void;

	/**
	 * Called when the Subject already sits on the page it was asked to move to.
	 */
	public function presentNoChange(): void;

	/**
	 * Called when no page hosts the Subject, when the page that does no longer holds it, when the
	 * caller may not read that page, and when that page went away between the read check and its
	 * write - by which point nothing has been written, since the source page is written first. All
	 * four take this one shape so a Subject on a hidden page cannot be told apart from one that does
	 * not exist.
	 */
	public function presentSubjectNotFound(): void;

	/**
	 * Called when the target page does not exist or the caller may not read it, for the reason
	 * {@see PageReadAuthorizer} gives, and when its write failed and the source page was written back
	 * as it was. No Subject moved either way, though the restore path leaves the source page two
	 * revisions richer.
	 */
	public function presentTargetPageNotFound(): void;

	public function presentSubjectAlreadyOnTargetPage(): void;

	/**
	 * Called when the target page could not be written and putting the Subject back on its source
	 * page failed too, so it is left on neither and has to be restored from the source page's history.
	 */
	public function presentMoveIncomplete(): void;

}
