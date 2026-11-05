import { NeoWikiExtension } from '@/NeoWikiExtension.ts';
import { blocksSaving, type SubjectViolation } from '@/domain/SubjectViolation.ts';

/**
 * Whether the wiki, with its validation enforcement setting, would refuse a save over one of these violations.
 */
export function anyBlocksSaving( violations: readonly SubjectViolation[] ): boolean {
	const validationEnforced = NeoWikiExtension.getInstance().isValidationEnforced();

	return violations.some( ( violation ) => blocksSaving( violation, validationEnforced ) );
}
