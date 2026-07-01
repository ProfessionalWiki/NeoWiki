import { RightsBasedSubjectAuthorizer } from '@/persistence/RightsBasedSubjectAuthorizer.ts';
import { SubjectAuthorizer } from '@/application/SubjectAuthorizer.ts';
import { RightsFetcher, UserObjectBasedRightsFetcher } from '@/persistence/UserObjectBasedRightsFetcher.ts';
import { TextType } from '@/domain/propertyTypes/Text.ts';
import TextDisplay from '@/components/Value/TextDisplay.vue';
import { UrlType } from '@/domain/propertyTypes/Url.ts';
import UrlDisplay from '@/components/Value/UrlDisplay.vue';
import { NumberType } from '@/domain/propertyTypes/Number.ts';
import NumberDisplay from '@/components/Value/NumberDisplay.vue';
import { SelectType } from '@/domain/propertyTypes/Select.ts';
import SelectDisplay from '@/components/Value/SelectDisplay.vue';
import { RelationType } from '@/domain/propertyTypes/Relation.ts';
import { DateTimeType } from '@/domain/propertyTypes/DateTime.ts';
import { DateType } from '@/domain/propertyTypes/Date.ts';
import { BooleanType } from '@/domain/propertyTypes/Boolean.ts';
import BooleanDisplay from '@/components/Value/BooleanDisplay.vue';
import { TypeSpecificComponentRegistry } from '@/TypeSpecificComponentRegistry.ts';
import { ViewTypeRegistry } from '@/ViewTypeRegistry.ts';
import Infobox from '@/components/Views/Infobox.vue';
import RelationDisplay from '@/components/Value/RelationDisplay.vue';
import DateTimeDisplay from '@/components/Value/DateTimeDisplay.vue';
import DateTimeInput from '@/components/Value/DateTimeInput.vue';
import DateDisplay from '@/components/Value/DateDisplay.vue';
import DateInput from '@/components/Value/DateInput.vue';
import BooleanInput from '@/components/Value/BooleanInput.vue';
import { HttpClient } from '@/infrastructure/HttpClient/HttpClient';
import { ProductionHttpClient } from '@/infrastructure/HttpClient/ProductionHttpClient';
import { RestSchemaRepository } from '@/persistence/RestSchemaRepository.ts';
import { SchemaRepository } from '@/application/SchemaRepository.ts';
import { LayoutRepository } from '@/application/LayoutRepository.ts';
import { RestLayoutRepository } from '@/persistence/RestLayoutRepository.ts';
import { LayoutSerializer } from '@/persistence/LayoutSerializer.ts';
import { LayoutDeserializer } from '@/persistence/LayoutDeserializer.ts';
import { LayoutAuthorizer } from '@/application/LayoutAuthorizer.ts';
import { RightsBasedLayoutAuthorizer } from '@/persistence/RightsBasedLayoutAuthorizer.ts';
import { CsrfSendingHttpClient } from '@/infrastructure/HttpClient/CsrfSendingHttpClient.ts';
import { SchemaSerializer } from '@/persistence/SchemaSerializer.ts';
import { SchemaDeserializer } from '@/persistence/SchemaDeserializer.ts';
import { RightsBasedSchemaAuthorizer } from '@/persistence/RightsBasedSchemaAuthorizer.ts';
import { SchemaAuthorizer } from '@/application/SchemaAuthorizer.ts';
import { SubjectRepository } from '@/domain/SubjectRepository.ts';
import { RestSubjectRepository } from '@/persistence/RestSubjectRepository.ts';
import { SubjectLabelSearch } from '@/domain/SubjectLabelSearch.ts';
import { RestSubjectLabelSearch } from '@/persistence/RestSubjectLabelSearch.ts';
import TextInput from '@/components/Value/TextInput.vue';
import UrlInput from '@/components/Value/UrlInput.vue';
import NumberInput from '@/components/Value/NumberInput.vue';
import SelectInput from '@/components/Value/SelectInput.vue';
import RelationInput from '@/components/Value/RelationInput.vue';
import { MediaWikiPageSaver } from '@/persistence/MediaWikiPageSaver.ts';
import { SubjectDeserializer } from '@/persistence/SubjectDeserializer.ts';
import { Neo } from '@/Neo.ts';
// import { cdxIconStringInteger } from '@/assets/CustomIcons.ts';
import { cdxIconLink, cdxIconSearchCaseSensitive, cdxIconArticles, cdxIconListBullet, cdxIconMathematics, cdxIconClock, cdxIconCalendar, cdxIconCheck, cdxIconAlert } from '@wikimedia/codex-icons';
import UnknownValueDisplay from '@/components/Value/UnknownValueDisplay.vue';
import UnknownValueInput from '@/components/Value/UnknownValueInput.vue';
import UnknownAttributesEditor from '@/components/SchemaEditor/Property/UnknownAttributesEditor.vue';
import TextAttributesEditor from '@/components/SchemaEditor/Property/TextAttributesEditor.vue';
import NumberAttributesEditor from '@/components/SchemaEditor/Property/NumberAttributesEditor.vue';
import SelectAttributesEditor from '@/components/SchemaEditor/Property/SelectAttributesEditor.vue';
import UrlAttributesEditor from '@/components/SchemaEditor/Property/UrlAttributesEditor.vue';
import RelationAttributesEditor from '@/components/SchemaEditor/Property/RelationAttributesEditor.vue';
import DateTimeAttributesEditor from '@/components/SchemaEditor/Property/DateTimeAttributesEditor.vue';
import DateAttributesEditor from '@/components/SchemaEditor/Property/DateAttributesEditor.vue';
import BooleanAttributesEditor from '@/components/SchemaEditor/Property/BooleanAttributesEditor.vue';
import { SubjectValidator } from '@/domain/SubjectValidator.ts';
import { PropertyTypeRegistry } from '@/domain/PropertyType.ts';
import { StoreStateLoader } from '@/persistence/StoreStateLoader.ts';
import { createPinia } from 'pinia';
import type { Pinia } from 'pinia';

