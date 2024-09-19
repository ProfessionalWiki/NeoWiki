import type { Subject } from '@neo/domain/Subject';
import type { SubjectId } from '@neo/domain/SubjectId';

export interface SubjectLookup {

	getSubject( id: SubjectId ): Promise<Subject>;

}

export class InMemorySubjectLookup implements SubjectLookup {

	protected readonly subjects: Map<string, Subject> = new Map();

	public constructor( subjects: Subject[] ) {
		for ( const subject of subjects ) {
			this.subjects.set( subject.getId().text, subject );
		}
	}

	public async getSubject( id: SubjectId ): Promise<Subject> {
		if ( !this.subjects.has( id.text ) ) {
			throw new Error( `Subject with id ${ id.text } not found` );
		}
		return this.subjects.get( id.text ) as Subject;
	}

	public clearSubjects(): void {
		this.subjects.clear();
	}

}
