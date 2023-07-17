import type { PropertyName } from '@/editor/domain/PropertyDefinition';
import type { Value } from '@/editor/domain/Value';

export class Statement {

	public constructor(
		public readonly propertyName: PropertyName,
		public readonly value: Value | undefined
	) {
	}

	public hasValue(): boolean {
		return this.value !== undefined;
	}

}
