<template>
	<div class="ext-neowiki-layout-preview">
		<div class="ext-neowiki-layout-preview__toolbar">
			<span class="ext-neowiki-layout-preview__heading">
				{{ $i18n( 'neowiki-layout-preview-heading' ).text() }}
			</span>
			<CdxButton
				class="ext-neowiki-layout-preview__another"
				weight="quiet"
				:disabled="otherCandidates.length === 0"
				:aria-label="$i18n( 'neowiki-layout-preview-another' ).text()"
				:title="$i18n( 'neowiki-layout-preview-another' ).text()"
				@click="previewAnother"
			>
				<CdxIcon :icon="cdxIconReload" />
			</CdxButton>
			<PreviewSubjectPicker
				:schema-name="props.layout.getSchema()"
				:subject="pickerSubject"
				:disabled="state === 'loading' || state === 'empty'"
				@pick="previewSubject"
			/>
		</div>

		<!-- The preview is not the page: a link in it would leave the editor and the Layout's unsaved changes. -->
		<div
			ref="stage"
			class="ext-neowiki-layout-preview__stage"
			@click.capture="onStageClick"
			@mouseover="emit( 'hover', propertyNameAt( $event.target ) )"
			@mouseleave="emit( 'hover', null )"
		>
			<CdxProgressBar
				v-if="state === 'loading'"
				class="ext-neowiki-layout-preview__centred"
				:inline="true"
				:aria-label="$i18n( 'neowiki-layout-preview-loading' ).text()"
			/>
			<CdxMessage
				v-else-if="state === 'failed'"
				class="ext-neowiki-layout-preview__centred"
				type="error"
				:inline="true"
			>
				{{ $i18n( 'neowiki-layout-preview-load-error' ).text() }}
			</CdxMessage>
			<p
				v-else-if="state === 'empty'"
				class="ext-neowiki-layout-preview__no-subjects ext-neowiki-layout-preview__centred"
			>
				{{ $i18n( 'neowiki-layout-preview-no-subjects' ).text() }}
			</p>
			<div
				v-else
				class="ext-neowiki-layout-preview__view"
			>
				<component
					:is="viewComponent"
					:subject-id="previewedSubjectId"
					:can-edit-subject="false"
					:layout="props.layout"
				/>
			</div>
		</div>
	</div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, shallowRef, watch, type Component } from 'vue';
import { CdxButton, CdxIcon, CdxMessage, CdxProgressBar } from '@wikimedia/codex';
import { cdxIconReload } from '@wikimedia/codex-icons';
import type { Layout } from '@/domain/Layout.ts';
import { SubjectId } from '@/domain/SubjectId.ts';
import { NeoWikiServices } from '@/NeoWikiServices.ts';
import { NeoWikiExtension } from '@/NeoWikiExtension.ts';
import { useSubjectStore } from '@/stores/SubjectStore.ts';
import { useSchemaStore } from '@/stores/SchemaStore.ts';
import { subjectDisplayName } from '@/presentation/subjectDisplayName.ts';
import Infobox from '@/components/Views/Infobox.vue';
import PreviewSubjectPicker, { type PreviewedSubjectChoice } from './PreviewSubjectPicker.vue';

// "Show another Subject" picks among the Subjects edited last: the Subjects API has no random order.
const CANDIDATE_COUNT = 50;

const PROPERTY_ATTRIBUTE = 'data-mw-neowiki-property';

// A View's own controls, such as a toggle that shows more values, work in the preview as on a page.
const VIEW_CONTROLS = 'button, input, select, textarea, summary';

const props = defineProps<{
	layout: Layout;
	hoveredProperty: string | null;
	selectedProperty: string | null;
}>();

const emit = defineEmits<{
	hover: [ name: string | null ];
	select: [ name: string ];
}>();

const lookup = NeoWikiServices.getSubjectSummaryLookup();
const viewTypeRegistry = NeoWikiServices.getViewTypeRegistry();
const subjectStore = useSubjectStore();
const schemaStore = useSchemaStore();

const state = ref<'loading' | 'ready' | 'empty' | 'failed'>( 'loading' );
const candidates = shallowRef<string[]>( [] );
const previewedSubjectId = shallowRef<SubjectId | null>( null );
const stage = ref<HTMLElement | null>( null );

// As on a page: a Layout of a View Type nobody registered is shown as an infobox.
const viewComponent = computed( (): Component => viewTypeRegistry.hasType( props.layout.getType() ) ?
	viewTypeRegistry.getComponent( props.layout.getType() ) :
	Infobox );

const pickerSubject = computed( (): PreviewedSubjectChoice | null => {
	const subject = previewedSubjectId.value === null ? undefined : subjectStore.findSubject( previewedSubjectId.value );

	return subject === undefined ? null : { id: subject.getId().text, name: subjectDisplayName( subject ) };
} );

const otherCandidates = computed( (): string[] =>
	candidates.value.filter( ( id ) => id !== previewedSubjectId.value?.text ) );

