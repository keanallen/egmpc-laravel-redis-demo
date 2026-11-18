<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employee extends Model
{
    protected $fillable = ['branch_id', 'employee_number', 'name'];
    protected function casts(): array { return ['branch_id' => 'integer']; }
    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function attendanceRecords(): HasMany { return $this->hasMany(AttendanceRecord::class); }
}
