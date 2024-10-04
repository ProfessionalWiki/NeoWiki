import { App, inject } from 'vue';
import { FormatSpecificComponentRegistry } from '@/FormatSpecificComponentRegistry.ts';
import { SchemaAuthorizer } from '@/application/SchemaAuthorizer.ts';
import { SubjectAuthorizer } from '@/application/SubjectAuthorizer.ts';
import { NeoWikiExtension } from '@/NeoWikiExtension.ts';

export enum Service {
	ComponentRegistry = 'ComponentRegistry',
	SchemaAuthorizer = 'SchemaAuthorizer',
	SubjectAuthorizer = 'SubjectAuthorizer'
}

export class NeoWikiServices {

	public static registerServices( app: App ): void {
		app.provide( Service.ComponentRegistry, NeoWikiExtension.getInstance().getFormatSpecificComponentRegistry() );
		app.provide( Service.SchemaAuthorizer, NeoWikiExtension.getInstance().newSchemaAuthorizer() );
		app.provide( Service.SubjectAuthorizer, NeoWikiExtension.getInstance().newSubjectAuthorizer() );
	}

	public static getComponentRegistry(): FormatSpecificComponentRegistry {
		return inject( Service.ComponentRegistry ) as FormatSpecificComponentRegistry;
	}

	public static getSchemaAuthorizer(): SchemaAuthorizer {
		return inject( Service.SchemaAuthorizer ) as SchemaAuthorizer;
	}

	public static getSubjectAuthorizer(): SubjectAuthorizer {
		return inject( Service.SubjectAuthorizer ) as SubjectAuthorizer;
	}

}
