import { TextFormat } from '@neo/domain/valueFormats/Text';
import { NumberFormat } from '@neo/domain/valueFormats/Number';
import { RelationFormat } from '@neo/domain/valueFormats/Relation';
import { UrlFormat } from '@neo/domain/valueFormats/Url';
import { ValueFormatRegistry } from '@neo/domain/ValueFormat';
import { PropertyDefinitionDeserializer } from '@neo/domain/PropertyDefinition';

export class Neo {
	private static instance: Neo;

	public static getInstance(): Neo {
		Neo.instance ??= new Neo();
		return Neo.instance;
	}

	public getValueFormatRegistry(): ValueFormatRegistry {
		const registry = new ValueFormatRegistry();

		registry.registerFormat( new TextFormat() );
		registry.registerFormat( new NumberFormat() );
		registry.registerFormat( new RelationFormat() );
		registry.registerFormat( new UrlFormat() );

		return registry;
	}

	public getPropertyDefinitionDeserializer(): PropertyDefinitionDeserializer {
		return new PropertyDefinitionDeserializer( this.getValueFormatRegistry() );
	}
}
