import { SubjectId } from './SubjectId';

/**
 * Parses Subject ID strings at system boundaries. An id that explicitly names
 * the local source canonicalizes to the bare form, so one Subject never has
 * two textual identities.
 */
export class SubjectIdParser {

	public constructor( private readonly localSourceKey: string ) {
	}

	public parse( text: string ): SubjectId {
		const id = new SubjectId( text );

		if ( id.source === this.localSourceKey ) {
			return this.newLocalId( id.localId, text );
		}

		return id;
	}

	private newLocalId( localId: string, originalText: string ): SubjectId {
		const bareId = new SubjectId( localId );

		if ( bareId.source !== null ) {
			throw new Error( 'Local Subject IDs must be bare: ' + originalText );
		}

		return bareId;
	}

}
