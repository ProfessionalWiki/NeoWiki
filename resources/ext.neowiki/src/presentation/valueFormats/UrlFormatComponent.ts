import { ValueFormatComponent } from '@/presentation/ValueFormatComponent';
import { Component } from 'vue';
import { UrlFormat } from '@neo/domain/valueFormats/Url';
import UrlValue from '@/components/AutomaticInfobox/Values/UrlValue.vue';

export class UrlFormatComponent extends ValueFormatComponent {

	public static readonly formatName: string = UrlFormat.formatName;

	public getInfoboxValueComponent(): Component {
		return UrlValue;
	}

}
