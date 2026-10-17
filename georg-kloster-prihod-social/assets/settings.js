document.addEventListener('DOMContentLoaded', () => {
    const enabled = document.querySelector('input[name="enabled"]');
    const token = document.querySelector('input[name="token"]');
    const target = document.querySelector('input[name="target"]');
    const clear = document.querySelector('input[name="clear_token"]');
    if (!enabled || !token || !target || !clear) return;
    const update = () => {
        token.required = enabled.checked && (!prihodTelegramSettings.hasToken || clear.checked);
        target.required = enabled.checked;
        token.setAttribute('aria-required', String(token.required));
        target.setAttribute('aria-required', String(target.required));
    };
    enabled.addEventListener('change', update); clear.addEventListener('change', update); update();
});
