# Config rules (summary)

## Version
- Root object may contain "version" (string). Current version: "1".
- If missing, migrator 'legacy' will be attempted (if registered).

## blocks.json
- Root key: "blocks": object
- Each block: object with required keys:
  - id (string)
  - name (string)
  - type (string) — supported: 'select', 'radio_group', 'checkbox', 'text', ...
- If block contains "options":
  - options must be non-empty array
  - each option must be object with keys "value" (string) and "label" (string)
  - default is required when options exist
  - for 'select' and 'radio_group', default must be one of option.value
- If type === 'checkbox':
  - default is required and must be boolean

## layouts.json
- Root key: "layouts": object
- Each layout must contain non-empty "sections" array
- Each section must have "id" (string) and non-empty "blocks" array
- Each block in "blocks" MUST be a ref object: { "ref": "<blockId>" }

## generators.json
- Root key: "generators": object
- Each generator must contain keys: id, label, layout_ref
- There must be a generator with key "subject"

## Cross checks
- Each generator.layout_ref must exist in layouts
- Each layout block ref must exist in blocks



## Architecture notes

- Parser and TemplateEngine are reusable components
- Handler and FieldFactory are application-specific modules
- App layer wires everything together