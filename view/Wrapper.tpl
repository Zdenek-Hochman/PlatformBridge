<link rel="stylesheet" href="./main.min.css">
<script src="./main.js"></script>

<div class="ai-wrapper">
    <h1 class="ai-wrapper__title">{$title}</h1>
    <form method="POST" class="ai-form">
        {for $params as $value on $key}
            <input type="hidden" name="__ai[{$key}]" value="{$value}">
        {/for}

        {for $data as $section}
            <div class="ai-form__section" id="{$section.id}">
                {$section.html}
            </div>
        {/for}

		<button class="ai-generate-button" id="generateButton">GENEROVAT TEXT</button>
	</form>

	<div class="generator-result"></div>
</div>