import { TextType } from '@neo/domain/valueFormats/Text';
import { NumberType } from '@neo/domain/valueFormats/Number';
import { RelationType } from '@neo/domain/valueFormats/Relation';
import { UrlType } from '@neo/domain/valueFormats/Url';
import { PropertyTypeRegistry } from '@neo/domain/PropertyType';
import { PropertyDefinitionDeserializer } from '@neo/domain/PropertyDefinition';
import { ValueDeserializer } from '@neo/persistence/ValueDeserializer';
import { StatementDeserializer } from '@neo/persistence/StatementDeserializer';
import { SubjectDeserializer } from '@neo/persistence/SubjectDeserializer';

export class Neo {

	private static instance: Neo;

	public static getInstance(): Neo {
		Neo.instance ??= new Neo();
		return Neo.instance;
	}

	public getValueFormatRegistry(): PropertyTypeRegistry {
		const registry = new PropertyTypeRegistry();

		registry.registerType( new TextType() );
		registry.registerType( new NumberType() );
		registry.registerType( new RelationType() );
		registry.registerType( new UrlType() );

		return registry;
	}

	public getPropertyDefinitionDeserializer(): PropertyDefinitionDeserializer {
		return new PropertyDefinitionDeserializer( this.getValueFormatRegistry(), this.getValueDeserializer() );
	}

	public getValueDeserializer(): ValueDeserializer {
		return new ValueDeserializer( this.getValueFormatRegistry() );
	}

	public getStatementDeserializer(): StatementDeserializer {
		return new StatementDeserializer( this.getValueDeserializer() );
	}

	public getSubjectDeserializer(): SubjectDeserializer {
		return new SubjectDeserializer( this.getStatementDeserializer() );
	}

}
