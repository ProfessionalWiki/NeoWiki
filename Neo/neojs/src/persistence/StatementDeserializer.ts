import { ValueDeserializer } from '@neo/persistence/ValueDeserializer';
import { PropertyName } from '@neo/domain/PropertyDefinition';
import { Statement } from '@neo/domain/Statement';

interface StatementJson {

	value: unknown;
	type: string;

}

function isJsonStatement( json: unknown ): json is StatementJson {
	return typeof json === 'object' &&
		json !== null &&
		'value' in json &&
		'type' in json;
}

export class StatementDeserializer {

	public constructor(
		private readonly valueDeserializer: ValueDeserializer
	) {
	}

	public deserialize( propertyName: string, json: unknown ): Statement {
		if ( !isJsonStatement( json ) ) {
			throw new Error( 'Invalid statement JSON' );
		}

		return new Statement(
			new PropertyName( propertyName ),
			json.type,
			this.valueDeserializer.deserialize( json.value, json.type )
		);
	}

}
