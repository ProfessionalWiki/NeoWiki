import type { Subject } from '@/domain/Subject';
import type { SubjectId } from '@/domain/SubjectId';

export interface SubjectLabelResult {
	id: string;
	label: string;
}

export interface SubjectLookup {

	getSubject( id: SubjectId ): Promise<Subject>;

	getSubjectLabels( search: string, schema: string ): Promise<SubjectLabelResult[]>;

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

	public async getSubjectLabels( _search: string, _schema: string ): Promise<SubjectLabelResult[]> {
		return [];
	}

	public clearSubjects(): void {
		this.subjects.clear();
	}

}
