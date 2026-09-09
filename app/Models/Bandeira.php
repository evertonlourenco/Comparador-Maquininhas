<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;

#[Table('bandeiras')]
#[Fillable(['nome', 'slug', 'logo_path', 'ordem'])]
class Bandeira extends Model
{
    protected function casts(): array
    {
        return [
            'ordem' => 'integer',
        ];
    }

    /** logo_path e caminho relativo no disco 'public' — nunca a URL pronta. */
    protected function logoUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->logo_path ? Storage::disk('public')->url($this->logo_path) : null);
    }

    public function marcas(): BelongsToMany
    {
        return $this->belongsToMany(Marca::class, 'bandeira_marca')
            ->withPivot('grupo_bandeira_id')
            ->withTimestamps();
    }
}
