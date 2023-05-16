import type { SubjectId } from '@/editor/domain/SubjectId';

export type SubjectProperties = Record<string, any>;

export interface RelationValue {
	target: string;
}

export class Subject {

	public constructor(
		private readonly id: SubjectId,
		private readonly label: string,
		private readonly schemaId: string,
		private properties: SubjectProperties
	) {
	}

	public getId(): SubjectId {
		return this.id;
	}

	public getLabel(): string {
		return this.label;
	}

	public getSchemaId(): string {
		return this.schemaId;
	}

	public getProperties(): SubjectProperties {
		return this.properties;
	}

}
