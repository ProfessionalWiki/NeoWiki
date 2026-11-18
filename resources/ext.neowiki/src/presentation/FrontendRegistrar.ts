import { type Component, markRaw } from 'vue';
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
/**
 * A component another extension shows on one of NeoWiki's pages, and whether it takes the place of the note the page
 * shows otherwise.
 */
export interface RegisteredNotice {
	readonly component: Component;
	readonly replacesNote: boolean;
}

export class FrontendRegistrar {

	private uiStarted = false;

	public constructor(
		private readonly componentRegistry: TypeSpecificComponentRegistry,
		private readonly propertyTypeRegistry: PropertyTypeRegistry,
		private readonly viewTypeRegistry: ViewTypeRegistry,
		private readonly overviewNotices: RegisteredNotice[],
		private readonly schemasNotices: RegisteredNotice[],
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

	/**
	 * Shows the component above the cards of Special:NeoWiki, in place of the note that tells a new wiki how to start
	 * when it replaces the first steps.
	 */
	public registerOverviewNotice( component: Component, replacesFirstSteps: boolean ): void {
		this.overviewNotices.push( { component: markRaw( component ), replacesNote: replacesFirstSteps } );
	}

	/**
	 * Shows the component above the Schemas of Special:Schemas, in place of the note that the wiki has none when it
	 * replaces that note.
	 */
	public registerSchemasNotice( component: Component, replacesEmptyNote: boolean ): void {
		this.schemasNotices.push( { component: markRaw( component ), replacesNote: replacesEmptyNote } );
	}

	public markUiStarted(): void {
		this.uiStarted = true;
	}

}
