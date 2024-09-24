import { ValueFormatComponent } from '@/presentation/ValueFormatComponent';
import { Component } from 'vue';
import RelationValue from '@/components/AutomaticInfobox/Values/RelationValue.vue';
import { RelationFormat } from '@neo/domain/valueFormats/Relation';

export class RelationFormatComponent extends ValueFormatComponent {

	public static readonly formatName: string = RelationFormat.formatName;

	public getInfoboxValueComponent(): Component {
		return RelationValue;
	}

}
