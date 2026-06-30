import type { Component } from 'vue';
import type { Icon } from '@wikimedia/codex-icons';

export interface TypeSpecificStuff {
	valueDisplayComponent: Component;
	valueEditor: Component;
	attributesEditor: Component;
	label: string;
	icon: Icon;
}

export class TypeSpecificComponentRegistry {

	private typeMap: Map<string, TypeSpecificStuff> = new Map();

	private unknownFallback: TypeSpecificStuff | undefined;

	public registerType(
		propertyType: string,
		stuff: TypeSpecificStuff,
	): void {
		this.typeMap.set( propertyType, stuff );
	}

	/**
	 * Components used to render a property type that is not registered (e.g. owned
	 * by a disabled or failed extension). When set, the per-type getters degrade to
	 * these instead of throwing, so a single unknown type does not take down the
	 * whole view. The fallback is never offered as a selectable type.
	 */
	public setUnknownFallback( stuff: TypeSpecificStuff ): void {
		this.unknownFallback = stuff;
	}

	/**
	 * Returns a Component following ValueDisplayContract.vue.
	 * The builder of the Component is responsible for providing a PropertyDefinition of the correct type.
	 */
	public getValueDisplayComponent( propertyType: string ): Component {
		return this.resolveType( propertyType ).valueDisplayComponent;
	}

	private resolveType( propertyType: string ): TypeSpecificStuff {
		const stuff = this.typeMap.get( propertyType );
		if ( stuff !== undefined ) {
			return stuff;
		}

		if ( this.unknownFallback !== undefined ) {
			return this.unknownFallback;
		}

		throw new Error( `Unknown property type: ${ propertyType }` );
	}

	/**
	 * Returns a Component following ValueInputContract.vue.
	 * The builder of the Component is responsible for providing a PropertyDefinition of the correct type.
	 */
	public getValueEditingComponent( propertyType: string ): Component {
		return this.resolveType( propertyType ).valueEditor;
	}

	/**
	 * Returns a Component following AttributesEditorContract.vue.
	 * The builder of the Component is responsible for providing a PropertyDefinition of the correct type.
	 */
	public getAttributesEditor( propertyType: string ): Component {
		return this.resolveType( propertyType ).attributesEditor;
	}

	public getPropertyTypes(): string[] {
		return Array.from( this.typeMap.keys() );
	}

	public getLabelsAndIcons(): { value: string; label: string; icon: Icon }[] {
		return Array.from( this.typeMap.entries() )
			.map( ( [ value, { label, icon } ] ) => ( {
				value: value, // TODO: rename to name or formatName
				label: label,
				icon: icon,
			} ) );
	}

	public getIcon( propertyType: string ): Icon {
		return this.resolveType( propertyType ).icon;
	}

	public getLabel( propertyType: string ): string {
		return this.resolveType( propertyType ).label;
	}

}
