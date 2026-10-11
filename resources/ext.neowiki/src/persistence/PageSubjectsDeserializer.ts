import { PageSubjects } from '@/domain/PageSubjects';
import { Subject } from '@/domain/Subject';
import { SubjectId } from '@/domain/SubjectId';
import { Schema } from '@/domain/Schema';
import type { SubjectDeserializer } from '@/persistence/SubjectDeserializer';
import { SchemaDeserializer } from '@/persistence/SchemaDeserializer';
import type { SubjectJson } from '@/persistence/RestSubjectRepository';
import { notFoundTargetIds } from '@/persistence/notFoundTargetIds';

export interface PageSubjectsJson {
	pageId: number;
	mainSubjectId: string | null;
	subjects: Record<string, SubjectJson>;
	referencedSubjects?: Record<string, SubjectJson>;
	schemas?: Record<string, Record<string, unknown>>;
}

export interface DeserializedPageSubjects {
	pageSubjects: PageSubjects;
	referencedSubjects: Subject[];
	/** The targets of the page's Subjects' relations that the read found no Subject for. */
	notFoundTargetIds: SubjectId[];
	schemas: Schema[];
}

export class PageSubjectsDeserializer {

	public constructor(
		private readonly subjectDeserializer: SubjectDeserializer,
		private readonly schemaDeserializer: SchemaDeserializer = new SchemaDeserializer(),
	) {
	}

	public deserialize( json: PageSubjectsJson ): DeserializedPageSubjects {
		const subjects = this.deserializeSubjects( json.subjects, json.pageId );
		const mainSubjectId = json.mainSubjectId !== null ? new SubjectId( json.mainSubjectId ) : null;

		return {
			pageSubjects: new PageSubjects( json.pageId, mainSubjectId, subjects ),
			referencedSubjects: this.deserializeSubjects( json.referencedSubjects ?? {}, json.pageId ),
			notFoundTargetIds: notFoundTargetIds(
				subjects,
				[ ...Object.keys( json.subjects ), ...Object.keys( json.referencedSubjects ?? {} ) ],
			),
			schemas: this.deserializeSchemas( json.schemas ?? {} ),
		};
	}

	private deserializeSubjects( map: Record<string, SubjectJson>, fallbackPageId: number ): Subject[] {
		return Object.values( map ).map( ( subjectJson ) => this.subjectDeserializer.deserialize( {
			...subjectJson,
			pageId: subjectJson.pageId ?? fallbackPageId,
			pageTitle: subjectJson.pageTitle ?? '',
		} ) );
	}

	private deserializeSchemas( map: Record<string, Record<string, unknown>> ): Schema[] {
		return Object.entries( map ).map( ( [ name, schemaJson ] ) => this.schemaDeserializer.deserialize( name, schemaJson ) );
	}

}
