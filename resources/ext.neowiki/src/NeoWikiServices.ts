import { App, inject } from 'vue';
import { TypeSpecificComponentRegistry } from '@/TypeSpecificComponentRegistry.ts';
import { SchemaPermissionHints } from '@/application/SchemaPermissionHints.ts';
import { SubjectPermissionHints } from '@/application/SubjectPermissionHints.ts';
import { NeoWikiExtension } from '@/NeoWikiExtension.ts';
import { PropertyTypeRegistry } from '@/domain/PropertyType.ts';
import { SchemaRepository } from '@/application/SchemaRepository.ts';
import { SubjectLabelSearch } from '@/domain/SubjectLabelSearch.ts';
import { ViewTypeRegistry } from '@/ViewTypeRegistry.ts';
import { LayoutPermissionHints } from '@/application/LayoutPermissionHints.ts';
import { LayoutRepository } from '@/application/LayoutRepository.ts';

export enum Service { // TODO: make private
	ComponentRegistry = 'ComponentRegistry',
	SchemaPermissionHints = 'SchemaPermissionHints',
	SubjectPermissionHints = 'SubjectPermissionHints',
	PropertyTypeRegistry = 'PropertyTypeRegistry',
	SchemaRepository = 'SchemaRepository',
	SubjectLabelSearch = 'SubjectLabelSearch',
	ViewTypeRegistry = 'ViewTypeRegistry',
	LayoutPermissionHints = 'LayoutPermissionHints',
	LayoutRepository = 'LayoutRepository'
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
			[ Service.SchemaPermissionHints ]: neoWiki.newSchemaPermissionHints(),
			[ Service.SubjectPermissionHints ]: neoWiki.newSubjectPermissionHints(),
			[ Service.PropertyTypeRegistry ]: neoWiki.getPropertyTypeRegistry(),
			[ Service.SchemaRepository ]: neoWiki.getSchemaRepository(),
			[ Service.SubjectLabelSearch ]: neoWiki.getSubjectLabelSearch(),
			[ Service.ViewTypeRegistry ]: neoWiki.getViewTypeRegistry(),
			[ Service.LayoutPermissionHints ]: neoWiki.newLayoutPermissionHints(),
			[ Service.LayoutRepository ]: neoWiki.getLayoutRepository(),
		};
	}

	public static getComponentRegistry(): TypeSpecificComponentRegistry {
		return inject( Service.ComponentRegistry ) as TypeSpecificComponentRegistry;
	}

	public static getPropertyTypeRegistry(): PropertyTypeRegistry {
		return inject( Service.PropertyTypeRegistry ) as PropertyTypeRegistry;
	}

	public static getSchemaPermissionHints(): SchemaPermissionHints {
		return inject( Service.SchemaPermissionHints ) as SchemaPermissionHints;
	}

	public static getSubjectPermissionHints(): SubjectPermissionHints {
		return inject( Service.SubjectPermissionHints ) as SubjectPermissionHints;
	}

	public static getSchemaRepository(): SchemaRepository {
		return inject( Service.SchemaRepository ) as SchemaRepository;
	}

	public static getSubjectLabelSearch(): SubjectLabelSearch {
		return inject( Service.SubjectLabelSearch ) as SubjectLabelSearch;
	}

	public static getViewTypeRegistry(): ViewTypeRegistry {
		return inject( Service.ViewTypeRegistry ) as ViewTypeRegistry;
	}

	public static getLayoutPermissionHints(): LayoutPermissionHints {
		return inject( Service.LayoutPermissionHints ) as LayoutPermissionHints;
	}

	public static getLayoutRepository(): LayoutRepository {
		return inject( Service.LayoutRepository ) as LayoutRepository;
	}

}
