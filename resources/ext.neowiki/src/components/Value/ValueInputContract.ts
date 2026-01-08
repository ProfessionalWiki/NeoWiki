import type { Value } from '@/domain/Value';
import type { PropertyDefinition } from '@/domain/PropertyDefinition';

export interface ValueInputProps<T extends PropertyDefinition> {
	modelValue: Value | undefined;
	label?: string;
	property: T;
}

export type ValueInputEmits = {
	'update:modelValue': [Value | undefined];
};

export interface ValueInputExposes {
	getCurrentValue(): Value | undefined;
}

export type ValueInputEmitFunction = {
	// eslint-disable-next-line @typescript-eslint/prefer-function-type
	( event: 'update:modelValue', value: Value ): void;
};
