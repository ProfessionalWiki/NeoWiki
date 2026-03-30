import { PropertyTypeRegistry } from '@/domain/PropertyType';
import { TypeSpecificComponentRegistry } from '@/TypeSpecificComponentRegistry';
import { PropertyTypeAdapter } from '@/domain/PropertyTypeAdapter';
import type { PropertyTypeRegistration } from '@/domain/PropertyTypeRegistration';

export class FrontendRegistrar {

	public constructor(
		private readonly typeRegistry: PropertyTypeRegistry,
		private readonly componentRegistry: TypeSpecificComponentRegistry,
	) {}

	public registerType( registration: PropertyTypeRegistration ): void {
		if ( !registration.typeName || typeof registration.typeName !== 'string' ) {
			throw new Error( 'PropertyTypeRegistration requires a non-empty typeName string' );
		}

		if ( typeof registration.validate !== 'function' ) {
			throw new Error( `PropertyTypeRegistration "${ registration.typeName }" requires a validate function` );
		}

		if ( this.typeRegistry.getTypeNames().includes( registration.typeName ) ) {
			throw new Error( `Property type "${ registration.typeName }" is already registered` );
		}

		this.typeRegistry.registerType( new PropertyTypeAdapter( registration ) );

		this.componentRegistry.registerType( registration.typeName, {
			valueDisplayComponent: registration.displayComponent,
			valueEditor: registration.inputComponent,
			attributesEditor: registration.attributesEditor,
			label: registration.label,
			icon: registration.icon,
		} );
	}

}
