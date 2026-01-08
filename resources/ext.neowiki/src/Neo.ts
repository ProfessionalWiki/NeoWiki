import { TextType } from '@/domain/propertyTypes/Text';
import { NumberType } from '@/domain/propertyTypes/Number';
import { RelationType } from '@/domain/propertyTypes/Relation';
import { UrlType } from '@/domain/propertyTypes/Url';
import { PropertyTypeRegistry } from '@/domain/PropertyType';
import { PropertyDefinitionDeserializer } from '@/domain/PropertyDefinition';
import { ValueDeserializer } from '@/persistence/ValueDeserializer';
import { StatementDeserializer } from '@/persistence/StatementDeserializer';
import { SubjectDeserializer } from '@/persistence/SubjectDeserializer';

export class Neo {

	private static instance: Neo;

	public static getInstance(): Neo {
		Neo.instance ??= new Neo();
		return Neo.instance;
	}

	public getPropertyTypeRegistry(): PropertyTypeRegistry {
		const registry = new PropertyTypeRegistry();

		registry.registerType( new TextType() );
		registry.registerType( new NumberType() );
		registry.registerType( new RelationType() );
		registry.registerType( new UrlType() );

		return registry;
	}

	public getPropertyDefinitionDeserializer(): PropertyDefinitionDeserializer {
		return new PropertyDefinitionDeserializer( this.getPropertyTypeRegistry(), this.getValueDeserializer() );
	}

	public getValueDeserializer(): ValueDeserializer {
		return new ValueDeserializer( this.getPropertyTypeRegistry() );
	}

	public getStatementDeserializer(): StatementDeserializer {
		return new StatementDeserializer( this.getValueDeserializer() );
	}

	public getSubjectDeserializer(): SubjectDeserializer {
		return new SubjectDeserializer( this.getStatementDeserializer() );
	}

}
