import type { PropertyDefinition } from '@/domain/PropertyDefinition';
import { PropertyName } from '@/domain/PropertyDefinition';
import { newStringValue, type StringValue, ValueType } from '@/domain/Value';
import { BasePropertyType } from '@/domain/PropertyType';
import { formatDateForDisplay } from '@/domain/propertyTypes/dateText';

export { formatDateForDisplay };

export interface DateProperty extends PropertyDefinition {

	/**
	 * Inclusive lower bound: an ISO 8601 calendar date of year, month or day precision
	 * (e.g. `2025`, `2025-06`, `2025-06-15`).
	 */
	readonly minimum?: string;

	/**
	 * Inclusive upper bound. Same shape rules as the minimum.
	 */
	readonly maximum?: string;

	/**
	 * Least precise date allowed. Left out, a year alone is allowed.
	 */
	readonly minPrecision?: 'month' | 'day';

}

/**
 * Property type for calendar dates without a time component, of year, month or day precision:
 * ISO 8601 `YYYY`, `YYYY-MM` or `YYYY-MM-DD`. The backend validates values and bounds.
 */
export class DateType extends BasePropertyType<DateProperty, StringValue> {

	public static readonly valueType = ValueType.String;

	public static readonly typeName = 'date';

	public getDisplayAttributeNames(): string[] {
		return [];
	}

	public getExampleValue(): StringValue {
		return newStringValue( '2026-01-01' );
	}

	public createPropertyDefinitionFromJson( base: PropertyDefinition, json: any ): DateProperty {
		return {
			...base,
			minimum: typeof json.minimum === 'string' ? json.minimum : undefined,
			maximum: typeof json.maximum === 'string' ? json.maximum : undefined,
			minPrecision: json.minPrecision ?? undefined,
		} as DateProperty;
	}

}

type DatePropertyAttributes = Omit<Partial<DateProperty>, 'name'> & {
	name?: string | PropertyName;
};

export function newDateProperty( attributes: DatePropertyAttributes = {} ): DateProperty {
	return {
		name: attributes.name instanceof PropertyName ? attributes.name : new PropertyName( attributes.name || 'Date' ),
		type: DateType.typeName,
		description: attributes.description ?? '',
		required: attributes.required ?? false,
		default: attributes.default,
		minimum: attributes.minimum,
		maximum: attributes.maximum,
		minPrecision: attributes.minPrecision,
	};
}
