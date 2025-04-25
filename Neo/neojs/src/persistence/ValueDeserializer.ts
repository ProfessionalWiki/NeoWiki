import { PropertyTypeRegistry } from '@neo/domain/PropertyType';
import {
	newBooleanValue,
	newNumberValue,
	newRelation,
	newStringValue,
	RelationValue,
	Value,
	ValueType
} from '@neo/domain/Value';

export class ValueDeserializer {

	public constructor(
		private readonly registry: PropertyTypeRegistry
	) {
	}

	/**
	 * Mismatch between the format and the value structure will cause errors.
	 */
	public deserialize( json: any, format: string ): Value {
		switch ( this.formatToType( format ) ) {
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
		}
	}

	private formatToType( format: string ): ValueType {
		return this.registry.getType( format ).getValueType();
	}

}