onMounted( async (): Promise<void> => {
	try {
		const summaries = await lookup.getSubjectSummaries( {
			schema: props.layout.getSchema(),
			search: '',
			sort: 'edited',
			direction: 'desc',
			cursor: null,
			limit: CANDIDATE_COUNT
		} );
		candidates.value = summaries.subjects.map( ( summary ) => summary.id );
	} catch ( error ) {
		console.warn( 'NeoWiki: could not list the Subjects to preview:', error );
		state.value = 'failed';
		return;
	}

	if ( candidates.value.length === 0 ) {
		state.value = 'empty';
		return;
	}

	await previewSubject( candidates.value[ 0 ] );
} );

function previewAnother(): void {
	const others = otherCandidates.value;
	previewSubject( others[ Math.floor( Math.random() * others.length ) ] );
}

// Each pick loads again; only the latest one is shown.
let previewSequence = 0;

/**
 * The Subject the previous pick showed stays until the next one has loaded, with the Subjects it points at, whose
 * names its View shows, and its Schema. A pick that does not load leaves it in place, and is not offered again.
 */
async function previewSubject( subjectId: string ): Promise<void> {
	const sequence = ++previewSequence;
	const id = new SubjectId( subjectId );

	await NeoWikiExtension.getInstance().getLatestStoreStateLoader().loadSubjectsAndSchemas(
		new Set( [ subjectId ] ),
		new Set( [ subjectId ] )
	);

	if ( sequence !== previewSequence ) {
		return;
	}

	const subject = subjectStore.findSubject( id );

	if ( subject !== undefined && schemaStore.schemas.has( subject.getSchemaName() ) ) {
		previewedSubjectId.value = id;
		state.value = 'ready';
		return;
	}

	candidates.value = candidates.value.filter( ( candidate ) => candidate !== subjectId );

	if ( previewedSubjectId.value === null ) {
		state.value = 'failed';
	} else {
		mw.notify( mw.msg( 'neowiki-layout-preview-load-error' ), { type: 'error' } );
	}
}

function propertyNameAt( target: EventTarget | null ): string | null {
	return target instanceof Element ?
		target.closest( `[${ PROPERTY_ATTRIBUTE }]` )?.getAttribute( PROPERTY_ATTRIBUTE ) ?? null :
		null;
}

/**
 * A link is stopped before the View's own handlers see it too, as a relation's link to a missing Subject would
 * offer to create one. A click anywhere else on a property selects it, but for one on a control of the View.
 */
function onStageClick( event: MouseEvent ): void {
	const target = event.target instanceof Element ? event.target : null;

	if ( target?.closest( 'a[href]' ) ) {
		event.preventDefault();
		event.stopPropagation();
	} else if ( target?.closest( VIEW_CONTROLS ) ) {
		return;
	}

	const name = propertyNameAt( target );

	if ( name !== null ) {
		emit( 'select', name );
	}
}

/**
 * Marked on the View's own elements, found by the attribute every View Type gives them, so that no View Type needs to
 * know about the editor. Marked again whenever what is marked, the Layout or the Subject changes, as each can render
 * those elements anew.
 */
function markProperties(): void {
	stage.value?.querySelectorAll( `[${ PROPERTY_ATTRIBUTE }]` ).forEach( ( element ) => {
		const name = element.getAttribute( PROPERTY_ATTRIBUTE );
		element.classList.add( 'ext-neowiki-layout-preview__property' );
		element.classList.toggle( 'ext-neowiki-layout-preview__property--hovered', name === props.hoveredProperty );
		element.classList.toggle( 'ext-neowiki-layout-preview__property--selected', name === props.selectedProperty );
	} );
}

watch(
	[ () => props.hoveredProperty, () => props.selectedProperty, () => props.layout, previewedSubjectId ],
	markProperties,
	{ flush: 'post' }
);
</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';
@import ( reference ) '@/assets/mixins.less';

/* Fills its pane, so that the toolbar stays put while the stage under it scrolls. */
.ext-neowiki-layout-preview {
	flex: 1 1 auto;
	min-height: 0;
	display: flex;
	flex-direction: column;
	gap: @spacing-100;
	padding: @spacing-50 @spacing-150 @spacing-100 @spacing-100;

	&__toolbar {
		display: flex;
		flex-wrap: wrap;
		align-items: center;
		gap: @spacing-50;
	}

	&__heading {
		margin-inline-end: auto;
		font-weight: @font-weight-bold;
	}

	&__stage {
		.ext-neowiki-scroll-fade();
		flex: 1 1 auto;
		min-height: 0;
		overflow-y: auto;
	}

	/* Centred down as well as across, by auto margins: a View taller than the stage starts at its top and scrolls. */
	&__view,
	&__centred {
		margin-block: auto;
	}

	&__centred {
		align-self: center;
	}

	&__no-subjects {
		color: @color-subtle;
	}

	&__property {
		cursor: @cursor-base--hover;
	}

	&__property--hovered {
		background-color: @background-color-interactive-subtle;
	}

	&__property--selected {
		background-color: @background-color-progressive-subtle;
	}
}
</style>
