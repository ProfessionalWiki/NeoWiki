import type { Value } from '@neo/domain/Value';
import type { PropertyDefinition } from '@neo/domain/PropertyDefinition';

export interface ValueDisplayProps<T extends PropertyDefinition> {
	value: Value;
	property: T;
}
