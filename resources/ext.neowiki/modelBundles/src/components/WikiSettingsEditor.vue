<template>
	<div class="ext-neowiki-wiki-settings">
		<p>
			{{ config.canEditSettings ?
				$i18n( 'neowiki-bundle-setup-settings-intro' ).text() :
				$i18n( 'neowiki-bundle-setup-settings-read-only' ).text() }}
		</p>

		<CdxField
			:is-fieldset="true"
			:disabled="!config.canEditSettings"
		>
			<template #label>
				{{ $i18n( 'neowiki-config-setting-subject-first' ).text() }}
			</template>
			<CdxRadio
				v-model="subjectFirst"
				:input-value="false"
				name="ext-neowiki-wiki-settings-type"
			>
				{{ $i18n( 'neowiki-config-setting-subject-first-page-first' ).text() }}
				<template #description>
					{{ $i18n( 'neowiki-bundle-setup-page-first-help' ).text() }}
				</template>
			</CdxRadio>
			<CdxRadio
				v-model="subjectFirst"
				:input-value="true"
				name="ext-neowiki-wiki-settings-type"
			>
				{{ $i18n( 'neowiki-config-setting-subject-first-subject-first' ).text() }}
				<template #description>
					{{ $i18n( 'neowiki-bundle-setup-subject-first-help' ).text() }}
				</template>
			</CdxRadio>
		</CdxField>

		<LanguageListInput
			v-model="dataLanguages"
			:label="$i18n( 'neowiki-config-setting-data-languages' ).text()"
			:disabled="!config.canEditSettings"
		>
			<template #description>
				{{ $i18n( 'neowiki-config-setting-data-languages-help' ).text() }}
			</template>
		</LanguageListInput>

		<div class="ext-neowiki-wiki-settings__actions">
			<CdxButton
				class="ext-neowiki-wiki-settings__continue"
				action="progressive"
				weight="primary"
				@click="emit( 'continue' )"
			>
				{{ $i18n( 'neowiki-bundle-setup-continue' ).text() }}
			</CdxButton>
			<!-- The way out, beside Continue. -->
			<slot name="actions" />
		</div>
	</div>
</template>

<script setup lang="ts">
import { computed, type WritableComputedRef } from 'vue';
import { CdxButton, CdxField, CdxRadio } from '@wikimedia/codex';
import { LanguageListInput, type ConfigValues, type PageValues } from 'ext.neowiki';
import { setupConfig } from '../presentation/setupConfig.ts';
import { valueInEffect } from '../presentation/wikiSettings.ts';

// What MediaWiki:NeoWiki will set, which installing saves.
const props = defineProps<{
	modelValue: PageValues;
}>();

const emit = defineEmits<{
	'update:modelValue': [ values: PageValues ];
	continue: [];
}>();

const config = setupConfig();
const subjectFirst = settingInEffect( 'subjectFirst' );
const dataLanguages = settingInEffect( 'dataLanguages' );

/**
 * Changing a setting sets it on the page, and what is not changed stays as it is.
 */
function settingInEffect<Key extends keyof ConfigValues>( key: Key ): WritableComputedRef<ConfigValues[ Key ]> {
	return computed( {
		get: () => valueInEffect( props.modelValue, key ),
		set: ( value: ConfigValues[ Key ] ): void => {
			emit( 'update:modelValue', { ...props.modelValue, [ key ]: value } );
		}
	} );
}
</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';

.ext-neowiki-wiki-settings {
	&__actions {
		display: flex;
		flex-wrap: wrap;
		gap: @spacing-50;
		margin-top: @spacing-150;
	}
}
</style>
