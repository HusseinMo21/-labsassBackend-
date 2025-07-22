<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditLog extends Model
{
    use HasFactory;

    protected $table = 'audit_logs';

    protected $fillable = [
        'user_id',
        'action',
        'model_type',
        'model_id',
        'table_name',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'description',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function log($action, $model = null, $description = null, $oldValues = null, $newValues = null)
    {
        $user = Auth::user();
        
        return self::create([
            'user_id' => $user ? $user->id : null,
            'action' => $action,
            'model_type' => $model ? get_class($model) : null,
            'model_id' => $model ? $model->id : null,
            'table_name' => $model ? $model->getTable() : null,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'description' => $description,
        ]);
    }

    public static function logCreate($model, $description = null)
    {
        return self::log('create', $model, $description, null, $model->getAttributes());
    }

    public static function logUpdate($model, $oldValues, $newValues, $description = null)
    {
        return self::log('update', $model, $description, $oldValues, $newValues);
    }

    public static function logDelete($model, $description = null)
    {
        return self::log('delete', $model, $description, $model->getAttributes(), null);
    }

    public static function logView($model, $description = null)
    {
        return self::log('view', $model, $description);
    }

    public static function logLogin($user)
    {
        return self::log('login', null, "User {$user->name} logged in");
    }

    public static function logLogout($user)
    {
        return self::log('logout', null, "User {$user->name} logged out");
    }

    public function getActionDescription()
    {
        $modelName = class_basename($this->model_type);
        
        switch ($this->action) {
            case 'create':
                return "Created {$modelName} #{$this->model_id}";
            case 'update':
                return "Updated {$modelName} #{$this->model_id}";
            case 'delete':
                return "Deleted {$modelName} #{$this->model_id}";
            case 'view':
                return "Viewed {$modelName} #{$this->model_id}";
            case 'login':
                return "User logged in";
            case 'logout':
                return "User logged out";
            default:
                return ucfirst($this->action);
        }
    }
} 