<h1 class="ai-module__result-title">Odpověď</h1>

<div class="ai-result" data-flag="results">
	{for $response as $item on $index}

		<div class="ai-result__item" data-index="{$index}">
			{for $item as $value on $key}

				<div class="ai-result__wrapper" data-key="{$key}" data-index="{$index}">
					<h2 class="ai-result__label">{$key}</h2>
					<p class="ai-result__content" data-flag="result-content">{$value}</p>

					{include Components/Handlers.tpl, key => $key, counter => $index}
				</div>
			{/for}
		</div>
	{/for}
</div>