import type { MultiStringProperty, PropertyDefinition } from '@/editor/domain/PropertyDefinition';
import { TagMultiselectWidgetFactory } from '@/editor/presentation/Widgets/TagMultiselectWidgetFactory';
import type { StringValue, Value } from '@/editor/domain/Value';
import { ValueType } from '@/editor/domain/Value';

export class ValueFormatRegistry {

	private propertyTypes: Map<string, ValueFormatInterface<any, any>> = new Map();

	public registerFormat( format: ValueFormatInterface<any, any> ): void {
		this.propertyTypes.set( format.name, format );
	}

	public getFormat( formatName: string ): ValueFormat {
		const format = this.propertyTypes.get( formatName );

		if ( format === undefined ) {
			throw new Error( 'Unknown value format: ' + formatName );
		}

		return format;
	}

}

export interface ValueFormatInterface<T extends PropertyDefinition, V extends Value> {
	readonly valueType: ValueType;
	readonly name: string;

	validate( value: V, property: T ): ValidationResult;

	createPropertyDefinitionFromJson( base: PropertyDefinition, json: any ): T;

	createFormField( value: V | undefined, property: T ): any; // TODO: is there no working supertype for OOUI widgets?!
	formatValueAsHtml( value: V, property: T ): string;

	// TODO: createTableEditorCell?
}

export type ValueFormat = ValueFormatInterface<PropertyDefinition, Value>;

export class ValidationResult {

	public constructor(
		public readonly errors: ValidationError[]
	) {
	}

	public get isValid(): boolean {
		return this.errors.length === 0;
	}
}

export type ValidationError = {
	message: string;
};

export function createStringFormField( value: StringValue | undefined, property: MultiStringProperty, fieldType: string ): OO.ui.Widget {
	value = value ?? {
		type: ValueType.String,
		strings: []
	};

	if ( property.multiple ) { // FIXME: this only works well for the text format
		return TagMultiselectWidgetFactory.create( {
			selected: value.strings,
			allowArbitrary: true,
			allowDuplicates: !property.uniqueItems,
			allowEditTags: true,
			allowReordering: true
			// TODO: handle required?
		} );
	}

	return new OO.ui.TextInputWidget( {
		type: fieldType,
		value: value.strings[ 0 ],
		required: property.required
	} );
}
