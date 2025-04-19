import { RightsBasedSubjectAuthorizer } from '@/persistence/RightsBasedSubjectAuthorizer.ts';
import { SubjectAuthorizer } from '@/application/SubjectAuthorizer.ts';
import { RightsFetcher, UserObjectBasedRightsFetcher } from '@/persistence/UserObjectBasedRightsFetcher.ts';
import { TextType } from '@neo/domain/valueFormats/Text.ts';
import TextDisplay from '@/components/Value/TextDisplay.vue';
import { UrlType } from '@neo/domain/valueFormats/Url.ts';
import UrlDisplay from '@/components/Value/UrlDisplay.vue';
import { NumberType } from '@neo/domain/valueFormats/Number.ts';
import NumberDisplay from '@/components/Value/NumberDisplay.vue';
import { RelationType } from '@neo/domain/valueFormats/Relation.ts';
import { FormatSpecificComponentRegistry } from '@/FormatSpecificComponentRegistry.ts';
import RelationDisplay from '@/components/Value/RelationDisplay.vue';
import { HttpClient } from '@/infrastructure/HttpClient/HttpClient';
import { ProductionHttpClient } from '@/infrastructure/HttpClient/ProductionHttpClient';
import { RestSchemaRepository } from '@/persistence/RestSchemaRepository.ts';
import { SchemaRepository } from '@/application/SchemaRepository.ts';
import { CsrfSendingHttpClient } from '@/infrastructure/HttpClient/CsrfSendingHttpClient.ts';
import { SchemaSerializer } from '@/persistence/SchemaSerializer.ts';
import { RightsBasedSchemaAuthorizer } from '@/persistence/RightsBasedSchemaAuthorizer.ts';
import { SchemaAuthorizer } from '@/application/SchemaAuthorizer.ts';
import { SubjectRepository } from '@neo/domain/SubjectRepository.ts';
import { RestSubjectRepository } from '@/persistence/RestSubjectRepository.ts';
import TextInput from '@/components/Value/TextInput.vue';
import UrlInput from '@/components/Value/UrlInput.vue';
import NumberInput from '@/components/Value/NumberInput.vue';
import { MediaWikiPageSaver } from '@/persistence/MediaWikiPageSaver.ts';
import { SubjectDeserializer } from '@neo/persistence/SubjectDeserializer.ts';
import { Neo } from '@neo/Neo.ts';
import { cdxIconStringInteger, cdxIconTextA } from '@/assets/CustomIcons.ts';
import { cdxIconLink } from '@wikimedia/codex-icons';
import TextAttributesEditor from '@/components/SchemaEditor/Property/TextAttributesEditor.vue';
import NumberAttributesEditor from '@/components/SchemaEditor/Property/NumberAttributesEditor.vue';
import { SubjectValidator } from '@neo/domain/SubjectValidator.ts';
import { PropertyTypeRegistry } from '@neo/domain/PropertyType.ts';

export class NeoWikiExtension {
	private static instance: NeoWikiExtension;

	public static getInstance(): NeoWikiExtension {
		NeoWikiExtension.instance ??= new NeoWikiExtension();
		return NeoWikiExtension.instance;
	}

	private rightsFetcher: RightsFetcher|undefined;

	public getFormatSpecificComponentRegistry(): FormatSpecificComponentRegistry {
		const registry = new FormatSpecificComponentRegistry();

		registry.registerFormat( TextType.typeName, {
			valueDisplayComponent: TextDisplay,
			valueEditor: TextInput,
			attributesEditor: TextAttributesEditor,
			label: 'neowiki-format-text',
			icon: cdxIconTextA
		} );

		registry.registerFormat( UrlType.typeName, {
			valueDisplayComponent: UrlDisplay,
			valueEditor: UrlInput,
			attributesEditor: TextAttributesEditor, // TODO
			label: 'neowiki-format-url',
			icon: cdxIconLink
		} );

		registry.registerFormat( NumberType.typeName, {
			valueDisplayComponent: NumberDisplay,
			valueEditor: NumberInput,
			attributesEditor: NumberAttributesEditor,
			label: 'neowiki-format-number',
			icon: cdxIconStringInteger
		} );

		registry.registerFormat( RelationType.typeName, {
			valueDisplayComponent: RelationDisplay,
			valueEditor: TextInput, // TODO
			attributesEditor: TextAttributesEditor, // TODO
			label: 'neowiki-format-relation',
			icon: cdxIconLink // TODO
		} );

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

	public getSchemaRepository(): SchemaRepository {
		return new RestSchemaRepository(
			this.getMediaWiki().util.wikiScript( 'rest' ),
			this.newHttpClient(),
			new SchemaSerializer(),
			new MediaWikiPageSaver( this.getMediaWiki() )
		);
	}

	private newHttpClient(): HttpClient {
		return new CsrfSendingHttpClient(
			new ProductionHttpClient()
		);
	}

	public newSchemaAuthorizer(): SchemaAuthorizer {
		return new RightsBasedSchemaAuthorizer(
			this.getUserObjectBasedRightsFetcher()
		);
	}

	public getSubjectRepository(): SubjectRepository {
		return new RestSubjectRepository(
			this.getMediaWiki().util.wikiScript( 'rest' ),
			this.newHttpClient(),
			this.getSubjectDeserializer()
		);
	}

	public getSubjectDeserializer(): SubjectDeserializer {
		return this.getNeo().getSubjectDeserializer();
	}

	private getNeo(): Neo {
		return Neo.getInstance();
	}

	public newSubjectValidator(): SubjectValidator {
		return new SubjectValidator(
			this.getValueFormatRegistry()
		);
	}

	public getValueFormatRegistry(): PropertyTypeRegistry {
		return this.getNeo().getValueFormatRegistry();
	}
}
