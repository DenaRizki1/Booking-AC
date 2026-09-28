<?php

namespace App\Http\Requests\Admin;

use App\Enums\AcMasterKind;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Simpan merk / kapasitas / tipe AC (aturan per jenis ada di AcMasterKind::rules()).
 */
class SaveAcMasterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function kind(): AcMasterKind
    {
        $kind = $this->route('kind');

        return $kind instanceof AcMasterKind ? $kind : AcMasterKind::from((string) $kind);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $id = $this->route('id');

        return $this->kind()->rules($id !== null ? $this->kind()->find((string) $id) : null);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => $this->kind() === AcMasterKind::BRAND ? 'nama merk' : 'nama tipe',
            'label' => 'label',
            'pk_value' => 'nilai PK',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => ':Attribute ini sudah ada.',
            'label.unique' => 'Label ini sudah ada.',
            'pk_value.unique' => 'Kapasitas dengan nilai PK ini sudah ada.',
            'pk_value.decimal' => 'Nilai PK maksimal 2 angka desimal (mis. 1.50).',
        ];
    }
}
