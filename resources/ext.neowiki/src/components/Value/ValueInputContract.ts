import type { Value } from '@neo/domain/Value';
import type { PropertyDefinition } from '@neo/domain/PropertyDefinition';
import { ValidationStatusType } from '@wikimedia/codex';

export interface ValueInputProps<T extends PropertyDefinition> {
	modelValue: Value;
	label?: string;
	property: T;
}

export type ValueInputEmits = {
	'update:modelValue': [Value | undefined];
	'validation': [boolean];
};

export interface ValidationState {
	isValid: boolean;
	statuses: ValidationStatusType[];
	messages: ValidationMessages[];
}

export interface ValidationMessages {
	[key: string]: string;
}
