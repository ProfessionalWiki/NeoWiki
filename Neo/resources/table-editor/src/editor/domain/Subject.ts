import type { SubjectId } from '@/editor/domain/SubjectId';

export type SubjectProperties = Record<string, any>;

export class Subject {

	public constructor(
		private readonly id: SubjectId,
		private readonly label: string,
		private readonly types: string[],
		private properties: SubjectProperties
	) {
	}

	public getId(): SubjectId {
		return this.id;
	}

	public getLabel(): string {
		return this.label;
	}

	public getTypes(): string[] {
		return this.types;
	}

	public getProperties(): SubjectProperties {
		return this.properties;
	}

}
