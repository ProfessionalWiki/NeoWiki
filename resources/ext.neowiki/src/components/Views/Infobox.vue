<!-- eslint-disable vue/multi-word-component-names -->
<template>
	<div v-if="subject !== null" class="ext-neowiki-infobox">
		<div class="ext-neowiki-infobox__header">
			<div class="ext-neowiki-infobox__header__text">
				<div
					class="ext-neowiki-infobox__title"
					role="heading"
					aria-level="2"
				>
					{{ subject.getDisplayName() }}
				</div>
				<!-- Conditional on the badge, not the reverse: a wrapper left behind when the
					badge withholds itself announces as an unnamed level-3 heading. -->
				<div
					v-if="schemaNameBadge !== null"
					class="ext-neowiki-infobox__schema"
					role="heading"
					aria-level="3"
				>
					<SchemaNameDisplay :schema-name="schemaNameBadge" />
				</div>
			</div>
			<CdxButton
				v-if="canEditSubject"
				weight="quiet"
				:aria-label="$i18n( 'neowiki-infobox-edit-link' ).text()"
				@click="openEditor"
			>
				<CdxIcon :icon="cdxIconEdit" />
			</CdxButton>
			<SubjectEditorDialog
				v-if="editingSubject !== null && editingSchema !== null"
				v-model:open="isEditorOpen"
				:subject="editingSubject as Subject"
				:schema="editingSchema as Schema"
				:on-save="handleSaveSubject"
				:on-create="handleCreateSubject"
				:on-save-schema="handleSaveSchema"
			/>
		</div>
		<div class="ext-neowiki-infobox__content">
			<div
				v-for="resolved in resolvedProperties"
				:key="resolved.propertyDefinition.name.toString()"
				class="ext-neowiki-infobox__item"
			>
				<div class="ext-neowiki-infobox__property">
					{{ resolved.propertyDefinition.name.toString() }}
				</div>
				<div class="ext-neowiki-infobox__value">
					<component
						:is="getComponent( resolved.propertyDefinition.type )"
						:key="`${resolved.propertyDefinition.name}${resolved.value}-ext-neowiki-infobox`"
						:value="resolved.value"
						:property="resolved.propertyDefinition"
					/>
				</div>
			</div>
		</div>
	</div>
</template>

<script setup lang="ts">
import { Component, computed, ref, shallowRef } from 'vue';
import { Subject } from '@/domain/Subject.ts';
import { Schema } from '@/domain/Schema.ts';
import { useSchemaStore } from '@/stores/SchemaStore.ts';
import { useLayoutStore } from '@/stores/LayoutStore.ts';
import { NeoWikiServices } from '@/NeoWikiServices.ts';
import SubjectEditorDialog from '@/components/SubjectEditor/SubjectEditorDialog.vue';
import SchemaNameDisplay from '@/components/common/SchemaNameDisplay.vue';
import { schemaNameToShow } from '@/presentation/schemaNameToShow.ts';
import { useSubjectStore } from '@/stores/SubjectStore.ts';
import { CdxButton, CdxIcon } from '@wikimedia/codex';
import { cdxIconEdit } from '@wikimedia/codex-icons';
import { resolveDisplayProperties, type ResolvedProperty } from '@/domain/resolveDisplayProperties.ts';
import type { ViewProps } from '@/components/Views/ViewContract.ts';

const props = defineProps<ViewProps>();

const subjectStore = useSubjectStore();
const schemaStore = useSchemaStore();
const layoutStore = useLayoutStore();
const subjectRepo = NeoWikiServices.getSubjectRepository();
const schemaRepo = NeoWikiServices.getSchemaRepository();

const isEditorOpen = ref( false );
// The dialog edits its own copy rather than the registry entry (ADR 16). The display below stays
// on the stores until a save, whose response carries the Subject and Schema as persisted.
const editingSubject = shallowRef<Subject | null>( null );
const editingSchema = shallowRef<Schema | null>( null );

