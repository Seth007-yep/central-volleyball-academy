# Shared Design System

The public homepage introduced the initial yellow-and-blue palette, spacing scale, and responsive layout. The staff login and admin shell now use the reusable components documented here. The exact brand color values remain provisional until the academy supplies its official branding.

## Where the styles live

Shared tokens and components are in [`public/assets/css/site.css`](../public/assets/css/site.css). Keep site-wide values there instead of adding one-off inline styles to PHP views.

## Tokens

The `:root` block defines colors, spacing, content width, and the small corner radius. Use a token when spacing or color is repeated. Change the token once when the academy confirms its official palette.

Examples:

```css
.my-section {
    padding-block: var(--space-7);
    color: var(--color-navy);
    background: var(--color-surface);
}
```

## Common components

- Buttons use `.button`; choose `.button-secondary` or `.button-accent` when their meaning calls for a quieter or highlighted action.
- Forms use `.form-grid`, `.field`, `.field-label`, and optional `.field-hint` or `.field-error`. Keep a real `<label>` connected to every input.
- Notices use `.notice` plus a state such as `.notice-success`, `.notice-warning`, or `.notice-danger`. Add `role="status"` for a normal update or `role="alert"` when immediate announcement is needed.
- Status text uses `.status-label` and an optional state class. Include the status word in the HTML so meaning does not depend on color.
- Tables use `.table-scroll` around `.data-table`. Provide column headings with `<th scope="col">` and a useful `<caption>`.
- Dialogs should use the native `<dialog>` element and its browser `showModal()` behavior. The CSS styles its presentation; JavaScript that opens a dialog still needs to manage the user flow and focus appropriately.

Example form field:

```html
<div class="field">
    <label class="field-label" for="email">Email address</label>
    <input id="email" name="email" type="email" autocomplete="email" required>
    <p class="field-hint">Use the address associated with your staff account.</p>
</div>
```

## Accessibility and responsive behavior

- Interactive controls have a visible keyboard focus outline.
- Form labels remain visible instead of relying on placeholder text.
- Status and notice styles use text plus color, not color alone.
- Narrow screens collapse form grids to one column. Wide tables scroll inside their wrapper so the page itself does not overflow horizontally.
- Reduced-motion preferences are respected by the existing global rule.

These are presentation primitives only. They do not validate forms, announce messages automatically, authorize actions, or make a dialog accessible by themselves. Those behaviors belong in the feature that uses each component.
