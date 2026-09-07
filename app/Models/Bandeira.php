<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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

    public function marcas(): BelongsToMany
    {
        return $this->belongsToMany(Marca::class, 'bandeira_marca')
            ->withPivot('grupo_bandeira_id')
            ->withTimestamps();
    }
}
