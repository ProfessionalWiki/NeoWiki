import { TypeSpecificComponentRegistry } from '@/TypeSpecificComponentRegistry';
import { PropertyTypeRegistry } from '@/domain/PropertyType';
import type { PropertyTypeRegistration } from '@/domain/PropertyTypeRegistration';
import type { ViewTypeRegistration } from '@/domain/ViewTypeRegistration';
import { ViewTypeRegistry } from '@/ViewTypeRegistry';
import { PropertyTypeAdapter } from '@/presentation/PropertyTypeAdapter';

/**
 * Handed to subscribers of `mw.hook('neowiki.registration')`. Each
 * register*() call mutates the registries that NeoWikiServices will
 * provide() to the Vue app — the same registry instances, guaranteed by
 * memoization on NeoWikiExtension / Neo.
 */
export class FrontendRegistrar {

	public constructor(
		private readonly componentRegistry: TypeSpecificComponentRegistry,
		private readonly propertyTypeRegistry: PropertyTypeRegistry,
		private readonly viewTypeRegistry: ViewTypeRegistry,
	) {
	}

	public registerPropertyType( registration: PropertyTypeRegistration ): void {
		this.propertyTypeRegistry.registerType( new PropertyTypeAdapter( registration ) );
		this.componentRegistry.registerType( registration.typeName, {
			valueDisplayComponent: registration.displayComponent,
			valueEditor: registration.inputComponent,
			attributesEditor: registration.attributesEditor,
			label: registration.label,
			icon: registration.icon,
		} );
	}

	public registerViewType( registration: ViewTypeRegistration ): void {
		this.viewTypeRegistry.registerType( registration.typeName, registration.component );
	}

}
