import { ValueFormatComponent } from '@/presentation/ValueFormatComponent';
import { Component } from 'vue';
import { NumberFormat } from '@neo/domain/valueFormats/Number';
import NumberValue from '@/components/AutomaticInfobox/Values/NumberValue.vue';

export class NumberFormatComponent extends ValueFormatComponent {

	public static readonly formatName: string = NumberFormat.formatName;

	public getInfoboxValueComponent(): Component {
		return NumberValue;
	}

}
