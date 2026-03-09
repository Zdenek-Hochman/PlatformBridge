<div class="ai-module" data-controller="layout">
    <h1 class="ai-module__title">{$title}</h1>
    <form method="POST" class="ai-module__form">
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
            <div class="ai-module__wrapper">
				<div class="ai-module__section" id="{$section.id}"{if $section.columns} data-layout-columns="{$section.columns}"{/if}{if $section.column_template} data-layout-column-template="{$section.column_template}"{/if}>
					{$section.html}
				</div>
			</div>
        {/for}

		<button class="ai-module__button ai-module__button--primary" data-action="send-request">
			<svg class="ai-result__icon"><use href="#ai-use"></use></svg>
			GENEROVAT TEXT
		</button>
	</form>

	<div class="ai-module__result" data-component="ai-result"></div>
</div>

{_require /Components/Icons.tpl}
