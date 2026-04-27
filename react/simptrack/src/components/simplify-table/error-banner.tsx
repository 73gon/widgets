import { useSimplifyTable } from '@/lib/simplify-table-context';

export function ErrorBanner() {
  const { state, dismissError } = useSimplifyTable();
  const err = state.error;
  if (!err) return null;

  const hint = err.hint ?? undefined;
  const detailEntries = err.details ? Object.entries(err.details) : [];

  return (
    <div role='alert' className='mb-3 rounded-md border border-red-500/40 bg-red-500/10 text-red-900 dark:text-red-100 p-3 text-sm'>
      <div className='flex items-start gap-3'>
        <div className='flex-1 min-w-0'>
          <div className='font-semibold flex items-center gap-2'>
            <span>Fehler</span>
            {err.rule && <span className='text-xs font-mono px-1.5 py-0.5 rounded bg-red-500/20'>{err.rule}</span>}
          </div>
          <div className='mt-1 wrap-break-word'>{err.message}</div>
          {hint && <div className='mt-1 text-xs opacity-90'>Hinweis: {hint}</div>}
          {detailEntries.length > 0 && (
            <details className='mt-2'>
              <summary className='cursor-pointer text-xs opacity-80'>Details</summary>
              <pre className='mt-1 text-xs whitespace-pre-wrap wrap-break-word bg-black/5 dark:bg-black/30 rounded p-2 max-h-48 overflow-auto'>
                {JSON.stringify(err.details, null, 2)}
              </pre>
            </details>
          )}
        </div>
        <button type='button' onClick={dismissError} className='text-xs px-2 py-1 rounded hover:bg-red-500/20' aria-label='Fehler ausblenden'>
          ×
        </button>
      </div>
    </div>
  );
}
