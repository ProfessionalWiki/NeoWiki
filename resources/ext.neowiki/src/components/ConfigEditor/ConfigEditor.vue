<template>
	<div class="ext-neowiki-config-editor">
		<p v-if="props.canEdit">
			{{ $i18n( 'neowiki-config-editor-intro' ).text() }}
		</p>
		<p v-else>
			{{ $i18n( 'neowiki-config-editor-read-only' ).text() }}
		</p>

		<div class="ext-neowiki-config-editor__setting">
			<CdxField
				ref="wikiTypeField"
				:is-fieldset="true"
				:disabled="!props.canEdit"
			>
				<template #label>
					{{ wikiTypeLabel }}
				</template>
				<template #description>
					<button
						v-if="canReset( 'subjectFirst' )"
						type="button"
						class="ext-neowiki-config-editor__reset"
						:aria-label="$i18n( 'neowiki-config-editor-reset-label', wikiTypeLabel ).text()"
						@click="reset( 'subjectFirst' )"
					>
						{{ $i18n( 'neowiki-config-editor-reset' ).text() }}
					</button>
				</template>
				<CdxRadio
					v-model="subjectFirst"
					:input-value="false"
					name="ext-neowiki-config-editor-wiki-type"
				>
					{{ $i18n( 'neowiki-config-setting-subject-first-page-first' ).text() }}
					<template #description>
						{{ $i18n( 'neowiki-config-setting-subject-first-page-first-help' ).text() }}
					</template>
				</CdxRadio>
				<CdxRadio
					v-model="subjectFirst"
					:input-value="true"
					name="ext-neowiki-config-editor-wiki-type"
				>
					{{ $i18n( 'neowiki-config-setting-subject-first-subject-first' ).text() }}
					<template #description>
						{{ $i18n( 'neowiki-config-setting-subject-first-subject-first-help' ).text() }}
					</template>
				</CdxRadio>
			</CdxField>
		</div>

		<div class="ext-neowiki-config-editor__setting">
			<LanguageListInput
				ref="languageList"
				v-model="dataLanguages"
				:label="dataLanguagesLabel"
				:disabled="!props.canEdit"
			>
				<template #description>
					<span class="ext-neowiki-config-editor__help">{{ $i18n( 'neowiki-config-setting-data-languages-help' ).text() }}</span>
					<button
						v-if="canReset( 'dataLanguages' )"
						type="button"
						class="ext-neowiki-config-editor__reset"
						:aria-label="$i18n( 'neowiki-config-editor-reset-label', dataLanguagesLabel ).text()"
						@click="reset( 'dataLanguages' )"
					>
						{{ $i18n( 'neowiki-config-editor-reset' ).text() }}
					</button>
				</template>
			</LanguageListInput>
		</div>

		<div class="ext-neowiki-config-editor__setting">
			<CdxCheckbox
				ref="enforcementCheckbox"
				v-model="enforceValidation"
				:disabled="!props.canEdit"
			>
				{{ enforcementLabel }}
				<template #description>
					<button
						v-if="canReset( 'enforceValidation' )"
						type="button"
						class="ext-neowiki-config-editor__reset"
						:aria-label="$i18n( 'neowiki-config-editor-reset-label', enforcementLabel ).text()"
						@click="reset( 'enforceValidation' )"
					>
						{{ $i18n( 'neowiki-config-editor-reset' ).text() }}
					</button>
				</template>
			</CdxCheckbox>
		</div>

		<div class="ext-neowiki-config-editor__setting">
			<CdxCheckbox
				ref="infoboxCheckbox"
				v-model="autoRenderMainSubject"
				:disabled="!props.canEdit"
			>
				{{ infoboxLabel }}
				<template #description>
					<button
						v-if="canReset( 'autoRenderMainSubject' )"
						type="button"
						class="ext-neowiki-config-editor__reset"
						:aria-label="$i18n( 'neowiki-config-editor-reset-label', infoboxLabel ).text()"
						@click="reset( 'autoRenderMainSubject' )"
					>
						{{ $i18n( 'neowiki-config-editor-reset' ).text() }}
					</button>
				</template>
			</CdxCheckbox>
		</div>

		<div
			v-if="props.canEdit"
			class="ext-neowiki-config-editor__save"
		>
			<CdxField :optional="true">
				<template #label>
					{{ $i18n( 'neowiki-edit-summary-label' ).text() }}
				</template>
				<CdxTextInput
					v-model="summary"
					:placeholder="$i18n( 'neowiki-edit-summary-placeholder' ).text()"
				/>
			</CdxField>
			<CdxButton
				action="progressive"
				weight="primary"
				:disabled="!changed || saving || changedMeanwhile"
				@click="save"
			>
				{{ $i18n( 'neowiki-config-editor-save' ).text() }}
			</CdxButton>
			<CdxMessage
				v-if="saveFailure !== null"
				type="error"
			>
				{{ $i18n( 'neowiki-config-editor-save-failed', saveFailure ).text() }}
			</CdxMessage>
			<CdxMessage
				v-if="changedMeanwhile"
				type="warning"
			>
				{{ $i18n( 'neowiki-config-editor-changed-meanwhile' ).text() }}
			</CdxMessage>
		</div>
	</div>
</template>

