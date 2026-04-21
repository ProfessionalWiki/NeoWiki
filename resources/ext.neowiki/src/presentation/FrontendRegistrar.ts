import { TypeSpecificComponentRegistry } from '@/TypeSpecificComponentRegistry';
import { PropertyTypeRegistry } from '@/domain/PropertyType';
import type { PropertyTypeRegistration } from '@/domain/PropertyTypeRegistration';
import { PropertyTypeAdapter } from '@/presentation/PropertyTypeAdapter';

/**
 * Handed to subscribers of `mw.hook('neowiki.registration')`. Each
 * registerPropertyType() call mutates the two registries that
 * NeoWikiServices will provide() to the Vue app — the same registry
 * instances, guaranteed by memoization on NeoWikiExtension / Neo.
 */
export class FrontendRegistrar {

	public constructor(
		private readonly componentRegistry: TypeSpecificComponentRegistry,
		private readonly propertyTypeRegistry: PropertyTypeRegistry,
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

}
