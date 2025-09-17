<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Check if expenses table exists and fix the schema
        if (Schema::hasTable('expenses')) {
            $columns = Schema::getColumnListing('expenses');
            
            // Drop existing columns that don't match the model (only if they exist)
            $columnsToDrop = [];
            if (in_array('description', $columns)) $columnsToDrop[] = 'description';
            if (in_array('expense_date', $columns)) $columnsToDrop[] = 'expense_date';
            if (in_array('created_by', $columns)) $columnsToDrop[] = 'created_by';
            if (in_array('category', $columns)) $columnsToDrop[] = 'category';
            if (in_array('payment_method', $columns)) $columnsToDrop[] = 'payment_method';
            if (in_array('reference_number', $columns)) $columnsToDrop[] = 'reference_number';
            if (in_array('notes', $columns)) $columnsToDrop[] = 'notes';
            
            if (!empty($columnsToDrop)) {
                Schema::table('expenses', function (Blueprint $table) use ($columnsToDrop) {
                    $table->dropColumn($columnsToDrop);
                });
            }
            
            // Add the correct columns that match the model (only if they don't exist)
            Schema::table('expenses', function (Blueprint $table) use ($columns) {
                if (!in_array('name', $columns)) {
                    $table->string('name')->after('id');
                }
                if (!in_array('amount', $columns)) {
                    $table->decimal('amount', 10, 2)->after('name');
                }
                if (!in_array('date', $columns)) {
                    $table->date('date')->after('amount');
                }
                if (!in_array('author', $columns)) {
                    $table->foreignId('author')->constrained('users')->onDelete('cascade')->after('date');
                }
            });
        } else {
            // Create the expenses table with the correct schema
            Schema::create('expenses', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->decimal('amount', 10, 2);
                $table->date('date');
                $table->foreignId('author')->constrained('users')->onDelete('cascade');
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the expenses table
        Schema::dropIfExists('expenses');
    }
};
