import type { Value } from '@/domain/Value';
import type { PropertyDefinition } from '@/domain/PropertyDefinition';

export interface ValueDisplayProps<T extends PropertyDefinition> {
	value: Value;
	property: T;
}
