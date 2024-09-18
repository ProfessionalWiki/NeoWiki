import type { SubjectId } from '@/domain/SubjectId';
import type { Subject } from '@/domain/Subject';

export class SubjectMap {

	private subjects: Record<string, Subject> = {};

	public constructor( ...subjects: Subject[] ) {
		for ( const subject of subjects ) {
			this.add( subject );
		}
	}

	private add( subject: Subject ): void {
		this.subjects[ subject.getId().text ] = subject;
	}

	public get( subjectId: SubjectId ): Subject|undefined {
		return this.subjects[ subjectId.text ];
	}

	public keys(): string[] {
		return Object.keys( this.subjects );
	}

	public size(): number {
		return this.keys().length;
	}

	public *[ Symbol.iterator ](): Generator<Subject, void> {
		for ( const subject of Object.values( this.subjects ) ) {
			yield subject;
		}
	}

}
