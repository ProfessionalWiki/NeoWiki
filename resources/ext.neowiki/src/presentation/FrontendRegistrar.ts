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

	private uiStarted = false;

	public constructor(
		private readonly componentRegistry: TypeSpecificComponentRegistry,
		private readonly propertyTypeRegistry: PropertyTypeRegistry,
		private readonly viewTypeRegistry: ViewTypeRegistry,
	) {
	}

	public registerPropertyType( registration: PropertyTypeRegistration ): void {
		if ( this.uiStarted ) {
			mw.log.warn(
				`NeoWiki: Property Type "${ registration.typeName }" was registered after the UI started, so values ` +
				'already read stay unregistered. Name its module in the NeoWikiGetFrontendModules hook.',
			);
		}

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

	public markUiStarted(): void {
		this.uiStarted = true;
	}

}
