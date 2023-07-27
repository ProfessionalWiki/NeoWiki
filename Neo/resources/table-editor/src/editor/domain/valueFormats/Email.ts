import type { MultiStringProperty, PropertyDefinition } from '@/editor/domain/PropertyDefinition';
import { type StringValue, ValueType } from '@/editor/domain/Value';
import {
	BaseValueFormat,
	createStringFormField,
	getTagFieldData, getTextFieldData,
	ValidationResult
} from '@/editor/domain/ValueFormat';
import type { FieldData } from '@/editor/presentation/SchemaForm';
import type { CellComponent, ColumnDefinition } from 'tabulator-tables';

export interface EmailProperty extends MultiStringProperty {
}

export class EmailFormat extends BaseValueFormat<EmailProperty, StringValue, OO.ui.TagMultiselectWidget|OO.ui.TextInputWidget> {

	public static readonly valueType = ValueType.String;
	public static readonly formatName = 'email';

	public validate( value: StringValue, property: EmailProperty ): ValidationResult {
		return new ValidationResult( [] ); // TODO
	}

	public createPropertyDefinitionFromJson( base: PropertyDefinition, json: any ): EmailProperty {
		return {
			...base,
			multiple: json.multiple ?? false,
			uniqueItems: json.uniqueItems ?? true
		} as EmailProperty;
	}
	public createFormField( value: StringValue | undefined, property: EmailProperty ): any {
		return createStringFormField( value, property, 'email' );
	}

	public async getFieldData( field: OO.ui.TagMultiselectWidget|OO.ui.TextInputWidget, property: PropertyDefinition ): Promise<FieldData> {
		if ( field instanceof OO.ui.TagMultiselectWidget ) {
			return getTagFieldData( field, property );
		}
		return await getTextFieldData( field );
	}

	public createTableEditorColumn( property: EmailProperty ): ColumnDefinition {
		const column = super.createTableEditorColumn( property );

		if ( property.multiple ) {
			column.formatter = ( cell: CellComponent ) => cell.getValue()?.join( ', ' );
		}

		return column;
	}
}
