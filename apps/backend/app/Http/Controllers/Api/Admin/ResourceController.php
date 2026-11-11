<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BillingController;
use App\Http\Controllers\Controller;
use App\Services\Audit;
use App\Support\AdminResources;
use App\Support\ApiException;
use App\Support\Present;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/** CRUD generik Admin: daftar server-side (cari/filter/sort/pagination/export), buat, ubah, hapus — semua tercatat audit. */
class ResourceController extends Controller
{
    private function row($m, array $def): array
    {
        $a = $m->toArray();
        foreach ($def['secret'] ?? [] as $s) {
            // Secret tidak pernah ditampilkan ulang — hanya status (Design Spec §7.2 butir 9)
            $a[$s.'_set'] = ! empty($m->getRawOriginal($s));
            unset($a[$s]);
        }
        foreach (['user', 'actor', 'referrer', 'referred', 'decider'] as $rel) {
            if (isset($a[$rel]) && is_array($a[$rel])) {
                $a[$rel] = ['id' => $a[$rel]['id'] ?? null, 'name' => $a[$rel]['name'] ?? null, 'email' => isset($a[$rel]['email']) ? Present::mask($a[$rel]['email']) : null];
            }
        }
        if (isset($a['provider']) && is_array($a['provider'])) {
            $a['provider'] = ['id' => $a['provider']['id'], 'name' => $a['provider']['name']];
        }

        return $a;
    }

    public function index(Request $r, string $resource)
    {
        $def = AdminResources::get($resource);
        $q = $def['model']::query()->with($def['with'] ?? []);
        foreach ($def['scope'] ?? [] as $k => $v) {
            $q->where($k, $v);
        }
        if (($s = trim((string) $r->query('search'))) && ! empty($def['search'])) {
            $q->where(function ($w) use ($def, $s) {
                foreach ($def['search'] as $col) {
                    $w->orWhere($col, 'like', "%{$s}%");
                }
            });
        }
        foreach ($def['filters'] ?? [] as $f) {
            $v = $r->query($f);
            if ($v !== null && $v !== '') {
                $q->where($f, in_array($v, ['true', 'false'], true) ? $v === 'true' : $v);
            }
        }
        if ($uid = $r->query('user_id')) {
            $q->where('user_id', (int) $uid);
        }
        $sort = (string) $r->query('sort', '');
        $col = ltrim($sort, '-');
        if ($col && in_array($col, $def['sort'] ?? [], true)) {
            $q->orderBy($col, str_starts_with($sort, '-') ? 'desc' : 'asc');
        } else {
            $q->orderByDesc((new $def['model'])->getKeyName());
        }
        if ($r->query('export') === 'csv') {
            Audit::log('admin.export', null, ['resource' => $resource, 'filters' => $r->except('export')]);
            $rows = $q->limit(10000)->get()->map(fn ($m) => collect($this->row($m, $def))->map(fn ($v) => is_array($v) ? json_encode($v) : $v)->all());
            $head = array_keys($rows->first() ?? []);

            return BillingController::csv("{$resource}.csv", $head, $rows->map(fn ($r) => array_values($r)));
        }

        return Present::paginate($q->paginate(min(100, max(10, (int) $r->query('per_page', 25)))), fn ($m) => $this->row($m, $def));
    }

    public function show(string $resource, string $id)
    {
        $def = AdminResources::get($resource);

        return ['data' => $this->row($def['model']::with($def['with'] ?? [])->findOrFail($id), $def)];
    }

    private function validated(Request $r, array $def, string $resource, $model = null): array
    {
        if (! empty($def['readonly']) || empty($def['rules'])) {
            throw new ApiException('read_only', 'Data ini tidak dapat diubah langsung.', 405);
        }
        $rules = $def['rules'];
        $table = (new $def['model'])->getTable();
        foreach (['code', 'key', 'slug', 'event'] as $uniq) {
            if (isset($rules[$uniq])) {
                $rules[$uniq] .= '|'.Rule::unique($table, $uniq)->ignore($model?->getKey(), $model?->getKeyName() ?? 'id');
            }
        }
        if ($model) {
            foreach ($rules as $k => $v) {
                $rules[$k] = is_string($v) ? str_replace('required|', 'sometimes|required|', $v) : $v;
            }
        }

        return Validator::make($r->all(), $rules)->validate();
    }

    public function store(Request $r, string $resource)
    {
        $def = AdminResources::get($resource);
        $data = $this->validated($r, $def, $resource);
        foreach ($def['secret'] ?? [] as $s) {
            if ($r->filled($s)) {
                $data[$s] = (string) $r->input($s);
                $data['key_rotated_at'] = now();
            }
        }
        $m = $def['model']::create($data + ($resource === 'templates' ? ['created_by' => $r->user()->id] : []));
        Audit::log("admin.{$resource}.created", $m);

        return response()->json(['data' => $this->row($m, $def)], 201);
    }

    public function update(Request $r, string $resource, string $id)
    {
        $def = AdminResources::get($resource);
        $m = $def['model']::findOrFail($id);
        $data = $this->validated($r, $def, $resource, $m);
        $before = collect($m->only(array_keys($data)))->all();
        $m->update($data);
        Audit::log("admin.{$resource}.updated", $m, ['changed' => array_keys(array_diff_assoc(array_map('json_encode', $data), array_map('json_encode', $before)))]);

        return ['data' => $this->row($m->fresh(), $def)];
    }

    public function destroy(Request $r, string $resource, string $id)
    {
        $def = AdminResources::get($resource);
        if (! empty($def['readonly'])) {
            throw new ApiException('read_only', 'Data ini tidak dapat dihapus.', 405);
        }
        $m = $def['model']::findOrFail($id);
        $m->delete();
        Audit::log("admin.{$resource}.deleted", $m);

        return response()->noContent();
    }

    public function bulk(Request $r, string $resource)
    {
        $def = AdminResources::get($resource);
        $data = $r->validate(['ids' => 'required|array|max:500', 'action' => 'required|in:delete,activate,deactivate', 'confirm' => 'nullable|string']);
        if (! empty($def['readonly'])) {
            throw new ApiException('read_only', 'Data ini tidak dapat diubah.', 405);
        }
        if ($data['action'] === 'delete' && ($data['confirm'] ?? '') !== 'HAPUS') {
            throw new ApiException('confirm_required', 'Ketik HAPUS untuk melanjutkan.', 422);
        }
        $q = $def['model']::whereIn((new $def['model'])->getKeyName(), $data['ids']);
        $field = isset($def['rules']['is_active']) ? 'is_active' : (isset($def['rules']['enabled']) ? 'enabled' : null);
        match ($data['action']) {
            'delete' => $q->get()->each->delete(),
            'activate' => $field ? $q->update([$field => true]) : null,
            'deactivate' => $field ? $q->update([$field => false]) : null,
        };
        Audit::log("admin.{$resource}.bulk_{$data['action']}", null, ['count' => count($data['ids'])]);

        return response()->noContent();
    }
}
