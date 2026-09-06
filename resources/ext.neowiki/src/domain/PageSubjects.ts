import { SubjectWithContext } from '@/domain/SubjectWithContext';
import { SubjectId } from '@/domain/SubjectId';

export class PageSubjects {

	public constructor(
		private readonly pageId: number,
		private readonly mainSubjectId: SubjectId | null,
		private readonly subjects: SubjectWithContext[],
	) {
	}

	public getPageId(): number {
		return this.pageId;
	}

	public getMainSubjectId(): SubjectId | null {
		return this.mainSubjectId;
	}

	public getSubjects(): SubjectWithContext[] {
		return this.subjects;
	}

	public hasSubjects(): boolean {
		return this.subjects.length > 0;
	}

	public getSubject( id: SubjectId ): SubjectWithContext | undefined {
		return this.subjects.find( ( s ) => s.getId().text === id.text );
	}

	public isMain( id: SubjectId ): boolean {
		return this.mainSubjectId !== null && this.mainSubjectId.text === id.text;
	}

}
