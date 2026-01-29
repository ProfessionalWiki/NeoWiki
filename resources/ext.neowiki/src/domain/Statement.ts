import { PropertyName } from '@/domain/PropertyName';
import { Value } from '@/domain/Value';

export class Statement {

	public constructor(
		public readonly propertyName: PropertyName,
		public readonly propertyType: string,
		public readonly value: Value | undefined,
	) {
	}

	public hasValue(): boolean {
		return this.value !== undefined;
	}

	public withValue( value: Value | undefined ): Statement {
		return new Statement(
			this.propertyName,
			this.propertyType,
			value,
		);
	}

}
