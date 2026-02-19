import type { PropertyDefinition, PropertyName } from '@/domain/PropertyDefinition';

export class PropertyDefinitionList implements Iterable<PropertyDefinition> {

	private readonly properties: Record<string, PropertyDefinition>;

	public constructor( properties: PropertyDefinition[] ) {
		this.properties = {};

		for ( const property of properties ) {
			const name = property.name.toString();

			if ( this.properties[ name ] ) {
				throw new Error( `Duplicate property name: ${ name }` );
			}

			this.properties[ name ] = property;
		}
	}

	public get( id: PropertyName ): PropertyDefinition {
		return this.properties[ id.toString() ];
	}

	public has( name: PropertyName ): boolean {
		return name.toString() in this.properties;
	}

	public asRecord(): Record<string, PropertyDefinition> {
		return this.properties;
	}

	public [ Symbol.iterator ](): Iterator<PropertyDefinition> {
		const properties = Object.values( this.properties );
		let index = 0;

		return {
			next: (): IteratorResult<PropertyDefinition> => {
				if ( index < properties.length ) {
					return { value: properties[ index++ ], done: false };
				} else {
					return { value: undefined, done: true };
				}
			},
		};
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
				.map( ( name ): PropertyDefinition | undefined => this.properties[ name.toString() ] )
				.filter( ( property ): property is PropertyDefinition => property !== undefined ),
		);
	}

	private filter( callback: ( property: PropertyDefinition ) => boolean ): PropertyDefinitionList {
		return new PropertyDefinitionList(
			Object.values( this.properties ).filter( callback ),
		);
	}

	/**
	 * Adds a Property Definition, replacing any existing definition with the same name.
	 */
	public withPropertyDefinition( property: PropertyDefinition ): PropertyDefinitionList {
		const newProperties = { ...this.properties };
		newProperties[ property.name.toString() ] = property;
		return new PropertyDefinitionList( Object.values( newProperties ) );
	}

}
