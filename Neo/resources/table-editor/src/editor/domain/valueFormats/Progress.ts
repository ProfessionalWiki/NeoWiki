import type { PropertyDefinition } from '@/editor/domain/PropertyDefinition';
import { newNumberValue, type NumberValue, ValueType } from '@/editor/domain/Value';
import { type ProgressBarWidget, ProgressBarWidgetFactory } from '@/editor/presentation/Widgets/ProgressBarWidgetFactory';
import {
	BaseValueFormat,
	ValidationResult
} from '@/editor/domain/ValueFormat';
import type { ColumnDefinition } from 'tabulator-tables';
import type { PropertyAttributes } from '@/editor/domain/PropertyDefinitionAttributes';
import { PropertyName } from '@/editor/domain/PropertyDefinition';
import type { NumberProperty } from '@/editor/domain/valueFormats/Number';
import { NumberFormat } from '@/editor/domain/valueFormats/Number';

export interface ProgressProperty extends PropertyDefinition {

	readonly minimum: number;
	readonly maximum: number;
	readonly step: number;

}

export interface ProgressAttributes extends PropertyAttributes {
	readonly minimum?: number;
	readonly maximum?: number;
	readonly step?: number;
}

export class ProgressFormat extends BaseValueFormat<ProgressProperty, NumberValue, ProgressBarWidget, ProgressAttributes> {

	public static readonly valueType = ValueType.Number;
	public static readonly formatName = 'progress';

	public getExampleValue(): NumberValue {
		return newNumberValue( 90 );
	}

	public validate( value: NumberValue, property: ProgressProperty ): ValidationResult {
		return new ValidationResult( [] ); // TODO
	}

	public createPropertyDefinitionFromJson( base: PropertyDefinition, json: any ): ProgressProperty {
		return {
			...base,
			minimum: json.minimum,
			maximum: json.maximum,
			step: json.step
		} as ProgressProperty;
	}

	public createFormField( value: NumberValue | undefined, property: ProgressProperty ): OO.ui.Widget {
		const progressBar = ProgressBarWidgetFactory.create( {
			progress: value?.number ?? 0,
			min: property.minimum ?? 0,
			max: property.maximum ?? 100,
			step: property.step ?? 1
		} );
		progressBar.appendLabel(); // TODO: add option to prop definition?
		return progressBar;
	}

	public getFieldData( field: ProgressBarWidget ): NumberValue {
		return field.getFieldData();
	}

	public createTableEditorColumn( property: ProgressProperty ): ColumnDefinition {
		const column: ColumnDefinition = super.createTableEditorColumn( property );

		column.formatter = 'progress';
		column.cssClass = 'progress';
		column.formatterParams = {
			legend: true,
			legendAlign: 'left',
			legendColor: '#FFFFFF',
			color: '#3366CC',
			min: property.minimum,
			max: property.maximum
			// TODO: step
		};

		return column;
	}

	public getAttributes( base: PropertyAttributes ): ProgressAttributes {
		return {
			...base,
			minimum: 0,
			maximum: 100,
			step: 1
		};
	}
}

export function newProgressProperty(
	name = 'MyProgressProperty',
	minimum = 0,
	maximum = 100,
	step = 1
): ProgressProperty {
	return {
		name: new PropertyName( name ),
		type: ValueType.Number,
		format: ProgressFormat.formatName,
		description: '',
		required: false,
		minimum: minimum,
		maximum: maximum,
		step: step
	};
}
