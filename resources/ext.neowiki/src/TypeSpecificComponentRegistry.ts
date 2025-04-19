import type { Component } from 'vue';

export interface TypeSpecificStuff {
	valueDisplayComponent: Component;
	valueEditor: Component;
	attributesEditor: Component;
	label: string;
	icon: string;
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
		if ( !this.typeMap.has( propertyType ) ) {
			throw new Error( `No value display component registered for property type: ${ propertyType }` );
		}
		return this.typeMap.get( propertyType )!.valueDisplayComponent;
	}

	/**
	 * Returns a Component following ValueInputContract.vue.
	 * The builder of the Component is responsible for providing a PropertyDefinition of the correct type.
	 */
	public getValueEditingComponent( propertyType: string ): Component {
		if ( !this.typeMap.has( propertyType ) ) {
			throw new Error( `No value editing component registered for property type: ${ propertyType }` );
		}
		return this.typeMap.get( propertyType )!.valueEditor;
	}

	/**
	 * Returns a Component following AttributesEditorContract.vue.
	 * The builder of the Component is responsible for providing a PropertyDefinition of the correct type.
	 */
	public getAttributesEditor( propertyType: string ): Component {
		if ( !this.typeMap.has( propertyType ) ) {
			throw new Error( `No attributes editor component registered for property type: ${ propertyType }` );
		}
		return this.typeMap.get( propertyType )!.attributesEditor;
	}

	public getPropertyTypes(): string[] {
		return Array.from( this.typeMap.keys() );
	}

	public getLabelsAndIcons(): { value: string; label: string; icon: string }[] {
		return Array.from( this.typeMap.entries() )
			.map( ( [ value, { label, icon } ] ) => ( {
				value: value, // TODO: rename to name or formatName
				label: label,
				icon: icon
			} ) );
	}

}
