import type { SubjectId } from '@/editor/domain/SubjectId';

export type SubjectProperties = Record<string, any>;

export interface RelationValue {
	target: string;
}

export class PageIdentifiers {

	public constructor(
		private readonly pageId: number,
		private readonly pageTitle: string
	) {
	}

	public getPageId(): number {
		return this.pageId;
	}

	public getPageName(): string {
		return this.pageTitle;
	}

}

export class Subject {

	public constructor(
		private readonly id: SubjectId,
		private readonly label: string,
		private readonly schemaId: string,
		private readonly properties: SubjectProperties,
		private readonly pageIdentifiers: PageIdentifiers
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

	public getPageIdentifiers(): PageIdentifiers {
		return this.pageIdentifiers;
	}

}
