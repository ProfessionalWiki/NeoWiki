import type { PropertyDefinition, PropertyName } from '@/domain/PropertyDefinition';

export class PropertyDefinitionList implements Iterable<PropertyDefinition> {

	private readonly properties = new Map<string, PropertyDefinition>();

	public constructor( properties: PropertyDefinition[] ) {
		for ( const property of properties ) {
			const name = property.name.toString();

			if ( this.properties.has( name ) ) {
				throw new Error( `Duplicate property name: ${ name }` );
			}

			this.properties.set( name, property );
		}
	}

	public get( id: PropertyName ): PropertyDefinition {
		return this.properties.get( id.toString() ) as PropertyDefinition;
	}

	public has( name: PropertyName ): boolean {
		return this.properties.has( name.toString() );
	}

	public asRecord(): Record<string, PropertyDefinition> {
		return Object.fromEntries( this.properties );
	}

	public [ Symbol.iterator ](): IterableIterator<PropertyDefinition> {
		return this.properties.values();
	}

	public withNames( names: PropertyName[] ): PropertyDefinitionList {
		const stringNames = names.map( ( name ): string => name.toString() );
		return this.filter( ( property ): boolean => stringNames.includes( property.name.toString() ) );
	}

	public withoutNames( names: PropertyName[] ): PropertyDefinitionList {
		const stringNames = names.map( ( name ): string => name.toString() );
		return this.filter( ( property ): boolean => !stringNames.includes( property.name.toString() ) );
	}

	public reordered( names: PropertyName[] ): PropertyDefinitionList {
		return new PropertyDefinitionList(
			names
				.map( ( name ): PropertyDefinition | undefined => this.properties.get( name.toString() ) )
				.filter( ( property ): property is PropertyDefinition => property !== undefined ),
		);
	}

	private filter( callback: ( property: PropertyDefinition ) => boolean ): PropertyDefinitionList {
		return new PropertyDefinitionList(
			[ ...this ].filter( callback ),
		);
	}

	/**
	 * Adds a Property Definition, replacing any existing definition with the same name.
	 */
	public withPropertyDefinition( property: PropertyDefinition ): PropertyDefinitionList {
		const newProperties = new Map( this.properties );
		newProperties.set( property.name.toString(), property );
		return new PropertyDefinitionList( [ ...newProperties.values() ] );
	}

}
