import type { PropertyName } from '@neo/domain/PropertyDefinition';
import type { Value } from '@neo/domain/Value';

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

}

// TODO: move somewhere else
export interface StatementJson {

	value: unknown;
	format: string;

}

// TODO: move somewhere else
export function isJsonStatement( json: unknown ): json is StatementJson {
	return typeof json === 'object' &&
		json !== null &&
		'value' in json &&
		'format' in json;
}
