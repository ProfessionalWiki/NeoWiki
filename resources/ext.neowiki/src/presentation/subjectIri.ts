import { SubjectId } from '@/domain/SubjectId';

/**
 * A Subject's RDF concept URI. The `$base/entity/` prefix is derived server-side from the same rule
 * the RDF export mints IRIs with, and reaches the frontend as a configuration variable.
 *
 * A Subject of another Source is named under that Source's own base, which this wiki does not hold,
 * so none is derived for one — minting under this wiki's base would assert ownership of an entity
 * elsewhere, the same refusal SubjectIriResolver makes on the export side.
 */
export function subjectIri( subjectId: string ): string {
	if ( !SubjectId.isValidLocalId( subjectId ) ) {
		return '';
	}

	return ( ( mw.config.get( 'wgNeoWikiSubjectIriBase' ) as string | null ) ?? '' ) + subjectId;
}