const subject = computed( () => subjectStore.getSubject( props.subjectId ) ); // TODO: handle not found
const schema = computed( () => schemaStore.getSchema( subject.value.getSchemaName() ) ); // TODO: handle not found

async function openEditor(): Promise<void> {
	try {
		const [ freshSubject, freshSchema ] = await Promise.all( [
			subjectRepo.getSubject( props.subjectId ),
			schemaRepo.getSchema( subject.value.getSchemaName() )
		] );

		editingSubject.value = freshSubject;
		editingSchema.value = freshSchema;
		isEditorOpen.value = true;
	} catch ( error ) {
		mw.notify(
			error instanceof Error ? error.message : String( error ),
			{ type: 'error' }
		);
	}
}

const handleSaveSubject = async ( updatedSubject: Subject, comment: string ): Promise<void> => {
	await subjectStore.updateSubject( updatedSubject, comment );
};

const handleCreateSubject = async ( subject: Subject, pageId: number, comment: string ): Promise<void> => {
	await subjectStore.createSubject( subject, pageId, comment );
};

const handleSaveSchema = async ( updatedSchema: Schema, comment: string ): Promise<void> => {
	await schemaStore.saveSchema( updatedSchema, comment );
};

function getComponent( propertyType: string ): Component {
	return NeoWikiServices.getComponentRegistry().getValueDisplayComponent( propertyType );
}

// Null when the Subject is already displayed under its Schema name (ADR 31), so the heading
// above goes with the badge. An unknown Schema throws while `schema` resolves, per its TODO.
const schemaNameBadge = computed( (): string | null =>
	schemaNameToShow( schema.value.getName(), subject.value.getDisplayName() )
);

const layout = computed( () => {
	if ( !props.layoutName ) {
		return undefined;
	}
	return layoutStore.getLayout( props.layoutName );
} );

const resolvedProperties = computed( (): ResolvedProperty[] => {
	if ( !schema.value || !subject.value ) {
		return [];
	}
	return resolveDisplayProperties( schema.value, subject.value, layout.value );
} );

</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';

.ext-neowiki-infobox {
	margin-inline: auto;
	margin-bottom: @spacing-100;
	max-width: 20rem;
	width: 100%;
	border: @border-base;
	border-radius: @border-radius-base;
	color: @color-base;
	background-color: @background-color-base;
	line-height: @line-height-small;

	@media ( min-width: @min-width-breakpoint-tablet ) {
		clear: both;
		float: right;
		margin-inline: @spacing-100 @spacing-0;
	}

	&__header {
		padding: @spacing-100 @spacing-75;
		display: flex;
		align-items: flex-start;

		/* A flex item's automatic minimum size is its max-content width, so without this the
			badge's un-wrappable name floors the column and the header grows past the infobox
			border. The badge ellipsises once allowed to be narrower than its text. */
		&__text {
			flex-grow: 1;
			min-width: 0;
		}
	}

	&__title {
		font-size: @font-size-x-large;
		font-weight: @font-weight-bold;
	}

	&__content {
		padding: @spacing-75;
	}

	&__item {
		display: flex;
		align-items: flex-start;
		margin-bottom: @spacing-75;
		padding-bottom: @spacing-75;
		border-bottom: @border-subtle;
		column-gap: @spacing-150;

		&:last-child {
			border-bottom: none;
			margin-bottom: @spacing-0;
			padding-bottom: @spacing-0;
		}
	}

	&__property {
		flex: 0 0 40%;
		font-weight: @font-weight-bold;
		color: @color-emphasized;
	}

	&__value {
		flex: 0 1 60%;
		overflow-wrap: anywhere;
		word-break: break-word;
	}
}

// TODO: This is a temporary fix until we implement Views.
@media ( min-width: @min-width-breakpoint-tablet ) {
	.ext-neowiki-view ~ h2,
	.ext-neowiki-view ~ h3,
	.ext-neowiki-view ~ h4,
	.ext-neowiki-view ~ h5,
	.ext-neowiki-view ~ h6,
	.ext-neowiki-view ~ .mw-heading {
		clear: both;
	}
}
</style>
