import type { SubjectId } from '@/editor/domain/SubjectId';
import type { Subject } from '@/editor/domain/Subject';

export class SubjectMap {

	private subjects = new Map<string, Subject>();

	public constructor( ...subjects: Subject[] ) {
		for ( const subject of subjects ) {
			this.add( subject );
		}
	}

	private add( subject: Subject ): void {
		this.subjects.set( subject.getId().text, subject );
	}

	public get( subjectId: SubjectId ): Subject|undefined {
		return this.subjects.get( subjectId.text );
	}

	public *[ Symbol.iterator ](): Generator<Subject, void, any> {
		yield* this.subjects.values();
	}

}
