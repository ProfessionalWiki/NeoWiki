import { PropertyName } from '@neo/domain/PropertyDefinition';
import { Value } from '@neo/domain/Value';

export class Statement {

	public constructor(
		public readonly propertyName: PropertyName,
		public readonly format: string,
		public readonly value: Value | undefined
	) {
	}

	public hasValue(): boolean {
		return this.value !== undefined;
	}

	public withValue( value: Value | undefined ): Statement {
		return new Statement(
			this.propertyName,
			this.format,
			value
		);
	}

}
