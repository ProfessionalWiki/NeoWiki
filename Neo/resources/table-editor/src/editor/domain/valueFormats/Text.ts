import type { MultiStringProperty, PropertyDefinition } from '@/editor/domain/PropertyDefinition';
import { type StringValue, ValueType } from '@/editor/domain/Value';
import {
	BaseValueFormat,
	createStringFormField,
	getTagFieldData, getTextFieldData,
	ValidationResult
} from '@/editor/domain/ValueFormat';
import { PropertyName } from '@/editor/domain/PropertyDefinition';
import type { FieldData } from '@/editor/presentation/SchemaForm';
import type { CellComponent, ColumnDefinition } from 'tabulator-tables';
import type { TagMultiselectWidget } from '@/editor/presentation/Widgets/TagMultiselectWidgetFactory';
import type { PropertyAttributes } from '@/editor/domain/PropertyDefinitionAttributes';

export interface TextProperty extends MultiStringProperty {
}

export interface TextAttributes extends PropertyAttributes {
	readonly multiple?: boolean;
}

export class TextFormat extends BaseValueFormat<TextProperty, StringValue, OO.ui.TextInputWidget|TagMultiselectWidget, TextAttributes> {

	public static readonly valueType = ValueType.String;
	public static readonly formatName = 'text';

	public validate( value: StringValue, property: TextProperty ): ValidationResult {
		return new ValidationResult( [] );
	}

	public createPropertyDefinitionFromJson( base: PropertyDefinition, json: any ): TextProperty {
		return {
			...base,
			multiple: json.multiple ?? false,
			uniqueItems: json.uniqueItems ?? true
		} as TextProperty;
	}

	public createFormField( value: StringValue | undefined, property: TextProperty ): OO.ui.TextInputWidget | TagMultiselectWidget {
		return createStringFormField( value, property, 'text' );
	}

	public async getFieldData( field: OO.ui.TextInputWidget|TagMultiselectWidget, property: PropertyDefinition ): Promise<FieldData> {
		if ( field instanceof OO.ui.TagMultiselectWidget ) {
			return getTagFieldData( field, property );
		}
		return await getTextFieldData( field );
	}

	public createTableEditorColumn( property: TextProperty ): ColumnDefinition {
		const column = super.createTableEditorColumn( property );

		if ( property.multiple ) {
			column.formatter = ( cell: CellComponent ) => cell.getValue()?.join( ', ' );
		}

		return column;
	}

	public getAttributes( base: PropertyAttributes ): TextAttributes {
		return {
			...base,
			multiple: false
		};
	}
}

export function newTextProperty( name = 'MyTextProperty', multiple = false, format = TextFormat.formatName ): TextProperty {
	return {
		name: new PropertyName( name ),
		type: ValueType.String,
		format: format,
		description: '',
		required: false,
		default: '',
		multiple: multiple,
		uniqueItems: true
	};
}
