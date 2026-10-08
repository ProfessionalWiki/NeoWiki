<template>
	<CdxTable
		class="ext-neowiki-schemas-table"
		:columns="columns"
		:data="schemas"
		:caption="$i18n( 'neowiki-special-schemas' ).text()"
		:hide-caption="true"
	>
		<template #item-name="{ row }">
			<div class="ext-neowiki-schemas-table__cell">
				<a
					:id="nameId( row.name )"
					class="ext-neowiki-schemas-table__name"
					:href="pageUrl( `Schema:${ row.name }` )"
					:title="row.name"
				>{{ row.name }}</a>
				<CdxButton
					v-if="canEdit"
					weight="quiet"
					:aria-label="$i18n( 'neowiki-edit-schema' ).text()"
					:aria-describedby="nameId( row.name )"
					:title="$i18n( 'neowiki-edit-schema' ).text()"
					@click="emit( 'edit', row.name )"
				>
					<CdxIcon :icon="cdxIconEdit" />
				</CdxButton>
				<CdxButton
					v-if="canDelete"
					weight="quiet"
					action="destructive"
					:aria-label="$i18n( 'neowiki-schema-delete' ).text()"
					:aria-describedby="nameId( row.name )"
					:title="$i18n( 'neowiki-schema-delete' ).text()"
					@click="emit( 'delete', row.name )"
				>
					<CdxIcon :icon="cdxIconTrash" />
				</CdxButton>
			</div>
		</template>

		<template #item-description="{ item }">
			<span class="ext-neowiki-schemas-table__description">{{ item }}</span>
		</template>

		<template #item-subjects="{ row }">
			<div class="ext-neowiki-schemas-table__cell">
				<span
					v-if="subjectListAvailable && subjectCountPending"
					class="ext-neowiki-schemas-table__subject-count"
				/>
				<a
					v-else-if="subjectListAvailable"
					class="ext-neowiki-schemas-table__subject-count"
					:href="pageUrl( `Special:Subjects/${ row.name }` )"
					:aria-describedby="nameId( row.name )"
				>{{ subjectListLinkText( subjectCountOf( row.name ) ) }}</a>
				<CdxButton
					v-if="canCreateSubject"
					weight="quiet"
					action="progressive"
					@click="emit( 'create-subject', row.name )"
				>
					<CdxIcon :icon="cdxIconAdd" />
					<span class="ext-neowiki-schemas-table__create-label">
						{{ $i18n( 'neowiki-schema-create-subject', row.name ).text() }}
					</span>
				</CdxButton>
			</div>
		</template>
	</CdxTable>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { CdxButton, CdxIcon, CdxTable, type TableColumn, useGeneratedId } from '@wikimedia/codex';
import { cdxIconAdd, cdxIconEdit, cdxIconTrash } from '@wikimedia/codex-icons';
import { type SubjectCounts, subjectListLinkText } from '@/composables/useSubjectCounts.ts';
import type { SchemaSummary } from '@/application/SchemaLookup.ts';

const props = defineProps<{
	schemas: SchemaSummary[];
	canEdit: boolean;
	canDelete: boolean;
	canCreateSubject: boolean;
	subjectListAvailable: boolean;
	subjectCountOf: SubjectCounts['subjectCountOf'];
	subjectCountPending: boolean;
}>();

const emit = defineEmits<{
	edit: [ schemaName: string ];
	delete: [ schemaName: string ];
	'create-subject': [ schemaName: string ];
}>();

const idPrefix = useGeneratedId( 'ext-neowiki-schemas-table' );

const columns = computed( (): TableColumn[] => [
	{ id: 'name', label: mw.msg( 'neowiki-schemas-column-name' ) },
	{ id: 'description', label: mw.msg( 'neowiki-schemas-column-description' ) },
	// The card's footer holds the same two, under the same condition.
	...( props.subjectListAvailable || props.canCreateSubject ?
		[ { id: 'subjects', label: mw.msg( 'neowiki-schemas-column-subjects' ) } ] :
		[] )
] );

// Names the Schema to screen readers on its row's buttons and link. Encoded: an id holds no spaces, a name can.
function nameId( schemaName: string ): string {
	return `${ idPrefix }-${ encodeURIComponent( schemaName ) }`;
}

function pageUrl( page: string ): string {
	return mw.util.getUrl( page );
}
</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';

.ext-neowiki-schemas-table {
	// Middle-aligned, so a row's text lines up with its taller buttons. Scoped under the table to outrank Codex's top
	// alignment, which MediaWiki loads after this.
	.cdx-table__table td {
		vertical-align: middle;
	}

	&__cell {
		display: flex;
		align-items: center;
		gap: @spacing-50;
		white-space: nowrap;
	}

	// Sends the buttons acting on the row to the cell's end, where they line up across the rows.
	&__name,
	&__subject-count {
		margin-inline-end: auto;
	}

	// Caps a long name, so its row still fits the page.
	&__name,
	&__create-label {
		max-width: 12em;
		overflow: hidden;
		text-overflow: ellipsis;
	}

	// Holds a two-digit count's width while the counts load, so the column does not widen when they arrive.
	&__subject-count {
		min-width: 5.5em;
	}

	// One line per Schema: the description gives way, cut with an ellipsis.
	&__description {
		display: -webkit-box;
		-webkit-box-orient: vertical;
		-webkit-line-clamp: 1;
		overflow: hidden;
		-webkit-hyphens: none;
		hyphens: none;
	}
}
</style>
