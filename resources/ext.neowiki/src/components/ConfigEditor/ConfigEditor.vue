<template>
	<div class="ext-neowiki-config-editor">
		<p v-if="props.canEdit">
			{{ $i18n( 'neowiki-config-editor-intro' ).text() }}
		</p>
		<p v-else>
			{{ $i18n( 'neowiki-config-editor-read-only' ).text() }}
		</p>

		<div
			v-for="setting in props.settings"
			:key="setting.key"
			class="ext-neowiki-config-editor__setting"
		>
			<component
				:is="setting.choices.length > 0 ? ChoiceSettingInput : CONTROLS[ setting.type ]"
				v-bind="setting.choices.length > 0 ? { choices: setting.choices } : {}"
				:ref="( control: unknown ) => rememberControl( setting.key, control )"
				:model-value="valueInEffect( setting )"
				:label="setting.label"
				:disabled="!props.canEdit"
				@update:model-value="( value: unknown ) => change( setting, value )"
			>
				<template #description>
					<span
						v-if="setting.help !== null"
						class="ext-neowiki-config-editor__help"
					>{{ setting.help }}</span>
					<button
						v-if="props.canEdit && isSet( setting )"
						type="button"
						class="ext-neowiki-config-editor__reset"
						:aria-label="$i18n( 'neowiki-config-editor-reset-label', setting.label ).text()"
						@click="reset( setting )"
					>
						{{ $i18n( 'neowiki-config-editor-reset' ).text() }}
					</button>
				</template>
			</component>
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
import type { Component } from 'vue';
import { CdxButton, CdxField, CdxMessage, CdxTextInput } from '@wikimedia/codex';
import BooleanSettingInput from '@/components/ConfigEditor/BooleanSettingInput.vue';
import ChoiceSettingInput from '@/components/ConfigEditor/ChoiceSettingInput.vue';
import LanguageListInput from '@/components/ConfigEditor/LanguageListInput.vue';
import {
	type ConfigEditorJsConfig,
	type ConfigSetting,
	pageSource,
	pageValuesOf,
	sameJson
} from '@/components/ConfigEditor/configEditing.ts';
import type { PageRevisionSaver } from '@/persistence/PageSaver.ts';

/**
 * The control for each type of value, unless the setting names its values, which ChoiceSettingInput
 * offers. Each takes the setting's label, a description slot and `disabled`, binds the value with
 * v-model, and exposes focus().
 */
const CONTROLS: Record<string, Component> = {
	boolean: BooleanSettingInput,
	languageList: LanguageListInput
};

const props = defineProps<ConfigEditorJsConfig & {
	canEdit: boolean;
	pageName: string;
	pageSaver: PageRevisionSaver;
}>();

const values = ref<Record<string, unknown>>( pageValuesOf( props.settings ) );
const source = computed( (): string => pageSource( props.settings, values.value ) );
const savedSource = ref( source.value );
const savedRevisionId = ref( props.revisionId );
const summary = ref( '' );
const saving = ref( false );
const saveFailure = ref<string | null>( null );
// MediaWiki merges a change saved since the form loaded into a save where it can. The form does not
// show that change, and a further save from it would undo it.
const changedMeanwhile = ref( false );

const controls = new Map<string, { focus(): void }>();

const changed = computed( (): boolean => source.value !== savedSource.value );

function rememberControl( key: string, control: unknown ): void {
	if ( control !== null ) {
		controls.set( key, control as { focus(): void } );
	}
}

function isSet( setting: ConfigSetting ): boolean {
	return values.value[ setting.key ] !== null;
}

function valueInEffect( setting: ConfigSetting ): unknown {
	return isSet( setting ) ? values.value[ setting.key ] : setting.localSettingsValue;
}

function change( setting: ConfigSetting, value: unknown ): void {
	values.value[ setting.key ] = value;
}

/**
 * The link that did this goes away with it, so the focus moves to the setting.
 */
async function reset( setting: ConfigSetting ): Promise<void> {
	values.value[ setting.key ] = null;

	await nextTick();
	controls.get( setting.key )?.focus();
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
