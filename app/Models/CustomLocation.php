<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A name -> coordinate pairing learned from user corrections, for places
 * too small/obscure for OpenStreetMap/Nominatim to know about (a specific
 * hostel block, a campus building, etc.). The Flutter map picker checks
 * this table before falling back to Nominatim, and saves a new entry
 * whenever a Nominatim search fails but the user manually pins the right
 * spot — so the app "learns" local places as people use it.
 */
class CustomLocation extends Model
{
    protected $fillable = ['name', 'latitude', 'longitude', 'search_count'];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }
}
