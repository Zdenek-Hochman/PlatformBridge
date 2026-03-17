<h1 class="pb-module__result-title">Odpověď</h1>

<div class="pb-result" data-flag="results">
	{for $response as $item on $index}

		<div class="pb-result__item" data-index="{$index}">
			{for $item as $value on $key}

				<div class="pb-result__wrapper" data-key="{$key}" data-index="{$index}">
					<h2 class="pb-result__label">{$key}</h2>
					<p class="pb-result__content" data-flag="result-content">{$value}</p>

					{include Components/Handlers.tpl, key => $key, counter => $index}
				</div>
			{/for}
		</div>
	{/for}
</div>