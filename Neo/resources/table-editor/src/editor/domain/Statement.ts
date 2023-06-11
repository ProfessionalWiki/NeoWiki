import type { PropertyName } from '@/editor/domain/Schema';

export class Statement {

	public constructor(
		public readonly propertyName: PropertyName,
		public readonly value: unknown // TODO
	) {
	}

	public hasValue(): boolean {
		return this.value !== undefined &&
			!( Array.isArray( this.value ) && this.value.length === 0 ); // TODO: do we need to check for empty arrays here?
	}

}