export class NeoWikiExtension {
	private static instance: NeoWikiExtension;

	public static getInstance(): NeoWikiExtension {
		NeoWikiExtension.instance ??= new NeoWikiExtension();
		return NeoWikiExtension.instance;
	}

	private rightsFetcher: RightsFetcher|undefined;

	private typeSpecificComponentRegistry: TypeSpecificComponentRegistry | undefined;

	public getTypeSpecificComponentRegistry(): TypeSpecificComponentRegistry {
		this.typeSpecificComponentRegistry ??= this.newTypeSpecificComponentRegistry();
		return this.typeSpecificComponentRegistry;
	}

	private newTypeSpecificComponentRegistry(): TypeSpecificComponentRegistry {
		const registry = new TypeSpecificComponentRegistry();

		registry.registerType( TextType.typeName, {
			valueDisplayComponent: TextDisplay,
			valueEditor: TextInput,
			attributesEditor: TextAttributesEditor,
			label: 'neowiki-property-type-text',
			icon: cdxIconSearchCaseSensitive,
		} );

		registry.registerType( UrlType.typeName, {
			valueDisplayComponent: UrlDisplay,
			valueEditor: UrlInput,
			attributesEditor: UrlAttributesEditor,
			label: 'neowiki-property-type-url',
			icon: cdxIconLink,
		} );

		registry.registerType( NumberType.typeName, {
			valueDisplayComponent: NumberDisplay,
			valueEditor: NumberInput,
			attributesEditor: NumberAttributesEditor,
			label: 'neowiki-property-type-number',
			icon: cdxIconMathematics,
		} );

		registry.registerType( SelectType.typeName, {
			valueDisplayComponent: SelectDisplay,
			valueEditor: SelectInput,
			attributesEditor: SelectAttributesEditor,
			label: 'neowiki-property-type-select',
			icon: cdxIconListBullet,
		} );

		registry.registerType( RelationType.typeName, {
			valueDisplayComponent: RelationDisplay,
			valueEditor: RelationInput,
			attributesEditor: RelationAttributesEditor,
			label: 'neowiki-property-type-relation',
			icon: cdxIconArticles,
		} );

		registry.registerType( DateTimeType.typeName, {
			valueDisplayComponent: DateTimeDisplay,
			valueEditor: DateTimeInput,
			attributesEditor: DateTimeAttributesEditor,
			label: 'neowiki-property-type-datetime',
			icon: cdxIconClock,
		} );

		registry.registerType( DateType.typeName, {
			valueDisplayComponent: DateDisplay,
			valueEditor: DateInput,
			attributesEditor: DateAttributesEditor,
			label: 'neowiki-property-type-date',
			icon: cdxIconCalendar,
		} );

		registry.registerType( BooleanType.typeName, {
			valueDisplayComponent: BooleanDisplay,
			valueEditor: BooleanInput,
			attributesEditor: BooleanAttributesEditor,
			label: 'neowiki-property-type-boolean',
			icon: cdxIconCheck,
		} );

		// Render any property type that is not registered (e.g. owned by a disabled
		// or failed extension) with read-only placeholders instead of throwing.
		registry.setUnknownFallback( {
			valueDisplayComponent: UnknownValueDisplay,
			valueEditor: UnknownValueInput,
			attributesEditor: UnknownAttributesEditor,
			label: 'neowiki-property-type-unknown',
			icon: cdxIconAlert,
		} );

		return registry;
	}

