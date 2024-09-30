import { RightsBasedSubjectAuthorizer } from '@/persistence/RightsBasedSubjectAuthorizer.ts';
import { SubjectAuthorizer } from '@/application/SubjectAuthorizer.ts';
import { RightsFetcher, UserObjectBasedRightsFetcher } from '@/persistence/UserObjectBasedRightsFetcher.ts';
import { TextFormat } from '@neo/domain/valueFormats/Text.ts';
import TextValue from '@/components/AutomaticInfobox/Values/TextValue.vue';
import NeoTextField from '@/components/UIComponents/NeoTextField.vue';
import { UrlFormat } from '@neo/domain/valueFormats/Url.ts';
import UrlValue from '@/components/AutomaticInfobox/Values/UrlValue.vue';
import { NumberFormat } from '@neo/domain/valueFormats/Number.ts';
import NumberValue from '@/components/AutomaticInfobox/Values/NumberValue.vue';
import NeoNumberField from '@/components/UIComponents/NeoNumberField.vue';
import { RelationFormat } from '@neo/domain/valueFormats/Relation.ts';
import { FormatSpecificComponentRegistry } from '@/FormatSpecificComponentRegistry.ts';
import RelationValue from '@/components/AutomaticInfobox/Values/RelationValue.vue';

export class NeoWikiExtension {
	private static instance: NeoWikiExtension;

	public static getInstance(): NeoWikiExtension {
		NeoWikiExtension.instance ??= new NeoWikiExtension();
		return NeoWikiExtension.instance;
	}

	private rightsFetcher: RightsFetcher|undefined;

	public getFormatSpecificComponentRegistry(): FormatSpecificComponentRegistry {
		const registry = new FormatSpecificComponentRegistry();

		registry.registerComponents( TextFormat.formatName, TextValue, NeoTextField );
		registry.registerComponents( UrlFormat.formatName, UrlValue, NeoTextField );
		registry.registerComponents( NumberFormat.formatName, NumberValue, NeoNumberField );
		registry.registerComponents( RelationFormat.formatName, RelationValue, NeoTextField ); // TODO

		return registry;
	}

	public getMediaWiki(): typeof mw {
		return window.mw;
	}

	public newSubjectAuthorizer(): SubjectAuthorizer {
		return new RightsBasedSubjectAuthorizer(
			this.getUserObjectBasedRightsFetcher()
		);
	}

	public getUserObjectBasedRightsFetcher(): RightsFetcher {
		if ( this.rightsFetcher === undefined ) {
			this.rightsFetcher = new UserObjectBasedRightsFetcher();
		}
		return this.rightsFetcher;
	}
}
