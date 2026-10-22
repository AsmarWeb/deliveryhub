@push('scripts')
<script>
document.querySelectorAll('[data-copy-value]').forEach(button => {
    button.addEventListener('click', async () => {
        const status = document.getElementById('integration-copy-status');
        try {
            await navigator.clipboard.writeText(button.dataset.copyValue);
            if (status) status.textContent = @json($t('تم النسخ.','Copied.'));
        } catch {
            if (status) status.textContent = @json($t('تعذر النسخ التلقائي. حدّد الرابط وانسخه يدويًا.','Could not copy automatically. Select and copy the URL manually.'));
        }
    });
});
</script>
@endpush
