import type { MultiStringProperty, PropertyDefinition } from '@/editor/domain/PropertyDefinition';
import { newStringValue, type StringValue, ValueType } from '@/editor/domain/Value';
import {
	BaseValueFormat,
	getTextFieldData,
	type ValidationError,
	ValidationResult
} from '@/editor/domain/ValueFormat';
import type { FieldData } from '@/editor/presentation/SchemaForm';
import type { TagMultiselectWidget } from '@/editor/presentation/Widgets/TagMultiselectWidgetFactory';
import type { CellComponent, ColumnDefinition } from 'tabulator-tables';
import {
	type MultipleTextInputWidget,
	MultipleTextInputWidgetFactory
} from '@/editor/presentation/Widgets/MultipleTextInputWidgetFactory';

export interface EmailProperty extends MultiStringProperty {
}

export class EmailFormat extends BaseValueFormat<EmailProperty, StringValue, TagMultiselectWidget|OO.ui.TextInputWidget> {

	public static readonly valueType = ValueType.String;
	public static readonly formatName = 'email';

	public validate( value: StringValue, property: EmailProperty ): ValidationResult {
		const errors: ValidationError[] = [];

		value.strings.forEach( ( email ) => {
			if ( !isValidEmail( email ) ) {
				errors.push( {
					message: `${email} is not a valid email`,
					value: newStringValue( email )
				} );
			}
		} );

		return new ValidationResult( errors );
	}

	public createPropertyDefinitionFromJson( base: PropertyDefinition, json: any ): EmailProperty {
		return {
			...base,
			multiple: json.multiple ?? false,
			uniqueItems: json.uniqueItems ?? true
		} as EmailProperty;
	}

	public createFormField( value: StringValue | undefined, property: EmailProperty ): OO.ui.Widget {
		if ( property.multiple ) {
			return MultipleTextInputWidgetFactory.create( {
				type: this.getFormatName(),
				typeFormat: this,
				values: value?.strings ?? [],
				required: property.required
			} );
		}

		return new OO.ui.TextInputWidget( {
			type: this.getFormatName(),
			value: value?.strings[ 0 ] ?? '',
			required: property.required
		} );
	}

	public async getFieldData( field: OO.ui.Widget, property: PropertyDefinition ): Promise<FieldData> {
		if ( Object.prototype.hasOwnProperty.call( field, 'multiple' ) ) {
			return ( field as MultipleTextInputWidget ).getFieldData();
		}
		return await getTextFieldData( field as OO.ui.TextInputWidget );
	}

	public createTableEditorColumn( property: EmailProperty ): ColumnDefinition {
		const column = super.createTableEditorColumn( property );

		if ( property.multiple ) {
			column.formatter = ( cell: CellComponent ) => cell.getValue()?.join( ', ' );
		}

		return column;
	}

	public getFormatAttributes(): string[] {
		return [
			'required',
			'default',
			'multiple'
		];
	}
}

export function isValidEmail( email: string ): boolean {
	return Boolean( email.match(
		/^(([^<>()[\]\\.,;:\s@\"]+(\.[^<>()[\]\\.,;:\s@\"]+)*)|(\".+\"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/ // eslint-disable-line
	) );
}
