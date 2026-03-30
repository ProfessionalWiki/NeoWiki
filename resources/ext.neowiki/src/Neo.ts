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

	// Cached so the neowiki.registration hook can populate it by reference.
	private propertyTypeRegistry: PropertyTypeRegistry | undefined;

	public static getInstance(): Neo {
		Neo.instance ??= new Neo();
		return Neo.instance;
	}

	public getPropertyTypeRegistry(): PropertyTypeRegistry {
		if ( this.propertyTypeRegistry === undefined ) {
			this.propertyTypeRegistry = new PropertyTypeRegistry();

			this.propertyTypeRegistry.registerType( new TextType() );
			this.propertyTypeRegistry.registerType( new NumberType() );
			this.propertyTypeRegistry.registerType( new RelationType() );
			this.propertyTypeRegistry.registerType( new UrlType() );
		}

		return this.propertyTypeRegistry;
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
