<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'parent_id',
        'image',
        'icon',
        'sort_order',
        'active',
        'meta_title',
        'meta_description',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    /**
     * Relacionamento com categoria pai
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /**
     * Relacionamento com categorias filhas
     */
    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    /**
     * Relacionamento com produtos
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Scopes para filtros comuns
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeRootCategories($query)
    {
        return $query->whereNull('parent_id');
    }

    public function scopeWithProductsCount($query)
    {
        return $query->withCount('products');
    }

    /**
     * Accessor para contagem de produtos
     */
    public function getProductsCountAttribute()
    {
        return $this->products()->count();
    }

    /**
     * Accessor para verificar se é categoria raiz
     */
    public function getIsRootAttribute()
    {
        return is_null($this->parent_id);
    }

    /**
     * Accessor para caminho completo da categoria
     */
    public function getFullPathAttribute()
    {
        $path = collect([$this->name]);
        $parent = $this->parent;

        while ($parent) {
            $path->prepend($parent->name);
            $parent = $parent->parent;
        }

        return $path->implode(' > ');
    }
}