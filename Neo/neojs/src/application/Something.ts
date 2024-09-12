import { AnotherThing } from '@/application/AnotherThing';

export class Something {
	public doSomething(): string {
		return 'Something is done!';
	}

	public getAnotherThing(): AnotherThing {
		return new AnotherThing();
	}
}
