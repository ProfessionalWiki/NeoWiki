<template>
	<section class="ext-neowiki-schema-card">
		<div class="ext-neowiki-schema-card__body">
			<div class="ext-neowiki-schema-card__head">
				<h2
					:id="headingId"
					class="ext-neowiki-schema-card__name"
				>
					<a :href="schemaUrl">{{ summary.name }}</a>
				</h2>
				<span
					v-if="canEdit || canDelete"
					class="ext-neowiki-schema-card__actions"
				>
					<CdxButton
						v-if="canEdit"
						weight="quiet"
						:aria-label="$i18n( 'neowiki-edit-schema' ).text()"
						:aria-describedby="headingId"
						:title="$i18n( 'neowiki-edit-schema' ).text()"
						@click="emit( 'edit' )"
					>
						<CdxIcon :icon="cdxIconEdit" />
					</CdxButton>
					<CdxButton
						v-if="canDelete"
						weight="quiet"
						action="destructive"
						:aria-label="$i18n( 'neowiki-schema-delete' ).text()"
						:aria-describedby="headingId"
						:title="$i18n( 'neowiki-schema-delete' ).text()"
						@click="emit( 'delete' )"
					>
						<CdxIcon :icon="cdxIconTrash" />
					</CdxButton>
				</span>
			</div>

			<p
				v-if="summary.description !== ''"
				class="ext-neowiki-schema-card__description"
			>
				{{ summary.description }}
			</p>

			<template v-if="subjectListAvailable">
				<ul
					v-if="subjects.length > 0"
					class="ext-neowiki-schema-card__subjects"
				>
					<li
						v-for="subject in subjects"
						:key="subject.id"
					>
						<SubjectSummaryCell
							column="name"
							:summary="subject"
						/>
						<SubjectSummaryCell
							column="edited"
							:summary="subject"
						/>
					</li>
				</ul>
				<p
					v-else-if="subjectsState !== 'loading'"
					class="ext-neowiki-schema-card__note"
				>
					{{ subjectsNote }}
				</p>
			</template>
		</div>

		<div
			v-if="subjectListAvailable || canCreateSubject"
			class="ext-neowiki-schema-card__footer"
		>
			<a
				v-if="subjectListAvailable"
				:href="subjectListUrl"
			>{{ $i18n( 'neowiki-subjects-view-all' ).text() }}</a>
			<CdxButton
				v-if="canCreateSubject"
				class="ext-neowiki-schema-card__create"
				weight="quiet"
				action="progressive"
				@click="emit( 'create-subject' )"
			>
				<CdxIcon :icon="cdxIconArticleAdd" />
				{{ $i18n( 'neowiki-schema-create-subject', summary.name ).text() }}
			</CdxButton>
		</div>
	</section>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { CdxButton, CdxIcon, useGeneratedId } from '@wikimedia/codex';
import { cdxIconArticleAdd, cdxIconEdit, cdxIconTrash } from '@wikimedia/codex-icons';
import SubjectSummaryCell from '@/components/SubjectsTable/SubjectSummaryCell.vue';
import { NeoWikiServices } from '@/NeoWikiServices.ts';
import type { SchemaSummary } from '@/application/SchemaLookup.ts';
import type { SubjectSummary } from '@/application/SubjectSummaryLookup.ts';

const NEWEST_SUBJECT_COUNT = 3;

const props = defineProps<{
	summary: SchemaSummary;
	canEdit: boolean;
	canDelete: boolean;
	canCreateSubject: boolean;
	/** False on a wiki without the Graph Store the Subject list reads. */
	subjectListAvailable: boolean;
}>();

const emit = defineEmits<{
	edit: [];
	delete: [];
	'create-subject': [];
}>();

// Names the Schema to screen readers on the card's buttons, whose labels are the same on every card.
const headingId = useGeneratedId( 'ext-neowiki-schema-card' );

const subjects = ref<SubjectSummary[]>( [] );
const subjectsState = ref<'loading' | 'loaded' | 'failed'>( 'loading' );

const schemaUrl = computed( () => mw.util.getUrl( `Schema:${ props.summary.name }` ) );
const subjectListUrl = computed( () => mw.util.getUrl( `Special:Subjects/${ props.summary.name }` ) );
const subjectsNote = computed( () => subjectsState.value === 'failed' ?
	mw.msg( 'neowiki-subjects-load-error' ) :
	mw.msg( 'neowiki-subjects-empty-schema', props.summary.name ) );

onMounted( async () => {
	if ( !props.subjectListAvailable ) {
		return;
	}

	try {
		subjects.value = ( await NeoWikiServices.getSubjectSummaryLookup().getSubjectSummaries( {
			schema: props.summary.name,
			search: '',
			sort: 'newest',
			direction: 'desc',
			cursor: null,
			limit: NEWEST_SUBJECT_COUNT
		} ) ).subjects;
		subjectsState.value = 'loaded';
	} catch {
		subjectsState.value = 'failed';
	}
} );
</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';

.ext-neowiki-schema-card {
	display: flex;
	flex-direction: column;
	border: @border-width-base @border-style-base @border-color-subtle;
	border-radius: @border-radius-base;
	background-color: @background-color-base;

	&__body {
		flex-grow: 1;
		padding: @spacing-75 @spacing-100;
	}

	&__head {
		display: flex;
		align-items: flex-start;
		justify-content: space-between;
		gap: @spacing-50;
	}

	// A card title, not the skin's section heading with its serif face and rule; scoped under the
	// card to outrank the skin's content-heading rules.
	& &__name {
		margin: @spacing-25 0 0;
		padding: 0;
		border: 0;
		font-family: inherit;
		font-size: @font-size-large;
		font-weight: @font-weight-bold;
		line-height: @line-height-small;
		overflow-wrap: anywhere;
	}

	&__actions {
		display: flex;
		flex-shrink: 0;
		margin-inline-end: -@spacing-50;
	}

	&__description {
		display: -webkit-box;
		-webkit-box-orient: vertical;
		-webkit-line-clamp: 2;
		overflow: hidden;
		margin: @spacing-25 0 0;
		color: @color-subtle;
	}

	&__subjects {
		margin: @spacing-75 0 0;
		padding: 0;
		list-style: none;

		li {
			display: flex;
			justify-content: space-between;
			gap: @spacing-75;
			margin: 0;
			padding: @spacing-35 0;
			border-top: @border-width-base @border-style-base @border-color-muted;

			a {
				min-width: 0;
				overflow: hidden;
				text-overflow: ellipsis;
				white-space: nowrap;
			}

			time {
				flex-shrink: 0;
				color: @color-subtle;
			}
		}
	}

	&__note {
		margin: @spacing-75 0 0;
		padding-top: @spacing-50;
		border-top: @border-width-base @border-style-base @border-color-muted;
		color: @color-subtle;
	}

	&__footer {
		display: flex;
		flex-wrap: wrap;
		align-items: center;
		justify-content: space-between;
		gap: @spacing-50;
		min-height: @size-275;
		padding: @spacing-25 @spacing-50 @spacing-25 @spacing-100;
		border-top: @border-width-base @border-style-base @border-color-muted;

		a {
			white-space: nowrap;
		}
	}

	// Scoped under the footer to outrank `.cdx-button`'s margin, which MediaWiki's Codex loads after this.
	&__footer &__create {
		margin-inline-start: auto;
	}
}
</style>
