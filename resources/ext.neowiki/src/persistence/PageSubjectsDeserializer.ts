import { PageSubjects } from '@/domain/PageSubjects';
import { Subject } from '@/domain/Subject';
import { SubjectId } from '@/domain/SubjectId';
import type { SubjectDeserializer } from '@/persistence/SubjectDeserializer';
import type { SubjectJson } from '@/persistence/RestSubjectRepository';

export interface PageSubjectsJson {
	pageId: number;
	mainSubjectId: string | null;
	subjects: Record<string, SubjectJson>;
}

export class PageSubjectsDeserializer {

	public constructor(
		private readonly subjectDeserializer: SubjectDeserializer,
	) {
	}

	public deserialize( json: PageSubjectsJson ): PageSubjects {
		const subjects: Subject[] = Object.values( json.subjects ).map( ( subjectJson ) => this.subjectDeserializer.deserialize( {
			...subjectJson,
			pageId: json.pageId,
			pageTitle: '',
		} ) );

		const mainSubjectId = json.mainSubjectId !== null ? new SubjectId( json.mainSubjectId ) : null;

		return new PageSubjects( json.pageId, mainSubjectId, subjects );
	}

}
