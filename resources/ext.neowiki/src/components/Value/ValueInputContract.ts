import type { Value } from '@neo/domain/Value';
import type { PropertyDefinition } from '@neo/domain/PropertyDefinition';

export interface ValueInputProps<T extends PropertyDefinition> {
	modelValue: Value;
	label?: string;
	property: T;
}

export type ValueInputEmits = {
	'update:modelValue': [Value | undefined];
};

export type ValueInputEmitFunction = {
	// eslint-disable-next-line @typescript-eslint/prefer-function-type
	( event: 'update:modelValue', value: Value ): void;
};
