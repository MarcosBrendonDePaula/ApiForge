<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2);
            $table->decimal('sale_price', 10, 2)->nullable();
            $table->string('sku')->unique();
            $table->string('brand')->nullable();
            $table->foreignId('category_id')->constrained()->onDelete('cascade');
            $table->integer('stock_quantity')->default(0);
            $table->integer('min_stock')->default(5);
            $table->enum('status', ['active', 'inactive', 'discontinued'])->default('active');
            $table->decimal('weight', 8, 2)->nullable();
            $table->json('dimensions')->nullable();
            $table->json('tags')->nullable();
            $table->boolean('featured')->default(false);
            $table->decimal('rating', 3, 1)->default(0);
            $table->integer('reviews_count')->default(0);
            $table->timestamps();

            // Indexes for filtering and performance
            $table->index(['status', 'featured']);
            $table->index(['category_id', 'status']);
            $table->index(['brand', 'status']);
            $table->index(['price']);
            $table->index(['sale_price']);
            $table->index(['stock_quantity']);
            $table->index(['rating']);
            $table->index(['sku']);
            $table->index(['name']);
            $table->index(['created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};