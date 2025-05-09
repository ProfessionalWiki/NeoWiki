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

	public registerType(
		propertyType: string,
		stuff: TypeSpecificStuff
	): void {
		this.typeMap.set( propertyType, stuff );
	}

	/**
	 * Returns a Component following ValueDisplayContract.vue.
	 * The builder of the Component is responsible for providing a PropertyDefinition of the correct type.
	 */
	public getValueDisplayComponent( propertyType: string ): Component {
		return this.getTypeOrThrow( propertyType ).valueDisplayComponent;
	}

	private getTypeOrThrow( propertyType: string ): TypeSpecificStuff {
		if ( !this.typeMap.has( propertyType ) ) {
			throw new Error( `Unknown property type: ${ propertyType }` );
		}
		return this.typeMap.get( propertyType )!;
	}

	/**
	 * Returns a Component following ValueInputContract.vue.
	 * The builder of the Component is responsible for providing a PropertyDefinition of the correct type.
	 */
	public getValueEditingComponent( propertyType: string ): Component {
		return this.getTypeOrThrow( propertyType ).valueEditor;
	}

	/**
	 * Returns a Component following AttributesEditorContract.vue.
	 * The builder of the Component is responsible for providing a PropertyDefinition of the correct type.
	 */
	public getAttributesEditor( propertyType: string ): Component {
		return this.getTypeOrThrow( propertyType ).attributesEditor;
	}

	public getPropertyTypes(): string[] {
		return Array.from( this.typeMap.keys() );
	}

	public getLabelsAndIcons(): { value: string; label: string; icon: Icon }[] {
		return Array.from( this.typeMap.entries() )
			.map( ( [ value, { label, icon } ] ) => ( {
				value: value, // TODO: rename to name or formatName
				label: label,
				icon: icon
			} ) );
	}

	public getIcon( propertyType: string ): Icon {
		return this.getTypeOrThrow( propertyType ).icon;
	}

	public getLabel( propertyType: string ): string {
		return this.getTypeOrThrow( propertyType ).label;
	}

}
