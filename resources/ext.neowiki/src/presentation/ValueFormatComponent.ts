import { Component } from 'vue';

export abstract class ValueFormatComponent {

	public static readonly formatName: string;

	public getFormatName(): string {
		return ( this.constructor as typeof ValueFormatComponent ).formatName;
	}

	public abstract getInfoboxValueComponent(): Component;

}
