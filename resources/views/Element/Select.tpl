<select id="{$id}" name="{$name}" class="ai-module__field {$class}" {$disabled} {$required}>
    {for $options as $option on $key}
        <option value="{$option.value}" {if $option.selected}selected{/if}>{$option.label}</option>
    {/for}
</select>