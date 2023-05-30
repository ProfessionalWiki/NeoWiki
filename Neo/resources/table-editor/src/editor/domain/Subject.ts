import { SubjectId } from '@/editor/domain/SubjectId';
import { SubjectMap } from '@/editor/domain/SubjectMap';
import type { SubjectLookup } from '@/editor/application/SubjectLookup';
import type { PageIdentifiers } from '@/editor/domain/PageIdentifiers';

export type SubjectProperties = Record<string, any>;

export interface RelationValue {
	target: string;
}

export class Subject {

	public constructor(
		private readonly id: SubjectId,
		private readonly label: string,
		private readonly schemaId: string, // TODO: rename to schemaName
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

	public getPropertyValue( propertyName: string ): any {
		return this.properties[ propertyName ];
	}

	public getPageIdentifiers(): PageIdentifiers {
		return this.pageIdentifiers;
	}

	public async getReferencedSubjects( lookup: SubjectLookup ): Promise<SubjectMap> {
		return new SubjectMap(
			...await Promise.all(
				// TODO: error handling: silently ignore missing subjects?
				this.getIdsOfReferencedSubjects().map( ( id ) => lookup.getSubject( id ) )
			)
		);
	}

	public getIdsOfReferencedSubjects(): SubjectId[] {
		const ids: SubjectId[] = [];

		// TODO: use schema information to determine which properties are references.
		// Or... use type information inside the subject if we decided to include it.
		/* eslint-disable */
		for ( const [ key, value ] of Object.entries( this.properties ) ) {
			if ( Array.isArray( value ) ) {
				for ( const relation of value ) {
					if ( typeof relation === 'object' && relation.target ) {
						ids.push( new SubjectId( relation.target ) );
					}
				}
			}
			else if ( typeof value === 'object' && value.target ) {
				ids.push( new SubjectId( value.target ) );
			}
		}
		/* eslint-enable */
		return ids;
	}

	public getNonEmptyProperties(): string[] {
		return Object.keys( this.properties ).filter( ( propertyName ) => {
			const value = this.properties[ propertyName ];
			if ( value === false ) {
				return true;
			}
			if ( !value ) {
				return false;
			}
			if ( Array.isArray( value ) ) {
				return value.length > 0;
			} else if ( typeof value === 'string' ) {
				return value.trim() !== '';
			} else if ( typeof value === 'number' ) {
				return !isNaN( value );
			} else {
				return true;
			}
		} );
	}

}
