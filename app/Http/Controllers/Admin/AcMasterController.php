<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AcMasterKind;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveAcMasterRequest;
use App\Support\SortOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * CRUD merk, kapasitas (PK), dan tipe AC dengan satu pola: tabel + modal, aktif/nonaktif, urutan.
 */
class AcMasterController extends Controller
{
    public function index(?AcMasterKind $kind = null): View
    {
        $kind ??= AcMasterKind::BRAND;
        $counts = $kind->hasPrices() ? ['orderItems', 'servicePrices'] : ['orderItems'];

        return view('admin.ac.index', [
            'kind' => $kind,
            'records' => $kind->query()->withCount($counts)->get(),
        ]);
    }

    public function store(SaveAcMasterRequest $request, AcMasterKind $kind): RedirectResponse
    {
        $class = $kind->modelClass();

        $class::query()->create($request->validated() + [
            'is_active' => $request->boolean('is_active'),
            'sort_order' => (int) $class::query()->max('sort_order') + 1,
        ]);

        return $this->back($kind, "{$kind->noun()} ditambahkan.");
    }

    public function update(SaveAcMasterRequest $request, AcMasterKind $kind, int $id): RedirectResponse
    {
        $kind->find($id)->update($request->validated() + ['is_active' => $request->boolean('is_active')]);

        return $this->back($kind, "{$kind->noun()} disimpan.");
    }

    public function toggle(AcMasterKind $kind, int $id): RedirectResponse
    {
        $record = $kind->find($id);
        $record->update(['is_active' => ! $record->is_active]);

        $name = $record->getAttribute($kind->nameColumn());

        return $this->back($kind, $record->is_active ? "{$name} diaktifkan." : "{$name} dinonaktifkan dan tidak bisa dipilih client.");
    }

    public function move(Request $request, AcMasterKind $kind, int $id): RedirectResponse
    {
        $direction = $request->validate(['direction' => ['required', 'in:up,down']])['direction'];

        SortOrder::move($kind->query(), $id, $direction);

        return to_route('admin.ac.index', $kind);
    }

    public function destroy(AcMasterKind $kind, int $id): RedirectResponse
    {
        $record = $kind->find($id);
        $name = $record->getAttribute($kind->nameColumn());

        if ($record->orderItems()->exists()) {
            return to_route('admin.ac.index', $kind)
                ->with('error', "{$name} sudah dipakai di order sehingga tidak bisa dihapus. Nonaktifkan saja agar tidak bisa dipilih lagi.");
        }

        DB::transaction(function () use ($kind, $record) {
            if ($kind->hasPrices()) {
                $record->servicePrices()->delete();
            }
            $record->delete();
        });

        return $this->back($kind, "{$name} dihapus.");
    }

    private function back(AcMasterKind $kind, string $message): RedirectResponse
    {
        return to_route('admin.ac.index', $kind)->with('success', $message);
    }
}