	private viewTypeRegistry: ViewTypeRegistry | undefined;

	public getViewTypeRegistry(): ViewTypeRegistry {
		this.viewTypeRegistry ??= this.newViewTypeRegistry();
		return this.viewTypeRegistry;
	}

	private newViewTypeRegistry(): ViewTypeRegistry {
		const registry = new ViewTypeRegistry();
		registry.registerType( 'infobox', Infobox );
		return registry;
	}

	private pinia: Pinia | undefined;

	public getPinia(): Pinia {
		this.pinia ??= createPinia();
		return this.pinia;
	}

	public getMediaWiki(): typeof mw {
		return window.mw;
	}

	public newSubjectAuthorizer(): SubjectAuthorizer {
		return new RightsBasedSubjectAuthorizer(
			this.getUserObjectBasedRightsFetcher(),
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
			new SchemaDeserializer(),
			new MediaWikiPageSaver( this.getMediaWiki() ),
		);
	}

	public getLayoutRepository(): LayoutRepository {
		return new RestLayoutRepository(
			this.getMediaWiki().util.wikiScript( 'rest' ),
			this.newHttpClient(),
			new LayoutSerializer(),
			new LayoutDeserializer(),
			new MediaWikiPageSaver( this.getMediaWiki() ),
		);
	}

	public newLayoutAuthorizer(): LayoutAuthorizer {
		return new RightsBasedLayoutAuthorizer(
			this.getUserObjectBasedRightsFetcher(),
		);
	}

	public newHttpClient(): HttpClient {
		return new CsrfSendingHttpClient(
			new ProductionHttpClient(),
		);
	}

	public newSchemaAuthorizer(): SchemaAuthorizer {
		return new RightsBasedSchemaAuthorizer(
			this.getUserObjectBasedRightsFetcher(),
		);
	}

	public getSubjectLabelSearch(): SubjectLabelSearch {
		return new RestSubjectLabelSearch(
			this.getMediaWiki().util.wikiScript( 'rest' ),
			this.newHttpClient(),
		);
	}

	public getSubjectRepository(): SubjectRepository {
		return new RestSubjectRepository(
			this.getMediaWiki().util.wikiScript( 'rest' ),
			this.newHttpClient(),
			this.getSubjectDeserializer(),
			this.getRevisionId(),
		);
	}

	private getRevisionId(): number | undefined {
		const current = mw.config.get( 'wgRevisionId' );
		const latest = mw.config.get( 'wgCurRevisionId' );
		// wgRevisionId is 0 on action pages and other non-revision contexts; treat as "latest".
		if ( !current || current === latest ) {
			return undefined;
		}
		return current;
	}

	public getSubjectDeserializer(): SubjectDeserializer {
		return this.getNeo().getSubjectDeserializer();
	}

	private getNeo(): Neo {
		return Neo.getInstance();
	}

	public getValidationDebounceMs(): number {
		const value = mw.config.get( 'wgNeoWikiValidationDebounceMs' );
		return typeof value === 'number' ? value : 300;
	}

	public newSubjectValidator(): SubjectValidator {
		return new SubjectValidator(
			this.getPropertyTypeRegistry(),
		);
	}

	public getPropertyTypeRegistry(): PropertyTypeRegistry {
		return this.getNeo().getPropertyTypeRegistry();
	}

	public getStoreStateLoader(): StoreStateLoader {
		return new StoreStateLoader(
			this.getSubjectRepository(),
			this.getSchemaRepository(),
			this.getLayoutRepository(),
		);
	}

}
