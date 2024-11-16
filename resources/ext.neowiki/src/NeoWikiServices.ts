import { App, inject } from 'vue';
import { FormatSpecificComponentRegistry } from '@/FormatSpecificComponentRegistry.ts';
import { SchemaAuthorizer } from '@/application/SchemaAuthorizer.ts';
import { SubjectAuthorizer } from '@/application/SubjectAuthorizer.ts';
import { NeoWikiExtension } from '@/NeoWikiExtension.ts';
import { SubjectValidator } from '@neo/domain/SubjectValidator.ts';
import { ValueFormatRegistry } from '@neo/domain/ValueFormat.ts';

export enum Service {
	ComponentRegistry = 'ComponentRegistry',
	SchemaAuthorizer = 'SchemaAuthorizer',
	SubjectAuthorizer = 'SubjectAuthorizer',
	SubjectValidator = 'SubjectValidator',
	ValueFormatRegistry = 'ValueFormatRegistry'
}

export class NeoWikiServices {

	public static registerServices( app: App ): void {
		app.provide( Service.ComponentRegistry, NeoWikiExtension.getInstance().getFormatSpecificComponentRegistry() );
		app.provide( Service.SchemaAuthorizer, NeoWikiExtension.getInstance().newSchemaAuthorizer() );
		app.provide( Service.SubjectAuthorizer, NeoWikiExtension.getInstance().newSubjectAuthorizer() );
		app.provide( Service.SubjectValidator, NeoWikiExtension.getInstance().newSubjectValidator() );
		app.provide( Service.ValueFormatRegistry, NeoWikiExtension.getInstance().getValueFormatRegistry() );
	}

	public static getComponentRegistry(): FormatSpecificComponentRegistry {
		return inject( Service.ComponentRegistry ) as FormatSpecificComponentRegistry;
	}

	public static getValueFormatRegistry(): ValueFormatRegistry {
		return inject( Service.ValueFormatRegistry ) as ValueFormatRegistry;
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

}
