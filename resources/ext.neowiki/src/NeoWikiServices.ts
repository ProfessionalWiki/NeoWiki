import { App, inject } from 'vue';
import { TypeSpecificComponentRegistry } from '@/TypeSpecificComponentRegistry.ts';
import { SchemaAuthorizer } from '@/application/SchemaAuthorizer.ts';
import { SubjectAuthorizer } from '@/application/SubjectAuthorizer.ts';
import { NeoWikiExtension } from '@/NeoWikiExtension.ts';
import { SubjectValidator } from '@neo/domain/SubjectValidator.ts';
import { PropertyTypeRegistry } from '@neo/domain/PropertyType.ts';
import { SchemaRepository } from '@/application/SchemaRepository.ts';

export enum Service { // TODO: make private
	ComponentRegistry = 'ComponentRegistry',
	SchemaAuthorizer = 'SchemaAuthorizer',
	SubjectAuthorizer = 'SubjectAuthorizer',
	SubjectValidator = 'SubjectValidator',
	PropertyTypeRegistry = 'PropertyTypeRegistry',
	SchemaRepository = 'SchemaRepository'
}

export class NeoWikiServices {

	public static registerServices( app: App ): void {
		Object.entries( NeoWikiServices.getServices() ).forEach( ( [ key, service ] ) => {
			app.provide( key, service );
		} );
	}

	public static getServices(): Record<string, unknown> {
		const neoWiki = NeoWikiExtension.getInstance();

		return {
			[ Service.ComponentRegistry ]: neoWiki.getTypeSpecificComponentRegistry(),
			[ Service.SchemaAuthorizer ]: neoWiki.newSchemaAuthorizer(),
			[ Service.SubjectAuthorizer ]: neoWiki.newSubjectAuthorizer(),
			[ Service.SubjectValidator ]: neoWiki.newSubjectValidator(),
			[ Service.PropertyTypeRegistry ]: neoWiki.getPropertyTypeRegistry(),
			[ Service.SchemaRepository ]: neoWiki.getSchemaRepository()
		};
	}

	public static getComponentRegistry(): TypeSpecificComponentRegistry {
		return inject( Service.ComponentRegistry ) as TypeSpecificComponentRegistry;
	}

	public static getPropertyTypeRegistry(): PropertyTypeRegistry {
		return inject( Service.PropertyTypeRegistry ) as PropertyTypeRegistry;
	}

	public static getSchemaAuthorizer(): SchemaAuthorizer {
		return inject( Service.SchemaAuthorizer ) as SchemaAuthorizer;
	}

	public static getSubjectAuthorizer(): SubjectAuthorizer {
		return inject( Service.SubjectAuthorizer ) as SubjectAuthorizer;
	}

	public static getSubjectValidator(): SubjectValidator {
		return inject( Service.SubjectValidator ) as SubjectValidator;
	}

	public static getSchemaRepository(): SchemaRepository {
		return inject( Service.SchemaRepository ) as SchemaRepository;
	}

}