<script setup lang="ts">
import { computed, nextTick, ref } from 'vue';
import type { WritableComputedRef } from 'vue';
import { CdxButton, CdxCheckbox, CdxField, CdxMessage, CdxRadio, CdxTextInput } from '@wikimedia/codex';
import LanguageListInput from '@/components/ConfigEditor/LanguageListInput.vue';
import {
	type ConfigEditorJsConfig,
	type ConfigValues,
	type PageValues,
	pageSource,
	sameJson
} from '@/components/ConfigEditor/configEditing.ts';
import type { PageRevisionSaver } from '@/persistence/PageSaver.ts';

const props = defineProps<ConfigEditorJsConfig & {
	canEdit: boolean;
	pageName: string;
	pageSaver: PageRevisionSaver;
}>();

const wikiTypeLabel = mw.message( 'neowiki-config-setting-subject-first' ).text();
const dataLanguagesLabel = mw.message( 'neowiki-config-setting-data-languages' ).text();
const enforcementLabel = mw.message( 'neowiki-config-setting-enforce-validation' ).text();
const infoboxLabel = mw.message( 'neowiki-config-setting-auto-render-main-subject' ).text();

const values = ref<PageValues>( { ...props.pageValues } );
const subjectFirst = valueInEffect( 'subjectFirst' );
const dataLanguages = valueInEffect( 'dataLanguages' );
const enforceValidation = valueInEffect( 'enforceValidation' );
const autoRenderMainSubject = valueInEffect( 'autoRenderMainSubject' );
const source = computed( (): string => pageSource( values.value ) );
const savedSource = ref( source.value );
const savedRevisionId = ref( props.revisionId );
const summary = ref( '' );
const saving = ref( false );
const saveFailure = ref<string | null>( null );
// MediaWiki merges a change saved since the form loaded into a save where it can. The form does not
// show that change, and a further save from it would undo it.
const changedMeanwhile = ref( false );

const wikiTypeField = ref<InstanceType<typeof CdxField> | null>( null );
const languageList = ref<InstanceType<typeof LanguageListInput> | null>( null );
const enforcementCheckbox = ref<InstanceType<typeof CdxCheckbox> | null>( null );
const infoboxCheckbox = ref<InstanceType<typeof CdxCheckbox> | null>( null );

const changed = computed( (): boolean => source.value !== savedSource.value );

/**
 * The value the page sets, or else the one LocalSettings.php gives. Changing it sets it on the page.
 */
function valueInEffect<Key extends keyof ConfigValues>( key: Key ): WritableComputedRef<ConfigValues[ Key ]> {
	return computed( {
		get: () => ( values.value[ key ] ?? props.localSettingsValues[ key ] ) as ConfigValues[ Key ],
		set: ( value: ConfigValues[ Key ] ): void => {
			values.value[ key ] = value;
		}
	} );
}

function canReset( key: keyof ConfigValues ): boolean {
	return props.canEdit && values.value[ key ] !== null;
}

const focusSetting: Record<keyof ConfigValues, () => void> = {
	subjectFirst: () => inputOf( wikiTypeField.value, 'input:checked' )?.focus(),
	dataLanguages: () => languageList.value?.focus(),
	enforceValidation: () => inputOf( enforcementCheckbox.value, 'input' )?.focus(),
	autoRenderMainSubject: () => inputOf( infoboxCheckbox.value, 'input' )?.focus()
};

/**
 * The link that did this goes away with it, so the focus moves to the setting.
 */
async function reset( key: keyof ConfigValues ): Promise<void> {
	values.value[ key ] = null;

	await nextTick();
	focusSetting[ key ]();
}

function inputOf( control: { $el: unknown } | null, selector: string ): HTMLInputElement | null | undefined {
	return ( control?.$el as HTMLElement | undefined )?.querySelector<HTMLInputElement>( selector );
}

async function save(): Promise<void> {
	saving.value = true;
	saveFailure.value = null;

	const sentSource = source.value;
	const status = await props.pageSaver.saveRevision(
		props.pageName,
		sentSource,
		summary.value,
		'json',
		savedRevisionId.value
	);

	saving.value = false;

	if ( status.success ) {
		savedSource.value = sentSource;
		savedRevisionId.value = status.revisionId;
		summary.value = '';
		changedMeanwhile.value = !sameJson( status.source, sentSource );
		mw.notify( mw.message( 'neowiki-config-editor-saved' ).text(), { type: 'success' } );
	} else {
		saveFailure.value = status.message;
	}
}
</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';
@import ( reference ) '@/assets/mixins.less';

.ext-neowiki-config-editor {
	max-width: @size-4000;

	&__setting {
		margin-block: @spacing-100;
		border: @border-width-base @border-style-base @border-color-subtle;
		border-radius: @border-radius-base;
		padding: @spacing-75;
		background-color: @background-color-neutral-subtle;

		/* Its own top margin would only add to the box's padding. Doubled to outrank Codex's rule,
			which loads later at the same specificity. */
		> .cdx-field.cdx-field {
			margin-top: 0;
		}
	}

	&__help {
		display: block;
	}

	&__reset {
		.ext-neowiki-link-button();
		display: block;
	}

	&__save {
		display: flex;
		flex-direction: column;
		gap: @spacing-75;
		margin-block-start: @spacing-200;

		.cdx-field {
			margin: 0;
		}

		> .cdx-button {
			align-self: flex-start;
		}
	}
}
</style>
