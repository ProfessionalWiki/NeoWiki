import { Something } from './application/Something';

export class Neo {
	private static instance: Neo;

	public static getInstance(): Neo {
		Neo.instance ??= new Neo();
		return Neo.instance;
	}

	public getSomething(): Something {
		return new Something();
	}

	public add( a: number, b: number ): number {
		return a + b;
	}

	public multiply( a: number, b: number ): number {
		return a * b;
	}
}
