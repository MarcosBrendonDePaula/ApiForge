<?php

namespace MarcosBrendon\ApiForge\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

class TestModel extends Model
{
    protected $table = 'test_models';

    protected $fillable = [
        'name',
        'email',
        'slug',
        'active',
        'category_id'
    ];

    protected $casts = [
        'active' => 'boolean'
    ];

    /**
     * Get the model's attributes as an array for testing
     */
    public function getAttributes(): array
    {
        return parent::getAttributes();
    }

    /**
     * Get the original attributes for testing
     */
    public function getOriginal($key = null, $default = null)
    {
        return parent::getOriginal($key, $default);
    }

    /**
     * Get dirty attributes for testing
     */
    public function getDirty(): array
    {
        return parent::getDirty();
    }

    /**
     * Check if attribute was changed for testing
     */
    public function wasChanged($attributes = null): bool
    {
        return parent::wasChanged($attributes);
    }

    /**
     * Check if attribute is dirty for testing
     */
    public function isDirty($attributes = null): bool
    {
        return parent::isDirty($attributes);
    }

    /**
     * Mock relationship for testing
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Mock relationship for testing
     */
    public function category()
    {
        return $this->belongsTo(TestCategory::class, 'category_id');
    }

    /**
     * Mock relationship for testing
     */
    public function orderItems()
    {
        return $this->hasMany(TestOrderItem::class, 'product_id');
    }

    /**
     * Mock relationship for testing
     */
    public function inventory()
    {
        return $this->hasOne(TestInventory::class, 'product_id');
    }

    /**
     * Mock relationship for testing
     */
    public function images()
    {
        return $this->hasMany(TestImage::class, 'product_id');
    }

    /**
     * Mock relationship for testing
     */
    public function priceHistory()
    {
        return $this->hasMany(TestPriceHistory::class, 'product_id');
    }
}

/**
 * Mock classes for relationships
 */
class TestCategory extends Model
{
    protected $fillable = ['name', 'max_products', 'products_count'];
}

class TestOrderItem extends Model
{
    protected $fillable = ['product_id', 'order_id', 'quantity'];
    
    public function order()
    {
        return $this->belongsTo(TestOrder::class);
    }
}

class TestOrder extends Model
{
    protected $fillable = ['status'];
}

class TestInventory extends Model
{
    protected $fillable = ['product_id', 'quantity', 'reserved_quantity', 'location'];
}

class TestImage extends Model
{
    protected $fillable = ['product_id', 'url', 'alt_text', 'sort_order'];
}

class TestPriceHistory extends Model
{
    protected $fillable = ['product_id', 'old_price', 'new_price', 'changed_by', 'reason'];
}