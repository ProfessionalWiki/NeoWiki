import { ValueFormatComponent } from '@/presentation/ValueFormatComponent';
import { Component } from 'vue';
import TextValue from '@/components/AutomaticInfobox/Values/TextValue.vue';
import { TextFormat } from '@neo/domain/valueFormats/Text';

export class TextFormatComponent extends ValueFormatComponent {

	public static readonly formatName: string = TextFormat.formatName;

	public getInfoboxValueComponent(): Component {
		return TextValue;
	}

}
