import type { Component } from 'vue';
import type { Icon } from '@wikimedia/codex-icons';
import type { PropertyDefinition } from '@/domain/PropertyDefinition';
import type { Value, ValueType } from '@/domain/Value';
import type { ValueValidationError } from '@/domain/PropertyType';

/**
 * Plain-object shape a frontend extension passes to the neowiki.registration hook.
 * Wrapped internally by PropertyTypeAdapter into a BasePropertyType.
 */
export interface PropertyTypeRegistration {
	typeName: string;
	valueType: ValueType;
	displayAttributeNames: string[];
	createPropertyDefinitionFromJson: ( base: PropertyDefinition, json: unknown ) => PropertyDefinition;
	getExampleValue: ( property: PropertyDefinition ) => Value;
	validate: ( value: Value | undefined, property: PropertyDefinition ) => ValueValidationError[];
	displayComponent: Component;
	inputComponent: Component;
	attributesEditor: Component;
	label: string;
	icon: Icon;
}
