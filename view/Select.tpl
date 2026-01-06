<select id="{$id}" name="{$name}" class="ai-form__select {$class}" {$disabled} {$required}>
    {for $options as $option on $key}
        <option value="{$option.value}" {if $option.selected}selected{/if}>{$option.label}</option>
    {/for}
</select>