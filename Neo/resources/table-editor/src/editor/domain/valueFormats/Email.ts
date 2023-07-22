import type { MultiStringProperty, PropertyDefinition } from '@/editor/domain/PropertyDefinition';
import { type StringValue, ValueType } from '@/editor/domain/Value';
import { createStringFormField, ValidationResult, BaseValueFormat } from '@/editor/domain/ValueFormat';
import type { CellComponent, ColumnDefinition } from 'tabulator-tables';

export interface EmailProperty extends MultiStringProperty {
}

export class EmailFormat extends BaseValueFormat<EmailProperty, StringValue> {

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

	public formatValueAsHtml( value: StringValue, property: EmailProperty ): string {
		return value.strings.join( ', ' ); // TODO
	}

	public createTableEditorColumn( property: EmailProperty ): ColumnDefinition {
		const column = super.createTableEditorColumn( property );

		if ( property.multiple ) {
			column.formatter = ( cell: CellComponent ) => cell.getValue()?.join( ', ' );
		}

		return column;
	}

}
