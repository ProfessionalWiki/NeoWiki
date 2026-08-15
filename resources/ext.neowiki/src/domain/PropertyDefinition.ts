import { Neo } from '@/Neo';
import { PropertyTypeRegistry } from '@/domain/PropertyType';
import type { Severity } from '@/domain/Severity';
import { extractSeverities } from '@/domain/SeverityNormalizer';
import { Value } from '@/domain/Value';
import { ValueDeserializer } from '@/persistence/ValueDeserializer';

export class PropertyName {

	private readonly name: string;

	/**
	 * @param name - The name of the property.
	 * @param placeholder - Whether the name is a placeholder, used when creating a new property.
	 */
	public constructor( name: string, placeholder: boolean = false ) {
		this.name = name.trim();

		if ( !PropertyName.isValid( name ) && !placeholder ) {
			throw new Error( 'Invalid PropertyName' );
		}
	}

	public toString(): string {
		return this.name;
	}

	public static isValid( name: string ): boolean {
		return name.trim() !== '';
	}

}

export interface PropertyDefinition {

	readonly name: PropertyName;
	readonly type: string;
	readonly description: string;
	readonly required: boolean;
	readonly default?: Value;

	/**
	 * Severity per Constraint name for Constraints written in the object form
	 * (ADR 26). Absent means every Constraint has the default `warning` severity;
	 * the map is only set when at least one Constraint is annotated, so shorthand
	 * Schemas produce domain objects identical to factory-built ones.
	 */
	readonly constraintSeverities?: Readonly<Record<string, Severity>>;

}

export interface MultiStringProperty extends PropertyDefinition {

	readonly multiple: boolean;
	readonly uniqueItems: boolean;

}

/**
 * In production code, prefer using an instance of PropertyDefinitionDeserializer
 */
export function createPropertyDefinitionFromJson( id: string, json: any ): PropertyDefinition {
	return Neo.getInstance().getPropertyDefinitionDeserializer().propertyDefinitionFromJson( id, json );
}

export class PropertyDefinitionDeserializer {

	public constructor(
		private readonly registry: PropertyTypeRegistry,
		private readonly valueDeserializer: ValueDeserializer,
	) {}

	public propertyDefinitionFromJson( name: string | PropertyName, json: any ): PropertyDefinition {
		const [ values, severities ] = extractSeverities( json );

		// Severity is a Constraint concept. Display Attributes are explicitly not
		// Constraints (they are overridable per Layout), so a severity on one is
		// meaningless. Drop it: left in the map it would make serialization re-emit
		// the attribute in object form and break every consumer reading a scalar.
		if ( this.registry.hasType( json.type ) ) {
			for ( const attribute of this.registry.getType( json.type ).getDisplayAttributeNames() ) {
				delete severities[ attribute ];
			}
		}

		const base: PropertyDefinition = {
			name: typeof name === 'string' ? new PropertyName( name ) : name,
			type: json.type as string,
			description: values.description as string ?? '',
			required: values.required as boolean ?? false,
			default: values.default !== undefined && values.default !== null ?
				this.valueDeserializer.deserialize( values.default, json.type ) :
				undefined,
			...( Object.keys( severities ).length > 0 ? { constraintSeverities: severities } : {} ),
		};

		// A type owned by a disabled or failed extension is not registered. Degrade
		// to the base definition so the rest of the Schema still loads and renders.
		// Retain the original type-specific keys (constraints, display attributes)
		// so they are not silently dropped when the Schema is later re-saved. The
		// normalized values (not the raw JSON) are spread so serialization re-wraps
		// every annotated key through the same applySeverities path.
		if ( !this.registry.hasType( json.type ) ) {
			return { ...values, ...base };
		}

		return this.registry.getType( json.type ).createPropertyDefinitionFromJson( base, values );
	}

}
