<div class="pb-module" data-controller="layout" data-api-url="{$apiUrl}">
    <h1 class="pb-module__title">{$title}</h1>
    <form method="POST" class="pb-module__form">
		{if $signedParams}
			<input type="hidden" name="__ai_signed" value="{$signedParams}">
		{else}
			{for $params as $pairs on $namespace}
				{for $pairs as $value on $key}
					<input type="hidden" name="__ai[{$namespace}][{$key}]" value="{$value}">
				{/for}
			{/for}
		{/if}

        {for $data as $section}
            <div class="pb-module__wrapper">
				<div class="pb-module__section" id="{$section.id}"{if $section.columns} data-layout-columns="{$section.columns}"{/if}{if $section.column_template} data-layout-column-template="{$section.column_template}"{/if}>
					{$section.html}
				</div>
			</div>
        {/for}

		<button class="pb-module__button pb-module__button--primary" data-action="send-request">
			<svg class="pb-result__icon"><use href="#pb-use"></use></svg>
			GENEROVAT TEXT
		</button>
	</form>

	<div class="pb-module__result" data-component="pb-result"></div>
</div>

{_require /Components/Icons.tpl}
