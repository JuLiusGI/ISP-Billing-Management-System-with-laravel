<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', AuditLog::class);

        $logs = AuditLog::query()
            ->with('user')
            ->when($request->filled('module'), fn (Builder $q) => $q->module($request->string('module')->toString()))
            ->when($request->filled('action'), fn (Builder $q) => $q->action($request->string('action')->toString()))
            ->when($request->filled('user'), fn (Builder $q) => $q->where('user_id', $request->integer('user')))
            ->when($request->filled('search'), function (Builder $query) use ($request): void {
                $term = '%'.$request->string('search').'%';

                $query->where(function (Builder $q) use ($term): void {
                    $q->where('description', 'like', $term)
                        ->orWhere('ip_address', 'like', $term);
                });
            })
            ->when($request->filled('from'), fn (Builder $q) => $q->whereDate('created_at', '>=', $request->string('from')))
            ->when($request->filled('to'), fn (Builder $q) => $q->whereDate('created_at', '<=', $request->string('to')))
            ->latest('created_at')
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

        return view('audit-logs.index', [
            'logs' => $logs,
            // Drawn from the data rather than a hard-coded list, so a module or
            // action added later appears in the filters without a code change.
            'modules' => AuditLog::query()->distinct()->orderBy('module')->pluck('module'),
            'actions' => AuditLog::query()->distinct()->orderBy('action')->pluck('action'),
            'users' => User::whereIn('id', AuditLog::query()->distinct()->pluck('user_id')->filter())
                ->orderBy('last_name')
                ->get(),
        ]);
    }

    public function show(AuditLog $auditLog): View
    {
        $this->authorize('view', $auditLog);

        return view('audit-logs.show', [
            'log' => $auditLog->load('user'),
            'changes' => $this->changes($auditLog),
        ]);
    }

    /**
     * Pairs old and new values per field for the detail view.
     *
     * Built from the union of both sides so a field that only appears in one
     * of them — a value first set, or one cleared — is still shown.
     *
     * @return array<int, array{field: string, old: mixed, new: mixed}>
     */
    private function changes(AuditLog $log): array
    {
        $old = $log->old_values ?? [];
        $new = $log->new_values ?? [];

        $fields = array_unique(array_merge(array_keys($old), array_keys($new)));
        sort($fields);

        return array_map(fn (string $field) => [
            'field' => $field,
            'old' => $old[$field] ?? null,
            'new' => $new[$field] ?? null,
        ], $fields);
    }
}
