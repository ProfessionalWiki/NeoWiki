import type { MultiStringProperty, PropertyDefinition } from '@/editor/domain/PropertyDefinition';
import { TagMultiselectWidgetFactory, type TagMultiselectWidget } from '@/editor/presentation/Widgets/TagMultiselectWidgetFactory';
import type { StringValue, Value } from '@/editor/domain/Value';
import { newStringValue, ValueType } from '@/editor/domain/Value';
import type { FieldData } from '@/editor/presentation/SchemaForm';
import type { ColumnDefinition } from 'tabulator-tables';
import type { MultipleTextInputWidget } from '@/editor/presentation/Widgets/MultipleTextInputWidgetFactory';
import type { PropertyAttributes } from '@/editor/domain/PropertyDefinitionAttributes';

export class ValueFormatRegistry {

	private propertyTypes: Map<string, ValueFormat> = new Map();

	public registerFormat( format: ValueFormat ): void {
		this.propertyTypes.set( format.getFormatName(), format );
	}

	public getFormat( formatName: string ): ValueFormat {
		const format = this.propertyTypes.get( formatName );

		if ( format === undefined ) {
			throw new Error( 'Unknown value format: ' + formatName );
		}

		return format;
	}

	public getFormatNames(): string[] {
		return Array.from( this.propertyTypes.keys() );
	}

}

// TODO: Consider a better solution but not all widgets are correctly defined as inheritors of OO.ui.Widget
export type Field = OO.ui.CheckboxInputWidget | OO.ui.InputWidget | TagMultiselectWidget | MultipleTextInputWidget
| OO.ui.TextInputWidget | OO.ui.NumberInputWidget | OO.ui.ProgressBarWidget | OO.ui.MenuTagMultiselectWidget | OO.ui.Widget;

export abstract class BaseValueFormat<T extends PropertyDefinition, V extends Value, F extends Field, A extends PropertyAttributes> {
	public static readonly valueType: ValueType;
	public static readonly formatName: string;

	public getFormatName(): string {
		return ( this.constructor as typeof BaseValueFormat ).formatName;
	}

	public abstract validate( value: V, property: T ): ValidationResult;

	public abstract createPropertyDefinitionFromJson( base: PropertyDefinition, json: any ): T;

	public abstract createFormField( value: V | undefined, property: T ): OO.ui.Widget;

	public abstract getFieldData( field: F, property: T ): Promise<FieldData>;

	public abstract getAttributes( base: PropertyAttributes ): A;

	public createTableEditorColumn( property: T ): ColumnDefinition {
		return {
			title: property.name.toString(),
			field: property.name.toString()
		};
	}
}

export type ValueFormat = BaseValueFormat<PropertyDefinition, Value, Field, PropertyAttributes>;

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
	value: Value;
};

// TODO consider how to move this and getTagFieldData and getTextFieldData from here
export function createStringFormField( value: StringValue | undefined, property: MultiStringProperty, fieldType: string ): OO.ui.TextInputWidget | TagMultiselectWidget {
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
			allowReordering: true,
			required: property.required
		} );
	}

	return new OO.ui.TextInputWidget( {
		type: fieldType,
		value: value.strings[ 0 ],
		required: property.required
	} );
}

export function getTagFieldData( field: TagMultiselectWidget, property: PropertyDefinition ): FieldData {
	return field.getFieldData();
}

export async function getTextFieldData( field: OO.ui.TextInputWidget ): Promise<FieldData> {
	// TODO: this is an ugly way to validate via Promise
	const isValid = await field.getValidity().catch( () => false ) !== false;
	const inputElement = field.$input[ 0 ] as HTMLInputElement;
	const value = field.getValue();

	return {
		value: value !== '' ? newStringValue( value ) : newStringValue(),
		valid: isValid,
		errorMessage: isValid ? undefined : inputElement.validationMessage
	};
}
