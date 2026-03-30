import type { Component } from 'vue';
import type { Icon } from '@wikimedia/codex-icons';
import type { BasePropertyType } from '@/domain/PropertyType';
import type { PropertyDefinition } from '@/domain/PropertyDefinition';
import type { Value } from '@/domain/Value';

export interface PropertyTypeRegistration {
	typeName: string;
	valueType: string;
	displayAttributeNames: string[];
	createPropertyDefinition: BasePropertyType<PropertyDefinition, Value>['createPropertyDefinitionFromJson'];
	getExampleValue: BasePropertyType<PropertyDefinition, Value>['getExampleValue'];
	validate: BasePropertyType<PropertyDefinition, Value>['validate'];
	displayComponent: Component;
	inputComponent: Component;
	attributesEditor: Component;
	label: string;
	icon: Icon;
}
