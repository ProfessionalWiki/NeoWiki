import { PropertyTypeRegistry } from '@/domain/PropertyType';
import {
	newBooleanValue,
	newNumberValue,
	newRelation,
	newStringValue,
	newUnknownValue,
	RelationValue,
	Value,
	ValueType,
} from '@/domain/Value';

export class ValueDeserializer {

	public constructor(
		private readonly registry: PropertyTypeRegistry,
	) {
	}

	/**
	 * Mismatch between the property type and the value structure will cause errors.
	 *
	 * A value of a type that is not registered (e.g. owned by a disabled or failed
	 * extension) is wrapped as an UnknownValue that preserves the raw stored data,
	 * rather than throwing and taking down the whole view.
	 */
	public deserialize( json: any, propertyTypeName: string ): Value {
		if ( !this.registry.hasType( propertyTypeName ) ) {
			return newUnknownValue( propertyTypeName, json );
		}

		switch ( this.propertyTypeNameToValueType( propertyTypeName ) ) {
			case ValueType.String:
				return newStringValue( json );
			case ValueType.Number:
				return newNumberValue( json );
			case ValueType.Boolean:
				return newBooleanValue( json );
			case ValueType.Relation:
				if ( !Array.isArray( json ) ) {
					throw new Error( 'Invalid relation value: ' + JSON.stringify( json ) );
				}

				return new RelationValue( json.map( ( relationJson: any ) => newRelation( relationJson.id, relationJson.target ) ) );
			// Keeps the switch exhaustive over ValueType. Not a live path: the hasType
			// guard above already handles unregistered types, and a registered type's
			// getValueType() never returns Unknown.
			case ValueType.Unknown:
				return newUnknownValue( propertyTypeName, json );
		}
	}

	private propertyTypeNameToValueType( propertyTypeName: string ): ValueType {
		return this.registry.getType( propertyTypeName ).getValueType();
	}

}
