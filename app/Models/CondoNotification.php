<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CondoNotification extends Model
{
    use HasFactory;

    protected $table = 'condo_notifications';

    protected $fillable = [
        'departamento',
        'tipo',
        'titulo',
        'detalle',
        'leida',
    ];

    protected function casts(): array
    {
        return [
            'leida' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
